<?php
/**
 * Worker para execução de análise CKAN via Cron Job
 * 
 * Este arquivo é chamado pelo start-worker.php quando há uma análise pendente.
 * Ele executa o script bin/run_scanner.php que contém a lógica principal.
 */

require_once __DIR__ . '/config.php';

$cacheDir = __DIR__ . '/cache';
$lockFile = $cacheDir . '/scan_status.json';

echo "=== WORKER CKAN - Iniciado em " . date('Y-m-d H:i:s') . " ===\n";

// Verificar se há análise pendente
if (!file_exists($lockFile)) {
    echo "Nenhuma análise pendente encontrada.\n";
    exit(0);
}

$statusData = json_decode(file_get_contents($lockFile), true);
if (!$statusData || !in_array($statusData['status'], ['pending', 'running'])) {
    echo "Status atual: " . ($statusData['status'] ?? 'indefinido') . " - Nenhuma ação necessária.\n";
    exit(0);
}

echo "Status: {$statusData['status']} - Executando análise...\n";

// Incluir e executar o scanner
$scannerScript = __DIR__ . '/bin/run_scanner.php';

if (!file_exists($scannerScript)) {
    echo "ERRO: Script do scanner não encontrado: $scannerScript\n";
    
    // Atualizar status para erro
    $statusData['status'] = 'failed';
    $statusData['error'] = 'Script do scanner não encontrado';
    $statusData['endTime'] = date('c');
    file_put_contents($lockFile, json_encode($statusData, JSON_PRETTY_PRINT));
    
    exit(1);
}

try {
    // Executar o scanner
    include $scannerScript;
    
    echo "=== WORKER CKAN - Finalizado em " . date('Y-m-d H:i:s') . " ===\n";
    exit(0);
    
} catch (Exception $e) {
    echo "ERRO ao executar scanner: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    
    // Atualizar status para erro
    $statusData = json_decode(file_get_contents($lockFile), true) ?: [];
    $statusData['status'] = 'failed';
    $statusData['error'] = $e->getMessage();
    $statusData['endTime'] = date('c');
    file_put_contents($lockFile, json_encode($statusData, JSON_PRETTY_PRINT));
    
    exit(1);
}
