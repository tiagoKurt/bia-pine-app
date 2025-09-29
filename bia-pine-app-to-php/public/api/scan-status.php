<?php
if (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

try {
    $lockFile = __DIR__ . '/../../cache/scan.lock';

    if (!file_exists($lockFile)) {
        echo json_encode([
            'inProgress' => false,
            'status' => 'idle',
            'message' => 'Nenhuma análise em andamento'
        ]);
        exit;
    }

    $lockContent = file_get_contents($lockFile);
    if ($lockContent === false || empty(trim($lockContent))) {
        unlink($lockFile);
        echo json_encode([
            'inProgress' => false,
            'status' => 'idle',
            'message' => 'Nenhuma análise em andamento'
        ]);
        exit;
    }

    $statusData = json_decode($lockContent, true);
    if (!$statusData) {
        unlink($lockFile);
        echo json_encode([
            'inProgress' => false,
            'status' => 'idle',
            'message' => 'Dados de status corrompidos - arquivo removido'
        ]);
        exit;
    }

    $inProgress = in_array($statusData['status'] ?? '', ['pending', 'running']);
    
    if (!$inProgress && isset($statusData['endTime'])) {
        $endTime = strtotime($statusData['endTime']);
        $oneHourAgo = time() - 3600;
        
        if ($endTime < $oneHourAgo) {
            unlink($lockFile);
            echo json_encode([
                'inProgress' => false,
                'status' => 'idle',
                'message' => 'Nenhuma análise em andamento'
            ]);
            exit;
        }
    }

    $response = [
        'inProgress' => $inProgress,
        'status' => $statusData['status'] ?? 'unknown',
        'progress' => $statusData['progress'] ?? null,
        'startTime' => $statusData['startTime'] ?? null,
        'lastUpdate' => $statusData['lastUpdate'] ?? null
    ];

    switch ($statusData['status'] ?? '') {
        case 'pending':
            $response['message'] = 'Análise na fila, aguardando início...';
            break;
        case 'running':
            $response['message'] = 'Análise em execução...';
            break;
        case 'completed':
            $response['message'] = 'Análise concluída com sucesso!';
            $response['endTime'] = $statusData['endTime'] ?? null;
            $response['results'] = $statusData['results'] ?? null;
            break;
        case 'failed':
            $response['message'] = 'Análise falhou';
            $response['error'] = $statusData['error'] ?? 'Erro desconhecido';
            $response['endTime'] = $statusData['endTime'] ?? null;
            break;
        default:
            $response['message'] = 'Status desconhecido';
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'inProgress' => false,
        'status' => 'error',
        'message' => 'Erro ao verificar status: ' . $e->getMessage()
    ]);
}
?>
