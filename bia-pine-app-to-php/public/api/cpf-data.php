<?php

// Start output buffering to prevent any unwanted output
ob_start();

require_once __DIR__ . '/../../config.php';

use App\Cpf\CpfController;

// Clean any output that might have been generated during config loading
ob_clean();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(max(1, (int)($_GET['per_page'] ?? 10)), 50); // Entre 1 e 50 itens
    
    // Filtros
    $filtros = [];
    if (!empty($_GET['orgao'])) {
        $filtros['orgao'] = trim($_GET['orgao']);
    }
    if (!empty($_GET['dataset'])) {
        $filtros['dataset'] = trim($_GET['dataset']);
    }
    if (!empty($_GET['data_inicio'])) {
        $filtros['data_inicio'] = $_GET['data_inicio'];
    }
    if (!empty($_GET['data_fim'])) {
        $filtros['data_fim'] = $_GET['data_fim'];
    }
    
    $pdo = conectarBanco();
    $cpfController = new CpfController($pdo);
    
    $result = $cpfController->getCpfFindingsPaginado($page, $perPage, $filtros);
    
    // Adicionar informações extras para debug em desenvolvimento
    if (defined('APP_DEBUG') && APP_DEBUG) {
        $result['debug'] = [
            'filters_applied' => $filtros,
            'query_params' => $_GET,
            'memory_usage' => memory_get_usage(true),
            'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']
        ];
    }
    
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    error_log("Erro na API CPF Data: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Clean any output buffer before sending error response
    if (ob_get_level()) {
        ob_clean();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor',
        'error' => $e->getMessage(),
        'findings' => [],
        'total' => 0,
        'page' => $page ?? 1,
        'per_page' => $perPage ?? 10,
        'total_pages' => 1,
        'has_next' => false,
        'has_prev' => false
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

// End output buffering and flush
if (ob_get_level()) {
    ob_end_flush();
}