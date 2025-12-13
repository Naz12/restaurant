#!/bin/bash

# Fix Styles and Assets Script
# Run with: sudo ./fix-styles.sh

set -e

echo "=========================================="
echo "Fixing Landing Page Styles"
echo "=========================================="

PROJECT_DIR="/home/deploy_user_dagi/services/table_track/restaurant"

if [ "$EUID" -ne 0 ]; then 
    echo "Please run as root (use sudo)"
    exit 1
fi

cd "$PROJECT_DIR"

echo ""
echo "Step 1: Updating APP_URL..."
sed -i 's|APP_URL=.*|APP_URL=https://restaurant.akmicroservice.com|' .env
echo "✓ APP_URL updated to: https://restaurant.akmicroservice.com"

echo ""
echo "Step 2: Ensuring storage link exists..."
sudo -u deploy_user_dagi php artisan storage:link 2>/dev/null || true
echo "✓ Storage link verified"

echo ""
echo "Step 3: Rebuilding frontend assets..."
sudo -u deploy_user_dagi npm install --silent
sudo -u deploy_user_dagi npm run build
echo "✓ Frontend assets rebuilt"

echo ""
echo "Step 4: Fixing permissions on build directory..."
chown -R deploy_user_dagi:deploy_user_dagi public/build
chmod -R 755 public/build
echo "✓ Permissions fixed"

echo ""
echo "Step 5: Clearing all caches..."
sudo -u deploy_user_dagi php artisan config:clear
sudo -u deploy_user_dagi php artisan cache:clear
sudo -u deploy_user_dagi php artisan view:clear
sudo -u deploy_user_dagi php artisan route:clear
echo "✓ Caches cleared"

echo ""
echo "Step 6: Restarting service..."
systemctl restart restaurant.service
sleep 3
if systemctl is-active --quiet restaurant.service; then
    echo "✓ Service restarted"
else
    echo "✗ Service failed to restart"
    exit 1
fi

echo ""
echo "Step 7: Testing asset loading..."
sleep 2

# Test CSS file
CSS_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8123/build/assets/app-*.css 2>/dev/null || echo "000")
if [ "$CSS_CODE" = "200" ] || [ "$CSS_CODE" = "404" ]; then
    echo "✓ CSS assets accessible (HTTP $CSS_CODE)"
else
    echo "⚠ CSS assets check: HTTP $CSS_CODE"
fi

# Test JS file  
JS_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8123/build/assets/app-*.js 2>/dev/null || echo "000")
if [ "$JS_CODE" = "200" ] || [ "$JS_CODE" = "404" ]; then
    echo "✓ JS assets accessible (HTTP $JS_CODE)"
else
    echo "⚠ JS assets check: HTTP $JS_CODE"
fi

# Check manifest
if [ -f "public/build/manifest.json" ] || [ -f "public/build/.vite/manifest.json" ]; then
    echo "✓ Manifest file exists"
else
    echo "✗ Manifest file missing"
fi

echo ""
echo "=========================================="
echo "Fix Complete!"
echo "=========================================="
echo ""
echo "If styles are still broken:"
echo "  1. Clear browser cache (Ctrl+Shift+R or Cmd+Shift+R)"
echo "  2. Try incognito/private window"
echo "  3. Check browser console for 404 errors on CSS/JS files"
echo ""
echo "To verify assets are loading, check:"
echo "  - http://restaurant.akmicroservice.com/build/assets/"
echo ""


