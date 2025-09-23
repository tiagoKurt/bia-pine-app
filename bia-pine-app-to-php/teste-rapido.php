<?php
/**
 * Teste Rápido do Worker
 * 
 * Este script executa um teste rápido para verificar
 * se o worker está funcionando corretamente
 */

echo "=== TESTE RÁPIDO DO WORKER ===\n\n";

// Função para logging
function logTest($message, $status = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $symbol = $status === 'OK' ? '✅' : ($status === 'ERROR' ? '❌' : ($status === 'WARNING' ? '⚠️' : 'ℹ️'));
    echo "[$timestamp] $symbol $message\n";
}

// 1. Verificar se há tarefa pendente
$lockFile = __DIR__ . '/cache/scan.lock';
if (!file_exists($lockFile)) {
    logTest("Nenhuma tarefa pendente encontrada", 'WARNING');
    logTest("Execute primeiro: php public/api/start-scan.php", 'INFO');
    exit(0);
}

$lockData = json_decode(file_get_contents($lockFile), true);
if (!$lockData || $lockData['status'] !== 'pending') {
    logTest("Tarefa não está pendente (status: " . ($lockData['status'] ?? 'indefinido') . ")", 'WARNING');
    exit(0);
}

logTest("Tarefa pendente encontrada", 'OK');

// 2. Limpar arquivo PID se existir
$pidFile = __DIR__ . '/cache/worker.pid';
if (file_exists($pidFile)) {
    unlink($pidFile);
    logTest("Arquivo PID removido", 'OK');
}

// 3. Executar worker
logTest("Executando worker...", 'INFO');
echo "\n--- SAÍDA DO WORKER ---\n";

$startTime = time();
$output = [];
$returnCode = 0;

exec("php " . __DIR__ . "/worker.php 2>&1", $output, $returnCode);

$endTime = time();
$duration = $endTime - $startTime;

echo implode("\n", $output) . "\n";
echo "--- FIM DA SAÍDA DO WORKER ---\n\n";

logTest("Worker executado em $duration segundos", 'INFO');
logTest("Código de retorno: $returnCode", $returnCode === 0 ? 'OK' : 'ERROR');

// 4. Verificar resultado
if (file_exists($lockFile)) {
    $newLockData = json_decode(file_get_contents($lockFile), true);
    if ($newLockData) {
        $newStatus = $newLockData['status'] ?? 'indefinido';
        logTest("Status final: $newStatus", $newStatus === 'completed' ? 'OK' : ($newStatus === 'failed' ? 'ERROR' : 'WARNING'));
        
        if ($newStatus === 'failed' && isset($newLockData['error'])) {
            logTest("Erro: " . $newLockData['error'], 'ERROR');
        }
    }
}

// 5. Verificar logs
$logFile = __DIR__ . '/logs/worker.log';
if (file_exists($logFile)) {
    logTest("Logs gerados em: $logFile", 'OK');
    
    $logContent = file_get_contents($logFile);
    $logLines = explode("\n", $logContent);
    $recentLines = array_slice($logLines, -10);
    
    logTest("Últimas linhas do log:", 'INFO');
    foreach ($recentLines as $line) {
        if (trim($line)) {
            echo "  $line\n";
        }
    }
} else {
    logTest("Nenhum log gerado", 'WARNING');
}

echo "\n=== TESTE CONCLUÍDO ===\n";
?>
