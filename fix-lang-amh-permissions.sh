#!/bin/bash

# Fix Permissions for lang/amh Directory
# This allows editing translation files for both deploy_user_dagi and dev_friend

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LANG_DIR="$SCRIPT_DIR/lang/amh"
USER="deploy_user_dagi"
GROUP="dagiservices"

echo "=========================================="
echo "Fixing Permissions for lang/amh Directory"
echo "=========================================="
echo ""

# Check if running as root or with sudo
if [ "$EUID" -ne 0 ]; then 
    echo "⚠️  This script needs sudo privileges to fix permissions."
    echo "Please run: sudo $0"
    exit 1
fi

# Fix ownership (set group to dagiservices so both users can edit)
echo "Setting ownership to $USER:$GROUP..."
chown -R $USER:$GROUP "$LANG_DIR"

# Fix permissions (664 for files = rw-rw-r--, 775 for directories = rwxrwxr-x)
echo "Setting permissions..."
# Make all PHP files writable by group
find "$LANG_DIR" -type f -name "*.php" -exec chmod 664 {} \;
# Make all directories writable by group
find "$LANG_DIR" -type d -exec chmod 775 {} \;

echo ""
echo "✅ Permissions fixed!"
echo ""
echo "Files are now writable by users in the $GROUP group"
echo "You should now be able to edit files in lang/amh/"
echo ""
echo "Verifying permissions..."
ls -la "$LANG_DIR" | head -10
echo ""
