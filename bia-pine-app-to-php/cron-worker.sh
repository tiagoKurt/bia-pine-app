#!/bin/bash

# Encontra o diretório onde o script está localizado
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"

# Navega para o diretório do projeto e executa o worker PHP
# O flock garante que apenas uma instância deste script de cron rode por vez,
# prevenindo sobreposição caso a análise demore mais que o intervalo do cron.
(
  flock -n 9 || exit 1
  /usr/bin/php "$DIR/worker.php"
) 9>/tmp/bia_pine_worker.lock