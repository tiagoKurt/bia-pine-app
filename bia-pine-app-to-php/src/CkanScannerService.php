<?php

namespace App;

use CpfScanner\Ckan\CkanApiClient;
use CpfScanner\Parsing\Factory\FileParserFactory;
use CpfScanner\Scanning\Strategy\LogicBasedScanner;
use CpfScanner\Integration\CpfVerificationService;
use Exception;

class CkanScannerService
{
    private const RECURSOS_POR_LOTE = 100; // Lote otimizado para processamento eficiente
    private const TEMPO_LIMITE_EXECUCAO = 3600; // 60 minutos - tempo mais generoso
    private const MEMORY_LIMIT_MB = 512; // Limite de memória aumentado
    private const BATCH_SIZE = 100; // Tamanho do lote para processamento

    private CkanApiClient $ckanClient;
    private LogicBasedScanner $scanner;
    private CpfVerificationService $verificationService;
    private string $tempDir;
    private float $startTime;
    private $progressCallback;

    public function __construct($ckanUrl, $ckanApiKey, $cacheDir, $pdo, $maxRetries = 5)
    {
        $this->ckanClient = new CkanApiClient($ckanUrl, $ckanApiKey, $cacheDir, $maxRetries);
        $this->scanner = new LogicBasedScanner();
        $this->verificationService = new CpfVerificationService($pdo);
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
        
        // Força coleta de lixo
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
        
        // Limpa cache de arquivos temporários
        $this->cleanTempFiles();
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
            $this->updateProgress(['current_step' => 'Descobrindo todos os recursos do portal...']);
            $this->_discoverAllResources($queueFile, $lockFile);
            // Recarrega o status após a descoberta
            $status = $this->readLockFile($lockFile);
        }
        
        // Processa todos os recursos em uma única fase
        $this->updateProgress(['current_step' => 'Processando recursos...']);
        return $this->processarTodosRecursos($queueFile, $lockFile);
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

            foreach ($datasetDetails['resources'] as $resource) {
                if (!empty($resource['url']) && FileParserFactory::isFormatSupported($resource['format'] ?? '')) {
                     $recursosParaProcessar[] = [
                        'dataset_id' => $datasetId,
                        'resource_id' => $resource['id'],
                        'name' => $resource['name'],
                        'url' => $resource['url'],
                        'format' => $resource['format']
                    ];
                }
            }
        }

        file_put_contents($queueFile, json_encode($recursosParaProcessar));
        
        // Atualiza o lockfile com o total a ser processado
        $status = $this->readLockFile($lockFile);
        $status['progress']['total_recursos'] = count($recursosParaProcessar);
        file_put_contents($lockFile, json_encode($status, JSON_PRETTY_PRINT));
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
            file_put_contents($lockFile, json_encode($status, JSON_PRETTY_PRINT));
        }
        
        if ($indiceInicial >= $totalRecursos) {
             return ['status' => 'completed', 'message' => 'Todos os recursos já foram processados.'];
        }

        // Calcula o tempo decorrido desde o início da análise
        $startTimeFromLock = isset($status['startTime']) ? strtotime($status['startTime']) : time();
        
        echo "=== PROCESSAMENTO EM LOTES OTIMIZADO ===\n";
        echo "Total de recursos: $totalRecursos\n";
        echo "Recursos já processados: $indiceInicial\n";
        echo "Recursos restantes: " . ($totalRecursos - $indiceInicial) . "\n";
        echo "Tamanho do lote: " . self::BATCH_SIZE . " recursos\n\n";
        
        $recursosProcessadosNesteLote = 0;
        $datasetsProcessados = [];
        
        // Processa em lotes de 100 recursos
        for ($i = $indiceInicial; $i < $totalRecursos; $i += self::BATCH_SIZE) {
            $loteAtual = min(self::BATCH_SIZE, $totalRecursos - $i);
            echo "--- Processando lote: recursos " . ($i + 1) . " a " . ($i + $loteAtual) . " ---\n";
            
            // Processa o lote atual
            $resultadoLote = $this->processarLoteOtimizado($fila, $i, $loteAtual, $status, $lockFile, $datasetsProcessados, $startTimeFromLock);
            
            $recursosProcessadosNesteLote += $resultadoLote['processados'];
            $datasetsProcessados = $resultadoLote['datasets'];
            $status = $resultadoLote['status'];
            
            echo "Lote processado: {$resultadoLote['processados']} recursos\n";
            echo "Total processado: " . ($i + $loteAtual) . "/$totalRecursos recursos\n";
            echo "Memória atual: " . round(memory_get_usage(true) / 1024 / 1024, 2) . "MB\n\n";
            
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
        file_put_contents($lockFile, json_encode($status, JSON_PRETTY_PRINT));

        // Se terminou a fila, marca como concluído
        if ($status['progress']['recursos_processados'] >= $totalRecursos) {
            echo "=== TODOS OS RECURSOS PROCESSADOS COM SUCESSO! ===\n";
            return ['status' => 'completed', 'message' => 'Análise concluída com sucesso!'];
        }

        echo "Processamento pausado. Continuará na próxima execução.\n";
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
                    echo "Parando dentro do lote por limite de tempo ou memória.\n";
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

                if (!empty($foundCpfs)) {
                    $metadados = [
                        'dataset_id' => $recurso['dataset_id'],
                        'resource_id' => $recurso['resource_id'],
                        'resource_name' => $recurso['name'],
                        'resource_url' => $recurso['url'],
                        'resource_format' => strtolower($recurso['format'] ?? 'unknown'),
                    ];

                    $this->verificationService->salvarResultadoRecurso($foundCpfs, $metadados);
                    $status['progress']['recursos_com_cpfs'] = ($status['progress']['recursos_com_cpfs'] ?? 0) + 1;
                    $status['progress']['total_cpfs_salvos'] = ($status['progress']['total_cpfs_salvos'] ?? 0) + count($foundCpfs);
                }
                
                $recursosProcessados++;
                $status['progress']['recursos_processados'] = $i + 1;
                
                // Salva progresso a cada 5 recursos no lote (mais frequente)
                if ($recursosProcessados % 5 === 0) {
                    file_put_contents($lockFile, json_encode($status, JSON_PRETTY_PRINT));
                    echo "Checkpoint: {$recursosProcessados} recursos processados no lote\n";
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
        file_put_contents($lockFile, json_encode($status, JSON_PRETTY_PRINT));
        
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
                    ];

                    $this->verificationService->salvarResultadoRecurso($foundCpfs, $metadados);
                    $status['progress']['recursos_com_cpfs'] = ($status['progress']['recursos_com_cpfs'] ?? 0) + 1;
                    $status['progress']['total_cpfs_salvos'] = ($status['progress']['total_cpfs_salvos'] ?? 0) + count($foundCpfs);
                }
                
                $recursosProcessadosNesteLote++;
                $status['progress']['recursos_processados'] = $i + 1;
                
                // Salva progresso a cada 10 recursos para melhor persistência
                if ($recursosProcessadosNesteLote % 10 === 0) {
                    file_put_contents($lockFile, json_encode($status, JSON_PRETTY_PRINT));
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
        file_put_contents($lockFile, json_encode($status, JSON_PRETTY_PRINT));

        // Se terminou a fila, marca como concluído
        if ($status['progress']['recursos_processados'] >= $totalRecursos) {
            return ['status' => 'completed', 'message' => 'Análise concluída com sucesso!'];
        }

        return ['status' => 'running', 'message' => "Lote de {$recursosProcessadosNesteLote} recursos processado. Análise continuará na próxima execução."];
    }

    /**
     * Lógica para baixar, analisar e extrair CPFs de um único recurso.
     */
    private function _processarRecursoIndividual(array $recurso): array
    {
        $filePath = null;
        try {
            // Verifica se a URL é válida
            if (empty($recurso['url']) || !filter_var($recurso['url'], FILTER_VALIDATE_URL)) {
                return [];
            }

            // Configura timeout para download
            $context = stream_context_create([
                'http' => [
                    'timeout' => 30,
                    'user_agent' => 'CKAN-Scanner/1.0'
                ]
            ]);

            $fileContent = @file_get_contents($recurso['url'], false, $context);
            if ($fileContent === false || empty($fileContent)) {
                return [];
            }

            // Verifica se o arquivo não é muito grande (limite reduzido para 10MB)
            if (strlen($fileContent) > 10 * 1024 * 1024) {
                error_log("Arquivo muito grande ignorado: {$recurso['resource_id']} (" . round(strlen($fileContent) / 1024 / 1024, 2) . "MB)");
                return [];
            }

            // Verifica espaço em disco antes de salvar
            $freeSpace = disk_free_space($this->tempDir);
            if ($freeSpace < strlen($fileContent) + (100 * 1024 * 1024)) { // 100MB de margem
                error_log("Espaço insuficiente em disco para processar: {$recurso['resource_id']}");
                return [];
            }

            $fileName = basename(parse_url($recurso['url'], PHP_URL_PATH)) ?: $recurso['resource_id'] . '.' . ($recurso['format'] ?? 'txt');
            $filePath = $this->tempDir . '/' . $fileName;
            
            // Salva o arquivo temporário
            if (file_put_contents($filePath, $fileContent) === false) {
                error_log("Falha ao salvar arquivo temporário: {$filePath}");
                return [];
            }

            // Limpa o conteúdo da memória imediatamente
            unset($fileContent);

            try {
                $parser = FileParserFactory::createParserFromFile($filePath);
                $textContent = $parser->getText($filePath);
            } catch (Exception $e) {
                error_log("Erro ao processar arquivo: {$filePath} - " . $e->getMessage());
                $textContent = '';
            } finally {
                // Remove o arquivo temporário imediatamente após processamento
                if ($filePath && file_exists($filePath)) {
                    unlink($filePath);
                }
            }

            if (empty(trim($textContent))) {
                return [];
            }

            $result = $this->scanner->scan($textContent);
            
            // Limpa o conteúdo de texto da memória
            unset($textContent);
            
            return $result;

        } catch (Exception $e) {
            error_log("Erro ao processar recurso {$recurso['resource_id']}: " . $e->getMessage());
            if ($filePath && file_exists($filePath)) {
                unlink($filePath);
            }
            return [];
        }
    }
}