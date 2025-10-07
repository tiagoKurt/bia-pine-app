<?php

// Start output buffering to prevent any unwanted output
ob_start();

require_once __DIR__ . '/../../config.php';

use App\Cpf\CpfController;

// Clean any output that might have been generated during config loading
$unwantedOutput = ob_get_contents();
if (!empty($unwantedOutput)) {
    error_log("CPF API: Cleaned unwanted output: " . substr($unwantedOutput, 0, 100));
}
ob_clean();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../src/functions.php';

try {
    $action = $_GET['action'] ?? 'list';
    $page = (int)($_GET['page'] ?? 1);
    $perPage = min((int)($_GET['per_page'] ?? 10), 50);
    
    $pdo = conectarBanco();
    
    // Tentar usar o novo controller, com fallback para funções antigas
    $useNewController = false;
    $cpfController = null;
    
    // Check if class exists and can be instantiated
    if (class_exists('App\Cpf\CpfController')) {
        try {
            $cpfController = new CpfController($pdo);
            $useNewController = true;
            error_log("CPF API: Using CpfController");
        } catch (Exception $e) {
            error_log("CPF API: Error instantiating CpfController: " . $e->getMessage());
            $useNewController = false;
            $cpfController = null;
        }
    } else {
        error_log("CPF API: CpfController class not found, using fallback");
    }
    
    switch ($action) {
        case 'list':
            // Build filters
            $filtros = [];
            if (!empty($_GET['orgao'])) {
                $filtros['orgao'] = $_GET['orgao'];
            }
            if (!empty($_GET['dataset'])) {
                $filtros['dataset'] = $_GET['dataset'];
            }
            if (!empty($_GET['data_inicio'])) {
                $filtros['data_inicio'] = $_GET['data_inicio'];
            }
            if (!empty($_GET['data_fim'])) {
                $filtros['data_fim'] = $_GET['data_fim'];
            }
            
            if ($useNewController && $cpfController) {
                // Use new controller
                error_log("CPF API: Using CpfController for list action");
                
                try {
                    $result = $cpfController->getCpfFindingsPaginado($page, $perPage, $filtros);
                    
                    // Ensure clean output before JSON
                    if (ob_get_level()) {
                        ob_clean();
                    }
                    
                    echo json_encode($result, JSON_UNESCAPED_UNICODE);
                    
                } catch (Exception $e) {
                    error_log("CPF API: Error in CpfController: " . $e->getMessage());
                    
                    // Fallback to old function on error
                    $cpfData = getCpfFindingsPaginadoFromNewTable($pdo, $page, $perPage);
                    
                    $result = [
                        'success' => true,
                        'findings' => $cpfData['findings'] ?? [],
                        'total' => $cpfData['total_resources'] ?? 0,
                        'page' => $page,
                        'per_page' => $perPage,
                        'total_pages' => $cpfData['total_paginas'] ?? 1,
                        'has_next' => $page < ($cpfData['total_paginas'] ?? 1),
                        'has_prev' => $page > 1
                    ];
                    
                    if (ob_get_level()) {
                        ob_clean();
                    }
                    
                    echo json_encode($result, JSON_UNESCAPED_UNICODE);
                }
                
            } else {
                // Use fallback function
                error_log("CPF API: Using fallback function for list action");
                
                $cpfData = getCpfFindingsPaginadoFromNewTable($pdo, $page, $perPage);
                
                $result = [
                    'success' => true,
                    'findings' => $cpfData['findings'] ?? [],
                    'total' => $cpfData['total_resources'] ?? 0,
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => $cpfData['total_paginas'] ?? 1,
                    'has_next' => $page < ($cpfData['total_paginas'] ?? 1),
                    'has_prev' => $page > 1
                ];
                
                // Ensure clean output before JSON
                if (ob_get_level()) {
                    ob_clean();
                }
                
                echo json_encode($result, JSON_UNESCAPED_UNICODE);
            }
            break;
            
        case 'stats':
            if ($useNewController && $cpfController) {
                $result = $cpfController->getEstatisticas();
                $lastScanInfo = $cpfController->getLastScanInfo();
                
                if ($result['success']) {
                    $result['last_scan'] = $lastScanInfo;
                }
                
                echo json_encode($result, JSON_UNESCAPED_UNICODE);
            } else {
                // Fallback para funções antigas
                $estatisticas = obterEstatisticasVerificacoesFromNewTable($pdo);
                $lastScanInfo = getLastCpfScanInfoFromNewTable($pdo);
                
                echo json_encode([
                    'success' => true,
                    'stats' => $estatisticas,
                    'last_scan' => $lastScanInfo
                ], JSON_UNESCAPED_UNICODE);
            }
            break;
            
        case 'details':
            if ($useNewController && $cpfController) {
                $id = (int)($_GET['id'] ?? 0);
                
                if ($id <= 0) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'ID do recurso não informado ou inválido'
                    ], JSON_UNESCAPED_UNICODE);
                    break;
                }
                
                $result = $cpfController->getRecursoDetalhes($id);
                echo json_encode($result, JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Funcionalidade não disponível'
                ], JSON_UNESCAPED_UNICODE);
            }
            break;
            
        case 'orgaos':
            if ($useNewController && $cpfController) {
                $result = $cpfController->getOrgaos();
                echo json_encode($result, JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode([
                    'success' => true,
                    'orgaos' => []
                ], JSON_UNESCAPED_UNICODE);
            }
            break;
            
        case 'verify':
            $cpf = $_POST['cpf'] ?? '';
            
            if (empty($cpf)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'CPF não informado'
                ], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $isValid = validaCPF($cpf);
            
            echo json_encode([
                'success' => true,
                'cpf' => $cpf,
                'is_valid' => $isValid
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Ação não reconhecida'
            ], JSON_UNESCAPED_UNICODE);
            break;
    }
    
} catch (Exception $e) {
    error_log("Erro na API CPF: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Clean any output buffer before sending error response
    if (ob_get_level()) {
        ob_clean();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor: ' . $e->getMessage(),
        'debug' => [
            'action' => $action ?? 'unknown',
            'controller_available' => $useNewController ?? false,
            'controller_class_exists' => class_exists('App\Cpf\CpfController'),
            'fallback_function_exists' => function_exists('getCpfFindingsPaginadoFromNewTable'),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'php_version' => PHP_VERSION,
            'autoloader_functions' => count(spl_autoload_functions())
        ]
    ], JSON_UNESCAPED_UNICODE);
}

// End output buffering and flush
if (ob_get_level()) {
    ob_end_flush();
}