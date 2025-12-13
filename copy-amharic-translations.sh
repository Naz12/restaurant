#!/bin/bash

# Copy English Translation Files to Amharic Directory
# This script copies all English translation files to Amharic as a starting point

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
USER="deploy_user_dagi"

echo "=========================================="
echo "Copying English Translations to Amharic"
echo "=========================================="
echo ""

# Check if running as root or with sudo
if [ "$EUID" -ne 0 ]; then 
    echo "⚠️  This script needs sudo privileges to fix permissions and copy files."
    echo "Please run: sudo $0"
    exit 1
fi

# Fix permissions on lang/amh directory first
echo "1. Fixing permissions on lang/amh directory..."
chown -R $USER:$USER "$SCRIPT_DIR/lang/amh"
chmod -R 775 "$SCRIPT_DIR/lang/amh"
echo "   ✅ Permissions fixed"

# Copy all English translation files to Amharic
echo ""
echo "2. Copying English translation files to Amharic..."
if [ -d "$SCRIPT_DIR/lang/eng" ]; then
    cp -r "$SCRIPT_DIR/lang/eng"/* "$SCRIPT_DIR/lang/amh/" 2>/dev/null || {
        echo "   ⚠️  Some files may have failed to copy, trying with sudo..."
        sudo -u $USER cp -r "$SCRIPT_DIR/lang/eng"/* "$SCRIPT_DIR/lang/amh/"
    }
    echo "   ✅ Files copied"
else
    echo "   ❌ Error: lang/eng directory not found"
    exit 1
fi

# Fix ownership of copied files
echo ""
echo "3. Setting correct ownership on copied files..."
chown -R $USER:$USER "$SCRIPT_DIR/lang/amh"
chmod -R 644 "$SCRIPT_DIR/lang/amh"/*.php 2>/dev/null || true
chmod 755 "$SCRIPT_DIR/lang/amh" 2>/dev/null || true
echo "   ✅ Ownership set"

# Count files
FILE_COUNT=$(find "$SCRIPT_DIR/lang/amh" -name "*.php" -type f 2>/dev/null | wc -l)
echo ""
echo "4. Verifying files..."
echo "   Found $FILE_COUNT translation files in lang/amh/"

# Clear Laravel caches
echo ""
echo "5. Clearing Laravel caches..."
cd "$SCRIPT_DIR"
sudo -u $USER php artisan config:clear 2>/dev/null || true
sudo -u $USER php artisan cache:clear 2>/dev/null || true
sudo -u $USER php artisan view:clear 2>/dev/null || true
echo "   ✅ Caches cleared"

echo ""
echo "=========================================="
echo "✅ Translation Files Copied Successfully!"
echo "=========================================="
echo ""
echo "📋 Summary:"
echo "  • All English translation files copied to lang/amh/"
echo "  • Files are ready for translation"
echo "  • Laravel caches cleared"
echo ""
echo "📝 Next Steps:"
echo "  1. Edit files in lang/amh/ to translate to Amharic"
echo "  2. Start with these important files:"
echo "     - app.php (common app strings)"
echo "     - messages.php (user messages)"
echo "     - menu.php (menu items)"
echo "     - landing.php (landing page)"
echo ""
echo "💡 Tip: Keep the array keys the same, only translate the values!"
echo ""

