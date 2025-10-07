<?php
require_once __DIR__ . '/../config.php';

/**
 * Valida um número de CPF usando o algoritmo oficial brasileiro.
 *
 * @param string $cpf O CPF a ser validado (pode conter formatação)
 * @return bool True se o CPF for válido, false caso contrário
 */
function validaCPF(string $cpf): bool {
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

/**
 * Formata um CPF para exibição (000.000.000-00).
 *
 * @param string $cpf O CPF sem formatação (apenas números)
 * @return string O CPF formatado
 */
function formatarCPF(string $cpf): string {
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
 * Limpa um CPF removendo formatação e mantendo apenas números.
 *
 * @param string $cpf O CPF com ou sem formatação
 * @return string O CPF apenas com números
 */
function limparCPF(string $cpf): string {
    return preg_replace('/[^0-9]/is', '', $cpf);
}

/**
 * Salva o resultado de uma verificação de CPF no banco de dados.
 *
 * @param PDO $pdo A instância da conexão PDO
 * @param string $cpf O CPF verificado (apenas dígitos)
 * @param bool $e_valido O resultado da validação
 * @param string|null $observacoes Observações opcionais
 * @param string|null $fonte Fonte da verificação (ex: 'manual', 'ckan_scanner')
 * @param string|null $identificador_fonte Identificador único da fonte
 * @return bool True em caso de sucesso, false em caso de falha
 */
function salvarVerificacaoCPF(PDO $pdo, string $cpf, bool $e_valido, ?string $observacoes = null, ?string $fonte = null, ?string $identificador_fonte = null): bool {
    $sql = "INSERT INTO mpda_verificacoes_cpf (cpf, e_valido, observacoes, fonte, identificador_fonte) VALUES (:cpf, :e_valido, :observacoes, :fonte, :identificador_fonte)
            ON DUPLICATE KEY UPDATE 
                e_valido = VALUES(e_valido), 
                data_verificacao = CURRENT_TIMESTAMP,
                observacoes = VALUES(observacoes),
                fonte = VALUES(fonte),
                identificador_fonte = VALUES(identificador_fonte)";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':cpf' => $cpf,
            ':e_valido' => $e_valido,
            ':observacoes' => $observacoes,
            ':fonte' => $fonte,
            ':identificador_fonte' => $identificador_fonte,
        ]);
        return true;
    } catch (PDOException $e) {
        error_log("Erro ao salvar verificação de CPF: " . $e->getMessage());
        return false;
    }
}

/**
 * Salva múltiplos resultados de verificação de CPF em lote usando uma transação.
 *
 * @param PDO $pdo A instância da conexão PDO
 * @param array $verificacoes Um array de arrays, cada um contendo ['cpf' => string, 'e_valido' => bool, 'observacoes' => string|null, 'fonte' => string|null, 'identificador_fonte' => string|null]
 * @return bool True se a transação for bem-sucedida, false caso contrário
 */
function salvarVerificacoesEmLote(PDO $pdo, array $verificacoes): bool {
    $sql = "INSERT INTO mpda_verificacoes_cpf (cpf, e_valido, observacoes, fonte, identificador_fonte) VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                e_valido = VALUES(e_valido), 
                data_verificacao = CURRENT_TIMESTAMP,
                observacoes = VALUES(observacoes),
                fonte = VALUES(fonte),
                identificador_fonte = VALUES(identificador_fonte)";
    
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare($sql);
        
        foreach ($verificacoes as $verificacao) {
            $stmt->execute([
                $verificacao['cpf'],
                $verificacao['e_valido'],
                $verificacao['observacoes'] ?? null,
                $verificacao['fonte'] ?? null,
                $verificacao['identificador_fonte'] ?? null
            ]);
        }
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erro ao salvar verificações em lote: " . $e->getMessage());
        return false;
    }
}

/**
 * Busca todos os registros de verificação de CPF do banco de dados.
 *
 * @param PDO $pdo A instância da conexão PDO
 * @param int|null $limite Limite de registros a retornar (opcional)
 * @param int $offset Offset para paginação (opcional)
 * @return array Uma lista de todas as verificações
 */
function buscarTodasVerificacoes(PDO $pdo, ?int $limite = null, int $offset = 0): array {
    $sql = "SELECT id, cpf, e_valido, data_verificacao, observacoes 
            FROM mpda_verificacoes_cpf 
            ORDER BY data_verificacao DESC";
    
    if ($limite !== null) {
        $sql .= " LIMIT :limite OFFSET :offset";
    }
    
    try {
        $stmt = $pdo->prepare($sql);
        
        if ($limite !== null) {
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Erro ao buscar verificações: " . $e->getMessage());
        return [];
    }
}

/**
 * Busca uma verificação específica por CPF.
 *
 * @param PDO $pdo A instância da conexão PDO
 * @param string $cpf O CPF a ser buscado
 * @return array|null Os dados da verificação ou null se não encontrado
 */
function buscarVerificacaoPorCPF(PDO $pdo, string $cpf): ?array {
    $sql = "SELECT id, cpf, e_valido, data_verificacao, observacoes 
            FROM mpda_verificacoes_cpf 
            WHERE cpf = :cpf";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':cpf' => $cpf]);
        $resultado = $stmt->fetch();
        
        return $resultado ?: null;
    } catch (PDOException $e) {
        error_log("Erro ao buscar verificação por CPF: " . $e->getMessage());
        return null;
    }
}

/**
 * Busca verificações com filtros opcionais.
 *
 * @param PDO $pdo A instância da conexão PDO
 * @param array $filtros Filtros a aplicar ['e_valido' => bool, 'data_inicio' => string, 'data_fim' => string]
 * @param int|null $limite Limite de registros
 * @param int $offset Offset para paginação
 * @return array Lista de verificações filtradas
 */
function buscarVerificacoesComFiltros(PDO $pdo, array $filtros = [], ?int $limite = null, int $offset = 0): array {
    $sql = "SELECT id, cpf, e_valido, data_verificacao, observacoes 
            FROM mpda_verificacoes_cpf 
            WHERE 1=1";
    $params = [];
    
    if (isset($filtros['e_valido']) && is_bool($filtros['e_valido'])) {
        $sql .= " AND e_valido = :e_valido";
        $params[':e_valido'] = $filtros['e_valido'];
    }
    
    if (isset($filtros['data_inicio']) && !empty($filtros['data_inicio'])) {
        $sql .= " AND data_verificacao >= :data_inicio";
        $params[':data_inicio'] = $filtros['data_inicio'];
    }
    
    if (isset($filtros['data_fim']) && !empty($filtros['data_fim'])) {
        $sql .= " AND data_verificacao <= :data_fim";
        $params[':data_fim'] = $filtros['data_fim'];
    }
    
    $sql .= " ORDER BY data_verificacao DESC";
    
    if ($limite !== null) {
        $sql .= " LIMIT :limite OFFSET :offset";
    }
    
    try {
        $stmt = $pdo->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        if ($limite !== null) {
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Erro ao buscar verificações com filtros: " . $e->getMessage());
        return [];
    }
}

/**
 * Conta o total de verificações no banco de dados.
 *
 * @param PDO $pdo A instância da conexão PDO
 * @param array $filtros Filtros opcionais
 * @return int Número total de verificações
 */
function contarVerificacoes(PDO $pdo, array $filtros = []): int {
    $sql = "SELECT COUNT(*) as total FROM mpda_verificacoes_cpf WHERE 1=1";
    $params = [];
    
    if (isset($filtros['e_valido']) && is_bool($filtros['e_valido'])) {
        $sql .= " AND e_valido = :e_valido";
        $params[':e_valido'] = $filtros['e_valido'];
    }
    
    if (isset($filtros['data_inicio']) && !empty($filtros['data_inicio'])) {
        $sql .= " AND data_verificacao >= :data_inicio";
        $params[':data_inicio'] = $filtros['data_inicio'];
    }
    
    if (isset($filtros['data_fim']) && !empty($filtros['data_fim'])) {
        $sql .= " AND data_verificacao <= :data_fim";
        $params[':data_fim'] = $filtros['data_fim'];
    }
    
    try {
        $stmt = $pdo->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        $resultado = $stmt->fetch();
        
        return (int) $resultado['total'];
    } catch (PDOException $e) {
        error_log("Erro ao contar verificações: " . $e->getMessage());
        return 0;
    }
}

/**
 * Remove uma verificação do banco de dados.
 *
 * @param PDO $pdo A instância da conexão PDO
 * @param int $id ID da verificação a ser removida
 * @return bool True se removida com sucesso, false caso contrário
 */
function removerVerificacao(PDO $pdo, int $id): bool {
    $sql = "DELETE FROM mpda_verificacoes_cpf WHERE id = :id";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Erro ao remover verificação: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtém estatísticas das verificações.
 *
 * @param PDO $pdo A instância da conexão PDO
 * @return array Estatísticas das verificações
 */
function obterEstatisticasVerificacoes(PDO $pdo): array {
    $sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN e_valido = 1 THEN 1 ELSE 0 END) as validos,
                SUM(CASE WHEN e_valido = 0 THEN 1 ELSE 0 END) as invalidos,
                MIN(data_verificacao) as primeira_verificacao,
                MAX(data_verificacao) as ultima_verificacao
            FROM mpda_verificacoes_cpf";
    
    try {
        $stmt = $pdo->query($sql);
        $resultado = $stmt->fetch();
        
        return [
            'total' => (int) $resultado['total'],
            'validos' => (int) $resultado['validos'],
            'invalidos' => (int) $resultado['invalidos'],
            'percentual_validos' => $resultado['total'] > 0 ? 
                round(($resultado['validos'] / $resultado['total']) * 100, 2) : 0,
            'primeira_verificacao' => $resultado['primeira_verificacao'],
            'ultima_verificacao' => $resultado['ultima_verificacao']
        ];
    } catch (PDOException $e) {
        error_log("Erro ao obter estatísticas: " . $e->getMessage());
        return [
            'total' => 0,
            'validos' => 0,
            'invalidos' => 0,
            'percentual_validos' => 0,
            'primeira_verificacao' => null,
            'ultima_verificacao' => null
        ];
    }
}

/**
 * Busca verificações por fonte específica.
 *
 * @param PDO $pdo A instância da conexão PDO
 * @param string $fonte Fonte das verificações
 * @param int $limite Limite de registros
 * @return array Lista de verificações da fonte
 */
function buscarVerificacoesPorFonte(PDO $pdo, string $fonte, int $limite = 100): array {
    $sql = "SELECT id, cpf, e_valido, data_verificacao, observacoes, fonte, identificador_fonte 
            FROM mpda_verificacoes_cpf 
            WHERE fonte = :fonte
            ORDER BY data_verificacao DESC
            LIMIT :limite";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':fonte', $fonte, PDO::PARAM_STR);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Erro ao buscar verificações por fonte: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtém estatísticas das verificações por fonte.
 *
 * @param PDO $pdo A instância da conexão PDO
 * @param string $fonte Fonte das verificações
 * @return array Estatísticas da fonte
 */
function obterEstatisticasPorFonte(PDO $pdo, string $fonte): array {
    $sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN e_valido = 1 THEN 1 ELSE 0 END) as validos,
                SUM(CASE WHEN e_valido = 0 THEN 1 ELSE 0 END) as invalidos,
                MIN(data_verificacao) as primeira_verificacao,
                MAX(data_verificacao) as ultima_verificacao
            FROM mpda_verificacoes_cpf 
            WHERE fonte = :fonte";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':fonte' => $fonte]);
        $resultado = $stmt->fetch();
        
        return [
            'fonte' => $fonte,
            'total' => (int) $resultado['total'],
            'validos' => (int) $resultado['validos'],
            'invalidos' => (int) $resultado['invalidos'],
            'percentual_validos' => $resultado['total'] > 0 ? 
                round(($resultado['validos'] / $resultado['total']) * 100, 2) : 0,
            'primeira_verificacao' => $resultado['primeira_verificacao'],
            'ultima_verificacao' => $resultado['ultima_verificacao']
        ];
    } catch (PDOException $e) {
        error_log("Erro ao obter estatísticas por fonte: " . $e->getMessage());
        return [
            'fonte' => $fonte,
            'total' => 0,
            'validos' => 0,
            'invalidos' => 0,
            'percentual_validos' => 0,
            'primeira_verificacao' => null,
            'ultima_verificacao' => null
        ];
    }
}

/**
 * Busca a data e os resultados da última verificação de CPF no banco de dados.
 * 
 * @param PDO $pdo A conexão com o banco de dados
 * @return array|null Retorna um array com 'lastScan' e 'lastResults' ou null se não houver registros
 */
function getLastCpfScanInfo(PDO $pdo): ?array {
    try {
        // Busca a última verificação de CPF
        $stmt = $pdo->query("SELECT MAX(data_verificacao) as lastScan FROM mpda_verificacoes_cpf");
        $lastScan = $stmt->fetchColumn();

        if (!$lastScan) {
            return null;
        }

        // Busca informações do histórico de análises
        $historyFile = __DIR__ . '/../cache/scan-history.json';
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

function getCpfFindingsPaginado(\PDO $pdo, int $pagina = 1, int $itensPorPagina = 10): array
{
    $offset = ($pagina - 1) * $itensPorPagina;

    try {
        $totalStmt = $pdo->query("SELECT COUNT(DISTINCT identificador_fonte) as total FROM mpda_verificacoes_cpf WHERE identificador_fonte IS NOT NULL AND observacoes LIKE '%Fonte: ckan_scanner%'");
        $totalResources = $totalStmt->fetchColumn() ?: 0;

        $sql = "
            SELECT 
                vc.identificador_fonte,
                COUNT(vc.id) as cpf_count,
                MAX(vc.data_verificacao) as last_checked,
                GROUP_CONCAT(vc.cpf SEPARATOR ',') as cpfs,
                SUBSTRING_INDEX(GROUP_CONCAT(vc.observacoes ORDER BY vc.data_verificacao DESC SEPARATOR '|||'), '|||', 1) as observacoes_json,
                d.name as dataset_name,
                d.url as dataset_url,
                d.organization as dataset_organization
            FROM 
                mpda_verificacoes_cpf vc
            LEFT JOIN mpda_datasets d ON SUBSTRING_INDEX(vc.identificador_fonte, '|', 1) COLLATE utf8mb4_unicode_ci = d.dataset_id COLLATE utf8mb4_unicode_ci
            WHERE 
                vc.identificador_fonte IS NOT NULL 
                AND vc.observacoes LIKE '%Fonte: ckan_scanner%'
            GROUP BY 
                vc.identificador_fonte
            ORDER BY 
                last_checked DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $itensPorPagina, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $findings = [];

        if ($results) {
            foreach ($results as $result) {
                $observacoes = $result['observacoes_json'];
                $jsonStart = strpos($observacoes, '{');
                $jsonEnd = strrpos($observacoes, '}');
                
                if ($jsonStart !== false && $jsonEnd !== false) {
                    $jsonString = substr($observacoes, $jsonStart, $jsonEnd - $jsonStart + 1);
                    $metadados = json_decode($jsonString, true);
                } else {
                    continue;
                }
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    continue;
                }

                $cpfs = explode(',', $result['cpfs']);
                $cpfsFormatados = array_map('formatarCPF', $cpfs);

                $findings[] = [
                    'dataset_id' => $metadados['dataset_id'] ?? 'Não encontrado',
                    'dataset_name' => $result['dataset_name'] ?? ($metadados['dataset_name'] ?? 'Dataset Desconhecido'),
                    'dataset_url' => $result['dataset_url'] ?? '#',
                    'dataset_organization' => $result['dataset_organization'] ?? 'Não informado',
                    'resource_id' => $metadados['resource_id'] ?? 'Não encontrado',
                    'resource_name' => $metadados['resource_name'] ?? 'Recurso Desconhecido',
                    'resource_url' => $metadados['resource_url'] ?? '#',
                    'resource_format' => $metadados['resource_format'] ?? 'N/A',
                    'cpf_count' => (int) $result['cpf_count'],
                    'cpfs' => $cpfsFormatados,
                    'last_checked' => $result['last_checked']
                ];
            }
        }

        return [
            'findings' => $findings,
            'total_resources' => (int) $totalResources,
            'total_paginas' => (int) ceil($totalResources / $itensPorPagina),
            'pagina_atual' => $pagina,
        ];

    } catch (PDOException $e) {
        error_log("Erro ao buscar dados de CPF paginados: " . $e->getMessage());
        return [
            'findings' => [],
            'total_resources' => 0,
            'total_paginas' => 1,
            'pagina_atual' => $pagina,
        ];
    }
}

/**
 * Busca dados de CPF da nova tabela otimizada mpda_recursos_com_cpf
 */
function getCpfFindingsFromNewTable(PDO $pdo): array {
    $findings = [];
    try {
        // Consulta para buscar dados da nova tabela otimizada
        $sql = "
            SELECT 
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
            ORDER BY 
                r.data_verificacao DESC
            LIMIT 100
        ";
        
        $stmt = $pdo->query($sql);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$results) {
            return [];
        }

        foreach ($results as $result) {
            $metadados = json_decode($result['metadados_recurso'], true);
            $cpfs = json_decode($result['cpfs_encontrados'], true);
            
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($cpfs)) {
                continue;
            }

            $findings[] = [
                'dataset_id' => $result['identificador_dataset'],
                'dataset_name' => $result['dataset_name'] ?? ($metadados['dataset_name'] ?? 'Dataset Desconhecido'),
                'dataset_url' => $result['dataset_url'] ?? '#',
                'dataset_organization' => $result['orgao'] ?? ($result['dataset_organization'] ?? 'Não informado'),
                'resource_id' => $result['identificador_recurso'],
                'resource_name' => $metadados['resource_name'] ?? 'Recurso Desconhecido',
                'resource_url' => $metadados['resource_url'] ?? '#',
                'resource_format' => $metadados['resource_format'] ?? 'N/A',
                'cpf_count' => (int) $result['quantidade_cpfs'],
                'cpfs' => array_map('formatarCPF', $cpfs),
                'last_checked' => $result['data_verificacao']
            ];
        }

        return $findings;

    } catch (PDOException $e) {
        error_log("Erro ao buscar dados de CPF da nova tabela: " . $e->getMessage());
        return [];
    }
}

/**
 * Busca dados de CPF paginados da nova tabela otimizada
 * Versão robusta que lida com diferentes tipos de dados JSON/LONGTEXT
 */
function getCpfFindingsPaginadoFromNewTable(PDO $pdo, int $pagina = 1, int $itensPorPagina = 10): array {
    $offset = ($pagina - 1) * $itensPorPagina;

    try {
        // Verificar se a tabela existe
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'mpda_recursos_com_cpf'");
        if ($tableCheck->rowCount() === 0) {
            error_log("Tabela mpda_recursos_com_cpf não existe");
            return [
                'findings' => [],
                'total_resources' => 0,
                'total_paginas' => 1,
                'pagina_atual' => $pagina,
            ];
        }

        $totalStmt = $pdo->query("SELECT COUNT(*) as total FROM mpda_recursos_com_cpf");
        $totalResources = $totalStmt->fetchColumn() ?: 0;

        if ($totalResources == 0) {
            error_log("Nenhum registro encontrado na tabela mpda_recursos_com_cpf");
            return [
                'findings' => [],
                'total_resources' => 0,
                'total_paginas' => 1,
                'pagina_atual' => $pagina,
            ];
        }

        // Query mais robusta que funciona com diferentes collations
        $sql = "
            SELECT 
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
            LEFT JOIN mpda_datasets d ON r.identificador_dataset = d.dataset_id
            ORDER BY 
                r.data_verificacao DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $itensPorPagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $findings = [];

        if ($results) {
            foreach ($results as $result) {
                // Decodificação robusta de JSON que funciona com LONGTEXT e JSON
                $metadados = [];
                $cpfs = [];
                
                // Tentar decodificar metadados
                if (!empty($result['metadados_recurso'])) {
                    $metadados = json_decode($result['metadados_recurso'], true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        error_log("Erro ao decodificar metadados JSON para recurso {$result['identificador_recurso']}: " . json_last_error_msg());
                        $metadados = [];
                    }
                }
                
                // Tentar decodificar CPFs
                if (!empty($result['cpfs_encontrados'])) {
                    $cpfs = json_decode($result['cpfs_encontrados'], true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        error_log("Erro ao decodificar CPFs JSON para recurso {$result['identificador_recurso']}: " . json_last_error_msg());
                        $cpfs = [];
                    }
                }
                
                // Se não conseguiu decodificar CPFs, pular este registro
                if (!is_array($cpfs) || empty($cpfs)) {
                    error_log("CPFs inválidos ou vazios para recurso {$result['identificador_recurso']}");
                    continue;
                }

                // Formatar CPFs - garantir que cada item seja string
                $cpfsFormatados = [];
                if (is_array($cpfs)) {
                    foreach ($cpfs as $cpf) {
                        if (is_string($cpf)) {
                            $cpfsFormatados[] = formatarCPF($cpf);
                        } elseif (is_array($cpf) && isset($cpf['cpf'])) {
                            $cpfsFormatados[] = formatarCPF($cpf['cpf']);
                        }
                    }
                }
                
                $findings[] = [
                    'dataset_id' => $result['identificador_dataset'] ?? 'N/A',
                    'dataset_name' => $result['dataset_name'] ?? ($metadados['dataset_name'] ?? 'Dataset Desconhecido'),
                    'dataset_url' => $result['dataset_url'] ?? '#',
                    'dataset_organization' => $result['orgao'] ?? 'Não informado',
                    'resource_id' => $result['identificador_recurso'] ?? 'N/A',
                    'resource_name' => $metadados['resource_name'] ?? 'Recurso Desconhecido',
                    'resource_url' => $metadados['resource_url'] ?? '#',
                    'resource_format' => $metadados['resource_format'] ?? 'N/A',
                    'cpf_count' => (int) $result['quantidade_cpfs'],
                    'cpfs' => $cpfsFormatados,
                    'last_checked' => $result['data_verificacao'] ?? null
                ];
            }
        }

        error_log("getCpfFindingsPaginadoFromNewTable: Retornando " . count($findings) . " findings de " . $totalResources . " recursos totais");

        return [
            'findings' => $findings,
            'total_resources' => (int) $totalResources,
            'total_paginas' => (int) ceil($totalResources / $itensPorPagina),
            'pagina_atual' => $pagina,
        ];

    } catch (PDOException $e) {
        error_log("Erro ao buscar dados de CPF paginados da nova tabela: " . $e->getMessage());
        error_log("SQL State: " . $e->getCode());
        return [
            'findings' => [],
            'total_resources' => 0,
            'total_paginas' => 1,
            'pagina_atual' => $pagina,
        ];
    } catch (Exception $e) {
        error_log("Erro geral ao buscar dados de CPF: " . $e->getMessage());
        return [
            'findings' => [],
            'total_resources' => 0,
            'total_paginas' => 1,
            'pagina_atual' => $pagina,
        ];
    }
}

/**
 * Obtém estatísticas da nova tabela otimizada
 */
function obterEstatisticasVerificacoesFromNewTable(PDO $pdo): array {
    try {
        $sql = "SELECT 
                    COUNT(*) as total_recursos,
                    SUM(quantidade_cpfs) as total_cpfs,
                    MIN(data_verificacao) as primeira_verificacao,
                    MAX(data_verificacao) as ultima_verificacao
                FROM mpda_recursos_com_cpf";
        
        $stmt = $pdo->query($sql);
        $resultado = $stmt->fetch();
        
        return [
            'total' => (int) $resultado['total_cpfs'],
            'validos' => 0, // Não temos validação na nova tabela
            'invalidos' => (int) $resultado['total_cpfs'],
            'percentual_validos' => 0,
            'primeira_verificacao' => $resultado['primeira_verificacao'],
            'ultima_verificacao' => $resultado['ultima_verificacao'],
            'total_recursos' => (int) $resultado['total_recursos']
        ];
    } catch (PDOException $e) {
        error_log("Erro ao obter estatísticas da nova tabela: " . $e->getMessage());
        return [
            'total' => 0,
            'validos' => 0,
            'invalidos' => 0,
            'percentual_validos' => 0,
            'primeira_verificacao' => null,
            'ultima_verificacao' => null,
            'total_recursos' => 0
        ];
    }
}

/**
 * Busca informações da última análise da nova tabela
 */
function getLastCpfScanInfoFromNewTable(PDO $pdo): ?array {
    try {
        $stmt = $pdo->query("SELECT MAX(data_verificacao) as lastScan FROM mpda_recursos_com_cpf");
        $lastScan = $stmt->fetchColumn();

        if (!$lastScan) {
            return null;
        }

        // Busca informações do histórico de análises
        $historyFile = __DIR__ . '/../cache/scan-history.json';
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
        error_log("Erro ao buscar informações da última varredura da nova tabela: " . $e->getMessage());
        return null;
    }
}

function getCpfFindings(PDO $pdo): array {
    $findings = [];
    try {
        // Consulta para buscar CPFs agrupados pelo identificador do recurso
        // Fazendo JOIN com a tabela datasets para obter nomes corretos e URLs
        $sql = "
            SELECT 
                vc.identificador_fonte,
                COUNT(vc.id) as cpf_count,
                MAX(vc.data_verificacao) as last_checked,
                GROUP_CONCAT(vc.cpf SEPARATOR ',') as cpfs,
                SUBSTRING_INDEX(GROUP_CONCAT(vc.observacoes ORDER BY vc.data_verificacao DESC SEPARATOR '|||'), '|||', 1) as observacoes_json,
                d.name as dataset_name,
                d.url as dataset_url,
                d.organization as dataset_organization
            FROM 
                mpda_verificacoes_cpf vc
            LEFT JOIN mpda_datasets d ON SUBSTRING_INDEX(vc.identificador_fonte, '|', 1) COLLATE utf8mb4_unicode_ci = d.dataset_id COLLATE utf8mb4_unicode_ci
            WHERE 
                vc.identificador_fonte IS NOT NULL 
                AND vc.observacoes LIKE '%Fonte: ckan_scanner%'
            GROUP BY 
                vc.identificador_fonte
            ORDER BY 
                last_checked DESC
            LIMIT 100
        ";
        
        $stmt = $pdo->query($sql);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$results) {
            return [];
        }

        foreach ($results as $result) {
            $observacoes = $result['observacoes_json'];
            $jsonStart = strpos($observacoes, '{');
            $jsonEnd = strrpos($observacoes, '}');
            
            if ($jsonStart !== false && $jsonEnd !== false) {
                $jsonString = substr($observacoes, $jsonStart, $jsonEnd - $jsonStart + 1);
                $metadados = json_decode($jsonString, true);
            } else {
                continue;
            }
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                continue;
            }

            $findings[] = [
                'dataset_id' => $metadados['dataset_id'] ?? 'Não encontrado',
                'dataset_name' => $result['dataset_name'] ?? ($metadados['dataset_name'] ?? 'Dataset Desconhecido'),
                'dataset_url' => $result['dataset_url'] ?? '#',
                'dataset_organization' => $result['dataset_organization'] ?? 'Não informado',
                'resource_id' => $metadados['resource_id'] ?? 'Não encontrado',
                'resource_name' => $metadados['resource_name'] ?? 'Recurso Desconhecido',
                'resource_url' => $metadados['resource_url'] ?? '#',
                'resource_format' => $metadados['resource_format'] ?? 'N/A',
                'cpf_count' => (int) $result['cpf_count'],
                'cpfs' => array_map('formatarCPF', explode(',', $result['cpfs'])),
                'last_checked' => $result['last_checked']
            ];
        }

        return $findings;

    } catch (PDOException $e) {
        error_log("Erro ao buscar dados de CPF: " . $e->getMessage());
        return [];
    }
}