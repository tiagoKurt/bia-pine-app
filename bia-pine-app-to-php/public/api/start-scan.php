<?php

require_once __DIR__ . '/../../config.php';
// Garantir que o autoloader esteja disponível
ensureAutoloader();

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
    
    // Verifica se é para forçar nova análise
    $force = isset($_POST['force']) && $_POST['force'] == '1';
    
    // Verifica se há análise em andamento
    if (file_exists($lockFile)) {
        $lockContent = file_get_contents($lockFile);
        $lockData = json_decode($lockContent, true);
        
        if ($lockData && isset($lockData['status'])) {
            $status = $lockData['status'];
            
            // Se está rodando ou pendente, e NÃO for para forçar, retorna erro 409 (Conflict)
            if (in_array($status, ['running', 'pending']) && !$force) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Uma análise já está em execução. Cancele-a antes de iniciar uma nova.'
                ], JSON_UNESCAPED_UNICODE);
                http_response_code(409);
                exit;
            }
            
            // 1. SE EXISTE, FORÇA O CANCELAMENTO DO SCAN ANTERIOR
            if (in_array($status, ['running', 'pending']) && $force) {
                // Altera o status para 'cancelling' para que o worker atual saia do loop
                $lockData['status'] = 'cancelling';
                $lockData['message'] = 'Scan anterior marcado para cancelamento. Reiniciando...';
                file_put_contents($lockFile, json_encode($lockData, JSON_PRETTY_PRINT));
                
                // Delay para o worker antigo ter tempo de perceber o cancelamento e sair.
                usleep(100000); 
            }
        }
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
    
    // 3. EXECUTA O SCANNER DE FORMA SIMPLES E DIRETA
    $scriptPath = __DIR__ . '/../../bin/run_scanner_real.php';
    $command = "php " . escapeshellarg($scriptPath);
    if ($force) {
        $command .= " --force";
    }

    // Log do comando que será executado
    error_log("Executando comando: " . $command);

    // Método mais simples e direto
    $output = [];
    $returnCode = 0;
    
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Windows: usa start /B para não travar o processo
        $command = "start /B " . $command . " > NUL 2>&1";
        exec($command, $output, $returnCode);
        error_log("Comando Windows executado. Código de retorno: " . $returnCode);
    } else {
        // Linux/Unix: usa '&' para rodar em background e redireciona output
        $command .= " > /dev/null 2>&1 &";
        exec($command, $output, $returnCode);
        error_log("Comando Unix executado. Código de retorno: " . $returnCode);
    }

    // Aguarda um pouco para o processo iniciar
    sleep(2);

    // Verifica se o worker realmente iniciou
    $workerStarted = false;
    if (file_exists($lockFile)) {
        $statusContent = file_get_contents($lockFile);
        $statusData = json_decode($statusContent, true);
        
        if ($statusData && isset($statusData['status'])) {
            if ($statusData['status'] === 'running') {
                $workerStarted = true;
                error_log("Worker iniciado com sucesso. Status: running");
            } else {
                error_log("Worker não mudou para 'running'. Status atual: " . $statusData['status']);
            }
        }
    }

    if (!$workerStarted) {
        error_log("AVISO: Worker pode não ter iniciado corretamente");
    }

    // Resposta final ao front-end
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
