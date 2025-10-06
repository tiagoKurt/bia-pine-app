<?php

require_once __DIR__ . '/../../config.php';

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

    // Configuração de cache
    $cacheDir = __DIR__ . '/../../cache';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    
    $lockFile = $cacheDir . '/scan_status.json';
    $workerLogFile = $cacheDir . '/worker_execution.log';
    
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
                    'message' => 'Uma análise já está em execução. Cancele-a antes de iniciar uma nova.',
                    'current_status' => $status
                ], JSON_UNESCAPED_UNICODE);
                http_response_code(409);
                exit;
            }
            
            // Se for para forçar, cancela a análise anterior
            if (in_array($status, ['running', 'pending']) && $force) {
                $lockData['status'] = 'cancelling';
                $lockData['message'] = 'Scan anterior marcado para cancelamento. Reiniciando...';
                $lockData['lastUpdate'] = date('c');
                file_put_contents($lockFile, json_encode($lockData, JSON_PRETTY_PRINT));
                
                // Aguarda um pouco para o worker anterior sair
                sleep(2);
            }
        }
    }

    // Conecta ao banco de dados
    $pdo = conectarBanco();
    
    // Verifica se as tabelas necessárias existem
    $requiredTables = ['mpda_recursos_com_cpf'];
    $missingTables = [];
    
    foreach ($requiredTables as $table) {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if ($stmt->rowCount() === 0) {
            $missingTables[] = $table;
        }
    }
    
    if (!empty($missingTables)) {
        echo json_encode([
            'success' => false,
            'message' => 'Tabelas do banco de dados não encontradas: ' . implode(', ', $missingTables) . '. Execute o script de criação de tabelas primeiro.',
            'missing_tables' => $missingTables
        ], JSON_UNESCAPED_UNICODE);
        http_response_code(500);
        exit;
    }

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
        'lastUpdate' => date('c'),
        'message' => 'Análise aguardando execução...'
    ];
    
    file_put_contents($lockFile, json_encode($initialStatus, JSON_PRETTY_PRINT));
    
    // Log de início
    $logMessage = "[" . date('Y-m-d H:i:s') . "] Iniciando análise CKAN - Force: " . ($force ? 'Sim' : 'Não') . "\n";
    file_put_contents($workerLogFile, $logMessage, FILE_APPEND | LOCK_EX);
    
    // Executa o worker de forma mais robusta
    $scriptPath = __DIR__ . '/../../bin/run_scanner.php';
    
    if (!file_exists($scriptPath)) {
        throw new Exception("Script do worker não encontrado: $scriptPath");
    }
    
    $command = "php " . escapeshellarg($scriptPath);
    if ($force) {
        $command .= " --force";
    }
    
    // Log do comando
    $logMessage = "[" . date('Y-m-d H:i:s') . "] Executando comando: $command\n";
    file_put_contents($workerLogFile, $logMessage, FILE_APPEND | LOCK_EX);
    
    // Executa o comando de forma assíncrona
    $output = [];
    $returnCode = 0;
    
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Windows: usa start /B para não travar o processo
        $command = "start /B " . $command . " >> " . escapeshellarg($workerLogFile) . " 2>&1";
        exec($command, $output, $returnCode);
    } else {
        // Linux/Unix: usa '&' para rodar em background
        $command .= " >> " . escapeshellarg($workerLogFile) . " 2>&1 &";
        exec($command, $output, $returnCode);
    }
    
    // Aguarda um pouco para o processo iniciar
    sleep(3);
    
    // Verifica se o worker realmente iniciou
    $workerStarted = false;
    $maxAttempts = 10;
    $attempt = 0;
    
    while ($attempt < $maxAttempts) {
        if (file_exists($lockFile)) {
            $statusContent = file_get_contents($lockFile);
            $statusData = json_decode($statusContent, true);
            
            if ($statusData && isset($statusData['status'])) {
                if (in_array($statusData['status'], ['running', 'pending'])) {
                    $workerStarted = true;
                    break;
                }
            }
        }
        
        $attempt++;
        sleep(1);
    }
    
    // Log do resultado
    $logMessage = "[" . date('Y-m-d H:i:s') . "] Worker iniciado: " . ($workerStarted ? 'Sim' : 'Não') . " (Tentativa $attempt/$maxAttempts)\n";
    file_put_contents($workerLogFile, $logMessage, FILE_APPEND | LOCK_EX);
    
    if (!$workerStarted) {
        // Tenta executar o worker diretamente como fallback
        $logMessage = "[" . date('Y-m-d H:i:s') . "] Tentando execução direta do worker...\n";
        file_put_contents($workerLogFile, $logMessage, FILE_APPEND | LOCK_EX);
        
        try {
            // Executa o worker diretamente
            $GLOBALS['FORCE_ANALYSIS'] = $force;
            include $scriptPath;
            $workerStarted = true;
            
            $logMessage = "[" . date('Y-m-d H:i:s') . "] Worker executado diretamente com sucesso\n";
            file_put_contents($workerLogFile, $logMessage, FILE_APPEND | LOCK_EX);
        } catch (Exception $e) {
            $logMessage = "[" . date('Y-m-d H:i:s') . "] Erro na execução direta: " . $e->getMessage() . "\n";
            file_put_contents($workerLogFile, $logMessage, FILE_APPEND | LOCK_EX);
        }
    }

    // Resposta final ao front-end
    echo json_encode([
        'success' => true,
        'message' => $force ? 'Análise reiniciada com sucesso.' : 'Análise iniciada com sucesso. Processamento em segundo plano.',
        'status' => 'started',
        'worker_started' => $workerStarted,
        'lock_file' => $lockFile,
        'log_file' => $workerLogFile
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Log do erro
    if (isset($workerLogFile)) {
        $logMessage = "[" . date('Y-m-d H:i:s') . "] ERRO: " . $e->getMessage() . "\n";
        file_put_contents($workerLogFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao iniciar análise: ' . $e->getMessage(),
        'error_details' => $e->getTraceAsString()
    ], JSON_UNESCAPED_UNICODE);
}
