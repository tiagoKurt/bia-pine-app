<?php
/**
 * Script de Diagnóstico Completo do Worker
 * 
 * Este script executa uma bateria de testes para diagnosticar
 * problemas com o worker de análise CKAN
 */

echo "=== DIAGNÓSTICO COMPLETO DO WORKER ===\n\n";

// Função para logging
function logTest($message, $status = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $symbol = $status === 'OK' ? '✅' : ($status === 'ERROR' ? '❌' : ($status === 'WARNING' ? '⚠️' : 'ℹ️'));
    echo "[$timestamp] $symbol $message\n";
}

// 1. Verificar ambiente PHP
logTest("=== TESTE 1: AMBIENTE PHP ===");
logTest("Versão do PHP: " . PHP_VERSION, 'OK');
logTest("Sistema Operacional: " . PHP_OS, 'OK');
logTest("Diretório atual: " . getcwd(), 'OK');
logTest("Caminho do script: " . __FILE__, 'OK');

// Verificar extensões necessárias
$requiredExtensions = ['pdo', 'pdo_mysql', 'curl', 'json', 'mbstring'];
foreach ($requiredExtensions as $ext) {
    if (extension_loaded($ext)) {
        logTest("Extensão $ext: Disponível", 'OK');
    } else {
        logTest("Extensão $ext: NÃO DISPONÍVEL", 'ERROR');
    }
}

// 2. Verificar estrutura de arquivos
logTest("\n=== TESTE 2: ESTRUTURA DE ARQUIVOS ===");

$requiredFiles = [
    'worker.php' => 'Script principal do worker',
    'config.php' => 'Arquivo de configuração',
    'vendor/autoload.php' => 'Autoloader do Composer',
    'cache' => 'Diretório de cache',
    'logs' => 'Diretório de logs'
];

foreach ($requiredFiles as $file => $description) {
    if (file_exists($file)) {
        logTest("$description: Encontrado", 'OK');
    } else {
        logTest("$description: NÃO ENCONTRADO", 'ERROR');
    }
}

// 3. Verificar permissões
logTest("\n=== TESTE 3: PERMISSÕES ===");

$directories = ['cache', 'logs'];
foreach ($directories as $dir) {
    if (is_dir($dir)) {
        if (is_writable($dir)) {
            logTest("Diretório $dir: Gravável", 'OK');
        } else {
            logTest("Diretório $dir: NÃO GRAVÁVEL", 'ERROR');
        }
    }
}

// 4. Verificar arquivo de lock
logTest("\n=== TESTE 4: ARQUIVO DE LOCK ===");

$lockFile = 'cache/scan.lock';
if (file_exists($lockFile)) {
    logTest("Arquivo de lock encontrado", 'OK');
    
    $lockContent = file_get_contents($lockFile);
    $lockData = json_decode($lockContent, true);
    
    if ($lockData) {
        logTest("Dados do lock válidos", 'OK');
        logTest("Status: " . ($lockData['status'] ?? 'indefinido'), 'INFO');
        logTest("Iniciado em: " . ($lockData['startTime'] ?? 'indefinido'), 'INFO');
        logTest("Última atualização: " . ($lockData['lastUpdate'] ?? 'indefinido'), 'INFO');
        
        // Verificar se está travado
        if (isset($lockData['lastUpdate'])) {
            $lastUpdate = strtotime($lockData['lastUpdate']);
            $currentTime = time();
            $minutesAgo = round(($currentTime - $lastUpdate) / 60);
            
            if ($minutesAgo > 30) {
                logTest("Análise pode estar travada (mais de 30 minutos sem atualização)", 'WARNING');
            } else {
                logTest("Análise parece ativa (atualizada há $minutesAgo minutos)", 'OK');
            }
        }
    } else {
        logTest("Dados do lock corrompidos", 'ERROR');
    }
} else {
    logTest("Arquivo de lock não encontrado", 'WARNING');
}

// 5. Verificar arquivo de PID
logTest("\n=== TESTE 5: ARQUIVO DE PID ===");

$pidFile = 'cache/worker.pid';
if (file_exists($pidFile)) {
    logTest("Arquivo PID encontrado", 'OK');
    
    $pid = (int) file_get_contents($pidFile);
    logTest("PID: $pid", 'INFO');
    
    // Verificar se o processo ainda está rodando
    if (function_exists('posix_kill')) {
        if (posix_kill($pid, 0)) {
            logTest("Processo com PID $pid está em execução", 'WARNING');
        } else {
            logTest("Processo com PID $pid não está mais em execução", 'OK');
        }
    } else {
        logTest("Função posix_kill não disponível - não é possível verificar processo", 'WARNING');
    }
} else {
    logTest("Arquivo PID não encontrado", 'OK');
}

// 6. Testar carregamento de dependências
logTest("\n=== TESTE 6: CARREGAMENTO DE DEPENDÊNCIAS ===");

try {
    logTest("Carregando autoloader...", 'INFO');
    require_once 'vendor/autoload.php';
    logTest("Autoloader carregado com sucesso", 'OK');
} catch (Exception $e) {
    logTest("Erro ao carregar autoloader: " . $e->getMessage(), 'ERROR');
}

try {
    logTest("Carregando configuração...", 'INFO');
    require_once 'config.php';
    logTest("Configuração carregada com sucesso", 'OK');
} catch (Exception $e) {
    logTest("Erro ao carregar configuração: " . $e->getMessage(), 'ERROR');
}

// 7. Testar conexão com banco de dados
logTest("\n=== TESTE 7: CONEXÃO COM BANCO DE DADOS ===");

try {
    if (function_exists('conectarBanco')) {
        $pdo = conectarBanco();
        logTest("Conexão com banco estabelecida com sucesso", 'OK');
        
        // Testar uma consulta simples
        $stmt = $pdo->query("SELECT 1 as test");
        $result = $stmt->fetch();
        if ($result && $result['test'] == 1) {
            logTest("Consulta de teste executada com sucesso", 'OK');
        } else {
            logTest("Consulta de teste falhou", 'ERROR');
        }
    } else {
        logTest("Função conectarBanco não encontrada", 'ERROR');
    }
} catch (Exception $e) {
    logTest("Erro na conexão com banco: " . $e->getMessage(), 'ERROR');
}

// 8. Testar execução manual do worker
logTest("\n=== TESTE 8: EXECUÇÃO MANUAL DO WORKER ===");

if (file_exists($lockFile) && isset($lockData) && $lockData['status'] === 'pending') {
    logTest("Tarefa pendente encontrada - testando execução do worker...", 'INFO');
    
    // Criar backup do lock atual
    $backupFile = 'cache/scan.lock.backup.' . date('Y-m-d_H-i-s');
    copy($lockFile, $backupFile);
    logTest("Backup do lock criado: $backupFile", 'OK');
    
    // Executar worker em modo de teste
    logTest("Executando worker (modo de teste)...", 'INFO');
    echo "\n--- SAÍDA DO WORKER ---\n";
    
    $startTime = time();
    $output = [];
    $returnCode = 0;
    
    // Executar worker e capturar saída
    exec("php worker.php 2>&1", $output, $returnCode);
    
    $endTime = time();
    $duration = $endTime - $startTime;
    
    echo implode("\n", $output) . "\n";
    echo "--- FIM DA SAÍDA DO WORKER ---\n\n";
    
    logTest("Worker executado em $duration segundos", 'INFO');
    logTest("Código de retorno: $returnCode", $returnCode === 0 ? 'OK' : 'ERROR');
    
    // Verificar se o status mudou
    if (file_exists($lockFile)) {
        $newLockData = json_decode(file_get_contents($lockFile), true);
        if ($newLockData && $newLockData['status'] !== 'pending') {
            logTest("Status mudou para: " . $newLockData['status'], 'OK');
        } else {
            logTest("Status permaneceu como 'pending'", 'WARNING');
        }
    }
    
    // Restaurar backup se necessário
    if (file_exists($backupFile)) {
        copy($backupFile, $lockFile);
        unlink($backupFile);
        logTest("Backup restaurado", 'OK');
    }
} else {
    logTest("Nenhuma tarefa pendente para testar", 'WARNING');
}

// 9. Verificar logs
logTest("\n=== TESTE 9: LOGS ===");

$logFile = 'logs/worker.log';
if (file_exists($logFile)) {
    logTest("Arquivo de log encontrado", 'OK');
    
    $logContent = file_get_contents($logFile);
    $logLines = explode("\n", $logContent);
    $recentLines = array_slice($logLines, -10); // Últimas 10 linhas
    
    logTest("Últimas 10 linhas do log:", 'INFO');
    foreach ($recentLines as $line) {
        if (trim($line)) {
            echo "  $line\n";
        }
    }
} else {
    logTest("Arquivo de log não encontrado", 'WARNING');
}

// 10. Recomendações
logTest("\n=== RECOMENDAÇÕES ===");

if (!file_exists('logs/worker.log')) {
    logTest("Execute o worker manualmente para gerar logs de diagnóstico", 'WARNING');
}

if (file_exists($lockFile) && isset($lockData) && $lockData['status'] === 'pending') {
    logTest("Execute: php clear-stuck-analysis.php para limpar análise travada", 'WARNING');
}

logTest("Execute: php worker.php para testar execução manual", 'INFO');
logTest("Configure um cron job para execução automática", 'INFO');

echo "\n=== DIAGNÓSTICO CONCLUÍDO ===\n";
?>
