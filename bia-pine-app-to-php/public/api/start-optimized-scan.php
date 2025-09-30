<?php
/**
 * API endpoint para iniciar varredura otimizada usando CkanScannerService
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config.php';

use App\CkanScannerService;

try {
    // Conecta ao banco de dados
    $pdo = conectarBanco();

    // Cria o serviço de scanner
    $scannerService = new CkanScannerService(
        'https://dadosabertos.go.gov.br',
        '', // API key vazia para acesso público
        __DIR__ . '/../../cache',
        $pdo
    );

    // Define callback de progresso
    $scannerService->setProgressCallback(function($data) {
        error_log("Scanner Service Progress: " . json_encode($data));
    });

    // Executa a análise otimizada
    $resultado = $scannerService->executarAnaliseOtimizada();

    // Retorna resultado em JSON
    echo json_encode([
        'success' => true,
        'data' => $resultado,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
