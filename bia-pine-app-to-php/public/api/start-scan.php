<?php
/**
 * Endpoint para iniciar análise CKAN em background
 * 
 * Este script não executa a análise completa, apenas registra
 * que uma análise precisa ser executada
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Apenas aceita POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Verificar se é para forçar nova análise
$forceNew = isset($_POST['force']) && $_POST['force'] === 'true';

try {
    $lockDir = __DIR__ . '/../../cache';
    $lockFile = $lockDir . '/scan.lock';
    $historyFile = $lockDir . '/scan-history.json';
    
    // Cria o diretório de cache se não existir
    if (!is_dir($lockDir)) {
        mkdir($lockDir, 0755, true);
    }

    // Verifica cooldown de 4 horas
    if (file_exists($historyFile)) {
        $history = json_decode(file_get_contents($historyFile), true);
        $lastScan = $history['lastCompletedScan'] ?? null;
        
        if ($lastScan) {
            $lastScanTime = strtotime($lastScan);
            $fourHoursAgo = time() - (4 * 3600); // 4 horas em segundos
            
            if ($lastScanTime > $fourHoursAgo) {
                $remainingTime = $lastScanTime + (4 * 3600) - time();
                $hoursRemaining = ceil($remainingTime / 3600);
                
                echo json_encode([
                    'success' => false, 
                    'message' => "Aguarde {$hoursRemaining} hora(s) antes de executar uma nova análise.",
                    'cooldownActive' => true,
                    'nextScanAllowed' => date('Y-m-d H:i:s', $lastScanTime + (4 * 3600))
                ]);
                exit;
            }
        }
    }

    // Verifica se já existe uma análise em andamento
    if (file_exists($lockFile)) {
        $lockData = json_decode(file_get_contents($lockFile), true);
        
        if (isset($lockData['status']) && in_array($lockData['status'], ['pending', 'running'])) {
            // Verificar se a análise não está travada (mais de 30 minutos sem atualização)
            $lastUpdate = isset($lockData['lastUpdate']) ? strtotime($lockData['lastUpdate']) : 0;
            $currentTime = time();
            $timeoutMinutes = 30; // 30 minutos de timeout
            $timeoutSeconds = $timeoutMinutes * 60;
            
            if (($currentTime - $lastUpdate) > $timeoutSeconds) {
                // Análise travada - limpar e permitir nova
                unlink($lockFile);
                error_log("Análise travada removida (timeout de {$timeoutMinutes} minutos)");
            } else {
                // Análise realmente ativa
                if ($forceNew) {
                    // Forçar nova análise - remover lock existente
                    unlink($lockFile);
                    error_log("Análise forçada - lock anterior removido");
                } else {
                    $remainingTime = $timeoutSeconds - ($currentTime - $lastUpdate);
                    $remainingMinutes = ceil($remainingTime / 60);
                    
                    echo json_encode([
                        'success' => false, 
                        'message' => "Uma análise já está em andamento. Aguarde {$remainingMinutes} minuto(s) ou force uma nova análise.",
                        'currentStatus' => $lockData['status'],
                        'canForce' => true,
                        'timeout' => $remainingTime
                    ]);
                    exit;
                }
            }
        }
    }

    // Cria arquivo de lock com status inicial
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

    $success = file_put_contents($lockFile, json_encode($lockData, JSON_PRETTY_PRINT));
    
    if (!$success) {
        throw new Exception('Não foi possível criar arquivo de controle da análise');
    }

    // Responde imediatamente ao front-end
    echo json_encode([
        'success' => true, 
        'message' => 'Análise iniciada em segundo plano.',
        'status' => 'pending'
    ]);

    // Opcional: Tentar iniciar o worker em background (funciona em sistemas Unix/Linux)
    if (function_exists('exec') && stripos(PHP_OS, 'WIN') === false) {
        $workerPath = dirname(__DIR__, 2) . '/worker.php';
        if (file_exists($workerPath)) {
            exec("php $workerPath > /dev/null 2>&1 &");
        }
    }

} catch (Exception $e) {
    error_log("Erro em start-scan.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erro interno: ' . $e->getMessage()
    ]);
}
?>
