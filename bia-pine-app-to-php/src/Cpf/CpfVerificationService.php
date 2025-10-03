<?php
// src/Cpf/CpfVerificationService.php

namespace App\Cpf;

use Exception;
use PDO;
use PDOException;

class CpfVerificationService
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Retorna a instância PDO para uso externo
     */
    public function getPdo()
    {
        return $this->pdo;
    }

    /**
     * Valida um CPF usando o algoritmo oficial brasileiro.
     * Esta função pode ser mantida para validação interna, se necessário.
     */
    public function validarCPF($cpf)
    {
        $cpf = preg_replace('/[^0-9]/is', '', $cpf);
        if (strlen($cpf) != 11 || preg_match('/(\d)\1{10}/', $cpf)) {
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
     * Salva o resultado consolidado de uma análise de recurso no banco de dados.
     *
     * @param array $cpfsEncontrados Lista de CPFs (strings de 11 dígitos).
     * @param array $metadados       Metadados do recurso (dataset_id, resource_id, etc.).
     * @return bool                  True em caso de sucesso, false em caso de falha.
     */
    public function salvarResultadoRecurso($cpfsEncontrados, $metadados)
    {
        if (empty($cpfsEncontrados) || empty($metadados['resource_id'])) {
            return false; // Não há nada para salvar ou falta o ID do recurso
        }

        $sql = "
            INSERT INTO mpda_recursos_com_cpf (
                identificador_recurso, 
                identificador_dataset, 
                orgao,
                cpfs_encontrados, 
                quantidade_cpfs, 
                metadados_recurso
            ) VALUES (
                :resource_id, 
                :dataset_id, 
                :orgao,
                :cpfs, 
                :count, 
                :metadata
            )
            ON DUPLICATE KEY UPDATE
                orgao = VALUES(orgao),
                cpfs_encontrados = VALUES(cpfs_encontrados),
                quantidade_cpfs = VALUES(quantidade_cpfs),
                metadados_recurso = VALUES(metadados_recurso),
                data_verificacao = CURRENT_TIMESTAMP
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':resource_id' => $metadados['resource_id'],
                ':dataset_id' => $metadados['dataset_id'],
                ':orgao' => $metadados['org_name'] ?? 'Não informado',
                ':cpfs' => json_encode($cpfsEncontrados),
                ':count' => count($cpfsEncontrados),
                ':metadata' => json_encode($metadados, JSON_UNESCAPED_UNICODE)
            ]);
            return true;
        } catch (PDOException $e) {
            error_log("Erro ao salvar resultado do recurso: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Processa CPFs encontrados e salva no banco de dados
     * 
     * @param array $foundCpfs Lista de CPFs encontrados
     * @param string $source Fonte da verificação (ex: 'ckan_scanner')
     * @param array $metadados Metadados do recurso
     * @return array Estatísticas do processamento
     */
    public function processarCPFsEncontrados($foundCpfs, $source, $metadados)
    {
        $stats = [
            'total_encontrados' => count($foundCpfs),
            'salvos_com_sucesso' => 0,
            'erros' => 0
        ];

        if (empty($foundCpfs)) {
            return $stats;
        }

        try {
            // Valida CPFs antes de salvar
            $cpfsValidos = [];
            foreach ($foundCpfs as $cpf) {
                if ($this->validarCPF($cpf)) {
                    $cpfsValidos[] = $cpf;
                }
            }

            if (empty($cpfsValidos)) {
                error_log("[AVISO] Nenhum CPF válido encontrado para o recurso: " . ($metadados['resource_id'] ?? 'unknown'));
                return $stats;
            }

            // Salva no banco de dados
            $sucesso = $this->salvarResultadoRecurso($cpfsValidos, $metadados);
            
            if ($sucesso) {
                $stats['salvos_com_sucesso'] = count($cpfsValidos);
                error_log("[SUCESSO] {$stats['salvos_com_sucesso']} CPFs salvos para o recurso: " . ($metadados['resource_id'] ?? 'unknown'));
            } else {
                $stats['erros'] = count($cpfsValidos);
                error_log("[ERRO] Falha ao salvar CPFs para o recurso: " . ($metadados['resource_id'] ?? 'unknown'));
            }

        } catch (Exception $e) {
            $stats['erros'] = count($foundCpfs);
            error_log("[ERRO] Exceção ao processar CPFs: " . $e->getMessage());
        }

        return $stats;
    }
}