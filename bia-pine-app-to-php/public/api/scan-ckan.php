<?php
// Aumenta o tempo máximo de execução para evitar timeouts em portais grandes.
// 0 = sem limite.
@set_time_limit(0);

// Garante que todo o output seja capturado, se houver algum erro inesperado.
@ob_start();

header('Content-Type: application/json; charset=utf-8');

// --- INCLUDES E CONFIGURAÇÕES ---
// Ajuste os caminhos conforme sua estrutura de projeto
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config.php'; // Seu arquivo de config, se tiver.

use CpfScanner\Ckan\CkanApiClient;
use CpfScanner\Parsing\Factory\FileParserFactory;
use CpfScanner\Scanning\Strategy\LogicBasedScanner;
use CpfScanner\Integration\CpfVerificationService;
use Dotenv\Dotenv;

// --- FUNÇÕES DE RESPOSTA (para manter o padrão) ---
function sendResponse($data, $statusCode = 200) {
    // Limpa qualquer output inesperado antes de enviar o JSON
    if (ob_get_length()) {
        ob_end_clean();
    }
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function sendError($message, $statusCode = 400) {
    $responseData = [
        'success' => false,
        'error' => $message,
        'timestamp' => date('c')
    ];
    sendResponse($responseData, $statusCode);
}

// --- CARREGAMENTO DAS VARIÁVEIS DE AMBIENTE ---
try {
    // Carrega o .env do diretório principal
    $dotenv = Dotenv::createImmutable(dirname(__DIR__, 2));
    $dotenv->load();
    $dotenv->required(['CKAN_API_URL', 'DB_HOST', 'DB_DATABASE', 'DB_USERNAME']);
} catch (Exception $e) {
    sendError("Erro nas variáveis de ambiente: " . $e->getMessage(), 500);
}

// --- CONEXÃO COM O BANCO DE DADOS ---
try {
    $pdo = conectarBanco(); // Usando sua função de `config.php`
} catch (Exception $e) {
    sendError('Erro de conexão com o banco de dados: ' . $e->getMessage(), 500);
}

// --- INICIALIZAÇÃO DOS SERVIÇOS ---
$ckanUrl = $_ENV['CKAN_API_URL'];
$ckanApiKey = $_ENV['CKAN_API_KEY'] ?? '';
$maxRetries = (int)($_ENV['MAX_RETRY_ATTEMPTS'] ?? 5);
$cacheDir = dirname(__DIR__, 2) . '/cpf-scanner/' . ($_ENV['CACHE_DIR'] ?? 'cache'); // Caminho absoluto para cache

$ckanClient = new CkanApiClient($ckanUrl, $ckanApiKey, $cacheDir, $maxRetries);
$scanner = new LogicBasedScanner();
$verificationService = new CpfVerificationService($pdo);

$tempDir = sys_get_temp_dir() . '/ckan_scanner_' . uniqid();
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0755, true);
}

// --- LÓGICA PRINCIPAL DA VARREDURA ---
try {
    $datasetIds = $ckanClient->getAllDatasetIds();
    $totalDatasets = count($datasetIds);

    if ($totalDatasets === 0) {
        sendResponse(['success' => true, 'message' => 'Nenhum dataset encontrado no portal.', 'data' => ['total_datasets' => 0]]);
    }

    $processedResources = 0;
    $totalCpfsSalvos = 0;
    $resourcesWithCpfs = 0;

    foreach ($datasetIds as $datasetId) {
        $datasetDetails = $ckanClient->getDatasetDetails($datasetId);
        if (!$datasetDetails || empty($datasetDetails['resources'])) {
            continue;
        }

        foreach ($datasetDetails['resources'] as $resource) {
            $resourceUrl = $resource['url'] ?? null;
            if (!$resourceUrl) continue;

            $processedResources++;
            $filePath = null;

            try {
                $fileContent = @file_get_contents($resourceUrl);
                if ($fileContent === false) continue;

                // 1. VALIDAÇÃO DE CONTEÚDO: Verificar se o arquivo não está vazio.
                if (empty($fileContent)) {
                    error_log("[ERRO] Arquivo baixado está vazio: " . $resource['id']);
                    continue;
                }

                // 2. VALIDAÇÃO DE CABEÇALHO MÁGICO para PDF.
                $resourceFormat = strtolower($resource['format'] ?? 'unknown');
                if ($resourceFormat === 'pdf' && substr(trim($fileContent), 0, 5) !== '%PDF-') {
                    // Se não for um PDF válido, pode ser um HTML de erro.
                    if (strpos(trim($fileContent), '<!DOCTYPE html>') !== false) {
                        error_log("[ERRO] Download retornou página HTML de erro para PDF: " . $resource['id']);
                    } else {
                        error_log("[ERRO] Arquivo PDF inválido - cabeçalho: " . substr($fileContent, 0, 20) . " - " . $resource['id']);
                    }
                    continue;
                }

                // 3. VALIDAÇÃO DE TAMANHO para evitar arquivos muito grandes
                if (strlen($fileContent) > 110 * 1024 * 1024) { // 200MB
                    error_log("[ERRO] Arquivo muito grande: " . round(strlen($fileContent) / 1024 / 1024, 2) . "MB - " . $resource['id']);
                    continue;
                }

                $fileName = basename(parse_url($resourceUrl, PHP_URL_PATH)) ?: $resource['id'] . '.' . $resourceFormat;
                $filePath = $tempDir . '/' . $fileName;
                
                // Salva o arquivo SOMENTE se as validações passarem
                file_put_contents($filePath, $fileContent);

                $parser = FileParserFactory::createParserFromFile($filePath);
                
                // Log de início do processamento
                error_log("[PROCESSAMENTO] Iniciando análise do arquivo: " . $fileName . " (" . round(strlen($fileContent) / 1024 / 1024, 2) . "MB)");
                
                $textContent = $parser->getText($filePath);
                
                // Log de conclusão do processamento
                error_log("[PROCESSAMENTO] Concluída análise do arquivo: " . $fileName . " - Texto extraído: " . strlen($textContent) . " caracteres");

                if (empty(trim($textContent))) {
                    unlink($filePath);
                    continue;
                }

                $foundCpfs = $scanner->scan($textContent);

                if (!empty($foundCpfs)) {
                    $resourcesWithCpfs++;
                    $metadados = [
                        'dataset_id' => $datasetId,
                        'resource_id' => $resource['id'],
                        'resource_name' => $resource['name'],
                        'resource_url' => $resourceUrl,
                        'resource_format' => strtolower($resource['format'] ?? 'unknown'),
                    ];

                    // Processa e salva os CPFs para ESTE recurso
                    $stats = $verificationService->processarCPFsEncontrados($foundCpfs, 'ckan_scanner', $metadados);
                    $totalCpfsSalvos += $stats['salvos_com_sucesso'];
                }

                unlink($filePath);

            } catch (Exception $e) {
                // Loga o erro mas continua o processo
                error_log("Erro ao processar recurso '{$resource['name']}': " . $e->getMessage());
                if ($filePath && file_exists($filePath)) {
                    unlink($filePath);
                }
                continue;
            }
        }
    }
    
    // --- LIMPEZA E RESPOSTA FINAL ---
    if (is_dir($tempDir)) {
        array_map('unlink', glob("$tempDir/*.*"));
        rmdir($tempDir);
    }
    
    sendResponse([
        'success' => true,
        'message' => 'Análise CKAN concluída com sucesso!',
        'data' => [
            'datasets_analisados' => $totalDatasets,
            'recursos_analisados' => $processedResources,
            'recursos_com_cpfs' => $resourcesWithCpfs,
            'total_cpfs_salvos' => $totalCpfsSalvos
        ]
    ]);

} catch (Exception $e) {
    if (is_dir($tempDir)) {
        array_map('unlink', glob("$tempDir/*.*"));
        rmdir($tempDir);
    }
    sendError("Erro fatal durante a varredura: " . $e->getMessage(), 500);
}