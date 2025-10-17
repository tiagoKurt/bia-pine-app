<?php

namespace App\Cpf;

use \PDO;

class CpfRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Armazena CPFs encontrados em um recurso na tabela mpda_recursos_com_cpf.
     * @param array $cpfs - Array de CPFs encontrados (apenas strings de CPF).
     * @param string $resourceId - O identificador do recurso.
     * @param string $datasetId - O identificador do dataset.
     * @param string $orgao - O nome do órgão.
     * @param array $metadados - Metadados do recurso.
     */
    public function storeCpfsForResource(array $cpfs, string $resourceId, string $datasetId, string $orgao, array $metadados): void
    {
        if (empty($cpfs)) {
            return;
        }

        // Normalizar CPFs - garantir que sejam apenas strings
        $cpfsNormalizados = [];
        foreach ($cpfs as $cpf) {
            if (is_string($cpf)) {
                $cpfsNormalizados[] = preg_replace('/[^0-9]/', '', $cpf);
            } elseif (is_array($cpf) && isset($cpf['cpf'])) {
                $cpfsNormalizados[] = preg_replace('/[^0-9]/', '', $cpf['cpf']);
            }
        }
        
        // Remove duplicatas
        $cpfsNormalizados = array_unique($cpfsNormalizados);
        
        if (empty($cpfsNormalizados)) {
            return;
        }

        // Query que insere ou atualiza o recurso com CPFs encontrados
        $sql = "INSERT INTO mpda_recursos_com_cpf 
                (identificador_recurso, identificador_dataset, orgao, 
                 cpfs_encontrados, quantidade_cpfs, metadados_recurso, data_verificacao) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    cpfs_encontrados = VALUES(cpfs_encontrados),
                    quantidade_cpfs = VALUES(quantidade_cpfs),
                    metadados_recurso = VALUES(metadados_recurso),
                    data_verificacao = NOW()";

        $stmt = $this->pdo->prepare($sql);
        
        try {
            $stmt->execute([
                $resourceId,
                $datasetId,
                $orgao,
                json_encode($cpfsNormalizados, JSON_UNESCAPED_UNICODE),
                count($cpfsNormalizados),
                json_encode($metadados, JSON_UNESCAPED_UNICODE)
            ]);
        } catch (\PDOException $e) {
            error_log("ERRO ao inserir CPFs no banco: " . $e->getMessage());
            throw $e;
        }
    }

    public function limparRecursosComCpf(): void
    {
        try {
            $sql = "DELETE FROM mpda_recursos_com_cpf";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            
            $rowsDeleted = $stmt->rowCount();
            echo "✓ Tabela mpda_recursos_com_cpf limpa: {$rowsDeleted} registros removidos\n";
            
        } catch (\PDOException $e) {
            error_log("Erro ao limpar tabela mpda_recursos_com_cpf: " . $e->getMessage());
            throw $e;
        }
    }
}
