<?php
$workerPath = __DIR__ . '/worker.php';

if (!file_exists($workerPath)) {
    echo "Erro: Worker não encontrado em {$workerPath}\n";
    exit(1);
}

$lockFile = __DIR__ . '/cache/scan.lock';
if (!file_exists($lockFile)) {
    echo "Nenhuma análise pendente encontrada.\n";
    exit(0);
}

$lockData = json_decode(file_get_contents($lockFile), true);
if (!$lockData || $lockData['status'] !== 'pending') {
    echo "Análise não está pendente (status: " . ($lockData['status'] ?? 'indefinido') . ")\n";
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
