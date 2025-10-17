<?php

namespace App\Worker;

use App\Cpf\Ckan\CkanApiClient;
use App\Cpf\Scanner\Parser\FileParserFactory;
use App\Cpf\Scanner\LogicBasedScanner;
use App\Cpf\CpfVerificationService;
use App\Cpf\CpfRepository;
use Exception;

class CkanScannerService
{
    private const RECURSOS_POR_LOTE = 20; // Lote reduzido para melhor controle de memória - 20 arquivos
    private const TEMPO_LIMITE_EXECUCAO = 3600; // 60 minutos - tempo mais generoso
    private const MEMORY_LIMIT_MB = 256; // Limite de memória mais conservador
    private const BATCH_SIZE = 20; // Tamanho do lote para processamento - 20 arquivos
    private const CLEANUP_AFTER_BATCH = true; // Limpar arquivos após cada lote
    private const MAX_FILE_SIZE_MB = 50; // Limite de arquivo reduzido para 50MB
    private const CHUNK_SIZE = 4096; // Chunk menor para streaming - 4KB

    private CkanApiClient $ckanClient;
    private LogicBasedScanner $scanner;
    private CpfVerificationService $verificationService;
    private CpfRepository $cpfRepository;
    private string $tempDir;
    private float $startTime;
    private $progressCallback;

    public function __construct($ckanUrl, $ckanApiKey, $cacheDir, $pdo, $maxRetries = 5)
    {
        $this->ckanClient = new CkanApiClient($ckanUrl, $ckanApiKey, $cacheDir, $maxRetries);
        $this->scanner = new LogicBasedScanner();
        $this->verificationService = new CpfVerificationService($pdo);
        $this->cpfRepository = new CpfRepository($pdo);
        $this->tempDir = sys_get_temp_dir() . '/ckan_scanner_batch';
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
        $this->startTime = microtime(true);
    }

    public function setProgressCallback(callable $callback): void
    {
        $this->progressCallback = $callback;
    }

    private function updateProgress(array $data): void
    {
        if ($this->progressCallback) {
            call_user_func($this->progressCallback, $data);
        }
    }

    /**
     * Verifica se o uso de memória está dentro dos limites
     */
    private function checkMemoryUsage(): bool
    {
        $memoryUsage = memory_get_usage(true) / 1024 / 1024; // MB
        return $memoryUsage < self::MEMORY_LIMIT_MB;
    }

    /**
     * Força limpeza agressiva de memória
     */
    private function cleanupMemory(): void
    {
        // Limpa variáveis grandes
        if (isset($this->scanner)) {
            unset($this->scanner);
            $this->scanner = new LogicBasedScanner();
        }
        
        // NÃO limpar ckanClient pois é necessário para descobrir recursos
        // O ckanClient tem cache interno que é útil manter
        
        // Força coleta de lixo múltiplas vezes
        $this->forceGarbageCollection();
        
        // Limpa cache de arquivos temporários
        $this->cleanTempFiles();
        
        // Log do uso de memória após limpeza
        $memoryUsage = memory_get_usage(true) / 1024 / 1024;
        $peakMemory = memory_get_peak_usage(true) / 1024 / 1024;
        echo "Memória após limpeza: " . round($memoryUsage, 2) . "MB (Pico: " . round($peakMemory, 2) . "MB)\n";
        
        // Se a memória ainda estiver alta, força mais limpeza
        if ($memoryUsage > self::MEMORY_LIMIT_MB * 0.8) {
            echo "Memória alta detectada, forçando limpeza adicional...\n";
            $this->forceGarbageCollection();
            $this->cleanTempFiles();
        }
    }

    /**
     * Limpa arquivos temporários antigos e verifica espaço em disco
     */
    private function cleanTempFiles(): void
    {
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            $now = time();
            $cleanedCount = 0;
            $freedSpace = 0;
            
            foreach ($files as $file) {
                if (is_file($file)) {
                    $fileAge = $now - filemtime($file);
                    $fileSize = filesize($file);
                    
                    // Remove arquivos mais antigos que 5 minutos ou se o espaço estiver baixo
                    $freeSpace = disk_free_space($this->tempDir);
                    $shouldClean = ($fileAge > 300) || ($freeSpace < (500 * 1024 * 1024)); // 500MB
                    
                    if ($shouldClean) {
                        if (unlink($file)) {
                            $cleanedCount++;
                            $freedSpace += $fileSize;
                        }
                    }
                }
            }
            
            if ($cleanedCount > 0) {
                error_log("Limpeza de arquivos temporários: {$cleanedCount} arquivos removidos, " . 
                         round($freedSpace / 1024 / 1024, 2) . "MB liberados");
            }
        }
    }

    /**
     * Limpa arquivos temporários do diretório de cache após cada lote
     */
    private function cleanupTempFiles(): void
    {
        if (!is_dir($this->tempDir)) {
            return;
        }

        $files = glob($this->tempDir . '/*');
        $deletedCount = 0;
        
        foreach ($files as $file) {
            if (is_file($file)) {
                // Remove todos os arquivos temporários após cada lote
                if (@unlink($file)) {
                    $deletedCount++;
                }
            }
        }
        
        if ($deletedCount > 0) {
            echo "Limpeza: {$deletedCount} arquivos temporários removidos após lote\n";
        }
        
        // Força garbage collection após limpeza
        gc_collect_cycles();
    }

    /**
     * Verifica se deve parar por tempo ou memória
     */
    private function shouldStop(int $recursosProcessados, int $startTimeFromLock): bool
    {
        // Para por quantidade de recursos processados no lote
        if ($recursosProcessados >= self::RECURSOS_POR_LOTE) {
            return true;
        }

        // Para por tempo limite
        $tempoDecorrido = time() - $startTimeFromLock;
        if ($tempoDecorrido > self::TEMPO_LIMITE_EXECUCAO) {
            return true;
        }

        // Para por uso excessivo de memória
        if (!$this->checkMemoryUsage()) {
            return true;
        }

        return false;
    }

    /**
     * Lê o arquivo de lock de forma segura
     */
    private function readLockFile(string $lockFile): array
    {
        if (!file_exists($lockFile)) {
            return [];
        }
        
        $content = file_get_contents($lockFile);
        if ($content === false || empty(trim($content))) {
            return [];
        }
        
        $data = json_decode($content, true);
        return $data ?: [];
    }

    /**
     * Ponto de entrada principal para o worker.
     * Processa tudo em uma única fase para evitar problemas de transição.
     */
    public function executarAnaliseControlada(string $lockFile, string $queueFile): array
    {
        $status = $this->readLockFile($lockFile);

        // Se a fila de recursos não existe, descobre e cria.
        if (!file_exists($queueFile)) {
            $this->updateProgress(['current_step' => 'Limpando registros antigos de CPF...']);
            $this->limparRegistrosAntigos();
            
            $this->updateProgress(['current_step' => 'Descobrindo todos os recursos do portal...']);
            $this->_discoverAllResources($queueFile, $lockFile);
            // Recarrega o status após a descoberta
            $status = $this->readLockFile($lockFile);
        }
        
        // Processa todos os recursos em uma única fase
        $this->updateProgress(['current_step' => 'Processando recursos...']);
        return $this->processarTodosRecursos($queueFile, $lockFile);
    }

    private function limparRegistrosAntigos(): void
    {
        try {
            echo "🗑️  Limpando registros antigos de CPF...\n";
            
            $this->cpfRepository->limparRecursosComCpf();
            
            echo "✓ Registros antigos limpos com sucesso!\n";
            
        } catch (Exception $e) {
            error_log("Erro ao limpar registros antigos: " . $e->getMessage());
        }
    }

    /**
     * Executa análise otimizada usando o novo scanner com paralelismo
     */
    public function executarAnaliseOtimizada(): array
    {
        try {
            // Usa o OptimizedCkanScanner para processamento paralelo
            $optimizedScanner = new \App\OptimizedCkanScanner($this->verificationService->getPdo());
            
            // Define callback de progresso
            $optimizedScanner->setProgressCallback(function($data) {
                $this->updateProgress($data);
            });

            // Executa a varredura otimizada
            $resultado = $optimizedScanner->executarVarreduraOtimizada();
            
            return $resultado;
            
        } catch (Exception $e) {
            error_log("Erro na análise otimizada: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Erro durante a análise otimizada: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Varre o CKAN, obtém a lista de TODOS os recursos e salva em um arquivo de fila.
     */
    private function _discoverAllResources(string $queueFile, string $lockFile): void
    {
        $datasetIds = $this->ckanClient->getAllDatasetIds();
        $recursosParaProcessar = [];
        
        foreach ($datasetIds as $datasetId) {
            $datasetDetails = $this->ckanClient->getDatasetDetails($datasetId);
            if (empty($datasetDetails['resources'])) continue;

            $orgInfo = $datasetDetails['organization'] ?? null;
            $orgName = "Não informado";
            
            if ($orgInfo) {
                $orgName = $orgInfo['title'] ?? 
                          $orgInfo['name'] ?? 
                          $orgInfo['display_name'] ?? 
                          "Não informado";
            }

            foreach ($datasetDetails['resources'] as $resource) {
                if (!empty($resource['url']) && FileParserFactory::isFormatSupported($resource['format'] ?? '')) {
                     $recursosParaProcessar[] = [
                        'dataset_id' => $datasetId,
                        'resource_id' => $resource['id'],
                        'name' => $resource['name'],
                        'url' => $resource['url'],
                        'format' => $resource['format'],
                        'org_name' => $orgName
                    ];
                }
            }
        }

        @file_put_contents($queueFile, json_encode($recursosParaProcessar));
        
        // Atualiza o lockfile com o total a ser processado
        $status = $this->readLockFile($lockFile);
        $status['progress']['total_recursos'] = count($recursosParaProcessar);
        @file_put_contents($lockFile, json_encode($status, JSON_PRETTY_PRINT));
    }

    /**
     * Processa todos os recursos da fila em lotes otimizados.
     */
    public function processarTodosRecursos(string $queueFile, string $lockFile): array
    {
        $status = $this->readLockFile($lockFile);
        $fila = json_decode(file_get_contents($queueFile), true);

        $totalRecursos = count($fila);
        $indiceInicial = $status['progress']['recursos_processados'] ?? 0;
        
        // Atualiza o total de recursos no status se não existir
        if (!isset($status['progress']['total_recursos'])) {
            $status['progress']['total_recursos'] = $totalRecursos;
            @file_put_contents($lockFile, json_encode($status, JSON_PRETTY_PRINT));
        }
        
        if ($indiceInicial >= $totalRecursos) {
             return ['status' => 'completed', 'message' => 'Todos os recursos já foram processados.'];
        }

        // Calcula o tempo decorrido desde o início da análise
        $startTimeFromLock = isset($status['startTime']) ? strtotime($status['startTime']) : time();
        
        // Log inicial apenas
        error_log("Iniciando processamento: $totalRecursos recursos, já processados: $indiceInicial");
        
        $recursosProcessadosNesteLote = 0;
        $datasetsProcessados = [];
        
        // Processa em lotes de 30 recursos
        for ($i = $indiceInicial; $i < $totalRecursos; $i += self::BATCH_SIZE) {
            $loteAtual = min(self::BATCH_SIZE, $totalRecursos - $i);
            // Processa o lote atual
            $resultadoLote = $this->processarLoteOtimizado($fila, $i, $loteAtual, $status, $lockFile, $datasetsProcessados, $startTimeFromLock);
            
            $recursosProcessadosNesteLote += $resultadoLote['processados'];
            $datasetsProcessados = $resultadoLote['datasets'];
            $status = $resultadoLote['status'];
            
            // Log apenas a cada 100 recursos
            if (($i + $loteAtual) % 100 === 0 || ($i + $loteAtual) >= $totalRecursos) {
                error_log("Progresso: " . ($i + $loteAtual) . "/$totalRecursos recursos processados");
            }
            
            // Limpeza automática de arquivos temporários após cada lote
            if (self::CLEANUP_AFTER_BATCH) {
                $this->cleanupTempFiles();
            }
            
            // Verifica se deve parar APÓS processar o lote
            if ($this->shouldStopForMemoryOrTime($startTimeFromLock)) {
                echo "Parando por limite de tempo ou memória. Continuará na próxima execução.\n";
                break;
            }
            
            // Limpeza agressiva de memória após cada lote
            $this->cleanupMemory();
            
            // Pequena pausa para o sistema respirar
            usleep(100000); // 0.1 segundo
        }
        
        // Salva o progresso final
        @file_put_contents($lockFile, json_encode($status, JSON_PRETTY_PRINT));

        // Se terminou a fila, marca como concluído
        if ($status['progress']['recursos_processados'] >= $totalRecursos) {
            error_log("✓ Análise concluída! Total: $totalRecursos recursos processados");
            return ['status' => 'completed', 'message' => 'Análise concluída com sucesso!'];
        }

        return ['status' => 'running', 'message' => "Processados {$recursosProcessadosNesteLote} recursos. Análise continuará na próxima execução."];
    }

    /**
     * Processa um lote otimizado de recursos com limpeza de memória.
     */
    private function processarLoteOtimizado(array $fila, int $indiceInicial, int $tamanhoLote, array $status, string $lockFile, array $datasetsProcessados, int $startTimeFromLock): array
    {
        $recursosProcessados = 0;
        $totalRecursos = count($fila);
        
        for ($i = $indiceInicial; $i < $indiceInicial + $tamanhoLote && $i < $totalRecursos; $i++) {
            // Verifica se deve parar DENTRO do lote a cada 10 recursos
            if ($recursosProcessados > 0 && $recursosProcessados % 10 === 0) {
                if ($this->shouldStopForMemoryOrTime($startTimeFromLock)) {
                    break;
                }
            }
            
            $recurso = $fila[$i];
            
            try {
                // Conta datasets únicos
                if (!in_array($recurso['dataset_id'], $datasetsProcessados)) {
                    $datasetsProcessados[] = $recurso['dataset_id'];
                }
                
                // Atualiza o progresso
                $status['progress']['current_step'] = "Processando recurso ".($i + 1)."/{$totalRecursos}: {$recurso['name']}";
                $status['progress']['datasets_analisados'] = count($datasetsProcessados);
                $status['progress']['recursos_analisados'] = $i + 1;
                $this->updateProgress($status['progress']);
                
                $foundCpfs = $this->_processarRecursoIndividual($recurso);

                // Log detalhado para diagnóstico
                error_log("Recurso {$recurso['resource_id']} processado. CPFs encontrados: " . count($foundCpfs));
                if (!empty($foundCpfs)) {
                    error_log("CPFs encontrados em {$recurso['resource_id']}: " . implode(', ', $foundCpfs));
                }

                if (!empty($foundCpfs)) {
                    $metadados = [
                        'dataset_id' => $recurso['dataset_id'],
                        'resource_id' => $recurso['resource_id'],
                        'resource_name' => $recurso['name'],
                        'resource_url' => $recurso['url'],
                        'resource_format' => strtolower($recurso['format'] ?? 'unknown'),
                        'org_name' => $recurso['org_name'] ?? 'Não informado',
                    ];

                    $this->verificationService->salvarResultadoRecurso($foundCpfs, $metadados);
                    $status['progress']['recursos_com_cpfs'] = ($status['progress']['recursos_com_cpfs'] ?? 0) + 1;
                    $status['progress']['total_cpfs_salvos'] = ($status['progress']['total_cpfs_salvos'] ?? 0) + count($foundCpfs);
                }
                
                $recursosProcessados++;
                $status['progress']['recursos_processados'] = $i + 1;
                
                // Salva progresso a cada 10 recursos no lote
                if ($recursosProcessados % 10 === 0) {
                    @file_put_contents($lockFile, json_encode($status, JSON_PRETTY_PRINT));
                }
                
                // Limpeza de memória a cada 10 recursos no lote
                if ($recursosProcessados % 10 === 0) {
                    $this->cleanupMemory();
                }
                
            } catch (Exception $e) {
                // Log do erro mas continua processando
                error_log("Erro ao processar recurso {$recurso['resource_id']}: " . $e->getMessage());
                $recursosProcessados++;
                $status['progress']['recursos_processados'] = $i + 1;
            }
        }
        
        // Salva o progresso do lote
        @file_put_contents($lockFile, json_encode($status, JSON_PRETTY_PRINT));
        
        return [
            'processados' => $recursosProcessados,
            'datasets' => $datasetsProcessados,
            'status' => $status
        ];
    }

    /**
     * Verifica se deve parar apenas por memória ou tempo (sem limite de lote)
     */
    private function shouldStopForMemoryOrTime(int $startTimeFromLock): bool
    {
        // Para por tempo limite
        $tempoDecorrido = time() - $startTimeFromLock;
        echo "Tempo decorrido: {$tempoDecorrido}s / " . self::TEMPO_LIMITE_EXECUCAO . "s\n";
        
        if ($tempoDecorrido > self::TEMPO_LIMITE_EXECUCAO) {
            echo "Parando por tempo limite\n";
            return true;
        }

        // Para por uso excessivo de memória
        $memoryUsage = memory_get_usage(true) / 1024 / 1024;
        echo "Uso de memória: " . round($memoryUsage, 2) . "MB / " . self::MEMORY_LIMIT_MB . "MB\n";
        
        if (!$this->checkMemoryUsage()) {
            echo "Parando por uso excessivo de memória\n";
            return true;
        }

        return false;
    }

    /**
     * Processa um lote de recursos da fila (método antigo mantido para compatibilidade).
     */
    public function processarLoteDeRecursos(string $queueFile, string $lockFile): array
    {
        $status = $this->readLockFile($lockFile);
        $fila = json_decode(file_get_contents($queueFile), true);

        $totalRecursos = count($fila);
        $indiceInicial = $status['progress']['recursos_processados'] ?? 0;
        
        if ($indiceInicial >= $totalRecursos) {
             return ['status' => 'completed', 'message' => 'Todos os recursos já foram processados.'];
        }

        $recursosProcessadosNesteLote = 0;
        $datasetsProcessados = [];
        
        // Calcula o tempo decorrido desde o início da análise usando o startTime do lock file
        $startTimeFromLock = isset($status['startTime']) ? strtotime($status['startTime']) : time();
        
        for ($i = $indiceInicial; $i < $totalRecursos; $i++) {
            // Verifica se deve parar (tempo, memória ou quantidade)
            if ($this->shouldStop($recursosProcessadosNesteLote, $startTimeFromLock)) {
                break;
            }

            $recurso = $fila[$i];
            
            try {
                // Conta datasets únicos
                if (!in_array($recurso['dataset_id'], $datasetsProcessados)) {
                    $datasetsProcessados[] = $recurso['dataset_id'];
                }
                
                // Atualiza o progresso no lockfile
                $status['progress']['current_step'] = "Processando recurso ".($i + 1)."/{$totalRecursos}: {$recurso['name']}";
                $status['progress']['datasets_analisados'] = count($datasetsProcessados);
                $status['progress']['recursos_analisados'] = $i + 1;
                $this->updateProgress($status['progress']);
                
                $foundCpfs = $this->_processarRecursoIndividual($recurso);

                if (!empty($foundCpfs)) {
                    $metadados = [
                        'dataset_id' => $recurso['dataset_id'],
                        'resource_id' => $recurso['resource_id'],
                        'resource_name' => $recurso['name'],
                        'resource_url' => $recurso['url'],
                        'resource_format' => strtolower($recurso['format'] ?? 'unknown'),
                        'org_name' => $recurso['org_name'] ?? 'Não informado',
                    ];

                    $this->verificationService->salvarResultadoRecurso($foundCpfs, $metadados);
                    $status['progress']['recursos_com_cpfs'] = ($status['progress']['recursos_com_cpfs'] ?? 0) + 1;
                    $status['progress']['total_cpfs_salvos'] = ($status['progress']['total_cpfs_salvos'] ?? 0) + count($foundCpfs);
                }
                
                $recursosProcessadosNesteLote++;
                $status['progress']['recursos_processados'] = $i + 1;
                
                // Salva progresso a cada 10 recursos para melhor persistência
                if ($recursosProcessadosNesteLote % 10 === 0) {
                    @file_put_contents($lockFile, json_encode($status, JSON_PRETTY_PRINT));
                }
                
                // Limpeza de memória a cada 25 recursos
                if ($recursosProcessadosNesteLote % 25 === 0) {
                    $this->cleanupMemory();
                }
                
            } catch (Exception $e) {
                // Log do erro mas continua processando
                error_log("Erro ao processar recurso {$recurso['resource_id']}: " . $e->getMessage());
                $recursosProcessadosNesteLote++;
                $status['progress']['recursos_processados'] = $i + 1;
            }
        }
        
        // Salva o progresso final
        @file_put_contents($lockFile, json_encode($status, JSON_PRETTY_PRINT));

        // Se terminou a fila, marca como concluído
        if ($status['progress']['recursos_processados'] >= $totalRecursos) {
            return ['status' => 'completed', 'message' => 'Análise concluída com sucesso!'];
        }

        return ['status' => 'running', 'message' => "Lote de {$recursosProcessadosNesteLote} recursos processado. Análise continuará na próxima execução."];
    }

    /**
     * Lógica para baixar, analisar e extrair CPFs de um único recurso.
     * Versão ULTRA OTIMIZADA - Processa em memória sem salvar arquivos.
     */
    private function _processarRecursoIndividual(array $recurso): array
    {
        $foundCpfs = [];
        
        try {
            // 1. Verifica se a URL é válida
            if (empty($recurso['url']) || !filter_var($recurso['url'], FILTER_VALIDATE_URL)) {
                return [];
            }

            // 2. Baixar e processar EM MEMÓRIA (sem salvar arquivo)
            $textContent = $this->downloadAndExtractTextInMemory($recurso['url'], $recurso['format'] ?? 'unknown');
            
            if (empty($textContent)) {
                return [];
            }

            // 3. Escanear CPFs diretamente do texto
            $foundCpfs = $this->scanner->scan($textContent);
            
            // Limpar memória imediatamente
            unset($textContent);
            
            // 4. Se encontrou CPFs, salva no banco
            if (!empty($foundCpfs)) {
                $metadados = [
                    'resource_name' => $recurso['name'] ?? 'Unknown',
                    'resource_url' => $recurso['url'] ?? '',
                    'resource_format' => $recurso['format'] ?? 'unknown',
                    'file_size' => 0
                ];
                
                $this->cpfRepository->storeCpfsForResource(
                    $foundCpfs, 
                    $recurso['resource_id'], 
                    $recurso['dataset_id'] ?? 'Unknown Dataset',
                    $recurso['org_name'] ?? 'Não informado',
                    $metadados
                );
                
                // Log apenas quando encontra CPFs
                error_log("✓ {$recurso['resource_id']}: " . count($foundCpfs) . " CPFs");
            }

        } catch (Exception $e) {
            error_log("ERRO {$recurso['resource_id']}: " . $e->getMessage());
            return [];
        } finally {
            // Força coleta de lixo agressiva
            $this->forceGarbageCollection();
        }
        
        return $foundCpfs;
    }

    /**
     * Baixa e extrai texto EM MEMÓRIA sem salvar arquivo
     * ULTRA OTIMIZADO para economizar espaço em disco
     */
    private function downloadAndExtractTextInMemory(string $url, string $format): string
    {
        try {
            // Limitar tamanho do download
            $maxSize = self::MAX_FILE_SIZE_MB * 1024 * 1024;
            
            // Contexto HTTP otimizado
            $context = stream_context_create([
                'http' => [
                    'timeout' => 30,
                    'user_agent' => 'CKAN-Scanner/2.0',
                    'method' => 'GET',
                    'header' => 'Connection: close'
                ]
            ]);
            
            // Baixar conteúdo diretamente para memória
            $content = @file_get_contents($url, false, $context, 0, $maxSize);
            
            if ($content === false || empty($content)) {
                return '';
            }
            
            // Extrair texto baseado no formato
            $text = $this->extractTextFromContent($content, $format);
            
            // Limpar memória
            unset($content);
            gc_collect_cycles();
            
            return $text;
            
        } catch (Exception $e) {
            error_log("ERRO download em memória: " . $e->getMessage());
            return '';
        }
    }
    
    /**
     * Extrai texto do conteúdo baseado no formato
     */
    private function extractTextFromContent(string $content, string $format): string
    {
        $format = strtolower($format);
        
        try {
            // Para formatos de texto simples
            if (in_array($format, ['txt', 'text', 'csv', 'tsv'])) {
                return $content;
            }
            
            // Para JSON
            if ($format === 'json') {
                $data = json_decode($content, true);
                if ($data) {
                    return $this->extractTextFromArray($data);
                }
            }
            
            // Para outros formatos, salvar temporariamente
            $tempFile = sys_get_temp_dir() . '/' . uniqid('scan_') . '.' . $format;
            file_put_contents($tempFile, $content);
            
            try {
                $parser = FileParserFactory::createParserFromFile($tempFile);
                $text = $parser->getText($tempFile);
            } finally {
                @unlink($tempFile);
            }
            
            return $text;
            
        } catch (Exception $e) {
            return $content; // Fallback: retorna conteúdo bruto
        }
    }
    
    /**
     * Extrai texto recursivamente de arrays (para JSON)
     */
    private function extractTextFromArray(array $data): string
    {
        $text = '';
        
        foreach ($data as $value) {
            if (is_string($value)) {
                $text .= $value . ' ';
            } elseif (is_array($value)) {
                $text .= $this->extractTextFromArray($value);
            }
        }
        
        return $text;
    }

    /**
     * Download de arquivo com streaming otimizado para reduzir uso de memória
     */
    private function downloadFileStreaming(string $url, string $filePath): bool
    {
        try {
            // Cria contexto HTTP com timeout e headers otimizados
            $context = stream_context_create([
                'http' => [
                    'timeout' => 30,
                    'user_agent' => 'CKAN-Scanner/1.0',
                    'method' => 'GET',
                    'header' => [
                        'Connection: close',
                        'Accept-Encoding: gzip, deflate'
                    ]
                ]
            ]);
            
            // Abre o arquivo de destino para escrita
            $fileHandle = fopen($filePath, 'w');
            if (!$fileHandle) {
                return false;
            }
            
            // Abre o stream de origem
            $sourceHandle = fopen($url, 'r', false, $context);
            if (!$sourceHandle) {
                fclose($fileHandle);
                return false;
            }
            
            // Copia em chunks pequenos para reduzir uso de memória
            $chunkSize = self::CHUNK_SIZE;
            $totalBytes = 0;
            $maxBytes = self::MAX_FILE_SIZE_MB * 1024 * 1024;
            
            while (!feof($sourceHandle)) {
                $chunk = fread($sourceHandle, $chunkSize);
                if ($chunk === false) {
                    break;
                }
                
                $totalBytes += strlen($chunk);
                
                // Verifica limite de tamanho durante o download
                if ($totalBytes > $maxBytes) {
                    fclose($sourceHandle);
                    fclose($fileHandle);
                    @unlink($filePath);
                    return false;
                }
                
                fwrite($fileHandle, $chunk);
                
                // Força coleta de lixo a cada 1MB baixado
                if ($totalBytes % (1024 * 1024) === 0) {
                    if (function_exists('gc_collect_cycles')) {
                        gc_collect_cycles();
                    }
                }
            }
            
            fclose($sourceHandle);
            fclose($fileHandle);
            
            return $totalBytes > 0;
            
        } catch (Exception $e) {
            error_log("Erro no download streaming: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Processamento otimizado de arquivo com streaming
     */
    private function processFileOptimized(string $filePath): array
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        // Para arquivos CSV/TXT, usa streaming linha por linha
        if (in_array($extension, ['csv', 'txt', 'tsv'])) {
            return $this->processFileStreamingOptimized($filePath);
        }
        
        // Para outros formatos, usa parser tradicional mas com limpeza agressiva
        return $this->processFileTraditional($filePath);
    }

    /**
     * Processamento tradicional com limpeza agressiva de memória
     */
    private function processFileTraditional(string $filePath): array
    {
        $foundCpfs = [];
        
        try {
            $parser = FileParserFactory::createParserFromFile($filePath);
            $textContent = $parser->getText($filePath);
            
            if (!empty(trim($textContent))) {
                $foundCpfs = $this->scanner->scan($textContent);
                
                // Log apenas se encontrar CPFs
                if (!empty($foundCpfs)) {
                    error_log("✓ Scanner encontrou " . count($foundCpfs) . " CPFs em " . basename($filePath));
                }
            }
            
            // Limpeza imediata
            unset($textContent);
            unset($parser);
            
        } catch (Exception $e) {
            error_log("ERRO ao processar arquivo " . basename($filePath) . ": " . $e->getMessage());
        }
        
        return $foundCpfs;
    }

    /**
     * Processamento com streaming otimizado para CSV/TXT
     */
    private function processFileStreamingOptimized(string $filePath): array
    {
        $foundCpfs = [];
        $handle = fopen($filePath, 'r');
        
        if (!$handle) {
            return [];
        }

        $buffer = '';
        $chunkSize = self::CHUNK_SIZE;
        $processedBytes = 0;
        $fileSize = filesize($filePath);
        
        while (!feof($handle)) {
            $chunk = fread($handle, $chunkSize);
            if ($chunk === false) {
                break;
            }
            
            $buffer .= $chunk;
            $processedBytes += strlen($chunk);
            
            // Processa o buffer quando ele atinge um tamanho razoável
            if (strlen($buffer) > 32768) { // 32KB
                $cpfs = $this->scanner->scan($buffer);
                $foundCpfs = array_merge($foundCpfs, $cpfs);
                $buffer = ''; // Limpa o buffer
                
                // Força coleta de lixo a cada chunk
                $this->forceGarbageCollection();
            }
            
            // Log de progresso a cada 10% do arquivo
            if ($fileSize > 0 && $processedBytes % max(1, intval($fileSize / 10)) === 0) {
                $progress = round(($processedBytes / $fileSize) * 100, 1);
                echo "Processando arquivo: {$progress}% ({$processedBytes}/{$fileSize} bytes)\n";
            }
        }
        
        // Processa o buffer restante
        if (!empty($buffer)) {
            $cpfs = $this->scanner->scan($buffer);
            $foundCpfs = array_merge($foundCpfs, $cpfs);
        }
        
        fclose($handle);
        
        // Remove duplicatas e retorna
        return array_unique($foundCpfs);
    }

    /**
     * Força coleta de lixo de forma agressiva
     */
    private function forceGarbageCollection(): void
    {
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
            gc_collect_cycles(); // Dupla coleta para garantir limpeza
        }
    }

    /**
     * Verifica se o arquivo pode ser processado com streaming (CSV, TXT)
     */
    private function canProcessStreaming(string $filePath): bool
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        return in_array($extension, ['csv', 'txt', 'tsv']);
    }

    /**
     * Verifica o arquivo de lock para o status 'cancelling'.
     */
    private function isCancelled(): bool
    {
        $lockFile = $this->getLockFilePath();
        if (!file_exists($lockFile)) {
            return false;
        }
        
        $status = json_decode(file_get_contents($lockFile), true);
        return isset($status['status']) && $status['status'] === 'cancelling';
    }

    /**
     * Retorna o caminho do arquivo de lock
     */
    private function getLockFilePath(): string
    {
        return __DIR__ . '/../../cache/scan_status.json';
    }

    /**
     * Extrai conteúdo de texto de um arquivo para processamento
     */
    private function getTextContentFromFile(string $filePath): string
    {
        try {
            $parser = FileParserFactory::createParserFromFile($filePath);
            return $parser->getText($filePath);
        } catch (Exception $e) {
            error_log("Erro ao extrair texto do arquivo {$filePath}: " . $e->getMessage());
            return '';
        }
    }

    /**
     * Processa arquivo com streaming para reduzir uso de memória
     */
    private function processFileStreaming(string $filePath, $parser): array
    {
        $foundCpfs = [];
        $handle = fopen($filePath, 'r');
        
        if (!$handle) {
            return [];
        }

        $buffer = '';
        $chunkSize = 8192; // 8KB por chunk
        
        while (!feof($handle)) {
            $chunk = fread($handle, $chunkSize);
            $buffer .= $chunk;
            
            // Processa o buffer quando ele atinge um tamanho razoável
            if (strlen($buffer) > 65536) { // 64KB
                $cpfs = $this->scanner->scan($buffer);
                $foundCpfs = array_merge($foundCpfs, $cpfs);
                $buffer = ''; // Limpa o buffer
                
                // Força coleta de lixo a cada chunk
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
            }
        }
        
        // Processa o buffer restante
        if (!empty($buffer)) {
            $cpfs = $this->scanner->scan($buffer);
            $foundCpfs = array_merge($foundCpfs, $cpfs);
        }
        
        fclose($handle);
        
        // Remove duplicatas
        return array_unique($foundCpfs);
    }
}