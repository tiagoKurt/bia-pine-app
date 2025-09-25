<?php
session_start();

require __DIR__ . '/../../config.php';
require __DIR__ . '/../../vendor/autoload.php';

use App\Pine;

header('Content-Type: application/json');

try {
    $pdo = conectarBanco();
    $pine = new Pine();
    
    $portalUrl = $_GET['portal_url'] ?? '';
    $page = (int)($_GET['page'] ?? 1);
    $perPage = (int)($_GET['per_page'] ?? 15);
    $organization = $_GET['organization'] ?? '';
    $status = $_GET['status'] ?? '';
    $search = $_GET['search'] ?? '';
    $useAnyPortal = ($portalUrl === 'any');
    
    if (empty($portalUrl) && !$useAnyPortal) {
        throw new Exception('URL do portal é obrigatória');
    }
    
    if ($useAnyPortal) {
        // Buscar URL de qualquer portal existente
        $urlStmt = $pdo->prepare("
            SELECT portal_url 
            FROM datasets 
            GROUP BY portal_url
            ORDER BY COUNT(*) DESC
            LIMIT 1
        ");
        $urlStmt->execute();
        $result = $urlStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $portalUrl = $result['portal_url'];
        } else {
            throw new Exception('Nenhum dado encontrado no banco');
        }
    }
    
    $offset = ($page - 1) * $perPage;
    
    $whereConditions = ['portal_url = ?'];
    $params = [$portalUrl];
    
    if (!empty($organization)) {
        $whereConditions[] = 'organization = ?';
        $params[] = $organization;
    }
    
    if (!empty($status)) {
        $whereConditions[] = 'status = ?';
        $params[] = $status;
    }
    
    if (!empty($search)) {
        $whereConditions[] = '(name LIKE ? OR dataset_id LIKE ?)';
        $searchTerm = '%' . $search . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $countSql = "SELECT COUNT(*) FROM datasets WHERE $whereClause";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalRecords = (int) $countStmt->fetchColumn();
    
    $dataSql = "SELECT * FROM datasets WHERE $whereClause ORDER BY last_updated DESC LIMIT ? OFFSET ?";
    $dataStmt = $pdo->prepare($dataSql);
    $dataParams = array_merge($params, [$perPage, $offset]);
    $dataStmt->execute($dataParams);
    $datasets = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="analise_pine_filtrada_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($output, ['ID', 'Nome do Dataset', 'Órgão', 'Última Atualização', 'Status', 'Dias Desde Atualização', 'Recursos', 'Link']);
        
        foreach ($datasets as $dataset) {
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
    
    echo json_encode([
        'success' => true,
        'datasets' => $datasets,
        'total' => $totalRecords,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => ceil($totalRecords / $perPage),
        'filters' => [
            'organization' => $organization,
            'status' => $status,
            'search' => $search
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
