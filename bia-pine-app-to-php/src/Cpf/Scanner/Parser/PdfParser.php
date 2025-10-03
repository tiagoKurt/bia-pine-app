<?php

namespace App\Cpf\Scanner\Parser;

use App\Cpf\Scanner\Parser\FileParserInterface;
use Smalot\PdfParser\Parser;
use Exception as PdfParserException;

class PdfParser implements FileParserInterface
{
    private $maxExecutionTime;
    private $memoryLimit;

    public function __construct()
    {
        // Configurações otimizadas para PDFs grandes
        $this->maxExecutionTime = 1800; // 30 minutos
        $this->memoryLimit = '4G';
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

        try {
            // Verificar tamanho do arquivo antes do processamento
            $fileSize = filesize($filePath);
            if ($fileSize > 100 * 1024 * 1024) { // 100MB
                error_log("WARNING: Arquivo PDF muito grande: " . round($fileSize / 1024 / 1024, 2) . "MB - {$filePath}");
            }

            // Verificar se deve processar em chunks (PDFs maiores que 50MB)
            if ($fileSize > 50 * 1024 * 1024) {
                error_log("INFO: Processando PDF grande em chunks - {$filePath} (" . round($fileSize / 1024 / 1024, 2) . "MB)");
                $chunks = $this->getTextInChunks($filePath);
                $fullText = '';
                
                foreach ($chunks as $chunk) {
                    $fullText .= $chunk['text'] . "\n";
                }
                
                // Restaurar configurações originais
                ini_set('max_execution_time', $originalTimeLimit);
                ini_set('memory_limit', $originalMemoryLimit);
                
                error_log("INFO: Concluído processamento em chunks - {$filePath} (" . strlen($fullText) . " caracteres)");
                return $fullText;
            }

            $parser = new Parser();
            
            // Configurar timeout específico para o parser
            $startTime = microtime(true);
            $timeout = 1200; // 20 minutos
            
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
            $executionTime = isset($startTime) ? microtime(true) - $startTime : 0;
            
            error_log("ERROR: Falha ao processar PDF - {$filePath} - {$e->getMessage()} - Tempo: {$executionTime}s");
            
            // Restaurar configurações originais
            ini_set('max_execution_time', $originalTimeLimit);
            ini_set('memory_limit', $originalMemoryLimit);
            
            throw new \Exception("Erro ao processar arquivo PDF '{$filePath}': " . $e->getMessage());
        } catch (\Exception $e) {
            // Restaurar configurações originais
            ini_set('max_execution_time', $originalTimeLimit);
            ini_set('memory_limit', $originalMemoryLimit);
            
            error_log("ERROR: Erro inesperado ao processar PDF - {$filePath} - {$e->getMessage()}");
            
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
            $maxChunkSize = 10; // 10 páginas por chunk
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
                    if ($totalPages > 100 && ($pageIndex + 1) % 50 === 0) {
                        $percentage = round((($pageIndex + 1) / $totalPages) * 100, 2);
                        error_log("INFO: Progresso do processamento em chunks - {$filePath} - Páginas: " . ($pageIndex + 1) . "/{$totalPages} ({$percentage}%)");
                    }

                } catch (\Exception $e) {
                    error_log("WARNING: Erro ao processar página específica - {$filePath} - Página: " . ($pageIndex + 1) . " - {$e->getMessage()}");
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
        if (!file_exists($filePath)) {
            return false;
        }
        
        $fileSize = filesize($filePath);
        return $fileSize > 50 * 1024 * 1024; // 50MB
    }
}
