<?php

namespace CpfScanner\Download;

use CpfScanner\Validation\PdfValidator;
use Exception;

/**
 * Classe para download robusto de arquivos com validação - Compatível com PHP 8.0.30
 */
class FileDownloader
{
    private $pdfValidator;
    private $timeout;
    private $maxFileSize;
    private $userAgent;

    public function __construct(
        $timeout = 30,
        $maxFileSize = 110 * 1024 * 1024, // 200MB
        $userAgent = 'CKAN-Scanner/1.0'
    ) {
        $this->pdfValidator = new PdfValidator();
        $this->timeout = $timeout;
        $this->maxFileSize = $maxFileSize;
        $this->userAgent = $userAgent;
    }

    /**
     * Baixa um arquivo com validação robusta
     * 
     * @param string $url URL do arquivo
     * @param string $destinationPath Caminho de destino
     * @param string $expectedFormat Formato esperado (pdf, csv, etc.)
     * @return array Array com 'success' => bool, 'message' => string, 'file_path' => string|null
     */
    public function downloadFile($url, $destinationPath, $expectedFormat = 'pdf')
    {
        try {
            // 1. Validar URL
            if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
                return [
                    'success' => false,
                    'message' => 'URL inválida: ' . $url,
                    'file_path' => null
                ];
            }

            // 2. Verificar espaço em disco
            $freeSpace = disk_free_space(dirname($destinationPath));
            if ($freeSpace < $this->maxFileSize + (200 * 1024 * 1024)) { // 200MB de margem
                return [
                    'success' => false,
                    'message' => 'Espaço insuficiente em disco',
                    'file_path' => null
                ];
            }

            // 3. Configurar contexto HTTP
            $context = $this->createHttpContext();

            // 4. Fazer download
            $fileContent = @file_get_contents($url, false, $context);
            
            if ($fileContent === false) {
                $error = error_get_last();
                return [
                    'success' => false,
                    'message' => 'Falha no download: ' . ($error['message'] ?? 'Erro desconhecido'),
                    'file_path' => null
                ];
            }

            // 5. Verificar se o conteúdo não está vazio
            if (empty($fileContent)) {
                return [
                    'success' => false,
                    'message' => 'Arquivo baixado está vazio',
                    'file_path' => null
                ];
            }

            // 6. Verificar tamanho do arquivo
            if (strlen($fileContent) > $this->maxFileSize) {
                return [
                    'success' => false,
                    'message' => 'Arquivo muito grande: ' . round(strlen($fileContent) / 1024 / 1024, 2) . 'MB',
                    'file_path' => null
                ];
            }

            // 7. Validar conteúdo específico para PDFs
            if (strtolower($expectedFormat) === 'pdf') {
                $validation = $this->pdfValidator->validateDownloadedContent($fileContent, $url);
                if (!$validation['valid']) {
                    return [
                        'success' => false,
                        'message' => 'Conteúdo PDF inválido: ' . $validation['message'],
                        'file_path' => null
                    ];
                }
            }

            // 8. Salvar arquivo
            $bytesWritten = file_put_contents($destinationPath, $fileContent);
            if ($bytesWritten === false) {
                return [
                    'success' => false,
                    'message' => 'Falha ao salvar arquivo em: ' . $destinationPath,
                    'file_path' => null
                ];
            }

            // 9. Verificar se o arquivo foi salvo corretamente
            if (!file_exists($destinationPath) || filesize($destinationPath) === 0) {
                return [
                    'success' => false,
                    'message' => 'Arquivo não foi salvo corretamente',
                    'file_path' => null
                ];
            }

            // 10. Validação final do arquivo salvo
            if (strtolower($expectedFormat) === 'pdf') {
                $finalValidation = $this->pdfValidator->validatePdfFile($destinationPath);
                if (!$finalValidation['valid']) {
                    // Limpar arquivo inválido
                    @unlink($destinationPath);
                    return [
                        'success' => false,
                        'message' => 'Arquivo PDF inválido após salvamento: ' . $finalValidation['message'],
                        'file_path' => null
                    ];
                }
            }

            return [
                'success' => true,
                'message' => 'Download realizado com sucesso',
                'file_path' => $destinationPath,
                'file_size' => filesize($destinationPath)
            ];

        } catch (Exception $e) {
            // Limpar arquivo em caso de erro
            if (file_exists($destinationPath)) {
                @unlink($destinationPath);
            }
            
            return [
                'success' => false,
                'message' => 'Erro durante download: ' . $e->getMessage(),
                'file_path' => null
            ];
        }
    }

    /**
     * Baixa arquivo com retry automático
     */
    public function downloadFileWithRetry(
        $url, 
        $destinationPath, 
        $expectedFormat = 'pdf',
        $maxRetries = 3
    ) {
        $lastError = null;
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            error_log("[DOWNLOAD] Tentativa {$attempt}/{$maxRetries} para: " . $url);
            
            $result = $this->downloadFile($url, $destinationPath, $expectedFormat);
            
            if ($result['success']) {
                if ($attempt > 1) {
                    error_log("[DOWNLOAD] Sucesso na tentativa {$attempt} para: " . $url);
                }
                return $result;
            }
            
            $lastError = $result['message'];
            error_log("[DOWNLOAD] Falha na tentativa {$attempt}: " . $lastError);
            
            // Aguardar antes da próxima tentativa (backoff exponencial)
            if ($attempt < $maxRetries) {
                $waitTime = pow(2, $attempt - 1); // 1s, 2s, 4s...
                sleep($waitTime);
            }
        }
        
        return [
            'success' => false,
            'message' => "Falha após {$maxRetries} tentativas. Último erro: " . $lastError,
            'file_path' => null
        ];
    }

    /**
     * Cria contexto HTTP com configurações robustas
     */
    private function createHttpContext()
    {
        return stream_context_create([
            'http' => [
                'timeout' => $this->timeout,
                'user_agent' => $this->userAgent,
                'follow_location' => true,
                'max_redirects' => 5,
                'method' => 'GET',
                'header' => [
                    'Accept: */*',
                    'Accept-Encoding: gzip, deflate',
                    'Connection: close'
                ]
            ]
        ]);
    }

    /**
     * Obtém informações sobre o download
     */
    public function getDownloadInfo($url)
    {
        $context = $this->createHttpContext();
        
        // Fazer HEAD request para obter informações sem baixar o arquivo
        $context = stream_context_create([
            'http' => [
                'timeout' => $this->timeout,
                'user_agent' => $this->userAgent,
                'method' => 'HEAD',
                'follow_location' => true,
                'max_redirects' => 5
            ]
        ]);

        $headers = @get_headers($url, 1, $context);
        
        if ($headers === false) {
            return ['error' => 'Não foi possível obter informações do arquivo'];
        }

        $statusCode = null;
        $contentLength = null;
        $contentType = null;

        if (isset($headers[0])) {
            preg_match('/HTTP\/\d\.\d\s+(\d+)/', $headers[0], $matches);
            $statusCode = isset($matches[1]) ? (int)$matches[1] : null;
        }

        $contentLength = isset($headers['Content-Length']) ? (int)$headers['Content-Length'] : null;
        $contentType = isset($headers['Content-Type']) ? $headers['Content-Type'] : null;

        return [
            'status_code' => $statusCode,
            'content_length' => $contentLength,
            'content_type' => $contentType,
            'is_success' => $statusCode >= 200 && $statusCode < 300,
            'is_redirect' => $statusCode >= 300 && $statusCode < 400,
            'is_error' => $statusCode >= 400
        ];
    }
}
