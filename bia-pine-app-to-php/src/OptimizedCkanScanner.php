<?php

namespace App;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\Utils;
use PDO;
use Exception;

/**
 * Serviço otimizado de varredura CKAN com paralelismo e baixo consumo de memória
 * Implementa as melhores práticas do PHP 8 para varredura de CPF
 */
class OptimizedCkanScanner
{
    // ========== PARÂMETROS GLOBAIS ==========
    private const CKAN_URL = "https://dadosabertos.go.gov.br";
    private const MAX_CONCURRENT_REQUESTS = 20; // Simula MAX_WORKERS do Python 
    private const BYTE_PREVIEW = 20000000; // Limite de bytes para download
    private const TIMEOUT = 60; // Timeout da requisição
    private const ROWS_PER_PAGE = 100; // Recursos por página na API CKAN

    private PDO $pdo;
    private Client $httpClient;
    private $progressCallback;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->httpClient = new Client([
            'base_uri' => self::CKAN_URL,
            'timeout' => self::TIMEOUT,
            'verify' => false // Para desenvolvimento local
        ]);
    }

    public function setProgressCallback(callable $callback): void
    {
        $this->progressCallback = $callback;
    }

    private function updateProgress(array $data): void
    {
        if ($this->progressCallback) {
            call_user_func($this->progressCallback, $data);
        }
    }

    /**
     * Valida um CPF usando o algoritmo oficial brasileiro
     * @param string $cpf CPF limpo (apenas 11 dígitos)
     * @return bool
     */
    public function validarCpf(string $cpf): bool
    {
        $cpf = preg_replace('/\D/', '', $cpf);

        if (strlen($cpf) != 11 || count(array_unique(str_split($cpf))) == 1) {
            return false;
        }

        // Digito 10
        $soma = 0;
        for ($i = 0; $i < 9; $i++) {
            $soma += (int)$cpf[$i] * (10 - $i);
        }
        $resto = ($soma * 10) % 11;
        if ($resto == 10) $resto = 0;
        if ($resto != (int)$cpf[9]) {
            return false;
        }

        // Digito 11
        $soma = 0;
        for ($i = 0; $i < 10; $i++) {
            $soma += (int)$cpf[$i] * (11 - $i);
        }
        $resto = ($soma * 10) % 11;
        if ($resto == 10) $resto = 0;
        if ($resto != (int)$cpf[10]) {
            return false;
        }

        return true;
    }

    /**
     * Extrai CPFs válidos de um texto e coleta o contexto
     * @param string $texto Texto do recurso
     * @param array $resInfo Metadados do recurso (inclui 'org_name')
     * @return array Lista de CPFs encontrados com contexto
     */
    public function extrairCpfsDoTexto(string $texto, array $resInfo): array
    {
        $resultados = [];
        // Regex similar à do Python
        $cpfRegex = '/\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b/'; 
        
        if (preg_match_all($cpfRegex, $texto, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                $cpfEncontrado = $match[0];
                $offset = $match[1];
                
                $cpfLimpo = preg_replace('/\D/', '', $cpfEncontrado);

                if ($this->validarCpf($cpfLimpo)) {
                    // Extração do contexto (similar ao script Python)
                    $inicio = max(0, $offset - 150);
                    $fim = min(strlen($texto), $offset + strlen($cpfEncontrado) + 150);
                    $contexto = substr($texto, $inicio, $fim - $inicio);
                    $contexto = str_replace(["\n", "\r"], " ", $contexto);
                    
                    $resultados[] = [
                        "cpf" => $cpfLimpo, 
                        "contexto" => $contexto, 
                        "url_recurso" => $resInfo["url"],
                        "nome_recurso" => $resInfo["resource_name"], 
                        "id_dataset" => $resInfo["dataset_id"],
                        "titulo_dataset" => $resInfo["dataset_title"], 
                        "orgao" => $resInfo["org_name"]
                    ];
                }
            }
        }
        return $resultados;
    }

    /**
     * Lista todos os recursos do CKAN
     * @return array Lista de recursos
     */
    public function listarTodosRecursos(): array
    {
        $recursos = [];
        $start = 0;
        
        $this->updateProgress(['current_step' => 'Conectando ao CKAN para listar todos os datasets...']);

        while (true) {
            try {
                $response = $this->httpClient->get('/api/3/action/package_search', [
                    'query' => ['start' => $start, 'rows' => self::ROWS_PER_PAGE]
                ]);
                $data = json_decode($response->getBody(), true);
                $searchResult = $data['result'] ?? [];
                $pacotes = $searchResult['results'] ?? [];

                if (empty($pacotes)) break;

                foreach ($pacotes as $pkg) {
                    $orgInfo = $pkg['organization'] ?? null;
                    $orgName = "Não informado";
                    
                    if ($orgInfo) {
                        $orgName = $orgInfo['title'] ?? 
                                  $orgInfo['name'] ?? 
                                  $orgInfo['display_name'] ?? 
                                  "Não informado";
                    }
                    
                    foreach ($pkg['resources'] ?? [] as $r) {
                        $url = $r['url'] ?? null;
                        if (!$url) continue;
                        
                        $recursos[] = [
                            "dataset_id" => $pkg['id'], 
                            "dataset_title" => $pkg['title'],
                            "org_name" => $orgName, 
                            "resource_id" => $r['id'],
                            "resource_name" => $r['name'], 
                            "url" => $url,
                            "format" => mb_strtoupper($r['format'] ?? "unknown", 'UTF-8'),
                        ];
                    }
                }
                $start += self::ROWS_PER_PAGE;
                $this->updateProgress(['current_step' => "Encontrados " . count($recursos) . " recursos até agora..."]);
            } catch (Exception $e) {
                error_log("Erro ao buscar pacotes (offset: $start): " . $e->getMessage());
                break;
            }
        }
        
        $this->updateProgress(['current_step' => "Total de " . count($recursos) . " recursos listados."]);
        return $recursos;
    }

    /**
     * Processa um recurso (Download e Varredura)
     * @param array $resInfo Metadados do recurso
     * @return array Lista de CPFs encontrados ou array vazio
     */
    public function processarRecurso(array $resInfo): array
    {
        try {
            // Usa o header 'Range' para baixar apenas o preview
            $headers = ["Range" => "bytes=0-" . (self::BYTE_PREVIEW - 1)]; 
            
            $response = $this->httpClient->get($resInfo["url"], [
                'headers' => $headers,
                'timeout' => self::TIMEOUT,
                'http_errors' => false // Não lança exceção para 4xx/5xx
            ]);

            if ($response->getStatusCode() != 200 && $response->getStatusCode() != 206) {
                 return [];
            }

            $contentBytes = (string)$response->getBody();

            // Decodificação de Texto
            $texto = null;
            foreach (['utf-8', 'latin-1', 'iso-8859-1'] as $encoding) {
                $texto = @iconv($encoding, 'UTF-8//IGNORE', $contentBytes);
                if ($texto !== false) {
                    break;
                }
            }
            if ($texto === false) { 
                 $texto = $contentBytes; // Usa o conteúdo bruto se falhar
            }
            
            // Extração dos CPFs
            return $this->extrairCpfsDoTexto($texto, $resInfo);

        } catch (Exception $e) {
            // Erro de rede/timeout/etc
            return [];
        }
    }

    /**
     * Função Principal: Gerencia o Paralelismo e Inserção Dinâmica
     */
    public function executarVarreduraOtimizada(): array
    {
        $recursos = $this->listarTodosRecursos();
        if (empty($recursos)) {
            return ['status' => 'error', 'message' => 'Nenhum recurso encontrado'];
        }
        
        $startTime = microtime(true);
        $promises = [];
        $recursosQueue = $recursos; // Fila de recursos a processar
        $count = 0;
        $totalCpfs = 0;
        $recursosComCpfs = 0;
        
        $this->updateProgress(['current_step' => "Iniciando varredura assíncrona em " . count($recursos) . " recursos..."]);

        // Loop de Controle de Concorrência
        while (!empty($recursosQueue) || !empty($promises)) {
            // Enche o pool de promessas (simula o max_workers)
            while (count($promises) < self::MAX_CONCURRENT_REQUESTS && !empty($recursosQueue)) {
                $resInfo = array_shift($recursosQueue);
                // Cria uma promessa (tarefa assíncrona) para o download
                $promises[$resInfo['resource_id']] = $this->httpClient->getAsync($resInfo['url'], [
                    'headers' => ["Range" => "bytes=0-" . (self::BYTE_PREVIEW - 1)],
                    'timeout' => self::TIMEOUT,
                    'http_errors' => false
                ])->then(function ($response) use ($resInfo) {
                    // Ao completar o download, processa a string
                    if ($response->getStatusCode() != 200 && $response->getStatusCode() != 206) {
                        return ['res_info' => $resInfo, 'cpfs' => []];
                    }
                    
                    $contentBytes = (string)$response->getBody();
                    // Decodificação
                    $texto = @iconv('UTF-8', 'UTF-8//IGNORE', $contentBytes) ?: $contentBytes;
                    
                    $cpfsEncontrados = $this->extrairCpfsDoTexto($texto, $resInfo);
                    return ['res_info' => $resInfo, 'cpfs' => $cpfsEncontrados];
                    
                }, function (Exception $e) use ($resInfo) {
                    // Lidar com erros de conexão
                    return ['res_info' => $resInfo, 'cpfs' => []];
                });
            }

            // Aguarda a conclusão de algumas promessas
            $results = Utils::settle($promises)->wait();
            
            // Processa os resultados concluídos e insere no BD (DINÂMICO)
            foreach ($results as $resourceId => $result) {
                if ($result['state'] === 'fulfilled') {
                    $data = $result['value'];
                    $resInfo = $data['res_info'];
                    $cpfsEncontrados = $data['cpfs'];
                    
                    $count++;
                    $this->updateProgress([
                        'current_step' => "Processando recurso $count/" . count($recursos),
                        'progress' => round(($count / count($recursos)) * 100, 2)
                    ]);

                    if (!empty($cpfsEncontrados)) {
                        $this->salvarResultadoRecurso($resInfo, $cpfsEncontrados);
                        $totalCpfs += count($cpfsEncontrados);
                        $recursosComCpfs++;
                    }
                }
                // Remove a promessa do pool após ser resolvida
                unset($promises[$resourceId]);
            }
            
            // Evita loop infinito se houver algum erro grave no Guzzle
            if (empty($recursosQueue) && empty($promises)) {
                break;
            }
        }

        $elapsedTime = microtime(true) - $startTime;
        
        return [
            'status' => 'completed',
            'message' => "Varredura concluída em " . number_format($elapsedTime, 2) . " segundos",
            'total_recursos' => count($recursos),
            'recursos_processados' => $count,
            'recursos_com_cpfs' => $recursosComCpfs,
            'total_cpfs' => $totalCpfs,
            'tempo_execucao' => $elapsedTime
        ];
    }

    /**
     * Salva o resultado de um recurso no banco de dados
     * @param array $resInfo Metadados do recurso
     * @param array $cpfsEncontrados Lista de CPFs encontrados
     */
    private function salvarResultadoRecurso(array $resInfo, array $cpfsEncontrados): void
    {
        $quantidadeCpfs = count($cpfsEncontrados);

        $sql = "INSERT INTO mpda_recursos_com_cpf 
                (identificador_recurso, identificador_dataset, orgao, cpfs_encontrados, quantidade_cpfs, metadados_recurso)
                VALUES (:res_id, :ds_id, :orgao, :cpfs_json, :qtd_cpfs, :meta_json)
                ON DUPLICATE KEY UPDATE
                    cpfs_encontrados = VALUES(cpfs_encontrados),
                    quantidade_cpfs = VALUES(quantidade_cpfs),
                    metadados_recurso = VALUES(metadados_recurso),
                    data_verificacao = NOW()";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':res_id' => $resInfo['resource_id'],
                ':ds_id' => $resInfo['dataset_id'],
                ':orgao' => $resInfo['org_name'],
                ':cpfs_json' => json_encode($cpfsEncontrados),
                ':qtd_cpfs' => $quantidadeCpfs,
                ':meta_json' => json_encode($resInfo) 
            ]);
        } catch (Exception $e) {
            error_log("Erro BD em {$resInfo['resource_id']}: " . $e->getMessage());
        }
    }

    /**
     * Obtém estatísticas da varredura
     * @return array Estatísticas da varredura
     */
    public function obterEstatisticas(): array
    {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_recursos,
                        SUM(quantidade_cpfs) as total_cpfs,
                        MIN(data_verificacao) as primeira_verificacao,
                        MAX(data_verificacao) as ultima_verificacao
                    FROM mpda_recursos_com_cpf";
            
            $stmt = $this->pdo->query($sql);
            $resultado = $stmt->fetch();
            
            return [
                'total_recursos' => (int) $resultado['total_recursos'],
                'total_cpfs' => (int) $resultado['total_cpfs'],
                'primeira_verificacao' => $resultado['primeira_verificacao'],
                'ultima_verificacao' => $resultado['ultima_verificacao']
            ];
        } catch (Exception $e) {
            error_log("Erro ao obter estatísticas: " . $e->getMessage());
            return [
                'total_recursos' => 0,
                'total_cpfs' => 0,
                'primeira_verificacao' => null,
                'ultima_verificacao' => null
            ];
        }
    }
}
