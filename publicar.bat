@echo off
rem abre a janela de publicar sem mostrar o terminal, o trabalho todo e feito no publicar.ps1
start "" conhost.exe --headless powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0publicar.ps1"
