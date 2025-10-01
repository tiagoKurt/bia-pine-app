<?php
$workerPath = __DIR__ . '/worker.php';

if (!file_exists($workerPath)) {
    echo "Erro: Worker não encontrado em {$workerPath}\n";
    exit(1);
}

$statusFile = __DIR__ . '/cache/scan_status.json';
if (!file_exists($statusFile)) {
    echo "Nenhuma análise pendente encontrada.\n";
    exit(0);
}

$statusData = json_decode(file_get_contents($statusFile), true);
if (!$statusData || $statusData['status'] !== 'pending') {
    echo "Análise não está pendente (status: " . ($statusData['status'] ?? 'indefinido') . ")\n";
    exit(0);
}

echo "Iniciando worker para análise pendente...\n";

try {
    $GLOBALS['FORCE_ANALYSIS'] = true;
    
    include $workerPath;
    echo "Worker executado com sucesso.\n";
} catch (Exception $e) {
    echo "Erro ao executar worker: " . $e->getMessage() . "\n";
    exit(1);
}
?>