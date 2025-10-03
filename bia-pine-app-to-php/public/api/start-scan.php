<?php

require_once __DIR__ . '/../../config.php';
ensureAutoloader();

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
                    'message' => 'Uma análise já está em execução. Cancele-a antes de iniciar uma nova.',
                    'current_status' => $status
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            if (in_array($status, ['running', 'pending']) && $force) {
                // Se rodando e FOR para forçar, marca o status como 'cancelling'
                $lockData['status'] = 'cancelling';
                $lockData['message'] = 'Scan anterior marcado para cancelamento. Reiniciando...';
                $lockData['lastUpdate'] = date('c');
                file_put_contents($lockFile, json_encode($lockData, JSON_PRETTY_PRINT));
                
                // Aguarda um pouco para o worker antigo sair (opcional, mas ajuda)
                sleep(1); 
            }
        }
    }

    // 2. Cria arquivo de status inicial com 'pending'
    $initialStatus = [
        'status' => 'pending', // O worker Cron Job iniciará quando vir 'pending'
        'startTime' => date('c'),
        'progress' => [
            'datasets_analisados' => 0,
            'recursos_analisados' => 0,
            'recursos_com_cpfs' => 0,
            'total_cpfs_salvos' => 0,
            'current_step' => 'Análise agendada e aguardando worker (Cron Job)...'
        ],
        'lastUpdate' => date('c'),
        'message' => 'Análise agendada. O worker Cron Job iniciará o processamento em breve.'
    ];
    
    // Se for 'force', apaga o arquivo da fila para garantir que a varredura comece do zero
    $queueFile = $cacheDir . '/scan_queue.json';
    if ($force && file_exists($queueFile)) {
        @unlink($queueFile);
    }
    
    file_put_contents($lockFile, json_encode($initialStatus, JSON_PRETTY_PRINT));
    
    // 3. Resposta final ao front-end
    echo json_encode([
        'success' => true,
        'message' => $force ? 'Análise reiniciada com sucesso. O worker Cron Job está sendo aguardado.' : 'Análise iniciada com sucesso. O worker Cron Job iniciará o processamento.',
        'status' => 'started',
        'worker_started' => false, // Setado como false pois a inicialização é externa/Cron
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

