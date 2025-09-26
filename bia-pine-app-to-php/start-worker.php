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

if (PHP_OS_FAMILY === 'Windows') {
    // Windows - usar start /B para executar em background
    exec("start /B php \"{$workerPath}\" > NUL 2>&1");
} else {
    // Linux/Unix - usar nohup para executar em background
    exec("nohup php \"{$workerPath}\" > /dev/null 2>&1 &");
}

echo "Worker iniciado em background.\n";
?>
