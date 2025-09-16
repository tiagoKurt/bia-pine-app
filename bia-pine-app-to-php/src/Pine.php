<?php

namespace App;

use GuzzleHttp\Client;
use Google\Client as GoogleClient;
use Google\Service\Sheets;
use Google\Service\Sheets\ValueRange;

class Pine
{
    private Client $httpClient;
    private GoogleClient $googleClient;
    private Sheets $sheetsService;
    
    private const SHEET_NAME = "RELATORIO";
    private const WORKSHEET_NAME = "Página1";
    
    public function __construct()
    {
        $this->httpClient = new Client([
            'timeout' => defined('HTTP_TIMEOUT') ? HTTP_TIMEOUT : 30,
            'verify' => defined('HTTP_VERIFY_SSL') ? HTTP_VERIFY_SSL : false
        ]);
        
        $this->inicializarGoogleClient();
    }
    
    private function inicializarGoogleClient(): void
    {
        $this->googleClient = new GoogleClient();
        $this->googleClient->setApplicationName('BIA-PINE-App');
        $this->googleClient->setScopes([
            'https://www.googleapis.com/auth/spreadsheets',
            'https://www.googleapis.com/auth/drive'
        ]);
        
        $credenciaisJson = defined('GOOGLE_CREDENTIALS_JSON') ? GOOGLE_CREDENTIALS_JSON : null;
        if (!$credenciaisJson) {
            throw new \RuntimeException('Variável de ambiente GOOGLE_CREDENTIALS_JSON não configurada.');
        }
        
        $credenciais = json_decode($credenciaisJson, true);
        if (!$credenciais) {
            throw new \RuntimeException('Erro ao decodificar credenciais Google.');
        }
        
        $this->googleClient->setAuthConfig($credenciais);
        $this->sheetsService = new Sheets($this->googleClient);
    }
    
    public function atualizarPlanilha(string $portalUrl, bool $verificarUrls): array
    {
        try {
            $baseUrl = rtrim($portalUrl, '/') . '/api/3/action';
            $listaDatasets = $this->buscarListaDatasets($baseUrl);
            
            $dadosFinais = [];
            $total = count($listaDatasets);
            
            foreach ($listaDatasets as $index => $datasetId) {
                try {
                    $dadosDataset = $this->processarDataset($baseUrl, $datasetId, $portalUrl, $verificarUrls);
                    if ($dadosDataset) {
                        $dadosFinais[] = $dadosDataset;
                    }
                } catch (\Exception $e) {
                    error_log("⚠️ Erro ao processar dataset {$datasetId}: " . $e->getMessage());
                }
                
                if (($index + 1) % 10 === 0) {
                    error_log("Processados " . strval($index + 1) . " de " . strval($total) . " datasets");
                }
            }
            
            $this->atualizarPlanilhaGoogle($dadosFinais);
            
            return [
                'sucesso' => true,
                'mensagem' => 'Os dados foram extraídos com sucesso!'
            ];
            
        } catch (\Exception $e) {
            return [
                'sucesso' => false,
                'mensagem' => 'Erro ao atualizar planilha: ' . $e->getMessage()
            ];
        }
    }
    
    private function buscarListaDatasets(string $baseUrl): array
    {
        $url = "{$baseUrl}/package_list";
        $response = $this->httpClient->get($url);
        $data = json_decode($response->getBody()->getContents(), true);
        
        if (!isset($data['result'])) {
            throw new \RuntimeException('Erro ao buscar lista de datasets');
        }
        
        return $data['result'];
    }
    
    private function processarDataset(string $baseUrl, string $datasetId, string $portalUrl, bool $verificarUrls): ?array
    {
        $url = "{$baseUrl}/package_show?id={$datasetId}";
        $response = $this->httpClient->get($url);
        $info = json_decode($response->getBody()->getContents(), true)['result'];
        
        $nomeBase = $info['title'] ?? $datasetId;
        $orgao = $info['organization']['title'] ?? 'Não informado';
        $ultimaAtualizacao = $info['metadata_modified'] ?? '';
        $dataCriacao = $info['metadata_created'] ?? '';
        
        $linkBase = rtrim($portalUrl, '/') . '/dataset/' . $datasetId;
        
        $resources = $info['resources'] ?? [];
        $qtdTotal = count($resources);
        
        $contadores = $this->contarTiposRecursos($resources);
        
        $qtdErro = 0;
        if ($verificarUrls) {
            $qtdErro = $this->verificarUrlsRecursos($resources);
        }
        
        return [
            'Nome_da_Base' => $nomeBase,
            'Orgao' => $orgao,
            'Ultima_Atualizacao' => $ultimaAtualizacao,
            'Data_Criacao' => $dataCriacao,
            'Link_Base' => $linkBase,
            'Quantidade_de_Recursos' => $qtdTotal,
            'Quantidade_CSV' => $contadores['csv'],
            'Quantidade_XLSX' => $contadores['xlsx'],
            'Quantidade_PDF' => $contadores['pdf'],
            'Quantidade_JSON' => $contadores['json'],
            'Quantidade_ErroLeitura' => $qtdErro
        ];
    }
    
    private function contarTiposRecursos(array $resources): array
    {
        $contadores = [
            'csv' => 0,
            'xlsx' => 0,
            'pdf' => 0,
            'json' => 0
        ];
        
        foreach ($resources as $res) {
            $formato = strtolower($res['format'] ?? '');
            if (isset($contadores[$formato])) {
                $contadores[$formato]++;
            }
        }
        
        return $contadores;
    }
    
    private function verificarUrlsRecursos(array $resources): int
    {
        $qtdErro = 0;
        
        foreach ($resources as $res) {
            $url = $res['url'] ?? '';
            if (empty($url)) {
                $qtdErro++;
                continue;
            }
            
            try {
                $response = $this->httpClient->head($url, ['timeout' => 5]);
                if ($response->getStatusCode() !== 200) {
                    $qtdErro++;
                }
            } catch (\Exception $e) {
                $qtdErro++;
            }
        }
        
        return $qtdErro;
    }
    
    private function atualizarPlanilhaGoogle(array $dadosFinais): void
    {
        if (empty($dadosFinais)) {
            throw new \RuntimeException('Nenhum dado para atualizar');
        }
        
        $dadosFormatados = $this->formatarDatas($dadosFinais);
        
        $headers = array_keys($dadosFormatados[0]);
        $values = array_map(function($row) {
            return array_values($row);
        }, $dadosFormatados);
        
        array_unshift($values, $headers);
        
        $range = self::WORKSHEET_NAME . '!A1';
        $valueRange = new ValueRange([
            'values' => $values
        ]);
        
        $spreadsheetId = $this->obterSpreadsheetId();
        $this->sheetsService->spreadsheets_values->clear($spreadsheetId, self::WORKSHEET_NAME . '!A:Z', new \Google\Service\Sheets\ClearValuesRequest());
        $this->sheetsService->spreadsheets_values->update(
            $spreadsheetId,
            $range,
            $valueRange,
            ['valueInputOption' => 'RAW']
        );
    }
    
    private function formatarDatas(array $dados): array
    {
        foreach ($dados as &$row) {
            if (!empty($row['Ultima_Atualizacao'])) {
                $data = new \DateTime($row['Ultima_Atualizacao']);
                $row['Ultima_Atualizacao'] = $data->format('d/m/Y');
            }
            
            if (!empty($row['Data_Criacao'])) {
                $data = new \DateTime($row['Data_Criacao']);
                $row['Data_Criacao'] = $data->format('d/m/Y');
            }
        }
        
        return $dados;
    }
    
    private function obterSpreadsheetId(): string
    {
        $spreadsheetId = defined('GOOGLE_SPREADSHEET_ID') ? GOOGLE_SPREADSHEET_ID : null;
        if (!$spreadsheetId) {
            throw new \RuntimeException('GOOGLE_SPREADSHEET_ID não configurado');
        }
        
        return $spreadsheetId;
    }
}
