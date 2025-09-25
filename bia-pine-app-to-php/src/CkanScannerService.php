<?php

namespace App;

use CpfScanner\Ckan\CkanApiClient;
use CpfScanner\Parsing\Factory\FileParserFactory;
use CpfScanner\Scanning\Strategy\LogicBasedScanner;
use CpfScanner\Integration\CpfVerificationService;
use PDO;
use Exception;

class CkanScannerService
{
    private $ckanClient;
    private $scanner;
    private $verificationService;
    private $tempDir;
    private $progressCallback;
    
    public function __construct($ckanUrl, $ckanApiKey, $cacheDir, $pdo, $maxRetries = 5)
    {
        $this->ckanClient = new CkanApiClient($ckanUrl, $ckanApiKey, $cacheDir, $maxRetries);
        $this->scanner = new LogicBasedScanner();
        $this->verificationService = new CpfVerificationService($pdo);
        
        $this->tempDir = sys_get_temp_dir() . '/ckan_scanner_' . uniqid();
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }
    
    public function setProgressCallback(callable $callback)
    {
        $this->progressCallback = $callback;
    }
    
    private function updateProgress($data)
    {
        if ($this->progressCallback) {
            call_user_func($this->progressCallback, $data);
        }
    }
    
    public function executeScan()
    {
        try {
            $this->updateProgress([
                'current_step' => 'Iniciando análise...',
                'datasets_analisados' => 0,
                'recursos_analisados' => 0,
                'recursos_com_cpfs' => 0,
                'total_cpfs_salvos' => 0
            ]);

            $this->updateProgress([
                'current_step' => 'Buscando lista de datasets...',
                'datasets_analisados' => 0,
                'recursos_analisados' => 0,
                'recursos_com_cpfs' => 0,
                'total_cpfs_salvos' => 0
            ]);

            $datasetIds = $this->ckanClient->getAllDatasetIds();
            $totalDatasets = count($datasetIds);

            if ($totalDatasets === 0) {
                $this->updateProgress([
                    'current_step' => 'Concluído - Nenhum dataset encontrado',
                    'datasets_analisados' => 0,
                    'recursos_analisados' => 0,
                    'recursos_com_cpfs' => 0,
                    'total_cpfs_salvos' => 0
                ]);
                return [
                    'success' => true,
                    'message' => 'Nenhum dataset encontrado no portal.',
                    'data' => [
                        'datasets_analisados' => 0,
                        'recursos_analisados' => 0,
                        'recursos_com_cpfs' => 0,
                        'total_cpfs_salvos' => 0
                    ]
                ];
            }

            $processedResources = 0;
            $totalCpfsSalvos = 0;
            $resourcesWithCpfs = 0;
            $datasetsAnalisados = 0;

            foreach ($datasetIds as $index => $datasetId) {
                $datasetsAnalisados++;
                
                $this->updateProgress([
                    'current_step' => "Analisando dataset {$datasetsAnalisados}/{$totalDatasets}: {$datasetId}",
                    'datasets_analisados' => $datasetsAnalisados,
                    'recursos_analisados' => $processedResources,
                    'recursos_com_cpfs' => $resourcesWithCpfs,
                    'total_cpfs_salvos' => $totalCpfsSalvos
                ]);

                try {
                    $datasetDetails = $this->ckanClient->getDatasetDetails($datasetId);
                    if (!$datasetDetails || empty($datasetDetails['resources'])) {
                        continue;
                    }

                    foreach ($datasetDetails['resources'] as $resource) {
                        $processedResources++;
                        
                        $this->updateProgress([
                            'current_step' => "Processando recurso {$processedResources} do dataset {$datasetId}",
                            'datasets_analisados' => $datasetsAnalisados,
                            'recursos_analisados' => $processedResources,
                            'recursos_com_cpfs' => $resourcesWithCpfs,
                            'total_cpfs_salvos' => $totalCpfsSalvos
                        ]);

                        $cpfsSalvos = $this->processResource($resource, $datasetId);
                        if ($cpfsSalvos > 0) {
                            $resourcesWithCpfs++;
                            $totalCpfsSalvos += $cpfsSalvos;
                        }
                    }
                } catch (Exception $e) {
                    continue;
                }
            }

            $this->updateProgress([
                'current_step' => 'Análise concluída com sucesso!',
                'datasets_analisados' => $datasetsAnalisados,
                'recursos_analisados' => $processedResources,
                'recursos_com_cpfs' => $resourcesWithCpfs,
                'total_cpfs_salvos' => $totalCpfsSalvos
            ]);

            return [
                'success' => true,
                'message' => 'Análise CKAN concluída com sucesso!',
                'data' => [
                    'datasets_analisados' => $datasetsAnalisados,
                    'recursos_analisados' => $processedResources,
                    'recursos_com_cpfs' => $resourcesWithCpfs,
                    'total_cpfs_salvos' => $totalCpfsSalvos
                ]
            ];

        } catch (Exception $e) {
            $this->cleanup();
            throw $e;
        }
    }
    
    /**
     * Processa um recurso individual
     */
    private function processResource($resource, $datasetId)
    {
        $resourceUrl = $resource['url'] ?? null;
        if (!$resourceUrl) {
            return 0;
        }

        $filePath = null;
        try {
            // Download do arquivo
            $fileContent = @file_get_contents($resourceUrl);
            if ($fileContent === false) {
                return 0;
            }

            $fileName = basename(parse_url($resourceUrl, PHP_URL_PATH)) ?: $resource['id'] . '.' . $resource['format'];
            $filePath = $this->tempDir . '/' . $fileName;
            file_put_contents($filePath, $fileContent);

            // Parse do arquivo
            $parser = FileParserFactory::createParserFromFile($filePath);
            $textContent = $parser->getText($filePath);

            if (empty(trim($textContent))) {
                unlink($filePath);
                return 0;
            }

            // Scan por CPFs
            $foundCpfs = $this->scanner->scan($textContent);

            if (!empty($foundCpfs)) {
                $metadados = [
                    'dataset_id' => $datasetId,
                    'resource_id' => $resource['id'],
                    'resource_name' => $resource['name'],
                    'resource_url' => $resourceUrl,
                    'resource_format' => strtolower($resource['format'] ?? 'unknown'),
                ];

                $stats = $this->verificationService->processarCPFsEncontrados($foundCpfs, 'ckan_scanner', $metadados);
                unlink($filePath);
                
                
                return $stats['salvos_com_sucesso'];
            }

            unlink($filePath);
            return 0;

        } catch (Exception $e) {
            if ($filePath && file_exists($filePath)) {
                unlink($filePath);
            }
            return 0;
        }
    }
    
    public function cleanup()
    {
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*.*');
            if ($files) {
                array_map('unlink', $files);
            }
            rmdir($this->tempDir);
        }
    }
    
    public function __destruct()
    {
        $this->cleanup();
    }
}
