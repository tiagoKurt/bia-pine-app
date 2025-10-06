<?php

require_once __DIR__ . '/../config.php';

use App\Worker\CkanScannerService;
use App\Cpf\Ckan\CkanApiClient;
use App\Cpf\CpfRepository;
use App\Cpf\CpfVerificationService; 
use App\Cpf\Scanner\LogicBasedScanner;

$cacheDir = __DIR__ . '/../cache';
$queueFile = $cacheDir . '/scan_queue.json';
$lockFile = $cacheDir . '/scan_status.json';

// --- Funções Auxiliares (Para o Worker de Cron) ---
function updateProgress(string $lockFile, array $progress) {
    if (!file_exists($lockFile)) return;
    
    $statusData = json_decode(file_get_contents($lockFile), true) ?: [];
    $statusData['progress'] = array_merge($statusData['progress'] ?? [], $progress);
    $statusData['lastUpdate'] = date('c');
    file_put_contents($lockFile, json_encode($statusData, JSON_PRETTY_PRINT));
}

function getStatus(string $lockFile): array {
    if (!file_exists($lockFile)) {
        return ['status' => 'not_started'];
    }
    $content = file_get_contents($lockFile);
    return json_decode($content, true) ?: ['status' => 'error'];
}
// --- FIM Funções Auxiliares ---

echo "=== WORKER DE ANÁLISE DE CPF (Cron) ===\n";
echo "Iniciado em: " . date('Y-m-d H:i:s') . "\n";

// PREVENIR MÚLTIPLAS EXECUÇÕES SIMULTÂNEAS
$pidFile = $cacheDir . '/scanner.pid';

// Verificar se já existe um processo rodando
if (file_exists($pidFile)) {
    $pid = file_get_contents($pidFile);
    
    // Verificar se o processo ainda está ativo (Windows)
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        exec("tasklist /FI \"PID eq $pid\" 2>NUL", $output);
        $isRunning = count($output) > 1;
    } else {
        // Linux/Unix
        $isRunning = posix_kill($pid, 0);
    }
    
    if ($isRunning) {
        echo "AVISO: Já existe uma análise em execução (PID: $pid)\n";
        echo "Aguardando análise anterior terminar...\n";
        exit(0);
    } else {
        // Processo morto, remover PID antigo
        @unlink($pidFile);
    }
}

// Criar arquivo PID
file_put_contents($pidFile, getmypid());

// Registrar função para remover PID ao finalizar
register_shutdown_function(function() use ($pidFile) {
    if (file_exists($pidFile)) {
        @unlink($pidFile);
    }
});

try {
    $status = getStatus($lockFile);
    
    if ($status['status'] !== 'pending' && $status['status'] !== 'running' && $status['status'] !== 'cancelling') {
        echo "Status atual: {$status['status']}. Nenhuma análise a ser executada.\n";
        exit(0);
    }
    
    // Se o status for 'cancelling', o worker finaliza a si mesmo
    if ($status['status'] === 'cancelling') {
        $status['status'] = 'cancelled';
        $status['endTime'] = date('c');
        $status['message'] = 'Análise anterior foi cancelada.';
        file_put_contents($lockFile, json_encode($status, JSON_PRETTY_PRINT));
        echo "Worker cancelado por requisição da API.\n";
        exit(0);
    }

    echo "Status: {$status['status']} - Preparando ambiente...\n";
    
    // Conexão e Injeção de Dependências
    $pdo = getPdoConnection();

    // O service já injeta todos os outros componentes (CkanApiClient, CpfRepository, etc.)
    // O construtor do CkanScannerService usa o $pdo para inicializar o CpfRepository e o CpfVerificationService
    $scannerService = new CkanScannerService(
        CKAN_API_URL,
        CKAN_API_KEY,
        $cacheDir,
        $pdo
    );

    // Define o callback de progresso usando a função auxiliar
    $scannerService->setProgressCallback(function($progress) use ($lockFile) {
        updateProgress($lockFile, $progress);
        echo "Progresso: " . ($progress['current_step'] ?? '...') . "\n";
    });
    
    // Atualiza o status para 'running' imediatamente (se estiver 'pending')
    if ($status['status'] === 'pending') {
        $status['status'] = 'running';
        $status['lastUpdate'] = date('c');
        $status['message'] = 'Worker iniciado e processando lotes de recursos...';
        file_put_contents($lockFile, json_encode($status, JSON_PRETTY_PRINT));
        error_log("Status atualizado para 'running' no arquivo: " . $lockFile);
    }
    
    
    // --- Loop Principal de Execução ---
    $maxIterations = 3000; 
    $iteration = 0;
    
    while ($iteration < $maxIterations) {
        $iteration++;
        
        // Verifica se houve um pedido de cancelamento durante o processamento do lote
        $currentStatus = getStatus($lockFile);
        if ($currentStatus['status'] === 'cancelling') {
            echo "Pedido de cancelamento detectado. Finalizando...\n";
            $currentStatus['status'] = 'cancelled';
            $currentStatus['endTime'] = date('c');
            $currentStatus['message'] = 'Análise cancelada pelo usuário.';
            file_put_contents($lockFile, json_encode($currentStatus, JSON_PRETTY_PRINT));
            exit(0);
        }
        
        echo "Iteração $iteration - Processando lote...\n";
        
        $result = $scannerService->executarAnaliseControlada($lockFile, $queueFile);
        
        if ($result['status'] === 'completed') {
            $finalStatus = getStatus($lockFile);
            $finalStatus['status'] = 'completed';
            $finalStatus['endTime'] = date('c');
            $finalStatus['message'] = $result['message'];
            file_put_contents($lockFile, json_encode($finalStatus, JSON_PRETTY_PRINT));
            echo "Análise concluída com sucesso!\n";
            exit(0);
        }
        
        if ($result['status'] === 'failed' || $result['status'] === 'error') {
            $finalStatus = getStatus($lockFile);
            $finalStatus['status'] = 'failed';
            $finalStatus['error'] = $result['message'];
            $finalStatus['endTime'] = date('c');
            file_put_contents($lockFile, json_encode($finalStatus, JSON_PRETTY_PRINT));
            echo "Erro na análise: " . ($result['message'] ?? 'Erro desconhecido') . "\n";
            exit(1);
        }
        
        // Se estiver 'running', continua para o próximo lote (com pausa)
        echo "Lote processado. Aguardando próximo lote (0.5s)...\n";
        usleep(500000); // 0.5 segundo
    }
    
    if ($iteration >= $maxIterations) {
        // Fallback de erro se atingir o limite de iterações
        $errorStatus = getStatus($lockFile);
        $errorStatus['status'] = 'failed';
        $errorStatus['error'] = 'Limite máximo de iterações atingido. Análise pode estar incompleta.';
        $errorStatus['endTime'] = date('c');
        file_put_contents($lockFile, json_encode($errorStatus, JSON_PRETTY_PRINT));
    }

} catch (Exception $e) {
    // Tratamento de erro fatal (e.g., falha de conexão com DB no início)
    error_log("ERRO FATAL NO WORKER: " . $e->getMessage());
    $errorStatus = getStatus($lockFile);
    $errorStatus['status'] = 'failed';
    $errorStatus['error'] = $e->getMessage();
    $errorStatus['endTime'] = date('c');
    file_put_contents($lockFile, json_encode($errorStatus, JSON_PRETTY_PRINT));
    echo "✗ ERRO FATAL: " . $e->getMessage() . "\n";
    exit(1);
}

echo "=== FIM DO WORKER ===\n";