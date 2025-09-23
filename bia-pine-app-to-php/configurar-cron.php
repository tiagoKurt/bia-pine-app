<?php
/**
 * Script para configurar o Cron Job automaticamente
 * 
 * Este script ajuda a configurar o cron job para executar
 * o worker automaticamente
 */

echo "=== CONFIGURADOR DE CRON JOB ===\n\n";

// Função para logging
function logCron($message, $status = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $symbol = $status === 'OK' ? '✅' : ($status === 'ERROR' ? '❌' : ($status === 'WARNING' ? '⚠️' : 'ℹ️'));
    echo "[$timestamp] $symbol $message\n";
}

// Detectar sistema operacional
$isWindows = stripos(PHP_OS, 'WIN') !== false;
$isUnix = !$isWindows;

logCron("Sistema detectado: " . ($isWindows ? 'Windows' : 'Unix/Linux'), 'OK');

// Caminhos absolutos
$projectDir = realpath(__DIR__);
$workerPath = $projectDir . '/worker.php';
$cronScript = $projectDir . '/cron-worker.sh';
$logFile = $projectDir . '/logs/cron.log';

logCron("Diretório do projeto: $projectDir", 'OK');
logCron("Caminho do worker: $workerPath", 'OK');

// Verificar se os arquivos existem
if (!file_exists($workerPath)) {
    logCron("Arquivo worker.php não encontrado!", 'ERROR');
    exit(1);
}

if ($isUnix && !file_exists($cronScript)) {
    logCron("Script cron-worker.sh não encontrado!", 'ERROR');
    exit(1);
}

// Criar diretório de logs se não existir
if (!is_dir($projectDir . '/logs')) {
    mkdir($projectDir . '/logs', 0755, true);
    logCron("Diretório de logs criado", 'OK');
}

if ($isWindows) {
    logCron("=== CONFIGURAÇÃO PARA WINDOWS ===");
    logCron("Windows não suporta cron nativamente", 'WARNING');
    logCron("Use o Agendador de Tarefas do Windows:", 'INFO');
    logCron("1. Abra o Agendador de Tarefas", 'INFO');
    logCron("2. Crie uma nova tarefa", 'INFO');
    logCron("3. Configure para executar a cada minuto", 'INFO');
    logCron("4. Ação: Executar programa", 'INFO');
    logCron("5. Programa: php", 'INFO');
    logCron("6. Argumentos: \"$workerPath\"", 'INFO');
    logCron("7. Diretório inicial: \"$projectDir\"", 'INFO');
    
    // Criar script batch para facilitar
    $batchContent = "@echo off\n";
    $batchContent .= "cd /d \"$projectDir\"\n";
    $batchContent .= "php \"$workerPath\" >> \"$logFile\" 2>&1\n";
    
    $batchFile = $projectDir . '/executar-worker.bat';
    file_put_contents($batchFile, $batchContent);
    logCron("Script batch criado: $batchFile", 'OK');
    
} else {
    logCron("=== CONFIGURAÇÃO PARA UNIX/LINUX ===");
    
    // Verificar se o script cron tem permissão de execução
    if (!is_executable($cronScript)) {
        chmod($cronScript, 0755);
        logCron("Permissão de execução adicionada ao script cron", 'OK');
    }
    
    // Criar entrada do crontab
    $cronEntry = "* * * * * $cronScript >> $logFile 2>&1";
    
    logCron("Entrada do crontab sugerida:", 'INFO');
    logCron("$cronEntry", 'INFO');
    
    // Tentar adicionar automaticamente ao crontab
    logCron("Tentando adicionar ao crontab automaticamente...", 'INFO');
    
    // Verificar se já existe uma entrada
    $currentCrontab = shell_exec('crontab -l 2>/dev/null');
    if (strpos($currentCrontab, $projectDir) !== false) {
        logCron("Entrada já existe no crontab", 'WARNING');
    } else {
        // Adicionar nova entrada
        $newCrontab = $currentCrontab . "\n" . $cronEntry . "\n";
        $tempCrontabFile = tempnam(sys_get_temp_dir(), 'crontab_');
        file_put_contents($tempCrontabFile, $newCrontab);
        
        $result = shell_exec("crontab $tempCrontabFile 2>&1");
        unlink($tempCrontabFile);
        
        if (empty($result)) {
            logCron("Crontab atualizado com sucesso!", 'OK');
        } else {
            logCron("Erro ao atualizar crontab: $result", 'ERROR');
            logCron("Execute manualmente: crontab -e", 'INFO');
            logCron("Adicione a linha: $cronEntry", 'INFO');
        }
    }
}

// Verificar configuração atual
logCron("\n=== VERIFICAÇÃO DA CONFIGURAÇÃO ===");

if ($isUnix) {
    $crontab = shell_exec('crontab -l 2>/dev/null');
    if ($crontab && strpos($crontab, $projectDir) !== false) {
        logCron("Cron job configurado corretamente", 'OK');
        
        // Mostrar entradas relacionadas
        $lines = explode("\n", $crontab);
        foreach ($lines as $line) {
            if (strpos($line, $projectDir) !== false) {
                logCron("Entrada encontrada: $line", 'INFO');
            }
        }
    } else {
        logCron("Cron job não encontrado", 'WARNING');
    }
}

// Testar execução
logCron("\n=== TESTE DE EXECUÇÃO ===");
logCron("Testando execução do worker...", 'INFO');

$testOutput = [];
$testReturnCode = 0;
exec("php \"$workerPath\" 2>&1", $testOutput, $testReturnCode);

if ($testReturnCode === 0) {
    logCron("Worker executado com sucesso", 'OK');
} else {
    logCron("Worker falhou com código: $testReturnCode", 'ERROR');
    logCron("Saída: " . implode("\n", $testOutput), 'INFO');
}

// Instruções finais
logCron("\n=== INSTRUÇÕES FINAIS ===");
logCron("1. Execute: php diagnostico-worker.php para diagnóstico completo", 'INFO');
logCron("2. Monitore os logs em: $logFile", 'INFO');
logCron("3. Para parar o cron: crontab -e (Unix) ou Agendador de Tarefas (Windows)", 'INFO');
logCron("4. Para testar manualmente: php $workerPath", 'INFO');

echo "\n=== CONFIGURAÇÃO CONCLUÍDA ===\n";
?>
