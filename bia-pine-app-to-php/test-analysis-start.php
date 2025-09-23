<?php
/**
 * Script para testar o início de análise
 */

echo "=== Teste de Início de Análise ===\n\n";

// Simular requisição POST
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [];

echo "1. Testando start-scan.php...\n";

// Capturar saída
ob_start();
include __DIR__ . '/public/api/start-scan.php';
$output = ob_get_clean();

echo "Resposta:\n";
echo $output . "\n\n";

$response = json_decode($output, true);

if ($response && $response['success']) {
    echo "✅ Análise iniciada com sucesso!\n";
    echo "📊 Status: " . ($response['status'] ?? 'N/A') . "\n";
    echo "🆔 ID: " . ($response['analysisId'] ?? 'N/A') . "\n";
} else {
    echo "❌ Erro ao iniciar análise:\n";
    echo "   - Mensagem: " . ($response['message'] ?? 'Erro desconhecido') . "\n";
    echo "   - Status: " . ($response['currentStatus'] ?? 'N/A') . "\n";
    
    if (isset($response['canForce']) && $response['canForce']) {
        echo "   - Pode forçar: Sim\n";
        echo "   - Timeout: " . ($response['timeout'] ?? 'N/A') . " segundos\n";
    }
}

echo "\n2. Verificando arquivo de lock...\n";
$lockFile = __DIR__ . '/cache/scan.lock';

if (file_exists($lockFile)) {
    $lockData = json_decode(file_get_contents($lockFile), true);
    echo "✅ Arquivo de lock existe:\n";
    echo "   - Status: " . ($lockData['status'] ?? 'N/A') . "\n";
    echo "   - Iniciado: " . ($lockData['startTime'] ?? 'N/A') . "\n";
} else {
    echo "❌ Arquivo de lock não encontrado\n";
}

echo "\n=== Teste Concluído ===\n";
?>
