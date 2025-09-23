<?php
/**
 * Script para limpar análises travadas
 */

$lockFile = __DIR__ . '/cache/scan.lock';

echo "=== Limpador de Análises Travadas ===\n\n";

if (!file_exists($lockFile)) {
    echo "✅ Nenhuma análise em andamento encontrada.\n";
    exit(0);
}

// Ler dados do lock
$lockData = json_decode(file_get_contents($lockFile), true);

if (!$lockData) {
    echo "❌ Erro ao ler arquivo de lock. Removendo arquivo corrompido...\n";
    unlink($lockFile);
    echo "✅ Arquivo corrompido removido.\n";
    exit(0);
}

echo "📊 Status atual da análise:\n";
echo "   - Status: " . ($lockData['status'] ?? 'indefinido') . "\n";
echo "   - Iniciada em: " . ($lockData['startTime'] ?? 'indefinido') . "\n";
echo "   - Última atualização: " . ($lockData['lastUpdate'] ?? 'indefinido') . "\n";

if (isset($lockData['lastUpdate'])) {
    $lastUpdate = strtotime($lockData['lastUpdate']);
    $currentTime = time();
    $minutesAgo = round(($currentTime - $lastUpdate) / 60);
    
    echo "   - Tempo sem atualização: {$minutesAgo} minutos\n";
    
    if ($minutesAgo > 30) {
        echo "\n⚠️  Análise travada detectada (mais de 30 minutos sem atualização)\n";
        echo "🗑️  Removendo arquivo de lock...\n";
        
        unlink($lockFile);
        echo "✅ Análise travada removida com sucesso!\n";
        echo "✅ Agora você pode executar uma nova análise.\n";
    } else {
        echo "\n✅ Análise parece estar ativa (menos de 30 minutos sem atualização)\n";
        echo "ℹ️  Se você tem certeza de que está travada, remova manualmente:\n";
        echo "   rm " . $lockFile . "\n";
    }
} else {
    echo "\n⚠️  Dados de lock incompletos\n";
    echo "🗑️  Removendo arquivo de lock...\n";
    
    unlink($lockFile);
    echo "✅ Arquivo de lock removido!\n";
}

echo "\n=== Concluído ===\n";
?>
