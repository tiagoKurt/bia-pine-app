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
    $pdo = conectarBanco();
    $cpfController = new CpfController($pdo);
    
    $stats = $cpfController->getEstatisticas();
    $lastScan = $cpfController->getLastScanInfo();
    $orgaos = $cpfController->getOrgaos();
    
    $result = [
        'success' => true,
        'stats' => $stats['success'] ? $stats['stats'] : [],
        'last_scan' => $lastScan,
        'orgaos' => $orgaos['success'] ? $orgaos['orgaos'] : [],
        'timestamp' => date('c')
    ];
    
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    error_log("Erro na API CPF Stats: " . $e->getMessage());
    
    // Clean any output buffer before sending error response
    if (ob_get_level()) {
        ob_clean();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor',
        'error' => $e->getMessage(),
        'stats' => [],
        'last_scan' => null,
        'orgaos' => [],
        'timestamp' => date('c')
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

// End output buffering and flush
if (ob_get_level()) {
    ob_end_flush();
}