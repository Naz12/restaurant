#!/bin/bash

# Fix Language Directory Permissions
# This script fixes permissions for the lang directory to allow adding new languages

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LANG_DIR="$SCRIPT_DIR/lang"
USER="deploy_user_dagi"

echo "=========================================="
echo "Fixing Language Directory Permissions"
echo "=========================================="
echo ""

# Check if running as root or with sudo
if [ "$EUID" -ne 0 ]; then 
    echo "⚠️  This script needs sudo privileges to fix permissions."
    echo "Please run: sudo $0"
    exit 1
fi

# Check if lang directory exists
if [ ! -d "$LANG_DIR" ]; then
    echo "Creating lang directory..."
    mkdir -p "$LANG_DIR"
fi

# Fix ownership
echo "Setting ownership to $USER..."
chown -R $USER:$USER "$LANG_DIR"

# Fix permissions (775 = rwxrwxr-x)
echo "Setting permissions to 775..."
chmod -R 775 "$LANG_DIR"

# Verify
echo ""
echo "Verifying permissions..."
ls -la "$LANG_DIR" | head -5

echo ""
echo "=========================================="
echo "✅ Permissions fixed successfully!"
echo "=========================================="
echo ""
echo "The lang directory is now writable by $USER"
echo "You should now be able to add new languages in the admin panel."
echo ""

