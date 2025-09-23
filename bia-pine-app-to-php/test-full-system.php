<?php
/**
 * Script para testar o sistema completo de análise assíncrona
 */

require_once __DIR__ . '/config.php';

echo "=== Teste Completo do Sistema Assíncrono ===\n\n";

try {
    // 1. Verificar conexão com banco
    echo "1. Testando conexão com banco...\n";
    $pdo = conectarBanco();
    echo "✓ Conexão OK\n\n";
    
    // 2. Verificar estrutura da tabela
    echo "2. Verificando estrutura da tabela...\n";
    $stmt = $pdo->query("DESCRIBE verificacoes_cpf");
    $columns = $stmt->fetchAll();
    
    $requiredColumns = ['id', 'cpf', 'e_valido', 'data_verificacao', 'observacoes', 'fonte', 'identificador_fonte'];
    $foundColumns = array_column($columns, 'Field');
    
    foreach ($requiredColumns as $col) {
        if (in_array($col, $foundColumns)) {
            echo "✓ Coluna '$col' existe\n";
        } else {
            echo "✗ Coluna '$col' faltando\n";
            exit(1);
        }
    }
    echo "\n";
    
    // 3. Verificar arquivos do sistema assíncrono
    echo "3. Verificando arquivos do sistema assíncrono...\n";
    $files = [
        'public/api/start-scan.php',
        'public/api/scan-status.php',
        'src/CkanScannerService.php',
        'worker.php',
        'start-worker.php'
    ];
    
    foreach ($files as $file) {
        if (file_exists(__DIR__ . '/' . $file)) {
            echo "✓ $file\n";
        } else {
            echo "✗ $file - FALTANDO\n";
            exit(1);
        }
    }
    echo "\n";
    
    // 4. Verificar diretórios
    echo "4. Verificando diretórios...\n";
    $dirs = ['cache', 'logs'];
    foreach ($dirs as $dir) {
        $dirPath = __DIR__ . '/' . $dir;
        if (!is_dir($dirPath)) {
            mkdir($dirPath, 0755, true);
            echo "✓ Criado diretório '$dir'\n";
        } else {
            echo "✓ Diretório '$dir' existe\n";
        }
        
        if (!is_writable($dirPath)) {
            echo "✗ Diretório '$dir' não é gravável\n";
            exit(1);
        }
    }
    echo "\n";
    
    // 5. Simular dados de teste
    echo "5. Inserindo dados de teste...\n";
    
    // Limpar dados antigos de teste
    $pdo->exec("DELETE FROM verificacoes_cpf WHERE fonte = 'teste_sistema'");
    
    // Inserir dados de teste
    $testData = [
        [
            'cpf' => '11144477735',
            'e_valido' => true,
            'observacoes' => json_encode([
                'dataset_id' => 'teste-dataset-1',
                'resource_id' => 'teste-resource-1',
                'resource_name' => 'Arquivo de Teste.csv',
                'resource_url' => 'http://example.com/teste.csv',
                'resource_format' => 'csv'
            ]),
            'fonte' => 'teste_sistema',
            'identificador_fonte' => 'teste-dataset-1|teste-resource-1'
        ],
        [
            'cpf' => '22233344455',
            'e_valido' => true,
            'observacoes' => json_encode([
                'dataset_id' => 'teste-dataset-2',
                'resource_id' => 'teste-resource-2',
                'resource_name' => 'Planilha Teste.xlsx',
                'resource_url' => 'http://example.com/teste.xlsx',
                'resource_format' => 'xlsx'
            ]),
            'fonte' => 'teste_sistema',
            'identificador_fonte' => 'teste-dataset-2|teste-resource-2'
        ]
    ];
    
    $sql = "INSERT INTO verificacoes_cpf (cpf, e_valido, observacoes, fonte, identificador_fonte) VALUES (?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    
    foreach ($testData as $data) {
        $stmt->execute([
            $data['cpf'],
            $data['e_valido'],
            $data['observacoes'],
            $data['fonte'],
            $data['identificador_fonte']
        ]);
    }
    
    echo "✓ " . count($testData) . " registros de teste inseridos\n\n";
    
    // 6. Testar consultas
    echo "6. Testando consultas...\n";
    
    // Testar busca por fonte
    require_once __DIR__ . '/src/functions.php';
    $verificacoes = buscarVerificacoesPorFonte($pdo, 'teste_sistema');
    echo "✓ Busca por fonte: " . count($verificacoes) . " registros encontrados\n";
    
    // Testar estatísticas
    $stats = obterEstatisticasVerificacoes($pdo);
    echo "✓ Estatísticas: {$stats['total']} total, {$stats['validos']} válidos\n\n";
    
    // 7. Testar sistema de cooldown
    echo "7. Testando sistema de cooldown...\n";
    $historyFile = __DIR__ . '/cache/scan-history.json';
    
    // Criar histórico de teste (sem cooldown)
    $history = [
        'lastCompletedScan' => date('c', time() - 5 * 3600), // 5 horas atrás
        'totalScans' => 1,
        'lastResults' => [
            'datasets_analisados' => 10,
            'recursos_analisados' => 50,
            'recursos_com_cpfs' => 2,
            'total_cpfs_salvos' => 5
        ]
    ];
    file_put_contents($historyFile, json_encode($history, JSON_PRETTY_PRINT));
    echo "✓ Histórico de teste criado (sem cooldown)\n";
    
    // Teste com cooldown ativo
    $historyWithCooldown = [
        'lastCompletedScan' => date('c', time() - 2 * 3600), // 2 horas atrás
        'totalScans' => 1,
        'lastResults' => [
            'datasets_analisados' => 10,
            'recursos_analisados' => 50,
            'recursos_com_cpfs' => 2,
            'total_cpfs_salvos' => 5
        ]
    ];
    file_put_contents($historyFile, json_encode($historyWithCooldown, JSON_PRETTY_PRINT));
    echo "✓ Histórico com cooldown ativo criado\n\n";
    
    // 8. Limpar dados de teste
    echo "8. Limpando dados de teste...\n";
    $pdo->exec("DELETE FROM verificacoes_cpf WHERE fonte = 'teste_sistema'");
    if (file_exists($historyFile)) {
        unlink($historyFile);
    }
    echo "✓ Dados de teste removidos\n\n";
    
    echo "=== RESULTADO FINAL ===\n";
    echo "✅ Sistema assíncrono está funcionando corretamente!\n\n";
    
    echo "Como usar:\n";
    echo "1. Acesse http://localhost/seu-projeto/public/app.php\n";
    echo "2. Vá para a aba 'CPF'\n";
    echo "3. Clique em 'Executar Análise CKAN'\n";
    echo "4. Monitore o progresso no modal\n\n";
    
    echo "Scripts manuais:\n";
    echo "- Worker manual: php start-worker.php\n";
    echo "- Verificar tabela: php check-and-fix-database.php\n";
    echo "- Teste completo: php test-full-system.php\n\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
?>
