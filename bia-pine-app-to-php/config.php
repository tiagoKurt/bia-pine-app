<?php

// Carregar o autoloader do Composer com verificação robusta
$autoloadPath = __DIR__ . '/vendor/autoload.php';

if (!file_exists($autoloadPath)) {
    error_log("ERRO CRÍTICO: Autoloader do Composer não encontrado em: {$autoloadPath}");
    error_log("Execute 'composer install' ou 'composer dump-autoload' para resolver este problema");
    
    // Tentar carregamento manual das classes críticas como último recurso
    $criticalClasses = [
        'App\\Bia' => __DIR__ . '/src/Bia.php',
        'App\\Pine' => __DIR__ . '/src/Pine.php',
        'App\\RobustAutoloader' => __DIR__ . '/src/RobustAutoloader.php',
        'App\\AutoloaderDiagnostic' => __DIR__ . '/src/AutoloaderDiagnostic.php'
    ];
    
    foreach ($criticalClasses as $className => $filePath) {
        if (file_exists($filePath)) {
            try {
                require_once $filePath;
                error_log("Carregamento manual bem-sucedido: {$className}");
            } catch (Exception $e) {
                error_log("Erro no carregamento manual de {$className}: " . $e->getMessage());
            }
        }
    }
} else {
    try {
        require_once $autoloadPath;
        error_log("Autoloader do Composer carregado com sucesso");
    } catch (Exception $e) {
        error_log("Erro ao carregar autoloader do Composer: " . $e->getMessage());
    }
}

// Carregar variáveis de ambiente
if (file_exists(__DIR__ . '/.env') && class_exists('Dotenv\Dotenv')) {
    try {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
        $dotenv->load();
    } catch (Exception $e) {
        error_log("Erro ao carregar arquivo .env: " . $e->getMessage());
    }
}

// Inicializar sistema robusto de autoloading se disponível
if (class_exists('App\RobustAutoloader')) {
    try {
        $robustAutoloader = App\RobustAutoloader::getInstance();
        $robustAutoloader->ensureAutoloaderLoaded();
        $robustAutoloader->registerFallbackAutoloader();
        error_log("Sistema robusto de autoloading inicializado");
    } catch (Exception $e) {
        error_log("Erro ao inicializar sistema robusto de autoloading: " . $e->getMessage());
    }
}

// Configurações do Banco de Dados via variáveis de ambiente
// if (!defined('DB_CONNECTION')) {
//     define('DB_CONNECTION', $_ENV['DB_CONNECTION'] ?? 'mysql');
// }
// if (!defined('DB_HOST')) {
//     define('DB_HOST', $_ENV['DB_HOST'] ?? '127.0.0.1');
// }
// if (!defined('DB_PORT')) {
//     define('DB_PORT', $_ENV['DB_PORT'] ?? '3306');
// }
// if (!defined('DB_DATABASE')) {
//     define('DB_DATABASE', $_ENV['DB_DATABASE'] ?? 'app_controladoria');
// }
// if (!defined('DB_USERNAME')) {
//     define('DB_USERNAME', $_ENV['DB_USERNAME'] ?? 'root');
// }
// if (!defined('DB_PASSWORD')) {
//     define('DB_PASSWORD', $_ENV['DB_PASSWORD'] ?? '');
// }

// homolog
if (!defined('DB_CONNECTION')) {
    define('DB_CONNECTION', $_ENV['DB_CONNECTION'] ?? 'mysql');
}
if (!defined('DB_HOST')) {
    define('DB_HOST', $_ENV['DB_HOST'] ?? 'mysqlhom01.intra.goias.gov.br');
}
if (!defined('DB_PORT')) {
    define('DB_PORT', $_ENV['DB_PORT'] ?? '3306');
}
if (!defined('DB_DATABASE')) {
    define('DB_DATABASE', $_ENV['DB_DATABASE'] ?? 'app_controladoria');
}
if (!defined('DB_USERNAME')) {
    define('DB_USERNAME', $_ENV['DB_USERNAME'] ?? 'user_controla');
}
if (!defined('DB_PASSWORD')) {
    define('DB_PASSWORD', $_ENV['DB_PASSWORD'] ?? 'VEUFwSpVmh778gUVWhae');
}

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



// banco prod:
// CREATE TABLE `mpda_recursos_com_cpf` (
//   `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
//   `identificador_recurso` VARCHAR(255) NOT NULL,
//   `identificador_dataset` VARCHAR(255) NOT NULL,
//   `orgao` VARCHAR(255) NOT NULL,
//   `cpfs_encontrados` LONGTEXT NOT NULL,
//   `quantidade_cpfs` INT UNSIGNED NOT NULL,
//   `metadados_recurso` LONGTEXT NOT NULL,
//   `data_verificacao` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
//   PRIMARY KEY (`id`),
//   UNIQUE KEY `idx_recurso_unique` (`identificador_recurso`),
//   KEY `idx_dataset` (`identificador_dataset`)
// ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;




if (!defined('DEFAULT_CKAN_PORTAL')) {
    define('DEFAULT_CKAN_PORTAL', $_ENV['DEFAULT_CKAN_PORTAL'] ?? 'https://dadosabertos.go.gov.br');
}

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

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

date_default_timezone_set('America/Sao_Paulo');
$memoryLimit = $_ENV['MEMORY_LIMIT'] ?? '10G';
$maxExecutionTime = (int)($_ENV['MAX_EXECUTION_TIME'] ?? 1800);

ini_set('memory_limit', $memoryLimit);
ini_set('max_execution_time', $maxExecutionTime);
if (!defined('PDF_MEMORY_LIMIT')) {
    define('PDF_MEMORY_LIMIT', $_ENV['PDF_MEMORY_LIMIT'] ?? '4G');
}

if (!defined('PDF_MAX_EXECUTION_TIME')) {
    define('PDF_MAX_EXECUTION_TIME', $_ENV['PDF_MAX_EXECUTION_TIME'] ?? 1800);
}

if (!defined('PDF_TIMEOUT')) {
    define('PDF_TIMEOUT', $_ENV['PDF_TIMEOUT'] ?? 1200);
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

/**
 * Retorna uma instância de PDO
 * @return \PDO
 * @throws \PDOException
 */
function getPdoConnection(): \PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_DATABASE . ';charset=utf8mb4';
        $options = [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new \PDO($dsn, DB_USERNAME, DB_PASSWORD, $options);
        } catch (\PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            throw new \PDOException("Erro ao conectar ao banco de dados.", (int)$e->getCode());
        }
    }
    return $pdo;
}


