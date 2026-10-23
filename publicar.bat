@echo off
rem envia o site para o servidor da UA (precisa da VPN ligada)
rem so copia ficheiros novos ou alterados, nunca apaga nada no servidor

cd /d "%~dp0"
set SERVIDOR=\\arca.ua.pt\Hosting\esan-tesp-ds-paw.web.ua.pt\tesp-ds-g17

if not exist "%SERVIDOR%" (
    echo Nao consegui chegar ao servidor. Ve se a VPN esta ligada.
    pause
    exit /b
)

robocopy common "%SERVIDOR%\common" /E /NJH /NJS /NDL /NP
rem o credentials.php do servidor nunca e substituido
robocopy projeto "%SERVIDOR%\projeto" /E /XF credentials.php /NJH /NJS /NDL /NP
robocopy . "%SERVIDOR%" index.html /NJH /NJS /NDL /NP

echo.
echo Site atualizado.
pause
