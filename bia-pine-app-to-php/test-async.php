<?php
/**
 * Script de teste para verificar se o sistema assíncrono está funcionando
 */

echo "=== Teste do Sistema Assíncrono CKAN ===\n\n";

// 1. Verificar estrutura de arquivos
echo "1. Verificando arquivos necessários...\n";

$requiredFiles = [
    'public/api/start-scan.php',
    'public/api/scan-status.php', 
    'src/CkanScannerService.php',
    'worker.php',
    'start-worker.php'
];

$missing = [];
foreach ($requiredFiles as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "   ✓ {$file}\n";
    } else {
        echo "   ✗ {$file} - FALTANDO\n";
        $missing[] = $file;
    }
}

if (!empty($missing)) {
    echo "\nERRO: Arquivos faltando. Sistema não funcionará corretamente.\n";
    exit(1);
}

// 2. Verificar diretório cache
echo "\n2. Verificando diretório cache...\n";
$cacheDir = __DIR__ . '/cache';
if (!is_dir($cacheDir)) {
    echo "   → Criando diretório cache...\n";
    mkdir($cacheDir, 0755, true);
}

if (is_writable($cacheDir)) {
    echo "   ✓ Diretório cache é gravável\n";
} else {
    echo "   ✗ Diretório cache não é gravável\n";
    exit(1);
}

// 3. Verificar variáveis de ambiente
echo "\n3. Verificando configuração...\n";

try {
    require_once __DIR__ . '/vendor/autoload.php';
    require_once __DIR__ . '/config.php';
    
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
    
    $required = ['CKAN_API_URL', 'DB_HOST', 'DB_DATABASE', 'DB_USERNAME'];
    foreach ($required as $var) {
        if (isset($_ENV[$var])) {
            echo "   ✓ {$var} = " . substr($_ENV[$var], 0, 20) . "...\n";
        } else {
            echo "   ✗ {$var} - NÃO DEFINIDA\n";
            exit(1);
        }
    }
    
} catch (Exception $e) {
    echo "   ✗ Erro ao carregar configuração: " . $e->getMessage() . "\n";
    exit(1);
}

// 4. Testar conexão com banco
echo "\n4. Testando conexão com banco...\n";
try {
    $pdo = conectarBanco();
    echo "   ✓ Conexão com banco OK\n";
} catch (Exception $e) {
    echo "   ✗ Erro de conexão: " . $e->getMessage() . "\n";
    exit(1);
}

// 5. Testar API endpoints
echo "\n5. Testando endpoints da API...\n";

// Teste scan-status
$baseUrl = 'http://localhost' . dirname($_SERVER['SCRIPT_NAME'] ?? '/test-async.php');
$statusUrl = $baseUrl . '/public/api/scan-status.php';

$context = stream_context_create([
    'http' => [
        'timeout' => 10,
        'ignore_errors' => true
    ]
]);

$response = @file_get_contents($statusUrl, false, $context);
if ($response !== false) {
    $data = json_decode($response, true);
    if ($data && isset($data['inProgress'])) {
        echo "   ✓ scan-status.php respondendo\n";
        echo "     Status atual: " . ($data['status'] ?? 'unknown') . "\n";
    } else {
        echo "   ? scan-status.php responde mas formato inesperado\n";
    }
} else {
    echo "   ! Não foi possível testar scan-status.php via HTTP\n";
    echo "     (Isso é normal se o servidor web não estiver rodando)\n";
}

// 6. Verificar classes necessárias
echo "\n6. Verificando classes PHP...\n";

$classes = [
    'CpfScanner\Ckan\CkanApiClient',
    'CpfScanner\Parsing\Factory\FileParserFactory', 
    'CpfScanner\Scanning\Strategy\LogicBasedScanner',
    'CpfScanner\Integration\CpfVerificationService'
];

foreach ($classes as $class) {
    if (class_exists($class)) {
        echo "   ✓ {$class}\n";
    } else {
        echo "   ✗ {$class} - NÃO ENCONTRADA\n";
        exit(1);
    }
}

echo "\n=== RESULTADO ===\n";
echo "✓ Sistema assíncrono está configurado corretamente!\n\n";

echo "Como usar:\n";
echo "1. Acesse o sistema pelo navegador\n";
echo "2. Vá para a aba 'CPF'\n";
echo "3. Clique em 'Executar Análise CKAN'\n";
echo "4. Um modal mostrará o progresso em tempo real\n\n";

echo "Para executar worker manualmente:\n";
echo "   php start-worker.php\n\n";

echo "Para Windows (duplo clique):\n";
echo "   start-worker.bat\n\n";

echo "Logs podem ser encontrados em:\n";
echo "   logs/worker.log\n";
echo "   logs/error.log\n\n";

echo "Status atual pode ser verificado em:\n";
echo "   cache/scan.lock\n\n";
?>
