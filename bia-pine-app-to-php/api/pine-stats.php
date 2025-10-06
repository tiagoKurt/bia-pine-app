<?php

require_once __DIR__ . '/../config.php';

use App\Pine;

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
    $portalUrl = $_GET['portal_url'] ?? '';
    
    if (empty($portalUrl) || $portalUrl === 'any') {
        // Buscar dados existentes no banco
        $pdo = conectarBanco();
        $pine = new Pine();
        
        // Buscar estatísticas gerais
        $stats = $pine->getEstatisticasGerais($pdo);
        $organizations = $pine->getOrganizacoes($pdo);
        
        // Buscar último portal analisado
        $lastPortal = $pine->getUltimoPortalAnalisado($pdo);
        
        echo json_encode([
            'success' => true,
            'stats' => $stats,
            'organizations' => $organizations,
            'portal_url' => $lastPortal
        ], JSON_UNESCAPED_UNICODE);
        
    } else {
        // Analisar portal específico
        $pine = new Pine();
        $resultado = $pine->analisarESalvarPortal($portalUrl);
        
        if (isset($resultado['success']) && $resultado['success']) {
            $pdo = conectarBanco();
            $stats = $pine->getEstatisticasPorPortal($pdo, $portalUrl);
            $organizations = $pine->getOrganizacoesPorPortal($pdo, $portalUrl);
            
            echo json_encode([
                'success' => true,
                'stats' => $stats,
                'organizations' => $organizations,
                'portal_url' => $portalUrl,
                'message' => $resultado['message'] ?? 'Análise concluída com sucesso'
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode([
                'success' => false,
                'message' => $resultado['message'] ?? 'Erro desconhecido ao analisar portal'
            ], JSON_UNESCAPED_UNICODE);
        }
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
