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

// Configurações do Banco de Dados via variáveis de ambiente
if (!defined('DB_CONNECTION')) {
    define('DB_CONNECTION', $_ENV['DB_CONNECTION'] ?? 'mysql');
}
if (!defined('DB_HOST')) {
    define('DB_HOST', $_ENV['DB_HOST'] ?? '127.0.0.1');
}
if (!defined('DB_PORT')) {
    define('DB_PORT', $_ENV['DB_PORT'] ?? '3306');
}
if (!defined('DB_DATABASE')) {
    define('DB_DATABASE', $_ENV['DB_DATABASE'] ?? 'app_controladoria');
}
if (!defined('DB_USERNAME')) {
    define('DB_USERNAME', $_ENV['DB_USERNAME'] ?? 'root');
}
if (!defined('DB_PASSWORD')) {
    define('DB_PASSWORD', $_ENV['DB_PASSWORD'] ?? '');
}

// homolog
// if (!defined('DB_CONNECTION')) {
//     define('DB_CONNECTION', $_ENV['DB_CONNECTION'] ?? 'mysql');
// }
// if (!defined('DB_HOST')) {
//     define('DB_HOST', $_ENV['DB_HOST'] ?? 'mysqlhom01.intra.goias.gov.br');
// }
// if (!defined('DB_PORT')) {
//     define('DB_PORT', $_ENV['DB_PORT'] ?? '3306');
// }
// if (!defined('DB_DATABASE')) {
//     define('DB_DATABASE', $_ENV['DB_DATABASE'] ?? 'app_controladoria');
// }
// if (!defined('DB_USERNAME')) {
//     define('DB_USERNAME', $_ENV['DB_USERNAME'] ?? 'user_controla');
// }
// if (!defined('DB_PASSWORD')) {
//     define('DB_PASSWORD', $_ENV['DB_PASSWORD'] ?? 'VEUFwSpVmh778gUVWhae');
// }

// CREATE DATABASE IF NOT EXISTS analise_ckan;

// USE analise_ckan;

// CREATE TABLE IF NOT EXISTS mpda_datasets (
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

// CREATE TABLE `mpda_verificacoes_cpf` (
//     `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
//     `cpf` VARCHAR(11) NOT NULL,
//     `e_valido` BOOLEAN NOT NULL,
//     `data_verificacao` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
//     `observacoes` TEXT NULL,
//     PRIMARY KEY (`id`),
//     UNIQUE KEY `idx_cpf_unique` (`cpf`)
//   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
// ALTER TABLE mpda_verificacoes_cpf ADD COLUMN identificador_fonte VARCHAR(255) NULL AFTER observacoes;
// ALTER TABLE mpda_verificacoes_cpf ADD COLUMN name_dataset VARCHAR(255);
// CREATE INDEX idx_identificador_fonte ON mpda_verificacoes_cpf (identificador_fonte);

// CREATE TABLE `mpda_recursos_com_cpf` (
//     `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
//     `identificador_recurso` VARCHAR(255) NOT NULL,
//     `identificador_dataset` VARCHAR(255) NOT NULL,
//     `orgao` VARCHAR(255) NOT NULL, -- CAMPO PARA O NOME DO ÓRGÃO
//     `cpfs_encontrados` JSON NOT NULL,
//     `quantidade_cpfs` INT UNSIGNED NOT NULL,
//     `metadados_recurso` JSON NOT NULL,
//     `data_verificacao` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
//     PRIMARY KEY (`id`),
//     UNIQUE KEY `idx_recurso_unique` (`identificador_recurso`),
//     KEY `idx_dataset` (`identificador_dataset`)
//   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

// Configurações do Google Sheets via variáveis de ambiente
if (!defined('GOOGLE_CREDENTIALS_JSON')) {
    define('GOOGLE_CREDENTIALS_JSON', $_ENV['GOOGLE_CREDENTIALS_JSON'] ?? '{"type":"service_account","project_id":"seu-projeto","private_key_id":"...","private_key":"-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----\n","client_email":"...","client_id":"...","auth_uri":"https://accounts.google.com/o/oauth2/auth","token_uri":"https://oauth2.googleapis.com/token","auth_provider_x509_cert_url":"https://www.googleapis.com/oauth2/v1/certs","client_x509_cert_url":"..."}');
}

if (!defined('GOOGLE_SPREADSHEET_ID')) {
    define('GOOGLE_SPREADSHEET_ID', $_ENV['GOOGLE_SPREADSHEET_ID'] ?? '1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms');
}

if (!defined('DEFAULT_CKAN_PORTAL')) {
    define('DEFAULT_CKAN_PORTAL', $_ENV['DEFAULT_CKAN_PORTAL'] ?? 'https://dadosabertos.go.gov.br');
}

// Configurações do CKAN via variáveis de ambiente
if (!defined('CKAN_API_URL')) {
    define('CKAN_API_URL', $_ENV['CKAN_API_URL'] ?? 'https://dadosabertos.go.gov.br/api/3/action/');
}

if (!defined('CKAN_API_KEY')) {
    define('CKAN_API_KEY', $_ENV['CKAN_API_KEY'] ?? '');
}

if (!defined('HTTP_TIMEOUT')) {
    define('HTTP_TIMEOUT', (int)($_ENV['HTTP_TIMEOUT'] ?? 30));
}

if (!defined('HTTP_VERIFY_SSL')) {
    define('HTTP_VERIFY_SSL', filter_var($_ENV['HTTP_VERIFY_SSL'] ?? 'false', FILTER_VALIDATE_BOOLEAN));
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

// Configurações de memória para processamento de documentos grandes via variáveis de ambiente
$memoryLimit = $_ENV['MEMORY_LIMIT'] ?? '10G';
$maxExecutionTime = (int)($_ENV['MAX_EXECUTION_TIME'] ?? 1800); // Aumentado para 30 minutos

ini_set('memory_limit', $memoryLimit);
ini_set('max_execution_time', $maxExecutionTime);

// Configurações específicas para PDFs grandes
if (!defined('PDF_MEMORY_LIMIT')) {
    define('PDF_MEMORY_LIMIT', $_ENV['PDF_MEMORY_LIMIT'] ?? '4G');
}

if (!defined('PDF_MAX_EXECUTION_TIME')) {
    define('PDF_MAX_EXECUTION_TIME', $_ENV['PDF_MAX_EXECUTION_TIME'] ?? 1800); // 30 minutos para PDFs
}

if (!defined('PDF_TIMEOUT')) {
    define('PDF_TIMEOUT', $_ENV['PDF_TIMEOUT'] ?? 1200); // 20 minutos para parsing de PDF
}

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