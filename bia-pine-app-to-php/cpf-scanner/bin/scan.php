#!/usr/bin/env php
<?php

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/vendor/autoload.php';

use CpfScanner\Ckan\CkanApiClient;
use CpfScanner\Parsing\Factory\FileParserFactory;
use CpfScanner\Scanning\Strategy\LogicBasedScanner;
use CpfScanner\Scanning\Strategy\AiBasedScanner;
use Dotenv\Dotenv;

try {
    $dotenv = Dotenv::createImmutable($projectRoot);
    $dotenv->load();
    $dotenv->required(['CKAN_API_URL']);
} catch (RuntimeException $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    echo "Certifique-se de que o arquivo .env existe e contém as variáveis necessárias.\n";
    echo "Use o arquivo env.example como modelo.\n";
    exit(1);
}

$ckanUrl = $_ENV['CKAN_API_URL'];
$ckanApiKey = $_ENV['CKAN_API_KEY'] ?? '';
$geminiApiKey = $_ENV['GEMINI_API_KEY'] ?? '';
$maxChunkSize = (int)($_ENV['MAX_CHUNK_SIZE'] ?? 15000);
$maxRetries = (int)($_ENV['MAX_RETRY_ATTEMPTS'] ?? 5);
$cacheDir = $_ENV['CACHE_DIR'] ?? 'cache';

echo "🔍 Selecionando a estratégia de verificação...\n";

$scanner = new LogicBasedScanner();
echo "✅ Modo selecionado: Verificador Lógico Determinístico.\n";

$ckanClient = new CkanApiClient($ckanUrl, $ckanApiKey, $cacheDir, $maxRetries);

$tempDir = sys_get_temp_dir() . '/ckan_scanner_' . uniqid();
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0755, true);
}

echo "🌐 Iniciando a varredura do portal CKAN em: {$ckanUrl}\n";
echo "📁 Diretório temporário: {$tempDir}\n";

echo "📊 Buscando lista de datasets...\n";
$datasetIds = $ckanClient->getAllDatasetIds();
$totalDatasets = count($datasetIds);
echo "📈 Total de datasets encontrados: {$totalDatasets}\n";

if ($totalDatasets === 0) {
    echo "⚠️  Nenhum dataset encontrado no portal.\n";
    exit(0);
}

$findings = [];
$datasetCount = 0;
$totalResources = 0;
$processedResources = 0;
$errors = 0;

echo "\n🔍 Iniciando verificação de CPFs...\n";
echo str_repeat("=", 60) . "\n";

foreach ($datasetIds as $datasetId) {
    $datasetCount++;
    echo "📦 Processando dataset {$datasetCount}/{$totalDatasets}: {$datasetId}\n";
    
    $datasetDetails = $ckanClient->getDatasetDetails($datasetId);
    if (!$datasetDetails || empty($datasetDetails['resources'])) {
        echo "  ⚠️  Dataset sem recursos ou erro ao carregar.\n";
        continue;
    }

    $resources = $datasetDetails['resources'];
    $totalResources += count($resources);
    
    foreach ($resources as $resource) {
        $resourceUrl = $resource['url'] ?? null;
        $resourceFormat = strtolower($resource['format'] ?? 'unknown');
        $resourceName = $resource['name'] ?? $resource['id'];
        $resourceId = $resource['id'] ?? 'unknown';

        if (!$resourceUrl) {
            echo "  ⚠️  Recurso sem URL: {$resourceName}\n";
            continue;
        }

        $processedResources++;
        echo "  📄 Processando recurso {$processedResources}: {$resourceName} ({$resourceFormat})\n";

        try {
            $fileName = basename(parse_url($resourceUrl, PHP_URL_PATH));
            if (empty($fileName)) {
                $fileName = $resourceId . '.' . $resourceFormat;
            }
            
            $filePath = $tempDir . '/' . $fileName;
            
            echo "    ⬇️  Baixando arquivo...\n";
            $fileContent = file_get_contents($resourceUrl);
            
            if ($fileContent === false) {
                // Caso extremo de falha de I/O ou rede (mantido)
                throw new \Exception("Falha ao baixar o arquivo: file_get_contents retornou false.");
            }
            
            // 1. VALIDAÇÃO DE CONTEÚDO: Verificar se o arquivo não está vazio.
            if (empty($fileContent)) {
                echo "    ❌ O arquivo baixado está vazio.\n";
                continue;
            }

            // 2. VALIDAÇÃO DE CABEÇALHO MÁGICO para PDF.
            // Verifica se os primeiros 5 bytes são %PDF-
            if ($resourceFormat === 'pdf' && substr(trim($fileContent), 0, 5) !== '%PDF-') {
                // Se não for um PDF válido, pode ser um HTML de erro.
                if (strpos(trim($fileContent), '<!DOCTYPE html>') !== false) {
                    echo "    ❌ O download do recurso PDF retornou uma página de erro HTML.\n";
                } else {
                    echo "    ❌ O arquivo baixado não possui o cabeçalho mágico de um PDF (%PDF-).\n";
                    echo "    📝 Primeiros bytes: " . substr($fileContent, 0, 20) . "...\n";
                }
                continue;
            }

            // 3. VALIDAÇÃO DE TAMANHO para evitar arquivos muito grandes
            if (strlen($fileContent) > 110 * 1024 * 1024) { // 200MB
                echo "    ❌ Arquivo muito grande: " . round(strlen($fileContent) / 1024 / 1024, 2) . "MB\n";
                continue;
            }
            
            // Salva o arquivo SOMENTE se as validações passarem
            file_put_contents($filePath, $fileContent);
            echo "    ✅ Arquivo baixado e validado com sucesso (" . round(strlen($fileContent) / 1024, 2) . "KB)\n";

            echo "    🔍 Analisando conteúdo...\n";
            $parser = FileParserFactory::createParserFromFile($filePath);
            $textContent = $parser->getText($filePath);

            if (empty(trim($textContent))) {
                echo "    ⚠️  Arquivo vazio ou sem conteúdo textual.\n";
                unlink($filePath);
                continue;
            }

            echo "    🔎 Verificando CPFs...\n";
            $foundCpfs = $scanner->scan($textContent);

            if (!empty($foundCpfs)) {
                $findings[] = [
                    'resource_name' => $resourceName,
                    'resource_id' => $resourceId,
                    'dataset_id' => $datasetId,
                    'resource_url' => $resourceUrl,
                    'resource_format' => $resourceFormat,
                    'cpfs' => $foundCpfs,
                    'cpf_count' => count($foundCpfs)
                ];
                
                echo "    ⚠️  CPFs encontrados: " . count($foundCpfs) . "\n";
            } else {
                echo "    ✅ Nenhum CPF encontrado.\n";
            }

            unlink($filePath);

        } catch (\Exception $e) {
            $errors++;
            echo "    ❌ Erro ao processar recurso '{$resourceName}': " . $e->getMessage() . "\n";
            error_log("Erro ao processar recurso '{$resourceName}': " . $e->getMessage());
            
            if (isset($filePath) && file_exists($filePath)) {
                unlink($filePath);
            }
        }
    }
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "📋 RELATÓRIO DE VERIFICAÇÃO DE CPF\n";
echo str_repeat("=", 60) . "\n";

echo "📊 Estatísticas Gerais:\n";
echo "  • Datasets processados: {$datasetCount}/{$totalDatasets}\n";
echo "  • Recursos processados: {$processedResources}/{$totalResources}\n";
echo "  • Erros encontrados: {$errors}\n";

if (empty($findings)) {
    echo "\n✅ Nenhum CPF válido foi encontrado em nenhum recurso do portal.\n";
    echo "🎉 O portal está em conformidade com as práticas de proteção de dados!\n";
} else {
    echo "\n⚠️  " . count($findings) . " recurso(s) com potenciais vazamentos de dados encontrados:\n\n";
    
    foreach ($findings as $index => $finding) {
        echo "🔍 Vazamento #" . ($index + 1) . ":\n";
        echo "  📦 Dataset ID: {$finding['dataset_id']}\n";
        echo "  📄 Recurso: {$finding['resource_name']}\n";
        echo "  🆔 ID do Recurso: {$finding['resource_id']}\n";
        echo "  📋 Formato: {$finding['resource_format']}\n";
        echo "  🔗 URL: {$finding['resource_url']}\n";
        echo "  🔢 CPFs Detectados: {$finding['cpf_count']}\n";
        
        echo "  📝 CPFs Encontrados: " . implode(', ', $finding['cpfs']) . "\n\n";
    }
    
    echo "🚨 AÇÃO NECESSÁRIA:\n";
    echo "  • Revise imediatamente os recursos listados acima\n";
    echo "  • Remova ou anonimize os dados de CPF encontrados\n";
    echo "  • Implemente controles para prevenir futuros vazamentos\n";
    echo "  • Considere a notificação às autoridades competentes se necessário\n";
}

$cacheStats = $ckanClient->getCacheStats();
echo "\n💾 Estatísticas do Cache:\n";
echo "  • Arquivos em cache: {$cacheStats['files_count']}\n";
echo "  • Tamanho total: {$cacheStats['total_size_mb']} MB\n";

echo "\n🧹 Limpando arquivos temporários...\n";
array_map('unlink', glob("$tempDir/*.*"));
rmdir($tempDir);

echo "✅ Verificação concluída.\n";
echo "📅 Data/Hora: " . date('d/m/Y H:i:s') . "\n";

exit(0);