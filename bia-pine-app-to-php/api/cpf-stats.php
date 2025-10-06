<?php

require_once __DIR__ . '/../config.php';

use App\Cpf\CpfController;

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