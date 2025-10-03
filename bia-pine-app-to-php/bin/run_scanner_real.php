<?php

// Garantir que o autoloader esteja disponível
ensureAutoloader();
require_once __DIR__ . '/../config.php';

echo "=== WORKER REAL DE ANÁLISE DE CPF ===\n";
echo "Iniciado em: " . date('Y-m-d H:i:s') . "\n";

try {
    // Configuração
    $cacheDir = __DIR__ . '/../cache';
    $lockFile = $cacheDir . '/scan_status.json';
    
    // Cria diretório de cache se não existir
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
        echo "✓ Diretório de cache criado\n";
    }
    
    // Cria arquivo de status inicial
    $initialStatus = [
        'status' => 'running',
        'startTime' => date('c'),
        'progress' => [
            'datasets_analisados' => 0,
            'recursos_analisados' => 0,
            'recursos_com_cpfs' => 0,
            'total_cpfs_salvos' => 0,
            'current_step' => 'Iniciando análise...'
        ],
        'lastUpdate' => date('c'),
        'message' => 'Worker iniciado e processando...'
    ];
    
    file_put_contents($lockFile, json_encode($initialStatus, JSON_PRETTY_PRINT));
    echo "✓ Arquivo de status criado\n";
    
    // Conecta ao banco de dados
    echo "Conectando ao banco de dados...\n";
    $pdo = conectarBanco();
    echo "✓ Conexão com banco estabelecida\n";
    
    // Verifica se a tabela existe
    echo "Verificando tabela mpda_recursos_com_cpf...\n";
    $result = $pdo->query("SHOW TABLES LIKE 'mpda_recursos_com_cpf'");
    if ($result->rowCount() === 0) {
        echo "✗ Tabela mpda_recursos_com_cpf não existe. Criando...\n";
        
        $createTableSql = "
        CREATE TABLE `mpda_recursos_com_cpf` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `identificador_recurso` VARCHAR(255) NOT NULL,
            `identificador_dataset` VARCHAR(255) NOT NULL,
            `orgao` VARCHAR(255) NOT NULL,
            `cpfs_encontrados` JSON NOT NULL,
            `quantidade_cpfs` INT UNSIGNED NOT NULL,
            `metadados_recurso` JSON NOT NULL,
            `data_verificacao` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_recurso_unique` (`identificador_recurso`),
            KEY `idx_dataset` (`identificador_dataset`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        $pdo->exec($createTableSql);
        echo "✓ Tabela criada com sucesso\n";
    } else {
        echo "✓ Tabela mpda_recursos_com_cpf existe\n";
    }
    
    // Verifica configurações CKAN
    if (!defined('CKAN_API_URL') || empty(CKAN_API_URL)) {
        throw new Exception("CKAN_API_URL não configurado");
    }
    
    echo "✓ CKAN_API_URL: " . CKAN_API_URL . "\n";
    echo "✓ CKAN_API_KEY: " . (defined('CKAN_API_KEY') && CKAN_API_KEY ? 'DEFINIDO' : 'NÃO DEFINIDO') . "\n";
    
    // Função para fazer requisições HTTP simples
    function makeHttpRequest($url, $timeout = 30) {
        $context = stream_context_create([
            'http' => [
                'timeout' => $timeout,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'method' => 'GET',
                'header' => [
                    'Accept: application/json',
                    'Connection: close'
                ]
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            throw new Exception("Falha na requisição HTTP para: $url");
        }
        
        return json_decode($response, true);
    }
    
    // Função para atualizar progresso
    function updateProgress($progress) {
        global $lockFile;
        $statusData = json_decode(file_get_contents($lockFile), true) ?: [];
        $statusData['progress'] = array_merge($statusData['progress'] ?? [], $progress);
        $statusData['lastUpdate'] = date('c');
        file_put_contents($lockFile, json_encode($statusData, JSON_PRETTY_PRINT));
        echo "Progresso: " . json_encode($progress) . "\n";
    }
    
    // Função para limpeza de arquivos temporários
    function cleanupTempFiles() {
        $tempDir = __DIR__ . '/../cache/temp';
        if (is_dir($tempDir)) {
            $files = glob($tempDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($tempDir);
        }
    }
    
    // Função para detectar CPFs em texto
    function detectarCpfs($texto) {
        $cpfs = [];
        
        // Remove caracteres especiais e normaliza
        $texto = preg_replace('/[^0-9]/', ' ', $texto);
        $texto = preg_replace('/\s+/', ' ', trim($texto));
        
        // Busca sequências de 11 dígitos
        preg_match_all('/\b\d{11}\b/', $texto, $matches);
        
        foreach ($matches[0] as $cpf) {
            // Valida se é um CPF válido
            if (validarCpf($cpf)) {
                $cpfs[] = $cpf;
            }
        }
        
        return array_unique($cpfs);
    }
    
    // Função para validar CPF
    function validarCpf($cpf) {
        $cpf = preg_replace('/[^0-9]/', '', $cpf);
        
        if (strlen($cpf) != 11) {
            return false;
        }
        
        // Verifica se todos os dígitos são iguais
        if (preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }
        
        // Calcula o primeiro dígito verificador
        $soma = 0;
        for ($i = 0; $i < 9; $i++) {
            $soma += intval($cpf[$i]) * (10 - $i);
        }
        $resto = $soma % 11;
        $dv1 = ($resto < 2) ? 0 : 11 - $resto;
        
        if (intval($cpf[9]) != $dv1) {
            return false;
        }
        
        // Calcula o segundo dígito verificador
        $soma = 0;
        for ($i = 0; $i < 10; $i++) {
            $soma += intval($cpf[$i]) * (11 - $i);
        }
        $resto = $soma % 11;
        $dv2 = ($resto < 2) ? 0 : 11 - $resto;
        
        return intval($cpf[10]) == $dv2;
    }
    
    // Função para baixar e analisar arquivo
    function analisarArquivo($url, $formato) {
        $cpfs = [];
        
        try {
            // Valida se a URL não está vazia
            if (empty($url) || !is_string($url)) {
                echo "    ⚠ URL vazia ou inválida, pulando arquivo\n";
                return $cpfs;
            }
            
            // Valida se a URL é válida
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                echo "    ⚠ URL inválida: $url, pulando arquivo\n";
                return $cpfs;
            }
            
            // Baixa o arquivo
            $context = stream_context_create([
                'http' => [
                    'timeout' => 30,
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                ]
            ]);
            
            $conteudo = @file_get_contents($url, false, $context);
            if ($conteudo === false) {
                echo "    ⚠ Falha ao baixar arquivo: $url\n";
                return $cpfs;
            }
            
            // Analisa baseado no formato
            switch (strtolower($formato)) {
                case 'csv':
                    $linhas = explode("\n", $conteudo);
                    foreach ($linhas as $linha) {
                        $cpfs = array_merge($cpfs, detectarCpfs($linha));
                    }
                    break;
                    
                case 'txt':
                    $cpfs = detectarCpfs($conteudo);
                    break;
                    
                default:
                    // Para outros formatos, tenta detectar CPFs no conteúdo bruto
                    $cpfs = detectarCpfs($conteudo);
                    break;
            }
            
        } catch (Exception $e) {
            echo "    ⚠ Erro ao analisar arquivo: " . $e->getMessage() . "\n";
        }
        
        return array_unique($cpfs);
    }
    
    // 1. Busca lista de datasets
    echo "Buscando lista de datasets...\n";
    updateProgress(['current_step' => 'Buscando lista de datasets...']);
    
    $datasetsUrl = CKAN_API_URL . 'package_list?limit=1000&offset=0';
    $datasetsData = makeHttpRequest($datasetsUrl);
    
    if (!isset($datasetsData['result']) || !is_array($datasetsData['result'])) {
        throw new Exception("Resposta inválida da API de datasets");
    }
    
    $datasets = $datasetsData['result'];
    $totalDatasets = count($datasets);
    echo "✓ $totalDatasets datasets encontrados\n";
    
    updateProgress([
        'datasets_analisados' => 0,
        'recursos_analisados' => 0,
        'recursos_com_cpfs' => 0,
        'total_cpfs_salvos' => 0,
        'current_step' => "Processando $totalDatasets datasets..."
    ]);
    
    $processedDatasets = 0;
    $processedResources = 0;
    $resourcesWithCpfs = 0;
    $totalCpfs = 0;
    
    // 2. Processa cada dataset
    foreach ($datasets as $index => $datasetId) {
        $processedDatasets++;
        
        echo "\n--- Dataset $processedDatasets/$totalDatasets: $datasetId ---\n";
        
        try {
            // Busca detalhes do dataset
            $datasetUrl = CKAN_API_URL . "package_show?id=" . urlencode($datasetId);
            $datasetData = makeHttpRequest($datasetUrl);
            
            if (!isset($datasetData['result'])) {
                echo "⚠ Dataset sem dados válidos\n";
                continue;
            }
            
            $dataset = $datasetData['result'];
            $datasetName = $dataset['title'] ?? $dataset['name'] ?? $datasetId;
            $organization = $dataset['organization']['title'] ?? 'Não informado';
            $datasetUrl = $dataset['url'] ?? '#';
            
            echo "Nome: $datasetName\n";
            echo "Órgão: $organization\n";
            
            // Processa recursos do dataset
            if (isset($dataset['resources']) && is_array($dataset['resources'])) {
                $resources = $dataset['resources'];
                echo "Recursos encontrados: " . count($resources) . "\n";
                
                foreach ($resources as $resource) {
                    $processedResources++;
                    
                    $resourceId = $resource['id'] ?? 'unknown';
                    $resourceName = $resource['name'] ?? 'Sem nome';
                    $resourceUrl = $resource['url'] ?? '';
                    $resourceFormat = strtolower($resource['format'] ?? 'unknown');
                    
                    echo "  - Recurso: $resourceName ($resourceFormat)\n";
                    
                    // Valida se o recurso tem URL válida
                    if (empty($resourceUrl)) {
                        echo "    ⚠ Recurso sem URL, pulando...\n";
                        continue;
                    }
                    
                    // Atualiza progresso
                    updateProgress([
                        'datasets_analisados' => $processedDatasets,
                        'recursos_analisados' => $processedResources,
                        'recursos_com_cpfs' => $resourcesWithCpfs,
                        'total_cpfs_salvos' => $totalCpfs,
                        'current_step' => "Processando recurso: $resourceName"
                    ]);
                    
                    // Analisa apenas arquivos CSV e TXT
                    $cpfsEncontrados = [];
                    if (in_array($resourceFormat, ['csv', 'txt'])) {
                        echo "    🔍 Analisando arquivo...\n";
                        try {
                            $cpfsEncontrados = analisarArquivo($resourceUrl, $resourceFormat);
                        } catch (Exception $e) {
                            echo "    ⚠ Erro ao analisar arquivo: " . $e->getMessage() . "\n";
                            $cpfsEncontrados = [];
                        }
                    } else {
                        echo "    ⏭️ Formato $resourceFormat não suportado para análise\n";
                    }
                    
                    if (!empty($cpfsEncontrados)) {
                        $resourcesWithCpfs++;
                        $totalCpfs += count($cpfsEncontrados);
                        
                        echo "    ✓ " . count($cpfsEncontrados) . " CPFs encontrados\n";
                        
                        // Salva na tabela mpda_recursos_com_cpf
                        try {
                            $stmt = $pdo->prepare("
                                INSERT INTO mpda_recursos_com_cpf 
                                (identificador_recurso, identificador_dataset, orgao, 
                                 cpfs_encontrados, quantidade_cpfs, metadados_recurso, data_verificacao) 
                                VALUES (?, ?, ?, ?, ?, ?, NOW())
                                ON DUPLICATE KEY UPDATE
                                cpfs_encontrados = VALUES(cpfs_encontrados),
                                quantidade_cpfs = VALUES(quantidade_cpfs),
                                metadados_recurso = VALUES(metadados_recurso),
                                data_verificacao = NOW()
                            ");
                            
                            $metadados = [
                                'resource_name' => $resourceName,
                                'resource_url' => $resourceUrl,
                                'resource_format' => $resourceFormat,
                                'dataset_name' => $datasetName,
                                'dataset_url' => $datasetUrl
                            ];
                            
                            $stmt->execute([
                                $resourceId,
                                $datasetId,
                                $organization,
                                json_encode($cpfsEncontrados),
                                count($cpfsEncontrados),
                                json_encode($metadados)
                            ]);
                            
                            echo "    ✓ CPFs salvos no banco de dados\n";
                            
                        } catch (Exception $e) {
                            echo "    ⚠ Erro ao salvar no banco: " . $e->getMessage() . "\n";
                        }
                        
                    } else {
                        echo "    - Nenhum CPF encontrado\n";
                    }
                }
            } else {
                echo "Nenhum recurso encontrado\n";
            }
            
        } catch (Exception $e) {
            echo "⚠ Erro ao processar dataset $datasetId: " . $e->getMessage() . "\n";
            continue;
        }
        
        // Pausa para não sobrecarregar a API
        if ($processedDatasets % 5 === 0) {
            echo "Pausa de 2 segundos...\n";
            sleep(2);
        }
    }
    
    // Atualiza status final
    $finalStatus = [
        'status' => 'completed',
        'startTime' => $initialStatus['startTime'],
        'endTime' => date('c'),
        'progress' => [
            'datasets_analisados' => $processedDatasets,
            'recursos_analisados' => $processedResources,
            'recursos_com_cpfs' => $resourcesWithCpfs,
            'total_cpfs_salvos' => $totalCpfs,
            'current_step' => 'Análise concluída com sucesso'
        ],
        'lastUpdate' => date('c'),
        'message' => "Análise concluída! $processedDatasets datasets, $processedResources recursos, $resourcesWithCpfs com CPFs, $totalCpfs CPFs salvos."
    ];
    
    file_put_contents($lockFile, json_encode($finalStatus, JSON_PRETTY_PRINT));
    
    // Limpa arquivos temporários
    cleanupTempFiles();
    
    echo "\n=== ANÁLISE CONCLUÍDA COM SUCESSO ===\n";
    echo "✓ $processedDatasets datasets processados\n";
    echo "✓ $processedResources recursos analisados\n";
    echo "✓ $resourcesWithCpfs recursos com CPFs encontrados\n";
    echo "✓ $totalCpfs CPFs salvos no banco de dados\n";
    echo "\nO sistema está funcionando corretamente!\n";
    echo "Agora você pode acessar o front-end para ver os resultados.\n";
    
} catch (Exception $e) {
    echo "✗ ERRO FATAL: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . "\n";
    echo "Linha: " . $e->getLine() . "\n";
    
    // Limpa arquivos temporários mesmo em caso de erro
    cleanupTempFiles();
    
    // Atualiza status de erro
    if (isset($lockFile) && file_exists($lockFile)) {
        $errorStatus = json_decode(file_get_contents($lockFile), true) ?: [];
        $errorStatus['status'] = 'failed';
        $errorStatus['error'] = $e->getMessage();
        $errorStatus['endTime'] = date('c');
        $errorStatus['lastUpdate'] = date('c');
        file_put_contents($lockFile, json_encode($errorStatus, JSON_PRETTY_PRINT));
    }
    
    exit(1);
}

echo "=== FIM DO WORKER ===\n";
