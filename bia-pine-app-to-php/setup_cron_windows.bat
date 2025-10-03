@echo off
REM Script para configurar o Scheduled Task do worker no Windows
REM Execute este script como Administrador

REM Diretório do projeto (ajuste conforme necessário)
set PROJECT_DIR=C:\Users\tiago.moliveira\Documents\GitHub\bia-pine-app\bia-pine-app-to-php
set PHP_PATH=php
set TASK_NAME=BIAPineWorker

REM Verifica se o diretório existe
if not exist "%PROJECT_DIR%" (
    echo ERRO: Diretório do projeto não encontrado: %PROJECT_DIR%
    echo Por favor, edite este script e defina o PROJECT_DIR correto.
    pause
    exit /b 1
)

REM Cria o arquivo de log do task se não existir
set TASK_LOG=%PROJECT_DIR%\logs\task_run.log
if not exist "%TASK_LOG%" (
    echo. > "%TASK_LOG%"
)

REM Remove o task existente se houver
schtasks /query /tn "%TASK_NAME%" >nul 2>&1
if %errorlevel% == 0 (
    echo Removendo Scheduled Task existente...
    schtasks /delete /tn "%TASK_NAME%" /f
)

REM Cria o novo Scheduled Task
echo Criando Scheduled Task...
schtasks /create /tn "%TASK_NAME%" /tr "\"%PHP_PATH%\" \"%PROJECT_DIR%\bin\run_scanner.php\" >> \"%TASK_LOG%\" 2>&1" /sc minute /mo 1 /ru "SYSTEM" /f

if %errorlevel% == 0 (
    echo Scheduled Task configurado com sucesso!
    echo Nome: %TASK_NAME%
    echo Comando: %PHP_PATH% %PROJECT_DIR%\bin\run_scanner.php
    echo Log será salvo em: %TASK_LOG%
    echo.
    echo Para verificar se está funcionando:
    echo   type "%TASK_LOG%"
    echo.
    echo Para remover o Scheduled Task:
    echo   schtasks /delete /tn "%TASK_NAME%" /f
) else (
    echo ERRO: Falha ao criar o Scheduled Task
    echo Certifique-se de executar como Administrador
)

pause
