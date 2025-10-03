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
     * @param array $cpfs - Array de CPFs encontrados.
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
                json_encode($cpfs),
                count($cpfs),
                json_encode($metadados)
            ]);
        } catch (\PDOException $e) {
            error_log("Erro durante a inserção de CPFs: " . $e->getMessage());
            throw $e;
        }
    }
}
