#!/bin/bash

# Application Test Script
# Tests the restaurant application to ensure it's working properly

echo "=========================================="
echo "Restaurant Application Test"
echo "=========================================="
echo ""

PROJECT_DIR="/home/deploy_user_dagi/services/table_track/restaurant"
cd "$PROJECT_DIR"

# Test 1: Service Status
echo "1. Service Status:"
if systemctl is-active --quiet restaurant.service; then
    echo "   ✓ Service is running"
else
    echo "   ✗ Service is NOT running"
    exit 1
fi

# Test 2: HTTP Response
echo ""
echo "2. HTTP Response:"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8123/)
if [ "$HTTP_CODE" = "200" ]; then
    echo "   ✓ HTTP Status: $HTTP_CODE (OK)"
else
    echo "   ✗ HTTP Status: $HTTP_CODE (ERROR)"
    exit 1
fi

# Test 3: Page Title
echo ""
echo "3. Page Title:"
TITLE=$(curl -s http://127.0.0.1:8123/ | grep -o "<title>.*</title>" | head -1)
if echo "$TITLE" | grep -q "TableTrack"; then
    echo "   ✓ Correct title found: $TITLE"
else
    echo "   ✗ Wrong title: $TITLE"
    exit 1
fi

# Test 4: Content Check
echo ""
echo "4. Content Check:"
CONTENT_COUNT=$(curl -s http://127.0.0.1:8123/ | grep -c "TableTrack")
if [ "$CONTENT_COUNT" -gt 0 ]; then
    echo "   ✓ TableTrack content found ($CONTENT_COUNT occurrences)"
else
    echo "   ✗ TableTrack content not found"
    exit 1
fi

# Test 5: Vite Manifest
echo ""
echo "5. Vite Manifest:"
if [ -f "public/build/manifest.json" ]; then
    MANIFEST_SIZE=$(stat -c%s "public/build/manifest.json")
    echo "   ✓ Manifest exists ($MANIFEST_SIZE bytes)"
else
    echo "   ✗ Manifest missing"
    exit 1
fi

# Test 6: Asset Files
echo ""
echo "6. Asset Files:"
JS_FILE=$(ls public/build/assets/*.js 2>/dev/null | head -1)
CSS_FILE=$(ls public/build/assets/*.css 2>/dev/null | head -1)
if [ -n "$JS_FILE" ] && [ -n "$CSS_FILE" ]; then
    echo "   ✓ JS file: $(basename $JS_FILE)"
    echo "   ✓ CSS file: $(basename $CSS_FILE)"
else
    echo "   ✗ Asset files missing"
    exit 1
fi

# Test 7: Error Check
echo ""
echo "7. Error Check:"
ERRORS=$(curl -s http://127.0.0.1:8123/ | grep -i "vite.*not found\|500.*error\|internal.*server.*error" | head -1)
if [ -z "$ERRORS" ]; then
    echo "   ✓ No errors in response"
else
    echo "   ✗ Errors found: $ERRORS"
    exit 1
fi

# Test 8: Laravel Welcome Page Check
echo ""
echo "8. Laravel Welcome Page Check:"
WELCOME_PAGE=$(curl -s http://127.0.0.1:8123/ | grep -i "laravel.*v[0-9]" | head -1)
if [ -z "$WELCOME_PAGE" ]; then
    echo "   ✓ Not showing default Laravel welcome page"
else
    echo "   ✗ Still showing default Laravel welcome page"
    exit 1
fi

echo ""
echo "=========================================="
echo "All Tests Passed! ✓"
echo "=========================================="
echo ""
echo "Application is working correctly at:"
echo "  - Local: http://127.0.0.1:8123"
echo "  - Domain: https://restaurant.akmicroservice.com"
echo ""


