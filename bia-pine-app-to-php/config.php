<?php
/**
 * Arquivo de configuração principal
 * Configure suas credenciais e configurações aqui
 */

// Carregar configurações do arquivo de exemplo se não existir
if (!defined('GOOGLE_CREDENTIALS_JSON')) {
    // Credenciais Google para autenticação com APIs
    // Substitua pelo conteúdo JSON das suas credenciais de serviço
    define('GOOGLE_CREDENTIALS_JSON', '{"type":"service_account","project_id":"seu-projeto","private_key_id":"...","private_key":"-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----\n","client_email":"...","client_id":"...","auth_uri":"https://accounts.google.com/o/oauth2/auth","token_uri":"https://oauth2.googleapis.com/token","auth_provider_x509_cert_url":"https://www.googleapis.com/oauth2/v1/certs","client_x509_cert_url":"..."}');
}

if (!defined('GOOGLE_SPREADSHEET_ID')) {
    // ID da planilha Google que será atualizada
    define('GOOGLE_SPREADSHEET_ID', '1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms');
}

if (!defined('DEFAULT_CKAN_PORTAL')) {
    // Configurações do portal CKAN padrão
    define('DEFAULT_CKAN_PORTAL', 'https://dadosabertos.go.gov.br');
}

if (!defined('HTTP_TIMEOUT')) {
    // Configurações de timeout para requisições HTTP
    define('HTTP_TIMEOUT', 30);
}

if (!defined('HTTP_VERIFY_SSL')) {
    // Para desenvolvimento local
    define('HTTP_VERIFY_SSL', false);
}

// Configurações de erro (para desenvolvimento)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

// Criar diretório de logs se não existir
if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

// Configurações de timezone
date_default_timezone_set('America/Sao_Paulo');

// Configurações de memória para processamento de documentos grandes
ini_set('memory_limit', defined('MEMORY_LIMIT') ? MEMORY_LIMIT : '2G');
ini_set('max_execution_time', defined('MAX_EXECUTION_TIME') ? MAX_EXECUTION_TIME : 600);
