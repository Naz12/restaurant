#!/bin/bash

# Restaurant Deployment Script
# This script deploys the restaurant Laravel application following the zooysbackend pattern

set -e  # Exit on error

echo "=========================================="
echo "Restaurant Deployment Script"
echo "=========================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Project paths
PROJECT_DIR="/home/deploy_user_dagi/services/table_track/restaurant"
SERVICE_DIR="/etc/systemd/system"
NGINX_AVAILABLE="/etc/nginx/sites-available"
NGINX_ENABLED="/etc/nginx/sites-enabled"
NGINX_CONF_D="/etc/nginx/conf.d"

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo -e "${RED}Please run as root (use sudo)${NC}"
    exit 1
fi

echo -e "${GREEN}Step 1: Installing dependencies...${NC}"
cd "$PROJECT_DIR"
if [ ! -d "vendor" ]; then
    echo "Installing composer dependencies..."
    composer install --no-dev --optimize-autoloader
    echo -e "${GREEN}✓ Dependencies installed${NC}"
else
    echo -e "${YELLOW}Dependencies already installed, skipping...${NC}"
fi

echo -e "${GREEN}Step 1b: Installing and building frontend assets...${NC}"
cd "$PROJECT_DIR"
if [ ! -d "node_modules" ]; then
    echo "Installing npm dependencies..."
    npm install
    echo -e "${GREEN}✓ NPM dependencies installed${NC}"
fi
if [ ! -f "public/build/manifest.json" ]; then
    echo "Building frontend assets..."
    npm run build
    echo -e "${GREEN}✓ Frontend assets built${NC}"
else
    echo -e "${YELLOW}Frontend assets already built, skipping...${NC}"
fi

echo -e "${GREEN}Step 2: Setting up directories and permissions...${NC}"
cd "$PROJECT_DIR"
mkdir -p bootstrap/cache storage/framework/{sessions,views,cache} storage/logs
chmod -R 775 storage bootstrap/cache 2>/dev/null || true
chown -R deploy_user_dagi:deploy_user_dagi storage bootstrap/cache 2>/dev/null || true
# Also ensure www-data can write if needed
chmod -R g+w storage/framework/views storage/framework/cache storage/framework/sessions 2>/dev/null || true
echo -e "${GREEN}✓ Directories and permissions set${NC}"

echo -e "${GREEN}Step 3: Clearing Laravel caches...${NC}"
cd "$PROJECT_DIR"
php artisan config:clear 2>/dev/null || true
php artisan cache:clear 2>/dev/null || true
echo -e "${GREEN}✓ Caches cleared${NC}"

echo -e "${GREEN}Step 4: Copying systemd service files...${NC}"
cp "$PROJECT_DIR/restaurant.service" "$SERVICE_DIR/restaurant.service"
cp "$PROJECT_DIR/restaurant-queue.service" "$SERVICE_DIR/restaurant-queue.service"
echo -e "${GREEN}✓ Service files copied${NC}"

echo -e "${GREEN}Step 5: Reloading systemd and enabling services...${NC}"
systemctl daemon-reload
systemctl enable restaurant.service
systemctl enable restaurant-queue.service
echo -e "${GREEN}✓ Services enabled${NC}"

echo -e "${GREEN}Step 6: Setting up nginx configuration...${NC}"

# Check which nginx structure is used
if [ -d "$NGINX_AVAILABLE" ]; then
    # Using sites-available/sites-enabled pattern
    cp "$PROJECT_DIR/nginx-restaurant.conf" "$NGINX_AVAILABLE/restaurant.akmicroservice.com.conf"
    
    # Create symlink if it doesn't exist
    if [ ! -L "$NGINX_ENABLED/restaurant.akmicroservice.com.conf" ]; then
        ln -s "$NGINX_AVAILABLE/restaurant.akmicroservice.com.conf" "$NGINX_ENABLED/restaurant.akmicroservice.com.conf"
    fi
    echo -e "${GREEN}✓ Nginx config copied to sites-available${NC}"
elif [ -d "$NGINX_CONF_D" ]; then
    # Using conf.d pattern
    cp "$PROJECT_DIR/nginx-restaurant.conf" "$NGINX_CONF_D/restaurant.akmicroservice.com.conf"
    echo -e "${GREEN}✓ Nginx config copied to conf.d${NC}"
else
    echo -e "${YELLOW}Warning: Could not determine nginx configuration directory${NC}"
    echo "Please manually copy nginx-restaurant.conf to your nginx configuration directory"
fi

echo -e "${GREEN}Step 7: Testing nginx configuration...${NC}"
if nginx -t; then
    echo -e "${GREEN}✓ Nginx configuration is valid${NC}"
    systemctl reload nginx
    echo -e "${GREEN}✓ Nginx reloaded${NC}"
else
    echo -e "${RED}✗ Nginx configuration test failed${NC}"
    exit 1
fi

echo -e "${GREEN}Step 8: Starting services...${NC}"
systemctl restart restaurant.service
systemctl restart restaurant-queue.service
echo -e "${GREEN}✓ Services started${NC}"

echo -e "${GREEN}Step 9: Checking service status...${NC}"
echo ""
echo "--- Restaurant Service Status ---"
systemctl status restaurant.service --no-pager -l || true
echo ""
echo "--- Restaurant Queue Service Status ---"
systemctl status restaurant-queue.service --no-pager -l || true

echo ""
echo -e "${GREEN}=========================================="
echo "Deployment completed successfully!"
echo "==========================================${NC}"
echo ""
echo "Services are running:"
echo "  - Restaurant app: http://127.0.0.1:8123"
echo "  - Domain: https://restaurant.akmicroservice.com"
echo ""
echo "Useful commands:"
echo "  - Check status: sudo systemctl status restaurant.service"
echo "  - View logs: sudo journalctl -u restaurant.service -f"
echo "  - Restart: sudo systemctl restart restaurant.service"
echo ""
echo -e "${YELLOW}Note: If you need SSL, run:${NC}"
echo "  sudo certbot --nginx -d restaurant.akmicroservice.com"
echo ""

