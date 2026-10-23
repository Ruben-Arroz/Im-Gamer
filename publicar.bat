@echo off
rem abre a janela de publicar, o trabalho todo e feito no publicar.ps1
start "" powershell -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File "%~dp0publicar.ps1"
