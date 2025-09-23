@echo off
REM Script em lote para Windows - Iniciar worker CKAN
REM 
REM Este arquivo facilita a execução do worker no Windows
REM Duplo clique neste arquivo ou execute via cmd

echo Iniciando worker CKAN...
echo.

REM Muda para o diretório do script
cd /d "%~dp0"

REM Executa o script PHP
php start-worker.php

echo.
echo Pressione qualquer tecla para fechar...
pause > nul
