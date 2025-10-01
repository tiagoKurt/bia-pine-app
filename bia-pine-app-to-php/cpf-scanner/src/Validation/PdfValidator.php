<?php

namespace CpfScanner\Validation;

/**
 * Classe para validação robusta de arquivos PDF - Compatível com PHP 8.0.30
 * Verifica integridade, cabeçalho mágico e outros aspectos de qualidade
 */
class PdfValidator
{
    private const PDF_MAGIC_HEADER = '%PDF-';
    private const MIN_PDF_SIZE = 100; // Tamanho mínimo em bytes para um PDF válido
    private const MAX_PDF_SIZE = 110 * 1024 * 1024; // 200MB máximo

    /**
     * Valida se um arquivo é um PDF válido
     * 
     * @param string $filePath Caminho para o arquivo
     * @return array Array com 'valid' => bool e 'message' => string
     */
    public function validatePdfFile($filePath)
    {
        // 1. Verificar se o arquivo existe
        if (!file_exists($filePath)) {
            return [
                'valid' => false,
                'message' => 'Arquivo não encontrado: ' . $filePath
            ];
        }

        // 2. Verificar se o arquivo não está vazio
        $fileSize = filesize($filePath);
        if ($fileSize === 0) {
            return [
                'valid' => false,
                'message' => 'Arquivo está vazio'
            ];
        }

        // 3. Verificar tamanho mínimo e máximo
        if ($fileSize < self::MIN_PDF_SIZE) {
            return [
                'valid' => false,
                'message' => 'Arquivo muito pequeno para ser um PDF válido (' . $fileSize . ' bytes)'
            ];
        }

        if ($fileSize > self::MAX_PDF_SIZE) {
            return [
                'valid' => false,
                'message' => 'Arquivo muito grande (' . round($fileSize / 1024 / 1024, 2) . 'MB)'
            ];
        }

        // 4. Verificar o cabeçalho mágico do PDF
        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            return [
                'valid' => false,
                'message' => 'Não foi possível abrir o arquivo para leitura'
            ];
        }

        $firstBytes = fread($handle, 5);
        fclose($handle);

        if ($firstBytes === false || strlen($firstBytes) < 4) {
            return [
                'valid' => false,
                'message' => 'Não foi possível ler os primeiros bytes do arquivo'
            ];
        }

        // Verificar se começa com %PDF-
        if (strtoupper(trim($firstBytes)) !== self::PDF_MAGIC_HEADER) {
            // Verificar se é uma página HTML de erro
            $this->logInvalidFileContent($filePath, $firstBytes);
            
            return [
                'valid' => false,
                'message' => 'Arquivo não é um PDF válido. Cabeçalho encontrado: "' . $firstBytes . '" (esperado: "' . self::PDF_MAGIC_HEADER . '")'
            ];
        }

        // 5. Verificação adicional: ler mais bytes para detectar HTML de erro
        $this->checkForHtmlError($filePath);

        return [
            'valid' => true,
            'message' => 'Arquivo PDF válido'
        ];
    }

    /**
     * Valida o conteúdo de download antes de salvar
     * 
     * @param string $content Conteúdo baixado
     * @param string $url URL de origem
     * @return array Array com 'valid' => bool e 'message' => string
     */
    public function validateDownloadedContent($content, $url)
    {
        // Verificar se o conteúdo não está vazio
        if (empty($content)) {
            return [
                'valid' => false,
                'message' => 'Conteúdo baixado está vazio'
            ];
        }

        // Verificar se não é uma página HTML de erro
        if ($this->isHtmlErrorPage($content)) {
            return [
                'valid' => false,
                'message' => 'Download retornou página HTML de erro em vez de PDF'
            ];
        }

        // Verificar cabeçalho PDF nos primeiros bytes
        $firstBytes = substr($content, 0, 5);
        if (strtoupper(trim($firstBytes)) !== self::PDF_MAGIC_HEADER) {
            return [
                'valid' => false,
                'message' => 'Conteúdo baixado não é um PDF válido. Cabeçalho: "' . $firstBytes . '"'
            ];
        }

        return [
            'valid' => true,
            'message' => 'Conteúdo PDF válido'
        ];
    }

    /**
     * Verifica se o conteúdo é uma página HTML de erro
     */
    private function isHtmlErrorPage($content)
    {
        $htmlIndicators = [
            '<!DOCTYPE html>',
            '<html',
            '<head>',
            '<title>',
            '404 Not Found',
            '403 Forbidden',
            '500 Internal Server Error',
            'Access Denied',
            'File Not Found'
        ];

        $contentLower = strtolower($content);
        foreach ($htmlIndicators as $indicator) {
            if (strpos($contentLower, strtolower($indicator)) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verifica se o arquivo contém HTML de erro
     */
    private function checkForHtmlError($filePath)
    {
        $handle = fopen($filePath, 'rb');
        if (!$handle) return;

        $firstChunk = fread($handle, 200); // Lê os primeiros 200 bytes
        fclose($handle);

        if ($firstChunk && $this->isHtmlErrorPage($firstChunk)) {
            error_log("[AVISO] Arquivo PDF pode conter HTML de erro: " . $filePath);
        }
    }

    /**
     * Loga informações sobre arquivo inválido para debug
     */
    private function logInvalidFileContent($filePath, $firstBytes)
    {
        error_log("[ERRO] Arquivo PDF inválido: " . $filePath);
        error_log("[ERRO] Primeiros bytes: " . bin2hex($firstBytes) . " (" . $firstBytes . ")");
        
        // Se for HTML, logar mais informações
        if ($this->isHtmlErrorPage($firstBytes)) {
            error_log("[ERRO] Arquivo contém HTML de erro em vez de PDF");
        }
    }

    /**
     * Obtém informações detalhadas sobre um arquivo
     */
    public function getFileInfo($filePath)
    {
        if (!file_exists($filePath)) {
            return ['error' => 'Arquivo não encontrado'];
        }

        $fileSize = filesize($filePath);
        $handle = fopen($filePath, 'rb');
        $firstBytes = $handle ? fread($handle, 10) : '';
        if ($handle) fclose($handle);

        return [
            'file_size' => $fileSize,
            'file_size_mb' => round($fileSize / 1024 / 1024, 2),
            'first_bytes' => $firstBytes,
            'first_bytes_hex' => bin2hex($firstBytes),
            'is_pdf_header' => strtoupper(trim($firstBytes)) === self::PDF_MAGIC_HEADER,
            'is_html_error' => $this->isHtmlErrorPage($firstBytes)
        ];
    }
}
