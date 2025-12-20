@echo off
REM =========================================================================
REM OpenBookManager - Docker Startup Script (Windows)
REM =========================================================================
REM
REM This script starts the OpenBookManager application using Docker Desktop.
REM
REM Requirements:
REM   - Docker Desktop for Windows must be installed and running
REM
REM Usage:
REM   start-bookmanager.bat
REM
REM Services:
REM   - Web:        http://localhost:8000 (OpenBookManager)
REM   - phpMyAdmin: http://localhost:8080 (Database management)
REM   - Database:   localhost:3307 (MariaDB)
REM
REM =========================================================================

setlocal enabledelayedexpansion

REM Get script directory
set SCRIPT_DIR=%~dp0
cd /d "%SCRIPT_DIR%"

REM Colors (limited support in Windows CMD)
set INFO=[INFO]
set SUCCESS=[OK]
set WARNING=[WARNING]
set ERROR=[ERROR]

echo.
echo ========================================================
echo      OpenBookManager - Docker Startup (Windows)
echo ========================================================
echo.

REM Check if Docker is installed
docker --version >nul 2>&1
if %errorlevel% neq 0 (
    echo %ERROR% Docker is not installed or not in PATH
    echo.
    echo Please install Docker Desktop for Windows:
    echo   https://docs.docker.com/desktop/install/windows-install/
    echo.
    pause
    exit /b 1
)

echo %SUCCESS% Docker is installed
echo.

REM Check if Docker is running
docker info >nul 2>&1
if %errorlevel% neq 0 (
    echo %ERROR% Docker Desktop is not running
    echo.
    echo Please start Docker Desktop and try again.
    echo.
    pause
    exit /b 1
)

echo %SUCCESS% Docker Desktop is running
echo.

REM Check for Docker Compose
docker compose version >nul 2>&1
if %errorlevel% neq 0 (
    docker-compose --version >nul 2>&1
    if %errorlevel% neq 0 (
        echo %ERROR% Docker Compose is not available
        echo.
        echo Please update Docker Desktop to the latest version.
        echo.
        pause
        exit /b 1
    )
    set COMPOSE_CMD=docker-compose
) else (
    set COMPOSE_CMD=docker compose
)

echo %SUCCESS% Docker Compose is available
echo.

REM Setup configuration
echo %INFO% Setting up configuration...

if not exist "%SCRIPT_DIR%config" mkdir "%SCRIPT_DIR%config"

if not exist "%SCRIPT_DIR%config\config.php" (
    if exist "%SCRIPT_DIR%config\config.docker.php" (
        copy "%SCRIPT_DIR%config\config.docker.php" "%SCRIPT_DIR%config\config.php" >nul
        echo %SUCCESS% Configuration file created from Docker template
    ) else (
        echo %WARNING% Docker config template not found
        if exist "%SCRIPT_DIR%config\config.example.php" (
            copy "%SCRIPT_DIR%config\config.example.php" "%SCRIPT_DIR%config\config.php" >nul
            echo %WARNING% Please edit config\config.php with Docker database settings
        )
    )
) else (
    echo %INFO% Configuration file already exists
)
echo.

REM Ensure uploads directory exists
if not exist "%SCRIPT_DIR%public\uploads" mkdir "%SCRIPT_DIR%public\uploads"

REM Start containers
echo %INFO% Starting Docker containers...
echo.

%COMPOSE_CMD% up -d --build

if %errorlevel% neq 0 (
    echo.
    echo %ERROR% Failed to start containers
    echo.
    echo Check logs with: %COMPOSE_CMD% logs
    echo.
    pause
    exit /b 1
)

echo.
echo %INFO% Waiting for services to be ready...
timeout /t 3 /nobreak >nul

echo.
echo ========================================================
echo      OpenBookManager is now running!
echo ========================================================
echo.
echo Services:
echo   [*] OpenBookManager:  http://localhost:8000
echo   [*] phpMyAdmin:       http://localhost:8080
echo   [*] MariaDB:          localhost:3307
echo.
echo Default Login:
echo   Username: admin
echo   Password: admin123
echo.
echo Useful commands:
echo   Stop:     stop-bookmanager.bat
echo   Logs:     %COMPOSE_CMD% logs -f
echo   Restart:  %COMPOSE_CMD% restart
echo.

REM Open browser
echo %INFO% Opening browser...
timeout /t 2 /nobreak >nul
start http://localhost:8000

echo.
echo %SUCCESS% Done! Press any key to exit...
pause >nul
