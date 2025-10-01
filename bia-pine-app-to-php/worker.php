<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

use App\CkanScannerService;
use Dotenv\Dotenv;

// --- Configuração ---
$cacheDir = __DIR__ . '/cache';
$queueFile = $cacheDir . '/scan_queue.json';

// Estratégia simplificada: usa apenas um arquivo de lock fixo
$actualLockFile = $cacheDir . '/scan_status.json';

// Função para criar/abrir arquivo de lock de forma robusta
function createLockFile($lockFile) {
    // Tenta criar o arquivo se não existir
    if (!file_exists($lockFile)) {
        $dir = dirname($lockFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents($lockFile, '{}');
    }
    
    // Tenta abrir o arquivo
    $lockFp = @fopen($lockFile, 'c+');
    if ($lockFp) {
        return [$lockFp, $lockFile];
    }
    
    return [false, null];
}

[$lockFp, $actualLockFile] = createLockFile($actualLockFile);
if (!$lockFp) {
    echo "Erro ao abrir arquivo de lock. Tentando continuar sem lock...\n";
    $actualLockFile = null;
}

$forceAnalysis = (isset($argv[1]) && $argv[1] === '--force') || 
                 (isset($GLOBALS['FORCE_ANALYSIS']) && $GLOBALS['FORCE_ANALYSIS']);

// Verifica se o arquivo tem um timestamp recente (menos de 5 minutos)
if ($actualLockFile && file_exists($actualLockFile) && !$forceAnalysis) {
    $fileTime = filemtime($actualLockFile);
    $currentTime = time();
    if (($currentTime - $fileTime) < 300) { // 5 minutos
        echo "Outro processo de análise pode estar em execução (arquivo recente).\n";
        if ($lockFp) fclose($lockFp);
        exit;
    }
}

if ($forceAnalysis && $actualLockFile && file_exists($actualLockFile)) {
    echo "Forçando nova análise - removendo status anterior...\n";
    @unlink($actualLockFile);
}

try {
    echo "Worker iniciado em: " . date('Y-m-d H:i:s') . "\n";
    echo "Diretório atual: " . __DIR__ . "\n";
    echo "Arquivo de status: " . ($actualLockFile ?: 'Nenhum (modo sem status)') . "\n";
    
    if (file_exists(__DIR__ . '/.env')) {
        echo "Carregando arquivo .env\n";
        $dotenv = Dotenv::createImmutable(__DIR__);
        $dotenv->load();
    } else {
        echo "Arquivo .env não encontrado, usando configurações padrão\n";
        $_ENV['CKAN_API_URL'] = 'https://dadosabertos.go.gov.br';
        $_ENV['CKAN_API_KEY'] = '';
    }

    if (!file_exists($lockFile)) {
        if ($forceAnalysis) {
            echo "Forçando análise - criando novo arquivo de lock...\n";
            fclose($lockFp);
            
            $lockData = [
                'status' => 'pending',
                'startTime' => date('c'),
                'progress' => [
                    'datasets_analisados' => 0,
                    'recursos_analisados' => 0,
                    'recursos_com_cpfs' => 0,
                    'total_cpfs_salvos' => 0,
                    'current_step' => 'Iniciando análise...'
                ],
                'lastUpdate' => date('c')
            ];
            file_put_contents($lockFile, json_encode($lockData, JSON_PRETTY_PRINT));
            
            $lockFp = fopen($lockFile, 'c+');
            if (!$lockFp) {
                echo "Erro ao reabrir arquivo de lock: $lockFile\n";
                exit(1);
            }
        } else {
            echo "Arquivo de lock não encontrado: $lockFile\n";
            fclose($lockFp);
            exit;
        }
    }
    
    echo "Arquivo de lock encontrado: $lockFile\n";

    $lockContent = '';
    rewind($lockFp);
    while (!feof($lockFp)) {
        $lockContent .= fread($lockFp, 8192);
    }
    
    if (empty(trim($lockContent))) {
        fclose($lockFp);
        unlink($lockFile);
        exit;
    }

    $status = json_decode($lockContent, true);
    if (!$status) {
        fclose($lockFp);
        unlink($lockFile);
        exit;
    }

    if ($status['status'] === 'completed' || $status['status'] === 'failed') {
        if (file_exists($queueFile)) unlink($queueFile);
        fclose($lockFp);
        unlink($lockFile);
        exit;
    }

    $pdo = conectarBanco();
    $scanner = new CkanScannerService(
        $_ENV['CKAN_API_URL'] ?? 'https://dadosabertos.go.gov.br',
        $_ENV['CKAN_API_KEY'] ?? '',
        $cacheDir . '/ckan_api',
        $pdo
    );

    $scanner->setProgressCallback(function($progress) use ($actualLockFile) {
        if (!$actualLockFile) {
            echo "Progresso: " . json_encode($progress) . "\n";
            return;
        }
        
        $currentLock = [];
        if (file_exists($actualLockFile)) {
            $content = @file_get_contents($actualLockFile);
            if ($content !== false && !empty(trim($content))) {
                $data = json_decode($content, true);
                if ($data) {
                    $currentLock = $data;
                }
            }
        }
        $currentLock['progress'] = array_merge($currentLock['progress'] ?? [], $progress);
        $currentLock['lastUpdate'] = date('c');
        @file_put_contents($actualLockFile, json_encode($currentLock, JSON_PRETTY_PRINT));
    });
    
    // Atualiza o status para 'running' imediatamente
    $status['status'] = 'running';
    $status['lastUpdate'] = date('c');
    if ($actualLockFile) {
        @file_put_contents($actualLockFile, json_encode($status, JSON_PRETTY_PRINT));
    }
    
    $maxIterations = 3000; // Limite de segurança para evitar loop infinito
    $iteration = 0;
    
    while ($iteration < $maxIterations) {
        $iteration++;
        echo "Iteração $iteration - Processando lote...\n";
        
        $result = $scanner->executarAnaliseControlada($actualLockFile, $queueFile);
        
        if ($result['status'] === 'completed') {
            $finalStatus = [];
            if ($actualLockFile && file_exists($actualLockFile)) {
                $content = @file_get_contents($actualLockFile);
                if ($content !== false && !empty(trim($content))) {
                    $data = json_decode($content, true);
                    if ($data) {
                        $finalStatus = $data;
                    }
                }
            }
            $finalStatus['status'] = 'completed';
            $finalStatus['endTime'] = date('c');
            $finalStatus['message'] = $result['message'];
            if ($actualLockFile) {
                @file_put_contents($actualLockFile, json_encode($finalStatus, JSON_PRETTY_PRINT));
            }
            echo "Análise concluída com sucesso!\n";
            break;
        }
        
        if ($result['status'] === 'running') {
            echo "Lote processado. Continuando...\n";
            usleep(100000); // 0.1 segundo
            continue;
        }
        
        if ($result['status'] === 'failed') {
            echo "Erro na análise: " . ($result['message'] ?? 'Erro desconhecido') . "\n";
            break;
        }
    }
    
    if ($iteration >= $maxIterations) {
        echo "Limite de iterações atingido. Análise pode estar incompleta.\n";
    }

} catch (Exception $e) {
    $errorStatus = [];
    if ($actualLockFile && file_exists($actualLockFile)) {
        $content = @file_get_contents($actualLockFile);
        if ($content !== false && !empty(trim($content))) {
            $data = json_decode($content, true);
            if ($data) {
                $errorStatus = $data;
            }
        }
    }
    $errorStatus['status'] = 'failed';
    $errorStatus['error'] = $e->getMessage();
    $errorStatus['endTime'] = date('c');
    if ($actualLockFile) {
        @file_put_contents($actualLockFile, json_encode($errorStatus, JSON_PRETTY_PRINT));
    }
    echo "Erro: " . $e->getMessage() . "\n";
    
} finally {
    if ($lockFp) {
        fclose($lockFp);
    }
}