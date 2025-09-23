<?php
/**
 * Script de Instalação - Sistema de Verificação de CPF
 * 
 * Este script automatiza a instalação e configuração inicial
 * do sistema de verificação de CPF.
 */

require_once __DIR__ . '/config.php';

echo "=== INSTALAÇÃO DO SISTEMA DE VERIFICAÇÃO DE CPF ===\n\n";

// Verificar se o PHP tem as extensões necessárias
echo "1. Verificando dependências do PHP...\n";

$extensoes_necessarias = ['pdo', 'pdo_mysql', 'json'];
$extensoes_faltando = [];

foreach ($extensoes_necessarias as $ext) {
    if (!extension_loaded($ext)) {
        $extensoes_faltando[] = $ext;
    }
}

if (!empty($extensoes_faltando)) {
    echo "❌ ERRO: Extensões PHP necessárias não encontradas:\n";
    foreach ($extensoes_faltando as $ext) {
        echo "   - $ext\n";
    }
    echo "\nInstale as extensões necessárias e tente novamente.\n";
    exit(1);
}

echo "✅ Todas as extensões PHP necessárias estão instaladas.\n\n";

// Verificar configuração do banco de dados
echo "2. Verificando configuração do banco de dados...\n";

try {
    $dsn = sprintf(
        '%s:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        DB_CONNECTION,
        DB_HOST,
        DB_PORT,
        DB_DATABASE
    );
    
    $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    echo "✅ Conexão com banco de dados estabelecida com sucesso.\n";
    
} catch (PDOException $e) {
    echo "❌ ERRO: Falha na conexão com banco de dados:\n";
    echo "   " . $e->getMessage() . "\n\n";
    echo "Verifique as configurações em config.php:\n";
    echo "   - DB_HOST: " . DB_HOST . "\n";
    echo "   - DB_PORT: " . DB_PORT . "\n";
    echo "   - DB_DATABASE: " . DB_DATABASE . "\n";
    echo "   - DB_USERNAME: " . DB_USERNAME . "\n";
    echo "   - DB_PASSWORD: " . (DB_PASSWORD ? '[CONFIGURADO]' : '[VAZIO]') . "\n\n";
    exit(1);
}

// Criar tabela verificacoes_cpf
echo "3. Criando tabela verificacoes_cpf...\n";

try {
    $sqlFile = __DIR__ . '/database/schema.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("Arquivo schema.sql não encontrado em database/");
    }
    
    $sql = file_get_contents($sqlFile);
    $pdo->exec($sql);
    
    echo "✅ Tabela verificacoes_cpf criada com sucesso.\n";
    
} catch (Exception $e) {
    echo "❌ ERRO ao criar tabela:\n";
    echo "   " . $e->getMessage() . "\n";
    exit(1);
}

// Verificar se a tabela foi criada corretamente
echo "4. Verificando estrutura da tabela...\n";

try {
    $stmt = $pdo->query("DESCRIBE verificacoes_cpf");
    $colunas = $stmt->fetchAll();
    
    $colunas_esperadas = ['id', 'cpf', 'e_valido', 'data_verificacao', 'observacoes'];
    $colunas_encontradas = array_column($colunas, 'Field');
    
    $colunas_faltando = array_diff($colunas_esperadas, $colunas_encontradas);
    
    if (!empty($colunas_faltando)) {
        echo "❌ ERRO: Colunas faltando na tabela:\n";
        foreach ($colunas_faltando as $coluna) {
            echo "   - $coluna\n";
        }
        exit(1);
    }
    
    echo "✅ Estrutura da tabela verificada com sucesso.\n";
    
} catch (Exception $e) {
    echo "❌ ERRO ao verificar estrutura da tabela:\n";
    echo "   " . $e->getMessage() . "\n";
    exit(1);
}

// Testar funções de validação
echo "5. Testando funções de validação...\n";

require_once __DIR__ . '/src/functions.php';

// CPFs de teste
$cpfs_teste = [
    '11144477735' => true,  // CPF válido
    '00000000000' => false, // CPF inválido (todos iguais)
    '12345678901' => false, // CPF inválido (dígitos verificadores incorretos)
    '123456789' => false,   // CPF inválido (menos de 11 dígitos)
];

$testes_passaram = true;

foreach ($cpfs_teste as $cpf => $esperado) {
    $resultado = validaCPF($cpf);
    if ($resultado !== $esperado) {
        echo "❌ ERRO: Validação falhou para CPF $cpf (esperado: " . ($esperado ? 'válido' : 'inválido') . ", obtido: " . ($resultado ? 'válido' : 'inválido') . ")\n";
        $testes_passaram = false;
    }
}

if ($testes_passaram) {
    echo "✅ Funções de validação testadas com sucesso.\n";
} else {
    echo "❌ ERRO: Alguns testes de validação falharam.\n";
    exit(1);
}

// Testar operações de banco de dados
echo "6. Testando operações de banco de dados...\n";

try {
    // Testar inserção
    $cpf_teste = '11144477735';
    $resultado_insercao = salvarVerificacaoCPF($pdo, $cpf_teste, true, 'Teste de instalação');
    
    if (!$resultado_insercao) {
        throw new Exception("Falha ao inserir registro de teste");
    }
    
    // Testar busca
    $verificacao = buscarVerificacaoPorCPF($pdo, $cpf_teste);
    if (!$verificacao) {
        throw new Exception("Falha ao buscar registro inserido");
    }
    
    // Testar estatísticas
    $estatisticas = obterEstatisticasVerificacoes($pdo);
    if ($estatisticas['total'] < 1) {
        throw new Exception("Estatísticas não refletem o registro inserido");
    }
    
    // Limpar registro de teste
    $pdo->prepare("DELETE FROM verificacoes_cpf WHERE cpf = ?")->execute([$cpf_teste]);
    
    echo "✅ Operações de banco de dados testadas com sucesso.\n";
    
} catch (Exception $e) {
    echo "❌ ERRO ao testar operações de banco de dados:\n";
    echo "   " . $e->getMessage() . "\n";
    exit(1);
}

// Verificar permissões de diretórios
echo "7. Verificando permissões de diretórios...\n";

$diretorios_necessarios = [
    __DIR__ . '/logs',
    __DIR__ . '/cache',
    __DIR__ . '/database'
];

foreach ($diretorios_necessarios as $dir) {
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            echo "❌ ERRO: Não foi possível criar diretório $dir\n";
            exit(1);
        }
    }
    
    if (!is_writable($dir)) {
        echo "❌ ERRO: Diretório $dir não tem permissão de escrita\n";
        exit(1);
    }
}

echo "✅ Permissões de diretórios verificadas.\n";

// Verificar arquivos necessários
echo "8. Verificando arquivos necessários...\n";

$arquivos_necessarios = [
    'public/cpf.php',
    'public/api/cpf.php',
    'public/api/scan-ckan.php',
    'src/functions.php',
    'config/db.php',
    'database/schema.sql'
];

foreach ($arquivos_necessarios as $arquivo) {
    if (!file_exists(__DIR__ . '/' . $arquivo)) {
        echo "❌ ERRO: Arquivo necessário não encontrado: $arquivo\n";
        exit(1);
    }
}

echo "✅ Todos os arquivos necessários estão presentes.\n";

// Verificar scanner CKAN
echo "9. Verificando scanner CKAN...\n";

$scanner_arquivos = [
    '../cpf-ckan-scanner/bin/scan-with-database.php',
    '../cpf-ckan-scanner/src/Integration/CpfVerificationService.php',
    '../cpf-ckan-scanner/env.example'
];

$scanner_ok = true;
foreach ($scanner_arquivos as $arquivo) {
    if (!file_exists(__DIR__ . '/' . $arquivo)) {
        echo "⚠️  AVISO: Arquivo do scanner CKAN não encontrado: $arquivo\n";
        $scanner_ok = false;
    }
}

if ($scanner_ok) {
    echo "✅ Scanner CKAN está disponível.\n";
} else {
    echo "⚠️  Scanner CKAN não está completamente configurado. Algumas funcionalidades podem não funcionar.\n";
}

// Instalação concluída
echo "\n=== INSTALAÇÃO CONCLUÍDA COM SUCESSO! ===\n\n";

echo "📋 PRÓXIMOS PASSOS:\n\n";

echo "1. Acesse a interface web:\n";
echo "   http://localhost/bia-pine-app-to-php/public/cpf.php\n\n";

echo "2. Configure o scanner CKAN (opcional):\n";
echo "   - Copie cpf-ckan-scanner/env.example para cpf-ckan-scanner/.env\n";
echo "   - Configure as variáveis de ambiente no arquivo .env\n";
echo "   - Execute: cd cpf-ckan-scanner && composer install\n\n";

echo "3. Teste a API REST:\n";
echo "   GET  http://localhost/bia-pine-app-to-php/public/api/cpf/verify?cpf=11144477735\n";
echo "   POST http://localhost/bia-pine-app-to-php/public/api/cpf/verify\n";
echo "   GET  http://localhost/bia-pine-app-to-php/public/api/cpf/stats\n";
echo "   POST http://localhost/bia-pine-app-to-php/public/api/scan-ckan.php\n\n";

echo "4. Configure seu servidor web (Apache/Nginx) para apontar para o diretório 'public/'\n\n";

echo "5. Para desenvolvimento, você pode usar o servidor embutido do PHP:\n";
echo "   cd bia-pine-app-to-php\n";
echo "   php -S localhost:8000 -t public\n\n";

echo "📚 DOCUMENTAÇÃO:\n";
echo "   - Interface Web: public/cpf.php\n";
echo "   - API REST: public/api/cpf.php\n";
echo "   - Scanner CKAN: public/api/scan-ckan.php\n";
echo "   - Funções: src/functions.php\n";
echo "   - Configuração: config/db.php\n\n";

echo "🔍 FUNCIONALIDADES:\n";
echo "   - Verificação individual de CPF\n";
echo "   - Histórico de verificações\n";
echo "   - Análise automática de recursos CKAN\n";
echo "   - Exportação de dados em CSV\n";
echo "   - API REST completa\n\n";

echo "✅ Sistema pronto para uso!\n";
