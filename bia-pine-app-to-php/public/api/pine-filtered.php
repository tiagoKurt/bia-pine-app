<?php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Pine;

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
    $portalUrl = $_GET['portal_url'] ?? '';
    $page = (int)($_GET['page'] ?? 1);
    $perPage = (int)($_GET['per_page'] ?? 15);
    $organization = $_GET['organization'] ?? '';
    $status = $_GET['status'] ?? '';
    $search = $_GET['search'] ?? '';
    
    $pdo = conectarBanco();
    $pine = new Pine();
    
    // Buscar datasets com filtros
    $result = $pine->getDatasetsPaginadosComFiltros(
        $pdo, 
        $portalUrl, 
        $page, 
        $perPage, 
        $organization, 
        $status, 
        $search
    );
    
    echo json_encode([
        'success' => true,
        'datasets' => $result['datasets'],
        'total' => $result['total'],
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => $result['total_pages']
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
