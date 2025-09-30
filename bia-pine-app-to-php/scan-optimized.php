<?php
/**
 * Script principal para execução da varredura otimizada de CPF no CKAN
 * Implementa paralelismo e baixo consumo de memória
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

use App\OptimizedCkanScanner;

// Configurações de execução
ini_set('memory_limit', '8G'); // Aumentado para 4GB
ini_set('max_execution_time', 0); // Sem limite de tempo
set_time_limit(0);

echo "=== SCANNER OTIMIZADO DE CPF NO CKAN ===\n";
echo "Iniciando varredura com paralelismo e baixo consumo de memória...\n\n";

try {
    // Conecta ao banco de dados
    $pdo = conectarBanco();
    echo "✓ Conexão com banco de dados estabelecida\n";

    // Cria o scanner otimizado
    $scanner = new OptimizedCkanScanner($pdo);
    
    // Define callback de progresso
    $scanner->setProgressCallback(function($data) {
        if (isset($data['current_step'])) {
            echo "📊 " . $data['current_step'] . "\n";
        }
        if (isset($data['progress'])) {
            echo "⏳ Progresso: " . $data['progress'] . "%\n";
        }
    });

    // Executa a varredura otimizada
    echo "\n🚀 Iniciando varredura otimizada...\n";
    $resultado = $scanner->executarVarreduraOtimizada();

    // Exibe resultados
    echo "\n=== RESULTADOS DA VARREURA ===\n";
    echo "Status: " . $resultado['status'] . "\n";
    echo "Mensagem: " . $resultado['message'] . "\n";
    echo "Total de recursos: " . $resultado['total_recursos'] . "\n";
    echo "Recursos processados: " . $resultado['recursos_processados'] . "\n";
    echo "Recursos com CPFs: " . $resultado['recursos_com_cpfs'] . "\n";
    echo "Total de CPFs encontrados: " . $resultado['total_cpfs'] . "\n";
    echo "Tempo de execução: " . number_format($resultado['tempo_execucao'], 2) . " segundos\n";

    // Obtém estatísticas do banco
    echo "\n=== ESTATÍSTICAS DO BANCO ===\n";
    $stats = $scanner->obterEstatisticas();
    echo "Total de recursos no banco: " . $stats['total_recursos'] . "\n";
    echo "Total de CPFs no banco: " . $stats['total_cpfs'] . "\n";
    echo "Primeira verificação: " . ($stats['primeira_verificacao'] ?? 'N/A') . "\n";
    echo "Última verificação: " . ($stats['ultima_verificacao'] ?? 'N/A') . "\n";

    echo "\n✅ Varredura concluída com sucesso!\n";

} catch (Exception $e) {
    echo "\n❌ Erro durante a varredura: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
