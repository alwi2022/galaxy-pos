@echo off
cls
echo ================================================
echo      Laravel POS - Development Server
echo ================================================
echo.
echo [INFO] Suppressing PHP 8.4 deprecation warnings
echo [INFO] Server will start at: http://127.0.0.1:8000
echo [INFO] Press Ctrl+C to stop
echo.
echo ================================================
echo.

php -d error_reporting="E_ALL & ~E_DEPRECATED & ~E_NOTICE" -d display_errors=0 artisan serve

echo.
echo [INFO] Development server stopped
pause