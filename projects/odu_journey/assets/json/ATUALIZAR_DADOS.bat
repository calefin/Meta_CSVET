@echo off
:: Define a codificação para UTF-8 para exibir acentos corretamente
chcp 65001 > nul

echo.
echo ==========================================
echo      GERANDO BANCO DE DADOS FINAL (V8)
echo ==========================================
echo.

:: 1. Executa o script Python
python generate_data_v8.py

:: Verifica se o Python deu erro
if %errorlevel% neq 0 (
    echo.
    echo [ERRO] O script Python falhou.
    echo Verifique se 'generate_data_v8.py' e o JSON estao nesta pasta.
    pause
    exit /b
)

echo.
echo 2. Movendo arquivo para a pasta correta (../lib/data)...

:: Garante que a pasta de destino existe (voltando um nível com ..)
if not exist ..\lib\data mkdir ..\lib\data

:: Move o arquivo gerado para a pasta certa
move /Y odu_data.dart ..\lib\data\odu_data.dart

echo.
echo ==========================================
echo      ATUALIZADO COM SUCESSO!
echo ==========================================
pause