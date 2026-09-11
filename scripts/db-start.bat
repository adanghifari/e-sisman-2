@echo off
set "MYSQLD=%~dp0..\..\_tools\mariadb-10.4.6\bin\mysqld.exe"
set "INI=%~dp0..\..\_tools\mariadb-10.4.6\my.ini"
powershell -NoProfile -ExecutionPolicy Bypass -Command "$mysqld = [IO.Path]::GetFullPath('%MYSQLD%'); $ini = [IO.Path]::GetFullPath('%INI%'); $existing = Get-Process mysqld -ErrorAction SilentlyContinue | Where-Object { $_.Path -eq $mysqld }; if (-not $existing) { Start-Process -FilePath $mysqld -ArgumentList ('--defaults-file=\"' + $ini + '\"') -WindowStyle Hidden; Start-Sleep -Seconds 3 }; & '%~dp0..\..\_tools\mariadb-10.4.6\bin\mysql.exe' --protocol=tcp -h 127.0.0.1 -P 3307 -uroot -proot -e 'SELECT VERSION() AS version;'"
