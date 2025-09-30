<?php
/**
 * API endpoint para execução da varredura otimizada de CPF
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

use App\OptimizedCkanScanner;

try {
    // Conecta ao banco de dados
    $pdo = conectarBanco();

    // Cria o scanner otimizado
    $scanner = new OptimizedCkanScanner($pdo);
    
    // Define callback de progresso para logging
    $scanner->setProgressCallback(function($data) {
        error_log("Scanner Progress: " . json_encode($data));
    });

    // Executa a varredura otimizada
    $resultado = $scanner->executarVarreduraOtimizada();

    // Obtém estatísticas do banco
    $stats = $scanner->obterEstatisticas();
    $resultado['estatisticas_banco'] = $stats;

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
