<?php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Bia;

// Headers para API
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Trata requisições OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $action = $_GET['action'] ?? '';
    $fileId = $_GET['file_id'] ?? '';
    
    switch ($action) {
        case 'dicionario':
            // Download de dicionário BIA
            if (empty($fileId)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'ID do arquivo não informado'
                ], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $filePath = __DIR__ . '/../../downloads/' . $fileId;
            
            if (!file_exists($filePath)) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Arquivo não encontrado'
                ], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            // Forçar download
            header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
            header('Content-Length: ' . filesize($filePath));
            
            readfile($filePath);
            break;
            
        case 'cpf_report':
            // Download de relatório de CPF
            $format = $_GET['format'] ?? 'csv';
            $page = (int)($_GET['page'] ?? 1);
            $perPage = (int)($_GET['per_page'] ?? 1000);
            
            $pdo = conectarBanco();
            require_once __DIR__ . '/../../src/functions.php';
            
            $cpfData = getCpfFindingsPaginadoFromNewTable($pdo, $page, $perPage);
            
            if ($format === 'csv') {
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="cpf_report_' . date('Y-m-d_H-i-s') . '.csv"');
                
                $output = fopen('php://output', 'w');
                
                // Cabeçalho CSV
                fputcsv($output, [
                    'CPF',
                    'Dataset ID',
                    'Resource ID',
                    'Resource Name',
                    'Organization',
                    'Data Verificação'
                ]);
                
                foreach ($cpfData['findings'] as $finding) {
                    fputcsv($output, [
                        $finding['cpf'],
                        $finding['dataset_id'],
                        $finding['resource_id'],
                        $finding['resource_name'],
                        $finding['organization'],
                        $finding['data_verificacao']
                    ]);
                }
                
                fclose($output);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Formato não suportado'
                ], JSON_UNESCAPED_UNICODE);
            }
            break;
            
        default:
            http_response_code(400);
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
