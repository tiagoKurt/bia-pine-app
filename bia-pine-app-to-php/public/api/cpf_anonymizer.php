<?php
/**
 * API para anonimização de CPFs em arquivos
 * Otimizado para compatibilidade com CKAN
 */

ob_start();

require_once __DIR__ . '/../../config.php';

// Limpar output indesejado
if (ob_get_length()) ob_clean();

use App\Cpf\CpfAnonymizer;

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $anonymizer = new CpfAnonymizer();
    $anonymizer->limparArquivosAntigos();
    
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'upload':
            if (!isset($_FILES['file'])) {
                throw new Exception('Nenhum arquivo enviado');
            }
            
            $fileInfo = $anonymizer->processarUpload($_FILES['file']);
            $resultado = $anonymizer->processarArquivo($fileInfo['filepath'], $fileInfo['extension']);
            
            echo json_encode([
                'success' => true,
                'message' => 'Arquivo processado com sucesso',
                'data' => [
                    'arquivo_original' => $fileInfo['original_name'],
                    'arquivo_saida' => $resultado['arquivo_saida'],
                    'total_cpfs' => $resultado['total_cpfs'],
                    'cpfs_encontrados' => $resultado['cpfs_encontrados'],
                    'detalhes' => $resultado
                ]
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'download':
            $filename = $_GET['filename'] ?? '';
            if (empty($filename)) {
                throw new Exception('Nome do arquivo não fornecido');
            }
            
            $anonymizer->downloadArquivo($filename);
            break;
            
        default:
            throw new Exception('Ação inválida. Use action=upload ou action=download');
    }
    
} catch (Exception $e) {
    error_log("CPF Anonymizer Error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

ob_end_flush();
