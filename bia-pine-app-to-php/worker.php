<?php
/**
 * Worker para executar análise CKAN em background
 * 
 * Este script deve ser executado pelo servidor para processar
 * análises pendentes em segundo plano
 */

// Configuração inicial
set_time_limit(0); // Sem limite de tempo
ignore_user_abort(true); // Continua mesmo se usuário fechar navegador

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

use App\CkanScannerService;
use Dotenv\Dotenv;

// Função para log
function workerLog($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] Worker: {$message}\n";
    error_log($logMessage);
    
    // Também salva em arquivo específico se possível
    $logFile = __DIR__ . '/logs/worker.log';
    if (is_dir(dirname($logFile))) {
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
}

workerLog("Worker iniciado");

try {
    // Carrega variáveis de ambiente
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
    $dotenv->required(['CKAN_API_URL', 'DB_HOST', 'DB_DATABASE', 'DB_USERNAME']);
    
    // Conexão com banco
    $pdo = conectarBanco();
    
    $lockFile = __DIR__ . '/cache/scan.lock';
    
    // Verifica se existe tarefa pendente
    if (!file_exists($lockFile)) {
        workerLog("Nenhuma tarefa pendente encontrada");
        exit;
    }
    
    $lockData = json_decode(file_get_contents($lockFile), true);
    
    // Verifica se tarefa está pendente
    if (!$lockData || $lockData['status'] !== 'pending') {
        workerLog("Tarefa não está pendente: " . ($lockData['status'] ?? 'status indefinido'));
        exit;
    }
    
    workerLog("Tarefa pendente encontrada, iniciando processamento");
    
    // Atualiza status para 'running'
    $lockData['status'] = 'running';
    $lockData['lastUpdate'] = date('c');
    file_put_contents($lockFile, json_encode($lockData, JSON_PRETTY_PRINT));
    
    // Configuração do scanner
    $ckanUrl = $_ENV['CKAN_API_URL'];
    $ckanApiKey = $_ENV['CKAN_API_KEY'] ?? '';
    $maxRetries = (int)($_ENV['MAX_RETRY_ATTEMPTS'] ?? 5);
    $cacheDir = __DIR__ . '/cpf-scanner/' . ($_ENV['CACHE_DIR'] ?? 'cache');
    
    // Inicializa o serviço
    $scannerService = new CkanScannerService($ckanUrl, $ckanApiKey, $cacheDir, $pdo, $maxRetries);
    
    // Define callback para atualizar progresso
    $scannerService->setProgressCallback(function($progress) use ($lockFile) {
        $currentLockData = json_decode(file_get_contents($lockFile), true);
        $currentLockData['progress'] = $progress;
        $currentLockData['lastUpdate'] = date('c');
        file_put_contents($lockFile, json_encode($currentLockData, JSON_PRETTY_PRINT));
    });
    
    workerLog("Iniciando análise CKAN");
    
    // Executa análise
    $results = $scannerService->executeScan();
    
    workerLog("Análise concluída com sucesso");
    
    // Atualiza status para 'completed'
    $lockData = json_decode(file_get_contents($lockFile), true);
    $lockData['status'] = 'completed';
    $lockData['endTime'] = date('c');
    $lockData['results'] = $results['data'];
    $lockData['lastUpdate'] = date('c');
    
    // Progresso final
    if (isset($results['data'])) {
        $lockData['progress'] = array_merge($lockData['progress'] ?? [], $results['data']);
        $lockData['progress']['current_step'] = 'Análise concluída com sucesso!';
    }
    
    file_put_contents($lockFile, json_encode($lockData, JSON_PRETTY_PRINT));
    
    // Atualiza histórico de análises
    $historyFile = __DIR__ . '/cache/scan-history.json';
    $history = [];
    if (file_exists($historyFile)) {
        $history = json_decode(file_get_contents($historyFile), true) ?? [];
    }
    
    $history['lastCompletedScan'] = date('c');
    $history['totalScans'] = ($history['totalScans'] ?? 0) + 1;
    $history['lastResults'] = $results['data'];
    
    file_put_contents($historyFile, json_encode($history, JSON_PRETTY_PRINT));
    
    workerLog("Status atualizado para 'completed' e histórico salvo");
    
    // Opcionalmente, remove o arquivo após algumas horas para não acumular
    // Mas por agora mantemos para que o front-end possa verificar o resultado
    
} catch (Exception $e) {
    workerLog("Erro fatal: " . $e->getMessage());
    
    // Atualiza status para 'failed'
    if (isset($lockFile) && file_exists($lockFile)) {
        $lockData = json_decode(file_get_contents($lockFile), true);
        $lockData['status'] = 'failed';
        $lockData['error'] = $e->getMessage();
        $lockData['endTime'] = date('c');
        $lockData['lastUpdate'] = date('c');
        
        if (isset($lockData['progress'])) {
            $lockData['progress']['current_step'] = 'Erro: ' . $e->getMessage();
        }
        
        file_put_contents($lockFile, json_encode($lockData, JSON_PRETTY_PRINT));
    }
    
    exit(1);
}

workerLog("Worker finalizado");
?>
