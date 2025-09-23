<?php
/**
 * Script para verificar e corrigir a estrutura da tabela verificacoes_cpf
 */

require_once __DIR__ . '/config.php';

echo "=== Verificando e corrigindo estrutura da tabela ===\n\n";

try {
    $pdo = conectarBanco();
    
    // Verificar se a coluna identificador_fonte existe
    $stmt = $pdo->query("DESCRIBE verificacoes_cpf");
    $columns = $stmt->fetchAll();
    
    $hasIdentificadorFonte = false;
    $hasFonte = false;
    
    foreach ($columns as $column) {
        if ($column['Field'] === 'identificador_fonte') {
            $hasIdentificadorFonte = true;
        }
        if ($column['Field'] === 'fonte') {
            $hasFonte = true;
        }
    }
    
    // Adicionar coluna fonte se não existir
    if (!$hasFonte) {
        echo "Adicionando coluna 'fonte'...\n";
        $pdo->exec("ALTER TABLE verificacoes_cpf ADD COLUMN fonte VARCHAR(100) NULL AFTER observacoes");
        echo "✓ Coluna 'fonte' adicionada.\n";
    } else {
        echo "✓ Coluna 'fonte' já existe.\n";
    }
    
    // Adicionar coluna identificador_fonte se não existir
    if (!$hasIdentificadorFonte) {
        echo "Adicionando coluna 'identificador_fonte'...\n";
        $pdo->exec("ALTER TABLE verificacoes_cpf ADD COLUMN identificador_fonte VARCHAR(255) NULL AFTER fonte");
        echo "✓ Coluna 'identificador_fonte' adicionada.\n";
        
        // Criar índice
        echo "Criando índice para 'identificador_fonte'...\n";
        $pdo->exec("CREATE INDEX idx_identificador_fonte ON verificacoes_cpf (identificador_fonte)");
        echo "✓ Índice criado.\n";
        
        // Criar índice para fonte também
        echo "Criando índice para 'fonte'...\n";
        $pdo->exec("CREATE INDEX idx_fonte ON verificacoes_cpf (fonte)");
        echo "✓ Índice para fonte criado.\n";
    } else {
        echo "✓ Coluna 'identificador_fonte' já existe.\n";
    }
    
    // Verificar estrutura final
    echo "\nEstrutura atual da tabela:\n";
    $stmt = $pdo->query("DESCRIBE verificacoes_cpf");
    $columns = $stmt->fetchAll();
    
    foreach ($columns as $column) {
        echo "  - {$column['Field']} ({$column['Type']}) " . 
             ($column['Null'] === 'NO' ? 'NOT NULL' : 'NULL') . "\n";
    }
    
    echo "\n✅ Estrutura da tabela verificada e corrigida!\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
?>
