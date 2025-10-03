<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';

use App\Worker\CkanScannerService;
use Dotenv\Dotenv;

// --- Configuração ---
$cacheDir = __DIR__ . '/../cache';
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
    
    // Log das configurações
    echo "CKAN_API_URL: " . (defined('CKAN_API_URL') ? CKAN_API_URL : 'NÃO DEFINIDO') . "\n";
    echo "CKAN_API_KEY: " . (defined('CKAN_API_KEY') ? (CKAN_API_KEY ? 'DEFINIDO' : 'VAZIO') : 'NÃO DEFINIDO') . "\n";
    
    if (file_exists(__DIR__ . '/../.env')) {
        echo "Carregando arquivo .env\n";
        $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->load();
    } else {
        echo "Arquivo .env não encontrado, usando configurações do config.php\n";
    }

    if (!file_exists($actualLockFile)) {
        if ($forceAnalysis) {
            echo "Forçando análise - criando novo arquivo de status...\n";
            if ($lockFp) {
                fclose($lockFp);
            }
            
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
            @file_put_contents($actualLockFile, json_encode($lockData, JSON_PRETTY_PRINT));
            
            $lockFp = @fopen($actualLockFile, 'c+');
            if (!$lockFp) {
                echo "Erro ao reabrir arquivo de status: $actualLockFile\n";
                exit(1);
            }
        } else {
            echo "Arquivo de status não encontrado: $actualLockFile\n";
            if ($lockFp) {
                fclose($lockFp);
            }
            exit;
        }
    }
    
    echo "Arquivo de status encontrado: $actualLockFile\n";

    $lockContent = '';
    if ($lockFp) {
        rewind($lockFp);
        while (!feof($lockFp)) {
            $lockContent .= fread($lockFp, 8192);
        }
    } else {
        $lockContent = @file_get_contents($actualLockFile);
    }
    
    if (empty(trim($lockContent))) {
        if ($lockFp) {
            fclose($lockFp);
        }
        if ($actualLockFile) {
            @unlink($actualLockFile);
        }
        exit;
    }

    $status = json_decode($lockContent, true);
    if (!$status) {
        if ($lockFp) {
            fclose($lockFp);
        }
        if ($actualLockFile) {
            @unlink($actualLockFile);
        }
        exit;
    }

    if ($status['status'] === 'completed' || $status['status'] === 'failed') {
        if (file_exists($queueFile)) unlink($queueFile);
        if ($lockFp) {
            fclose($lockFp);
        }
        if ($actualLockFile) {
            @unlink($actualLockFile);
        }
        exit;
    }

    $pdo = conectarBanco();
    $scanner = new CkanScannerService(
        CKAN_API_URL,
        CKAN_API_KEY,
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