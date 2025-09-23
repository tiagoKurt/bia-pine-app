#!/bin/bash
# Script para executar worker CKAN via cron
# 
# Para usar este script, adicione uma linha similar a esta no crontab:
# */1 * * * * /caminho/para/seu/projeto/cron-worker.sh
# 
# Isso executará o worker a cada minuto, verificando se há análises pendentes

# Caminho para o projeto (ajuste conforme necessário)
PROJECT_DIR="/caminho/para/seu/projeto/bia-pine-app-to-php"

# Caminho para o PHP (ajuste se necessário)
PHP_BIN="/usr/bin/php"

# Executa o script de inicialização do worker
cd "$PROJECT_DIR"
$PHP_BIN start-worker.php >> logs/cron-worker.log 2>&1
