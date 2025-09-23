<?php

namespace CpfScanner\Integration;

use PDO;
use PDOException;

/**
 * Serviço de Integração com Sistema de Verificação de CPF
 * 
 * Esta classe integra o scanner CKAN com o sistema de verificação
 * de CPF, permitindo salvar e consultar verificações no banco de dados.
 */
class CpfVerificationService
{
    private PDO $pdo;
    private string $apiBaseUrl;

    public function __construct(PDO $pdo, string $apiBaseUrl = '')
    {
        $this->pdo = $pdo;
        $this->apiBaseUrl = rtrim($apiBaseUrl, '/');
    }

    /**
     * Valida um CPF usando o algoritmo oficial brasileiro
     *
     * @param string $cpf O CPF a ser validado (pode conter formatação)
     * @return bool True se o CPF for válido, false caso contrário
     */
    public function validarCPF(string $cpf): bool
    {
        // Limpa o CPF, removendo caracteres não numéricos
        $cpf = preg_replace('/[^0-9]/is', '', $cpf);

        // Verifica se o CPF possui 11 dígitos
        if (strlen($cpf) != 11) {
            return false;
        }

        // Verifica se todos os dígitos são iguais (sequências inválidas)
        if (preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }

        // Calcula os dígitos verificadores para validar o CPF
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
     * Salva uma verificação de CPF no banco de dados
     *
     * @param string $cpf O CPF verificado (apenas dígitos)
     * @param bool $e_valido O resultado da validação
     * @param string|null $observacoes Observações opcionais
     * @param string|null $fonte Fonte da verificação (ex: "ckan_scanner")
     * @return bool True em caso de sucesso, false em caso de falha
     */
    public function salvarVerificacao(string $cpf, bool $e_valido, ?string $observacoes = null, ?string $fonte = null): bool
    {
        $observacoesCompletas = $observacoes;
        if ($fonte) {
            $observacoesCompletas = ($observacoes ? $observacoes . ' | ' : '') . "Fonte: {$fonte}";
        }

        $sql = "INSERT INTO verificacoes_cpf (cpf, e_valido, observacoes) VALUES (:cpf, :e_valido, :observacoes)
                ON DUPLICATE KEY UPDATE 
                    e_valido = VALUES(e_valido), 
                    data_verificacao = CURRENT_TIMESTAMP,
                    observacoes = VALUES(observacoes)";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':cpf' => $cpf,
                ':e_valido' => $e_valido,
                ':observacoes' => $observacoesCompletas,
            ]);
            return true;
        } catch (PDOException $e) {
            error_log("Erro ao salvar verificação de CPF: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Salva múltiplas verificações em lote de forma altamente performática.
     *
     * @param array $verificacoes Array de verificações
     * @param string|null $fonte Fonte das verificações
     * @return bool True se todas foram salvas com sucesso
     */
    public function salvarVerificacoesEmLote(array $verificacoes, ?string $fonte = null): bool
    {
        if (empty($verificacoes)) {
            return true;
        }

        // Base da query SQL. ON DUPLICATE KEY UPDATE garante que, se um CPF já existe,
        // ele será atualizado com a nova informação de validade e data.
        $sql = "INSERT INTO verificacoes_cpf (cpf, e_valido, observacoes, identificador_fonte) VALUES ";
        
        $placeholders = [];
        $bindings = [];
        
        foreach ($verificacoes as $verificacao) {
            // Adiciona placeholders para cada linha: (?, ?, ?, ?)
            $placeholders[] = '(?, ?, ?, ?)';
            
            // Adiciona os valores reais ao array de bindings
            $observacoes = $verificacao['observacoes'] ?? '';
            if ($fonte) {
                $observacoes = "Fonte: {$fonte} | " . $observacoes;
            }

            $bindings[] = $verificacao['cpf'];
            $bindings[] = $verificacao['e_valido'];
            $bindings[] = $observacoes;
            $bindings[] = $verificacao['identificador_fonte'] ?? null; // Adiciona o identificador do recurso
        }

        // Combina a base da query com os placeholders
        // Ex: INSERT INTO ... VALUES (?, ?, ?, ?), (?, ?, ?, ?), ...
        $sql .= implode(', ', $placeholders);
        
        // Adiciona a cláusula ON DUPLICATE KEY UPDATE
        $sql .= " ON DUPLICATE KEY UPDATE 
                    e_valido = VALUES(e_valido), 
                    data_verificacao = CURRENT_TIMESTAMP,
                    observacoes = VALUES(observacoes),
                    identificador_fonte = VALUES(identificador_fonte)";
        
        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($bindings); // Executa a query UMA ÚNICA VEZ com todos os dados
            $this->pdo->commit();
            return true;
        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("Erro ao salvar verificações em lote: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Processa e salva CPFs encontrados pelo scanner (modificado para incluir identificador)
     */
    public function processarCPFsEncontrados(array $cpfsEncontrados, string $fonte, array $metadados = []): array
    {
        $estatisticas = [
            'total_encontrados' => count($cpfsEncontrados),
            'validos' => 0,
            'invalidos' => 0,
            'salvos_com_sucesso' => 0,
            'erros' => 0
        ];

        $verificacoes = [];
        // Um identificador único para este recurso específico, para agrupar CPFs na UI
        $identificadorFonte = $metadados['dataset_id'] . '|' . $metadados['resource_id'];

        foreach ($cpfsEncontrados as $cpf) {
            $cpfLimpo = preg_replace('/[^0-9]/is', '', $cpf);
            
            if (strlen($cpfLimpo) === 11) {
                $e_valido = $this->validarCPF($cpfLimpo);
                
                if ($e_valido) $estatisticas['validos']++; else $estatisticas['invalidos']++;

                $verificacoes[] = [
                    'cpf' => $cpfLimpo,
                    'e_valido' => $e_valido,
                    'observacoes' => json_encode($metadados, JSON_UNESCAPED_UNICODE),
                    'identificador_fonte' => $identificadorFonte, // Passa o identificador
                ];
            }
        }

        if (!empty($verificacoes)) {
            if ($this->salvarVerificacoesEmLote($verificacoes, $fonte)) {
                $estatisticas['salvos_com_sucesso'] = count($verificacoes);
            } else {
                $estatisticas['erros'] = count($verificacoes);
            }
        }

        return $estatisticas;
    }

    /**
     * Busca verificações por fonte
     *
     * @param string $fonte Fonte das verificações
     * @param int $limite Limite de registros
     * @return array Lista de verificações
     */
    public function buscarPorFonte(string $fonte, int $limite = 100): array
    {
        $sql = "SELECT id, cpf, e_valido, data_verificacao, observacoes 
                FROM verificacoes_cpf 
                WHERE observacoes LIKE :fonte_pattern
                ORDER BY data_verificacao DESC
                LIMIT :limite";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':fonte_pattern', "%Fonte: {$fonte}%", PDO::PARAM_STR);
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Erro ao buscar verificações por fonte: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtém estatísticas das verificações por fonte
     *
     * @param string $fonte Fonte das verificações
     * @return array Estatísticas
     */
    public function obterEstatisticasPorFonte(string $fonte): array
    {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN e_valido = 1 THEN 1 ELSE 0 END) as validos,
                    SUM(CASE WHEN e_valido = 0 THEN 1 ELSE 0 END) as invalidos,
                    MIN(data_verificacao) as primeira_verificacao,
                    MAX(data_verificacao) as ultima_verificacao
                FROM verificacoes_cpf 
                WHERE observacoes LIKE :fonte_pattern";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':fonte_pattern', "%Fonte: {$fonte}%", PDO::PARAM_STR);
            $stmt->execute();
            
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
     * Verifica se um CPF já foi verificado anteriormente
     *
     * @param string $cpf CPF a ser verificado
     * @return array|null Dados da verificação anterior ou null se não encontrado
     */
    public function verificarHistorico(string $cpf): ?array
    {
        $cpfLimpo = preg_replace('/[^0-9]/is', '', $cpf);
        
        $sql = "SELECT id, cpf, e_valido, data_verificacao, observacoes 
                FROM verificacoes_cpf 
                WHERE cpf = :cpf";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':cpf' => $cpfLimpo]);
            $resultado = $stmt->fetch();
            
            return $resultado ?: null;
        } catch (PDOException $e) {
            error_log("Erro ao verificar histórico: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Chama a API REST para verificar CPF (se configurada)
     *
     * @param string $cpf CPF a ser verificado
     * @return array|null Resposta da API ou null se não configurada
     */
    public function verificarViaAPI(string $cpf): ?array
    {
        if (empty($this->apiBaseUrl)) {
            return null;
        }

        $cpfLimpo = preg_replace('/[^0-9]/is', '', $cpf);
        
        $url = $this->apiBaseUrl . '/api/cpf/verify?cpf=' . urlencode($cpfLimpo);
        
        try {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 10,
                    'header' => 'Content-Type: application/json'
                ]
            ]);
            
            $response = file_get_contents($url, false, $context);
            
            if ($response === false) {
                return null;
            }
            
            return json_decode($response, true);
        } catch (\Exception $e) {
            error_log("Erro ao chamar API de verificação: " . $e->getMessage());
            return null;
        }
    }
}
