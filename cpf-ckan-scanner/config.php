<?php
/**
 * Arquivo de configuração principal do Agente de Verificação de CPF
 * 
 * Este arquivo centraliza configurações adicionais e constantes
 * utilizadas em todo o sistema.
 */

// Configurações de erro (para desenvolvimento)
if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', $_ENV['APP_DEBUG'] ?? false);
}

if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/logs/error.log');
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Criar diretório de logs se não existir
if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

// Configurações de timezone
date_default_timezone_set('America/Sao_Paulo');

// Configurações de memória para processamento de documentos grandes
if (!defined('MEMORY_LIMIT')) {
    define('MEMORY_LIMIT', $_ENV['MEMORY_LIMIT'] ?? '2G');
}
ini_set('memory_limit', MEMORY_LIMIT);

if (!defined('MAX_EXECUTION_TIME')) {
    define('MAX_EXECUTION_TIME', $_ENV['MAX_EXECUTION_TIME'] ?? 600);
}
ini_set('max_execution_time', MAX_EXECUTION_TIME);

// Configurações de timeout para requisições HTTP
if (!defined('HTTP_TIMEOUT')) {
    define('HTTP_TIMEOUT', $_ENV['HTTP_TIMEOUT'] ?? 30);
}

if (!defined('HTTP_CONNECT_TIMEOUT')) {
    define('HTTP_CONNECT_TIMEOUT', $_ENV['HTTP_CONNECT_TIMEOUT'] ?? 10);
}

// Configurações de SSL (para desenvolvimento local)
if (!defined('HTTP_VERIFY_SSL')) {
    define('HTTP_VERIFY_SSL', $_ENV['HTTP_VERIFY_SSL'] ?? true);
}

// Configurações de cache
if (!defined('CACHE_TTL')) {
    define('CACHE_TTL', $_ENV['CACHE_TTL'] ?? 3600); // 1 hora
}

// Configurações de retry
if (!defined('MAX_RETRY_ATTEMPTS')) {
    define('MAX_RETRY_ATTEMPTS', $_ENV['MAX_RETRY_ATTEMPTS'] ?? 5);
}

if (!defined('RETRY_DELAY_BASE')) {
    define('RETRY_DELAY_BASE', $_ENV['RETRY_DELAY_BASE'] ?? 1); // segundos
}

// Configurações de chunk para processamento de texto
if (!defined('MAX_CHUNK_SIZE')) {
    define('MAX_CHUNK_SIZE', $_ENV['MAX_CHUNK_SIZE'] ?? 15000);
}

// Configurações de logging
if (!defined('LOG_LEVEL')) {
    define('LOG_LEVEL', $_ENV['LOG_LEVEL'] ?? 'INFO');
}

// Configurações de segurança
if (!defined('ENABLE_CPF_OBFUSCATION')) {
    define('ENABLE_CPF_OBFUSCATION', $_ENV['ENABLE_CPF_OBFUSCATION'] ?? true);
}

// Configurações de relatório
if (!defined('REPORT_INCLUDE_STATS')) {
    define('REPORT_INCLUDE_STATS', $_ENV['REPORT_INCLUDE_STATS'] ?? true);
}

if (!defined('REPORT_INCLUDE_CACHE_INFO')) {
    define('REPORT_INCLUDE_CACHE_INFO', $_ENV['REPORT_INCLUDE_CACHE_INFO'] ?? true);
}

// Configurações de formato de saída
if (!defined('OUTPUT_FORMAT')) {
    define('OUTPUT_FORMAT', $_ENV['OUTPUT_FORMAT'] ?? 'console'); // console, json, csv
}

// Configurações de paralelização (futuro)
if (!defined('ENABLE_PARALLEL_PROCESSING')) {
    define('ENABLE_PARALLEL_PROCESSING', $_ENV['ENABLE_PARALLEL_PROCESSING'] ?? false);
}

if (!defined('MAX_PARALLEL_WORKERS')) {
    define('MAX_PARALLEL_WORKERS', $_ENV['MAX_PARALLEL_WORKERS'] ?? 4);
}

// Configurações de notificação (futuro)
if (!defined('ENABLE_NOTIFICATIONS')) {
    define('ENABLE_NOTIFICATIONS', $_ENV['ENABLE_NOTIFICATIONS'] ?? false);
}

if (!defined('NOTIFICATION_EMAIL')) {
    define('NOTIFICATION_EMAIL', $_ENV['NOTIFICATION_EMAIL'] ?? '');
}

if (!defined('NOTIFICATION_WEBHOOK_URL')) {
    define('NOTIFICATION_WEBHOOK_URL', $_ENV['NOTIFICATION_WEBHOOK_URL'] ?? '');
}

// Configurações de banco de dados (futuro)
if (!defined('DB_ENABLED')) {
    define('DB_ENABLED', $_ENV['DB_ENABLED'] ?? false);
}

if (!defined('DB_DSN')) {
    define('DB_DSN', $_ENV['DB_DSN'] ?? 'sqlite:' . __DIR__ . '/data/scanner.db');
}

// Configurações de interface web (futuro)
if (!defined('WEB_INTERFACE_ENABLED')) {
    define('WEB_INTERFACE_ENABLED', $_ENV['WEB_INTERFACE_ENABLED'] ?? false);
}

if (!defined('WEB_PORT')) {
    define('WEB_PORT', $_ENV['WEB_PORT'] ?? 8080);
}

// Configurações de monitoramento
if (!defined('ENABLE_METRICS')) {
    define('ENABLE_METRICS', $_ENV['ENABLE_METRICS'] ?? false);
}

if (!defined('METRICS_FILE')) {
    define('METRICS_FILE', $_ENV['METRICS_FILE'] ?? __DIR__ . '/logs/metrics.json');
}

// Configurações de backup
if (!defined('ENABLE_BACKUP')) {
    define('ENABLE_BACKUP', $_ENV['ENABLE_BACKUP'] ?? false);
}

if (!defined('BACKUP_DIR')) {
    define('BACKUP_DIR', $_ENV['BACKUP_DIR'] ?? __DIR__ . '/backups');
}

// Configurações de limpeza automática
if (!defined('AUTO_CLEANUP')) {
    define('AUTO_CLEANUP', $_ENV['AUTO_CLEANUP'] ?? true);
}

if (!defined('CLEANUP_AFTER_DAYS')) {
    define('CLEANUP_AFTER_DAYS', $_ENV['CLEANUP_AFTER_DAYS'] ?? 7);
}

// Configurações de validação
if (!defined('VALIDATE_CPF_ALGORITHM')) {
    define('VALIDATE_CPF_ALGORITHM', $_ENV['VALIDATE_CPF_ALGORITHM'] ?? 'official'); // official, simple
}

if (!defined('CPF_PATTERN_STRICT')) {
    define('CPF_PATTERN_STRICT', $_ENV['CPF_PATTERN_STRICT'] ?? true);
}

// Configurações de performance
if (!defined('ENABLE_PROGRESS_BAR')) {
    define('ENABLE_PROGRESS_BAR', $_ENV['ENABLE_PROGRESS_BAR'] ?? true);
}

if (!defined('PROGRESS_UPDATE_INTERVAL')) {
    define('PROGRESS_UPDATE_INTERVAL', $_ENV['PROGRESS_UPDATE_INTERVAL'] ?? 10); // a cada 10 itens
}

// Configurações de debug
if (!defined('DEBUG_VERBOSE')) {
    define('DEBUG_VERBOSE', $_ENV['DEBUG_VERBOSE'] ?? false);
}

if (!defined('DEBUG_SAVE_TEMP_FILES')) {
    define('DEBUG_SAVE_TEMP_FILES', $_ENV['DEBUG_SAVE_TEMP_FILES'] ?? false);
}

// Configurações de teste
if (!defined('TEST_MODE')) {
    define('TEST_MODE', $_ENV['TEST_MODE'] ?? false);
}

if (!defined('TEST_LIMIT_DATASETS')) {
    define('TEST_LIMIT_DATASETS', $_ENV['TEST_LIMIT_DATASETS'] ?? 10);
}

// Configurações de compliance
if (!defined('LGPD_COMPLIANCE_MODE')) {
    define('LGPD_COMPLIANCE_MODE', $_ENV['LGPD_COMPLIANCE_MODE'] ?? true);
}

if (!defined('AUDIT_LOG_ENABLED')) {
    define('AUDIT_LOG_ENABLED', $_ENV['AUDIT_LOG_ENABLED'] ?? true);
}

if (!defined('AUDIT_LOG_FILE')) {
    define('AUDIT_LOG_FILE', $_ENV['AUDIT_LOG_FILE'] ?? __DIR__ . '/logs/audit.log');
}

// Configurações de exportação
if (!defined('EXPORT_FORMATS')) {
    define('EXPORT_FORMATS', $_ENV['EXPORT_FORMATS'] ?? 'json,csv'); // json, csv, xml, pdf
}

if (!defined('EXPORT_DIR')) {
    define('EXPORT_DIR', $_ENV['EXPORT_DIR'] ?? __DIR__ . '/exports');
}

// Criar diretórios necessários
$requiredDirs = ['logs', 'cache', 'exports', 'backups', 'data'];

foreach ($requiredDirs as $dir) {
    $dirPath = __DIR__ . '/' . $dir;
    if (!is_dir($dirPath)) {
        mkdir($dirPath, 0755, true);
    }
}

// Função utilitária para logging
if (!function_exists('logMessage')) {
    function logMessage(string $level, string $message, array $context = []): void
    {
        if (!AUDIT_LOG_ENABLED) {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        $logEntry = "[{$timestamp}] [{$level}] {$message}{$contextStr}" . PHP_EOL;
        
        file_put_contents(AUDIT_LOG_FILE, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

// Função utilitária para debug
if (!function_exists('debugLog')) {
    function debugLog(string $message, array $context = []): void
    {
        if (DEBUG_VERBOSE) {
            logMessage('DEBUG', $message, $context);
        }
    }
}

// Função utilitária para métricas
if (!function_exists('recordMetric')) {
    function recordMetric(string $name, mixed $value, array $tags = []): void
    {
        if (!ENABLE_METRICS) {
            return;
        }
        
        $metric = [
            'timestamp' => time(),
            'name' => $name,
            'value' => $value,
            'tags' => $tags
        ];
        
        $metricsFile = METRICS_FILE;
        $metrics = [];
        
        if (file_exists($metricsFile)) {
            $metrics = json_decode(file_get_contents($metricsFile), true) ?: [];
        }
        
        $metrics[] = $metric;
        
        // Manter apenas as últimas 1000 métricas
        if (count($metrics) > 1000) {
            $metrics = array_slice($metrics, -1000);
        }
        
        file_put_contents($metricsFile, json_encode($metrics, JSON_PRETTY_PRINT));
    }
}
