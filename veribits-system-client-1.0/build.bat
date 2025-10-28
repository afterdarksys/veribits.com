@echo off
REM Build script for file_hasher on Windows
REM Creates standalone executable using PyInstaller

echo === File Hasher Build Script (Windows) ===
echo.

REM Check Python version
python --version
echo.

REM Install PyInstaller if not present
pip show pyinstaller >nul 2>&1
if errorlevel 1 (
    echo PyInstaller not found. Installing...
    pip install pyinstaller
) else (
    echo PyInstaller found
)

REM Clean previous builds
echo.
echo Cleaning previous builds...
if exist build rmdir /s /q build
if exist dist rmdir /s /q dist
if exist *.spec del /q *.spec

REM Build
echo.
echo Building executable...
pyinstaller --onefile --name file-hasher.exe --clean file_hasher.py

REM Test the build
echo.
echo Testing build...
dist\file-hasher.exe --help >nul 2>&1
if errorlevel 1 (
    echo Build failed!
    exit /b 1
) else (
    echo Build successful!
)

REM Show results
echo.
echo === Build Complete ===
echo Executable: dist\file-hasher.exe
dir dist\file-hasher.exe | find "file-hasher.exe"
echo.
echo Run with: dist\file-hasher.exe
echo (Run as Administrator for full system access)
