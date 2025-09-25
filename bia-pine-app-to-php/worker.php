<?php
@set_time_limit(0);

try {
    require_once __DIR__ . '/vendor/autoload.php';
    require_once __DIR__ . '/config.php';
} catch (Exception $e) {
    error_log("ERRO FATAL ao carregar dependências: " . $e->getMessage());
    exit(1);
}

use App\CkanScannerService;
use Dotenv\Dotenv;

$pidFile = __DIR__ . '/cache/worker.pid';
if (file_exists($pidFile)) {
    $pid = (int) file_get_contents($pidFile);
    if (function_exists('posix_kill') && posix_kill($pid, 0)) {
        exit;
    } else {
        unlink($pidFile);
    }
}

$currentPid = getmypid();
file_put_contents($pidFile, $currentPid);

$lockFile = __DIR__ . '/cache/scan.lock';
$historyFile = __DIR__ . '/cache/scan-history.json';

register_shutdown_function(function () use ($pidFile) {
    if (file_exists($pidFile)) {
        unlink($pidFile);
    }
});

if (!file_exists($lockFile)) {
    exit;
}

$lockData = json_decode(file_get_contents($lockFile), true);

if (!$lockData) {
    exit;
}

if (($lockData['status'] ?? '') !== 'pending') {
    exit;
}

try {
    $lockData['status'] = 'running';
    $lockData['lastUpdate'] = date('c');
    file_put_contents($lockFile, json_encode($lockData, JSON_PRETTY_PRINT));

    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
    
    $pdo = conectarBanco();

    $ckanUrl = $_ENV['CKAN_API_URL'] ?? 'https://dadosabertos.go.gov.br';
    $ckanKey = $_ENV['CKAN_API_KEY'] ?? '';
    $cacheDir = __DIR__ . '/cpf-scanner/' . ($_ENV['CACHE_DIR'] ?? 'cache');
    $maxRetries = (int)($_ENV['MAX_RETRY_ATTEMPTS'] ?? 5);
    
    $scannerService = new CkanScannerService(
        $ckanUrl,
        $ckanKey,
        $cacheDir,
        $pdo,
        $maxRetries
    );

    $progressCallback = function ($progress) use ($lockFile) {
        $currentLockData = json_decode(file_get_contents($lockFile), true);
        $currentLockData['progress'] = $progress;
        $currentLockData['lastUpdate'] = date('c');
        file_put_contents($lockFile, json_encode($currentLockData, JSON_PRETTY_PRINT));
        echo "Progresso: " . ($progress['current_step'] ?? 'Processando...') . "\n";
    };
    $scannerService->setProgressCallback($progressCallback);

    $results = $scannerService->executeScan();
    
    $finalLockData = json_decode(file_get_contents($lockFile), true);
    $finalLockData['status'] = 'completed';
    $finalLockData['endTime'] = date('c');
    $finalLockData['results'] = $results['data'];
    $finalLockData['lastUpdate'] = date('c');
    file_put_contents($lockFile, json_encode($finalLockData, JSON_PRETTY_PRINT));
    
    $history = file_exists($historyFile) ? json_decode(file_get_contents($historyFile), true) : ['totalScans' => 0];
    $history['lastCompletedScan'] = date('c');
    $history['totalScans'] = ($history['totalScans'] ?? 0) + 1;
    $history['lastResults'] = $results['data'];
    file_put_contents($historyFile, json_encode($history, JSON_PRETTY_PRINT));

    echo "Análise concluída com sucesso.\n";
    echo "Resultados: " . json_encode($results['data'], JSON_PRETTY_PRINT) . "\n";

} catch (Exception $e) {
    error_log("ERRO DURANTE A EXECUÇÃO: " . $e->getMessage());
    
    $errorData = json_decode(file_get_contents($lockFile), true);
    $errorData['status'] = 'failed';
    $errorData['error'] = $e->getMessage();
    $errorData['endTime'] = date('c');
    $errorData['lastUpdate'] = date('c');
    file_put_contents($lockFile, json_encode($errorData, JSON_PRETTY_PRINT));
    
    error_log("Erro no worker.php: " . $e->getMessage());
} finally {
    if (file_exists($pidFile)) {
        unlink($pidFile);
    }
}
?>