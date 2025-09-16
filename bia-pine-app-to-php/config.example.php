<?php
/**
 * Arquivo de configuração de exemplo
 * Copie este arquivo para config.php e preencha com suas credenciais
 */

// Credenciais Google para autenticação com APIs
// Substitua pelo conteúdo JSON das suas credenciais de serviço
define('GOOGLE_CREDENTIALS_JSON', '{"type":"service_account","project_id":"seu-projeto","private_key_id":"...","private_key":"-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----\n","client_email":"...","client_id":"...","auth_uri":"https://accounts.google.com/o/oauth2/auth","token_uri":"https://oauth2.googleapis.com/token","auth_provider_x509_cert_url":"https://www.googleapis.com/oauth2/v1/certs","client_x509_cert_url":"..."}');

// ID da planilha Google que será atualizada
define('GOOGLE_SPREADSHEET_ID', '1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms');

// Configurações do portal CKAN padrão
define('DEFAULT_CKAN_PORTAL', 'https://dadosabertos.go.gov.br');

// Configurações de timeout para requisições HTTP
define('HTTP_TIMEOUT', 30);
define('HTTP_VERIFY_SSL', false); // Para desenvolvimento local

// Configurações de memória otimizadas
define('MEMORY_LIMIT', '2G');
define('MAX_EXECUTION_TIME', 600);
