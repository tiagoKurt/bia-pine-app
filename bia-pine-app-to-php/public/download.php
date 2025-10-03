<?php
// Habilitar logging de erros
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if (!isset($_GET['file']) || !isset($_GET['path'])) {
    http_response_code(400);
    error_log("Download error: Parâmetros inválidos - file: " . ($_GET['file'] ?? 'não definido') . ", path: " . ($_GET['path'] ?? 'não definido'));
    die('Parâmetros inválidos');
}

$fileName = $_GET['file'];
$filePath = $_GET['path'];

if (empty($fileName) || empty($filePath)) {
    http_response_code(400);
    die('Parâmetros vazios');
}

$filePath = urldecode($filePath);

$tempDir = sys_get_temp_dir();
$realPath = realpath($filePath);

error_log("Temp Dir: " . $tempDir);
error_log("File Path: " . $filePath);
error_log("Real Path: " . $realPath);

if (!$realPath) {
    http_response_code(404);
    die('Arquivo não encontrado');
}

$tempDirReal = realpath($tempDir);
$fileDir = dirname($realPath);

// Verificação mais flexível para permitir arquivos temporários
$isInTempDir = strpos($realPath, $tempDirReal) === 0;
$isInProjectDir = strpos($realPath, realpath(__DIR__ . '/../..')) === 0;

if (!$isInTempDir && !$isInProjectDir) {
    http_response_code(403);
    die('Acesso negado: arquivo fora dos diretórios permitidos. Temp: ' . $tempDirReal . ', Project: ' . realpath(__DIR__ . '/../..') . ', File Dir: ' . $fileDir);
}

if (!file_exists($realPath)) {
    http_response_code(404);
    die('Arquivo não encontrado');
}

if (!is_file($realPath)) {
    http_response_code(400);
    die('Caminho não é um arquivo válido');
}

$extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
if ($extension !== 'docx') {
    http_response_code(400);
    die('Tipo de arquivo não permitido');
}

header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Length: ' . filesize($realPath));
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

if (ob_get_level()) {
    ob_end_clean();
}

try {
    $handle = fopen($realPath, 'rb');
    if ($handle === false) {
        error_log("Download error: Não foi possível abrir o arquivo: " . $realPath);
        http_response_code(500);
        die('Erro ao abrir arquivo');
    }

    while (!feof($handle)) {
        $chunk = fread($handle, 8192);
        if ($chunk === false) {
            error_log("Download error: Erro ao ler chunk do arquivo: " . $realPath);
            break;
        }
        echo $chunk;
    }

    fclose($handle);

    // Tentar remover o arquivo temporário, mas não falhar se não conseguir
    if (!unlink($realPath)) {
        error_log("Download warning: Não foi possível remover arquivo temporário: " . $realPath);
    }

} catch (Exception $e) {
    error_log("Download error: Exceção durante download: " . $e->getMessage());
    http_response_code(500);
    die('Erro interno durante download');
}

exit;
?>
