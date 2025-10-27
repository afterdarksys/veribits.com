#!/bin/bash
# VeriBits CLI Repository Update Script
# Syncs CLI code to separate repositories for distribution
# © After Dark Systems, LLC

set -e

echo "🔄 VeriBits CLI Repository Update Script"
echo "========================================"
echo ""

# Configuration
NODEJS_REPO=${NODEJS_REPO:-""}
PYTHON_REPO=${PYTHON_REPO:-""}
PHP_REPO=${PHP_REPO:-""}

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Check if we're in the right directory
if [[ ! -d "cli" ]]; then
    echo -e "${RED}❌ Error: Must be run from veribits.com root directory${NC}"
    exit 1
fi

# Function to sync to repository
sync_to_repo() {
    local name=$1
    local source_dir=$2
    local repo_url=$3
    local temp_dir="/tmp/veribits-cli-$name"

    echo -e "${BLUE}📦 Syncing $name CLI...${NC}"

    if [[ -z "$repo_url" ]]; then
        echo -e "${YELLOW}  ⏭️  Skipped (no repository configured)${NC}"
        echo "     Set ${name^^}_REPO environment variable"
        return
    fi

    # Clone or pull repository
    if [[ -d "$temp_dir" ]]; then
        echo "  Pulling latest changes..."
        cd "$temp_dir"
        git pull origin main
    else
        echo "  Cloning repository..."
        git clone "$repo_url" "$temp_dir"
        cd "$temp_dir"
    fi

    # Remove old files (except .git and README.md)
    echo "  Cleaning old files..."
    find . -maxdepth 1 -not -name '.git' -not -name '.' -not -name '..' -not -name 'README.md' -not -name '.gitignore' -exec rm -rf {} +

    # Copy new files
    echo "  Copying new files..."
    cp -r "$(pwd | sed "s|/tmp/veribits-cli-$name|$(pwd)|")/$source_dir"/* .

    # Check if there are changes
    if git diff --quiet && git diff --cached --quiet; then
        echo -e "${GREEN}  ✅ No changes to commit${NC}"
        return
    fi

    # Show changes
    echo "  Changes:"
    git status --short

    # Commit and push
    git add .
    git commit -m "Update CLI from main repository $(date +%Y-%m-%d)"

    # Ask for confirmation
    read -p "  Push changes to $repo_url? (y/n) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        git push origin main
        echo -e "${GREEN}  ✅ Pushed to repository${NC}"
    else
        echo -e "${YELLOW}  ⏭️  Skipped push${NC}"
    fi

    cd - > /dev/null
}

# ==================
# Sync All Repos
# ==================

sync_to_repo "nodejs" "cli/nodejs" "$NODEJS_REPO"
echo ""

sync_to_repo "python" "cli/python" "$PYTHON_REPO"
echo ""

sync_to_repo "php" "cli" "$PHP_REPO"
echo ""

# ==================
# Summary
# ==================
echo "========================================"
echo -e "${GREEN}🎉 CLI repository sync complete!${NC}"
echo ""
echo "Next steps:"
echo "  1. Review changes in each repository"
echo "  2. Create releases/tags"
echo "  3. Run ./cli/publish-cli.sh to publish to npm/PyPI"
echo ""
echo "Environment variables for future runs:"
echo "  export NODEJS_REPO='git@github.com:afterdarksystems/veribits-cli-node.git'"
echo "  export PYTHON_REPO='git@github.com:afterdarksystems/veribits-cli-python.git'"
echo "  export PHP_REPO='git@github.com:afterdarksystems/veribits-cli-php.git'"
