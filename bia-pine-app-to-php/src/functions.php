<?php
/**
 * Funções de validação e manipulação de CPF
 * 
 * Este arquivo contém todas as funções relacionadas à validação de CPF
 * e operações de banco de dados para o sistema de verificação.
 */

require_once __DIR__ . '/../config.php';

/**
 * Valida um número de CPF usando o algoritmo oficial brasileiro.
 *
 * @param string $cpf O CPF a ser validado (pode conter formatação)
 * @return bool True se o CPF for válido, false caso contrário
 */
function validaCPF(string $cpf): bool {
    // 1. Limpa o CPF, removendo caracteres não numéricos
    $cpf = preg_replace('/[^0-9]/is', '', $cpf);

    // 2. Verifica se o CPF possui 11 dígitos
    if (strlen($cpf) != 11) {
        return false;
    }

    // 3. Verifica se todos os dígitos são iguais (sequências inválidas)
    if (preg_match('/(\d)\1{10}/', $cpf)) {
        return false;
    }

    // 4. Calcula os dígitos verificadores para validar o CPF
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
        return $cpf; // Retorna sem formatação se não tiver 11 dígitos
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
    $sql = "INSERT INTO verificacoes_cpf (cpf, e_valido, observacoes, fonte, identificador_fonte) VALUES (:cpf, :e_valido, :observacoes, :fonte, :identificador_fonte)
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
    $sql = "INSERT INTO verificacoes_cpf (cpf, e_valido, observacoes, fonte, identificador_fonte) VALUES (?, ?, ?, ?, ?)
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
            FROM verificacoes_cpf 
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
            FROM verificacoes_cpf 
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
            FROM verificacoes_cpf 
            WHERE 1=1";
    $params = [];
    
    // Filtro por status de validação
    if (isset($filtros['e_valido']) && is_bool($filtros['e_valido'])) {
        $sql .= " AND e_valido = :e_valido";
        $params[':e_valido'] = $filtros['e_valido'];
    }
    
    // Filtro por data de início
    if (isset($filtros['data_inicio']) && !empty($filtros['data_inicio'])) {
        $sql .= " AND data_verificacao >= :data_inicio";
        $params[':data_inicio'] = $filtros['data_inicio'];
    }
    
    // Filtro por data de fim
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
    $sql = "SELECT COUNT(*) as total FROM verificacoes_cpf WHERE 1=1";
    $params = [];
    
    // Aplicar os mesmos filtros da função buscarVerificacoesComFiltros
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
    $sql = "DELETE FROM verificacoes_cpf WHERE id = :id";
    
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
            FROM verificacoes_cpf";
    
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
            FROM verificacoes_cpf 
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
            FROM verificacoes_cpf 
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
        $stmt = $pdo->query("SELECT MAX(data_verificacao) as lastScan FROM verificacoes_cpf");
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

/**
 * Busca e agrupa os resultados de CPFs encontrados no banco de dados.
 * Esta função carrega os dados para exibição na aba CPF.
 * 
 * @param PDO $pdo A conexão com o banco de dados
 * @return array Um array estruturado com os resultados
 */
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
                verificacoes_cpf vc
            LEFT JOIN datasets d ON SUBSTRING_INDEX(vc.identificador_fonte, '|', 1) COLLATE utf8mb4_unicode_ci = d.dataset_id COLLATE utf8mb4_unicode_ci
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

        // Processa os resultados e organiza no formato esperado pela view
        foreach ($results as $result) {
            // Extrair JSON das observações
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