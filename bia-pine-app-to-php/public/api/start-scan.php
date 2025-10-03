<?php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Worker\CkanScannerService;
use App\Cpf\CpfVerificationService;

// Headers para API
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Trata requisições OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Verifica se é uma requisição POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Método não permitido. Use POST.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Verifica se já existe uma análise em andamento
    $cacheDir = __DIR__ . '/../../cache';
    $lockFile = $cacheDir . '/scan_status.json';
    
    // Cria diretório de cache se não existir
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    
    // Verifica se há análise em andamento
    if (file_exists($lockFile)) {
        $lockContent = file_get_contents($lockFile);
        $lockData = json_decode($lockContent, true);
        
        if ($lockData && isset($lockData['status'])) {
            $status = $lockData['status'];
            
            // Se está rodando ou pendente, não permite nova análise
            if (in_array($status, ['running', 'pending'])) {
                $nextScanAllowed = 'N/A';
                if (isset($lockData['startTime'])) {
                    $startTime = new DateTime($lockData['startTime']);
                    $nextScanTime = $startTime->modify('+1 hour');
                    $nextScanAllowed = $nextScanTime->format('d/m/Y H:i:s');
                }
                
                echo json_encode([
                    'success' => false,
                    'cooldownActive' => true,
                    'message' => 'Já existe uma análise em andamento',
                    'nextScanAllowed' => $nextScanAllowed
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    }

    // Verifica se é para forçar nova análise
    $force = isset($_POST['force']) && $_POST['force'] === 'true';
    
    if ($force && file_exists($lockFile)) {
        unlink($lockFile);
    }

    // Conecta ao banco de dados
    $pdo = conectarBanco();
    
    // Configurações do CKAN
    $ckanUrl = CKAN_API_URL;
    $ckanApiKey = CKAN_API_KEY;
    
    // Cria o serviço de scanner
    $scannerService = new CkanScannerService($ckanUrl, $ckanApiKey, $cacheDir, $pdo);
    
    // Define callback de progresso
    $scannerService->setProgressCallback(function($data) use ($lockFile) {
        $statusData = [
            'status' => 'running',
            'startTime' => date('c'),
            'progress' => $data,
            'lastUpdate' => date('c')
        ];
        file_put_contents($lockFile, json_encode($statusData, JSON_PRETTY_PRINT));
    });
    
    // Cria arquivo de status inicial
    $initialStatus = [
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
    
    file_put_contents($lockFile, json_encode($initialStatus, JSON_PRETTY_PRINT));
    
    // Executa o scanner diretamente em background usando popen
    $scriptPath = __DIR__ . '/../../bin/run_scanner.php';
    
    // Cria o comando
    $command = "php " . escapeshellarg($scriptPath);
    if ($force) {
        $command .= " --force";
    }
    
    // Para Windows, usa start /B para executar em background
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $command = "start /B " . $command . " > NUL 2>&1";
    } else {
        $command .= " > /dev/null 2>&1 &";
    }
    
    // Executa o comando em background
    $handle = popen($command, 'r');
    if ($handle) {
        pclose($handle);
        error_log("Scanner iniciado com sucesso via popen");
    } else {
        // Fallback para exec se popen falhar
        exec($command);
        error_log("Scanner iniciado via exec (fallback)");
    }
    
    // Aguarda um momento para garantir que o processo iniciou
    usleep(1000000); // 1 segundo
    
    // Verifica se o status foi atualizado para 'running'
    $maxAttempts = 10;
    $attempt = 0;
    $statusUpdated = false;
    
    while ($attempt < $maxAttempts && !$statusUpdated) {
        usleep(200000); // 0.2 segundos
        if (file_exists($lockFile)) {
            $lockContent = file_get_contents($lockFile);
            $lockData = json_decode($lockContent, true);
            if ($lockData && isset($lockData['status']) && $lockData['status'] === 'running') {
                $statusUpdated = true;
            }
        }
        $attempt++;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Análise CKAN iniciada com sucesso',
        'status' => 'started',
        'status_updated' => $statusUpdated
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao iniciar análise: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
