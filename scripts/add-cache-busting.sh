#!/bin/bash
#
# Add Cache Busting to All PHP Files
#
# This script:
# 1. Adds AssetHelper require statement to PHP files
# 2. Replaces static asset URLs with asset() function calls
#

set -e

PUBLIC_DIR="/Users/ryan/development/veribits.com/app/public"
HELPER_REQUIRE='<?php require_once __DIR__ . "/../src/Utils/AssetHelper.php"; ?>'

echo "🔧 Implementing cache busting..."

# Find all PHP files that contain asset references
find "$PUBLIC_DIR" -name "*.php" -type f | while read -r file; do
    # Skip index.php (API router) and other non-HTML files
    if grep -q "assets/\(css\|js\)/" "$file" 2>/dev/null; then
        echo "  Processing: ${file##*/}"

        # Create temporary file
        tmp_file="${file}.tmp"

        # Add helper require at top if not already present
        if ! grep -q "AssetHelper.php" "$file"; then
            # Insert after <?php declaration
            sed '1 a\
<?php require_once __DIR__ . "/../src/Utils/AssetHelper.php"; ?>
' "$file" > "$tmp_file"
            mv "$tmp_file" "$file"
        fi

        # Replace CSS asset URLs
        sed -i.bak 's|href="/assets/css/main\.css"|href="<?= asset('\''css/main.css'\'') ?>"|g' "$file"

        # Replace JS asset URLs
        sed -i.bak 's|src="/assets/js/main\.js"|src="<?= asset('\''js/main.js'\'') ?>"|g' "$file"
        sed -i.bak 's|src="/assets/js/auth\.js"|src="<?= asset('\''js/auth.js'\'') ?>"|g' "$file"
        sed -i.bak 's|src="/assets/js/dashboard\.js"|src="<?= asset('\''js/dashboard.js'\'') ?>"|g' "$file"
        sed -i.bak 's|src="/assets/js/settings\.js"|src="<?= asset('\''js/settings.js'\'') ?>"|g' "$file"
        sed -i.bak 's|src="/assets/js/firewall-editor\.js"|src="<?= asset('\''js/firewall-editor.js'\'') ?>"|g' "$file"

        # Remove backup file
        rm -f "${file}.bak"
    fi
done

echo "✅ Cache busting implemented!"
echo ""
echo "Next steps:"
echo "1. Build and deploy Docker image"
echo "2. Clear CloudFront cache"
echo "3. Test asset loading with browser DevTools"
