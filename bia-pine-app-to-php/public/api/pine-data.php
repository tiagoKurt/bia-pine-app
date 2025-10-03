<?php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/functions.php';

// Headers para API
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Trata requisições OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $action = $_GET['action'] ?? 'stats';
    $page = (int)($_GET['page'] ?? 1);
    $perPage = (int)($_GET['per_page'] ?? 15);
    $organization = $_GET['organization'] ?? '';
    $status = $_GET['status'] ?? '';
    $search = $_GET['search'] ?? '';
    $portalUrl = $_GET['portal_url'] ?? '';
    
    $pdo = conectarBanco();
    $pine = new App\Pine();
    
    switch ($action) {
        case 'stats':
            // Estatísticas gerais dos datasets PINE
            if ($portalUrl) {
                $stats = $pine->getEstatisticasPorPortal($pdo, $portalUrl);
                $organizations = $pine->getOrganizacoesPorPortal($pdo, $portalUrl);
            } else {
                $stats = $pine->getEstatisticasGerais($pdo);
                $organizations = $pine->getOrganizacoes($pdo);
            }
            
            // Converter organizações para array simples
            $orgNames = array_column($organizations, 'organization');
            
            echo json_encode([
                'success' => true,
                'stats' => $stats,
                'organizations' => $orgNames,
                'portal_url' => $portalUrl ?: $pine->getUltimoPortalAnalisado($pdo)
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'datasets':
            // Listar datasets PINE
            if (!$portalUrl) {
                $portalUrl = $pine->getUltimoPortalAnalisado($pdo);
            }
            
            if (!$portalUrl) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Nenhum portal analisado encontrado'
                ], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $datasets = $pine->getDatasetsPaginadosComFiltros(
                $pdo, 
                $portalUrl, 
                $page, 
                $perPage, 
                $organization, 
                $status, 
                $search
            );
            
            echo json_encode([
                'success' => true,
                'datasets' => $datasets['datasets'] ?? [],
                'total' => $datasets['total'] ?? 0,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => $datasets['total_pages'] ?? 1
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'organizations':
            // Listar organizações disponíveis
            if ($portalUrl) {
                $organizations = $pine->getOrganizacoesPorPortal($pdo, $portalUrl);
            } else {
                $organizations = $pine->getOrganizacoes($pdo);
            }
            
            $orgNames = array_column($organizations, 'organization');
            
            echo json_encode([
                'success' => true,
                'organizations' => $orgNames
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
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}