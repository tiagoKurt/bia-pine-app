<?php
/**
 * API específica para o módulo BIA
 */

// Desabilitar exibição de erros para evitar HTML na resposta JSON
error_reporting(0);
ini_set('display_errors', 0);

// Configurar tratamento de erro global
set_error_handler(function($severity, $message, $file, $line) {
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
});

// Configurar tratamento de exceções
set_exception_handler(function($exception) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor: ' . $exception->getMessage(),
        'type' => 'error',
        'debug' => [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

// Headers para API
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método não permitido. Use POST.',
        'type' => 'error'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../../config.php';

ensureAutoloader();

use App\Bia;

try {
    $action = $_POST['action'] ?? '';
    
    if ($action !== 'gerar_dicionario') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Ação não reconhecida.',
            'type' => 'error'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $recursoUrl = $_POST['recurso_url'] ?? '';
    
    if (empty($recursoUrl)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Informe o link do recurso CKAN.',
            'type' => 'error'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Instanciar classe Bia
    $bia = new Bia();
    
    // Verificar template
    $templateFile = __DIR__ . '/../../templates/modelo_bia2_pronto_para_preencher.docx';
    if (!file_exists($templateFile)) {
        throw new Exception("Template não encontrado: " . $templateFile);
    }
    
    // Limpar arquivos temporários antigos antes de gerar novo documento
    limparArquivosTemporarios();
    
    // Verificar espaço em disco antes de gerar documento
    $tempDir = sys_get_temp_dir();
    $freeSpace = disk_free_space($tempDir);
    $freeSpaceMB = round($freeSpace / 1024 / 1024, 2);
    $requiredSpaceMB = 10; // 10MB mínimo necessário
    
    if ($freeSpaceMB < $requiredSpaceMB) {
        // Tentar usar diretório alternativo
        $alternativeDir = __DIR__ . '/../../cache/temp';
        if (!is_dir($alternativeDir)) {
            mkdir($alternativeDir, 0755, true);
        }
        
        $altFreeSpace = disk_free_space($alternativeDir);
        $altFreeSpaceMB = round($altFreeSpace / 1024 / 1024, 2);
        
        if ($altFreeSpaceMB < $requiredSpaceMB) {
            throw new Exception("Espaço insuficiente em disco. /tmp: {$freeSpaceMB}MB, Alternativo: {$altFreeSpaceMB}MB, Necessário: {$requiredSpaceMB}MB. Contate o administrador do sistema para liberar espaço em disco.");
        }
        
        // Configurar diretório temporário alternativo
        putenv('TMPDIR=' . $alternativeDir);
    }
    
    // Gerar dicionário
    $outputFile = $bia->gerarDicionarioWord($recursoUrl, $templateFile);
    
    if (!file_exists($outputFile)) {
        throw new Exception("Arquivo não foi gerado: " . $outputFile);
    }
    
    // Retornar resposta de sucesso
    echo json_encode([
        'success' => true,
        'message' => 'Documento gerado e baixado com sucesso!',
        'type' => 'success',
        'downloadFile' => $outputFile,
        'downloadFileName' => basename($outputFile)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Ocorreu um erro ao gerar o dicionário: ' . $e->getMessage(),
        'type' => 'error',
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Limpa arquivos temporários antigos para liberar espaço em disco
 */
function limparArquivosTemporarios() {
    try {
        $tempDir = sys_get_temp_dir();
        $maxAge = 3600; // 1 hora em segundos
        
        // Limpar arquivos .docx antigos do diretório temporário
        $files = glob($tempDir . '/*.docx');
        $cleaned = 0;
        
        foreach ($files as $file) {
            if (is_file($file) && (time() - filemtime($file)) > $maxAge) {
                if (unlink($file)) {
                    $cleaned++;
                }
            }
        }
        
        // Limpar arquivos temporários do PhpWord
        $phpWordTempDir = $tempDir . '/PhpWord';
        if (is_dir($phpWordTempDir)) {
            $phpWordFiles = glob($phpWordTempDir . '/*');
            foreach ($phpWordFiles as $file) {
                if (is_file($file) && (time() - filemtime($file)) > $maxAge) {
                    if (unlink($file)) {
                        $cleaned++;
                    }
                }
            }
        }
        
        // Limpar diretório alternativo também
        $alternativeDir = __DIR__ . '/../../cache/temp';
        if (is_dir($alternativeDir)) {
            $altFiles = glob($alternativeDir . '/*');
            foreach ($altFiles as $file) {
                if (is_file($file) && (time() - filemtime($file)) > $maxAge) {
                    if (unlink($file)) {
                        $cleaned++;
                    }
                }
            }
        }
        
    } catch (Exception $e) {
        // Erro silencioso na limpeza
    }
}
