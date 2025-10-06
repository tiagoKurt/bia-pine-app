<?php

namespace App;

use PhpOffice\PhpWord\TemplateProcessor;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Exception;

class Bia
{
    private const PORTAL_URL = 'https://dadosabertos.go.gov.br';
    private Client $httpClient;
    
    public function __construct()
    {
        // Verificar se o PhpWord está disponível
        if (!class_exists('PhpOffice\PhpWord\TemplateProcessor')) {
            throw new \Exception('PhpOffice\PhpWord\TemplateProcessor não está disponível. Verifique se o PhpWord está instalado corretamente.');
        }

        // Instanciar Guzzle
        $this->httpClient = new Client([
            'timeout' => 30,
            'http_errors' => false,
            'verify' => false // Ajustar conforme o ambiente
        ]);
    }

    public function gerarDocumentoComTemplate(string $templatePath, string $titulo, string $descricao, array $colunas, string $outputPath = null): string
    {
        if (!file_exists($templatePath)) {
            throw new \Exception("Template não encontrado: " . $templatePath);
        }

        $template = new TemplateProcessor($templatePath);
        
        $template->setValue('titulo_documento', $titulo);
        $template->setValue('descricao_base', $descricao);

        if (!empty($colunas)) {
            try {
                $template->cloneRowAndSetValues('coluna', array_map(function($r) {
                    return [
                        'coluna'     => $r['coluna'],
                        'tipo'       => $r['tipo'],
                        'descricao'  => $r['descricao'],
                    ];
                }, $colunas));
            } catch (\Exception $e) {
                $template->cloneRowAndSetValues('row', array_map(function($r) {
                    return [
                        'coluna'     => $r['coluna'],
                        'tipo'       => $r['tipo'],
                        'descricao'  => $r['descricao'],
                    ];
                }, $colunas));
            }
        }

        if ($outputPath === null) {
            $outputFile = sys_get_temp_dir() . '/' . str_replace(' ', '_', $titulo) . '.docx';
        } else {
            $outputFile = $outputPath;
        }

        $template->saveAs($outputFile);

        return $outputFile;
    }

    public function gerarDicionarioWord(string $recursoUrl, string $templatePath): string
    {
        try {
            error_log("BIA: Iniciando gerarDicionarioWord com URL: " . $recursoUrl);
            error_log("BIA: Template path: " . $templatePath);
            
            // Validar URL do recurso
            if (empty($recursoUrl) || !filter_var($recursoUrl, FILTER_VALIDATE_URL)) {
                error_log("BIA: URL inválida: " . $recursoUrl);
                throw new \Exception('URL do recurso inválida.');
            }

            // Extrair resource ID
            if (!preg_match('/\/resource\/([a-zA-Z0-9-]+)/', $recursoUrl, $matches)) {
                error_log("BIA: Não foi possível extrair resource ID da URL: " . $recursoUrl);
                throw new \Exception('Link do recurso CKAN inválido. Deve conter "/resource/" seguido de um ID válido.');
            }
            $resourceId = $matches[1];

            // Extrair dataset ID
            if (!preg_match('/\/dataset\/([a-zA-Z0-9-]+)/', $recursoUrl, $matches)) {
                error_log("BIA: Não foi possível extrair dataset ID da URL: " . $recursoUrl);
                throw new \Exception('Não foi possível extrair o dataset_id do link. Deve conter "/dataset/" seguido de um ID válido.');
            }
            $datasetId = $matches[1];

            error_log("BIA: Processando recurso ID: {$resourceId}, dataset ID: {$datasetId}");

            // Buscar informações do dataset
            error_log("BIA: Buscando informações do dataset...");
            $datasetInfo = $this->buscarDatasetInfo($datasetId);
            if (empty($datasetInfo)) {
                error_log("BIA: Dataset não encontrado ou inacessível");
                throw new \Exception('Dataset não encontrado ou inacessível.');
            }
            
            $nomeBase = $datasetInfo['title'] ?? 'Dataset sem título';
            $tituloDocumento = $this->gerarTituloDocumento($nomeBase);
            error_log("BIA: Título do documento: " . $tituloDocumento);
            
            // Buscar descrição do recurso
            error_log("BIA: Buscando descrição do recurso...");
            $descricaoBase = $this->buscarDescricaoRecurso($resourceId, $datasetInfo);
            error_log("BIA: Descrição obtida: " . substr($descricaoBase, 0, 100) . "...");
            
            // Buscar dados do recurso
            error_log("BIA: Buscando dados do recurso...");
            $colunasInfo = $this->buscarDadosRecurso($resourceId);
            if (empty($colunasInfo)) {
                error_log("BIA: Não foi possível extrair informações das colunas");
                throw new \Exception('Não foi possível extrair informações das colunas do recurso.');
            }
            
            error_log("BIA: Gerando documento com " . count($colunasInfo) . " colunas");
            
            error_log("BIA: Chamando gerarDocumentoComTemplate...");
            $result = $this->gerarDocumentoComTemplate(
                $templatePath,
                $tituloDocumento,
                $descricaoBase,
                $colunasInfo
            );
            
            error_log("BIA: Documento gerado com sucesso: " . $result);
            return $result;
            
        } catch (\Exception $e) {
            error_log("BIA: Erro em gerarDicionarioWord: " . $e->getMessage());
            error_log("BIA: URL: " . $recursoUrl);
            error_log("BIA: Template: " . $templatePath);
            error_log("BIA: Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }

    private function buscarDatasetInfo(string $datasetId): array
    {
        $url = self::PORTAL_URL . "/api/3/action/package_show?id={$datasetId}";
        $response = $this->fazerRequisicao($url);
        return $response['result'] ?? [];
    }

    private function buscarDescricaoRecurso(string $resourceId, array $datasetInfo): string
    {
        $url = self::PORTAL_URL . "/api/3/action/resource_show?id={$resourceId}";
        $response = $this->fazerRequisicao($url);
        
        if ($response && isset($response['result']['description'])) {
            return $response['result']['description'];
        }
        
        if (isset($datasetInfo['notes'])) {
            return $datasetInfo['notes'];
        }
        
        return "DADOS DA BASE: " . strtoupper($datasetInfo['title'] ?? '');
    }

    private function buscarDadosRecurso(string $resourceId): array
    {
        $url = self::PORTAL_URL . "/api/3/action/datastore_search?resource_id={$resourceId}&limit=5000";
        $response = $this->fazerRequisicao($url);
        
        if (!isset($response['result']['records']) || empty($response['result']['records'])) {
            throw new \Exception('Não foi possível obter dados do recurso.');
        }
        
        $records = $response['result']['records'];
        $colunas = array_keys($records[0]);
        
        $colunasInfo = [];
        foreach ($colunas as $coluna) {
            $tipoDado = $this->detectarTipoColuna($records, $coluna);
            $descricao = strtoupper(str_replace(['_', '/'], ' ', $coluna));
            
            $colunasInfo[] = [
                'coluna' => $coluna,
                'tipo' => $tipoDado,
                'descricao' => $descricao
            ];
        }
        
        return $colunasInfo;
    }

    private function detectarTipoColuna(array $records, string $coluna): string
    {
        $amostra = array_slice($records, 0, min(200, count($records)));
        $valores = array_column($amostra, $coluna);
        
        $temLetras = false;
        $temNumeros = false;
        
        foreach ($valores as $valor) {
            if ($valor === null) continue;
            
            $valorStr = (string) $valor;
            if (preg_match('/[a-zA-Z]/', $valorStr)) {
                $temLetras = true;
            }
            if (preg_match('/[0-9]/', $valorStr)) {
                $temNumeros = true;
            }
        }
        
        if ($temLetras && $temNumeros) {
            return 'ALFANUMÉRICO';
        } elseif ($temNumeros && !$temLetras) {
            return 'NUMÉRICO';
        } else {
            return 'TEXTO';
        }
    }

    private function gerarTituloDocumento(string $nomeBase): string
    {
        $nomeLimpo = preg_replace('/\b(20\d{2}|\d{1,2}|janeiro|fevereiro|março|marco|abril|maio|junho|julho|agosto|setembro|outubro|novembro|dezembro)\b/i', '', $nomeBase);
        
        $nomeSomenteLetras = preg_replace('/[^a-zA-Z\s]/', '', $nomeLimpo);
        $nomeSomenteLetras = preg_replace('/\s+/', ' ', trim($nomeSomenteLetras));
        
        return "DICIONÁRIO DE DADOS " . strtoupper($nomeSomenteLetras);
    }

    private function fazerRequisicao(string $url): array
    {
        error_log("BIA: Fazendo requisição para: " . $url);
        
        try {
            // Usar Guzzle para requisições de API (JSON)
            $response = $this->httpClient->get($url, [
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'BIA-PINE-App/1.0'
                ]
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode >= 400) {
                // Capturar erros HTTP (4xx ou 5xx)
                $errorBody = $response->getBody()->getContents();
                error_log("BIA: ERRO HTTP {$statusCode} ao acessar API: " . $url . " | Corpo: " . substr($errorBody, 0, 100));
                
                throw new Exception("Erro de API/Rede ao acessar CKAN. Status: {$statusCode}.");
            }
            
            // Obter o corpo da resposta
            $responseContent = $response->getBody()->getContents();
            error_log("BIA: Resposta recebida, tamanho: " . strlen($responseContent) . " bytes");
            
            $data = json_decode($responseContent, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $jsonError = json_last_error_msg();
                error_log("BIA: Erro JSON: " . $jsonError);
                throw new Exception("Erro ao decodificar resposta JSON da API: {$jsonError}");
            }
            
            if (isset($data['error'])) {
                $errorMsg = $data['error']['message'] ?? 'Erro desconhecido da API';
                error_log("BIA: API retornou erro: " . $errorMsg);
                throw new Exception("API retornou erro: {$errorMsg}");
            }
            
            return $data;

        } catch (RequestException $e) {
            // Erro de rede (timeout, falha de conexão)
            $errorMsg = $e->getMessage();
            error_log("BIA: ERRO DE CONEXÃO FATAL ao acessar API: " . $errorMsg);
            throw new Exception("Erro de Rede ao acessar API CKAN: {$url}. Mensagem: {$errorMsg}");
        } catch (Exception $e) {
            // Outras exceções
            throw $e;
        }
    }
}


