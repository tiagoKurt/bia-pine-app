<?php
// Carrega o autoloader do Composer se ainda não foi carregado
if (!class_exists('Dotenv\Dotenv') && file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Carrega variáveis de ambiente do arquivo .env se existir
if (file_exists(__DIR__ . '/.env') && class_exists('Dotenv\Dotenv')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

if (!defined('DB_CONNECTION')) {
    define('DB_CONNECTION', 'mysql');
}
if (!defined('DB_HOST')) {
    define('DB_HOST', '127.0.0.1');
}
if (!defined('DB_PORT')) {
    define('DB_PORT', '3306');
}
if (!defined('DB_DATABASE')) {
    define('DB_DATABASE', 'analise_ckan');
}
if (!defined('DB_USERNAME')) {
    define('DB_USERNAME', 'root');
}
if (!defined('DB_PASSWORD')) {
    define('DB_PASSWORD', ''); 
}

// CREATE DATABASE IF NOT EXISTS analise_ckan;

// USE analise_ckan;

// CREATE TABLE IF NOT EXISTS datasets (
//     id INT AUTO_INCREMENT PRIMARY KEY,
//     dataset_id VARCHAR(255) NOT NULL UNIQUE,
//     name VARCHAR(255) NOT NULL,
//     organization VARCHAR(255),
//     last_updated DATETIME,
//     status VARCHAR(20),
//     days_since_update INT,
//     resources_count INT,
//     url VARCHAR(2083),
//     portal_url VARCHAR(2083)
// );

// CREATE TABLE `verificacoes_cpf` (
//     `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
//     `cpf` VARCHAR(11) NOT NULL,
//     `e_valido` BOOLEAN NOT NULL,
//     `data_verificacao` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
//     `observacoes` TEXT NULL,
//     PRIMARY KEY (`id`),
//     UNIQUE KEY `idx_cpf_unique` (`cpf`)
//   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
// ALTER TABLE verificacoes_cpf ADD COLUMN identificador_fonte VARCHAR(255) NULL AFTER observacoes;
// ALTER TABLE verificacoes_cpf ADD COLUMN name_dataset VARCHAR(255);
// CREATE INDEX idx_identificador_fonte ON verificacoes_cpf (identificador_fonte);


if (!defined('GOOGLE_CREDENTIALS_JSON')) {
    $credenciaisEnv = getenv('GOOGLE_CREDENTIALS_JSON');
    if ($credenciaisEnv) {
        define('GOOGLE_CREDENTIALS_JSON', $credenciaisEnv);
    } else {
        define('GOOGLE_CREDENTIALS_JSON', '{"type":"service_account","project_id":"seu-projeto","private_key_id":"...","private_key":"-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----\n","client_email":"...","client_id":"...","auth_uri":"https://accounts.google.com/o/oauth2/auth","token_uri":"https://oauth2.googleapis.com/token","auth_provider_x509_cert_url":"https://www.googleapis.com/oauth2/v1/certs","client_x509_cert_url":"..."}');
    }
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

/**
 * Cria uma conexão PDO com o banco de dados.
 *
 * @return PDO A instância da conexão PDO
 * @throws PDOException Em caso de erro na conexão
 */
function conectarBanco(): PDO {
    $dsn = DB_CONNECTION . ':host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_DATABASE . ';charset=utf8mb4';
    
    $opcoes = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ];
    
    return new PDO($dsn, DB_USERNAME, DB_PASSWORD, $opcoes);
}