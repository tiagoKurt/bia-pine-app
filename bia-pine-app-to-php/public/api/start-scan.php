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
    
    // NOTA: O disparo do worker agora é feito via cron/supervisor
    // O arquivo scan_status.json foi criado com status 'pending'
    // O worker (bin/run_scanner.php) deve ser executado via cron job
    // Exemplo de cron job: * * * * * /usr/bin/php /caminho/para/bin/run_scanner.php >> /caminho/para/logs/cron_run.log 2>&1
    
    error_log("Arquivo de status criado com status 'pending'. Worker deve ser executado via cron/supervisor.");
    
    echo json_encode([
        'success' => true,
        'message' => 'Análise CKAN iniciada com sucesso. Worker será executado via cron/supervisor.',
        'status' => 'pending',
        'note' => 'O worker será executado automaticamente via cron job em até 1 minuto.'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao iniciar análise: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
