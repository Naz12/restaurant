#!/bin/bash

# Fix Permissions Script for Restaurant Application
# Run this with: sudo ./fix-permissions.sh

set -e

echo "=========================================="
echo "Fixing Permissions for Restaurant App"
echo "=========================================="

PROJECT_DIR="/home/deploy_user_dagi/services/table_track/restaurant"

if [ "$EUID" -ne 0 ]; then 
    echo "Please run as root (use sudo)"
    exit 1
fi

echo "Setting ownership to deploy_user_dagi..."
chown -R deploy_user_dagi:deploy_user_dagi "$PROJECT_DIR/storage"
chown -R deploy_user_dagi:deploy_user_dagi "$PROJECT_DIR/bootstrap/cache"

echo "Setting permissions..."
chmod -R 775 "$PROJECT_DIR/storage"
chmod -R 775 "$PROJECT_DIR/bootstrap/cache"

echo "Clearing Laravel caches..."
cd "$PROJECT_DIR"
sudo -u deploy_user_dagi php artisan view:clear
sudo -u deploy_user_dagi php artisan config:clear
sudo -u deploy_user_dagi php artisan cache:clear

echo "Restarting service..."
systemctl restart restaurant.service

echo ""
echo "=========================================="
echo "Permissions fixed! Service restarted."
echo "=========================================="
echo ""
echo "Check the service status:"
echo "  sudo systemctl status restaurant.service"
echo ""
echo "View logs if needed:"
echo "  sudo journalctl -u restaurant.service -f"
echo ""

