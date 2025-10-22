<?php

require_once __DIR__ . '/../../config.php';

// Headers para API
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
        echo json_encode(['success' => false, 'message' => 'Método não permitido. Use POST.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $cacheDir = __DIR__ . '/../../cache';
    $lockFile = $cacheDir . '/scan_status.json';
    
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    
    $force = isset($_POST['force']) && $_POST['force'] == '1';
    
    // 1. LÓGICA DE CANCELAMENTO / CONFLITO
    if (file_exists($lockFile)) {
        $lockContent = file_get_contents($lockFile);
        $lockData = json_decode($lockContent, true);
        
        if ($lockData && isset($lockData['status'])) {
            $status = $lockData['status'];
            
            if (in_array($status, ['running', 'pending', 'cancelling']) && !$force) {
                // Se rodando e NÃO for para forçar, retorna 409 (Conflict)
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'message' => 'Uma análise já está em execução.',
                    'current_status' => $status,
                    'requires_confirmation' => true
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            if (in_array($status, ['running', 'pending']) && $force) {
                // Se rodando e FOR para forçar, marca o status como 'cancelling'
                $lockData['status'] = 'cancelling';
                $lockData['message'] = 'Análise anterior cancelada. Reiniciando...';
                $lockData['lastUpdate'] = date('c');
                file_put_contents($lockFile, json_encode($lockData, JSON_PRETTY_PRINT));
                
                // Remove o arquivo de fila para forçar nova descoberta
                $queueFile = $cacheDir . '/scan_queue.json';
                if (file_exists($queueFile)) {
                    @unlink($queueFile);
                }
                
                sleep(3);
            }
        }
    }
    
    $queueFile = $cacheDir . '/scan_queue.json';
    if (file_exists($queueFile)) {
        @unlink($queueFile);
        error_log("Arquivo de fila removido para forçar limpeza da base de dados");
    }

    // 2. Cria arquivo de status inicial com 'pending'
    $initialStatus = [
        'status' => 'pending',
        'startTime' => date('c'),
        'progress' => [
            'datasets_analisados' => 0,
            'recursos_analisados' => 0,
            'recursos_com_cpfs' => 0,
            'total_cpfs_salvos' => 0,
            'total_recursos' => 0,
            'recursos_processados' => 0,
            'current_step' => $force 
                ? 'Nova análise iniciada. Preparando ambiente...' 
                : 'Análise iniciada. Conectando ao portal CKAN...'
        ],
        'lastUpdate' => date('c'),
        'message' => $force 
            ? 'Nova análise forçada. Processamento iniciado.' 
            : 'Análise iniciada. O worker processará os recursos em breve.'
    ];
    
    // Se for 'force', apaga o arquivo da fila para garantir que a varredura comece do zero
    $queueFile = $cacheDir . '/scan_queue.json';
    if ($force && file_exists($queueFile)) {
        @unlink($queueFile);
        error_log("Arquivo de fila removido para forçar nova análise");
    }
    
    file_put_contents($lockFile, json_encode($initialStatus, JSON_PRETTY_PRINT));
    error_log("Arquivo de status criado: " . $lockFile);
    
    // 3. TENTAR INICIAR O WORKER AUTOMATICAMENTE (Fallback se cron não estiver configurado)
    $workerStarted = false;
    $workerScript = __DIR__ . '/../../bin/run_scanner.php';
    
    if (file_exists($workerScript)) {
        try {
            // Tentar executar o worker em background
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                // Windows
                $command = "start /B php " . escapeshellarg($workerScript) . " > NUL 2>&1";
                pclose(popen($command, 'r'));
                $workerStarted = true;
                error_log("Worker iniciado em background (Windows): $command");
            } else {
                // Linux/Unix
                $command = "php " . escapeshellarg($workerScript) . " > /dev/null 2>&1 &";
                exec($command);
                $workerStarted = true;
                error_log("Worker iniciado em background (Linux): $command");
            }
        } catch (Exception $e) {
            error_log("Erro ao tentar iniciar worker automaticamente: " . $e->getMessage());
            // Não é erro fatal - o cron job pode pegar depois
        }
    }
    
    // 4. Resposta final ao front-end
    echo json_encode([
        'success' => true,
        'message' => $force ? 'Análise reiniciada com sucesso. Processamento iniciado.' : 'Análise iniciada com sucesso. Processamento em andamento.',
        'status' => 'started',
        'worker_started' => $workerStarted,
        'worker_method' => $workerStarted ? 'automatic' : 'cron_job',
        'lock_file' => $lockFile
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Erro no start-scan: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro fatal ao agendar análise: ' . $e->getMessage(),
        'error_details' => $e->getTraceAsString()
    ], JSON_UNESCAPED_UNICODE);
}

