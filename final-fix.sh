#!/bin/bash

# Final Fix Script - Run with sudo
# This script ensures everything is properly configured and working

set -e

echo "=========================================="
echo "Final Fix for Restaurant Application"
echo "=========================================="

PROJECT_DIR="/home/deploy_user_dagi/services/table_track/restaurant"

if [ "$EUID" -ne 0 ]; then 
    echo "Please run as root (use sudo)"
    exit 1
fi

cd "$PROJECT_DIR"

echo ""
echo "Step 1: Fixing all permissions..."
chown -R deploy_user_dagi:deploy_user_dagi storage bootstrap/cache public/build 2>/dev/null || true
chmod -R 775 storage bootstrap/cache public/build 2>/dev/null || true
echo "✓ Permissions fixed"

echo ""
echo "Step 2: Ensuring frontend assets..."
if [ ! -f "public/build/manifest.json" ]; then
    echo "Building assets..."
    sudo -u deploy_user_dagi npm install --silent
    sudo -u deploy_user_dagi npm run build
fi
echo "✓ Frontend assets ready"

echo ""
echo "Step 3: Clearing all caches..."
sudo -u deploy_user_dagi php artisan config:clear
sudo -u deploy_user_dagi php artisan cache:clear
sudo -u deploy_user_dagi php artisan view:clear
sudo -u deploy_user_dagi php artisan route:clear
echo "✓ Caches cleared"

echo ""
echo "Step 4: Restarting Laravel service..."
systemctl restart restaurant.service
sleep 2
if systemctl is-active --quiet restaurant.service; then
    echo "✓ Service restarted and running"
else
    echo "✗ Service failed to start"
    systemctl status restaurant.service --no-pager | head -10
    exit 1
fi

echo ""
echo "Step 5: Reloading nginx..."
systemctl reload nginx
if [ $? -eq 0 ]; then
    echo "✓ Nginx reloaded"
else
    echo "✗ Nginx reload failed"
    nginx -t
    exit 1
fi

echo ""
echo "Step 6: Testing application..."
sleep 2

# Test local
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8123/)
if [ "$HTTP_CODE" = "200" ]; then
    TITLE=$(curl -s http://127.0.0.1:8123/ | grep -o "<title>.*</title>" | head -1)
    if echo "$TITLE" | grep -q "TableTrack"; then
        echo "✓ Local test passed: $TITLE"
    else
        echo "✗ Local test failed: Wrong title - $TITLE"
        exit 1
    fi
else
    echo "✗ Local test failed: HTTP $HTTP_CODE"
    exit 1
fi

# Test via nginx
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://restaurant.akmicroservice.com/)
if [ "$HTTP_CODE" = "200" ]; then
    TITLE=$(curl -s http://restaurant.akmicroservice.com/ | grep -o "<title>.*</title>" | head -1)
    if echo "$TITLE" | grep -q "TableTrack"; then
        echo "✓ Domain test passed: $TITLE"
    else
        echo "✗ Domain test failed: Wrong title - $TITLE"
        echo "  This might be a caching issue. Try clearing browser cache."
    fi
else
    echo "⚠ Domain test: HTTP $HTTP_CODE (might need SSL setup)"
fi

echo ""
echo "=========================================="
echo "Fix Complete!"
echo "=========================================="
echo ""
echo "Application URLs:"
echo "  - Local: http://127.0.0.1:8123"
echo "  - HTTP: http://restaurant.akmicroservice.com"
echo "  - HTTPS: https://restaurant.akmicroservice.com (may need SSL cert)"
echo ""
echo "If HTTPS still shows Laravel welcome page:"
echo "  1. Clear browser cache"
echo "  2. Try incognito/private window"
echo "  3. Run: sudo certbot --nginx -d restaurant.akmicroservice.com"
echo ""

