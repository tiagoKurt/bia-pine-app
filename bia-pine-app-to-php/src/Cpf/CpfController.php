<?php

namespace App\Cpf;

use PDO;
use PDOException;
use Exception;

class CpfController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Busca dados de CPF paginados da tabela mpda_recursos_com_cpf
     */
    public function getCpfFindingsPaginado(int $pagina = 1, int $itensPorPagina = 10, array $filtros = []): array
    {
        $offset = ($pagina - 1) * $itensPorPagina;

        try {
            // Construir WHERE clause com filtros
            $whereConditions = ['1=1'];
            $params = [];
            
            if (!empty($filtros['orgao'])) {
                $whereConditions[] = 'r.orgao LIKE ?';
                $params[] = '%' . $filtros['orgao'] . '%';
            }
            
            if (!empty($filtros['dataset'])) {
                $whereConditions[] = '(r.identificador_dataset LIKE ? OR d.name LIKE ?)';
                $params[] = '%' . $filtros['dataset'] . '%';
                $params[] = '%' . $filtros['dataset'] . '%';
            }
            
            if (!empty($filtros['data_inicio'])) {
                $whereConditions[] = 'r.data_verificacao >= ?';
                $params[] = $filtros['data_inicio'];
            }
            
            if (!empty($filtros['data_fim'])) {
                $whereConditions[] = 'r.data_verificacao <= ?';
                $params[] = $filtros['data_fim'] . ' 23:59:59';
            }
            
            $whereClause = implode(' AND ', $whereConditions);

            // Contar total de registros
            $totalSql = "SELECT COUNT(*) as total FROM mpda_recursos_com_cpf r WHERE {$whereClause}";
            $totalStmt = $this->pdo->prepare($totalSql);
            $totalStmt->execute($params);
            $totalRegistros = (int) $totalStmt->fetchColumn();

            // Buscar dados paginados
            $sql = "
                SELECT 
                    r.id,
                    r.identificador_recurso,
                    r.identificador_dataset,
                    r.orgao,
                    r.cpfs_encontrados,
                    r.quantidade_cpfs,
                    r.metadados_recurso,
                    r.data_verificacao,
                    d.name as dataset_name,
                    d.url as dataset_url,
                    d.organization as dataset_organization
                FROM 
                    mpda_recursos_com_cpf r
                LEFT JOIN mpda_datasets d ON r.identificador_dataset COLLATE utf8mb4_unicode_ci = d.dataset_id COLLATE utf8mb4_unicode_ci
                WHERE {$whereClause}
                ORDER BY 
                    r.data_verificacao DESC
                LIMIT ? OFFSET ?
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_merge($params, [$itensPorPagina, $offset]));
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $findings = [];

            if ($results) {
                foreach ($results as $result) {
                    $metadados = json_decode($result['metadados_recurso'], true);
                    $cpfs = json_decode($result['cpfs_encontrados'], true);
                    
                    if (json_last_error() !== JSON_ERROR_NONE || !is_array($cpfs)) {
                        continue;
                    }

                    // Formatar CPFs - garantir que cada item seja string
                    $cpfsFormatados = [];
                    if (is_array($cpfs)) {
                        foreach ($cpfs as $cpf) {
                            if (is_string($cpf)) {
                                $cpfsFormatados[] = $this->formatarCPF($cpf);
                            } elseif (is_array($cpf) && isset($cpf['cpf'])) {
                                $cpfsFormatados[] = $this->formatarCPF($cpf['cpf']);
                            }
                        }
                    }
                    
                    $findings[] = [
                        'id' => (int) $result['id'],
                        'dataset_id' => $result['identificador_dataset'],
                        'dataset_name' => $result['dataset_name'] ?? ($metadados['dataset_name'] ?? 'Dataset Desconhecido'),
                        'dataset_url' => $result['dataset_url'] ?? '#',
                        'dataset_organization' => $result['orgao'] ?? 'Não informado',
                        'resource_id' => $result['identificador_recurso'],
                        'resource_name' => $metadados['resource_name'] ?? 'Recurso Desconhecido',
                        'resource_url' => $metadados['resource_url'] ?? '#',
                        'resource_format' => $metadados['resource_format'] ?? 'N/A',
                        'cpf_count' => (int) $result['quantidade_cpfs'],
                        'cpfs' => $cpfsFormatados,
                        'last_checked' => $result['data_verificacao']
                    ];
                }
            }

            return [
                'success' => true,
                'findings' => $findings,
                'total' => $totalRegistros,
                'page' => $pagina,
                'per_page' => $itensPorPagina,
                'total_pages' => ceil($totalRegistros / $itensPorPagina),
                'has_next' => $pagina < ceil($totalRegistros / $itensPorPagina),
                'has_prev' => $pagina > 1
            ];

        } catch (PDOException $e) {
            error_log("Erro ao buscar dados de CPF paginados: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao buscar dados de CPF',
                'findings' => [],
                'total' => 0,
                'page' => $pagina,
                'per_page' => $itensPorPagina,
                'total_pages' => 1,
                'has_next' => false,
                'has_prev' => false
            ];
        }
    }

    /**
     * Obtém estatísticas dos CPFs encontrados
     */
    public function getEstatisticas(): array
    {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_recursos,
                        SUM(quantidade_cpfs) as total_cpfs,
                        COUNT(DISTINCT orgao) as total_orgaos,
                        COUNT(DISTINCT identificador_dataset) as total_datasets,
                        MIN(data_verificacao) as primeira_verificacao,
                        MAX(data_verificacao) as ultima_verificacao,
                        AVG(quantidade_cpfs) as media_cpfs_por_recurso
                    FROM mpda_recursos_com_cpf";
            
            $stmt = $this->pdo->query($sql);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Estatísticas por órgão
            $sqlOrgaos = "SELECT 
                            orgao,
                            COUNT(*) as recursos,
                            SUM(quantidade_cpfs) as cpfs
                          FROM mpda_recursos_com_cpf 
                          GROUP BY orgao 
                          ORDER BY cpfs DESC 
                          LIMIT 10";
            
            $stmtOrgaos = $this->pdo->query($sqlOrgaos);
            $estatisticasOrgaos = $stmtOrgaos->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'stats' => [
                    'total_recursos' => (int) $resultado['total_recursos'],
                    'total_cpfs' => (int) $resultado['total_cpfs'],
                    'total_orgaos' => (int) $resultado['total_orgaos'],
                    'total_datasets' => (int) $resultado['total_datasets'],
                    'media_cpfs_por_recurso' => round((float) $resultado['media_cpfs_por_recurso'], 2),
                    'primeira_verificacao' => $resultado['primeira_verificacao'],
                    'ultima_verificacao' => $resultado['ultima_verificacao'],
                    'top_orgaos' => $estatisticasOrgaos
                ]
            ];
        } catch (PDOException $e) {
            error_log("Erro ao obter estatísticas de CPF: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao obter estatísticas',
                'stats' => [
                    'total_recursos' => 0,
                    'total_cpfs' => 0,
                    'total_orgaos' => 0,
                    'total_datasets' => 0,
                    'media_cpfs_por_recurso' => 0,
                    'primeira_verificacao' => null,
                    'ultima_verificacao' => null,
                    'top_orgaos' => []
                ]
            ];
        }
    }

    /**
     * Busca informações da última análise
     */
    public function getLastScanInfo(): ?array
    {
        try {
            $stmt = $this->pdo->query("SELECT MAX(data_verificacao) as lastScan FROM mpda_recursos_com_cpf");
            $lastScan = $stmt->fetchColumn();

            if (!$lastScan) {
                return null;
            }

            // Busca informações do histórico de análises
            $historyFile = __DIR__ . '/../../cache/scan-history.json';
            $lastResults = null;
            
            if (file_exists($historyFile)) {
                $history = json_decode(file_get_contents($historyFile), true);
                $lastResults = $history['lastResults'] ?? null;
            }
            
            return [
                'lastScan' => $lastScan,
                'lastResults' => $lastResults
            ];

        } catch (PDOException $e) {
            error_log("Erro ao buscar informações da última varredura: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Busca detalhes de um recurso específico
     */
    public function getRecursoDetalhes(int $id): array
    {
        try {
            $sql = "
                SELECT 
                    r.*,
                    d.name as dataset_name,
                    d.url as dataset_url,
                    d.organization as dataset_organization
                FROM 
                    mpda_recursos_com_cpf r
                LEFT JOIN mpda_datasets d ON r.identificador_dataset COLLATE utf8mb4_unicode_ci = d.dataset_id COLLATE utf8mb4_unicode_ci
                WHERE r.id = ?
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                return [
                    'success' => false,
                    'message' => 'Recurso não encontrado'
                ];
            }

            $metadados = json_decode($result['metadados_recurso'], true);
            $cpfs = json_decode($result['cpfs_encontrados'], true);
            
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($cpfs)) {
                $cpfs = [];
            }

            // Formatar CPFs
            $cpfsFormatados = [];
            if (is_array($cpfs)) {
                foreach ($cpfs as $cpf) {
                    if (is_string($cpf)) {
                        $cpfsFormatados[] = $this->formatarCPF($cpf);
                    } elseif (is_array($cpf) && isset($cpf['cpf'])) {
                        $cpfsFormatados[] = $this->formatarCPF($cpf['cpf']);
                    }
                }
            }

            return [
                'success' => true,
                'recurso' => [
                    'id' => (int) $result['id'],
                    'dataset_id' => $result['identificador_dataset'],
                    'dataset_name' => $result['dataset_name'] ?? ($metadados['dataset_name'] ?? 'Dataset Desconhecido'),
                    'dataset_url' => $result['dataset_url'] ?? '#',
                    'dataset_organization' => $result['orgao'],
                    'resource_id' => $result['identificador_recurso'],
                    'resource_name' => $metadados['resource_name'] ?? 'Recurso Desconhecido',
                    'resource_url' => $metadados['resource_url'] ?? '#',
                    'resource_format' => $metadados['resource_format'] ?? 'N/A',
                    'cpf_count' => (int) $result['quantidade_cpfs'],
                    'cpfs' => $cpfsFormatados,
                    'metadados' => $metadados,
                    'data_verificacao' => $result['data_verificacao']
                ]
            ];

        } catch (PDOException $e) {
            error_log("Erro ao buscar detalhes do recurso: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao buscar detalhes do recurso'
            ];
        }
    }

    /**
     * Busca lista de órgãos únicos para filtros
     */
    public function getOrgaos(): array
    {
        try {
            $sql = "SELECT DISTINCT orgao, COUNT(*) as count 
                    FROM mpda_recursos_com_cpf 
                    WHERE orgao IS NOT NULL AND orgao != ''
                    GROUP BY orgao 
                    ORDER BY count DESC";
            
            $stmt = $this->pdo->query($sql);
            $orgaos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'orgaos' => $orgaos
            ];
        } catch (PDOException $e) {
            error_log("Erro ao buscar órgãos: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao buscar órgãos',
                'orgaos' => []
            ];
        }
    }

    /**
     * Formata um CPF para exibição (000.000.000-00)
     */
    private function formatarCPF(string $cpf): string
    {
        $cpf = preg_replace('/[^0-9]/is', '', $cpf);
        
        if (strlen($cpf) != 11) {
            return $cpf;
        }
        
        return substr($cpf, 0, 3) . '.' . 
               substr($cpf, 3, 3) . '.' . 
               substr($cpf, 6, 3) . '-' . 
               substr($cpf, 9, 2);
    }

    /**
     * Valida um número de CPF usando o algoritmo oficial brasileiro
     */
    private function validaCPF(string $cpf): bool
    {
        $cpf = preg_replace('/[^0-9]/is', '', $cpf);

        if (strlen($cpf) != 11) {
            return false;
        }

        if (preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            for ($d = 0, $c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) {
                return false;
            }
        }

        return true;
    }
}