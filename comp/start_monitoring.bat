@echo off
echo 댄스스코어 결과 모니터링 시스템을 시작합니다...
echo.

REM PHP 경로 확인
php --version >nul 2>&1
if %errorlevel% neq 0 (
    echo PHP가 설치되어 있지 않거나 PATH에 등록되어 있지 않습니다.
    echo PHP를 설치하고 PATH에 등록한 후 다시 실행해주세요.
    pause
    exit /b 1
)

REM 결과 디렉토리 확인
if not exist "C:\dancescore\Web\results" (
    echo 결과 디렉토리가 존재하지 않습니다: C:\dancescore\Web\results
    echo 디렉토리를 생성하거나 경로를 확인해주세요.
    pause
    exit /b 1
)

echo 결과 디렉토리: C:\dancescore\Web\results
echo 모니터링을 시작합니다...
echo.

REM 모니터링 시작
php result_watcher.php

pause




