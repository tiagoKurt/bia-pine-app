<?php

namespace App;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Google\Client as GoogleClient;
use Google\Service\Sheets;
use Google\Service\Sheets\ValueRange;
use Google\Service\Sheets\ClearValuesRequest;
use RuntimeException;
use DateTime;

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
            'verify' => defined('HTTP_VERIFY_SSL') ? HTTP_VERIFY_SSL : false,
            'http_errors' => false
        ]);
        
        $this->inicializarGoogleClient();
    }

    public function analisarPortal(string $portalUrl, int $diasParaDesatualizado = 30): array
    {
        $baseUrl = rtrim($portalUrl, '/') . '/api/3/action';
        $listaDatasetsIds = $this->buscarListaDatasets($baseUrl);

        $datasetsProcessados = [];
        $totalDatasets = 0;
        $datasetsAtualizados = 0;
        $datasetsDesatualizados = 0;
        $agora = new DateTime();

        foreach ($listaDatasetsIds as $datasetId) {
            try {
                $info = $this->processarDataset($baseUrl, $datasetId, $portalUrl, false);
                
                if (!$info) {
                    continue;
                }

                $totalDatasets++;

                $ultimaAtualizacaoStr = $info['Ultima_Atualizacao'];
                $diasDesdeAtualizacao = PHP_INT_MAX;
                $status = 'outdated';

                if (!empty($ultimaAtualizacaoStr)) {
                    $dataAtualizacao = new DateTime($ultimaAtualizacaoStr);
                    $intervalo = $agora->diff($dataAtualizacao);
                    $diasDesdeAtualizacao = (int)$intervalo->format('%a');

                    if ($diasDesdeAtualizacao <= $diasParaDesatualizado) {
                        $status = 'updated';
                        $datasetsAtualizados++;
                    } else {
                        $datasetsDesatualizados++;
                    }
                } else {
                    $datasetsDesatualizados++;
                }
                
                $datasetsProcessados[] = [
                    'id' => $datasetId,
                    'name' => $info['Nome_da_Base'],
                    'organization' => $info['Orgao'],
                    'last_updated' => $ultimaAtualizacaoStr,
                    'status' => $status,
                    'days_since_update' => $diasDesdeAtualizacao,
                    'resources_count' => $info['Quantidade_de_Recursos'],
                    'url' => $info['Link_Base']
                ];

            } catch (RuntimeException $e) {
                error_log("⚠️ Erro ao processar o dataset '{$datasetId}' na análise do portal: " . $e->getMessage());
            }
        }

        return [
            'portal_url' => $portalUrl,
            'analysis_date' => date('Y-m-d H:i:s'),
            'total_datasets' => $totalDatasets,
            'updated_datasets' => $datasetsAtualizados,
            'outdated_datasets' => $datasetsDesatualizados,
            'datasets' => $datasetsProcessados
        ];
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
            throw new RuntimeException('Variável de ambiente GOOGLE_CREDENTIALS_JSON não configurada.');
        }
        
        $credenciais = json_decode($credenciaisJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Erro ao decodificar as credenciais Google. Verifique o formato do JSON.');
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
                } catch (RuntimeException $e) {
                    error_log("⚠️ Erro ao processar o dataset '{$datasetId}': " . $e->getMessage());
                }
                
                if (($index + 1) % 10 === 0 || ($index + 1) === $total) {
                    error_log("Processados " . ($index + 1) . " de " . $total . " datasets.");
                }
            }
            
            if (empty($dadosFinais)) {
                return [
                    'sucesso' => true,
                    'mensagem' => 'Nenhum dado foi encontrado ou processado nos datasets. A planilha não foi alterada.'
                ];
            }
            
            $this->atualizarPlanilhaGoogle($dadosFinais);
            
            return [
                'sucesso' => true,
                'mensagem' => 'Os dados foram extraídos e a planilha foi atualizada com sucesso!'
            ];
            
        } catch (RuntimeException $e) {
            return [
                'sucesso' => false,
                'mensagem' => 'Erro geral ao atualizar planilha: ' . $e->getMessage()
            ];
        }
    }
    
    private function buscarListaDatasets(string $baseUrl): array
    {
        $url = "{$baseUrl}/package_list";
        $response = $this->httpClient->get($url);
        
        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException("Erro ao buscar lista de datasets. Status: " . $response->getStatusCode());
        }

        $data = json_decode($response->getBody()->getContents(), true);
        
        if (!isset($data['success']) || $data['success'] !== true || !isset($data['result'])) {
            throw new RuntimeException('A resposta da API para a lista de datasets não foi bem-sucedida ou está malformada.');
        }
        
        return $data['result'];
    }
    
    private function processarDataset(string $baseUrl, string $datasetId, string $portalUrl, bool $verificarUrls): ?array
    {
        $url = "{$baseUrl}/package_show?id={$datasetId}";
        $response = $this->httpClient->get($url);

        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException("Não foi possível obter detalhes do dataset. Status: " . $response->getStatusCode());
        }
        
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
        $contadores = ['csv' => 0, 'xlsx' => 0, 'pdf' => 0, 'json' => 0];
        
        foreach ($resources as $res) {
            $formato = strtolower($res['format'] ?? '');
            if (array_key_exists($formato, $contadores)) {
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
            if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
                $qtdErro++;
                continue;
            }
            
            try {
                $response = $this->httpClient->head($url, ['timeout' => 10]);
                if ($response->getStatusCode() >= 400) {
                    $qtdErro++;
                }
            } catch (RequestException $e) {
                $qtdErro++;
            }
        }
        
        return $qtdErro;
    }
    
    private function atualizarPlanilhaGoogle(array $dadosFinais): void
    {
        $spreadsheetId = $this->obterSpreadsheetId();
        
        $dadosFormatados = $this->formatarDatas($dadosFinais);
        
        $headers = array_keys($dadosFormatados[0]);
        $values = array_map(fn($row) => array_values($row), $dadosFormatados);
        
        array_unshift($values, $headers);
        
        $range = self::WORKSHEET_NAME . '!A1';
        $valueRange = new ValueRange(['values' => $values]);
        
        $params = ['valueInputOption' => 'USER_ENTERED'];
        
        $this->sheetsService->spreadsheets_values->clear($spreadsheetId, self::WORKSHEET_NAME, new ClearValuesRequest());
        
        $this->sheetsService->spreadsheets_values->update(
            $spreadsheetId,
            $range,
            $valueRange,
            $params
        );
    }
    
    private function formatarDatas(array $dados): array
    {
        foreach ($dados as &$row) {
            if (!empty($row['Ultima_Atualizacao'])) {
                try {
                    $data = new DateTime($row['Ultima_Atualizacao']);
                    $row['Ultima_Atualizacao'] = $data->format('d/m/Y');
                } catch (\Exception $e) {
                    $row['Ultima_Atualizacao'] = ''; 
                }
            }
            
            if (!empty($row['Data_Criacao'])) {
                 try {
                    $data = new DateTime($row['Data_Criacao']);
                    $row['Data_Criacao'] = $data->format('d/m/Y');
                } catch (\Exception $e) {
                    $row['Data_Criacao'] = '';
                }
            }
        }
        
        return $dados;
    }
    
    private function obterSpreadsheetId(): string
    {
        $spreadsheetId = defined('GOOGLE_SPREADSHEET_ID') ? GOOGLE_SPREADSHEET_ID : null;
        if (!$spreadsheetId) {
            throw new RuntimeException('A constante GOOGLE_SPREADSHEET_ID não está configurada.');
        }
        
        return $spreadsheetId;
    }
}