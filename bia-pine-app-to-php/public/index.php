<?php

// Garantir que o autoloader esteja disponível
ensureAutoloader();
require_once __DIR__ . '/../config.php';

use App\Api\StatusController;

// Configuração de CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// Trata requisições OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Configuração de cache
$cacheDir = __DIR__ . '/../cache';
$controller = new StatusController($cacheDir);

// Roteamento simples
$requestUri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($requestUri) {
        case '/api/status':
        case '/api/status/':
            if ($method === 'GET') {
                $response = $controller->getStatus();
            } else {
                $response = ['error' => 'Método não permitido'];
                http_response_code(405);
            }
            break;

        case '/api/start':
        case '/api/start/':
            if ($method === 'POST') {
                $response = $controller->startAnalysis();
            } else {
                $response = ['error' => 'Método não permitido'];
                http_response_code(405);
            }
            break;

        case '/api/stop':
        case '/api/stop/':
            if ($method === 'POST') {
                $response = $controller->stopAnalysis();
            } else {
                $response = ['error' => 'Método não permitido'];
                http_response_code(405);
            }
            break;

        case '/':
        case '/api':
        case '/api/':
            $response = [
                'message' => 'API BIA/PINE Scanner',
                'version' => '2.0',
                'endpoints' => [
                    'GET /api/status' => 'Obter status da análise',
                    'POST /api/start' => 'Iniciar nova análise',
                    'POST /api/stop' => 'Parar análise atual'
                ]
            ];
            break;

        default:
            $response = ['error' => 'Endpoint não encontrado'];
            http_response_code(404);
            break;
    }

    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Erro interno do servidor',
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}