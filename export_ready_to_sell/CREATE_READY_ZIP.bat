@echo off
title Generating Clean E-Commerce Package ZIP...
echo ============================================================
echo   Creating Clean E-Commerce Package (Zero Personal Data)
echo ============================================================
echo.

set "PHP_BIN=c:\xampp\php\php.exe"
if not exist "%PHP_BIN%" set "PHP_BIN=php"

echo Starting packaging process...
"%PHP_BIN%" "%~dp0build_zip.php"

echo.
echo ============================================================
echo Process completed! Check above output.
echo ============================================================
echo.
pause
