<?php
// src/Integration/CpfVerificationService.php

namespace CpfScanner\Integration;

use PDO;
use PDOException;

class CpfVerificationService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Valida um CPF usando o algoritmo oficial brasileiro.
     * Esta função pode ser mantida para validação interna, se necessário.
     */
    public function validarCPF(string $cpf): bool
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
    public function salvarResultadoRecurso(array $cpfsEncontrados, array $metadados): bool
    {
        if (empty($cpfsEncontrados) || empty($metadados['resource_id'])) {
            return false; // Não há nada para salvar ou falta o ID do recurso
        }

        $sql = "
            INSERT INTO mpda_recursos_com_cpf (
                identificador_recurso, 
                identificador_dataset, 
                cpfs_encontrados, 
                quantidade_cpfs, 
                metadados_recurso
            ) VALUES (
                :resource_id, 
                :dataset_id, 
                :cpfs, 
                :count, 
                :metadata
            )
            ON DUPLICATE KEY UPDATE
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
}