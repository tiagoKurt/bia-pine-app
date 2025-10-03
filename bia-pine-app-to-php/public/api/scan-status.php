<?php

require_once __DIR__ . '/../../config.php';
// Garantir que o autoloader esteja disponível
ensureAutoloader();

use App\Api\StatusController;

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
    // Verifica se é uma requisição GET
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Método não permitido. Use GET.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Configuração de cache
    $cacheDir = __DIR__ . '/../../cache';
    $controller = new StatusController($cacheDir);
    
    // Obtém o status da análise
    $statusData = $controller->getStatus();
    
    // Converte o status para o formato esperado pelo frontend
    $response = [
        'success' => true,
        'inProgress' => in_array($statusData['status'] ?? 'not_started', ['running', 'pending']),
        'status' => $statusData['status'] ?? 'not_started',
        'message' => $statusData['message'] ?? 'Análise não iniciada',
        'progress' => $statusData['progress'] ?? [],
        'startTime' => $statusData['startTime'] ?? null,
        'lastUpdate' => $statusData['lastUpdate'] ?? null
    ];
    
    // Se há progresso, adiciona informações específicas
    if (isset($statusData['progress']) && is_array($statusData['progress'])) {
        $progress = $statusData['progress'];
        $response['datasets_analisados'] = $progress['datasets_analisados'] ?? 0;
        $response['recursos_analisados'] = $progress['recursos_analisados'] ?? 0;
        $response['recursos_com_cpfs'] = $progress['recursos_com_cpfs'] ?? 0;
        $response['total_cpfs_salvos'] = $progress['total_cpfs_salvos'] ?? 0;
        $response['current_step'] = $progress['current_step'] ?? 'Aguardando...';
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao obter status: ' . $e->getMessage(),
        'inProgress' => false
    ], JSON_UNESCAPED_UNICODE);
}
