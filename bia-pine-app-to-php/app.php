<?php
/**
 * SIMDA — Sistema de Monitoramento de Dados Abertos
 * 
 * Sistema completo para análise e monitoramento de portais de dados abertos
 * Controladoria-Geral do Estado de Goiás
 * 
 * Funcionalidades:
 * - BIA: Geração de dicionários de dados
 * - PINE: Análise de datasets e recursos
 * - CPF: Verificação de dados pessoais
 * - Painel: Métricas e estatísticas
 */

// Debug: verificar se o arquivo está sendo executado
error_log("DEBUG: SIMDA app.php iniciado - " . date('Y-m-d H:i:s'));
error_log("DEBUG: REQUEST_URI = " . ($_SERVER['REQUEST_URI'] ?? 'N/A'));
error_log("DEBUG: SCRIPT_NAME = " . ($_SERVER['SCRIPT_NAME'] ?? 'N/A'));

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

const DIAS_PARA_DESATUALIZADO = 40;
$portalUrl = $_SESSION['portalUrl'] ?? '';

$paginaAtual = isset($_GET['page']) && isset($_GET['tab']) && $_GET['tab'] === 'pine' ? (int)$_GET['page'] : 1;
$itensPorPagina = 10;

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
    
    if ($action === 'export_pine_excel') {
        try {
            // Obter os filtros da sessão ou de POST/GET, se aplicável
            $organization = $_SESSION['pine_filters']['organization'] ?? $_POST['organization'] ?? '';
            $status = $_SESSION['pine_filters']['status'] ?? $_POST['status'] ?? '';
            $search = $_SESSION['pine_filters']['search'] ?? $_POST['search'] ?? '';
            
            // Usar a função que aceita filtros
            $exportData = $pine->getDatasetsPaginadosComFiltros(
                $pdo,
                $portalUrl,
                1,
                999999, // um número grande para pegar todos os registros
                $organization,
                $status,
                $search
            ); 
            
            if ($exportData && !empty($exportData['datasets'])) {
                // Usar PhpSpreadsheet para gerar Excel
                require_once __DIR__ . '/vendor/autoload.php';
                
                $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();
                
                // Cabeçalhos relevantes
                $headers = ['Nome do Dataset', 'Órgão', 'Status', 'Última Atualização', 'Dias Desatualizado', 'Quantidade Recursos', 'Link'];
                $sheet->fromArray($headers, null, 'A1');
                
                // Dados
                $row = 2;
                foreach ($exportData['datasets'] as $dataset) {
                    $sheet->setCellValue('A' . $row, $dataset['name']);
                    $sheet->setCellValue('B' . $row, $dataset['organization']);
                    $sheet->setCellValue('C' . $row, $dataset['status']);
                    $sheet->setCellValue('D' . $row, $dataset['last_updated'] ? date('d/m/Y H:i', strtotime($dataset['last_updated'])) : 'N/A');
                    $sheet->setCellValue('E' . $row, $dataset['days_since_update'] === PHP_INT_MAX ? 'N/A' : $dataset['days_since_update']);
                    $sheet->setCellValue('F' . $row, $dataset['resources_count']);
                    $sheet->setCellValue('G' . $row, $dataset['url']);
                    $row++;
                }
                
                // Configurar cabeçalhos para download
                $filename = 'analise_pine_' . date('Y-m-d_H-i-s') . '.xlsx';
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment;filename="' . $filename . '"');
                header('Cache-Control: max-age=0');
                
                $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
                $writer->save('php://output');
                exit;
            }
        } catch (Exception $e) {
            $_SESSION['message'] = 'Erro ao exportar dados PINE: ' . $e->getMessage();
            $_SESSION['messageType'] = 'error';
            header("Location: " . $_SERVER['PHP_SELF'] . "?tab=pine");
            exit;
        }
    }
    
    if ($action === 'export_cpf_excel') {
        // Buscar todos os dados do banco para exportação completa
        try {
            // Obter o órgão do filtro (assumindo que está sendo passado via POST/GET ou sessão)
            $orgaoFiltro = $_POST['orgao'] ?? $_SESSION['cpf_orgao_filter'] ?? '';
            $allCpfData = getCpfFindingsPaginadoComFiltro($pdo, 1, 999999, $orgaoFiltro);
            $allFindings = $allCpfData['findings'] ?? [];
            
            // Usar PhpSpreadsheet para gerar Excel
            require_once __DIR__ . '/vendor/autoload.php';
            
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            // Cabeçalhos
            $headers = ['Dataset', 'Órgão', 'Recurso', 'Formato', 'Quantidade CPFs', 'Data Verificação'];
            $sheet->fromArray($headers, null, 'A1');
            
            // Dados
            $row = 2;
            foreach ($allFindings as $finding) {
                $sheet->setCellValue('A' . $row, $finding['dataset_name']);
                $sheet->setCellValue('B' . $row, $finding['dataset_organization']);
                $sheet->setCellValue('C' . $row, $finding['resource_name']);
                $sheet->setCellValue('D' . $row, $finding['resource_format']);
                $sheet->setCellValue('E' . $row, $finding['cpf_count']);
                $sheet->setCellValue('F' . $row, $finding['last_checked']);
                $row++;
            }
            
            // Configurar cabeçalhos para download
            $filename = 'cpf_findings_' . date('Y-m-d_H-i-s') . '.xlsx';
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;
            
        } catch (Exception $e) {
            $_SESSION['message'] = 'Erro ao exportar dados: ' . $e->getMessage();
            $_SESSION['messageType'] = 'error';
            header("Location: " . $_SERVER['PHP_SELF'] . "?tab=cpf");
            exit;
        }
    }

    if ($action === 'export_cpf_recurso_excel') {
        try {
            $resourceId = $_POST['resource_id'] ?? '';
            
            error_log("DEBUG: Iniciando export_cpf_recurso_excel para resource_id: " . $resourceId);
            
            if (empty($resourceId)) {
                error_log("ERROR: ID do recurso não fornecido");
                throw new Exception('ID do recurso não fornecido');
            }
            
            // Verificar se PDO está disponível
            if (!$pdo) {
                error_log("ERROR: Conexão PDO não disponível");
                throw new Exception('Erro de conexão com banco de dados');
            }
            
            // Verificar se o recurso existe na tabela mpda_recursos_com_cpf
            $checkStmt = $pdo->prepare("SELECT COUNT(*) as count FROM mpda_recursos_com_cpf WHERE identificador_recurso = ?");
            $checkStmt->execute([$resourceId]);
            $checkResult = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            error_log("DEBUG: Recursos encontrados: " . $checkResult['count']);
            
            if ($checkResult['count'] == 0) {
                error_log("ERROR: Recurso não encontrado na base de dados");
                throw new Exception('Recurso não encontrado na base de dados');
            }
            
            // Buscar dados do recurso na tabela mpda_recursos_com_cpf
            $stmt = $pdo->prepare("
                SELECT 
                    identificador_recurso,
                    identificador_dataset,
                    orgao,
                    cpfs_encontrados,
                    quantidade_cpfs,
                    metadados_recurso,
                    data_verificacao
                FROM mpda_recursos_com_cpf
                WHERE identificador_recurso = ?
            ");
            
            $stmt->execute([$resourceId]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("DEBUG: Registros encontrados: " . count($results));
            
            if (empty($results)) {
                error_log("ERROR: Nenhum dado encontrado para este recurso");
                throw new Exception('Nenhum dado encontrado para este recurso');
            }
            
            error_log("DEBUG: Iniciando geração do Excel");
            
            // Verificar se PhpSpreadsheet está disponível
            if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
                error_log("ERROR: Vendor autoload não encontrado");
                throw new Exception('Biblioteca PhpSpreadsheet não encontrada');
            }
            
            require_once __DIR__ . '/vendor/autoload.php';
            
            // Função para validar CPF
            function validarCPF($cpf) {
                $cpf = preg_replace('/[^0-9]/', '', $cpf);
                
                if (strlen($cpf) != 11 || preg_match('/(\d)\1{10}/', $cpf)) {
                    return false;
                }
                
                for ($t = 9; $t < 11; $t++) {
                    for ($d = 0, $c = 0; $c < $t; $c++) {
                        $d += $cpf[$c] * (($t + 1) - $c);
                    }
                    $d = ((10 * $d) % 11) % 10;
                    if ($cpf[$c] != $d) {
                        return false;
                    }
                }
                return true;
            }
            
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            
            $sheet1 = $spreadsheet->getActiveSheet();
            $sheet1->setTitle('Informações Gerais');
            
            $resourceInfo = $results[0];
            
            // Decodificar metadados JSON
            $metadados = json_decode($resourceInfo['metadados_recurso'], true);
            $cpfsArray = json_decode($resourceInfo['cpfs_encontrados'], true);
            
            // Cabeçalho principal
            $sheet1->setCellValue('A1', 'RELATÓRIO DE CPFs DETECTADOS');
            $sheet1->getStyle('A1')->getFont()->setBold(true)->setSize(16);
            $sheet1->getStyle('A1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('4472C4');
            $sheet1->getStyle('A1')->getFont()->getColor()->setRGB('FFFFFF');
            $sheet1->mergeCells('A1:B1');
            
            // Data de geração
            $sheet1->setCellValue('A2', 'Gerado em: ' . date('d/m/Y H:i:s'));
            $sheet1->getStyle('A2')->getFont()->setItalic(true);
            $sheet1->mergeCells('A2:B2');
            
            // Informações do recurso
            $sheet1->setCellValue('A4', 'Dataset:');
            $sheet1->setCellValue('B4', $metadados['dataset_id'] ?? $resourceInfo['identificador_dataset']);
            $sheet1->setCellValue('A5', 'Órgão:');
            $sheet1->setCellValue('B5', $resourceInfo['orgao']);
            $sheet1->setCellValue('A6', 'Recurso:');
            $sheet1->setCellValue('B6', $metadados['resource_name'] ?? 'N/A');
            $sheet1->setCellValue('A7', 'Formato:');
            $sheet1->setCellValue('B7', $metadados['resource_format'] ?? 'N/A');
            $sheet1->setCellValue('A8', 'Total de CPFs:');
            $sheet1->setCellValue('B8', $resourceInfo['quantidade_cpfs']);
            $sheet1->setCellValue('A9', 'Data da Verificação:');
            $sheet1->setCellValue('B9', date('d/m/Y H:i:s', strtotime($resourceInfo['data_verificacao'])));
            $sheet1->setCellValue('A10', 'URL do Dataset:');
            $sheet1->setCellValue('B10', 'https://dadosabertos.go.gov.br/dataset/' . $resourceInfo['identificador_dataset']);
            $sheet1->setCellValue('A11', 'URL do Recurso:');
            $sheet1->setCellValue('B11', $metadados['resource_url'] ?? 'N/A');
            
            // Formatação das células de informações
            $sheet1->getStyle('A4:A11')->getFont()->setBold(true);
            $sheet1->getStyle('A4:B11')->getBorders()->getAllBorders()
                    ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
            
            // Ajustar largura das colunas
            $sheet1->getColumnDimension('A')->setWidth(20);
            $sheet1->getColumnDimension('B')->setWidth(50);
            
            $sheet2 = $spreadsheet->createSheet();
            $sheet2->setTitle('CPFs Detectados');
            
            // Cabeçalho da segunda aba
            $sheet2->setCellValue('A1', 'LISTA DE CPFs DETECTADOS');
            $sheet2->getStyle('A1')->getFont()->setBold(true)->setSize(14);
            $sheet2->getStyle('A1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('70AD47');
            $sheet2->getStyle('A1')->getFont()->getColor()->setRGB('FFFFFF');
            $sheet2->mergeCells('A1:D1');
            
            // Cabeçalhos das colunas
            $headers = ['#', 'CPF', 'CPF Formatado', 'Status'];
            $sheet2->fromArray($headers, null, 'A3');
            $sheet2->getStyle('A3:D3')->getFont()->setBold(true);
            $sheet2->getStyle('A3:D3')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('E2EFDA');
            
            $row = 4;
            if (is_array($cpfsArray) && !empty($cpfsArray)) {
                foreach ($cpfsArray as $index => $cpf) {
                    // Limpar CPF (remover caracteres especiais)
                    $cpfLimpo = preg_replace('/[^0-9]/', '', $cpf);
                    
                    // Formatar CPF (XXX.XXX.XXX-XX)
                    $cpfFormatado = '';
                    if (strlen($cpfLimpo) === 11) {
                        $cpfFormatado = substr($cpfLimpo, 0, 3) . '.' . 
                                       substr($cpfLimpo, 3, 3) . '.' . 
                                       substr($cpfLimpo, 6, 3) . '-' . 
                                       substr($cpfLimpo, 9, 2);
                    }
                    
                    // Validar CPF
                    $status = validarCPF($cpfLimpo) ? 'Válido' : 'Inválido';
                    $statusColor = validarCPF($cpfLimpo) ? '70AD47' : 'C5504B';
                    
                    $sheet2->setCellValue('A' . $row, $index + 1);
                    $sheet2->setCellValue('B' . $row, $cpfLimpo);
                    $sheet2->setCellValue('C' . $row, $cpfFormatado);
                    $sheet2->setCellValue('D' . $row, $status);
                    
                    // Colorir status
                    $sheet2->getStyle('D' . $row)->getFont()->getColor()->setRGB($statusColor);
                    $sheet2->getStyle('D' . $row)->getFont()->setBold(true);
                    
                    $row++;
                }
                
                // Adicionar bordas à tabela
                $sheet2->getStyle('A3:D' . ($row - 1))->getBorders()->getAllBorders()
                        ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
                
                // Resumo no final
                $totalCpfs = count($cpfsArray);
                $cpfsValidos = 0;
                foreach ($cpfsArray as $cpf) {
                    $cpfLimpo = preg_replace('/[^0-9]/', '', $cpf);
                    if (validarCPF($cpfLimpo)) {
                        $cpfsValidos++;
                    }
                }
                $cpfsInvalidos = $totalCpfs - $cpfsValidos;
                
                $row += 2;
                $sheet2->setCellValue('A' . $row, 'RESUMO:');
                $sheet2->getStyle('A' . $row)->getFont()->setBold(true);
                $row++;
                $sheet2->setCellValue('A' . $row, 'Total de CPFs:');
                $sheet2->setCellValue('B' . $row, $totalCpfs);
                $row++;
                $sheet2->setCellValue('A' . $row, 'CPFs Válidos:');
                $sheet2->setCellValue('B' . $row, $cpfsValidos);
                $sheet2->getStyle('B' . $row)->getFont()->getColor()->setRGB('70AD47');
                $row++;
                $sheet2->setCellValue('A' . $row, 'CPFs Inválidos:');
                $sheet2->setCellValue('B' . $row, $cpfsInvalidos);
                $sheet2->getStyle('B' . $row)->getFont()->getColor()->setRGB('C5504B');
                
            } else {
                $sheet2->setCellValue('A4', 'Nenhum CPF encontrado neste recurso.');
                $sheet2->getStyle('A4')->getFont()->setItalic(true);
            }
            
            // Ajustar largura das colunas
            $sheet2->getColumnDimension('A')->setWidth(8);
            $sheet2->getColumnDimension('B')->setWidth(15);
            $sheet2->getColumnDimension('C')->setWidth(18);
            $sheet2->getColumnDimension('D')->setWidth(12);
            
            $resourceName = $metadados['resource_name'] ?? $resourceInfo['identificador_recurso'];
            $orgaoLimpo = preg_replace('/[^a-zA-Z0-9]/', '_', $resourceInfo['orgao']);
            $resourceNameLimpo = preg_replace('/[^a-zA-Z0-9]/', '_', $resourceName);
            $filename = 'Relatorio_CPF_' . $orgaoLimpo . '_' . $resourceNameLimpo . '_' . date('Y-m-d_H-i-s') . '.xlsx';
            
            error_log("DEBUG: Enviando arquivo Excel: " . $filename);
            
            // Limpar qualquer output anterior
            if (ob_get_level()) {
                ob_clean();
            }
            
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            header('Pragma: public');
            header('Expires: 0');
            
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
            
            error_log("DEBUG: Arquivo Excel enviado com sucesso");
            exit;
            
        } catch (Exception $e) {
            error_log("ERROR: Erro ao gerar relatório CPF: " . $e->getMessage());
            error_log("ERROR: Stack trace: " . $e->getTraceAsString());
            
            // Limpar qualquer output anterior
            if (ob_get_level()) {
                ob_clean();
            }
            
            // Verificar se é uma requisição AJAX
            $isAjaxRequest = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                             strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') ||
                             (isset($_SERVER['HTTP_ACCEPT']) && 
                             strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
            
            if ($isAjaxRequest) {
                // Retornar erro em formato JSON para requisições AJAX
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Erro ao gerar relatório: ' . $e->getMessage(),
                    'type' => 'error'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            } else {
                // Para requisições normais, mostrar erro na página
                $_SESSION['message'] = 'Erro ao gerar relatório: ' . $e->getMessage();
                $_SESSION['messageType'] = 'error';
                header("Location: " . $_SERVER['PHP_SELF'] . "?tab=cpf");
                exit;
            }
        }
    }

    if ($action === 'export_dataset_excel') {
        try {
            $datasetId = $_POST['dataset_id'] ?? '';
            
            if (empty($datasetId)) {
                throw new Exception('ID do dataset não fornecido');
            }
            
            $datasetInfo = $pine->getDatasetInfo($portalUrl, $datasetId);
            
            if (!$datasetInfo) {
                throw new Exception('Dataset não encontrado');
            }
            
            require_once __DIR__ . '/vendor/autoload.php';
            
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Informações do Dataset');
            
            $sheet->setCellValue('A1', 'RELATÓRIO DO DATASET');
            $sheet->setCellValue('A3', 'Nome:');
            $sheet->setCellValue('B3', $datasetInfo['name'] ?? 'N/A');
            $sheet->setCellValue('A4', 'ID:');
            $sheet->setCellValue('B4', $datasetInfo['id'] ?? 'N/A');
            $sheet->setCellValue('A5', 'Organização:');
            $sheet->setCellValue('B5', $datasetInfo['organization']['title'] ?? 'N/A');
            $sheet->setCellValue('A6', 'Descrição:');
            $sheet->setCellValue('B6', $datasetInfo['notes'] ?? 'N/A');
            $sheet->setCellValue('A7', 'Data de Criação:');
            $sheet->setCellValue('B7', $datasetInfo['metadata_created'] ?? 'N/A');
            $sheet->setCellValue('A8', 'Última Modificação:');
            $sheet->setCellValue('B8', $datasetInfo['metadata_modified'] ?? 'N/A');
            $sheet->setCellValue('A9', 'URL:');
            $sheet->setCellValue('B9', $datasetInfo['url'] ?? 'N/A');
            
            if (!empty($datasetInfo['resources'])) {
                $sheet->setCellValue('A11', 'RECURSOS:');
                $headers = ['Nome', 'Formato', 'Tamanho', 'URL', 'Criado', 'Modificado'];
                $sheet->fromArray($headers, null, 'A12');
                
                $row = 13;
                foreach ($datasetInfo['resources'] as $resource) {
                    $sheet->setCellValue('A' . $row, $resource['name'] ?? 'N/A');
                    $sheet->setCellValue('B' . $row, $resource['format'] ?? 'N/A');
                    $sheet->setCellValue('C' . $row, $resource['size'] ?? 'N/A');
                    $sheet->setCellValue('D' . $row, $resource['url'] ?? 'N/A');
                    $sheet->setCellValue('E' . $row, $resource['created'] ?? 'N/A');
                    $sheet->setCellValue('F' . $row, $resource['last_modified'] ?? 'N/A');
                    $row++;
                }
            }
            
            $filename = 'relatorio_dataset_' . preg_replace('/[^a-zA-Z0-9]/', '_', $datasetInfo['name'] ?? 'dataset') . '_' . date('Y-m-d_H-i-s') . '.xlsx';
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;
            
        } catch (Exception $e) {
            $_SESSION['message'] = 'Erro ao gerar relatório do dataset: ' . $e->getMessage();
            $_SESSION['messageType'] = 'error';
            header("Location: " . $_SERVER['PHP_SELF'] . "?tab=pine");
            exit;
        }
    }
    
    if ($action === 'excluir_recurso') {
        $resourceId = $_POST['resource_id'] ?? '';
        
        if (empty($resourceId)) {
            if ($isAjax) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'ID do recurso não informado.',
                    'type' => 'error'
                ]);
                exit;
            } else {
                $_SESSION['message'] = 'ID do recurso não informado.';
                $_SESSION['messageType'] = 'error';
                header("Location: " . $_SERVER['PHP_SELF'] . "?tab=cpf");
                exit;
            }
        }
        
        try {
            $stmt = $pdo->prepare("DELETE FROM mpda_recursos_com_cpf WHERE identificador_recurso = ?");
            $result = $stmt->execute([$resourceId]);
            
            if ($result && $stmt->rowCount() > 0) {
                if ($isAjax) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Recurso excluído com sucesso!',
                        'type' => 'success'
                    ]);
                    exit;
                } else {
                    $_SESSION['message'] = 'Recurso excluído com sucesso!';
                    $_SESSION['messageType'] = 'success';
                }
            } else {
                if ($isAjax) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Recurso não encontrado ou já foi excluído.',
                        'type' => 'warning'
                    ]);
                    exit;
                } else {
                    $_SESSION['message'] = 'Recurso não encontrado ou já foi excluído.';
                    $_SESSION['messageType'] = 'warning';
                }
            }
        } catch (Exception $e) {
            error_log("Erro ao excluir recurso: " . $e->getMessage());
            if ($isAjax) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Erro ao excluir recurso: ' . $e->getMessage(),
                    'type' => 'error'
                ]);
                exit;
            } else {
                $_SESSION['message'] = 'Erro ao excluir recurso: ' . $e->getMessage();
                $_SESSION['messageType'] = 'error';
            }
        }
        
        if (!$isAjax) {
            header("Location: " . $_SERVER['PHP_SELF'] . "?tab=cpf");
            exit;
        }
    }
    
    if ($action === 'filtrar_cpf_orgao') {
        $orgao = $_POST['orgao'] ?? '';
        
        if (empty($orgao)) {
            if ($isAjax) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Órgão não informado.',
                    'type' => 'error'
                ]);
                exit;
            }
        }
        
        try {
            // Buscar todos os dados do órgão selecionado
            $filteredData = getCpfFindingsPaginadoComFiltro($pdo, 1, 999999, $orgao);
            
            if ($isAjax) {
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'findings' => $filteredData['findings'],
                        'total_resources' => $filteredData['total_resources'],
                        'total_pages' => 1,
                        'page' => 1,
                        'has_next' => false,
                        'has_prev' => false
                    ]
                ]);
                exit;
            }
        } catch (Exception $e) {
            error_log("Erro ao filtrar CPF por órgão: " . $e->getMessage());
            if ($isAjax) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Erro ao filtrar dados: ' . $e->getMessage(),
                    'type' => 'error'
                ]);
                exit;
            }
        }
    }
    
    if ($action === 'get_total_recursos') {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM mpda_recursos_com_cpf");
            $total = $stmt->fetchColumn() ?: 0;
            
            if ($isAjax) {
                echo json_encode([
                    'success' => true,
                    'total' => (int) $total
                ]);
                exit;
            }
        } catch (Exception $e) {
            error_log("Erro ao buscar total de recursos: " . $e->getMessage());
            if ($isAjax) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Erro ao buscar total de recursos'
                ]);
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="SIMDA - Sistema de Monitoramento de Dados Abertos. Análise e monitoramento de portais de dados abertos do Estado de Goiás.">
    <meta name="keywords" content="dados abertos, CKAN, monitoramento, análise, transparência, governo, Goiás">
    <meta name="author" content="Controladoria-Geral do Estado de Goiás">
    <title>SIMDA — Sistema de Monitoramento de Dados Abertos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSS Customizado -->
    <link href="public/css/main.css" rel="stylesheet">
    <link href="public/css/components.css" rel="stylesheet">
    <link href="public/css/pine.css" rel="stylesheet">
    <link href="public/css/cpf.css" rel="stylesheet">
    <link href="public/css/responsive.css" rel="stylesheet">
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
                                <h1 class="system-title">SIMDA</h1>
                                <p class="system-subtitle">Sistema de Monitoramento de Dados Abertos</p>
                            </div>
                        </div>
                    </div>
                    <div class="header-right">
                        <div class="government-logos">
                            <div class="gov-go-logo">
                                <img src="assets/img/CGE_Abreviada_negativo.png" alt="CGE e Estado de Goiás" class="logo-image">
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
                        <h2>Bem-vindo ao SIMDA!</h2>
                        <p>Sistema de Monitoramento de Dados Abertos - Monitore métricas, verifique e analise o desempenho do Portal de Dados Abertos do Estado de Goiás.</p>
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
                            <button class="nav-link <?= isset($_GET['tab']) && $_GET['tab'] === 'painel' ? 'active' : (!isset($_GET['tab']) ? 'active' : '') ?>" id="painel-tab" data-bs-toggle="tab" data-bs-target="#painel" type="button" role="tab">
                                <i class="fas fa-tachometer-alt icon"></i> 
                                <span class="tab-text">Painel</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= isset($_GET['tab']) && $_GET['tab'] === 'bia' ? 'active' : '' ?>" id="bia-tab" data-bs-toggle="tab" data-bs-target="#bia" type="button" role="tab">
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
                            <button class="nav-link <?= isset($_GET['tab']) && $_GET['tab'] === 'cpf' ? 'active' : '' ?>" id="cpf-tab" data-bs-toggle="tab" data-bs-target="#cpf" type="button" role="tab">
                                <i class="fas fa-shield-alt icon"></i> 
                                <span class="tab-text">CPF</span>
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="tab-content" id="mainTabsContent">
                    <!-- Painel Tab -->
                    <div class="tab-pane fade <?= isset($_GET['tab']) && $_GET['tab'] === 'painel' ? 'show active' : (!isset($_GET['tab']) ? 'show active' : '') ?>" id="painel" role="tabpanel">
                        <?php include __DIR__ . '/public/painel_content.php'; ?>
                    </div>

                    <!-- BIA Tab -->
                    <div class="tab-pane fade <?= isset($_GET['tab']) && $_GET['tab'] === 'bia' ? 'show active' : '' ?>" id="bia" role="tabpanel">
                        <?php include __DIR__ . '/public/bia_content.php'; ?>
                    </div>

                    <!-- PINE Tab -->
                    <div class="tab-pane fade <?= isset($_GET['tab']) && $_GET['tab'] === 'pine' ? 'show active' : '' ?>" id="pine" role="tabpanel">
                        <?php include __DIR__ . '/public/pine_content.php'; ?>
                    </div>

                    <!-- CPF Tab -->
                    <div class="tab-pane fade <?= isset($_GET['tab']) && $_GET['tab'] === 'cpf' ? 'show active' : '' ?>" id="cpf" role="tabpanel">
                        <?php include __DIR__ . '/public/cpf_content.php'; ?>
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
                                <img src="assets/img/CGE_Abreviada_negativo.png" alt="CGE e Estado de Goiás" class="logo-image">
                            </div>
                        </div>
                    </div>
                    <!-- <div class="footer-right">
                        <div class="footer-info">
                            <p><strong>SIMDA</strong> — Sistema de Monitoramento de Dados Abertos</p>
                            <p>Controladoria-Geral do Estado de Goiás</p>
                        </div>
                    </div> -->
                </div>
            </div>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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

        // Variável para controlar se os event listeners já foram configurados
        let pineEventListenersSetup = false;

        function setupPineEventListeners() {
            // Evitar configurar event listeners múltiplas vezes
            if (pineEventListenersSetup) {
                console.log('⚠️ Event listeners PINE já configurados, ignorando...');
                return;
            }

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

            // Marcar como configurado
            pineEventListenersSetup = true;
            console.log('✅ Event listeners PINE configurados com sucesso');
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

        // Variáveis de controle para evitar requisições duplicadas
        let isLoadingDatasets = false;
        let loadDatasetsTimeout = null;
        let currentAbortController = null;

        // Função para carregar datasets com filtros
        async function loadPineDatasets() {
            // Evitar múltiplas requisições simultâneas
            if (isLoadingDatasets) {
                console.log('⏳ Requisição já em andamento, ignorando...');
                return;
            }

            // Limpar timeout anterior se existir
            if (loadDatasetsTimeout) {
                clearTimeout(loadDatasetsTimeout);
            }

            // Debounce de 300ms para evitar chamadas muito rápidas
            return new Promise((resolve) => {
                loadDatasetsTimeout = setTimeout(async () => {
                    try {
                        isLoadingDatasets = true;
                        console.log('🚀 Iniciando carregamento de datasets...');

                        // Mostrar indicador de loading
                        showPineSection('pine-loading');

                        const portalUrl = currentPortalUrl || document.getElementById('portal_url').value;

                        const params = new URLSearchParams({
                            action: 'datasets',
                            portal_url: portalUrl,
                            page: currentFilters.page,
                            per_page: 10,
                            organization: currentFilters.organization,
                            status: currentFilters.status,
                            search: currentFilters.search
                        });

                        if (currentAbortController) {
                            currentAbortController.abort();
                        }

                        currentAbortController = new AbortController();

                        const baseUrl = window.location.origin + window.location.pathname.replace('app.php', '');
                        const response = await fetch(`${baseUrl}api/pine-data.php?${params}`, {
                            signal: currentAbortController.signal
                        });
                        const data = await response.json();

                        console.log('📋 Dados dos datasets recebidos:', data);

                        if (data.success) {
                            updatePineDatasetsTable(data.datasets);
                            updatePinePagination(data);
                            updateDatasetsTitle(data.total);
                            
                            setTimeout(() => {
                                showPineSection('pine-datasets');
                                hidePineLoading(); 
                            }, 100);
                        } else {
                            console.log('❌ Erro ao carregar datasets:', data.message);
                            showPineSection('pine-no-data');
                            hidePineLoading(); 
                        }

                        resolve(data);
                    } catch (error) {
                        if (error.name === 'AbortError') {
                            console.log('🚫 Requisição cancelada (nova requisição iniciada)');
                        } else {
                            console.error('Erro ao carregar datasets:', error);
                            showPineSection('pine-no-data');
                            hidePineLoading(); // Garantir que o loading seja escondido
                        }
                        resolve(null);
                    } finally {
                        isLoadingDatasets = false;
                        currentAbortController = null;
                        console.log('✅ Carregamento de datasets finalizado');
                    }
                }, 300);
            });
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
                        <div class="py-4">
                            <i class="fas fa-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
                            <h5 class="mt-3 text-muted">Nenhum dataset encontrado</h5>
                            <p class="text-muted">Tente ajustar os filtros ou execute uma nova análise.</p>
                        </div>
                    </td>
                `;
                tbody.appendChild(row);
                return;
            }

            datasets.forEach((dataset, index) => {
                const datasetName = dataset.name.length > 50 ? dataset.name.substring(0, 50) + '...' : dataset.name;
                const orgName = dataset.organization.length > 25 ? dataset.organization.substring(0, 25) + '...' : dataset.organization;
                const datasetId = dataset.dataset_id.substring(0, 8) + '...';
                
                const row = document.createElement('tr');
                row.className = 'dataset-row';
                row.innerHTML = `
                    <td class="compact-cell">
                        <div class="cell-content">
                            <strong class="dataset-title custom-tooltip" title="${escapeHtml(dataset.name)}" data-tooltip="${escapeHtml(dataset.name)}">
                                ${escapeHtml(datasetName)}
                            </strong>
                            <small class="text-muted d-block">ID: ${escapeHtml(datasetId)}</small>
                        </div>
                    </td>
                    <td class="compact-cell">
                        <span class="orgao-name custom-tooltip" title="${escapeHtml(dataset.organization)}" data-tooltip="${escapeHtml(dataset.organization)}">
                            <i class="fas fa-building me-1 text-primary"></i>
                            ${escapeHtml(orgName)}
                        </span>
                    </td>
                    <td class="compact-cell">
                        <div class="update-info">
                            <strong class="text-${dataset.status === 'Atualizado' ? 'success' : 'danger'}">
                                ${dataset.last_updated ? formatDateCompact(dataset.last_updated) : 'N/A'}
                            </strong>
                            <small class="text-muted d-block">
                                ${dataset.days_since_update !== 2147483647 ? 
                                    (dataset.days_since_update === 0 ? 'Hoje' : 
                                     dataset.days_since_update === 1 ? 'Ontem' : 
                                     dataset.days_since_update + ' dias') : 'Sem data'}
                            </small>
                        </div>
                    </td>
                    <td class="text-center">
                        <span class="badge status-badge ${dataset.status === 'Atualizado' ? 'bg-success' : 'bg-danger'}">
                            <i class="fas fa-${dataset.status === 'Atualizado' ? 'check' : 'exclamation-triangle'} me-1"></i>
                            ${dataset.status === 'Atualizado' ? 'OK' : 'Desatualizado'}
                        </span>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-secondary resource-count">${dataset.resources_count}</span>
                    </td>
                    <td class="text-center">
                        <a href="${escapeHtml(dataset.url)}" target="_blank" class="btn btn-outline-primary btn-sm" title="Ver Dataset">
                            <i class="fas fa-external-link-alt"></i>
                        </a>
                    </td>
                `;
                tbody.appendChild(row);
                
                // Adicionar animação de entrada
                setTimeout(() => {
                    row.classList.add('fade-in');
                }, index * 50);
            });
            
            // Dropdowns não são mais necessários na tabela Pine
        }

        // Funções removidas - não são mais necessárias para a tabela Pine simplificada

        // Variável para controlar se o event listener já foi adicionado
        let paginationListenerAdded = false;

        // Atualizar paginação
        function updatePinePagination(data) {
            const pagination = document.getElementById('pine-pagination');
            const totalPages = data.total_pages;
            const currentPage = data.page;

            if (totalPages <= 1) {
                pagination.innerHTML = '';
                return;
            }

            let paginationHtml = '<ul class="pagination justify-content-center flex-wrap">';
            
            // Botão anterior
            paginationHtml += `<li class="page-item ${currentPage <= 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${currentPage - 1}">
                    <i class="fas fa-chevron-left"></i> Anterior
                </a>
            </li>`;

            // Lógica de paginação inteligente
            const maxVisiblePages = 5; // Máximo de páginas visíveis por vez
            let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
            let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
            
            // Ajustar se não temos páginas suficientes no final
            if (endPage - startPage + 1 < maxVisiblePages) {
                startPage = Math.max(1, endPage - maxVisiblePages + 1);
            }

            // Primeira página (se não estiver no range)
            if (startPage > 1) {
                paginationHtml += `<li class="page-item">
                    <a class="page-link" href="#" data-page="1">1</a>
                </li>`;
                
                if (startPage > 2) {
                    paginationHtml += `<li class="page-item disabled">
                        <span class="page-link">...</span>
                    </li>`;
                }
            }

            // Páginas do range atual
            for (let i = startPage; i <= endPage; i++) {
                paginationHtml += `<li class="page-item ${i === currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                </li>`;
            }

            // Última página (se não estiver no range)
            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    paginationHtml += `<li class="page-item disabled">
                        <span class="page-link">...</span>
                    </li>`;
                }
                
                paginationHtml += `<li class="page-item ${totalPages === currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" data-page="${totalPages}">${totalPages}</a>
                </li>`;
            }

            // Botão próximo
            paginationHtml += `<li class="page-item ${currentPage >= totalPages ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${currentPage + 1}">
                    Próximo <i class="fas fa-chevron-right"></i>
                </a>
            </li>`;

            paginationHtml += '</ul>';
            pagination.innerHTML = paginationHtml;

            // Adicionar event listener apenas uma vez usando delegação de eventos
            if (!paginationListenerAdded) {
                pagination.addEventListener('click', handlePaginationClick);
                paginationListenerAdded = true;
                console.log('🔗 Event listener de paginação adicionado');
            }
        }

        // Função separada para lidar com cliques na paginação
        function handlePaginationClick(e) {
            e.preventDefault();
            const pageLink = e.target.closest('.page-link');
            
            if (pageLink && !pageLink.parentElement.classList.contains('disabled')) {
                const page = parseInt(pageLink.getAttribute('data-page'));
                
                if (page && page !== currentFilters.page) {
                    console.log(`📄 Navegando para página ${page}`);
                    currentFilters.page = page;
                    loadPineDatasets();
                }
            }
        }

        function hidePineLoading() {
            const loadingElement = document.getElementById('pine-loading');
            if (loadingElement) {
                loadingElement.style.display = 'none';
                loadingElement.classList.add('hidden');
                loadingElement.classList.remove('visible');
            }
        }

        function resetPineRequestControls() {
            isLoadingDatasets = false;
            paginationListenerAdded = false;
            pineEventListenersSetup = false;
            
            if (currentAbortController) {
                currentAbortController.abort();
                currentAbortController = null;
            }
            
            if (loadDatasetsTimeout) {
                clearTimeout(loadDatasetsTimeout);
                loadDatasetsTimeout = null;
            }
            
            hidePineLoading();
            
            console.log('🔄 Controles de requisição PINE resetados');
        }

        // Atualizar título da lista
        function updateDatasetsTitle(total) {
            document.getElementById('datasets-title').textContent = `Lista de Datasets (${total})`;
            
            // Atualizar informações de paginação
            const datasetsInfo = document.getElementById('datasets-info');
            if (datasetsInfo) {
                const totalPages = Math.ceil(total / 10);
                if (total > 10) {
                    datasetsInfo.innerHTML = `
                        <i class="fas fa-info-circle me-1"></i>
                        Exibindo 10 itens por página • ${totalPages} páginas no total
                    `;
                } else {
                    datasetsInfo.innerHTML = `
                        <i class="fas fa-info-circle me-1"></i>
                        Exibindo ${total} ${total === 1 ? 'item' : 'itens'}
                    `;
                }
            }
        }

        // Aplicar filtros
        function applyFilters() {
            currentFilters.search = document.getElementById('search-dataset').value;
            currentFilters.organization = document.getElementById('filter-organization').value;
            currentFilters.status = document.getElementById('filter-status').value;
            currentFilters.page = 1; // Reset para primeira página

            console.log('🔍 Aplicando filtros:', currentFilters);
            console.log('📊 Status de controles:', {
                isLoadingDatasets,
                paginationListenerAdded,
                pineEventListenersSetup,
                hasAbortController: !!currentAbortController
            });
            
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
            console.log('🎯 Mostrando seção:', sectionId);
            
            // Lista de todas as seções PINE
            const allSections = ['pine-dashboard', 'pine-filters', 'pine-datasets', 'pine-loading', 'pine-no-data'];
            
            // Esconder todas as seções primeiro
            allSections.forEach(id => {
                const element = document.getElementById(id);
                if (element) {
                    element.classList.remove('visible');
                    element.classList.add('hidden');
                    element.style.display = 'none';
                }
            });
            
            // Mostrar apenas a seção solicitada
            const targetElement = document.getElementById(sectionId);
            if (targetElement) {
                targetElement.classList.remove('hidden');
                targetElement.classList.add('visible');
                targetElement.style.display = 'block';
                console.log('✅ Seção exibida com sucesso:', sectionId);
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
            
            // Atualizar botão para mostrar que está processando
            const btnScanCkan = document.getElementById('btnScanCkan');
            if (btnScanCkan) {
                btnScanCkan.disabled = true;
                btnScanCkan.innerHTML = force 
                    ? '<i class="fas fa-spinner fa-spin me-2"></i>Reiniciando Análise...'
                    : '<i class="fas fa-spinner fa-spin me-2"></i>Iniciando Análise...';
            }
            
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
                    
                    // Restaurar botão
                    const btnScanCkan = document.getElementById('btnScanCkan');
                    if (btnScanCkan) {
                        btnScanCkan.disabled = false;
                        btnScanCkan.innerHTML = '<i class="fas fa-search me-2"></i>Executar Análise';
                    }
                }
            });
        }

        // Função para restaurar o estado do botão de análise
        function restoreScanButton() {
            const btnScanCkan = document.getElementById('btnScanCkan');
            if (btnScanCkan) {
                btnScanCkan.disabled = false;
                btnScanCkan.innerHTML = '<i class="fas fa-search me-2"></i>Executar Nova Análise';
            }
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
                        
                        // Restaurar botão
                        restoreScanButton();
                        
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
                        
                        // Restaurar botão
                        restoreScanButton();
                        
                        setTimeout(() => {
                            hideAsyncProgressModal();
                            showMessage('Erro na análise: ' + (data.message || 'Erro desconhecido'), 'error');
                        }, 2000);
                        
                    } else if (status === 'cancelled' || status === 'stopped') {
                        console.log('⚠️ Análise cancelada');
                        scanInProgress = false;
                        clearInterval(scanStatusInterval);
                        scanStatusInterval = null;
                        
                        // Restaurar botão
                        restoreScanButton();
                        
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
                    
                    // Verificar se o botão está desabilitado
                    if (btnScanCkan.disabled) {
                        console.log('⚠️ Botão está desabilitado - análise não pode ser executada agora');
                        return;
                    }
                    
                    // Desabilitar botão temporariamente para evitar cliques múltiplos
                    btnScanCkan.disabled = true;
                    const originalText = btnScanCkan.innerHTML;
                    btnScanCkan.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Verificando...';
                    
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
                            // Análise já em andamento - mostrar dialog de confirmação
                            btnScanCkan.disabled = false;
                            btnScanCkan.innerHTML = originalText;
                            
                            showConfirmDialog(
                                'Análise em Andamento',
                                'Uma análise já está sendo executada no momento. Deseja cancelar a análise anterior e iniciar uma nova?',
                                'Sim, iniciar nova análise',
                                'Cancelar',
                                () => {
                                    executeScanCkan(true); // Forçar nova análise
                                },
                                () => {
                                    // Usuário cancelou - não fazer nada
                                }
                            );
                        } else {
                            // Iniciar nova análise
                            executeScanCkan(false);
                        }
                    })
                    .catch(error => {
                        console.error('Erro ao verificar status:', error);
                        // Se houver erro ao verificar, restaurar botão e tentar iniciar
                        btnScanCkan.disabled = false;
                        btnScanCkan.innerHTML = originalText;
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
        
        // Mostrar loading com mensagem personalizada
        function showCpfLoadingWithMessage(message) {
            const cpfContent = document.querySelector('#cpf .table-responsive');
            if (cpfContent) {
                cpfContent.innerHTML = `
                    <div class="text-center p-5">
                        <div class="spinner-border text-primary mb-3" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                        <h5>${escapeHtml(message)}</h5>
                        <p class="text-muted">Aguarde enquanto processamos sua solicitação.</p>
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
                <table class="table table-hover cpf-table-compact">
                    <thead>
                        <tr>
                            <th style="width: 25%;">Dataset</th>
                            <th style="width: 15%;">Órgão</th>
                            <th style="width: 25%;">Recurso</th>
                            <th style="width: 8%;">Formato</th>
                            <th style="width: 8%;">CPFs</th>
                            <th style="width: 12%;">Verificado</th>
                            <th style="width: 7%;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            data.findings.forEach((finding, index) => {
                const datasetName = finding.dataset_name.length > 40 ? finding.dataset_name.substring(0, 40) + '...' : finding.dataset_name;
                const resourceName = finding.resource_name.length > 35 ? finding.resource_name.substring(0, 35) + '...' : finding.resource_name;
                const orgaoName = finding.dataset_organization.length > 15 ? finding.dataset_organization.substring(0, 15) + '...' : finding.dataset_organization;
                const datasetId = finding.dataset_id.substring(0, 8) + '...';
                
                tableHtml += `
                    <tr>
                        <td class="compact-cell">
                            <div class="cell-content">
                                <strong class="dataset-name" title="${escapeHtml(finding.dataset_name)}">
                                    ${escapeHtml(datasetName)}
                                </strong>
                                <small class="text-muted d-block">ID: ${escapeHtml(datasetId)}</small>
                            </div>
                        </td>
                        <td class="compact-cell">
                            <span class="badge bg-primary orgao-badge-compact" title="${escapeHtml(finding.dataset_organization)}">
                                <i class="fas fa-building me-1"></i>
                                ${escapeHtml(orgaoName)}
                            </span>
                        </td>
                        <td class="compact-cell">
                            <div class="cell-content">
                                <strong class="resource-name" title="${escapeHtml(finding.resource_name)}">
                                    ${escapeHtml(resourceName)}
                                </strong>
                                <div class="resource-actions mt-1">
                                    <a href="${escapeHtml(finding.resource_url)}" target="_blank" class="text-decoration-none">
                                        <small><i class="fas fa-external-link-alt"></i> Ver</small>
                                    </a>
                                </div>
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-secondary format-badge">${finding.resource_format.toUpperCase()}</span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-danger cpf-badge">${finding.cpf_count}</span>
                        </td>
                        <td class="compact-cell">
                            <small class="text-muted">${formatDateCompact(finding.last_checked)}</small>
                        </td>
                        <td class="text-center">
                            <div class="d-flex gap-1 justify-content-center">
                                <button class="btn btn-outline-info btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#collapse${index}" aria-expanded="false" 
                                        data-bs-toggle="tooltip" data-bs-placement="top" title="Clique para ver os CPFs detectados neste recurso">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <div class="table-dropdown">
                                    <button class="btn btn-outline-primary btn-sm" type="button" title="Ações">
                                        <i class="fas fa-cog"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <button class="dropdown-item" type="button" onclick="return gerarRelatorioCpf('${escapeHtml(finding.resource_id)}', '${escapeHtml(finding.resource_name)}', event);">
                                            <i class="fas fa-file-excel me-2 text-success"></i> Gerar Relatório
                                        </button>
                                        ${finding.dataset_url && finding.dataset_url !== '#' ? 
                                            `<hr class="dropdown-divider">
                                            <a class="dropdown-item" href="${escapeHtml(finding.dataset_url)}" target="_blank">
                                                <i class="fas fa-external-link-alt me-2 text-primary"></i> Ver Dataset
                                            </a>` : ''
                                        }
                                        <hr class="dropdown-divider">
                                        <button class="dropdown-item text-danger" type="button" onclick="excluirRecurso('${escapeHtml(finding.resource_id)}', '${escapeHtml(finding.resource_name)}'); return false;">
                                            <i class="fas fa-trash me-2"></i> Excluir
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="7" class="p-0">
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
            
            // Reconfigurar dropdowns após carregar novos dados
            setupTableDropdowns();
            
            // Atualizar contador se não estiver filtrado
            const contador = document.getElementById('contadorRecursos');
            if (contador && !contador.classList.contains('bg-info')) {
                contador.textContent = `${data.total_resources} recursos`;
                contador.classList.remove('bg-info');
                contador.classList.add('bg-secondary');
            }
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

        // Função auxiliar para formatar data compacta
        function formatDateCompact(dateString) {
            const date = new Date(dateString);
            const day = date.getDate().toString().padStart(2, '0');
            const month = (date.getMonth() + 1).toString().padStart(2, '0');
            const year = date.getFullYear().toString().substr(-2);
            const hours = date.getHours().toString().padStart(2, '0');
            const minutes = date.getMinutes().toString().padStart(2, '0');
            return `${day}/${month}/${year}<br>${hours}:${minutes}`;
        }
        
        // Variáveis globais para controle do filtro
        let filtroAtivo = false;
        let orgaoAtual = '';

        // Função para selecionar órgão no dropdown
        function selecionarOrgao(valor, texto, total = null) {
            orgaoAtual = valor;
            
            // Atualizar texto do botão
            const botaoTexto = document.getElementById('orgaoSelecionadoText');
            const contadorOrgao = document.getElementById('contadorOrgao');
            const botaoDropdown = document.getElementById('filtroOrgaoDropdown');
            const btnLimpar = document.getElementById('btnLimparFiltro');
            
            if (botaoTexto) {
                // Truncar o texto se for muito longo para o botão
                let textoTruncado = texto;
                if (texto.length > 30) {
                    textoTruncado = texto.substring(0, 30) + '...';
                }
                botaoTexto.textContent = textoTruncado;
                
                // Adicionar tooltip com o nome completo se foi truncado
                if (texto.length > 30) {
                    botaoDropdown.setAttribute('title', texto);
                } else {
                    botaoDropdown.removeAttribute('title');
                }
            }
            
            if (valor === '') {
                // Limpar filtro
                filtroAtivo = false;
                contadorOrgao.classList.add('d-none');
                botaoDropdown.classList.remove('active');
                btnLimpar.style.display = 'none';
                
                // Recarregar dados normais
                loadCpfData(1);
                
                // Resetar título e contador
                const titulo = document.querySelector('#cpf .card-title');
                if (titulo) {
                    // Buscar o total real de recursos
                    fetch(window.location.pathname, {
                        method: 'POST',
                        body: new URLSearchParams({
                            'action': 'get_total_recursos'
                        }),
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/x-www-form-urlencoded'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        const contadorRecursos = document.getElementById('contadorRecursos');
                        if (contadorRecursos && data.success) {
                            contadorRecursos.textContent = `${data.total} recursos`;
                            contadorRecursos.classList.remove('bg-info');
                            contadorRecursos.classList.add('bg-secondary');
                        }
                    })
                    .catch(error => {
                        console.error('Erro ao buscar total de recursos:', error);
                    });
                }
                
                // Mostrar paginação
                mostrarPaginacao(true);
            } else {
                // Aplicar filtro
                filtroAtivo = true;
                contadorOrgao.textContent = total || '...';
                contadorOrgao.classList.remove('d-none');
                botaoDropdown.classList.add('active');
                btnLimpar.style.display = 'inline-block';
                
                // Filtrar dados
                filtrarPorOrgao(valor, texto, total);
                
                // Ocultar paginação quando filtrado
                mostrarPaginacao(false);
            }
        }

        // Função para filtrar por órgão
        function filtrarPorOrgao(orgao, nomeOrgao, totalRecursos) {
            // Mostrar loading com mensagem específica
            showCpfLoadingWithMessage(`Filtrando dados por: ${nomeOrgao}`);
            
            // Fazer requisição AJAX para buscar dados filtrados
            const formData = new FormData();
            formData.append('action', 'filtrar_cpf_orgao');
            formData.append('orgao', orgao);
            
            fetch(window.location.pathname, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateCpfDisplay(data.data);
                    
                    // Atualizar contador no dropdown
                    const contadorOrgao = document.getElementById('contadorOrgao');
                    if (contadorOrgao) {
                        contadorOrgao.textContent = data.data.total_resources;
                    }
                    
                    // Atualizar contador principal
                    const contador = document.getElementById('contadorRecursos');
                    if (contador) {
                        contador.textContent = `${data.data.total_resources} recursos`;
                        contador.classList.remove('bg-secondary');
                        contador.classList.add('bg-info');
                    }
                    
                    // Mostrar mensagem de sucesso discreta
                    showToast(`Filtro aplicado: ${data.data.total_resources} recursos encontrados`, 'success');
                } else {
                    showCpfError(data.message);
                }
            })
            .catch(error => {
                console.error('Erro ao filtrar por órgão:', error);
                showCpfError('Erro ao filtrar dados. Tente novamente.');
            });
        }
        
        // Função para limpar filtro
        function limparFiltro() {
            selecionarOrgao('', 'Todos os órgãos');
        }

        // Função para mostrar/ocultar paginação
        function mostrarPaginacao(mostrar) {
            const paginacao = document.querySelector('#cpf nav[aria-label="Navegação da página de CPFs"]');
            if (paginacao) {
                paginacao.style.display = mostrar ? 'block' : 'none';
            }
        }

        // Função para mostrar toast (notificação discreta)
        function showToast(message, type = 'info') {
            // Remover toast anterior se existir
            const existingToast = document.getElementById('cpfToast');
            if (existingToast) {
                existingToast.remove();
            }

            const toastClass = type === 'success' ? 'bg-success' : 
                             type === 'warning' ? 'bg-warning' : 
                             type === 'error' ? 'bg-danger' : 'bg-info';

            const toast = document.createElement('div');
            toast.id = 'cpfToast';
            toast.className = `toast align-items-center text-white ${toastClass} border-0`;
            toast.setAttribute('role', 'alert');
            toast.setAttribute('aria-live', 'assertive');
            toast.setAttribute('aria-atomic', 'true');
            toast.style.position = 'fixed';
            toast.style.top = '20px';
            toast.style.right = '20px';
            toast.style.zIndex = '9999';
            
            const iconClass = type === 'success' ? 'check-circle' : 
                             type === 'warning' ? 'exclamation-triangle' : 
                             type === 'error' ? 'exclamation-circle' : 'info-circle';
            
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-${iconClass} me-2"></i>
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;

            document.body.appendChild(toast);
            
            // Delay maior para mensagens de sucesso e erro
            const delay = (type === 'success' || type === 'error') ? 5000 : 3000;
            const bsToast = new bootstrap.Toast(toast, { delay: delay });
            bsToast.show();
            
            // Remover do DOM após ser ocultado
            toast.addEventListener('hidden.bs.toast', () => {
                toast.remove();
            });
        }
        
        // Variáveis globais para controle da exclusão
        let currentDeleteResourceId = null;
        let currentDeleteButton = null;

        // Função auxiliar para controlar loading de botões
        function setButtonLoading(button, isLoading, textOrOriginal = null) {
            if (isLoading) {
                button.disabled = true;
                const loadingText = textOrOriginal || 'Processando...';
                button.innerHTML = `<i class="fas fa-spinner fa-spin me-1"></i> ${loadingText}`;
                button.setAttribute('data-original-text', button.innerHTML);
            } else {
                button.disabled = false;
                if (textOrOriginal) {
                    // textOrOriginal é o texto original para restaurar
                    button.innerHTML = textOrOriginal;
                } else {
                    // Tentar restaurar do atributo data ou limpar o loading
                    const originalText = button.getAttribute('data-original-text');
                    if (originalText) {
                        button.innerHTML = originalText;
                        button.removeAttribute('data-original-text');
                    } else {
                        // Fallback: remover apenas o spinner
                        button.innerHTML = button.innerHTML.replace(/<i class="fas fa-spinner fa-spin[^>]*><\/i>\s*[^<]*/, '');
                    }
                }
            }
        }

        // Função para gerar relatório de CPF específico
        function gerarRelatorioCpf(resourceId, resourceName, evt) {
            console.log('📊 Gerando relatório de CPF para recurso:', resourceId);
            
            // Usar o event passado como parâmetro ou o global
            const currentEvent = evt || event;
            
            if (!resourceId) {
                showToast('Erro: ID do recurso não encontrado', 'error');
                return false;
            }
            
            // Prevenir comportamento padrão
            if (currentEvent) {
                currentEvent.preventDefault();
                currentEvent.stopPropagation();
            }
            
            // Mostrar loading no botão
            const button = currentEvent ? currentEvent.target.closest('button') : 
                          document.querySelector(`button[onclick*="${resourceId}"]`);
            
            if (!button) {
                console.error('Botão não encontrado');
                showToast('Erro interno: botão não encontrado', 'error');
                return false;
            }
            
            const originalText = button.innerHTML;
            setButtonLoading(button, true, 'Gerando relatório...');
            
            // Mostrar feedback imediato
            showToast(`Iniciando geração do relatório para "${resourceName}"...`, 'info');
            
            // Adicionar classe visual ao botão para indicar processamento
            button.classList.add('btn-processing');
            
            // Usar fetch para requisição AJAX
            const formData = new FormData();
            formData.append('action', 'export_cpf_recurso_excel');
            formData.append('resource_id', resourceId);
            
            fetch(window.location.pathname, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                console.log('📥 Resposta recebida:', response.status, response.statusText);
                
                if (!response.ok) {
                    throw new Error(`Erro HTTP: ${response.status} - ${response.statusText}`);
                }
                
                // Verificar se é um arquivo Excel
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')) {
                    // É um arquivo Excel, fazer download
                    return response.blob().then(blob => {
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.style.display = 'none';
                        a.href = url;
                        
                        // Tentar extrair nome do arquivo do header
                        const contentDisposition = response.headers.get('content-disposition');
                        let filename = 'relatorio_cpf.xlsx';
                        if (contentDisposition) {
                            const filenameMatch = contentDisposition.match(/filename="(.+)"/);
                            if (filenameMatch) {
                                filename = filenameMatch[1];
                            }
                        }
                        
                        a.download = filename;
                        document.body.appendChild(a);
                        a.click();
                        window.URL.revokeObjectURL(url);
                        document.body.removeChild(a);
                        
                        showToast('Relatório gerado e baixado com sucesso!', 'success');
                        return { success: true };
                    });
                } else {
                    // Pode ser uma resposta JSON com erro
                    return response.text().then(text => {
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            throw new Error('Resposta inválida do servidor: ' + text.substring(0, 100));
                        }
                    });
                }
            })
            .then(result => {
                if (result && !result.success) {
                    throw new Error(result.message || 'Erro desconhecido ao gerar relatório');
                }
                console.log('✅ Relatório processado com sucesso');
            })
            .catch(error => {
                console.error('❌ Erro ao gerar relatório:', error);
                let errorMessage = 'Erro ao gerar relatório';
                
                if (error.message) {
                    errorMessage += ': ' + error.message;
                } else if (error.toString) {
                    errorMessage += ': ' + error.toString();
                }
                
                showToast(errorMessage, 'error');
            })
            .finally(() => {
                // Restaurar botão
                setButtonLoading(button, false, originalText);
                button.classList.remove('btn-processing');
                console.log('🔄 Botão restaurado');
            });
            
            return false;
        }

        // Função para gerar relatório de dataset
        function gerarRelatorioDataset(datasetId, datasetName) {
            console.log('📊 Gerando relatório de dataset:', datasetId);
            
            if (!datasetId) {
                showToast('Erro: ID do dataset não encontrado', 'error');
                return false;
            }
            
            // Prevenir comportamento padrão
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            // Mostrar loading no botão
            const button = event ? event.target.closest('button') : null;
            if (!button) {
                console.error('Botão não encontrado');
                return false;
            }
            
            const originalText = button.innerHTML;
            setButtonLoading(button, true);
            
            try {
                // Mostrar feedback imediato
                showToast(`Gerando relatório para "${datasetName}"...`, 'info');
                
                // Usar fetch para fazer a requisição
                const formData = new FormData();
                formData.append('action', 'export_dataset_excel');
                formData.append('dataset_id', datasetId);
                
                console.log('📋 Enviando requisição com dataset_id:', datasetId);
                
                fetch(window.location.pathname, {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    console.log('📋 Resposta recebida:', response.status);
                    
                    if (!response.ok) {
                        throw new Error(`Erro HTTP: ${response.status}`);
                    }
                    
                    // Verificar se é um arquivo Excel
                    const contentType = response.headers.get('content-type');
                    if (contentType && contentType.includes('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')) {
                        // É um arquivo Excel, fazer download
                        return response.blob().then(blob => {
                            const url = window.URL.createObjectURL(blob);
                            const a = document.createElement('a');
                            a.style.display = 'none';
                            a.href = url;
                            
                            // Tentar obter o nome do arquivo do header
                            const contentDisposition = response.headers.get('content-disposition');
                            let filename = 'relatorio_dataset.xlsx';
                            if (contentDisposition) {
                                const filenameMatch = contentDisposition.match(/filename="(.+)"/);
                                if (filenameMatch) {
                                    filename = filenameMatch[1];
                                }
                            }
                            
                            a.download = filename;
                            document.body.appendChild(a);
                            a.click();
                            window.URL.revokeObjectURL(url);
                            document.body.removeChild(a);
                            
                            showToast('Relatório gerado com sucesso!', 'success');
                        });
                    } else {
                        // Não é um arquivo Excel, pode ser uma mensagem de erro
                        return response.text().then(text => {
                            console.error('Resposta inesperada:', text);
                            throw new Error('Formato de resposta inesperado');
                        });
                    }
                })
                .catch(error => {
                    console.error('Erro ao gerar relatório:', error);
                    showToast('Erro ao gerar relatório: ' + error.message, 'error');
                })
                .finally(() => {
                    // Restaurar botão
                    setTimeout(() => {
                        setButtonLoading(button, false, originalText);
                    }, 1000);
                });
                
            } catch (error) {
                console.error('Erro ao gerar relatório:', error);
                showToast('Erro ao gerar relatório: ' + error.message, 'error');
                setButtonLoading(button, false, originalText);
            }
            
            return false;
        }

        // Função para excluir recurso
        function excluirRecurso(resourceId, resourceName) {
            // Armazenar dados para uso no modal
            currentDeleteResourceId = resourceId;
            currentDeleteButton = event.target.closest('button');
            
            // Atualizar conteúdo do modal
            document.getElementById('deleteResourceName').innerHTML = `
                <strong>Recurso:</strong> ${escapeHtml(resourceName)}<br>
                <small class="text-muted">ID: ${escapeHtml(resourceId)}</small>
            `;
            
            // Mostrar modal
            const modal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
            modal.show();
        }

        // Função para configurar atalhos de teclado
        function setupKeyboardShortcuts() {
            document.addEventListener('keydown', function(e) {
                // Ctrl/Cmd + K para focar na busca
                if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                    e.preventDefault();
                    const searchInput = document.querySelector('input[name="url"]');
                    if (searchInput) {
                        searchInput.focus();
                        searchInput.select();
                    }
                }
                
                // Esc para limpar filtros
                if (e.key === 'Escape') {
                    const limparFiltrosBtn = document.getElementById('limparFiltros');
                    if (limparFiltrosBtn && !limparFiltrosBtn.disabled) {
                        limparFiltrosBtn.click();
                    }
                }
            });
        }

        // Função para adicionar tooltips dinâmicos
        function setupDynamicTooltips() {
            // Inicializar tooltips do Bootstrap
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }

        // Função para monitorar performance
        function setupPerformanceMonitoring() {
            let requestCount = 0;
            const originalFetch = window.fetch;
            
            window.fetch = function(...args) {
                requestCount++;
                const startTime = performance.now();
                
                return originalFetch.apply(this, args).then(response => {
                    const endTime = performance.now();
                    const duration = endTime - startTime;
                    
                    console.log(`📊 Requisição ${requestCount}: ${duration.toFixed(2)}ms`);
                    
                    // Mostrar aviso se requisição demorar muito
                    if (duration > 5000) {
                        showToast('Conexão lenta detectada', 'warning');
                    }
                    
                    return response;
                });
            };
        }

        // Função simples para configurar dropdowns
        function setupTableDropdowns() {
            // Configurar dropdowns das tabelas
            document.querySelectorAll('.table-dropdown').forEach(dropdown => {
                const button = dropdown.querySelector('button');
                const menu = dropdown.querySelector('.dropdown-menu');
                
                if (button && menu) {
                    button.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        // Fechar outros dropdowns
                        document.querySelectorAll('.table-dropdown.show').forEach(other => {
                            if (other !== dropdown) {
                                other.classList.remove('show');
                            }
                        });
                        
                        // Toggle atual
                        dropdown.classList.toggle('show');
                    });
                }
            });
            
            // Fechar ao clicar fora
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.table-dropdown')) {
                    document.querySelectorAll('.table-dropdown.show').forEach(dropdown => {
                        dropdown.classList.remove('show');
                    });
                }
            });
        }

        // Inicialização completa do sistema
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 Sistema de monitoramento inicializando...');
            
            // Configurar atalhos de teclado
            setupKeyboardShortcuts();
            
            // Configurar tooltips dinâmicos
            setupDynamicTooltips();
            
            // Configurar monitoramento de performance
            setupPerformanceMonitoring();
            
            // Configurar dropdowns
            setupTableDropdowns();
        });

        // Event listener para o botão de confirmação no modal
        document.addEventListener('DOMContentLoaded', function() {
            const confirmBtn = document.getElementById('confirmDeleteBtn');
            if (confirmBtn) {
                confirmBtn.addEventListener('click', function() {
                    if (!currentDeleteResourceId || !currentDeleteButton) return;
                    
                    // Fechar modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('confirmDeleteModal'));
                    modal.hide();
                    
                    // Mostrar loading no botão
                    const originalText = currentDeleteButton.innerHTML;
                    currentDeleteButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Excluindo...';
                    currentDeleteButton.disabled = true;
                    
                    // Fazer requisição AJAX
                    const formData = new FormData();
                    formData.append('action', 'excluir_recurso');
                    formData.append('resource_id', currentDeleteResourceId);
                    
                    fetch(window.location.pathname, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Remover a linha da tabela com animação
                            const row = currentDeleteButton.closest('tr');
                            const detailsRow = row.nextElementSibling;
                            
                            // Animação de fade out
                            row.style.transition = 'all 0.3s ease';
                            row.style.opacity = '0';
                            row.style.transform = 'translateX(-20px)';
                            
                            setTimeout(() => {
                                row.remove();
                                if (detailsRow && detailsRow.querySelector('.collapse')) {
                                    detailsRow.remove();
                                }
                                
                                // Mostrar mensagem de sucesso
                                showAlert(data.message, data.type);
                                
                                // Recarregar dados se a tabela ficou vazia
                                const remainingRows = document.querySelectorAll('#cpf .table tbody tr:not([style*="display: none"])');
                                if (remainingRows.length === 0) {
                                    loadCpfData();
                                }
                            }, 300);
                        } else {
                            showAlert(data.message, data.type);
                            currentDeleteButton.innerHTML = originalText;
                            currentDeleteButton.disabled = false;
                        }
                        
                        // Limpar variáveis
                        currentDeleteResourceId = null;
                        currentDeleteButton = null;
                    })
                    .catch(error => {
                        console.error('Erro ao excluir recurso:', error);
                        showAlert('Erro ao excluir recurso. Tente novamente.', 'error');
                        currentDeleteButton.innerHTML = originalText;
                        currentDeleteButton.disabled = false;
                        
                        // Limpar variáveis
                        currentDeleteResourceId = null;
                        currentDeleteButton = null;
                    });
                });
            }
        });
        
        // Função para mostrar alertas
        function showAlert(message, type) {
            const alertContainer = document.querySelector('.alert-container') || document.querySelector('#cpf');
            const alertClass = type === 'success' ? 'alert-success' : 
                             type === 'warning' ? 'alert-warning' : 'alert-danger';
            
            const alert = document.createElement('div');
            alert.className = `alert ${alertClass} alert-dismissible fade show`;
            alert.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            alertContainer.insertBefore(alert, alertContainer.firstChild);
            
            // Auto-remover após 5 segundos
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 5000);
        }
    </script>

    <!-- Modal de Confirmação de Exclusão -->
    <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="confirmDeleteModalLabel">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Confirmar Exclusão
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex align-items-start">
                        <div class="flex-shrink-0 me-3">
                            <div class="bg-danger bg-opacity-10 rounded-circle p-3">
                                <i class="fas fa-trash-alt text-danger" style="font-size: 1.5rem;"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-2">Tem certeza que deseja excluir este recurso?</h6>
                            <p class="mb-2" id="deleteResourceName"></p>
                            <div class="alert alert-warning d-flex align-items-center mb-0">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <small>Esta ação não pode ser desfeita. Todos os dados relacionados a este recurso serão removidos permanentemente.</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>
                        Cancelar
                    </button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                        <i class="fas fa-trash me-1"></i>
                        Excluir Recurso
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Script Anonimizador de CPF -->
    <script>
        let currentOutputFile = null;
        
        // Drag and Drop
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('fileInput');
        
        if (uploadArea) {
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                uploadArea.addEventListener(eventName, preventDefaults, false);
            });
            
            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            ['dragenter', 'dragover'].forEach(eventName => {
                uploadArea.addEventListener(eventName, () => {
                    uploadArea.classList.add('border-primary', 'bg-light');
                }, false);
            });
            
            ['dragleave', 'drop'].forEach(eventName => {
                uploadArea.addEventListener(eventName, () => {
                    uploadArea.classList.remove('border-primary', 'bg-light');
                }, false);
            });
            
            uploadArea.addEventListener('drop', handleDrop, false);
        }
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            if (files.length > 0) {
                handleFile(files[0]);
            }
        }
        
        if (fileInput) {
            fileInput.addEventListener('change', function() {
                if (this.files.length > 0) {
                    handleFile(this.files[0]);
                }
            });
        }
        
        function handleFile(file) {
            // Validar tipo de arquivo
            const allowedTypes = ['text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
            const allowedExtensions = ['.csv', '.xls', '.xlsx'];
            
            const fileExtension = '.' + file.name.split('.').pop().toLowerCase();
            
            if (!allowedTypes.includes(file.type) && !allowedExtensions.includes(fileExtension)) {
                showError('Formato de arquivo não suportado. Use CSV, Excel ou PDF.');
                return;
            }
            
            // Validar tamanho
            if (file.size > 10485760) { // 10MB
                showError('Arquivo muito grande. Tamanho máximo: 10MB');
                return;
            }
            
            // Mostrar progresso
            document.getElementById('uploadArea').classList.add('d-none');
            document.getElementById('progressArea').classList.remove('d-none');
            document.getElementById('fileName').textContent = file.name;
            
            // Upload
            uploadFile(file);
        }
        
        function uploadFile(file) {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('action', 'upload');
            
            const xhr = new XMLHttpRequest();
            
            // Usar baseUrl como nas outras APIs
            const baseUrl = window.location.origin + window.location.pathname.replace('app.php', '');
            const apiUrl = `${baseUrl}api/cpf_anonymizer.php`;
            
            console.log('📤 Enviando para:', apiUrl);
            console.log('📁 Arquivo:', file.name, '(' + file.size + ' bytes)');
            
            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    const percentComplete = Math.round((e.loaded / e.total) * 100);
                    updateProgress(percentComplete, 'Enviando arquivo...');
                }
            });
            
            xhr.addEventListener('load', function() {
                console.log('📥 Resposta recebida. Status:', xhr.status);
                console.log('📄 Response:', xhr.responseText);
                
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            showResults(response.data);
                        } else {
                            showError(response.message || 'Erro ao processar arquivo');
                        }
                    } catch (e) {
                        console.error('❌ Erro ao parsear JSON:', e);
                        showError('Erro ao processar resposta do servidor: ' + e.message);
                    }
                } else {
                    console.error('❌ Erro HTTP:', xhr.status);
                    showError('Erro no servidor. Código: ' + xhr.status);
                }
            });
            
            xhr.addEventListener('error', function() {
                console.error('❌ Erro de conexão');
                showError('Erro de conexão com o servidor');
            });
            
            xhr.open('POST', apiUrl);
            xhr.send(formData);
            
            updateProgress(0, 'Processando arquivo...');
        }
        
        function updateProgress(percent, text) {
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('progressText');
            
            if (progressBar) {
                progressBar.style.width = percent + '%';
                progressBar.textContent = percent + '%';
            }
            
            if (progressText) {
                progressText.textContent = text;
            }
        }
        
        function showResults(data) {
            document.getElementById('progressArea').classList.add('d-none');
            document.getElementById('resultsArea').classList.remove('d-none');
            
            document.getElementById('resultOriginalFile').textContent = data.arquivo_original;
            document.getElementById('resultCpfCount').textContent = data.total_cpfs + ' CPF(s)';
            
            currentOutputFile = data.arquivo_saida;
            
            // Mostrar lista de CPFs se houver
            if (data.cpfs_encontrados && data.cpfs_encontrados.length > 0) {
                const cpfListContainer = document.getElementById('cpfListContainer');
                const cpfList = document.getElementById('cpfList');
                
                cpfListContainer.classList.remove('d-none');
                cpfList.innerHTML = data.cpfs_encontrados.map(cpf => 
                    `<span class="badge bg-secondary me-2 mb-2">${cpf}</span>`
                ).join('');
            }
            
            // Configurar botão de download
            const btnDownload = document.getElementById('btnDownload');
            if (btnDownload) {
                btnDownload.onclick = function() {
                    const baseUrl = window.location.origin + window.location.pathname.replace('app.php', '');
                    const downloadUrl = `${baseUrl}api/cpf_anonymizer.php?action=download&filename=` + encodeURIComponent(currentOutputFile);
                    console.log('📥 Baixando de:', downloadUrl);
                    window.location.href = downloadUrl;
                };
            }
        }
        
        function showError(message) {
            document.getElementById('uploadArea').classList.add('d-none');
            document.getElementById('progressArea').classList.add('d-none');
            document.getElementById('errorArea').classList.remove('d-none');
            document.getElementById('errorMessage').textContent = message;
        }
        
        function resetAnonymizer() {
            document.getElementById('uploadArea').classList.remove('d-none');
            document.getElementById('progressArea').classList.add('d-none');
            document.getElementById('resultsArea').classList.add('d-none');
            document.getElementById('errorArea').classList.add('d-none');
            document.getElementById('cpfListContainer').classList.add('d-none');
            
            if (fileInput) {
                fileInput.value = '';
            }
            
            currentOutputFile = null;
        }
        
        // Reset ao fechar modal
        const modalAnonymizer = document.getElementById('modalAnonymizer');
        if (modalAnonymizer) {
            modalAnonymizer.addEventListener('hidden.bs.modal', function() {
                resetAnonymizer();
            });
        }
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const orgaoBadges = document.querySelectorAll('.orgao-badge-compact[title]');
            orgaoBadges.forEach(function(badge) {
                new bootstrap.Tooltip(badge, {
                    placement: 'top',
                    trigger: 'hover focus'
                });
            });
            
            const filtroButton = document.getElementById('filtroOrgaoDropdown');
            if (filtroButton && filtroButton.hasAttribute('title')) {
                new bootstrap.Tooltip(filtroButton, {
                    placement: 'bottom',
                    trigger: 'hover focus'
                });
            }
            
            const dropdownItems = document.querySelectorAll('.dropdown-item[title]');
            dropdownItems.forEach(function(item) {
                new bootstrap.Tooltip(item, {
                    placement: 'right',
                    trigger: 'hover focus'
                });
            });
        });
        
        function reinitializeTooltips() {
            const existingTooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
            existingTooltips.forEach(function(element) {
                const tooltip = bootstrap.Tooltip.getInstance(element);
                if (tooltip) {
                    tooltip.dispose();
                }
            });
            
            const elementsWithTitle = document.querySelectorAll('[title]');
            elementsWithTitle.forEach(function(element) {
                if (element.classList.contains('orgao-badge-compact') || 
                    element.id === 'filtroOrgaoDropdown' || 
                    element.classList.contains('dropdown-item')) {
                    new bootstrap.Tooltip(element, {
                        placement: element.classList.contains('dropdown-item') ? 'right' : 'top',
                        trigger: 'hover focus'
                    });
                }
            });
        }
    </script>
</body>
</html>
