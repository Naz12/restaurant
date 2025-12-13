#!/bin/bash

# Comprehensive Permissions Fix Script
# This script fixes all permission issues for the Laravel application
# Run with: sudo ./fix-all-permissions.sh

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
USER="deploy_user_dagi"
GROUP="deploy_user_dagi"

echo "=========================================="
echo "Comprehensive Permissions Fix"
echo "=========================================="
echo ""

# Check if running as root or with sudo
if [ "$EUID" -ne 0 ]; then 
    echo "⚠️  This script needs sudo privileges to fix permissions."
    echo "Please run: sudo $0"
    exit 1
fi

echo "📁 Fixing ownership and permissions for all writable directories..."
echo ""

# 1. Storage directory (logs, cache, sessions, views, etc.)
echo "1. Fixing storage directory..."
chown -R $USER:$GROUP "$SCRIPT_DIR/storage"
chmod -R 775 "$SCRIPT_DIR/storage"
echo "   ✅ storage/"

# 2. Bootstrap cache directory
echo "2. Fixing bootstrap/cache directory..."
chown -R $USER:$GROUP "$SCRIPT_DIR/bootstrap/cache"
chmod -R 775 "$SCRIPT_DIR/bootstrap/cache"
echo "   ✅ bootstrap/cache/"

# 3. Language directory (for adding new languages)
echo "3. Fixing lang directory..."
if [ -d "$SCRIPT_DIR/lang" ]; then
    chown -R $USER:$GROUP "$SCRIPT_DIR/lang"
    chmod -R 775 "$SCRIPT_DIR/lang"
    echo "   ✅ lang/"
else
    mkdir -p "$SCRIPT_DIR/lang"
    chown -R $USER:$GROUP "$SCRIPT_DIR/lang"
    chmod -R 775 "$SCRIPT_DIR/lang"
    echo "   ✅ lang/ (created)"
fi

# 4. Public user-uploads directory (for file uploads, favicons, etc.)
echo "4. Fixing public/user-uploads directory..."
if [ -d "$SCRIPT_DIR/public/user-uploads" ]; then
    chown -R $USER:$GROUP "$SCRIPT_DIR/public/user-uploads"
    chmod -R 775 "$SCRIPT_DIR/public/user-uploads"
    echo "   ✅ public/user-uploads/"
else
    mkdir -p "$SCRIPT_DIR/public/user-uploads"
    chown -R $USER:$GROUP "$SCRIPT_DIR/public/user-uploads"
    chmod -R 775 "$SCRIPT_DIR/public/user-uploads"
    echo "   ✅ public/user-uploads/ (created)"
fi

# 5. Ensure storage/app/public exists and is linked
echo "5. Checking storage/app/public symlink..."
if [ ! -L "$SCRIPT_DIR/public/storage" ]; then
    echo "   Creating storage symlink..."
    cd "$SCRIPT_DIR"
    sudo -u $USER php artisan storage:link 2>/dev/null || echo "   ⚠️  Symlink may already exist or failed"
fi
echo "   ✅ storage symlink"

# 6. Fix any subdirectories that might be created dynamically
echo "6. Ensuring common subdirectories exist with correct permissions..."

# Storage subdirectories
mkdir -p "$SCRIPT_DIR/storage/framework/sessions"
mkdir -p "$SCRIPT_DIR/storage/framework/views"
mkdir -p "$SCRIPT_DIR/storage/framework/cache"
mkdir -p "$SCRIPT_DIR/storage/logs"
mkdir -p "$SCRIPT_DIR/storage/app/public"
chown -R $USER:$GROUP "$SCRIPT_DIR/storage"
chmod -R 775 "$SCRIPT_DIR/storage"

# Public upload subdirectories (common ones)
mkdir -p "$SCRIPT_DIR/public/user-uploads/favicons"
mkdir -p "$SCRIPT_DIR/public/user-uploads/logo"
mkdir -p "$SCRIPT_DIR/public/user-uploads/qrcodes"
chown -R $USER:$GROUP "$SCRIPT_DIR/public/user-uploads"
chmod -R 775 "$SCRIPT_DIR/public/user-uploads"

echo "   ✅ Common subdirectories"

# 7. Clear Laravel caches to ensure fresh start
echo ""
echo "7. Clearing Laravel caches..."
cd "$SCRIPT_DIR"
sudo -u $USER php artisan config:clear 2>/dev/null || true
sudo -u $USER php artisan cache:clear 2>/dev/null || true
sudo -u $USER php artisan view:clear 2>/dev/null || true
sudo -u $USER php artisan route:clear 2>/dev/null || true
echo "   ✅ Caches cleared"

# 8. Verify permissions
echo ""
echo "8. Verifying permissions..."
echo ""
echo "Directory ownership check:"
ls -ld "$SCRIPT_DIR/storage" | awk '{print "  storage: " $3 ":" $4 " " $1}'
ls -ld "$SCRIPT_DIR/bootstrap/cache" | awk '{print "  bootstrap/cache: " $3 ":" $4 " " $1}'
ls -ld "$SCRIPT_DIR/lang" 2>/dev/null | awk '{print "  lang: " $3 ":" $4 " " $1}' || echo "  lang: (not found)"
ls -ld "$SCRIPT_DIR/public/user-uploads" 2>/dev/null | awk '{print "  public/user-uploads: " $3 ":" $4 " " $1}' || echo "  public/user-uploads: (not found)"

echo ""
echo "=========================================="
echo "✅ All Permissions Fixed!"
echo "=========================================="
echo ""
echo "📋 Summary:"
echo "  • storage/ - Fixed (logs, cache, sessions, views)"
echo "  • bootstrap/cache/ - Fixed (compiled views)"
echo "  • lang/ - Fixed (language files)"
echo "  • public/user-uploads/ - Fixed (file uploads)"
echo "  • Laravel caches - Cleared"
echo ""
echo "🎯 The application should now be able to:"
echo "  • Add new languages"
echo "  • Write logs"
echo "  • Cache views and config"
echo "  • Upload files"
echo "  • Create directories dynamically"
echo ""
echo "💡 If you still encounter permission issues, check:"
echo "  1. The specific directory mentioned in the error"
echo "  2. Run: ls -la <directory> to check ownership"
echo "  3. Ensure the directory is owned by $USER:$GROUP"
echo ""

