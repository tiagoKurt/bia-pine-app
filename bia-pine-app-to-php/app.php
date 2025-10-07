<?php
session_start();

// Headers para evitar cache
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com https://fonts.googleapis.com; img-src 'self' data: https:; connect-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com;");

// Carregar configuração e autoloader
require __DIR__ . '/config.php';

// Importar classes necessárias ANTES de verificar se existem
use App\Bia;
use App\Pine;
use App\RobustAutoloader;
use App\AutoloaderDiagnostic;

// Inicializar sistema robusto de autoloading
$robustAutoloader = null;
$diagnostic = null;

try {
    // Tentar carregar o sistema robusto de autoloading
    if (class_exists('App\RobustAutoloader')) {
        $robustAutoloader = RobustAutoloader::getInstance();
        $robustAutoloader->ensureAutoloaderLoaded();
        $robustAutoloader->registerFallbackAutoloader();
    }
    
    if (class_exists('App\AutoloaderDiagnostic')) {
        $diagnostic = new AutoloaderDiagnostic();
    }
} catch (Exception $e) {
    error_log("AVISO: Sistema robusto de autoloading não disponível: " . $e->getMessage());
}

// Função para diagnóstico detalhado em caso de erro
function diagnosticarProblemaAutoloader($className) {
    global $diagnostic, $robustAutoloader;
    
    error_log("=== DIAGNÓSTICO DE PROBLEMA DE AUTOLOADER ===");
    error_log("Classe não encontrada: {$className}");
    error_log("Contexto: " . (php_sapi_name() === 'cli' ? 'CLI' : 'Web'));
    error_log("Working Directory: " . getcwd());
    error_log("Script Filename: " . ($_SERVER['SCRIPT_FILENAME'] ?? 'N/A'));
    
    if ($diagnostic) {
        try {
            $results = $diagnostic->runDiagnostic();
            error_log("Diagnóstico executado, " . count($results) . " verificações realizadas");
            
            foreach ($results as $key => $result) {
                if ($result['status'] === 'error') {
                    error_log("ERRO: {$result['message']}");
                    if ($result['solution']) {
                        error_log("SOLUÇÃO: {$result['solution']}");
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Erro ao executar diagnóstico: " . $e->getMessage());
        }
    }
    
    if ($robustAutoloader) {
        try {
            error_log("Tentando carregamento manual da classe {$className}...");
            $loaded = $robustAutoloader->loadClassManually($className);
            error_log("Carregamento manual: " . ($loaded ? 'SUCESSO' : 'FALHOU'));
            return $loaded;
        } catch (Exception $e) {
            error_log("Erro no carregamento manual: " . $e->getMessage());
        }
    }
    
    return false;
}

// Verificar e carregar classes críticas com diagnóstico aprimorado
$bia = null;
$pine = null;

// Verificar e instanciar classe Bia
if (!class_exists('App\Bia')) {
    error_log("ERRO: Classe App\Bia não encontrada, executando diagnóstico...");
    
    $loaded = diagnosticarProblemaAutoloader('App\Bia');
    
    if (!$loaded && !class_exists('App\Bia')) {
        // Tentar carregamento manual direto como último recurso
        $biaPath = __DIR__ . '/src/Bia.php';
        if (file_exists($biaPath)) {
            error_log("Tentando carregamento direto do arquivo: {$biaPath}");
            try {
                require_once $biaPath;
                if (class_exists('App\Bia')) {
                    error_log("Classe App\Bia carregada diretamente com sucesso");
                }
            } catch (Exception $e) {
                error_log("Erro no carregamento direto: " . $e->getMessage());
            }
        }
    }
}

if (class_exists('App\Bia')) {
    try {
        error_log("BIA: Tentando instanciar classe Bia...");
        $bia = new Bia();
        error_log("BIA: Classe Bia instanciada com sucesso");
    } catch (Exception $e) {
        error_log("ERRO: Falha ao instanciar classe Bia: " . $e->getMessage());
        error_log("ERRO: Stack trace: " . $e->getTraceAsString());
        
        // Não interromper a execução, apenas registrar o erro
        $bia = null;
    }
} else {
    error_log("ERRO CRÍTICO: Classe App\Bia não pôde ser carregada mesmo após tentativas de recuperação");
    
    // Mostrar erro mais informativo para o usuário
    $errorMessage = "Erro crítico: Sistema de autoloading falhou.\n\n";
    $errorMessage .= "Possíveis soluções:\n";
    $errorMessage .= "1. Execute 'composer dump-autoload' no diretório do projeto\n";
    $errorMessage .= "2. Verifique se o arquivo src/Bia.php existe\n";
    $errorMessage .= "3. Verifique as permissões dos arquivos\n";
    $errorMessage .= "4. Consulte os logs para mais detalhes\n\n";
    $errorMessage .= "Contexto: " . (php_sapi_name() === 'cli' ? 'CLI' : 'Web Server') . "\n";
    $errorMessage .= "Diretório: " . getcwd();
    
    die($errorMessage);
}

// Verificar e instanciar classe Pine
if (!class_exists('App\Pine')) {
    error_log("AVISO: Classe App\Pine não encontrada, executando diagnóstico...");
    diagnosticarProblemaAutoloader('App\Pine');
    
    // Tentar carregamento manual direto
    $pinePath = __DIR__ . '/src/Pine.php';
    if (file_exists($pinePath)) {
        try {
            require_once $pinePath;
        } catch (Exception $e) {
            error_log("Erro ao carregar Pine.php: " . $e->getMessage());
        }
    }
}

if (class_exists('App\Pine')) {
    try {
        $pine = new Pine();
        error_log("Classe Pine instanciada com sucesso");
    } catch (Exception $e) {
        error_log("Erro ao instanciar classe Pine: " . $e->getMessage());
        $pine = null;
    }
} else {
    error_log("AVISO: Classe App\Pine não disponível, algumas funcionalidades podem estar limitadas");
    $pine = null;
}

// Criar conexão com o banco de dados (apenas se necessário)
$pdo = null;
try {
    $pdo = conectarBanco();
} catch (Exception $e) {
    // Log do erro mas não interrompe a execução para funcionalidades que não dependem do banco
    error_log("Erro de conexão com banco de dados: " . $e->getMessage());
}


$message = $_SESSION['message'] ?? '';
$messageType = $_SESSION['messageType'] ?? 'error';
$downloadFile = $_SESSION['downloadFile'] ?? null;
$downloadFileName = $_SESSION['downloadFileName'] ?? null;
unset($_SESSION['message'], $_SESSION['messageType'], $_SESSION['downloadFile'], $_SESSION['downloadFileName']);

const DIAS_PARA_DESATUALIZADO = 30;
$portalUrl = $_SESSION['portalUrl'] ?? '';

$paginaAtual = isset($_GET['page']) && isset($_GET['tab']) && $_GET['tab'] === 'pine' ? (int)$_GET['page'] : 1;
$itensPorPagina = 15;

$analysisResults = []; 
if (!empty($portalUrl) && $pine) {
    try {
        $analysisResults = $pine->getDatasetsPaginados($portalUrl, $paginaAtual, $itensPorPagina);
    } catch (Exception $e) {
        error_log("Erro ao buscar datasets: " . $e->getMessage());
        $analysisResults = [];
    }
}


// Incluir funções de verificação de CPF
require_once __DIR__ . '/src/functions.php';

$paginaCpfAtual = isset($_GET['page']) && isset($_GET['tab']) && $_GET['tab'] === 'cpf' ? (int)$_GET['page'] : 1;
$itensPorPaginaCpf = 10;

$cpfData = [];
$cpfFindings = [];
$estatisticas = [];
$lastScanInfo = null;

try {
    if ($pdo) {
        // Primeiro tenta usar a nova tabela otimizada
        $cpfData = getCpfFindingsPaginadoFromNewTable($pdo, $paginaCpfAtual, $itensPorPaginaCpf);
        $cpfFindings = $cpfData['findings'] ?? [];
        
        $lastScanInfo = getLastCpfScanInfoFromNewTable($pdo);
        
        // Buscar estatísticas da nova tabela
        $estatisticas = obterEstatisticasVerificacoesFromNewTable($pdo);
        
        // Se não há dados na nova tabela, tenta a tabela antiga como fallback
        if (empty($cpfFindings)) {
            $cpfData = getCpfFindingsPaginado($pdo, $paginaCpfAtual, $itensPorPaginaCpf);
            $cpfFindings = $cpfData['findings'] ?? [];
            
            $lastScanInfo = getLastCpfScanInfo($pdo);
            
            // Buscar estatísticas gerais do banco
            $estatisticas = obterEstatisticasVerificacoes($pdo);
        }
    } else {
        $cpfData = ['total_resources' => 0, 'total_paginas' => 1, 'pagina_atual' => 1];
        $cpfFindings = [];
        $estatisticas = ['total' => 0, 'validos' => 0, 'invalidos' => 0, 'total_recursos' => 0];
        $lastScanInfo = null;
    }
    
} catch (Exception $e) {
    $cpfData = ['total_resources' => 0, 'total_paginas' => 1, 'pagina_atual' => 1];
    $cpfFindings = [];
    $estatisticas = ['total' => 0, 'validos' => 0, 'invalidos' => 0, 'total_recursos' => 0];
    $lastScanInfo = null;
    error_log("Erro ao buscar dados CPF em app.php: " . $e->getMessage());
}



if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Configurar tratamento de erro global para requisições AJAX
    set_error_handler(function($severity, $message, $file, $line) {
        if (error_reporting() & $severity) {
            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                      strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
            
            if ($isAjax) {
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'message' => 'Erro interno do servidor: ' . $message,
                    'type' => 'error',
                    'debug' => [
                        'file' => $file,
                        'line' => $line,
                        'severity' => $severity
                    ]
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
        return false;
    });
    
    $action = $_POST['action'] ?? '';
    
    // Verificar se é uma requisição AJAX
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    error_log("BIA: Verificando requisição AJAX");
    error_log("BIA: HTTP_X_REQUESTED_WITH = " . ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? 'NÃO DEFINIDO'));
    error_log("BIA: isAjax = " . ($isAjax ? 'true' : 'false'));
    
    // Ação da aba BIA
    if ($action === 'gerar_dicionario') {
        error_log("BIA: Processando requisição gerar_dicionario");
        error_log("BIA: isAjax = " . ($isAjax ? 'true' : 'false'));
        error_log("BIA: Headers: " . json_encode(getallheaders()));
        
        $recursoUrl = $_POST['recurso_url'] ?? '';
        
        if (empty($recursoUrl)) {
            if ($isAjax) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Informe o link do recurso CKAN.',
                    'type' => 'error'
                ]);
                exit;
            } else {
                $_SESSION['message'] = 'Informe o link do recurso CKAN.';
                $_SESSION['messageType'] = 'error';
                header("Location: " . $_SERVER['PHP_SELF'] . "?tab=bia");
                exit;
            }
        } else {
            try {
                // Log da requisição
                error_log("BIA: Iniciando geração de dicionário para URL: " . $recursoUrl);
                error_log("BIA: Método da requisição: " . $_SERVER['REQUEST_METHOD']);
                // Log dos headers de forma compatível
                $headers = function_exists('getallheaders') ? getallheaders() : [];
                error_log("BIA: Headers da requisição: " . json_encode($headers));
                
                $templateFile = __DIR__ . '/templates/modelo_bia2_pronto_para_preencher.docx';
                
                // Verificar se o template existe
                if (!file_exists($templateFile)) {
                    error_log("BIA: Template não encontrado: " . $templateFile);
                    throw new Exception("Template não encontrado: " . $templateFile);
                }
                
                error_log("BIA: Template encontrado: " . $templateFile);
                
                // Verificar se a classe Bia está disponível
                if (!isset($bia) || !is_object($bia)) {
                    error_log("BIA: Classe Bia não está disponível");
                    throw new Exception("Classe Bia não está disponível");
                }
                
                error_log("BIA: Classe Bia está disponível, tipo: " . get_class($bia));
                error_log("BIA: Chamando gerarDicionarioWord...");
                
                // Verificar se a URL é válida
                if (!filter_var($recursoUrl, FILTER_VALIDATE_URL)) {
                    error_log("BIA: URL inválida: " . $recursoUrl);
                    throw new Exception("URL do recurso inválida: " . $recursoUrl);
                }
                
                $outputFile = $bia->gerarDicionarioWord($recursoUrl, $templateFile);
                error_log("BIA: Arquivo gerado: " . $outputFile);

                if ($isAjax) {
                    // Limpar qualquer output anterior
                    if (ob_get_level()) {
                        ob_clean();
                    }
                    
                    // Retornar resposta AJAX com dados de download
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode([
                        'success' => true,
                        'message' => 'Documento gerado e baixado com sucesso!',
                        'type' => 'success',
                        'downloadFile' => $outputFile,
                        'downloadFileName' => basename($outputFile)
                    ], JSON_UNESCAPED_UNICODE);
                    exit;
                } else {
                    $_SESSION['message'] = 'Documento gerado e baixado com sucesso!';
                    $_SESSION['messageType'] = 'success';
                    $_SESSION['downloadFile'] = $outputFile;
                    $_SESSION['downloadFileName'] = basename($outputFile);
                    header("Location: " . $_SERVER['PHP_SELF'] . "?tab=bia");
                    exit;
                }
                
            } catch (Exception $e) {
                error_log("BIA: Erro ao gerar dicionário: " . $e->getMessage());
                error_log("BIA: Stack trace: " . $e->getTraceAsString());
                error_log("BIA: File: " . $e->getFile() . " Line: " . $e->getLine());
                
                $errorMessage = 'Ocorreu um erro ao gerar o dicionário: ' . $e->getMessage();
                
                if ($isAjax) {
                    // Limpar qualquer output anterior
                    if (ob_get_level()) {
                        ob_clean();
                    }
                    
                    http_response_code(500);
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode([
                        'success' => false,
                        'message' => $errorMessage,
                        'type' => 'error',
                        'debug' => [
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                            'trace' => $e->getTraceAsString()
                        ]
                    ], JSON_UNESCAPED_UNICODE);
                    exit;
                } else {
                    $_SESSION['message'] = $errorMessage;
                    $_SESSION['messageType'] = 'error';
                    header("Location: " . $_SERVER['PHP_SELF'] . "?tab=bia");
                    exit;
                }
            }
        }
    }
    
    if ($action === 'analyze_portal') {
        $portalUrl = $_POST['portal_url'] ?? '';
        $_SESSION['portalUrl'] = $portalUrl;

        if (empty($portalUrl) || !filter_var($portalUrl, FILTER_VALIDATE_URL)) {
            $_SESSION['message'] = 'Por favor, informe uma URL válida para o portal CKAN.';
            $_SESSION['messageType'] = 'error';
        } else {
            try {
                $pine->analisarESalvarPortal($portalUrl, DIAS_PARA_DESATUALIZADO); //
                $_SESSION['message'] = 'Análise concluída e dados salvos com sucesso!';
                $_SESSION['messageType'] = 'success';

            } catch (Exception $e) {
                $_SESSION['message'] = 'Ocorreu um erro ao analisar o portal: ' . $e->getMessage();
                $_SESSION['messageType'] = 'error';
            }
        }
        header("Location: " . $_SERVER['PHP_SELF'] . "?tab=pine");
        exit;
    }
    
    if ($action === 'export_pine_csv') {
        $exportData = $pine->getDatasetsPaginados($portalUrl, 1, 99999); 
        
        if ($exportData && !empty($exportData['datasets'])) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="analise_pine_' . date('Y-m-d') . '.csv"');
            
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM para UTF-8 no Excel
            fputcsv($output, ['ID', 'Nome do Dataset', 'Órgão', 'Última Atualização', 'Status', 'Dias Desde Atualização', 'Recursos', 'Link']);
            
            foreach ($exportData['datasets'] as $dataset) {
                fputcsv($output, [
                    $dataset['dataset_id'],
                    $dataset['name'],
                    $dataset['organization'],
                    $dataset['last_updated'] ? date('d/m/Y H:i', strtotime($dataset['last_updated'])) : 'N/A',
                    $dataset['status'],
                    $dataset['days_since_update'] === PHP_INT_MAX ? 'N/A' : $dataset['days_since_update'],
                    $dataset['resources_count'],
                    $dataset['url']
                ]);
            }
            
            fclose($output);
            exit;
        }
    }
    
    if ($action === 'export_cpf_csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="cpf_findings_' . date('Y-m-d_H-i-s') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM para UTF-8 no Excel
        fputcsv($output, ['Dataset ID', 'Dataset Name', 'Resource ID', 'Resource Name', 'Resource Format', 'CPF Count', 'CPFs Encontrados', 'Last Checked']);
        
        foreach ($cpfFindings as $finding) {
            fputcsv($output, [
                $finding['dataset_id'],
                $finding['dataset_name'],
                $finding['resource_id'],
                $finding['resource_name'],
                $finding['resource_format'],
                $finding['cpf_count'],
                implode('; ', $finding['cpfs']),
                $finding['last_checked']
            ]);
        }
        
        fclose($output);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoramento Portal de Dados Abertos - CGE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Seu CSS (sem alterações) */
        :root {
            --primary-color: #3d6b35;
            --primary-dark: #2d5a27;
            --secondary-color: #64748b;
            --success-color: #3d6b35;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --light-bg: #f8f9fa;
            --card-bg: #ffffff;
            --border-color: #dee2e6;
            --text-primary: #212529;
            --text-secondary: #6c757d;
            --shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 4px 8px rgba(0, 0, 0, 0.15);
            --green-gradient: linear-gradient(135deg, #3d6b35 0%, #2d5a27 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
            color: var(--text-primary);
            line-height: 1.6;
        }

        .app-container {
            min-height: 100vh;
            background: #f5f5f5;
        }

        .header {
            background: var(--green-gradient);
            color: white;
            padding: 1.5rem 0;
            box-shadow: var(--shadow);
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .system-logo {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .system-logo i {
            font-size: 2rem;
            color: white;
        }

        .system-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
        }

        .system-subtitle {
            font-size: 0.9rem;
            opacity: 0.9;
            margin: 0;
        }

        .government-logos {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .cge-logo {
            text-align: center;
        }

        .cge-logo .logo-text {
            font-size: 0.8rem;
            font-weight: 600;
            line-height: 1.2;
        }

        .gov-go-logo {
            text-align: center;
        }

        .gov-go-text {
            font-size: 1.2rem;
            font-weight: 700;
            color: #ffd700;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
        }

        .logo-image {
            max-height: 80px;
            width: auto;
        }

        .header h1 {
            font-weight: 700;
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
            font-weight: 300;
        }

        .main-content {
            padding: 2rem 0;
        }

        .welcome-section {
            margin-bottom: 2rem;
        }

        .welcome-banner {
            background: var(--green-gradient);
            color: white;
            padding: 2rem;
            border-radius: 8px;
            text-align: center;
            box-shadow: var(--shadow);
        }

        .welcome-banner h2 {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .welcome-banner p {
            font-size: 1rem;
            opacity: 0.9;
            margin: 0;
        }

        .module-access h3 {
            color: var(--text-primary);
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .nav-tabs-container {
            margin-bottom: 2rem;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .nav-tabs {
            border: none;
            margin-bottom: 0;
            display: flex;
            flex-wrap: nowrap;
            min-width: max-content;
        }

        .nav-tabs .nav-link {
            border: 2px solid var(--primary-color);
            background: white;
            color: var(--primary-color);
            border-radius: 8px;
            margin-right: 0.5rem;
            padding: 1rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            position: relative;
        }

        .nav-tabs .nav-link:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-1px);
            box-shadow: var(--shadow);
        }

        .nav-tabs .nav-link.active {
            background: var(--primary-color);
            color: white;
            box-shadow: var(--shadow);
        }

        .nav-tabs .nav-link i {
            margin-right: 0.5rem;
        }

        .nav-tabs .nav-link .tab-text {
            white-space: nowrap;
        }

        .nav-tabs-container::-webkit-scrollbar {
            height: 6px;
        }

        .nav-tabs-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        .nav-tabs-container::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 3px;
        }

        .nav-tabs-container::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }

        .tab-content {
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: var(--shadow);
            padding: 2.5rem;
            border: 1px solid var(--border-color);
            min-height: 500px;
        }

        .tab-pane h2 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 1.5rem;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .form-control {
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 1rem 1.25rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--light-bg);
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            background: white;
        }

        .form-label {
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-size: 1rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            border: none;
            border-radius: 12px;
            padding: 1rem 2rem;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-shadow: var(--shadow);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success-color) 0%, #059669 100%);
            border: none;
            border-radius: 12px;
            padding: 1rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .alert {
            border-radius: 12px;
            border: none;
            padding: 1.25rem 1.5rem;
            margin-bottom: 2rem;
            font-weight: 500;
            box-shadow: var(--shadow);
        }

        .alert-success {
            background: linear-gradient(135deg, var(--success-color) 0%, #059669 100%);
            color: white;
        }

        .alert-danger {
            background: linear-gradient(135deg, var(--danger-color) 0%, #dc2626 100%);
            color: white;
        }

        .alert-warning {
            background: linear-gradient(135deg, var(--warning-color) 0%, #d97706 100%);
            color: white;
        }

        .description-text {
            color: var(--text-secondary);
            font-size: 1rem;
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }

        .icon {
            font-size: 1.2em;
        }

        .download-section {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border: 1px solid #bae6fd;
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 1.5rem;
            text-align: center;
        }

        .stats-card {
            background: linear-gradient(135deg, var(--danger-color) 0%, #dc2626 100%);
            color: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }

        .stats-card.success {
            background: linear-gradient(135deg, var(--success-color) 0%, #059669 100%);
        }

        .table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--shadow);
            margin-bottom: 0;
        }

        .table-responsive {
            border-radius: 12px;
            box-shadow: var(--shadow);
            background: white;
        }

        .table thead th {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 1rem;
            font-weight: 600;
            text-align: center;
        }

        .table tbody td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background: var(--light-bg);
        }

        .badge {
            font-size: 0.875rem;
            padding: 0.5rem 1rem;
            border-radius: 8px;
        }

        .btn-outline-primary {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            background: transparent;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-outline-primary:hover {
            background: var(--primary-color);
            color: white;
        }

        .cpf-list {
            font-family: 'Courier New', monospace;
            background: var(--light-bg);
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
        }

        .cpf-item {
            display: inline-block;
            background: var(--danger-color);
            color: white;
            padding: 0.25rem 0.5rem;
            margin: 0.25rem;
            border-radius: 4px;
            font-size: 0.875rem;
        }

        .footer {
            background: var(--green-gradient);
            color: white;
            padding: 1.5rem 0;
            margin-top: 3rem;
        }

        .footer-content {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .footer-left {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .footer-right {
            display: flex;
            align-items: center;
        }

        .footer-info p {
            margin: 0;
            font-size: 0.9rem;
            font-weight: 500;
        }

        @media (max-width: 576px) {
            .header {
                padding: 1rem 0;
            }

            .header-content {
                flex-direction: column;
                gap: 0.75rem;
                text-align: center;
            }

            .system-title {
                font-size: 1.1rem;
                line-height: 1.2;
            }

            .system-subtitle {
                font-size: 0.75rem;
            }

            .logo-image {
                max-height: 60px;
            }

            .main-content {
                padding: 1rem 0;
            }

            .welcome-banner {
                padding: 1.5rem 1rem;
                margin-bottom: 1.5rem;
            }

            .welcome-banner h2 {
                font-size: 1.3rem;
                margin-bottom: 0.5rem;
            }

            .welcome-banner p {
                font-size: 0.9rem;
            }

            .tab-content {
                padding: 1rem;
                border-radius: 12px;
            }

            .tab-pane h2 {
                font-size: 1.4rem;
                margin-bottom: 1rem;
            }

            .nav-tabs-container {
                margin-bottom: 1.5rem;
            }

            .nav-tabs {
                flex-wrap: nowrap;
                overflow-x: auto;
                padding-bottom: 0.5rem;
            }

            .nav-tabs .nav-link {
                margin-right: 0.25rem;
                margin-bottom: 0;
                padding: 0.6rem 0.8rem;
                font-size: 0.8rem;
                border-radius: 6px;
                flex: 0 0 auto;
                min-width: 80px;
                text-align: center;
                white-space: nowrap;
            }

            .nav-tabs .nav-link .tab-text {
                display: inline;
            }

            .form-control {
                padding: 0.75rem 1rem;
                font-size: 0.9rem;
            }

            .btn-primary, .btn-success {
                padding: 0.75rem 1.5rem;
                font-size: 0.9rem;
                width: 100%;
                margin-top: 0.5rem;
            }

            .btn-outline-primary {
                padding: 0.4rem 0.8rem;
                font-size: 0.8rem;
            }

            .table-responsive {
                font-size: 0.8rem;
                border: 1px solid var(--border-color);
            }

            .table {
                min-width: 600px;
            }

            .table thead th {
                padding: 0.75rem 0.5rem;
                font-size: 0.8rem;
                white-space: nowrap;
            }

            .table tbody td {
                padding: 0.75rem 0.5rem;
                vertical-align: top;
            }

            .table tbody td strong {
                font-size: 0.85rem;
            }

            .table tbody td small {
                font-size: 0.75rem;
            }

            .table tbody td:nth-child(1) {
                min-width: 200px;
            }

            .table tbody td:nth-child(2) {
                min-width: 120px;
            }

            .table tbody td:nth-child(3) {
                min-width: 100px;
            }

            .table tbody td:nth-child(4) {
                min-width: 80px;
            }

            .table tbody td:nth-child(5) {
                min-width: 60px;
            }

            .table tbody td:nth-child(6) {
                min-width: 100px;
            }

            .badge {
                font-size: 0.75rem;
                padding: 0.4rem 0.8rem;
            }

            .stats-card {
                padding: 1rem;
                margin-bottom: 1.5rem;
            }

            .stats-card h3 {
                font-size: 1.5rem;
            }

            .stats-card h4 {
                font-size: 1.1rem;
            }

            .cpf-item {
                font-size: 0.75rem;
                padding: 0.2rem 0.4rem;
                margin: 0.15rem;
            }

            .footer {
                padding: 1rem 0;
            }

            .footer-content {
                flex-direction: column;
                gap: 0.75rem;
                text-align: center;
            }

            .logo-image {
                max-height: 50px;
            }

            .pagination {
                font-size: 0.8rem;
            }

            .page-link {
                padding: 0.5rem 0.75rem;
            }

            .alert {
                padding: 1rem;
                font-size: 0.9rem;
            }

            .description-text {
                font-size: 0.9rem;
                margin-bottom: 1rem;
            }

            .module-access h3 {
                font-size: 1.1rem;
                margin-bottom: 0.75rem;
            }

            .progress-container .card {
                margin: 0;
            }

            .progress-container .card-body {
                padding: 1rem;
            }

            .progress-container h5 {
                font-size: 1rem;
            }

            .progress-container .card-text {
                font-size: 0.85rem;
            }
        }

        @media (min-width: 577px) and (max-width: 768px) {
            .header-content {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }

            .system-title {
                font-size: 1.3rem;
            }

            .system-subtitle {
                font-size: 0.85rem;
            }

            .logo-image {
                max-height: 70px;
            }

            .tab-content {
                padding: 1.75rem;
            }

            .nav-tabs-container {
                margin-bottom: 1.75rem;
            }

            .nav-tabs .nav-link {
                margin-right: 0.5rem;
                margin-bottom: 0;
                padding: 0.8rem 1.2rem;
                font-size: 0.9rem;
                flex: 0 0 auto;
                min-width: 100px;
            }

            .welcome-banner h2 {
                font-size: 1.6rem;
            }

            .table-responsive {
                font-size: 0.85rem;
                border: 1px solid var(--border-color);
            }

            .table {
                min-width: 700px;
            }

            .table thead th {
                padding: 0.85rem 0.75rem;
                font-size: 0.85rem;
            }

            .table tbody td {
                padding: 0.85rem 0.75rem;
            }

            .btn-primary, .btn-success {
                padding: 0.85rem 1.75rem;
            }

            .stats-card {
                padding: 1.25rem;
            }

            .stats-card h3 {
                font-size: 1.8rem;
            }
        }

        @media (min-width: 769px) and (max-width: 992px) {
            .header-content {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }

            .system-title {
                font-size: 1.4rem;
            }

            .tab-content {
                padding: 2rem;
            }

            .nav-tabs .nav-link {
                padding: 0.9rem 1.3rem;
                font-size: 0.95rem;
            }

            .table-responsive {
                font-size: 0.9rem;
            }
        }

        @media (min-width: 993px) and (max-width: 1200px) {
            .container {
                max-width: 960px;
            }

            .tab-content {
                padding: 2.25rem;
            }
        }

        @media (min-width: 1201px) {
            .container {
                max-width: 1140px;
            }

            .tab-content {
                padding: 2.5rem;
            }
        }

        .table-responsive {
            border-radius: 12px;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .table-responsive::-webkit-scrollbar {
            height: 8px;
        }

        .table-responsive::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .table-responsive::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 4px;
        }

        .table-responsive::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }

        @media (max-width: 576px) {
            .modal-dialog {
                margin: 0.5rem;
                max-width: calc(100% - 1rem);
            }

            .modal-content {
                border-radius: 12px;
            }

            .modal-header {
                padding: 1rem;
            }

            .modal-body {
                padding: 1rem;
            }

            .modal-footer {
                padding: 1rem;
            }

            .modal-title {
                font-size: 1.1rem;
            }
        }

        @media (max-width: 576px) {
            .btn-group {
                flex-direction: column;
                width: 100%;
            }

            .btn-group .btn {
                margin-bottom: 0.25rem;
                border-radius: 6px !important;
            }

            .btn-group .btn:last-child {
                margin-bottom: 0;
            }
        }

        @media (max-width: 576px) {
            .stats-card .row {
                margin: 0;
            }

            .stats-card .col-md-4 {
                padding: 0.5rem;
                margin-bottom: 1rem;
            }

            .stats-card .col-md-4:last-child {
                margin-bottom: 0;
            }
        }

        @media (max-width: 576px) {
            .pagination {
                flex-wrap: wrap;
                justify-content: center;
            }

            .page-item {
                margin: 0.125rem;
            }

            .page-link {
                padding: 0.4rem 0.6rem;
                font-size: 0.8rem;
                border-radius: 6px;
            }
        }

        @media (max-width: 576px) {
            .row .col-md-8,
            .row .col-md-4 {
                margin-bottom: 1rem;
            }

            .row .col-md-4:last-child {
                margin-bottom: 0;
            }

            .form-label {
                font-size: 0.9rem;
                margin-bottom: 0.4rem;
            }

            .form-text {
                font-size: 0.8rem;
            }
        }

        @media (max-width: 576px) {
            .collapse .p-3 {
                padding: 0.75rem !important;
            }

            .collapse h6 {
                font-size: 0.9rem;
                margin-bottom: 0.75rem;
            }
        }

        @media (max-width: 576px) {
            .icon {
                font-size: 1em;
            }

            .system-logo i {
                font-size: 1.5rem;
            }

            .stats-card .icon {
                font-size: 1.5rem;
            }
        }

        .w-md-auto {
            width: auto !important;
        }

        @media (max-width: 767.98px) {
            .w-md-auto {
                width: 100% !important;
            }
        }

        .gap-2 {
            gap: 0.5rem !important;
        }

        .gap-3 {
            gap: 1rem !important;
        }

        .g-3 > * {
            padding-right: calc(var(--bs-gutter-x) * 0.5);
            padding-left: calc(var(--bs-gutter-x) * 0.5);
            margin-top: var(--bs-gutter-y);
        }

        .g-3 {
            --bs-gutter-x: 1rem;
            --bs-gutter-y: 0;
            display: flex;
            flex-wrap: wrap;
            margin-top: calc(-1 * var(--bs-gutter-y));
            margin-right: calc(-0.5 * var(--bs-gutter-x));
            margin-left: calc(-0.5 * var(--bs-gutter-x));
        }

        .g-2 {
            --bs-gutter-x: 0.5rem;
            --bs-gutter-y: 0;
            display: flex;
            flex-wrap: wrap;
            margin-top: calc(-1 * var(--bs-gutter-y));
            margin-right: calc(-0.5 * var(--bs-gutter-x));
            margin-left: calc(-0.5 * var(--bs-gutter-x));
        }

        .g-2 > * {
            padding-right: calc(var(--bs-gutter-x) * 0.5);
            padding-left: calc(var(--bs-gutter-x) * 0.5);
            margin-top: var(--bs-gutter-y);
        }

        @media (max-width: 576px) {
            .container {
                padding-left: 1rem;
                padding-right: 1rem;
            }

            .btn-group-vertical .btn {
                margin-bottom: 0.25rem;
            }

            .btn-group-vertical .btn:last-child {
                margin-bottom: 0;
            }

            .mb-4 {
                margin-bottom: 1.5rem !important;
            }

            .mt-4 {
                margin-top: 1.5rem !important;
            }

            .card {
                border-radius: 8px;
            }

            .card-body {
                padding: 1rem;
            }

            .badge {
                word-break: break-word;
            }

            a {
                word-break: break-all;
            }

            p, li {
                word-wrap: break-word;
                overflow-wrap: break-word;
            }
        }

        @media (max-width: 576px) and (orientation: landscape) {
            .header {
                padding: 0.75rem 0;
            }

            .main-content {
                padding: 0.75rem 0;
            }

            .welcome-banner {
                padding: 1rem;
            }

            .tab-content {
                padding: 0.75rem;
            }

            .modal-dialog {
                margin: 0.25rem;
            }
        }

        @media (max-width: 375px) {
            .system-title {
                font-size: 1rem;
            }

            .welcome-banner h2 {
                font-size: 1.2rem;
            }

            .tab-pane h2 {
                font-size: 1.2rem;
            }

            .nav-tabs .nav-link {
                padding: 0.5rem 0.6rem;
                font-size: 0.75rem;
            }

            .btn-primary, .btn-success {
                padding: 0.6rem 1.25rem;
                font-size: 0.85rem;
            }
        }

        /* Estilos específicos para PINE */
        .stats-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .input-group .input-group-text {
            background: var(--light-bg);
            border-color: var(--border-color);
        }

        .dropdown-menu {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-lg);
        }

        .dropdown-item {
            padding: 0.75rem 1rem;
            transition: all 0.2s ease;
        }

        .dropdown-item:hover {
            background: var(--primary-color);
            color: white;
        }

        .btn-group .btn-check:checked + .btn {
            transform: scale(1.05);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .btn-group .btn {
            transition: all 0.2s ease;
            font-weight: 500;
        }

        .btn-group .btn:hover {
            transform: translateY(-1px);
        }

        #clear-search {
            border-left: none;
        }

        #clear-search:hover {
            background: var(--danger-color);
            color: white;
        }

        @media (max-width: 991.98px) {
            #pine-filters .row .col-lg-4,
            #pine-filters .row .col-lg-3,
            #pine-filters .row .col-lg-2 {
                margin-bottom: 1rem;
            }
            
            #pine-filters .row .col-lg-2:last-child {
                margin-bottom: 0;
            }
            
            .btn-group .btn {
                font-size: 0.875rem;
                padding: 0.5rem 0.75rem;
            }
        }

        @media (max-width: 767.98px) {
            #pine-filters .card-body {
                padding: 1rem;
            }
            
            #pine-filters .row {
                margin: 0;
            }
            
            #pine-filters .row > [class*="col-"] {
                padding-left: 0.5rem;
                padding-right: 0.5rem;
                margin-bottom: 1rem;
            }
            
            #pine-filters .row > [class*="col-"]:last-child {
                margin-bottom: 0;
            }
            
            .btn-group {
                flex-direction: column;
                width: 100%;
                gap: 0.25rem;
            }
            
            .btn-group .btn {
                border-radius: 0.375rem !important;
                margin-bottom: 0;
                width: 100%;
                text-align: center;
                padding: 0.75rem 1rem;
                font-size: 0.9rem;
            }
            
            .btn-group .btn:last-child {
                margin-bottom: 0;
            }
            
            .form-select, .form-control {
                font-size: 0.9rem;
                padding: 0.75rem 1rem;
                border-radius: 0.5rem;
            }
            
            .form-label {
                font-size: 0.9rem;
                margin-bottom: 0.5rem;
                font-weight: 500;
            }
            
            .input-group .input-group-text {
                padding: 0.75rem 1rem;
                border-radius: 0.5rem 0 0 0.5rem;
            }
            
            .input-group .form-control {
                border-radius: 0 0.5rem 0.5rem 0;
            }
            
            .dropdown-toggle {
                text-align: left;
                padding: 0.75rem 1rem;
                border-radius: 0.5rem;
            }
            
            #pine-dashboard .row > [class*="col-"] {
                margin-bottom: 1rem;
            }
            
            #pine-dashboard .row > [class*="col-"]:last-child {
                margin-bottom: 0;
            }
        }

        .stats-card h3 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stats-card p {
            font-size: 0.9rem;
            opacity: 0.9;
            margin: 0;
        }

        #pine-filters .card {
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
        }

        #pine-filters .card-title {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .form-select {
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 1rem 1.25rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--light-bg);
        }

        .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            background: white;
        }

        #datasets-table tbody tr {
            transition: background-color 0.2s ease;
        }

        #datasets-table tbody tr:hover {
            background-color: var(--light-bg);
        }

        .badge {
            font-size: 0.875rem;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 500;
        }

        .badge.bg-success {
            background: linear-gradient(135deg, var(--success-color) 0%, #059669 100%) !important;
        }

        .badge.bg-danger {
            background: linear-gradient(135deg, var(--danger-color) 0%, #dc2626 100%) !important;
        }

        .badge.bg-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%) !important;
        }

        @media (max-width: 768px) {
            .stats-card h3 {
                font-size: 1.5rem;
            }

            .stats-card .icon {
                font-size: 1.5rem !important;
            }

            #pine-filters .row .col-md-4,
            #pine-filters .row .col-md-3,
            #pine-filters .row .col-md-2 {
                margin-bottom: 1rem;
            }

            #pine-filters .row .col-md-2:last-child {
                margin-bottom: 0;
            }
            
            #pine-dashboard .row {
                margin: 0 -0.5rem;
            }
            
            #pine-dashboard .row > [class*="col-"] {
                padding-left: 0.5rem;
                padding-right: 0.5rem;
                margin-bottom: 1rem;
            }
            
            #pine-dashboard .row > [class*="col-"]:last-child {
                margin-bottom: 0;
            }
            
            .stats-card {
                padding: 1rem;
                margin-bottom: 0;
            }
            
            .stats-card .d-flex {
                flex-direction: column;
                text-align: center;
            }
            
            .stats-card .icon {
                margin-bottom: 0.5rem !important;
                margin-right: 0 !important;
            }
        }

        @media (max-width: 576px) {
            .stats-card {
                padding: 1rem;
                margin-bottom: 1rem;
            }

            .stats-card h3 {
                font-size: 1.25rem;
            }

            .stats-card p {
                font-size: 0.8rem;
            }

            .stats-card .icon {
                font-size: 1.25rem !important;
            }

            #pine-filters .card-body {
                padding: 1rem;
            }

            #pine-filters .card-title {
                font-size: 1.1rem;
                margin-bottom: 0.75rem;
            }

            .form-select, .form-control {
                padding: 0.75rem 1rem;
                font-size: 0.9rem;
            }

            #datasets-table {
                font-size: 0.8rem;
            }

            #datasets-table thead th {
                padding: 0.75rem 0.5rem;
                font-size: 0.8rem;
            }

            #datasets-table tbody td {
                padding: 0.75rem 0.5rem;
            }

            .badge {
                font-size: 0.75rem;
                padding: 0.4rem 0.8rem;
            }
        }

        /* Animações suaves */
        .fade-in {
            animation: fadeIn 0.3s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Loading states */
        .loading-overlay {
            position: relative;
        }

        .loading-overlay::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Header -->
        <header class="header">
            <div class="container">
                <div class="header-content">
                    <div class="header-left">
                        <div class="system-logo">
                            <i class="fas fa-chart-bar"></i>
                            <div>
                                <h1 class="system-title">Monitoramento Portal de Dados Abertos</h1>
                                <!-- <p class="system-subtitle">Sistema de Controle de Procedimentos Administrativos Correcionais</p> -->
                            </div>
                        </div>
                    </div>
                    <div class="header-right">
                        <div class="government-logos">
                            <div class="gov-go-logo">
                                <img src="assets/img/logo-cge-e-estado-goias.png" alt="CGE e Estado de Goiás" class="logo-image">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <div class="main-content">
            <div class="container">
                <?php if ($message && !isset($_GET['tab'])): ?>
                    <div class="alert alert-<?= $messageType === 'success' ? 'success' : ($messageType === 'warning' ? 'warning' : 'danger') ?> alert-dismissible fade show" role="alert">
                        <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : ($messageType === 'warning' ? 'exclamation-triangle' : 'exclamation-triangle') ?> icon"></i>
                        <?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Welcome Section -->
                <div class="welcome-section mb-4">
                    <div class="welcome-banner">
                        <h2>Bem-vindo ao Sistema de Monitoramento!</h2>
                        <p>Monitore métricas, verifique e analise o desempenho dos portais de dados abertos.</p>
                    </div>
                </div>

                <!-- Module Access -->
                <div class="module-access mb-4">
                    <h3>Você tem acesso aos seguintes módulos:</h3>
                </div>

                <!-- Navigation Tabs -->
                <div class="nav-tabs-container">
                    <ul class="nav nav-tabs" id="mainTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= isset($_GET['tab']) && $_GET['tab'] === 'bia' ? 'active' : (!isset($_GET['tab']) ? 'active' : '') ?>" id="bia-tab" data-bs-toggle="tab" data-bs-target="#bia" type="button" role="tab">
                                <i class="fas fa-file-word icon"></i> 
                                <span class="tab-text">BIA</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= isset($_GET['tab']) && $_GET['tab'] === 'pine' ? 'active' : '' ?>" id="pine-tab" data-bs-toggle="tab" data-bs-target="#pine" type="button" role="tab">
                                <i class="fas fa-chart-line icon"></i> 
                                <span class="tab-text">PINE</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="cpf-tab" data-bs-toggle="tab" data-bs-target="#cpf" type="button" role="tab">
                                <i class="fas fa-shield-alt icon"></i> 
                                <span class="tab-text">CPF</span>
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="tab-content" id="mainTabsContent">
                    <!-- BIA Tab -->
                    <div class="tab-pane fade <?= isset($_GET['tab']) && $_GET['tab'] === 'bia' ? 'show active' : (!isset($_GET['tab']) ? 'show active' : '') ?>" id="bia" role="tabpanel">
                        <h2>
                            <i class="fas fa-file-word icon"></i>
                            Gerar Dicionário de Dados
                        </h2>
                        <p class="description-text">
                            Crie dicionários de dados em formato Word a partir de recursos CKAN.
                            O sistema analisa automaticamente a estrutura dos dados e gera documentação completa.
                        </p>
                        
                        <form method="POST" class="mt-4" id="dicionario-form">
                            <input type="hidden" name="action" value="gerar_dicionario">
                            <div class="mb-4">
                                <label for="recurso_url" class="form-label">
                                    <i class="fas fa-link icon"></i> Link do Recurso CKAN
                                </label>
                                <input type="url" class="form-control" id="recurso_url" name="recurso_url" 
                                       placeholder="https://dadosabertos.go.gov.br/dataset/.../resource/..." required>
                                <div class="form-text text-muted mt-2">
                                    <i class="fas fa-info-circle icon"></i>
                                    Cole aqui o link completo do recurso que deseja documentar
                                </div>
                            </div>
                            <button type="submit" id="gerar-btn" class="btn btn-primary">
                                <span id="btn-text">
                                    <i class="fas fa-magic icon"></i> Gerar Dicionário
                                </span>
                                <span id="btn-loading" class="d-none">
                                    <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                    Gerando dicionário...
                                </span>
                            </button>
                        </form>
                        
                        <!-- Barra de progresso -->
                        <div id="progress-container" class="d-none mt-4">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <i class="fas fa-cogs icon"></i> Gerando Dicionário de Dados
                                    </h5>
                                    <p class="card-text text-muted">
                                        Analisando a estrutura dos dados e gerando documentação...
                                    </p>
                                    <div class="progress mb-3">
                                        <div id="progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" 
                                             role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <small class="text-muted" id="progress-text">Iniciando...</small>
                                        <small class="text-muted" id="progress-percent">0%</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($downloadFile && file_exists($downloadFile)): ?>
                            <div id="download-notification" class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle icon"></i>
                                Documento gerado e baixado com sucesso!
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <div id="download-data" 
                                 data-file="<?= htmlspecialchars($downloadFileName, ENT_QUOTES, 'UTF-8') ?>" 
                                 data-path="<?= htmlspecialchars($downloadFile, ENT_QUOTES, 'UTF-8') ?>" 
                                 style="display: none;">
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- PINE Tab -->
                    <div class="tab-pane fade" id="pine" role="tabpanel">
                        <h2>
                            <i class="fas fa-chart-line icon"></i>
                            Análise PINE - Monitoramento de Datasets
                        </h2>
                        <p class="description-text">
                            Digite a URL do portal CKAN para analisar a atualização dos datasets. Os dados são salvos e exibidos abaixo com filtros avançados.
                        </p>
                        
                        <!-- Formulário de Análise -->
                        <form method="POST" id="analysis-form">
                            <input type="hidden" name="action" value="analyze_portal">
                            <div class="row g-3">
                                <div class="col-12 col-md-8">
                                    <label for="portal_url" class="form-label">
                                        <i class="fas fa-link icon"></i> URL do Portal CKAN
                                    </label>
                                    <input type="url" class="form-control" id="portal_url" name="portal_url" 
                                           placeholder="https://dadosabertos.go.gov.br" 
                                           value="<?= htmlspecialchars($portalUrl) ?>" required>
                                </div>
                                <div class="col-12 col-md-4 d-flex align-items-end">
                                    <button type="submit" id="submit-btn" class="btn btn-primary w-100">
                                        <span id="btn-text"><i class="fas fa-play icon"></i> Iniciar Análise</span>
                                        <span id="loading-spinner" class="d-none">
                                            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                            Analisando...
                                        </span>
                                    </button>
                                </div>
                            </div>
                        </form>
                        
                        <!-- Dashboard de Estatísticas -->
                        <div id="pine-dashboard" class="mt-4" style="display: none !important;">
                            <div class="row g-3 mb-4">
                                <div class="col-6 col-md-3">
                                    <div class="stats-card">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-database icon me-3" style="font-size: 2rem;"></i>
                                            <div>
                                                <h3 id="total-datasets" class="mb-0">0</h3>
                                                <p class="mb-0">Total de Datasets</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="stats-card success">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-check-circle icon me-3" style="font-size: 2rem;"></i>
                                            <div>
                                                <h3 id="datasets-atualizados" class="mb-0">0</h3>
                                                <p class="mb-0">Atualizados</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="stats-card">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-exclamation-triangle icon me-3" style="font-size: 2rem;"></i>
                                            <div>
                                                <h3 id="datasets-desatualizados" class="mb-0">0</h3>
                                                <p class="mb-0">Desatualizados</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="stats-card" style="background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-building icon me-3" style="font-size: 2rem;"></i>
                                            <div>
                                                <h3 id="total-orgaos" class="mb-0">0</h3>
                                                <p class="mb-0">Órgãos</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Filtros e Busca -->
                        <div id="pine-filters" class="mt-4" style="display: none !important;">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <i class="fas fa-filter icon"></i> Filtros e Busca
                                    </h5>
                                    <div class="row g-3">
                                        <!-- Busca - sempre em coluna completa em mobile -->
                                        <div class="col-12">
                                            <label for="search-dataset" class="form-label">
                                                <i class="fas fa-search icon"></i> Buscar Dataset
                                            </label>
                                            <div class="input-group">
                                                <span class="input-group-text">
                                                    <i class="fas fa-search text-muted"></i>
                                                </span>
                                                <input type="text" class="form-control" id="search-dataset" 
                                                       placeholder="Digite o nome ou ID do dataset...">
                                                <button class="btn btn-outline-secondary" type="button" id="clear-search">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <!-- Filtros em linha em desktop, coluna em mobile -->
                                        <div class="col-12 col-md-6 col-lg-4 mt-4">
                                            <label for="filter-organization" class="form-label">
                                                <i class="fas fa-building icon"></i> Órgão
                                            </label>
                                            <div class="dropdown">
                                                <button class="btn btn-outline-primary dropdown-toggle w-100 text-start" type="button" 
                                                        id="organizationDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                                    <span id="organization-text">Todos os órgãos</span>
                                                </button>
                                                <ul class="dropdown-menu w-100" aria-labelledby="organizationDropdown">
                                                    <li><a class="dropdown-item" href="#" data-value="">Todos os órgãos</a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <div id="organization-list"></div>
                                                </ul>
                                            </div>
                                            <input type="hidden" id="filter-organization" value="">
                                        </div>
                                        
                                        <div class="col-12 col-md-6 col-lg-4 mt-4">
                                            <label for="filter-status" class="form-label">
                                                <i class="fas fa-flag icon"></i> Status
                                            </label>
                                            <div class="btn-group w-100" role="group" aria-label="Status filter">
                                                <input type="radio" class="btn-check" name="status-filter" id="status-all" value="" checked>
                                                <label class="btn btn-outline-secondary" for="status-all">
                                                    <i class="fas fa-list"></i> Todos
                                                </label>
                                                
                                                <input type="radio" class="btn-check" name="status-filter" id="status-updated" value="Atualizado">
                                                <label class="btn btn-outline-success" for="status-updated">
                                                    <i class="fas fa-check-circle"></i> Atualizado
                                                </label>
                                                
                                                <input type="radio" class="btn-check" name="status-filter" id="status-outdated" value="Desatualizado">
                                                <label class="btn btn-outline-danger" for="status-outdated">
                                                    <i class="fas fa-exclamation-triangle"></i> Desatualizado
                                                </label>
                                            </div>
                                            <input type="hidden" id="filter-status" value="">
                                        </div>
                                        
                                        <!-- Botão limpar - sempre em coluna completa -->
                                        <div class="col-12 col-lg-4 d-flex align-items-end ">
                                            <button type="button" class="btn btn-outline-secondary w-100" id="clear-filters">
                                                <i class="fas fa-broom icon"></i> Limpar Filtros
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Lista de Datasets -->
                        <div id="pine-datasets" class="mt-4" style="display: none !important;">
                                <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
                                    <h3 class="mb-0">
                                        <i class="fas fa-list icon"></i>
                                    <span id="datasets-title">Lista de Datasets</span>
                                    </h3>
                                    <div class="w-100 w-md-auto">
                                    <button type="button" class="btn btn-success w-100 w-md-auto" id="export-csv">
                                                <i class="fas fa-download icon"></i> Exportar CSV
                                            </button>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                <table class="table table-hover" id="datasets-table">
                                        <thead>
                                            <tr>
                                                <th>Dataset</th>
                                                <th>Órgão</th>
                                                <th>Última Atualização</th>
                                                <th>Status</th>
                                                <th>Recursos</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                    <tbody id="datasets-tbody">
                                        <!-- Dados carregados via AJAX -->
                                        </tbody>
                                    </table>
                                </div>

                            <!-- Paginação -->
                            <nav class="mt-4" id="pine-pagination">
                                <!-- Paginação carregada via AJAX -->
                                    </nav>
                            </div>
                        
                        <!-- Loading -->
                        <div id="pine-loading" class="text-center py-5" style="display: none !important;">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Carregando...</span>
                            </div>
                            <p class="mt-3">Carregando dados...</p>
                        </div>
                        
                        <!-- Mensagem quando não há dados -->
                        <div id="pine-no-data" class="text-center py-5" style="display: none !important;">
                            <i class="fas fa-inbox icon" style="font-size: 3rem; color: #6c757d;"></i>
                            <h4 class="mt-3">Nenhum dataset encontrado</h4>
                            <p class="text-muted">Execute uma análise para visualizar os datasets do portal.</p>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="cpf" role="tabpanel">
                        <h2>
                            <i class="fas fa-shield-alt icon"></i>
                            Verificação de CPF
                        </h2>
                        <p class="description-text">
                            Auditoria de segurança em portais CKAN para detectar vazamentos de CPF em datasets públicos.
                        </p>

                        <?php if (empty($cpfFindings) && ($cpfData['total_resources'] ?? 0) == 0): ?>
                            <!-- Nenhuma análise executada -->
                            <div class="stats-card info">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-info-circle icon me-3" style="font-size: 2rem;"></i>
                                    <div>
                                        <h4 class="mb-1">Nenhuma análise executada</h4>
                                        <p class="mb-0">Execute a análise CKAN para verificar vazamentos de CPF nos recursos do portal de dados abertos.</p>
                                    </div>
                                </div>
                            </div>
                        <?php elseif ($lastScanInfo && empty($cpfFindings) && ($cpfData['total_resources'] ?? 0) == 0): ?>
                            <!-- Análise executada mas sem CPFs encontrados -->
                            <div class="stats-card success">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-check-circle icon me-3" style="font-size: 2rem;"></i>
                                    <div>
                                        <h4 class="mb-1">Análise realizada - Nenhum CPF encontrado</h4>
                                        <p class="mb-2">A última análise foi executada em <?= date('d/m/Y H:i', strtotime($lastScanInfo['lastScan'])) ?> e não encontrou CPFs nos recursos analisados.</p>
                                        <?php if ($lastScanInfo['lastResults']): ?>
                                            <small class="text-light">
                                                <i class="fas fa-chart-bar"></i> 
                                                <?= $lastScanInfo['lastResults']['datasets_analisados'] ?? 0 ?> datasets, 
                                                <?= $lastScanInfo['lastResults']['recursos_analisados'] ?? 0 ?> recursos analisados
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php elseif (!empty($cpfFindings) || ($cpfData['total_resources'] ?? 0) > 0): ?>
                            <!-- Dados existentes no banco - mostrar mesmo sem análise completa -->
                            <div class="stats-card warning">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-database icon me-3" style="font-size: 2rem;"></i>
                                    <div>
                                        <h4 class="mb-1">Dados de CPF encontrados no banco</h4>
                                        <p class="mb-2">
                                            <?php if ($lastScanInfo): ?>
                                                Última análise: <?= date('d/m/Y H:i', strtotime($lastScanInfo['lastScan'])) ?>
                                            <?php else: ?>
                                                Dados históricos disponíveis no banco de dados
                                            <?php endif; ?>
                                        </p>
                                        <small class="text-light">
                                            <i class="fas fa-exclamation-triangle"></i> 
                                            <?= number_format($cpfData['total_resources'] ?? 0, 0, ',', '.') ?> recursos com CPFs encontrados
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Dados encontrados - mostrar estatísticas -->
                            <div class="stats-card success">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-shield-alt icon me-3" style="font-size: 2rem;"></i>
                                    <div>
                                        <h4 class="mb-1">Análise de CPF - Dados Encontrados</h4>
                                        <p class="mb-2">Foram encontrados CPFs em recursos do portal de dados abertos.</p>
                                        <small class="text-light">
                                            <i class="fas fa-chart-bar"></i> 
                                            Total de recursos: <?= $cpfData['total_resources'] ?? 0 ?> | 
                                            Página <?= $cpfData['pagina_atual'] ?? 1 ?> de <?= $cpfData['total_paginas'] ?? 1 ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                            
                            <?php if ($lastScanInfo): ?>
                                <!-- Mostrar quando próxima análise pode ser executada -->
                                <?php 
                                $lastScanTime = strtotime($lastScanInfo['lastScan']);
                                $nextScanTime = $lastScanTime + (4 * 3600); // 4 horas
                                $canScanNow = time() >= $nextScanTime;
                                ?>
                                
                                <?php if ($canScanNow): ?>
                                    <div class="text-center mt-4">
                                        <button id="btnScanCkan" class="btn btn-warning btn-lg">
                                            <i class="fas fa-search icon"></i>
                                            Executar Nova Análise CKAN
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info mt-4">
                                        <i class="fas fa-clock icon"></i>
                                        <strong>Próxima análise disponível em:</strong> <?= date('d/m/Y H:i', $nextScanTime) ?>
                                        <br><small>Para evitar sobrecarga do servidor, análises só podem ser executadas a cada 4 horas.</small>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="text-center mt-4">
                                    <button id="btnScanCkan" class="btn btn-warning btn-lg">
                                        <i class="fas fa-search icon"></i>
                                        Executar Análise CKAN
                                    </button>
                                </div>
                            <?php endif; ?>
                        
                        <?php if ($lastScanInfo || !empty($cpfFindings) || ($cpfData['total_resources'] ?? 0) > 0): ?>
                            <!-- Resumo dos resultados -->
                            <div class="stats-card">
                                <div class="row">
                                    <div class="col-md-3 text-center">
                                        <i class="fas fa-exclamation-triangle icon mb-2" style="font-size: 2rem;"></i>
                                        <h3><?= number_format($cpfData['total_resources'] ?? 0, 0, ',', '.') ?></h3>
                                        <p class="mb-0">Recursos com CPFs</p>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <i class="fas fa-file-alt icon mb-2" style="font-size: 2rem;"></i>
                                        <h3><?= number_format($estatisticas['total'] ?? 0, 0, ',', '.') ?></h3>
                                        <p class="mb-0">Total de CPFs encontrados</p>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <i class="fas fa-database icon mb-2" style="font-size: 2rem;"></i>
                                        <h3><?= number_format($estatisticas['total_recursos'] ?? 0, 0, ',', '.') ?></h3>
                                        <p class="mb-0">Recursos analisados</p>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <i class="fas fa-clock icon mb-2" style="font-size: 2rem;"></i>
                                        <h3><?= $lastScanInfo ? date('H:i', strtotime($lastScanInfo['lastScan'])) : '--:--' ?></h3>
                                        <p class="mb-0">Última verificação</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Tabela de resultados -->
                            <div class="mt-4">
                                <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
                                    <h3 class="mb-0">
                                        <i class="fas fa-list icon"></i>
                                        Recursos com CPFs Detectados
                                    </h3>
                                    <div class="w-100 w-md-auto">
                                        <form method="POST" class="d-inline w-100">
                                            <input type="hidden" name="action" value="export_cpf_csv">
                                            <button type="submit" class="btn btn-success w-100 w-md-auto">
                                                <i class="fas fa-download icon"></i> Exportar CSV
                                            </button>
                                        </form>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Dataset</th>
                                                <!-- <th>Órgão</th> -->
                                                <th>Recurso</th>
                                                <th>Formato</th>
                                                <th>CPFs</th>
                                                <th>Verificado</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($cpfFindings as $index => $finding): ?>
                                                <tr>
                                                    <td>
                                                        <div>
                                                            <strong><?= htmlspecialchars($finding['dataset_name']) ?></strong>
                                                            <br>
                                                            <small class="text-muted">ID: <?= htmlspecialchars($finding['dataset_id']) ?></small>
                                                        </div>
                                                    </td>
                                                    <!-- <td>
                                                        <span class="badge bg-info"><?= htmlspecialchars($finding['dataset_organization']) ?></span>
                                                    </td> -->
                                                    <td>
                                                        <div>
                                                            <strong><?= htmlspecialchars($finding['resource_name']) ?></strong>
                                                            <br>
                                                            <small class="text-muted">ID: <?= htmlspecialchars($finding['resource_id']) ?></small>
                                                            <br>
                                                            <a href="<?= htmlspecialchars($finding['resource_url']) ?>" target="_blank" class="text-decoration-none">
                                                                <small><i class="fas fa-external-link-alt"></i> Ver recurso</small>
                                                            </a>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-secondary"><?= strtoupper($finding['resource_format']) ?></span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-danger"><?= $finding['cpf_count'] ?> CPFs</span>
                                                    </td>
                                                    <td>
                                                        <small><?= date('d/m/Y H:i', strtotime($finding['last_checked'])) ?></small>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $index ?>" aria-expanded="false">
                                                                <i class="fas fa-eye icon"></i> Ver CPFs
                                                            </button>
                                                            <?php if (!empty($finding['dataset_url']) && $finding['dataset_url'] !== '#'): ?>
                                                                <a href="<?= htmlspecialchars($finding['dataset_url']) ?>" target="_blank" class="btn btn-outline-success btn-sm">
                                                                    <i class="fas fa-external-link-alt icon"></i> Dataset
                                                                </a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td colspan="7" class="p-0">
                                                        <div class="collapse" id="collapse<?= $index ?>">
                                                            <div class="p-3 bg-light">
                                                                <h6 class="mb-3">
                                                                    <i class="fas fa-id-card icon"></i>
                                                                    CPFs Encontrados (<?= $finding['cpf_count'] ?>)
                                                                </h6>
                                                                <div class="cpf-list">
                                                                    <?php foreach ($finding['cpfs'] as $cpf): ?>
                                                                        <span class="cpf-item"><?= $cpf ?></span>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <?php if (isset($cpfData['total_paginas']) && $cpfData['total_paginas'] > 1): ?>
                                    <nav class="mt-4" aria-label="Navegação da página de CPFs">
                                        <ul class="pagination justify-content-center">
                                            <li class="page-item <?= $paginaCpfAtual <= 1 ? 'disabled' : '' ?>">
                                                <a class="page-link" href="?tab=cpf&page=<?= $paginaCpfAtual - 1 ?>">Anterior</a>
                                            </li>
                                            
                                            <?php for ($i = 1; $i <= $cpfData['total_paginas']; $i++): ?>
                                                <li class="page-item <?= $i === $paginaCpfAtual ? 'active' : '' ?>" aria-current="page">
                                                    <a class="page-link" href="?tab=cpf&page=<?= $i ?>"><?= $i ?></a>
                                                </li>
                                            <?php endfor; ?>
                                            
                                            <li class="page-item <?= $paginaCpfAtual >= $cpfData['total_paginas'] ? 'disabled' : '' ?>">
                                                <a class="page-link" href="?tab=cpf&page=<?= $paginaCpfAtual + 1 ?>">Próximo</a>
                                            </li>
                                        </ul>
                                    </nav>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="footer">
            <div class="container">
                <div class="footer-content">
                    <div class="footer-left">
                        <div class="government-logos">
                            <div class="gov-go-logo">
                                <img src="assets/img/logo-cge-e-estado-goias.png" alt="CGE e Estado de Goiás" class="logo-image">
                            </div>
                        </div>
                    </div>
                    <div class="footer-right">
                    </div>
                </div>
            </div>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Forçar reload do cache
        console.log('PINE Script carregado - versão 1.1 - ' + new Date().toISOString());
        
        // Forçar reload da página se necessário
        if (window.location.search.indexOf('reload=1') === -1 && window.location.search.indexOf('tab=pine') !== -1) {
            console.log('Forçando reload para aplicar alterações');
            // window.location.href = window.location.href + '&reload=1';
        }
    </script>
    <script>
        // Script para carregamento no botão de análise PINE
        document.getElementById('analysis-form').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submit-btn');
            const btnText = document.getElementById('btn-text');
            const loadingSpinner = document.getElementById('loading-spinner');
            
            submitBtn.disabled = true;
            btnText.classList.add('d-none');
            loadingSpinner.classList.remove('d-none');
            
            // Atualizar URL atual para recarregar dados após análise
            const portalUrl = document.getElementById('portal_url').value;
            if (portalUrl) {
                currentPortalUrl = portalUrl;
            }
        });

        // PINE - Funcionalidades avançadas
        let currentPortalUrl = '';
        let currentFilters = {
            search: '',
            organization: '',
            status: '',
            page: 1
        };

        // Carregar dados PINE quando a aba for ativada
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM carregado - configurando PINE');
            
            // Aguardar um pouco para garantir que todos os elementos estejam disponíveis
            setTimeout(() => {
                setupPineEventListeners();
                checkForExistingPortalUrl();
                
                // Se estivermos na aba PINE, carregar dados automaticamente
                const urlParams = new URLSearchParams(window.location.search);
                const tab = urlParams.get('tab');
                if (tab === 'pine') {
                    loadExistingPineData();
                }
            }, 200);
        });

        function setupPineEventListeners() {
            const pineTab = document.getElementById('pine-tab');
            if (pineTab) {
                console.log('PINE tab encontrada');
                pineTab.addEventListener('shown.bs.tab', function() {
                    console.log('PINE tab ativada');
                    const portalUrl = document.getElementById('portal_url').value;
                    if (portalUrl) {
                        currentPortalUrl = portalUrl;
                    }
                    // Carregar dados existentes quando a aba for ativada
                    loadExistingPineData();
                });
            } else {
                console.log('PINE tab NÃO encontrada');
            }

            // Event listeners para filtros - com verificação de existência
            const searchDataset = document.getElementById('search-dataset');
            const clearSearchBtn = document.getElementById('clear-search');
            const organizationDropdown = document.getElementById('organizationDropdown');
            const organizationList = document.getElementById('organization-list');
            const statusRadios = document.querySelectorAll('input[name="status-filter"]');
            const clearFilters = document.getElementById('clear-filters');
            const exportCsv = document.getElementById('export-csv');

            if (searchDataset) {
                searchDataset.addEventListener('input', debounce(applyFilters, 500));
                console.log('Event listener para busca adicionado');
            } else {
                console.log('Campo de busca não encontrado');
            }

            if (clearSearchBtn) {
                clearSearchBtn.addEventListener('click', function() {
                    document.getElementById('search-dataset').value = '';
                    applyFilters();
                });
                console.log('Event listener para limpar busca adicionado');
            }

            if (organizationList) {
                organizationList.addEventListener('click', function(e) {
                    e.preventDefault();
                    const item = e.target.closest('.dropdown-item');
                    if (item) {
                        const value = item.getAttribute('data-value');
                        const text = item.textContent;
                        
                        document.getElementById('filter-organization').value = value;
                        document.getElementById('organization-text').textContent = text;
                        
                        applyFilters();
                    }
                });
                console.log('Event listener para organização adicionado');
            }

            if (statusRadios.length > 0) {
                statusRadios.forEach(radio => {
                    radio.addEventListener('change', function() {
                        document.getElementById('filter-status').value = this.value;
                        applyFilters();
                    });
                });
                console.log('Event listeners para status adicionados');
            }

            if (clearFilters) {
                clearFilters.addEventListener('click', function() {
                    clearFiltersFunction();
                });
                console.log('Event listener para limpar filtros adicionado');
            } else {
                console.log('Botão limpar filtros não encontrado');
            }

            if (exportCsv) {
                exportCsv.addEventListener('click', exportFilteredData);
                console.log('Event listener para exportar CSV adicionado');
            } else {
                console.log('Botão exportar CSV não encontrado');
            }
        }

        function checkForExistingPortalUrl() {
            const portalUrl = document.getElementById('portal_url').value;
            if (portalUrl) {
                console.log('URL de portal encontrada:', portalUrl);
                currentPortalUrl = portalUrl;
                // Removido carregamento automático - análise só será executada manualmente
            } else {
                loadExistingPineData();
            }
        }

        async function loadExistingPineData() {
            console.log('=== INÍCIO loadExistingPineData ===');
            
            showPineLoading(true);
            hidePineSections();

            try {
                console.log('📊 Carregando dados existentes...');
                const baseUrl = window.location.origin + window.location.pathname.replace('app.php', '');
                
                // Usar portal_url atual se disponível
                const portalUrl = currentPortalUrl || document.getElementById('portal_url').value;
                const statsUrl = `${baseUrl}api/pine-data.php?action=stats${portalUrl ? '&portal_url=' + encodeURIComponent(portalUrl) : ''}`;
                console.log('URL da API:', statsUrl);
                
                const statsResponse = await fetch(statsUrl);
                console.log('Status da resposta:', statsResponse.status);
                
                if (!statsResponse.ok) {
                    throw new Error(`HTTP ${statsResponse.status}: ${statsResponse.statusText}`);
                }
                
                const statsData = await statsResponse.json();
                console.log('📊 Dados recebidos:', statsData);

                if (statsData.success) {
                    console.log('✅ Sucesso! Atualizando dashboard...');
                    updatePineDashboard(statsData.stats);
                    populateOrganizationFilter(statsData.organizations);

                    if (statsData.portal_url) {
                        currentPortalUrl = statsData.portal_url;
                        document.getElementById('portal_url').value = statsData.portal_url;
                    }
                    
                    showPineSections(['pine-dashboard', 'pine-filters']);
                    console.log('✅ Dashboard e filtros exibidos');
                    
                    console.log('📋 Carregando datasets...');
                    await loadPineDatasets();
                } else {
                    console.log('❌ API retornou success: false');
                    console.log('Mensagem de erro:', statsData.message);
                    showPineSection('pine-no-data');
                }

            } catch (error) {
                console.error('❌ Erro ao carregar dados existentes:', error);
                showPineSection('pine-no-data');
            } finally {
                showPineLoading(false);
                console.log('=== FIM loadExistingPineData ===');
            }
        }

        // Função para carregar dados PINE
        async function loadPineData() {
            console.log('=== INÍCIO loadPineData ===');
            console.log('URL atual:', currentPortalUrl);
            
            // Verificar se os elementos existem
            checkPineElements();
            
            if (!currentPortalUrl) {
                console.log('❌ Nenhuma URL de portal definida');
                return;
            }

            showPineLoading(true);
            hidePineSections();

            try {
                console.log('📊 Carregando estatísticas...');
                // Usar caminho absoluto para evitar problemas de roteamento
                const baseUrl = window.location.origin + window.location.pathname.replace('app.php', '');
                const statsUrl = `${baseUrl}api/pine-data.php?action=stats`;
                console.log('URL base:', baseUrl);
                console.log('URL da API:', statsUrl);
                
                const statsResponse = await fetch(statsUrl);
                console.log('Status da resposta:', statsResponse.status);
                console.log('Headers da resposta:', statsResponse.headers);
                
                if (!statsResponse.ok) {
                    throw new Error(`HTTP ${statsResponse.status}: ${statsResponse.statusText}`);
                }
                
                const statsData = await statsResponse.json();
                console.log('📊 Dados recebidos:', statsData);

                if (statsData.success) {
                    console.log('✅ Sucesso! Atualizando dashboard...');
                    updatePineDashboard(statsData.stats);
                    populateOrganizationFilter(statsData.organizations);
                    
                    // Mostrar dashboard e filtros
                    showPineSections(['pine-dashboard', 'pine-filters']);
                    console.log('✅ Dashboard e filtros exibidos');
                    
                    // Verificar se realmente foram exibidos
                    setTimeout(() => {
                        checkPineElements();
                    }, 100);
                } else {
                    console.log('❌ API retornou success: false');
                    console.log('Mensagem de erro:', statsData.message);
                    showPineSection('pine-no-data');
                }

                // Carregar datasets
                console.log('📋 Carregando datasets...');
                await loadPineDatasets();

            } catch (error) {
                console.error('❌ Erro ao carregar dados PINE:', error);
                console.error('Tipo do erro:', typeof error);
                console.error('Stack trace:', error.stack);
                showPineSection('pine-no-data');
            } finally {
                showPineLoading(false);
                console.log('=== FIM loadPineData ===');
            }
        }

        // Função para carregar datasets com filtros
        async function loadPineDatasets() {
            const portalUrl = currentPortalUrl || document.getElementById('portal_url').value;

            const params = new URLSearchParams({
                action: 'datasets',
                portal_url: portalUrl,
                page: currentFilters.page,
                per_page: 15,
                organization: currentFilters.organization,
                status: currentFilters.status,
                search: currentFilters.search
            });

            try {
                const baseUrl = window.location.origin + window.location.pathname.replace('app.php', '');
                const response = await fetch(`${baseUrl}api/pine-data.php?${params}`);
                const data = await response.json();

                console.log('📋 Dados dos datasets recebidos:', data);

                if (data.success) {
                    updatePineDatasetsTable(data.datasets);
                    updatePinePagination(data);
                    updateDatasetsTitle(data.total);
                    showPineSection('pine-datasets');
                } else {
                    console.log('❌ Erro ao carregar datasets:', data.message);
                    showPineSection('pine-no-data');
                }
            } catch (error) {
                console.error('Erro ao carregar datasets:', error);
                showPineSection('pine-no-data');
            }
        }

        // Atualizar dashboard
        function updatePineDashboard(stats) {
            console.log('📊 Atualizando dashboard com stats:', stats);
            document.getElementById('total-datasets').textContent = stats.total_datasets || 0;
            document.getElementById('datasets-atualizados').textContent = stats.datasets_atualizados || 0;
            document.getElementById('datasets-desatualizados').textContent = stats.datasets_desatualizados || 0;
            document.getElementById('total-orgaos').textContent = stats.total_orgaos || 0;
        }

        // Popular filtro de organizações
        function populateOrganizationFilter(organizations) {
            const organizationList = document.getElementById('organization-list');
            if (!organizationList) return;
            
            organizationList.innerHTML = '';
            
            organizations.forEach(org => {
                const li = document.createElement('li');
                const a = document.createElement('a');
                a.className = 'dropdown-item';
                a.setAttribute('data-value', org);
                a.textContent = org;
                li.appendChild(a);
                organizationList.appendChild(li);
            });
            
            console.log(`✅ ${organizations.length} organizações carregadas no dropdown`);
        }

        // Atualizar tabela de datasets
        function updatePineDatasetsTable(datasets) {
            const tbody = document.getElementById('datasets-tbody');
            tbody.innerHTML = '';

            console.log('📊 Atualizando tabela com', datasets.length, 'datasets');

            if (datasets.length === 0) {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td colspan="6" class="text-center text-muted py-4">
                        <i class="fas fa-inbox icon"></i><br>
                        Nenhum dataset encontrado
                    </td>
                `;
                tbody.appendChild(row);
                return;
            }

            datasets.forEach(dataset => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>
                        <div>
                            <strong>${escapeHtml(dataset.name)}</strong><br>
                            <small class="text-muted">ID: ${escapeHtml(dataset.dataset_id)}</small>
                        </div>
                    </td>
                    <td>
                        <span class="text-muted">${escapeHtml(dataset.organization)}</span>
                    </td>
                    <td>
                        <div>
                            <strong>${dataset.last_updated ? formatDate(dataset.last_updated) : 'N/A'}</strong><br>
                            <small class="text-muted">
                                ${dataset.days_since_update !== 2147483647 ? dataset.days_since_update + ' dias atrás' : 'Sem data'}
                            </small>
                        </div>
                    </td>
                    <td>
                        ${dataset.status === 'Atualizado' 
                            ? '<span class="badge bg-success"><i class="fas fa-check icon"></i> Atualizado</span>'
                            : '<span class="badge bg-danger"><i class="fas fa-exclamation-triangle icon"></i> Desatualizado</span>'
                        }
                    </td>
                    <td><span class="badge bg-secondary">${dataset.resources_count}</span></td>
                    <td>
                        <a href="${escapeHtml(dataset.url)}" target="_blank" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-external-link-alt icon"></i> Ver
                        </a>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        // Atualizar paginação
        function updatePinePagination(data) {
            const pagination = document.getElementById('pine-pagination');
            const totalPages = data.total_pages;
            const currentPage = data.page;

            if (totalPages <= 1) {
                pagination.innerHTML = '';
                return;
            }

            let paginationHtml = '<ul class="pagination justify-content-center">';
            
            // Botão anterior
            paginationHtml += `<li class="page-item ${currentPage <= 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${currentPage - 1}">Anterior</a>
            </li>`;

            // Páginas
            for (let i = 1; i <= totalPages; i++) {
                paginationHtml += `<li class="page-item ${i === currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                </li>`;
            }

            // Botão próximo
            paginationHtml += `<li class="page-item ${currentPage >= totalPages ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${currentPage + 1}">Próximo</a>
            </li>`;

            paginationHtml += '</ul>';
            pagination.innerHTML = paginationHtml;

            // Event listeners para paginação
            pagination.addEventListener('click', function(e) {
                e.preventDefault();
                const pageLink = e.target.closest('.page-link');
                if (pageLink && !pageLink.parentElement.classList.contains('disabled')) {
                    const page = parseInt(pageLink.getAttribute('data-page'));
                    if (page && page !== currentPage) {
                        currentFilters.page = page;
                        loadPineDatasets();
                    }
                }
            });
        }

        // Atualizar título da lista
        function updateDatasetsTitle(total) {
            document.getElementById('datasets-title').textContent = `Lista de Datasets (${total})`;
        }

        // Aplicar filtros
        function applyFilters() {
            currentFilters.search = document.getElementById('search-dataset').value;
            currentFilters.organization = document.getElementById('filter-organization').value;
            currentFilters.status = document.getElementById('filter-status').value;
            currentFilters.page = 1; // Reset para primeira página

            console.log('🔍 Aplicando filtros:', currentFilters);
            loadPineDatasets();
        }

        // Limpar filtros
        function clearFiltersFunction() {
            console.log('🧹 Limpando filtros...');
            
            document.getElementById('search-dataset').value = '';
            
            document.getElementById('filter-organization').value = '';
            document.getElementById('organization-text').textContent = 'Todos os órgãos';
            
            document.getElementById('status-all').checked = true;
            document.getElementById('filter-status').value = '';
            
            currentFilters = {
                search: '',
                organization: '',
                status: '',
                page: 1
            };

            console.log('✅ Filtros limpos, recarregando datasets...');
            loadPineDatasets();
        }

        // Exportar dados filtrados
        function exportFilteredData() {
            if (!currentPortalUrl) return;

            const params = new URLSearchParams({
                portal_url: currentPortalUrl,
                ...currentFilters,
                export: 'csv'
            });

            const baseUrl = window.location.origin + window.location.pathname.replace('app.php', '');
            window.open(`${baseUrl}api/pine-data.php?action=datasets&${params}`, '_blank');
        }

        // Funções auxiliares
        function showPineLoading(show) {
            document.getElementById('pine-loading').style.display = show ? 'block' : 'none';
        }

        function showPineSection(sectionId) {
            console.log('Mostrando seção:', sectionId);
            const element = document.getElementById(sectionId);
            if (element) {
                // Forçar exibição com !important
                element.style.setProperty('display', 'block', 'important');
                element.style.setProperty('visibility', 'visible', 'important');
                element.style.setProperty('opacity', '1', 'important');
                console.log('✅ Seção exibida:', sectionId);
                console.log('   - display:', element.style.display);
                console.log('   - visibility:', element.style.visibility);
                console.log('   - opacity:', element.style.opacity);
            } else {
                console.log('❌ Elemento não encontrado:', sectionId);
            }
        }

        function showPineSections(sectionIds) {
            console.log('Mostrando múltiplas seções:', sectionIds);
            sectionIds.forEach(sectionId => {
                showPineSection(sectionId);
            });
        }

        // Função para verificar se os elementos existem
        function checkPineElements() {
            console.log('=== VERIFICANDO ELEMENTOS PINE ===');
            const elements = [
                'pine-dashboard',
                'pine-filters', 
                'pine-datasets',
                'pine-loading',
                'pine-no-data'
            ];
            
            elements.forEach(id => {
                const element = document.getElementById(id);
                if (element) {
                    console.log(`✅ ${id}: encontrado, display = ${element.style.display}`);
                } else {
                    console.log(`❌ ${id}: NÃO encontrado`);
                }
            });
            console.log('=== FIM VERIFICAÇÃO ===');
        }

        // Função de teste para forçar exibição
        function forceShowPineElements() {
            console.log('=== FORÇANDO EXIBIÇÃO DOS ELEMENTOS ===');
            const elements = ['pine-dashboard', 'pine-filters'];
            
            elements.forEach(id => {
                const element = document.getElementById(id);
                if (element) {
                    element.style.setProperty('display', 'block', 'important');
                    element.style.visibility = 'visible';
                    element.style.opacity = '1';
                    element.classList.add('fade-in');
                    console.log(`✅ ${id} forçado a aparecer`);
                } else {
                    console.log(`❌ ${id} não encontrado`);
                }
            });
        }

        // Expor função globalmente para teste
        window.forceShowPineElements = forceShowPineElements;

        function hidePineSections() {
            const sections = ['pine-dashboard', 'pine-filters', 'pine-datasets', 'pine-no-data'];
            sections.forEach(id => {
                document.getElementById(id).style.display = 'none';
            });
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('pt-BR');
        }

        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        // Script para carregamento no botão de geração de dicionário BIA
        document.getElementById('dicionario-form').addEventListener('submit', function(e) {
            e.preventDefault(); // Prevenir submit padrão
            
            const gerarBtn = document.getElementById('gerar-btn');
            const btnText = document.getElementById('btn-text');
            const btnLoading = document.getElementById('btn-loading');
            const progressContainer = document.getElementById('progress-container');
            const progressBar = document.getElementById('progress-bar');
            const progressText = document.getElementById('progress-text');
            const progressPercent = document.getElementById('progress-percent');
            const recursoUrl = document.getElementById('recurso_url').value;
            
            if (!recursoUrl) {
                showMessage('Por favor, informe o link do recurso CKAN.', 'error');
                return;
            }
            
            // Desabilitar botão e mostrar loading
            gerarBtn.disabled = true;
            btnText.classList.add('d-none');
            btnLoading.classList.remove('d-none');
            
            // Mostrar barra de progresso
            progressContainer.classList.remove('d-none');
            
            // Simular progresso
            let progress = 0;
            const progressSteps = [
                { percent: 20, text: 'Conectando com a API CKAN...' },
                { percent: 40, text: 'Analisando estrutura dos dados...' },
                { percent: 60, text: 'Processando metadados...' },
                { percent: 80, text: 'Gerando documento Word...' },
                { percent: 100, text: 'Finalizando e preparando download...' }
            ];
            
            let currentStep = 0;
            const progressInterval = setInterval(() => {
                if (currentStep < progressSteps.length) {
                    const step = progressSteps[currentStep];
                    progressBar.style.width = step.percent + '%';
                    progressBar.setAttribute('aria-valuenow', step.percent);
                    progressText.textContent = step.text;
                    progressPercent.textContent = step.percent + '%';
                    currentStep++;
                } else {
                    clearInterval(progressInterval);
                }
            }, 800);
            
            // Fazer requisição AJAX
            const formData = new FormData();
            formData.append('action', 'gerar_dicionario');
            formData.append('recurso_url', recursoUrl);
            
            console.log('Enviando requisição AJAX...');
            console.log('URL:', window.location.href);
            console.log('FormData:', Object.fromEntries(formData));
            
            const baseUrl = window.location.origin + window.location.pathname.replace('app.php', '');
            fetch(`${baseUrl}api/bia.php`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);
                
                // Verificar se a resposta é JSON válida
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    console.error('Resposta não é JSON válida. Content-Type:', contentType);
                    return response.text().then(text => {
                        console.error('Resposta em texto:', text);
                        throw new Error(`Resposta inválida do servidor: ${text.substring(0, 200)}...`);
                    });
                }
                
                if (!response.ok) {
                    return response.json().then(errorData => {
                        console.error('Erro do servidor:', errorData);
                        throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
                    });
                }
                
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                
                // Limpar progresso
                clearInterval(progressInterval);
                progressBar.style.width = '100%';
                progressBar.setAttribute('aria-valuenow', '100');
                progressText.textContent = 'Processando resposta...';
                progressPercent.textContent = '100%';
                
                if (data.success) {
                    console.log('Sucesso! Iniciando download:', data.downloadFileName);
                    
                    // Iniciar download
                    window.location.href = 'download.php?file=' + encodeURIComponent(data.downloadFileName) + '&path=' + encodeURIComponent(data.downloadFile);
                    
                    // Mostrar notificação de sucesso
                    showMessage(data.message, 'success');
                    
                    // Resetar formulário após 2 segundos
                    setTimeout(() => {
                        document.getElementById('dicionario-form').reset();
                        progressContainer.classList.add('d-none');
                        gerarBtn.disabled = false;
                        btnText.classList.remove('d-none');
                        btnLoading.classList.add('d-none');
                    }, 2000);
                } else {
                    console.error('Erro do servidor:', data.message);
                    throw new Error(data.message || 'Erro desconhecido do servidor');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                clearInterval(progressInterval);
                progressContainer.classList.add('d-none');
                gerarBtn.disabled = false;
                btnText.classList.remove('d-none');
                btnLoading.classList.add('d-none');
                showMessage('Erro ao gerar dicionário: ' + error.message, 'error');
            });
        });

        // Script para manter a aba ativa após o refresh da página
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab');
            if (tab) {
                const tabElement = document.querySelector('#' + tab + '-tab');
                if(tabElement) {
                    const tabInstance = new bootstrap.Tab(tabElement);
                    tabInstance.show();
                }
            }
            
            // Paginação AJAX
            const pagination = document.getElementById('pagination');
            if (pagination) {
                pagination.addEventListener('click', function(e) {
                    e.preventDefault();
                    const pageLink = e.target.closest('.page-link');
                    if (pageLink && !pageLink.parentElement.classList.contains('disabled')) {
                        const page = pageLink.getAttribute('data-page');
                        if (page) {
                            loadPinePage(page);
                        }
                    }
                });
            }
        });

        // Função para carregar página do PINE via AJAX
        function loadPinePage(page) {
            const portalUrl = document.getElementById('portal_url').value;
            if (!portalUrl) return;
            
            // Mostrar loading
            const tableContainer = document.querySelector('#pine .table-responsive');
            if (tableContainer) {
                tableContainer.innerHTML = '<div class="text-center p-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Carregando...</span></div></div>';
            }
            
            // Fazer requisição AJAX
            fetch('pine.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=load_page&page=' + page + '&portal_url=' + encodeURIComponent(portalUrl)
            })
            .then(response => response.text())
            .then(data => {
                // Atualizar apenas a tabela e paginação
                const parser = new DOMParser();
                const doc = parser.parseFromString(data, 'text/html');
                const newTable = doc.querySelector('.table-responsive');
                const newPagination = doc.querySelector('#pagination');
                
                if (newTable && tableContainer) {
                    tableContainer.innerHTML = newTable.innerHTML;
                }
                
                if (newPagination) {
                    const paginationContainer = document.querySelector('#pagination').parentElement;
                    paginationContainer.innerHTML = newPagination.outerHTML;
                    
                    // Re-adicionar event listeners
                    const newPaginationEl = document.getElementById('pagination');
                    if (newPaginationEl) {
                        newPaginationEl.addEventListener('click', function(e) {
                            e.preventDefault();
                            const pageLink = e.target.closest('.page-link');
                            if (pageLink && !pageLink.parentElement.classList.contains('disabled')) {
                                const page = pageLink.getAttribute('data-page');
                                if (page) {
                                    loadPinePage(page);
                                }
                            }
                        });
                    }
                }
            })
            .catch(error => {
                console.error('Erro ao carregar página:', error);
                if (tableContainer) {
                    tableContainer.innerHTML = '<div class="alert alert-danger">Erro ao carregar dados da página.</div>';
                }
            });
        }

        // Scanner CKAN
        document.addEventListener('DOMContentLoaded', function() {
            const btnScanCkan = document.getElementById('btnScanCkan');
            if (btnScanCkan) {
                btnScanCkan.addEventListener('click', function() {
                    executeScanCkan(false); // Chama sem forçar por padrão
                });
            } else {
            }
        });

        // Função para executar o scanner CKAN (versão assíncrona)
        function executeScanCkan(force = false) {
            const btn = document.getElementById('btnScanCkan');
            if (!btn) {
                return;
            }
            
            const originalText = btn.innerHTML;
            
            btn.disabled = true;
            btn.innerHTML = force ? 'Reiniciando...' : 'Iniciando...';
            
            // Mostra mensagem de feedback imediato
            showMessage('Iniciando análise CKAN...', 'info');
            
            // Prepara os dados com a flag 'force'
            const formData = new FormData();
            formData.append('force', force ? 1 : 0); 
            
            console.log('Iniciando análise CKAN...', { force: force });
            
            const baseUrl = window.location.origin + window.location.pathname.replace('app.php', '');
            fetch(`${baseUrl}api/start-scan.php`, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Resposta recebida:', response.status);
                if (response.status === 409) { 
                    // CONFLITO: Análise já rodando, e não foi solicitado force
                    showConfirmDialog(
                        'Análise em Andamento',
                        'Uma análise já está sendo executada no momento. Deseja cancelar a análise anterior e iniciar uma nova?',
                        'Sim, iniciar nova análise',
                        'Cancelar',
                        () => {
                            executeScanCkan(true); // Chama novamente, forçando o restart
                        },
                        () => {
                            btn.disabled = false;
                            btn.innerHTML = originalText;
                        }
                    );
                    return Promise.reject('Analysis already running');
                }
                if (!response.ok) {
                    throw new Error('Erro ao iniciar a análise. Código: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                console.log('Dados recebidos:', data);
                if (data.success) {
                    showMessage(data.message, 'success');
                    showAsyncProgressModal();
                    startPollingStatus();
                } else {
                    showMessage('Erro: ' + data.message, 'error');
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            })
            .catch(error => {
                console.error('Falha ao iniciar a análise:', error);
                if (error !== 'Analysis already running') {
                    showMessage('Erro grave ao tentar iniciar a análise: ' + error, 'error');
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            });
        }
        
        // Função para verificar status da análise
        let pollingInterval;
        function startPollingStatus() {
            if (pollingInterval) {
                clearInterval(pollingInterval);
            }
            
            pollingInterval = setInterval(() => {
                const baseUrl = window.location.origin + window.location.pathname.replace('app.php', '');
                fetch(`${baseUrl}api/scan-status.php`)
                    .then(response => response.json())
                    .then(statusData => {
                        updateAsyncProgress(statusData);
                        
                        if (!statusData.inProgress) {
                            clearInterval(pollingInterval);
                            hideAsyncProgressModal();
                            
                            const btn = document.getElementById('btnScanCkan');
                            if (btn) {
                                btn.disabled = false;
                                btn.innerHTML = '<i class="fas fa-search icon"></i> Executar Análise CKAN';
                            }
                            
                            if (statusData.status === 'completed') {
                                const results = statusData.results || {};
                                const message = `Análise concluída! ${results.recursos_analisados || 0} recursos verificados, ` +
                                              `${results.recursos_com_cpfs || 0} continham CPFs. Total de ${results.total_cpfs_salvos || 0} CPFs salvos. A página será atualizada.`;
                                
                                showMessage(message, 'success');
                                
                                setTimeout(() => {
                                    window.location.href = window.location.pathname + '?tab=cpf';
                                }, 3000);
                            } else if (statusData.status === 'failed') {
                                showMessage('Análise falhou: ' + (statusData.error || 'Erro desconhecido'), 'error');
                            }
                        }
                    })
                    .catch(error => {   
                        clearInterval(pollingInterval);
                        hideAsyncProgressModal();
                        
                        const btn = document.getElementById('btnScanCkan');
                        if (btn) {
                            btn.disabled = false;
                            btn.innerHTML = '<i class="fas fa-search icon"></i> Executar Análise CKAN';
                        }
                        
                        showMessage('Erro de conexão ao verificar status da análise.', 'error');
                    });
            }, 3000);
        }

        function showMessage(message, type) {
            let alertClass;
            switch(type) {
                case 'success':
                    alertClass = 'alert-success';
                    break;
                case 'warning':
                    alertClass = 'alert-warning';
                    break;
                case 'info':
                    alertClass = 'alert-info';
                    break;
                case 'error':
                default:
                    alertClass = 'alert-danger';
                    break;
            }
            
            const messageDiv = document.createElement('div');
            messageDiv.className = `alert ${alertClass} alert-dismissible fade show`;
            messageDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            const container = document.querySelector('.main-content .container');
            container.insertBefore(messageDiv, container.firstChild);
            
            const timeout = type === 'warning' ? 8000 : 5000;
            setTimeout(() => {
                if (messageDiv.parentNode) {
                    messageDiv.remove();
                }
            }, timeout);
        }

        function showConfirmDialog(title, message, confirmText, cancelText, onConfirm, onCancel) {
            // Remove dialog existente se houver
            const existingDialog = document.getElementById('confirmDialog');
            if (existingDialog) {
                existingDialog.remove();
            }

            const dialogHtml = `
                <div class="modal fade" id="confirmDialog" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                                    ${title}
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p class="mb-0">${message}</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="cancelBtn">
                                    <i class="fas fa-times me-1"></i>
                                    ${cancelText}
                                </button>
                                <button type="button" class="btn btn-warning" id="confirmBtn">
                                    <i class="fas fa-play me-1"></i>
                                    ${confirmText}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            document.body.insertAdjacentHTML('beforeend', dialogHtml);
            
            const modal = new bootstrap.Modal(document.getElementById('confirmDialog'));
            const confirmBtn = document.getElementById('confirmBtn');
            const cancelBtn = document.getElementById('cancelBtn');

            confirmBtn.addEventListener('click', () => {
                modal.hide();
                onConfirm();
            });

            cancelBtn.addEventListener('click', () => {
                modal.hide();
                onCancel();
            });

            // Remove o modal do DOM quando fechado
            document.getElementById('confirmDialog').addEventListener('hidden.bs.modal', () => {
                document.getElementById('confirmDialog').remove();
            });

            modal.show();
        }

        function showAsyncProgressModal() {
            const modalHtml = `
                <div class="modal fade" id="asyncProgressModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
                    <div class="modal-dialog modal-dialog-centered modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="fas fa-search icon"></i> Análise CKAN em Andamento
                                </h5>
                            </div>
                            <div class="modal-body">
                                <div class="text-center mb-3">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Carregando...</span>
                                    </div>
                                </div>
                                
                                <div class="progress mb-3">
                                    <div id="async-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary" 
                                         role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                                    </div>
                                </div>
                                
                                <div id="async-progress-text" class="text-center mb-2">
                                    <strong>Iniciando análise...</strong>
                                </div>
                                
                                <div class="row text-center g-2">
                                    <div class="col-6 col-md-3">
                                        <small class="text-muted">Datasets</small><br>
                                        <strong id="datasets-count">0</strong>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <small class="text-muted">Recursos</small><br>
                                        <strong id="recursos-count">0</strong>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <small class="text-muted">Com CPFs</small><br>
                                        <strong id="cpfs-recursos-count">0</strong>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <small class="text-muted">CPFs Total</small><br>
                                        <strong id="cpfs-total-count">0</strong>
                                    </div>
                                </div>
                                
                                <div class="text-center mt-3">
                                    <small class="text-muted">Este processo roda em segundo plano. Você pode fechar esta janela.</small>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary w-100 w-md-auto" onclick="hideAsyncProgressModal()">
                                    <i class="fas fa-eye-slash"></i> Ocultar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            const existingModal = document.getElementById('asyncProgressModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            const modal = new bootstrap.Modal(document.getElementById('asyncProgressModal'));
            modal.show();
        }

        function updateAsyncProgress(statusData) {
            const progressText = document.getElementById('async-progress-text');
            const progressBar = document.getElementById('async-progress-bar');
            const datasetsCount = document.getElementById('datasets-count');
            const recursosCount = document.getElementById('recursos-count');
            const cpfsRecursosCount = document.getElementById('cpfs-recursos-count');
            const cpfsTotalCount = document.getElementById('cpfs-total-count');
            
            if (statusData.progress) {
                const progress = statusData.progress;
                
                // Atualizar texto do progresso
                if (progressText) {
                    let stepText = progress.current_step || 'Processando...';
                    
                    // Adicionar informação de progresso se disponível
                    if (progress.total_recursos && progress.recursos_processados) {
                        const percent = Math.round((progress.recursos_processados / progress.total_recursos) * 100);
                        stepText += ` (${percent}%)`;
                    }
                    
                    progressText.innerHTML = `<strong>${stepText}</strong>`;
                }
                
                // Atualizar contadores
                if (datasetsCount) datasetsCount.textContent = progress.datasets_analisados || 0;
                if (recursosCount) recursosCount.textContent = progress.recursos_analisados || 0;
                if (cpfsRecursosCount) cpfsRecursosCount.textContent = progress.recursos_com_cpfs || 0;
                if (cpfsTotalCount) cpfsTotalCount.textContent = progress.total_cpfs_salvos || 0;
                
                // Calcular porcentagem real baseada no progresso
                let percentage = 0;
                
                if (progress.total_recursos && progress.recursos_processados) {
                    // Usar progresso real
                    percentage = Math.min(99, (progress.recursos_processados / progress.total_recursos) * 100);
                } else if (progress.recursos_analisados > 0) {
                    // Fallback: estimativa baseada em recursos analisados
                    percentage = Math.min(95, (progress.recursos_analisados / 100) * 100);
                }
                
                if (statusData.status === 'completed') {
                    percentage = 100;
                }
                
                // Atualizar barra de progresso
                if (progressBar) {
                    progressBar.style.width = percentage + '%';
                    progressBar.setAttribute('aria-valuenow', Math.round(percentage));
                    
                    // Adicionar texto de porcentagem na barra
                    if (percentage > 10) {
                        progressBar.textContent = Math.round(percentage) + '%';
                    }
                }
            }
        }

        function hideAsyncProgressModal() {
            const modal = document.getElementById('asyncProgressModal');
            if (modal) {
                const bootstrapModal = bootstrap.Modal.getInstance(modal);
                if (bootstrapModal) {
                    bootstrapModal.hide();
                }
                setTimeout(() => {
                    modal.remove();
                }, 300);
            }
        }

        function showForceAnalysisDialog(message, timeout) {
            const dialogHtml = `
                <div class="modal fade" id="forceAnalysisModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
                    <div class="modal-dialog modal-dialog-centered modal-lg">
                        <div class="modal-content">
                            <div class="modal-header bg-warning text-dark">
                                <h5 class="modal-title">
                                    <i class="fas fa-exclamation-triangle"></i> Análise em Andamento
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="alert alert-info mb-3">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>Uma análise já está em execução.</strong>
                                </div>
                                
                                <p class="mb-3">Você tem duas opções:</p>
                                
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="card border-success">
                                            <div class="card-body">
                                                <h6 class="card-title text-success">
                                                    <i class="fas fa-clock"></i> Aguardar (Recomendado)
                                                </h6>
                                                <p class="card-text small">
                                                    A análise atual continuará normalmente até ser concluída.
                                                    Você pode fechar esta janela e acompanhar o progresso.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card border-warning">
                                            <div class="card-body">
                                                <h6 class="card-title text-warning">
                                                    <i class="fas fa-redo"></i> Forçar Nova Análise
                                                </h6>
                                                <p class="card-text small">
                                                    A análise atual será cancelada e uma nova será iniciada do zero.
                                                    <strong>Use apenas se necessário.</strong>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer d-flex flex-column flex-md-row gap-2">
                                <button type="button" class="btn btn-success w-100 w-md-auto" data-bs-dismiss="modal">
                                    <i class="fas fa-check"></i> Aguardar Análise Atual
                                </button>
                                <button type="button" class="btn btn-warning w-100 w-md-auto" onclick="forceNewAnalysis()">
                                    <i class="fas fa-redo"></i> Forçar Nova Análise
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            const existingModal = document.getElementById('forceAnalysisModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            document.body.insertAdjacentHTML('beforeend', dialogHtml);
            
            const modal = new bootstrap.Modal(document.getElementById('forceAnalysisModal'));
            modal.show();
        }

        function forceNewAnalysis() {
            // Fechar modal de confirmação
            const modal = document.getElementById('forceAnalysisModal');
            if (modal) {
                const bootstrapModal = bootstrap.Modal.getInstance(modal);
                if (bootstrapModal) {
                    bootstrapModal.hide();
                }
                // Remover modal após animação
                setTimeout(() => {
                    modal.remove();
                }, 300);
            }
            
            // Aguardar um pouco para o modal fechar antes de iniciar
            setTimeout(() => {
                executeScanCkan(true);
            }, 400);
        }

        // Função para lidar com download automático (CSP-safe)
        function handleAutoDownload() {
            const downloadData = document.getElementById('download-data');
            if (downloadData) {
                const fileName = downloadData.getAttribute('data-file');
                const filePath = downloadData.getAttribute('data-path');
                
                if (fileName && filePath) {
                    // Download automático
                    window.location.href = 'download.php?file=' + encodeURIComponent(fileName) + '&path=' + encodeURIComponent(filePath);
                    
                    // Remove o pop-up após 3 segundos
                    setTimeout(function() {
                        const notification = document.getElementById('download-notification');
                        if (notification) {
                            // Usar método nativo do Bootstrap 5
                            if (typeof bootstrap !== 'undefined') {
                                const bsAlert = bootstrap.Alert.getOrCreateInstance(notification);
                                bsAlert.close();
                            } else {
                                // Fallback: remover manualmente
                                notification.style.display = 'none';
                            }
                        }
                    }, 3000);
                }
            }
        }

        // ========== ANÁLISE DE CPF CKAN ==========
        
        // Variáveis globais para controle da análise
        let scanStatusInterval = null;
        let scanInProgress = false;

        // Função principal para executar análise CKAN
        function executeScanCkan(force = false) {
            console.log('🔍 Iniciando análise CKAN... Force:', force);
            
            // Mostrar modal de progresso imediatamente
            showAsyncProgressModal();
            
            // Atualizar texto inicial do modal
            const progressText = document.getElementById('async-progress-text');
            if (progressText) {
                progressText.innerHTML = force 
                    ? '<strong>Cancelando análise anterior e iniciando nova...</strong>' 
                    : '<strong>Iniciando análise...</strong>';
            }
            
            // Fazer requisição para iniciar análise
            const formData = new FormData();
            if (force) {
                formData.append('force', '1');
            }
            
            const baseUrl = window.location.origin + window.location.pathname.replace('app.php', '');
            fetch(`${baseUrl}api/start-scan.php`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                
                if (response.status === 409 && !force) {
                    // Conflito - análise já em andamento (apenas se não for force)
                    return response.json().then(data => {
                        hideAsyncProgressModal();
                        showForceAnalysisDialog(data.message, 0);
                        throw new Error('Análise já em andamento');
                    });
                }
                
                if (!response.ok) {
                    return response.json().then(errorData => {
                        throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
                    });
                }
                
                return response.json();
            })
            .then(data => {
                console.log('✅ Análise iniciada:', data);
                
                if (data.success) {
                    scanInProgress = true;
                    
                    // Atualizar modal com mensagem de sucesso
                    const progressText = document.getElementById('async-progress-text');
                    if (progressText) {
                        progressText.innerHTML = '<strong>Análise iniciada! Aguardando worker...</strong>';
                    }
                    
                    // Mostrar notificação de sucesso (pequena, não intrusiva)
                    showMessage(data.message, 'success');
                    
                    // Iniciar monitoramento do progresso após 1 segundo
                    setTimeout(() => {
                        startScanStatusMonitoring();
                    }, 1000);
                } else {
                    hideAsyncProgressModal();
                    showMessage(data.message || 'Erro ao iniciar análise', 'error');
                }
            })
            .catch(error => {
                console.error('❌ Erro ao iniciar análise:', error);
                if (error.message !== 'Análise já em andamento') {
                    hideAsyncProgressModal();
                    showMessage('Erro ao iniciar análise: ' + error.message, 'error');
                }
            });
        }

        // Função para monitorar o status da análise
        function startScanStatusMonitoring() {
            console.log('📊 Iniciando monitoramento de status...');
            
            // Limpar intervalo anterior se existir
            if (scanStatusInterval) {
                clearInterval(scanStatusInterval);
            }
            
            // Verificar status a cada 1 segundo (mais responsivo)
            scanStatusInterval = setInterval(() => {
                checkScanStatus();
            }, 1000);
            
            // Verificar imediatamente
            checkScanStatus();
        }

        // Função para verificar o status da análise
        function checkScanStatus() {
            const baseUrl = window.location.origin + window.location.pathname.replace('app.php', '');
            fetch(`${baseUrl}api/scan-status.php`, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                console.log('📊 Status da análise:', data);
                
                if (data.success) {
                    const status = data.status;
                    
                    // Atualizar modal de progresso
                    updateAsyncProgress(data);
                    
                    // Verificar se a análise terminou
                    if (status === 'completed') {
                        console.log('✅ Análise concluída!');
                        scanInProgress = false;
                        clearInterval(scanStatusInterval);
                        scanStatusInterval = null;
                        
                        // Atualizar modal para mostrar conclusão
                        const progressText = document.getElementById('async-progress-text');
                        if (progressText) {
                            progressText.innerHTML = '<strong class="text-success">✓ Análise concluída com sucesso!</strong>';
                        }
                        
                        // Aguardar 2 segundos antes de fechar e recarregar
                        setTimeout(() => {
                            hideAsyncProgressModal();
                            showMessage('Análise concluída! Recarregando resultados...', 'success');
                            
                            // Recarregar página após mais 1 segundo
                            setTimeout(() => {
                                window.location.href = '?tab=cpf';
                            }, 1000);
                        }, 2000);
                        
                    } else if (status === 'failed' || status === 'error') {
                        console.error('❌ Análise falhou:', data.message);
                        scanInProgress = false;
                        clearInterval(scanStatusInterval);
                        scanStatusInterval = null;
                        
                        // Atualizar modal para mostrar erro
                        const progressText = document.getElementById('async-progress-text');
                        if (progressText) {
                            progressText.innerHTML = '<strong class="text-danger">✗ Erro na análise</strong>';
                        }
                        
                        setTimeout(() => {
                            hideAsyncProgressModal();
                            showMessage('Erro na análise: ' + (data.message || 'Erro desconhecido'), 'error');
                        }, 2000);
                        
                    } else if (status === 'cancelled' || status === 'stopped') {
                        console.log('⚠️ Análise cancelada');
                        scanInProgress = false;
                        clearInterval(scanStatusInterval);
                        scanStatusInterval = null;
                        
                        hideAsyncProgressModal();
                        showMessage('Análise cancelada.', 'warning');
                    }
                }
            })
            .catch(error => {
                console.error('❌ Erro ao verificar status:', error);
                // Não para o monitoramento em caso de erro de rede temporário
            });
        }

        // Event listener para o botão de análise CKAN
        document.addEventListener('DOMContentLoaded', function() {
            const btnScanCkan = document.getElementById('btnScanCkan');
            
            if (btnScanCkan) {
                console.log('✅ Botão de análise CKAN encontrado');
                
                btnScanCkan.addEventListener('click', function(e) {
                    e.preventDefault();
                    console.log('🖱️ Botão de análise CKAN clicado');
                    
                    // Verificar se já há uma análise em andamento
                    const baseUrl = window.location.origin + window.location.pathname.replace('app.php', '');
                    fetch(`${baseUrl}api/scan-status.php`, {
                        method: 'GET',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.inProgress) {
                            // Análise já em andamento - perguntar se quer forçar
                            showForceAnalysisDialog(
                                'Uma análise já está em execução. Deseja forçar uma nova análise?',
                                0
                            );
                        } else {
                            // Iniciar nova análise
                            executeScanCkan(false);
                        }
                    })
                    .catch(error => {
                        console.error('Erro ao verificar status:', error);
                        // Se houver erro ao verificar, tenta iniciar mesmo assim
                        executeScanCkan(false);
                    });
                });
            } else {
                console.warn('⚠️ Botão de análise CKAN não encontrado');
            }
            
            // Executar download automático se necessário
            handleAutoDownload();
            
            // Inicializar funcionalidades de CPF
            initializeCpfFeatures();
        });
        
        // Funcionalidades de CPF
        function initializeCpfFeatures() {
            console.log('🔍 Inicializando funcionalidades de CPF');
            
            // Verificar se estamos na aba CPF
            const cpfTab = document.getElementById('cpf');
            if (!cpfTab) {
                console.log('❌ Aba CPF não encontrada');
                return;
            }
            
            // Carregar dados de CPF se a aba estiver ativa
            const cpfTabButton = document.querySelector('[data-bs-target="#cpf"]');
            if (cpfTabButton) {
                cpfTabButton.addEventListener('shown.bs.tab', function() {
                    console.log('📊 Aba CPF ativada - carregando dados');
                    loadCpfData();
                });
            }
            
            // Se a aba CPF já estiver ativa, carregar dados
            if (cpfTab.classList.contains('active') || window.location.hash === '#cpf' || new URLSearchParams(window.location.search).get('tab') === 'cpf') {
                console.log('📊 Aba CPF já ativa - carregando dados');
                loadCpfData();
            }
        }
        
        // Carregar dados de CPF via AJAX
        function loadCpfData(page = 1, filters = {}) {
            console.log('🔄 Carregando dados de CPF - Página:', page);
            console.log('🔍 Filtros aplicados:', filters);
            
            const params = new URLSearchParams({
                page: page,
                per_page: 10,
                ...filters
            });
            
            // Usar caminho correto baseado na estrutura atual
            const baseUrl = window.location.origin + window.location.pathname.replace('app.php', '');
            const url = `${baseUrl}api/cpf.php?action=list&${params}`;
            console.log('🌐 URL da requisição:', url);
            
            // Mostrar loading
            showCpfLoading();
            
            fetch(url)
                .then(response => {
                    console.log('📡 Resposta recebida:', response.status, response.statusText);
                    
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('✅ Dados de CPF carregados:', data);
                    
                    if (data.success) {
                        updateCpfDisplay(data);
                        updateCpfPagination(data);
                    } else {
                        console.error('❌ API retornou erro:', data);
                        showCpfError(data.message || 'Erro ao carregar dados de CPF', data.debug);
                    }
                })
                .catch(error => {
                    console.error('❌ Erro ao carregar dados de CPF:', error);
                    showCpfError('Erro de conexão: ' + error.message);
                })
                .finally(() => {
                    hideCpfLoading();
                });
        }
        
        // Mostrar loading na seção CPF
        function showCpfLoading() {
            const cpfContent = document.querySelector('#cpf .table-responsive');
            if (cpfContent) {
                cpfContent.innerHTML = `
                    <div class="text-center p-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                        <p class="mt-2">Carregando dados de CPF...</p>
                    </div>
                `;
            }
        }
        
        // Esconder loading na seção CPF
        function hideCpfLoading() {
            // O loading será substituído pelo conteúdo real
        }
        
        // Atualizar exibição dos dados de CPF
        function updateCpfDisplay(data) {
            const tableContainer = document.querySelector('#cpf .table-responsive');
            if (!tableContainer) return;
            
            if (!data.findings || data.findings.length === 0) {
                tableContainer.innerHTML = `
                    <div class="text-center p-4">
                        <i class="fas fa-info-circle text-muted" style="font-size: 3rem;"></i>
                        <h5 class="mt-3">Nenhum CPF encontrado</h5>
                        <p class="text-muted">Não foram encontrados CPFs nos recursos analisados.</p>
                    </div>
                `;
                return;
            }
            
            let tableHtml = `
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Dataset</th>
                            <th>Recurso</th>
                            <th>Formato</th>
                            <th>CPFs</th>
                            <th>Verificado</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            data.findings.forEach((finding, index) => {
                tableHtml += `
                    <tr>
                        <td>
                            <div>
                                <strong>${escapeHtml(finding.dataset_name)}</strong>
                                <br>
                                <small class="text-muted">ID: ${escapeHtml(finding.dataset_id)}</small>
                            </div>
                        </td>
                        <td>
                            <div>
                                <strong>${escapeHtml(finding.resource_name)}</strong>
                                <br>
                                <small class="text-muted">ID: ${escapeHtml(finding.resource_id)}</small>
                                <br>
                                <a href="${escapeHtml(finding.resource_url)}" target="_blank" class="text-decoration-none">
                                    <small><i class="fas fa-external-link-alt"></i> Ver recurso</small>
                                </a>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-secondary">${finding.resource_format.toUpperCase()}</span>
                        </td>
                        <td>
                            <span class="badge bg-danger">${finding.cpf_count} CPFs</span>
                        </td>
                        <td>
                            <small>${formatDate(finding.last_checked)}</small>
                        </td>
                        <td>
                            <div class="btn-group" role="group">
                                <button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#collapse${index}" aria-expanded="false">
                                    <i class="fas fa-eye icon"></i> Ver CPFs
                                </button>
                                ${finding.dataset_url && finding.dataset_url !== '#' ? 
                                    `<a href="${escapeHtml(finding.dataset_url)}" target="_blank" class="btn btn-outline-success btn-sm">
                                        <i class="fas fa-external-link-alt icon"></i> Dataset
                                    </a>` : ''
                                }
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="6" class="p-0">
                            <div class="collapse" id="collapse${index}">
                                <div class="p-3 bg-light">
                                    <h6 class="mb-3">
                                        <i class="fas fa-id-card icon"></i>
                                        CPFs Encontrados (${finding.cpf_count})
                                    </h6>
                                    <div class="cpf-list">
                                        ${finding.cpfs.map(cpf => `<span class="cpf-item">${cpf}</span>`).join('')}
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                `;
            });
            
            tableHtml += `
                    </tbody>
                </table>
            `;
            
            tableContainer.innerHTML = tableHtml;
        }
        
        // Atualizar paginação de CPF
        function updateCpfPagination(data) {
            const paginationContainer = document.querySelector('#cpf nav[aria-label="Navegação da página de CPFs"]');
            if (!paginationContainer || data.total_pages <= 1) {
                if (paginationContainer) paginationContainer.style.display = 'none';
                return;
            }
            
            let paginationHtml = `
                <ul class="pagination justify-content-center">
                    <li class="page-item ${!data.has_prev ? 'disabled' : ''}">
                        <a class="page-link" href="#" onclick="loadCpfData(${data.page - 1}); return false;">Anterior</a>
                    </li>
            `;
            
            for (let i = 1; i <= data.total_pages; i++) {
                paginationHtml += `
                    <li class="page-item ${i === data.page ? 'active' : ''}" aria-current="page">
                        <a class="page-link" href="#" onclick="loadCpfData(${i}); return false;">${i}</a>
                    </li>
                `;
            }
            
            paginationHtml += `
                    <li class="page-item ${!data.has_next ? 'disabled' : ''}">
                        <a class="page-link" href="#" onclick="loadCpfData(${data.page + 1}); return false;">Próximo</a>
                    </li>
                </ul>
            `;
            
            paginationContainer.innerHTML = paginationHtml;
            paginationContainer.style.display = 'block';
        }
        
        // Mostrar erro na seção CPF
        function showCpfError(message, debug = null) {
            const tableContainer = document.querySelector('#cpf .table-responsive');
            if (tableContainer) {
                let debugInfo = '';
                if (debug) {
                    debugInfo = `
                        <details class="mt-3">
                            <summary class="btn btn-sm btn-outline-secondary">Informações de Debug</summary>
                            <pre class="mt-2 p-2 bg-light text-start small">${JSON.stringify(debug, null, 2)}</pre>
                        </details>
                    `;
                }
                
                tableContainer.innerHTML = `
                    <div class="text-center p-4">
                        <i class="fas fa-exclamation-triangle text-danger" style="font-size: 3rem;"></i>
                        <h5 class="mt-3 text-danger">Erro ao carregar dados</h5>
                        <p class="text-muted">${escapeHtml(message)}</p>
                        <button class="btn btn-primary" onclick="loadCpfData()">
                            <i class="fas fa-redo"></i> Tentar novamente
                        </button>
                        ${debugInfo}
                    </div>
                `;
            }
        }
        
        // Função auxiliar para escapar HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Função auxiliar para formatar data
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('pt-BR') + ' ' + date.toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'});
        }
    </script>
</body>
</html>