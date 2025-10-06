<?php

require_once __DIR__ . '/../config.php';

use App\Cpf\CpfController;

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../src/functions.php';

try {
    $action = $_GET['action'] ?? 'list';
    $page = (int)($_GET['page'] ?? 1);
    $perPage = min((int)($_GET['per_page'] ?? 10), 50); // Limitar a 50 itens por página
    
    $pdo = conectarBanco();
    
    // Tentar usar o novo controller, com fallback para funções antigas
    $useNewController = class_exists('App\Cpf\CpfController');
    $cpfController = null;
    
    if ($useNewController) {
        try {
            $cpfController = new CpfController($pdo);
        } catch (Exception $e) {
            error_log("Erro ao instanciar CpfController: " . $e->getMessage());
            $useNewController = false;
        }
    }
    
    switch ($action) {
        case 'list':
            if ($useNewController && $cpfController) {
                // Usar novo controller
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
                
                $result = $cpfController->getCpfFindingsPaginado($page, $perPage, $filtros);
                echo json_encode($result, JSON_UNESCAPED_UNICODE);
            } else {
                // Fallback para função antiga
                $cpfData = getCpfFindingsPaginadoFromNewTable($pdo, $page, $perPage);
                
                echo json_encode([
                    'success' => true,
                    'findings' => $cpfData['findings'] ?? [],
                    'total' => $cpfData['total_resources'] ?? 0,
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => $cpfData['total_paginas'] ?? 1,
                    'has_next' => $page < ($cpfData['total_paginas'] ?? 1),
                    'has_prev' => $page > 1
                ], JSON_UNESCAPED_UNICODE);
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
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor: ' . $e->getMessage(),
        'debug' => [
            'action' => $action ?? 'unknown',
            'controller_available' => $useNewController ?? false,
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ], JSON_UNESCAPED_UNICODE);
}
