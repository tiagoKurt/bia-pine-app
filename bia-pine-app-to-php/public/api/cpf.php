<?php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../vendor/autoload.php';

// Headers para API
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Trata requisições OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Incluir funções de verificação de CPF
require_once __DIR__ . '/../../src/functions.php';

try {
    $action = $_GET['action'] ?? 'list';
    $page = (int)($_GET['page'] ?? 1);
    $perPage = (int)($_GET['per_page'] ?? 10);
    
    $pdo = conectarBanco();
    
    switch ($action) {
        case 'list':
            // Listar CPFs encontrados
            $cpfData = getCpfFindingsPaginadoFromNewTable($pdo, $page, $perPage);
            
            echo json_encode([
                'success' => true,
                'findings' => $cpfData['findings'] ?? [],
                'total' => $cpfData['total_resources'] ?? 0,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => $cpfData['total_paginas'] ?? 1
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'stats':
            // Estatísticas de CPF
            $estatisticas = obterEstatisticasVerificacoesFromNewTable($pdo);
            $lastScanInfo = getLastCpfScanInfoFromNewTable($pdo);
            
            echo json_encode([
                'success' => true,
                'stats' => $estatisticas,
                'last_scan' => $lastScanInfo
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'verify':
            // Verificar CPF específico
            $cpf = $_POST['cpf'] ?? '';
            
            if (empty($cpf)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'CPF não informado'
                ], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $isValid = validaCPF($cpf);
            
            echo json_encode([
                'success' => true,
                'cpf' => $cpf,
                'is_valid' => $isValid
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Ação não reconhecida'
            ], JSON_UNESCAPED_UNICODE);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
