#!/bin/bash
# VeriBits CLI Publishing Script
# © After Dark Systems, LLC

set -e

echo "🚀 VeriBits CLI Publishing Script"
echo "=================================="
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Get version from user or use default
VERSION=${1:-"1.0.0"}

echo "📦 Publishing version: $VERSION"
echo ""

# Function to update version in files
update_version() {
    local file=$1
    local pattern=$2
    local replacement=$3

    if [[ "$OSTYPE" == "darwin"* ]]; then
        # macOS
        sed -i '' "s/$pattern/$replacement/g" "$file"
    else
        # Linux
        sed -i "s/$pattern/$replacement/g" "$file"
    fi
}

# ==================
# Node.js CLI (npm)
# ==================
echo -e "${YELLOW}📦 Publishing Node.js CLI to npm...${NC}"
cd nodejs

# Update version
npm version $VERSION --no-git-tag-version

# Test installation locally
echo "  Testing local install..."
npm link

# Verify it works
if ! command -v vb &> /dev/null; then
    echo -e "${RED}❌ Error: vb command not found after npm link${NC}"
    exit 1
fi

echo "  Running quick test..."
vb --version

# Publish to npm (dry run first)
echo "  Dry run..."
npm publish --dry-run

# Ask for confirmation
read -p "Publish Node.js CLI to npm? (y/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    npm publish
    echo -e "${GREEN}✅ Node.js CLI published to npm${NC}"
else
    echo -e "${YELLOW}⏭️  Skipped npm publish${NC}"
fi

cd ..

# ==================
# Python CLI (PyPI)
# ==================
echo ""
echo -e "${YELLOW}📦 Publishing Python CLI to PyPI...${NC}"
cd python

# Update version in setup.py
update_version "setup.py" 'version="[^"]*"' "version=\"$VERSION\""
update_version "veribits.py" 'version="veribits [^"]*"' "version=\"veribits $VERSION\""

# Clean previous builds
rm -rf dist/ build/ *.egg-info/

# Build distribution
echo "  Building distribution..."
python3 setup.py sdist bdist_wheel

# Test installation locally
echo "  Testing local install..."
pip3 install --force-reinstall dist/*.whl

# Verify it works
if ! command -v veribits &> /dev/null; then
    echo -e "${RED}❌ Error: veribits command not found after pip install${NC}"
    exit 1
fi

echo "  Running quick test..."
veribits --version

# Check with twine
echo "  Checking distribution..."
twine check dist/*

# Ask for confirmation
read -p "Publish Python CLI to PyPI? (y/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    # Upload to PyPI
    twine upload dist/*
    echo -e "${GREEN}✅ Python CLI published to PyPI${NC}"
else
    echo -e "${YELLOW}⏭️  Skipped PyPI publish${NC}"
fi

cd ..

# ==================
# PHP CLI
# ==================
echo ""
echo -e "${YELLOW}📦 PHP CLI (standalone)${NC}"
echo "  PHP CLI is distributed as a standalone script"
echo "  No package manager publish needed"
echo -e "${GREEN}✅ PHP CLI ready${NC}"

echo ""
echo "=================================="
echo -e "${GREEN}🎉 Publishing complete!${NC}"
echo ""
echo "Installation commands:"
echo "  Node.js: npm install -g veribits"
echo "  Python:  pip install veribits"
echo "  PHP:     wget https://www.veribits.com/cli/veribits.php && chmod +x veribits.php"
echo ""
echo "Total commands available:"
echo "  Node.js: 24 commands"
echo "  Python:  24 commands"
echo "  PHP:     32 commands"
