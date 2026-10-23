@echo off
rem envia o site para o servidor da UA (precisa da VPN ligada)
rem copia ficheiros novos ou alterados e apaga do servidor o que ja nao existe aqui
rem nas pastas common e projeto (as pastas aulas e uploads nunca sao tocadas)

cd /d "%~dp0"
set SERVIDOR=\\arca.ua.pt\Hosting\esan-tesp-ds-paw.web.ua.pt\tesp-ds-g17

if not exist "%SERVIDOR%" (
    echo Nao consegui chegar ao servidor. Ve se a VPN esta ligada.
    pause
    exit /b
)

robocopy common "%SERVIDOR%\common" /MIR /NJH /NJS /NDL /NP
rem o credentials.php do servidor nunca e substituido nem apagado
robocopy projeto "%SERVIDOR%\projeto" /MIR /XF credentials.php /NJH /NJS /NDL /NP
robocopy . "%SERVIDOR%" index.html /NJH /NJS /NDL /NP

echo.
echo Site atualizado.
pause
