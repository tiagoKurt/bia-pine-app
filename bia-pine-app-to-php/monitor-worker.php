<?php
echo "=== MONITOR DO WORKER ===\n";
echo "Pressione Ctrl+C para sair\n\n";

function clearScreen() {
    if (PHP_OS_FAMILY === 'Windows') {
        system('cls');
    } else {
        system('clear');
    }
}

function formatTime($timestamp) {
    if (!$timestamp) return 'N/A';
    
    $time = strtotime($timestamp);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return $diff . 's atrás';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . 'm atrás';
    } else {
        return floor($diff / 3600) . 'h atrás';
    }
}
function displayStatus() {
    $lockFile = __DIR__ . '/cache/scan.lock';
    $pidFile = __DIR__ . '/cache/worker.pid';
    $logFile = __DIR__ . '/logs/worker.log';
    
    echo "┌─────────────────────────────────────────────────────────────┐\n";
    echo "│                    MONITOR DO WORKER                       │\n";
    echo "├─────────────────────────────────────────────────────────────┤\n";
    
    if (file_exists($lockFile)) {
        $lockData = json_decode(file_get_contents($lockFile), true);
        if ($lockData) {
            $status = $lockData['status'] ?? 'unknown';
            $startTime = $lockData['startTime'] ?? null;
            $lastUpdate = $lockData['lastUpdate'] ?? null;
            $progress = $lockData['progress'] ?? [];
            
            echo "│ Status da Análise: " . str_pad($status, 40) . " │\n";
            echo "│ Iniciada em: " . str_pad(formatTime($startTime), 40) . " │\n";
            echo "│ Última atualização: " . str_pad(formatTime($lastUpdate), 30) . " │\n";
            
            if (!empty($progress)) {
                echo "├─────────────────────────────────────────────────────────────┤\n";
                echo "│                    PROGRESSO                                │\n";
                echo "├─────────────────────────────────────────────────────────────┤\n";
                
                $datasets = $progress['datasets_analisados'] ?? 0;
                $recursos = $progress['recursos_analisados'] ?? 0;
                $comCpf = $progress['recursos_com_cpfs'] ?? 0;
                $cpfsSalvos = $progress['total_cpfs_salvos'] ?? 0;
                $currentStep = $progress['current_step'] ?? 'Processando...';
                
                echo "│ Datasets analisados: " . str_pad($datasets, 30) . " │\n";
                echo "│ Recursos analisados: " . str_pad($recursos, 30) . " │\n";
                echo "│ Recursos com CPFs: " . str_pad($comCpf, 32) . " │\n";
                echo "│ CPFs salvos: " . str_pad($cpfsSalvos, 37) . " │\n";
                echo "│ Etapa atual: " . str_pad(substr($currentStep, 0, 40), 40) . " │\n";
            }
        } else {
            echo "│ Status: " . str_pad("Dados corrompidos", 40) . " │\n";
        }
    } else {
        echo "│ Status: " . str_pad("Nenhuma análise ativa", 40) . " │\n";
    }
    
    echo "├─────────────────────────────────────────────────────────────┤\n";
    echo "│                    PROCESSO                                 │\n";
    echo "├─────────────────────────────────────────────────────────────┤\n";
    
    if (file_exists($pidFile)) {
        $pid = (int) file_get_contents($pidFile);
        echo "│ PID: " . str_pad($pid, 50) . " │\n";
        
        if (function_exists('posix_kill')) {
            if (posix_kill($pid, 0)) {
                echo "│ Status: " . str_pad("Em execução", 40) . " │\n";
            } else {
                echo "│ Status: " . str_pad("Processo não encontrado", 40) . " │\n";
            }
        } else {
            echo "│ Status: " . str_pad("Não é possível verificar", 40) . " │\n";
        }
    } else {
        echo "│ Status: " . str_pad("Nenhum processo ativo", 40) . " │\n";
    }
    
    echo "├─────────────────────────────────────────────────────────────┤\n";
    echo "│                    LOGS RECENTES                            │\n";
    echo "├─────────────────────────────────────────────────────────────┤\n";
    
    if (file_exists($logFile)) {
        $logContent = file_get_contents($logFile);
        $logLines = explode("\n", $logContent);
        $recentLines = array_slice($logLines, -5);
        
        foreach ($recentLines as $line) {
            if (trim($line)) {
                $displayLine = substr($line, 0, 55);
                echo "│ " . str_pad($displayLine, 55) . " │\n";
            }
        }
    } else {
        echo "│ Nenhum log encontrado                                  │\n";
    }
    
    echo "└─────────────────────────────────────────────────────────────┘\n";
    echo "Atualizado em: " . date('Y-m-d H:i:s') . "\n";
}

$running = true;
$lastUpdate = 0;

if (function_exists('pcntl_signal') && defined('SIGINT')) {
    pcntl_signal(SIGINT, function() use (&$running) {
        echo "\n\nMonitor interrompido pelo usuário.\n";
        $running = false;
    });
}

while ($running) {
    clearScreen();
    displayStatus();
    
    if (!$running) {
        break;
    }
    
    sleep(5);
    
    if (function_exists('pcntl_signal_dispatch')) {
        pcntl_signal_dispatch();
    }
}

echo "\nMonitor finalizado.\n";
?>
