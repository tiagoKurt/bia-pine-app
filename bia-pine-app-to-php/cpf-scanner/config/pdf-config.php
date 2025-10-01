<?php
/**
 * Configurações específicas para processamento de PDFs
 * 
 * Este arquivo centraliza todas as configurações relacionadas ao processamento
 * de arquivos PDF, incluindo timeouts, limites de memória e estratégias de processamento.
 */

// Configurações de timeout para PDFs
if (!defined('PDF_MAX_EXECUTION_TIME')) {
    define('PDF_MAX_EXECUTION_TIME', $_ENV['PDF_MAX_EXECUTION_TIME'] ?? 1800); // 30 minutos
}

if (!defined('PDF_TIMEOUT')) {
    define('PDF_TIMEOUT', $_ENV['PDF_TIMEOUT'] ?? 1200); // 20 minutos para parsing
}

if (!defined('PDF_MEMORY_LIMIT')) {
    define('PDF_MEMORY_LIMIT', $_ENV['PDF_MEMORY_LIMIT'] ?? '4G'); // 4GB de memória
}

// Configurações de processamento em chunks
if (!defined('PDF_CHUNK_SIZE')) {
    define('PDF_CHUNK_SIZE', $_ENV['PDF_CHUNK_SIZE'] ?? 50); // Páginas por chunk
}

if (!defined('PDF_MAX_SIZE_FOR_CHUNKS')) {
    define('PDF_MAX_SIZE_FOR_CHUNKS', $_ENV['PDF_MAX_SIZE_FOR_CHUNKS'] ?? 50 * 1024 * 1024); // 50MB
}

if (!defined('PDF_MAX_SIZE_WARNING')) {
    define('PDF_MAX_SIZE_WARNING', $_ENV['PDF_MAX_SIZE_WARNING'] ?? 100 * 1024 * 1024); // 100MB
}

// Configurações de retry para PDFs problemáticos
if (!defined('PDF_MAX_RETRIES')) {
    define('PDF_MAX_RETRIES', $_ENV['PDF_MAX_RETRIES'] ?? 3);
}

if (!defined('PDF_RETRY_DELAY')) {
    define('PDF_RETRY_DELAY', $_ENV['PDF_RETRY_DELAY'] ?? 5); // segundos
}

// Configurações de logging
if (!defined('PDF_LOG_PROGRESS_INTERVAL')) {
    define('PDF_LOG_PROGRESS_INTERVAL', $_ENV['PDF_LOG_PROGRESS_INTERVAL'] ?? 50); // Páginas
}

if (!defined('PDF_ENABLE_DETAILED_LOGS')) {
    define('PDF_ENABLE_DETAILED_LOGS', $_ENV['PDF_ENABLE_DETAILED_LOGS'] ?? 'true');
}

/**
 * Aplica as configurações de PDF ao ambiente PHP
 */
function aplicarConfiguracoesPdf(): void
{
    ini_set('max_execution_time', PDF_MAX_EXECUTION_TIME);
    ini_set('memory_limit', PDF_MEMORY_LIMIT);
    
    // Configurar timezone se não estiver definido
    if (!ini_get('date.timezone')) {
        date_default_timezone_set('America/Sao_Paulo');
    }
}

/**
 * Log específico para PDFs com formatação consistente
 */
function logPdf(string $level, string $message, array $context = []): void
{
    if (!PDF_ENABLE_DETAILED_LOGS && $level === 'DEBUG') {
        return;
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
    $logMessage = "[{$timestamp}] [PDF-{$level}] {$message}{$contextStr}";
    
    error_log($logMessage);
}

/**
 * Verifica se um arquivo PDF deve ser processado em chunks
 */
function deveProcessarEmChunks(string $filePath): bool
{
    if (!file_exists($filePath)) {
        return false;
    }
    
    $fileSize = filesize($filePath);
    return $fileSize > PDF_MAX_SIZE_FOR_CHUNKS;
}

/**
 * Obtém configurações de PDF como array
 */
function obterConfiguracoesPdf(): array
{
    return [
        'max_execution_time' => PDF_MAX_EXECUTION_TIME,
        'timeout' => PDF_TIMEOUT,
        'memory_limit' => PDF_MEMORY_LIMIT,
        'chunk_size' => PDF_CHUNK_SIZE,
        'max_size_for_chunks' => PDF_MAX_SIZE_FOR_CHUNKS,
        'max_size_warning' => PDF_MAX_SIZE_WARNING,
        'max_retries' => PDF_MAX_RETRIES,
        'retry_delay' => PDF_RETRY_DELAY,
        'log_progress_interval' => PDF_LOG_PROGRESS_INTERVAL,
        'enable_detailed_logs' => PDF_ENABLE_DETAILED_LOGS
    ];
}
