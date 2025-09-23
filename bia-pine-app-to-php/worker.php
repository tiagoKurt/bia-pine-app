<?php
// worker.php (na raiz do seu projeto)

// Define um tempo de execução ilimitado para este script
@set_time_limit(0);

// === LOGGING AGRESSIVO PARA DIAGNÓSTICO ===
function writeLog($message, $level = 'INFO') {
    $logFile = __DIR__ . '/logs/worker.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    echo $logEntry; // Também exibe no console
}

// Inicia logging imediatamente
writeLog("=== WORKER INICIADO ===");
writeLog("PHP Version: " . PHP_VERSION);
writeLog("Working Directory: " . getcwd());
writeLog("Script Path: " . __FILE__);

try {
    writeLog("Carregando autoloader...");
    require_once __DIR__ . '/vendor/autoload.php';
    writeLog("Autoloader carregado com sucesso");
    
    writeLog("Carregando configuração...");
    require_once __DIR__ . '/config.php';
    writeLog("Configuração carregada com sucesso");
} catch (Exception $e) {
    writeLog("ERRO FATAL ao carregar dependências: " . $e->getMessage(), 'ERROR');
    exit(1);
}

use App\CkanScannerService;
use Dotenv\Dotenv;

// --- Bloco de Segurança: Garante que apenas um worker rode por vez ---
writeLog("Verificando se worker já está em execução...");
$pidFile = __DIR__ . '/cache/worker.pid';
if (file_exists($pidFile)) {
    $pid = (int) file_get_contents($pidFile);
    writeLog("Arquivo PID encontrado: $pid");
    // Verifica se o processo com o PID ainda está em execução
    if (function_exists('posix_kill') && posix_kill($pid, 0)) {
        writeLog("Worker já está em execução (PID: $pid). Saindo.", 'WARNING');
        exit;
    } else {
        // Se posix_kill não estiver disponível ou processo não existir, remove o PID file
        writeLog("Processo PID $pid não existe mais. Removendo arquivo PID.");
        unlink($pidFile);
    }
} else {
    writeLog("Nenhum arquivo PID encontrado. Prosseguindo...");
}

$currentPid = getmypid();
writeLog("Criando arquivo PID: $currentPid");
file_put_contents($pidFile, $currentPid);
// -----------------------------------------------------------------

$lockFile = __DIR__ . '/cache/scan.lock';
$historyFile = __DIR__ . '/cache/scan-history.json';

// Função para remover o PID file ao sair
register_shutdown_function(function () use ($pidFile) {
    if (file_exists($pidFile)) {
        unlink($pidFile);
    }
});

// Verifica se existe uma tarefa pendente
writeLog("Verificando arquivo de lock: $lockFile");
if (!file_exists($lockFile)) {
    writeLog("Nenhuma tarefa pendente. Arquivo de lock não encontrado.", 'WARNING');
    exit;
}

writeLog("Arquivo de lock encontrado. Lendo dados...");
$lockData = json_decode(file_get_contents($lockFile), true);

if (!$lockData) {
    writeLog("ERRO: Não foi possível decodificar dados do arquivo de lock", 'ERROR');
    exit;
}

writeLog("Dados do lock: " . json_encode($lockData, JSON_PRETTY_PRINT));

// Se a tarefa não está pendente, sai
if (($lockData['status'] ?? '') !== 'pending') {
    writeLog("A tarefa não está pendente (status: {$lockData['status']}). Saindo.", 'WARNING');
    exit;
}

writeLog("Tarefa pendente encontrada. Iniciando processamento...");

// --- Início do Processo de Análise ---
try {
    writeLog("=== INICIANDO ANÁLISE CKAN ===");
    
    // Atualiza o status para "running"
    writeLog("Atualizando status para 'running'...");
    $lockData['status'] = 'running';
    $lockData['lastUpdate'] = date('c');
    file_put_contents($lockFile, json_encode($lockData, JSON_PRETTY_PRINT));
    writeLog("Status atualizado com sucesso");

    // Carrega variáveis de ambiente
    writeLog("Carregando variáveis de ambiente...");
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
    writeLog("Variáveis de ambiente carregadas");
    
    // Conecta ao banco
    writeLog("Conectando ao banco de dados...");
    $pdo = conectarBanco();
    writeLog("Conexão com banco estabelecida com sucesso");

    // Inicializa o serviço de scanner
    writeLog("Inicializando serviço de scanner...");
    $ckanUrl = $_ENV['CKAN_API_URL'] ?? 'https://dadosabertos.go.gov.br';
    $ckanKey = $_ENV['CKAN_API_KEY'] ?? '';
    $cacheDir = __DIR__ . '/cpf-scanner/' . ($_ENV['CACHE_DIR'] ?? 'cache');
    $maxRetries = (int)($_ENV['MAX_RETRY_ATTEMPTS'] ?? 5);
    
    writeLog("Configurações do scanner:");
    writeLog("  - CKAN URL: $ckanUrl");
    writeLog("  - Cache Dir: $cacheDir");
    writeLog("  - Max Retries: $maxRetries");
    
    $scannerService = new CkanScannerService(
        $ckanUrl,
        $ckanKey,
        $cacheDir,
        $pdo,
        $maxRetries
    );
    writeLog("Serviço de scanner inicializado com sucesso");

    // Define a função de callback para atualizar o progresso em tempo real
    $progressCallback = function ($progress) use ($lockFile) {
        // Lê o estado atual para não sobrescrever
        $currentLockData = json_decode(file_get_contents($lockFile), true);
        $currentLockData['progress'] = $progress;
        $currentLockData['lastUpdate'] = date('c');
        file_put_contents($lockFile, json_encode($currentLockData, JSON_PRETTY_PRINT));
        echo "Progresso: " . ($progress['current_step'] ?? 'Processando...') . "\n";
    };
    $scannerService->setProgressCallback($progressCallback);

    // Executa a análise!
    writeLog("=== EXECUTANDO ANÁLISE ===");
    $results = $scannerService->executeScan();
    writeLog("Análise executada com sucesso");
    
    writeLog("Salvando resultados...");
    
    // Atualiza o lock file com o resultado final
    $finalLockData = json_decode(file_get_contents($lockFile), true);
    $finalLockData['status'] = 'completed';
    $finalLockData['endTime'] = date('c');
    $finalLockData['results'] = $results['data'];
    $finalLockData['lastUpdate'] = date('c');
    file_put_contents($lockFile, json_encode($finalLockData, JSON_PRETTY_PRINT));
    
    // Atualiza o histórico
    $history = file_exists($historyFile) ? json_decode(file_get_contents($historyFile), true) : ['totalScans' => 0];
    $history['lastCompletedScan'] = date('c');
    $history['totalScans'] = ($history['totalScans'] ?? 0) + 1;
    $history['lastResults'] = $results['data'];
    file_put_contents($historyFile, json_encode($history, JSON_PRETTY_PRINT));

    echo "Análise concluída com sucesso.\n";
    echo "Resultados: " . json_encode($results['data'], JSON_PRETTY_PRINT) . "\n";

} catch (Exception $e) {
    writeLog("ERRO DURANTE A EXECUÇÃO: " . $e->getMessage(), 'ERROR');
    writeLog("Stack trace: " . $e->getTraceAsString(), 'ERROR');
    
    // Em caso de erro, atualiza o status para "failed"
    writeLog("Atualizando status para 'failed'...");
    $errorData = json_decode(file_get_contents($lockFile), true);
    $errorData['status'] = 'failed';
    $errorData['error'] = $e->getMessage();
    $errorData['endTime'] = date('c');
    $errorData['lastUpdate'] = date('c');
    file_put_contents($lockFile, json_encode($errorData, JSON_PRETTY_PRINT));
    writeLog("Status de erro atualizado");
    
    error_log("Erro no worker.php: " . $e->getMessage());
} finally {
    // Garante que o arquivo de PID seja removido
    writeLog("Limpando arquivo PID...");
    if (file_exists($pidFile)) {
        unlink($pidFile);
        writeLog("Arquivo PID removido");
    }
    writeLog("=== WORKER FINALIZADO ===");
}
?>