#!/bin/bash

# Setup Cron Job for Laravel Scheduler
# This script sets up the recommended cron job with logging

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CRON_LOG="$SCRIPT_DIR/storage/logs/cron.log"
CRON_COMMAND="* * * * * cd $SCRIPT_DIR && /usr/bin/php artisan schedule:run >> $CRON_LOG 2>&1"
USER="deploy_user_dagi"

echo "=========================================="
echo "Laravel Scheduler Cron Job Setup"
echo "=========================================="
echo ""

# Check if running as root or with sudo
if [ "$EUID" -ne 0 ]; then 
    echo "⚠️  This script needs sudo privileges to set up cron job."
    echo "Please run: sudo $0"
    exit 1
fi

# Verify PHP path
if [ ! -f "/usr/bin/php" ]; then
    echo "❌ Error: PHP not found at /usr/bin/php"
    echo "Please update the PHP path in this script."
    exit 1
fi

# Verify application directory
if [ ! -f "$SCRIPT_DIR/artisan" ]; then
    echo "❌ Error: Laravel artisan file not found at $SCRIPT_DIR/artisan"
    exit 1
fi

# Create storage/logs directory if it doesn't exist
mkdir -p "$SCRIPT_DIR/storage/logs"
chown -R $USER:$USER "$SCRIPT_DIR/storage/logs"
chmod -R 775 "$SCRIPT_DIR/storage/logs"

echo "✅ Verified PHP path: /usr/bin/php"
echo "✅ Verified application directory: $SCRIPT_DIR"
echo ""

# Check if cron job already exists
EXISTING_CRON=$(sudo crontab -l -u $USER 2>/dev/null | grep "artisan schedule:run" || true)

if [ -n "$EXISTING_CRON" ]; then
    echo "⚠️  A cron job for Laravel scheduler already exists:"
    echo "$EXISTING_CRON"
    echo ""
    read -p "Do you want to replace it? (y/n): " -n 1 -r
    echo ""
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "❌ Setup cancelled. Existing cron job kept."
        exit 0
    fi
    
    # Remove existing cron job
    (sudo crontab -l -u $USER 2>/dev/null | grep -v "artisan schedule:run" || true) | sudo crontab -u $USER -
    echo "✅ Removed existing cron job"
fi

# Add new cron job
(sudo crontab -l -u $USER 2>/dev/null || true; echo "$CRON_COMMAND") | sudo crontab -u $USER -

echo "✅ Cron job added successfully!"
echo ""
echo "Cron job details:"
echo "  User: $USER"
echo "  Schedule: Every minute (* * * * *)"
echo "  Command: /usr/bin/php $SCRIPT_DIR/artisan schedule:run"
echo "  Log file: $CRON_LOG"
echo ""

# Verify the cron job was added
echo "Verifying cron job setup..."
CRON_LIST=$(sudo crontab -l -u $USER 2>/dev/null | grep "artisan schedule:run" || true)

if [ -n "$CRON_LIST" ]; then
    echo "✅ Cron job verified:"
    echo "$CRON_LIST"
    echo ""
else
    echo "❌ Error: Cron job was not added correctly"
    exit 1
fi

# Test the scheduler manually
echo "Testing scheduler manually..."
if sudo -u $USER bash -c "cd '$SCRIPT_DIR' && /usr/bin/php artisan schedule:run" >> "$CRON_LOG" 2>&1; then
    echo "✅ Scheduler test successful!"
    echo ""
else
    echo "⚠️  Warning: Scheduler test had issues. Check the log file: $CRON_LOG"
    echo ""
fi

# Show recent log entries if log file exists
if [ -f "$CRON_LOG" ]; then
    echo "Recent log entries (last 5 lines):"
    tail -n 5 "$CRON_LOG" 2>/dev/null || echo "  (Log file is empty or not readable)"
    echo ""
fi

echo "=========================================="
echo "Setup Complete!"
echo "=========================================="
echo ""
echo "📋 Next steps:"
echo "  1. Monitor the cron log: tail -f $CRON_LOG"
echo "  2. Check cron is running: sudo crontab -l -u $USER"
echo "  3. View scheduled tasks: sudo -u $USER php $SCRIPT_DIR/artisan schedule:list"
echo ""
echo "The cron job will run every minute and execute scheduled tasks."
echo ""

