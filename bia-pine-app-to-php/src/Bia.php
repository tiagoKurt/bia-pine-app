<?php

namespace App;

use PhpOffice\PhpWord\TemplateProcessor;

class Bia
{
    private const PORTAL_URL = 'https://dadosabertos.go.gov.br';
    
    public function __construct()
    {
    }

    public function gerarDocumentoComTemplate(string $templatePath, string $titulo, string $descricao, array $colunas, string $outputPath = null): string
    {
        if (!file_exists($templatePath)) {
            throw new \Exception("Template não encontrado: " . $templatePath);
        }

        $template = new TemplateProcessor($templatePath);
        
        $template->setValue('titulo_documento', $titulo);
        $template->setValue('descricao_base', $descricao);

        error_log('Variáveis no template: ' . json_encode($template->getVariables(), JSON_UNESCAPED_UNICODE));

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
                error_log('Tentando com placeholder âncora "row": ' . $e->getMessage());
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
        if (!preg_match('/\/resource\/([a-zA-Z0-9-]+)/', $recursoUrl, $matches)) {
            throw new \Exception('Link do recurso CKAN inválido.');
        }
        $resourceId = $matches[1];

        if (!preg_match('/\/dataset\/([a-zA-Z0-9-]+)/', $recursoUrl, $matches)) {
            throw new \Exception('Não foi possível extrair o dataset_id do link.');
        }
        $datasetId = $matches[1];

        $datasetInfo = $this->buscarDatasetInfo($datasetId);
        
        $nomeBase = $datasetInfo['title'] ?? '';
        $tituloDocumento = $this->gerarTituloDocumento($nomeBase);
        
        $descricaoBase = $this->buscarDescricaoRecurso($resourceId, $datasetInfo);
        
        $colunasInfo = $this->buscarDadosRecurso($resourceId);
        
        return $this->gerarDocumentoComTemplate(
            $templatePath,
            $tituloDocumento,
            $descricaoBase,
            $colunasInfo
        );
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
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'BIA-PINE-App/1.0'
            ]
        ]);
        
        $response = file_get_contents($url, false, $context);
        if ($response === false) {
            throw new \Exception("Erro ao acessar API: {$url}");
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Erro ao decodificar resposta JSON da API");
        }
        
        return $data;
    }
}


