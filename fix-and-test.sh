#!/bin/bash

# Complete Fix and Test Script for Restaurant Application
# Run with: sudo ./fix-and-test.sh

set -e

echo "=========================================="
echo "Restaurant Application - Complete Fix & Test"
echo "=========================================="

PROJECT_DIR="/home/deploy_user_dagi/services/table_track/restaurant"

if [ "$EUID" -ne 0 ]; then 
    echo "Please run as root (use sudo)"
    exit 1
fi

cd "$PROJECT_DIR"

echo ""
echo "Step 1: Fixing permissions..."
chown -R deploy_user_dagi:deploy_user_dagi storage bootstrap/cache public/build 2>/dev/null || true
chmod -R 775 storage bootstrap/cache public/build 2>/dev/null || true
echo "✓ Permissions fixed"

echo ""
echo "Step 2: Ensuring frontend assets are built..."
if [ ! -f "public/build/manifest.json" ]; then
    echo "Building frontend assets..."
    sudo -u deploy_user_dagi npm install
    sudo -u deploy_user_dagi npm run build
    echo "✓ Frontend assets built"
else
    echo "✓ Frontend assets already exist"
fi

echo ""
echo "Step 3: Clearing all Laravel caches..."
sudo -u deploy_user_dagi php artisan config:clear
sudo -u deploy_user_dagi php artisan cache:clear
sudo -u deploy_user_dagi php artisan view:clear
sudo -u deploy_user_dagi php artisan route:clear
echo "✓ Caches cleared"

echo ""
echo "Step 4: Restarting service..."
systemctl restart restaurant.service
sleep 3
echo "✓ Service restarted"

echo ""
echo "Step 5: Testing application..."
echo ""

# Test 1: Check service status
echo "--- Service Status ---"
systemctl is-active restaurant.service && echo "✓ Service is running" || echo "✗ Service is not running"

# Test 2: Check HTTP response
echo ""
echo "--- HTTP Response Test ---"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8123/)
if [ "$HTTP_CODE" = "200" ]; then
    echo "✓ HTTP Status: $HTTP_CODE (OK)"
else
    echo "✗ HTTP Status: $HTTP_CODE (ERROR)"
fi

# Test 3: Check content
echo ""
echo "--- Content Test ---"
CONTENT=$(curl -s http://127.0.0.1:8123/ | grep -i "TableTrack" | head -1)
if [ -n "$CONTENT" ]; then
    echo "✓ TableTrack content found"
else
    echo "✗ TableTrack content not found"
fi

# Test 4: Check for errors
echo ""
echo "--- Error Check ---"
ERRORS=$(curl -s http://127.0.0.1:8123/ | grep -i "error\|exception\|vite.*not found" | head -1)
if [ -z "$ERRORS" ]; then
    echo "✓ No errors found in response"
else
    echo "✗ Errors found: $ERRORS"
fi

# Test 5: Check manifest file
echo ""
echo "--- Asset Manifest Check ---"
if [ -f "public/build/manifest.json" ]; then
    echo "✓ Vite manifest file exists"
    MANIFEST_SIZE=$(stat -c%s "public/build/manifest.json")
    echo "  Manifest size: $MANIFEST_SIZE bytes"
else
    echo "✗ Vite manifest file missing"
fi

echo ""
echo "=========================================="
echo "Fix and Test Complete!"
echo "=========================================="
echo ""
echo "Application URL: http://127.0.0.1:8123"
echo "Domain URL: https://restaurant.akmicroservice.com"
echo ""
echo "To view logs: sudo journalctl -u restaurant.service -f"
echo "To check status: sudo systemctl status restaurant.service"
echo ""


