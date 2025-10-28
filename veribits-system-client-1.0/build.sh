#!/bin/bash
# Build script for file_hasher
# Creates standalone executable using PyInstaller

set -e

echo "=== File Hasher Build Script ==="
echo ""

# Check Python version
python_version=$(python3 --version 2>&1 | awk '{print $2}')
echo "Python version: $python_version"

# Install PyInstaller if not present
if ! command -v pyinstaller &> /dev/null; then
    echo "PyInstaller not found. Installing..."
    pip3 install pyinstaller
else
    echo "PyInstaller found: $(pyinstaller --version)"
fi

# Clean previous builds
echo ""
echo "Cleaning previous builds..."
rm -rf build/ dist/ *.spec

# Detect platform
platform=$(uname -s)
echo ""
echo "Platform: $platform"

# Build based on platform
echo ""
echo "Building executable..."

case "$platform" in
    Linux*)
        pyinstaller --onefile \
                    --name file-hasher \
                    --strip \
                    --clean \
                    file_hasher.py
        ;;
    Darwin*)
        pyinstaller --onefile \
                    --name file-hasher \
                    --target-arch universal2 \
                    --strip \
                    --clean \
                    file_hasher.py
        ;;
    *)
        pyinstaller --onefile \
                    --name file-hasher \
                    --clean \
                    file_hasher.py
        ;;
esac

# Test the build
echo ""
echo "Testing build..."
./dist/file-hasher --help > /dev/null 2>&1 && echo "✓ Build successful!" || echo "✗ Build failed!"

# Show results
echo ""
echo "=== Build Complete ==="
echo "Executable: dist/file-hasher"
echo "Size: $(du -h dist/file-hasher | awk '{print $1}')"
echo ""
echo "Run with: sudo ./dist/file-hasher"
