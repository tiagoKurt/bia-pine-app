<?php
session_start();


require __DIR__ . '/../../config.php';
require __DIR__ . '/../../vendor/autoload.php';

use App\Pine;

header('Content-Type: application/json');
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

try {
    $pdo = conectarBanco();
    
    $pine = new Pine();
    
    $portalUrl = $_GET['portal_url'] ?? '';
    $useAnyPortal = ($portalUrl === 'any');
    
    if (empty($portalUrl) && !$useAnyPortal) {
        throw new Exception('URL do portal é obrigatória');
    }
    
    if ($useAnyPortal) {
        $statsStmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_datasets,
                SUM(CASE WHEN status = 'Atualizado' THEN 1 ELSE 0 END) as datasets_atualizados,
                SUM(CASE WHEN status = 'Desatualizado' THEN 1 ELSE 0 END) as datasets_desatualizados,
                COUNT(DISTINCT organization) as total_orgaos,
                portal_url
            FROM datasets 
            GROUP BY portal_url
            ORDER BY total_datasets DESC
            LIMIT 1
        ");
        $statsStmt->execute();
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($stats) {
            $portalUrl = $stats['portal_url'];
            unset($stats['portal_url']);
        } else {
            throw new Exception('Nenhum dado encontrado no banco');
        }
        
        $orgsStmt = $pdo->prepare("
            SELECT DISTINCT organization 
            FROM datasets 
            WHERE portal_url = ? AND organization IS NOT NULL AND organization != ''
            ORDER BY organization
        ");
        $orgsStmt->execute([$portalUrl]);
        $organizations = $orgsStmt->fetchAll(PDO::FETCH_COLUMN);
    } else {
        $statsStmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_datasets,
                SUM(CASE WHEN status = 'Atualizado' THEN 1 ELSE 0 END) as datasets_atualizados,
                SUM(CASE WHEN status = 'Desatualizado' THEN 1 ELSE 0 END) as datasets_desatualizados,
                COUNT(DISTINCT organization) as total_orgaos
            FROM datasets 
            WHERE portal_url = ?
        ");
        $statsStmt->execute([$portalUrl]);
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
        
        $orgsStmt = $pdo->prepare("
            SELECT DISTINCT organization 
            FROM datasets 
            WHERE portal_url = ? AND organization IS NOT NULL AND organization != ''
            ORDER BY organization
        ");
        $orgsStmt->execute([$portalUrl]);
        $organizations = $orgsStmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    $response = [
        'success' => true,
        'stats' => $stats,
        'organizations' => $organizations,
        'portal_url' => $portalUrl
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
