<?php

namespace CpfScanner\Parsing\Parser;

use CpfScanner\Parsing\Contract\FileParserInterface;
use CpfScanner\Validation\PdfValidator;
use Smalot\PdfParser\Parser;
use Exception as PdfParserException;

// Carregar configurações específicas de PDF
require_once __DIR__ . '/../../../config/pdf-config.php';

class PdfParser implements FileParserInterface
{
    private $pdfValidator;
    private $maxExecutionTime;
    private $memoryLimit;

    public function __construct()
    {
        $this->pdfValidator = new PdfValidator();
        // Configurações otimizadas para PDFs grandes
        $this->maxExecutionTime = PDF_MAX_EXECUTION_TIME;
        $this->memoryLimit = PDF_MEMORY_LIMIT;
        
        // Aplicar configurações específicas de PDF
        aplicarConfiguracoesPdf();
    }

    public function getText(string $filePath): string
    {
        if (!file_exists($filePath)) {
            throw new \Exception("Arquivo não encontrado: {$filePath}");
        }

        // Configurar limites antes do processamento
        $originalTimeLimit = ini_get('max_execution_time');
        $originalMemoryLimit = ini_get('memory_limit');
        
        ini_set('max_execution_time', $this->maxExecutionTime);
        ini_set('memory_limit', $this->memoryLimit);

        // Validar o arquivo PDF antes de processar
        $validation = $this->pdfValidator->validatePdfFile($filePath);
        if (!$validation['valid']) {
            // Restaurar configurações originais
            ini_set('max_execution_time', $originalTimeLimit);
            ini_set('memory_limit', $originalMemoryLimit);
            throw new \Exception("Arquivo PDF inválido: " . $validation['message']);
        }

        try {
            // Verificar tamanho do arquivo antes do processamento
            $fileSize = filesize($filePath);
            if ($fileSize > PDF_MAX_SIZE_WARNING) {
                logPdf('WARNING', "Arquivo PDF muito grande: " . round($fileSize / 1024 / 1024, 2) . "MB", ['file' => $filePath]);
            }

            // Verificar se deve processar em chunks
            if (deveProcessarEmChunks($filePath)) {
                logPdf('INFO', "Processando PDF grande em chunks", ['file' => $filePath, 'size' => $fileSize]);
                $chunks = $this->getTextInChunks($filePath);
                $fullText = '';
                
                foreach ($chunks as $chunk) {
                    $fullText .= $chunk['text'] . "\n";
                }
                
                // Restaurar configurações originais
                ini_set('max_execution_time', $originalTimeLimit);
                ini_set('memory_limit', $originalMemoryLimit);
                
                logPdf('INFO', "Concluído processamento em chunks", ['file' => $filePath, 'text_length' => strlen($fullText)]);
                return $fullText;
            }

            $parser = new Parser();
            
            // Configurar timeout específico para o parser
            $startTime = microtime(true);
            $timeout = PDF_TIMEOUT;
            
            // Usar output buffering para capturar possíveis erros
            ob_start();
            
            try {
                $pdf = $parser->parseFile($filePath);
                
                if (!$pdf) {
                    throw new \Exception("Não foi possível analisar o arquivo PDF");
                }
                
                // Verificar timeout durante o processamento
                if ((microtime(true) - $startTime) > $timeout) {
                    throw new \Exception("Timeout: PDF muito complexo para processar em tempo hábil");
                }
                
                $text = $pdf->getText();
                
                // Limpar buffer de saída
                ob_end_clean();
                
                // Restaurar configurações originais
                ini_set('max_execution_time', $originalTimeLimit);
                ini_set('memory_limit', $originalMemoryLimit);
                
                return $text;
                
            } catch (\Exception $e) {
                // Limpar buffer de saída em caso de erro
                ob_end_clean();
                throw $e;
            }
            
        } catch (PdfParserException $e) {
            // Log detalhado para debug
            $fileInfo = $this->pdfValidator->getFileInfo($filePath);
            $executionTime = isset($startTime) ? microtime(true) - $startTime : 0;
            
            logPdf('ERROR', "Falha ao processar PDF", [
                'file' => $filePath,
                'file_info' => $fileInfo,
                'error' => $e->getMessage(),
                'execution_time' => $executionTime
            ]);
            
            // Restaurar configurações originais
            ini_set('max_execution_time', $originalTimeLimit);
            ini_set('memory_limit', $originalMemoryLimit);
            
            throw new \Exception("Erro ao processar arquivo PDF '{$filePath}': " . $e->getMessage());
        } catch (\Exception $e) {
            // Restaurar configurações originais
            ini_set('max_execution_time', $originalTimeLimit);
            ini_set('memory_limit', $originalMemoryLimit);
            
            logPdf('ERROR', "Erro inesperado ao processar PDF", [
                'file' => $filePath,
                'error' => $e->getMessage()
            ]);
            
            throw new \Exception("Erro inesperado ao processar '{$filePath}': " . $e->getMessage());
        }
    }

    public function supports(string $filePath): bool
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        return in_array($extension, $this->getSupportedFormats());
    }

    public function getSupportedFormats(): array
    {
        return ['pdf'];
    }

    /**
     * Processa PDFs grandes em chunks para evitar timeout
     */
    public function getTextInChunks(string $filePath, int $maxChunkSize = null): array
    {
        if (!file_exists($filePath)) {
            throw new \Exception("Arquivo não encontrado: {$filePath}");
        }

        // Usar configuração padrão se não especificado
        if ($maxChunkSize === null) {
            $maxChunkSize = PDF_CHUNK_SIZE;
        }

        // Configurar limites antes do processamento
        $originalTimeLimit = ini_get('max_execution_time');
        $originalMemoryLimit = ini_get('memory_limit');
        
        ini_set('max_execution_time', $this->maxExecutionTime);
        ini_set('memory_limit', $this->memoryLimit);

        try {
            $parser = new Parser();
            $pdf = $parser->parseFile($filePath);
            
            if (!$pdf) {
                throw new \Exception("Não foi possível analisar o arquivo PDF");
            }

            $pages = $pdf->getPages();
            $totalPages = count($pages);
            $chunks = [];
            $currentChunk = '';
            $currentChunkSize = 0;

            foreach ($pages as $pageIndex => $page) {
                try {
                    $pageText = $page->getText();
                    $currentChunk .= $pageText . "\n";
                    $currentChunkSize++;

                    // Se atingiu o tamanho máximo do chunk ou é a última página
                    if ($currentChunkSize >= $maxChunkSize || $pageIndex === $totalPages - 1) {
                        if (!empty(trim($currentChunk))) {
                            $chunks[] = [
                                'text' => $currentChunk,
                                'pages' => range($pageIndex - $currentChunkSize + 1, $pageIndex + 1),
                                'chunk_index' => count($chunks)
                            ];
                        }
                        $currentChunk = '';
                        $currentChunkSize = 0;

                        // Força limpeza de memória entre chunks
                        if (function_exists('gc_collect_cycles')) {
                            gc_collect_cycles();
                        }
                    }

                    // Log de progresso para PDFs grandes
                    if ($totalPages > 100 && ($pageIndex + 1) % PDF_LOG_PROGRESS_INTERVAL === 0) {
                        logPdf('INFO', "Progresso do processamento em chunks", [
                            'file' => $filePath,
                            'pages_processed' => $pageIndex + 1,
                            'total_pages' => $totalPages,
                            'percentage' => round((($pageIndex + 1) / $totalPages) * 100, 2)
                        ]);
                    }

                } catch (\Exception $e) {
                    logPdf('WARNING', "Erro ao processar página específica", [
                        'file' => $filePath,
                        'page' => $pageIndex + 1,
                        'error' => $e->getMessage()
                    ]);
                    continue; // Continua com a próxima página
                }
            }

            // Restaurar configurações originais
            ini_set('max_execution_time', $originalTimeLimit);
            ini_set('memory_limit', $originalMemoryLimit);

            return $chunks;

        } catch (\Exception $e) {
            // Restaurar configurações originais
            ini_set('max_execution_time', $originalTimeLimit);
            ini_set('memory_limit', $originalMemoryLimit);
            
            throw new \Exception("Erro ao processar PDF em chunks '{$filePath}': " . $e->getMessage());
        }
    }

    /**
     * Verifica se um PDF é muito grande e deve ser processado em chunks
     */
    public function shouldProcessInChunks(string $filePath): bool
    {
        return deveProcessarEmChunks($filePath);
    }
}
