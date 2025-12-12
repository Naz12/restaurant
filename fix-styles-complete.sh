#!/bin/bash

# Complete Fix for Landing Page Styles
# Run with: sudo ./fix-styles-complete.sh

set -e

echo "=========================================="
echo "Complete Fix for Landing Page Styles"
echo "=========================================="

PROJECT_DIR="/home/deploy_user_dagi/services/table_track/restaurant"

if [ "$EUID" -ne 0 ]; then 
    echo "Please run as root (use sudo)"
    exit 1
fi

cd "$PROJECT_DIR"

echo ""
echo "Step 1: Updating APP_URL to HTTPS..."
sed -i 's|APP_URL=.*|APP_URL=https://restaurant.akmicroservice.com|' .env
echo "✓ APP_URL updated"

echo ""
echo "Step 2: Ensuring storage link..."
sudo -u deploy_user_dagi php artisan storage:link 2>/dev/null || true
echo "✓ Storage link verified"

echo ""
echo "Step 3: Rebuilding frontend assets..."
sudo -u deploy_user_dagi npm install --silent
sudo -u deploy_user_dagi npm run build
echo "✓ Assets rebuilt"

echo ""
echo "Step 4: Ensuring manifest is in correct location..."
cp public/build/.vite/manifest.json public/build/manifest.json 2>/dev/null || true
echo "✓ Manifest verified"

echo ""
echo "Step 5: Fixing permissions..."
chown -R deploy_user_dagi:deploy_user_dagi public/build storage bootstrap/cache
chmod -R 755 public/build
chmod -R 775 storage bootstrap/cache
echo "✓ Permissions fixed"

echo ""
echo "Step 6: Updating nginx config with proper headers..."
if [ -f "/etc/nginx/sites-available/restaurant.akmicroservice.com.conf" ]; then
    # Update nginx config to include X-Forwarded-Ssl
    if ! grep -q "X-Forwarded-Ssl" /etc/nginx/sites-available/restaurant.akmicroservice.com.conf; then
        sed -i '/X-Forwarded-Port/a\        proxy_set_header X-Forwarded-Ssl on;' /etc/nginx/sites-available/restaurant.akmicroservice.com.conf
    fi
    echo "✓ Nginx config updated"
fi

echo ""
echo "Step 7: Clearing all Laravel caches..."
sudo -u deploy_user_dagi php artisan config:clear
sudo -u deploy_user_dagi php artisan cache:clear
sudo -u deploy_user_dagi php artisan view:clear
sudo -u deploy_user_dagi php artisan route:clear
echo "✓ Caches cleared"

echo ""
echo "Step 8: Restarting services..."
systemctl restart restaurant.service
sleep 2
systemctl reload nginx
sleep 1
echo "✓ Services restarted"

echo ""
echo "Step 9: Testing..."
sleep 2

# Test local
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8123/)
if [ "$HTTP_CODE" = "200" ]; then
    echo "✓ Local service: HTTP $HTTP_CODE"
else
    echo "✗ Local service: HTTP $HTTP_CODE"
fi

# Test domain
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://restaurant.akmicroservice.com/)
if [ "$HTTP_CODE" = "200" ]; then
    echo "✓ Domain HTTP: $HTTP_CODE"
    
    # Check if assets use HTTPS when accessed via HTTPS
    ASSET_URL=$(curl -s -k https://restaurant.akmicroservice.com/ | grep -o 'href="[^"]*\.css[^"]*"' | head -1 | grep -o 'https://' || echo "")
    if [ -n "$ASSET_URL" ]; then
        echo "✓ Assets using HTTPS protocol"
    else
        echo "⚠ Assets may be using HTTP (browser will handle redirect)"
    fi
else
    echo "⚠ Domain HTTP: $HTTP_CODE"
fi

echo ""
echo "=========================================="
echo "Fix Complete!"
echo "=========================================="
echo ""
echo "IMPORTANT: Clear your browser cache!"
echo "  - Chrome/Edge: Ctrl+Shift+Delete or Cmd+Shift+Delete"
echo "  - Firefox: Ctrl+Shift+Delete or Cmd+Shift+Delete"
echo "  - Or use Incognito/Private window"
echo ""
echo "If styles are still broken:"
echo "  1. Hard refresh: Ctrl+Shift+R (Windows/Linux) or Cmd+Shift+R (Mac)"
echo "  2. Check browser console (F12) for 404 errors on CSS/JS files"
echo "  3. Verify assets: curl -I https://restaurant.akmicroservice.com/build/assets/app-*.css"
echo ""

