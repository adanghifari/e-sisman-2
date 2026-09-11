@echo off
"%~dp0..\..\_tools\mariadb-10.4.6\bin\mysql.exe" --protocol=tcp -h 127.0.0.1 -P 3307 -uroot -proot -e "SELECT VERSION() AS version;"
