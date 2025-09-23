<?php
/**
 * API REST para Verificação de CPF
 * 
 * Esta API permite verificar CPFs e consultar o histórico
 * via requisições HTTP (GET, POST, PUT, DELETE).
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Responder a requisições OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/functions.php';

// Criar conexão com o banco de dados
try {
    $pdo = conectarBanco();
} catch (Exception $e) {
    sendError('Erro de conexão com o banco de dados: ' . $e->getMessage(), 500);
}

// Função para enviar resposta JSON
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Função para enviar erro
function sendError($message, $statusCode = 400) {
    sendResponse([
        'success' => false,
        'error' => $message,
        'timestamp' => date('c')
    ], $statusCode);
}

// Verificar se a conexão com o banco está funcionando
if (!$pdo) {
    sendError('Erro de conexão com o banco de dados', 500);
}

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));

// Remover 'api' e 'cpf' do path
array_shift($pathParts); // Remove 'api'
array_shift($pathParts); // Remove 'cpf'

$action = $pathParts[0] ?? '';

try {
    switch ($method) {
        case 'GET':
            handleGet($action, $pathParts);
            break;
            
        case 'POST':
            handlePost($action, $pathParts);
            break;
            
        case 'PUT':
            handlePut($action, $pathParts);
            break;
            
        case 'DELETE':
            handleDelete($action, $pathParts);
            break;
            
        default:
            sendError('Método HTTP não suportado', 405);
    }
} catch (Exception $e) {
    error_log("Erro na API CPF: " . $e->getMessage());
    sendError('Erro interno do servidor', 500);
}

/**
 * Manipula requisições GET
 */
function handleGet($action, $pathParts) {
    global $pdo;
    
    switch ($action) {
        case 'verify':
            // GET /api/cpf/verify?cpf=12345678901
            $cpf = $_GET['cpf'] ?? '';
            if (empty($cpf)) {
                sendError('Parâmetro CPF é obrigatório');
            }
            
            $cpfLimpo = limparCPF($cpf);
            if (strlen($cpfLimpo) !== 11) {
                sendError('CPF deve ter 11 dígitos');
            }
            
            $isValid = validaCPF($cpfLimpo);
            
            sendResponse([
                'success' => true,
                'data' => [
                    'cpf' => $cpfLimpo,
                    'cpf_formatted' => formatarCPF($cpfLimpo),
                    'is_valid' => $isValid,
                    'status' => $isValid ? 'Válido' : 'Inválido',
                    'timestamp' => date('c')
                ]
            ]);
            break;
            
        case 'history':
            // GET /api/cpf/history?limit=50&offset=0&valid=true
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            $filtros = [];
            
            if (isset($_GET['valid'])) {
                $filtros['e_valido'] = filter_var($_GET['valid'], FILTER_VALIDATE_BOOLEAN);
            }
            
            if (isset($_GET['date_from'])) {
                $filtros['data_inicio'] = $_GET['date_from'];
            }
            
            if (isset($_GET['date_to'])) {
                $filtros['data_fim'] = $_GET['date_to'];
            }
            
            $verificacoes = buscarVerificacoesComFiltros($pdo, $filtros, $limit, $offset);
            $total = contarVerificacoes($pdo, $filtros);
            
            // Formatar dados para resposta
            $formattedData = array_map(function($row) {
                return [
                    'id' => (int)$row['id'],
                    'cpf' => $row['cpf'],
                    'cpf_formatted' => formatarCPF($row['cpf']),
                    'is_valid' => (bool)$row['e_valido'],
                    'status' => $row['e_valido'] ? 'Válido' : 'Inválido',
                    'verification_date' => $row['data_verificacao'],
                    'observations' => $row['observacoes']
                ];
            }, $verificacoes);
            
            sendResponse([
                'success' => true,
                'data' => $formattedData,
                'pagination' => [
                    'total' => $total,
                    'limit' => $limit,
                    'offset' => $offset,
                    'has_more' => ($offset + $limit) < $total
                ]
            ]);
            break;
            
        case 'stats':
            // GET /api/cpf/stats
            $estatisticas = obterEstatisticasVerificacoes($pdo);
            
            sendResponse([
                'success' => true,
                'data' => $estatisticas
            ]);
            break;
            
        case 'lookup':
            // GET /api/cpf/lookup?cpf=12345678901
            $cpf = $_GET['cpf'] ?? '';
            if (empty($cpf)) {
                sendError('Parâmetro CPF é obrigatório');
            }
            
            $cpfLimpo = limparCPF($cpf);
            if (strlen($cpfLimpo) !== 11) {
                sendError('CPF deve ter 11 dígitos');
            }
            
            $verificacao = buscarVerificacaoPorCPF($pdo, $cpfLimpo);
            
            if ($verificacao) {
                sendResponse([
                    'success' => true,
                    'data' => [
                        'id' => (int)$verificacao['id'],
                        'cpf' => $verificacao['cpf'],
                        'cpf_formatted' => formatarCPF($verificacao['cpf']),
                        'is_valid' => (bool)$verificacao['e_valido'],
                        'status' => $verificacao['e_valido'] ? 'Válido' : 'Inválido',
                        'verification_date' => $verificacao['data_verificacao'],
                        'observations' => $verificacao['observacoes']
                    ]
                ]);
            } else {
                sendResponse([
                    'success' => true,
                    'data' => null,
                    'message' => 'CPF não encontrado no histórico'
                ]);
            }
            break;
            
        default:
            // GET /api/cpf - Lista endpoints disponíveis
            sendResponse([
                'success' => true,
                'message' => 'API de Verificação de CPF - BIA Pine App',
                'endpoints' => [
                    'GET /api/cpf/verify?cpf=12345678901' => 'Verificar se um CPF é válido',
                    'GET /api/cpf/history?limit=50&offset=0' => 'Listar histórico de verificações',
                    'GET /api/cpf/stats' => 'Obter estatísticas das verificações',
                    'GET /api/cpf/lookup?cpf=12345678901' => 'Buscar verificação específica',
                    'POST /api/cpf/verify' => 'Verificar e salvar CPF',
                    'DELETE /api/cpf/{id}' => 'Remover verificação específica'
                ]
            ]);
    }
}

/**
 * Manipula requisições POST
 */
function handlePost($action, $pathParts) {
    global $pdo;
    
    switch ($action) {
        case 'verify':
            // POST /api/cpf/verify
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input || !isset($input['cpf'])) {
                sendError('Dados inválidos. CPF é obrigatório.');
            }
            
            $cpf = $input['cpf'];
            $observacoes = $input['observations'] ?? null;
            
            $cpfLimpo = limparCPF($cpf);
            if (strlen($cpfLimpo) !== 11) {
                sendError('CPF deve ter 11 dígitos');
            }
            
            $isValid = validaCPF($cpfLimpo);
            
            if (salvarVerificacaoCPF($pdo, $cpfLimpo, $isValid, $observacoes)) {
                sendResponse([
                    'success' => true,
                    'data' => [
                        'cpf' => $cpfLimpo,
                        'cpf_formatted' => formatarCPF($cpfLimpo),
                        'is_valid' => $isValid,
                        'status' => $isValid ? 'Válido' : 'Inválido',
                        'saved' => true,
                        'timestamp' => date('c')
                    ]
                ], 201);
            } else {
                sendError('Erro ao salvar verificação no banco de dados', 500);
            }
            break;
            
        case 'batch':
            // POST /api/cpf/batch - Verificar múltiplos CPFs
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input || !isset($input['cpfs']) || !is_array($input['cpfs'])) {
                sendError('Dados inválidos. Array de CPFs é obrigatório.');
            }
            
            $verificacoes = [];
            $resultados = [];
            
            foreach ($input['cpfs'] as $cpfData) {
                $cpf = is_array($cpfData) ? $cpfData['cpf'] : $cpfData;
                $cpfLimpo = limparCPF($cpf);
                
                if (strlen($cpfLimpo) === 11) {
                    $isValid = validaCPF($cpfLimpo);
                    $observacoes = is_array($cpfData) ? ($cpfData['observations'] ?? null) : null;
                    
                    $verificacoes[] = [
                        'cpf' => $cpfLimpo,
                        'e_valido' => $isValid,
                        'observacoes' => $observacoes
                    ];
                    
                    $resultados[] = [
                        'cpf' => $cpfLimpo,
                        'cpf_formatted' => formatarCPF($cpfLimpo),
                        'is_valid' => $isValid,
                        'status' => $isValid ? 'Válido' : 'Inválido'
                    ];
                }
            }
            
            if (empty($verificacoes)) {
                sendError('Nenhum CPF válido encontrado');
            }
            
            if (salvarVerificacoesEmLote($pdo, $verificacoes)) {
                sendResponse([
                    'success' => true,
                    'data' => [
                        'processed' => count($verificacoes),
                        'results' => $resultados,
                        'timestamp' => date('c')
                    ]
                ], 201);
            } else {
                sendError('Erro ao salvar verificações em lote', 500);
            }
            break;
            
        default:
            sendError('Ação não encontrada', 404);
    }
}

/**
 * Manipula requisições PUT
 */
function handlePut($action, $pathParts) {
    // PUT não implementado ainda
    sendError('Método PUT não implementado', 501);
}

/**
 * Manipula requisições DELETE
 */
function handleDelete($action, $pathParts) {
    global $pdo;
    
    if ($action === '' && isset($pathParts[0]) && is_numeric($pathParts[0])) {
        // DELETE /api/cpf/{id}
        $id = (int)$pathParts[0];
        
        if (removerVerificacao($pdo, $id)) {
            sendResponse([
                'success' => true,
                'message' => 'Verificação removida com sucesso'
            ]);
        } else {
            sendError('Verificação não encontrada ou erro ao remover', 404);
        }
    } else {
        sendError('ID da verificação é obrigatório', 400);
    }
}
