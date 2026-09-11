@echo off
set "MYSQLD=%~dp0..\..\_tools\mariadb-10.4.6\bin\mysqld.exe"
powershell -NoProfile -ExecutionPolicy Bypass -Command "$mysqld = [IO.Path]::GetFullPath('%MYSQLD%'); Get-Process mysqld -ErrorAction SilentlyContinue | Where-Object { $_.Path -eq $mysqld } | Stop-Process -Force"
