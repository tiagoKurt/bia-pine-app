<?php

require_once __DIR__ . '/../../config.php';
ensureAutoloader();

use App\Worker\CkanScannerService;
use App\Cpf\CpfVerificationService;
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Método não permitido. Use POST.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $cacheDir = __DIR__ . '/../../cache';
    $lockFile = $cacheDir . '/scan_status.json';
    
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    
    $force = isset($_POST['force']) && $_POST['force'] == '1';
    if (file_exists($lockFile)) {
        $lockContent = file_get_contents($lockFile);
        $lockData = json_decode($lockContent, true);
        
        if ($lockData && isset($lockData['status'])) {
            $status = $lockData['status'];
            
            if (in_array($status, ['running', 'pending']) && !$force) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Uma análise já está em execução. Cancele-a antes de iniciar uma nova.'
                ], JSON_UNESCAPED_UNICODE);
                http_response_code(409);
                exit;
            }
            
            if (in_array($status, ['running', 'pending']) && $force) {
                $lockData['status'] = 'cancelling';
                $lockData['message'] = 'Scan anterior marcado para cancelamento. Reiniciando...';
                file_put_contents($lockFile, json_encode($lockData, JSON_PRETTY_PRINT));
                
                usleep(100000); 
            }
        }
    }

    $pdo = conectarBanco();
    
    $ckanUrl = CKAN_API_URL;
    $ckanApiKey = CKAN_API_KEY;
    
    $scannerService = new CkanScannerService($ckanUrl, $ckanApiKey, $cacheDir, $pdo);
    
    $scannerService->setProgressCallback(function($data) use ($lockFile) {
        $statusData = [
            'status' => 'running',
            'startTime' => date('c'),
            'progress' => $data,
            'lastUpdate' => date('c')
        ];
        file_put_contents($lockFile, json_encode($statusData, JSON_PRETTY_PRINT));
    });
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
    
    $scriptPath = __DIR__ . '/../../bin/run_scanner_real.php';
    $command = "php " . escapeshellarg($scriptPath);
    if ($force) {
        $command .= " --force";
    }

    $output = [];
    $returnCode = 0;
    
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $command = "start /B " . $command . " > NUL 2>&1";
        exec($command, $output, $returnCode);
    } else {
        $command .= " > /dev/null 2>&1 &";
        exec($command, $output, $returnCode);
    }

    sleep(2);

    $workerStarted = false;
    if (file_exists($lockFile)) {
        $statusContent = file_get_contents($lockFile);
        $statusData = json_decode($statusContent, true);
        
        if ($statusData && isset($statusData['status'])) {
            if ($statusData['status'] === 'running') {
                $workerStarted = true;
            }
        }
    }
    echo json_encode([
        'success' => true,
        'message' => $force ? 'Análise reiniciada com sucesso.' : 'Análise iniciada com sucesso. Processamento em segundo plano.',
        'status' => 'started',
        'worker_started' => $workerStarted
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao iniciar análise: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

