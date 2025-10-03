<?php

namespace App;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use RuntimeException;
use DateTime;
use PDO;
use PDOException;
use Exception;

class Pine
{
    private Client $httpClient;
    private ?PDO $db = null;

    public function __construct()
    {
        $this->httpClient = new Client([
            'timeout' => defined('HTTP_TIMEOUT') ? HTTP_TIMEOUT : 30,
            'verify' => defined('HTTP_VERIFY_SSL') ? HTTP_VERIFY_SSL : false,
            'http_errors' => false
        ]);

        try {
            $dsn = sprintf(
                '%s:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                DB_CONNECTION,
                DB_HOST,
                DB_PORT,
                DB_DATABASE
            );
            
            $this->db = new PDO($dsn, DB_USERNAME, DB_PASSWORD, [
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        } catch (PDOException $e) {
            throw new RuntimeException('Erro de conexão com o banco de dados. Verifique as credenciais no arquivo de configuração.');
        }
    }

    public function analisarESalvarPortal(string $portalUrl, int $diasParaDesatualizado = 30): array
    {
        try {
            $baseUrl = rtrim($portalUrl, '/') . '/api/3/action';
            $listaDatasetsIds = $this->buscarListaDatasets($baseUrl);

            $datasetsParaSalvar = [];
            $totalDatasets = 0;
            $datasetsAtualizados = 0;
            $datasetsDesatualizados = 0;
            $agora = new DateTime();

            foreach ($listaDatasetsIds as $datasetId) {
                try {
                    $info = $this->processarDataset($baseUrl, $datasetId, $portalUrl);

                    if (!$info) {
                        continue;
                    }

                    $totalDatasets++;

                    $ultimaAtualizacaoStr = $info['last_updated'];
                    $diasDesdeAtualizacao = PHP_INT_MAX;
                    $status = 'Desatualizado';

                    if (!empty($ultimaAtualizacaoStr)) {
                        $dataAtualizacao = new DateTime($ultimaAtualizacaoStr);
                        $intervalo = $agora->diff($dataAtualizacao);
                        $diasDesdeAtualizacao = (int)$intervalo->format('%a');

                        if ($diasDesdeAtualizacao <= $diasParaDesatualizado) {
                            $status = 'Atualizado';
                            $datasetsAtualizados++;
                        } else {
                            $datasetsDesatualizados++;
                        }
                    } else {
                        $datasetsDesatualizados++;
                    }

                    $datasetsParaSalvar[] = [
                        'dataset_id' => $datasetId,
                        'name' => $info['name'],
                        'organization' => $info['organization'],
                        'last_updated' => $ultimaAtualizacaoStr ?: null,
                        'status' => $status,
                        'days_since_update' => $diasDesdeAtualizacao,
                        'resources_count' => $info['resources_count'],
                        'url' => $info['url'],
                        'portal_url' => $portalUrl
                    ];

                } catch (RuntimeException $e) {
                    // Erro silencioso para continuar processamento
                    error_log("Erro ao processar dataset {$datasetId}: " . $e->getMessage());
                }
            }
            
            $this->limparDadosAntigosDoPortal($portalUrl);

            if (!empty($datasetsParaSalvar)) {
                $this->salvarDatasetsEmLote($datasetsParaSalvar);
            }

            return [
                'success' => true,
                'message' => "Análise concluída: {$totalDatasets} datasets processados ({$datasetsAtualizados} atualizados, {$datasetsDesatualizados} desatualizados)",
                'total_datasets' => $totalDatasets,
                'updated_datasets' => $datasetsAtualizados,
                'outdated_datasets' => $datasetsDesatualizados,
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => "Erro ao analisar portal: " . $e->getMessage(),
                'total_datasets' => 0,
                'updated_datasets' => 0,
                'outdated_datasets' => 0,
            ];
        }
    }

    private function buscarListaDatasets(string $baseUrl): array
    {
        $url = "{$baseUrl}/package_list";
        $response = $this->httpClient->get($url);

        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException("Erro ao buscar lista de datasets. Status: " . $response->getStatusCode());
        }

        $data = json_decode($response->getBody()->getContents(), true);

        if (!isset($data['success']) || $data['success'] !== true || !isset($data['result'])) {
            throw new RuntimeException('A resposta da API para a lista de datasets não foi bem-sucedida.');
        }

        return $data['result'];
    }

    private function processarDataset(string $baseUrl, string $datasetId, string $portalUrl): ?array
    {
        $url = "{$baseUrl}/package_show?id={$datasetId}";
        $response = $this->httpClient->get($url);

        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException("Não foi possível obter detalhes do dataset '{$datasetId}'.");
        }

        $info = json_decode($response->getBody()->getContents(), true)['result'];

        return [
            'name' => $info['title'] ?? $datasetId,
            'organization' => $info['organization']['title'] ?? 'Não informado',
            'last_updated' => $info['metadata_modified'] ?? '',
            'resources_count' => count($info['resources'] ?? []),
            'url' => rtrim($portalUrl, '/') . '/dataset/' . $datasetId
        ];
    }
    
    private function limparDadosAntigosDoPortal(string $portalUrl): void
    {
        $sql = "DELETE FROM mpda_datasets WHERE portal_url = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$portalUrl]);
    }


    private function salvarDatasetsEmLote(array $datasets): void
    {
        $sql = "INSERT INTO mpda_datasets (dataset_id, name, organization, last_updated, status, days_since_update, resources_count, url, portal_url) VALUES ";
        
        $placeholders = [];
        $values = [];
        foreach ($datasets as $dataset) {
            $placeholders[] = '(?, ?, ?, ?, ?, ?, ?, ?, ?)';
            array_push($values,
                $dataset['dataset_id'],
                $dataset['name'],
                $dataset['organization'],
                $dataset['last_updated'],
                $dataset['status'],
                $dataset['days_since_update'],
                $dataset['resources_count'],
                $dataset['url'],
                $dataset['portal_url']
            );
        }
        
        $sql .= implode(', ', $placeholders);
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);
        } catch(PDOException $e) {
            throw new RuntimeException("Falha ao salvar os dados no banco.");
        }
    }

    public function getDatasetsPaginados(string $portalUrl, int $pagina = 1, int $porPagina = 10): array
    {
        $offset = ($pagina - 1) * $porPagina;

        $totalStmt = $this->db->prepare("SELECT COUNT(id) FROM mpda_datasets WHERE portal_url = ?");
        $totalStmt->execute([$portalUrl]);
        $totalRegistros = (int) $totalStmt->fetchColumn();

        // Pega os dados paginados
        $dataStmt = $this->db->prepare(
            "SELECT * FROM mpda_datasets WHERE portal_url = ? ORDER BY last_updated DESC LIMIT ? OFFSET ?"
        );
        $dataStmt->bindValue(1, $portalUrl, PDO::PARAM_STR);
        $dataStmt->bindValue(2, $porPagina, PDO::PARAM_INT);
        $dataStmt->bindValue(3, $offset, PDO::PARAM_INT);
        $dataStmt->execute();

        $datasets = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'datasets' => $datasets,
            'total' => $totalRegistros,
            'pagina' => $pagina,
            'por_pagina' => $porPagina,
            'total_paginas' => ceil($totalRegistros / $porPagina)
        ];
    }

    /**
     * Retorna estatísticas gerais dos datasets
     */
    public function getEstatisticasGerais(PDO $pdo): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_datasets,
                    SUM(CASE WHEN status = 'Atualizado' THEN 1 ELSE 0 END) as datasets_atualizados,
                    SUM(CASE WHEN status = 'Desatualizado' THEN 1 ELSE 0 END) as datasets_desatualizados,
                    SUM(resources_count) as total_recursos
                FROM mpda_datasets";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total_datasets' => 0,
            'datasets_atualizados' => 0,
            'datasets_desatualizados' => 0,
            'total_recursos' => 0
        ];
    }

    /**
     * Retorna lista de organizações
     */
    public function getOrganizacoes(PDO $pdo): array
    {
        $sql = "SELECT DISTINCT organization, COUNT(*) as count 
                FROM mpda_datasets 
                GROUP BY organization 
                ORDER BY count DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna último portal analisado
     */
    public function getUltimoPortalAnalisado(PDO $pdo): ?string
    {
        $sql = "SELECT portal_url FROM mpda_datasets ORDER BY id DESC LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['portal_url'] : null;
    }

    /**
     * Retorna estatísticas por portal específico
     */
    public function getEstatisticasPorPortal(PDO $pdo, string $portalUrl): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_datasets,
                    SUM(CASE WHEN status = 'Atualizado' THEN 1 ELSE 0 END) as datasets_atualizados,
                    SUM(CASE WHEN status = 'Desatualizado' THEN 1 ELSE 0 END) as datasets_desatualizados,
                    SUM(resources_count) as total_recursos
                FROM mpda_datasets 
                WHERE portal_url = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$portalUrl]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total_datasets' => 0,
            'datasets_atualizados' => 0,
            'datasets_desatualizados' => 0,
            'total_recursos' => 0
        ];
    }

    /**
     * Retorna organizações por portal específico
     */
    public function getOrganizacoesPorPortal(PDO $pdo, string $portalUrl): array
    {
        $sql = "SELECT DISTINCT organization, COUNT(*) as count 
                FROM mpda_datasets 
                WHERE portal_url = ?
                GROUP BY organization 
                ORDER BY count DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$portalUrl]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna datasets paginados com filtros
     */
    public function getDatasetsPaginadosComFiltros(
        PDO $pdo, 
        string $portalUrl, 
        int $pagina = 1, 
        int $porPagina = 15,
        string $organization = '',
        string $status = '',
        string $search = ''
    ): array {
        $offset = ($pagina - 1) * $porPagina;
        
        // Construir WHERE clause
        $whereConditions = ['portal_url = ?'];
        $params = [$portalUrl];
        
        if (!empty($organization)) {
            $whereConditions[] = 'organization = ?';
            $params[] = $organization;
        }
        
        if (!empty($status)) {
            $whereConditions[] = 'status = ?';
            $params[] = $status;
        }
        
        if (!empty($search)) {
            $whereConditions[] = '(name LIKE ? OR organization LIKE ?)';
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        // Contar total
        $totalSql = "SELECT COUNT(*) FROM mpda_datasets WHERE {$whereClause}";
        $totalStmt = $pdo->prepare($totalSql);
        $totalStmt->execute($params);
        $totalRegistros = (int) $totalStmt->fetchColumn();
        
        // Buscar dados paginados
        $dataSql = "SELECT * FROM mpda_datasets WHERE {$whereClause} ORDER BY last_updated DESC LIMIT ? OFFSET ?";
        $dataParams = array_merge($params, [$porPagina, $offset]);
        $dataStmt = $pdo->prepare($dataSql);
        $dataStmt->execute($dataParams);
        
        $datasets = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'datasets' => $datasets,
            'total' => $totalRegistros,
            'total_pages' => ceil($totalRegistros / $porPagina)
        ];
    }
}