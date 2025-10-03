<?php

namespace App\Api;

use Exception;

class StatusController
{
    private string $cacheDir;

    public function __construct(string $cacheDir)
    {
        $this->cacheDir = $cacheDir;
    }

    /**
     * Retorna o status atual da análise
     */
    public function getStatus(): array
    {
        $lockFile = $this->cacheDir . '/scan_status.json';
        
        if (!file_exists($lockFile)) {
            return [
                'status' => 'not_started',
                'message' => 'Análise não iniciada',
                'progress' => []
            ];
        }

        $content = file_get_contents($lockFile);
        if ($content === false || empty(trim($content))) {
            return [
                'status' => 'error',
                'message' => 'Erro ao ler arquivo de status',
                'progress' => []
            ];
        }

        $data = json_decode($content, true);
        if (!$data) {
            return [
                'status' => 'error',
                'message' => 'Erro ao decodificar arquivo de status',
                'progress' => []
            ];
        }

        return $data;
    }

    /**
     * Inicia uma nova análise
     */
    public function startAnalysis(): array
    {
        $lockFile = $this->cacheDir . '/scan_status.json';
        
        // Remove arquivo de status anterior se existir
        if (file_exists($lockFile)) {
            unlink($lockFile);
        }

        // Cria novo arquivo de status
        $statusData = [
            'status' => 'pending',
            'startTime' => date('c'),
            'progress' => [
                'datasets_analisados' => 0,
                'recursos_analisados' => 0,
                'recursos_com_cpfs' => 0,
                'total_cpfs_salvos' => 0,
                'current_step' => 'Iniciando análise...'
            ],
            'lastUpdate' => date('c')
        ];

        if (file_put_contents($lockFile, json_encode($statusData, JSON_PRETTY_PRINT)) === false) {
            return [
                'status' => 'error',
                'message' => 'Erro ao criar arquivo de status'
            ];
        }

        return [
            'status' => 'success',
            'message' => 'Análise iniciada com sucesso'
        ];
    }

    /**
     * Para a análise atual
     */
    public function stopAnalysis(): array
    {
        $lockFile = $this->cacheDir . '/scan_status.json';
        
        if (!file_exists($lockFile)) {
            return [
                'status' => 'error',
                'message' => 'Nenhuma análise em execução'
            ];
        }

        $content = file_get_contents($lockFile);
        if ($content === false) {
            return [
                'status' => 'error',
                'message' => 'Erro ao ler arquivo de status'
            ];
        }

        $data = json_decode($content, true);
        if (!$data) {
            return [
                'status' => 'error',
                'message' => 'Erro ao decodificar arquivo de status'
            ];
        }

        $data['status'] = 'stopped';
        $data['endTime'] = date('c');
        $data['lastUpdate'] = date('c');

        if (file_put_contents($lockFile, json_encode($data, JSON_PRETTY_PRINT)) === false) {
            return [
                'status' => 'error',
                'message' => 'Erro ao atualizar arquivo de status'
            ];
        }

        return [
            'status' => 'success',
            'message' => 'Análise parada com sucesso'
        ];
    }
}
