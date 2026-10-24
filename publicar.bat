@echo off
rem abre a janela para publicar o site e sincronizar as contas, sem mostrar o terminal
start "" conhost.exe --headless powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0ferramentas\publicar.ps1"
