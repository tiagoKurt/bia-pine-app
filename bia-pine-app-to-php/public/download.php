<?php
if (!isset($_GET['file']) || !isset($_GET['path'])) {
    http_response_code(400);
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

if (strpos($realPath, $tempDirReal) !== 0) {
    http_response_code(403);
    die('Acesso negado: arquivo fora do diretório temporário. Temp: ' . $tempDirReal . ', File Dir: ' . $fileDir);
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

$handle = fopen($realPath, 'rb');
if ($handle === false) {
    http_response_code(500);
    die('Erro ao abrir arquivo');
}

while (!feof($handle)) {
    $chunk = fread($handle, 8192);
    if ($chunk === false) {
        break;
    }
    echo $chunk;
}

fclose($handle);

unlink($realPath);

exit;
?>
