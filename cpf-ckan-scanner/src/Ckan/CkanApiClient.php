<?php

namespace App\Ckan;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\GuzzleException;

class CkanApiClient
{
    private Client $httpClient;
    private string $apiKey;
    private string $cacheDir;
    private int $maxRetries;

    public function __construct(string $baseUrl, string $apiKey = '', string $cacheDir = 'cache', int $maxRetries = 5)
    {
        $this->httpClient = new Client([
            'base_uri' => rtrim($baseUrl, '/') . '/api/3/action/',
            'timeout' => 30,
            'connect_timeout' => 10
        ]);
        $this->apiKey = $apiKey;
        $this->cacheDir = $cacheDir;
        $this->maxRetries = $maxRetries;
        
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    public function getAllDatasetIds(): array
    {
        $allDatasetIds = [];
        $offset = 0;
        $limit = 1000;

        while (true) {
            try {
                $response = $this->makeRequestWithRetry('package_list', [
                    'query' => [
                        'limit' => $limit,
                        'offset' => $offset
                    ]
                ]);

                $body = json_decode($response->getBody()->getContents(), true);

                if (empty($body['result'])) {
                    break;
                }

                $allDatasetIds = array_merge($allDatasetIds, $body['result']);
                $offset += $limit;

                echo "  Buscados " . count($allDatasetIds) . " datasets...\n";

            } catch (RequestException $e) {
                error_log("Erro ao buscar lista de datasets: " . $e->getMessage());
                break;
            }
        }

        return $allDatasetIds;
    }

    public function getDatasetDetails(string $datasetId): ?array
    {
        $cacheFile = $this->cacheDir . '/' . $datasetId . '.json';
        
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 3600) {
            $cachedData = json_decode(file_get_contents($cacheFile), true);
            if ($cachedData !== null) {
                return $cachedData;
            }
        }

        try {
            $response = $this->makeRequestWithRetry('package_show', [
                'query' => ['id' => $datasetId],
                'headers' => $this->getAuthHeaders()
            ]);

            $body = json_decode($response->getBody()->getContents(), true);
            $result = $body['result'] ?? null;

            if ($result !== null) {
                file_put_contents($cacheFile, json_encode($result, JSON_PRETTY_PRINT));
            }

            return $result;

        } catch (RequestException $e) {
            error_log("Erro ao buscar detalhes do dataset '{$datasetId}': " . $e->getMessage());
            return null;
        }
    }

    private function makeRequestWithRetry(string $endpoint, array $options = []): \Psr\Http\Message\ResponseInterface
    {
        $lastException = null;
        
        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                $response = $this->httpClient->get($endpoint, $options);
                
                if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                    return $response;
                }
                
                if ($response->getStatusCode() < 500) {
                    throw new RequestException(
                        "Erro HTTP " . $response->getStatusCode(),
                        $this->httpClient->getConfig('request'),
                        $response
                    );
                }
                
            } catch (GuzzleException $e) {
                $lastException = $e;
                
                if ($attempt < $this->maxRetries) {
                    $waitTime = pow(2, $attempt - 1);
                    echo "  Tentativa {$attempt} falhou. Aguardando {$waitTime}s antes de tentar novamente...\n";
                    sleep($waitTime);
                }
            }
        }
        
        throw $lastException ?? new RequestException("Falha após {$this->maxRetries} tentativas", $this->httpClient->getConfig('request'));
    }

    private function getAuthHeaders(): array
    {
        if (empty($this->apiKey)) {
            return [];
        }
        
        return [
            'Authorization' => $this->apiKey
        ];
    }

    public function clearCache(): void
    {
        $files = glob($this->cacheDir . '/*.json');
        foreach ($files as $file) {
            unlink($file);
        }
        echo "Cache limpo.\n";
    }

    public function getCacheStats(): array
    {
        $files = glob($this->cacheDir . '/*.json');
        $totalSize = 0;
        
        foreach ($files as $file) {
            $totalSize += filesize($file);
        }
        
        return [
            'files_count' => count($files),
            'total_size_bytes' => $totalSize,
            'total_size_mb' => round($totalSize / 1024 / 1024, 2)
        ];
    }
}
