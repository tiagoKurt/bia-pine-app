<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$forceNew = isset($_POST['force']) && $_POST['force'] === 'true';

try {
    $lockDir = __DIR__ . '/../../cache';
    $lockFile = $lockDir . '/scan.lock';
    $historyFile = $lockDir . '/scan-history.json';
    
    if (!is_dir($lockDir)) {
        mkdir($lockDir, 0755, true);
    }

    if (file_exists($historyFile)) {
        $history = json_decode(file_get_contents($historyFile), true);
        $lastScan = $history['lastCompletedScan'] ?? null;
        
        if ($lastScan) {
            $lastScanTime = strtotime($lastScan);
            $fourHoursAgo = time() - (4 * 3600);
            
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

    if (file_exists($lockFile)) {
        $lockData = json_decode(file_get_contents($lockFile), true);
        
        if (isset($lockData['status']) && in_array($lockData['status'], ['completed', 'failed'])) {
            unlink($lockFile);
        } elseif (isset($lockData['status']) && in_array($lockData['status'], ['pending', 'running'])) {
            $lastUpdate = isset($lockData['lastUpdate']) ? strtotime($lockData['lastUpdate']) : 0;
            $currentTime = time();
            $timeoutMinutes = 30;
            $timeoutSeconds = $timeoutMinutes * 60;
            
            if (($currentTime - $lastUpdate) > $timeoutSeconds) {
                unlink($lockFile);
            } else {
                if ($forceNew) {
                    unlink($lockFile);
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

    $jsonData = json_encode($lockData, JSON_PRETTY_PRINT);
    if ($jsonData === false) {
        throw new Exception('Erro ao serializar dados de status');
    }
    
    $success = file_put_contents($lockFile, $jsonData);
    
    if (!$success) {
        throw new Exception('Não foi possível criar arquivo de controle da análise');
    }
    
    if (!file_exists($lockFile) || filesize($lockFile) === 0) {
        throw new Exception('Arquivo de controle não foi criado corretamente');
    }

    $workerPath = dirname(__DIR__, 2) . '/worker.php';
    if (file_exists($workerPath)) {
        $startWorkerPath = dirname(__DIR__, 2) . '/start-worker.php';
        if (file_exists($startWorkerPath)) {
            if (PHP_OS_FAMILY === 'Windows') {
                $command = "start /B php \"$startWorkerPath\" > nul 2>&1";
                pclose(popen($command, 'r'));
            } else {
                $command = "php \"$startWorkerPath\" > /dev/null 2>&1 &";
                exec($command);
            }
            error_log("Worker iniciado em background via start-worker.php");
        } else {
            if (PHP_OS_FAMILY === 'Windows') {
                $command = "start /B php \"$workerPath\" > nul 2>&1";
                pclose(popen($command, 'r'));
            } else {
                $command = "php \"$workerPath\" > /dev/null 2>&1 &";
                exec($command);
            }
            error_log("Worker iniciado em background diretamente");
        }
    } else {
        error_log("Worker não encontrado em: " . $workerPath);
    }

    echo json_encode([
        'success' => true, 
        'message' => 'Análise iniciada em segundo plano.',
        'status' => 'pending'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erro interno: ' . $e->getMessage()
    ]);
}
?>
