@echo off
REM =========================================================================
REM OpenBookManager - Docker Stop Script (Windows)
REM =========================================================================
REM
REM This script stops the OpenBookManager Docker containers.
REM
REM Usage:
REM   stop-bookmanager.bat
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
echo      OpenBookManager - Docker Stop (Windows)
echo ========================================================
echo.

REM Check for Docker Compose
docker compose version >nul 2>&1
if %errorlevel% neq 0 (
    docker-compose --version >nul 2>&1
    if %errorlevel% neq 0 (
        echo %ERROR% Docker Compose is not available
        pause
        exit /b 1
    )
    set COMPOSE_CMD=docker-compose
) else (
    set COMPOSE_CMD=docker compose
)

REM Stop containers
echo %INFO% Stopping Docker containers...
echo.

%COMPOSE_CMD% down

if %errorlevel% neq 0 (
    echo.
    echo %ERROR% Failed to stop containers
    pause
    exit /b 1
)

echo.
echo %SUCCESS% Containers stopped successfully
echo.
pause
