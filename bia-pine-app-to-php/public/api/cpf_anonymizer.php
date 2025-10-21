<?php
/**
 * API para anonimização de CPFs em arquivos
 */

// Start output buffering to prevent any unwanted output
ob_start();

// Log de debug
error_log("=== CPF Anonymizer API Called ===");
error_log("REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? 'N/A'));
error_log("POST action: " . ($_POST['action'] ?? 'N/A'));
error_log("FILES: " . (isset($_FILES['file']) ? $_FILES['file']['name'] : 'N/A'));

require_once __DIR__ . '/../../config.php';

// Clean any output that might have been generated during config loading
$unwantedOutput = ob_get_contents();
if (!empty($unwantedOutput)) {
    error_log("CPF Anonymizer API: Cleaned unwanted output: " . substr($unwantedOutput, 0, 100));
}
ob_clean();

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
    error_log("Creating CpfAnonymizer instance...");
    $anonymizer = new CpfAnonymizer();
    error_log("CpfAnonymizer instance created successfully");
    
    // Limpa arquivos antigos
    $anonymizer->limparArquivosAntigos();
    
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    error_log("Action: $action");
    
    switch ($action) {
        case 'upload':
            error_log("Processing upload...");
            
            if (!isset($_FILES['file'])) {
                error_log("ERROR: No file in upload");
                throw new Exception('Nenhum arquivo enviado');
            }
            
            error_log("File info: " . print_r($_FILES['file'], true));
            
            // Processa upload
            $fileInfo = $anonymizer->processarUpload($_FILES['file']);
            error_log("Upload processed: " . print_r($fileInfo, true));
            
            // Processa arquivo
            $resultado = $anonymizer->processarArquivo($fileInfo['filepath'], $fileInfo['extension']);
            error_log("File processed: " . print_r($resultado, true));
            
            $response = [
                'success' => true,
                'message' => 'Arquivo processado com sucesso',
                'data' => [
                    'arquivo_original' => $fileInfo['original_name'],
                    'arquivo_saida' => $resultado['arquivo_saida'],
                    'total_cpfs' => $resultado['total_cpfs'],
                    'cpfs_encontrados' => $resultado['cpfs_encontrados'],
                    'detalhes' => $resultado
                ]
            ];
            
            error_log("Sending response: " . json_encode($response));
            echo json_encode($response);
            break;
            
        case 'download':
            $filename = $_GET['filename'] ?? '';
            if (empty($filename)) {
                throw new Exception('Nome do arquivo não fornecido');
            }
            
            error_log("Downloading file: $filename");
            $anonymizer->downloadArquivo($filename);
            break;
            
        default:
            error_log("ERROR: Invalid action: $action");
            throw new Exception('Ação inválida. Use action=upload ou action=download');
    }
    
} catch (Exception $e) {
    error_log("ERROR: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error' => $e->getMessage()
    ]);
}

// End output buffering and send response
ob_end_flush();
