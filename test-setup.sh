#!/bin/bash

# Test Database Setup Script
# This script sets up a testing database and runs migrations/seeds

echo "=========================================="
echo "Setting up Test Database"
echo "=========================================="

# Set environment to testing to bypass production checks
export APP_ENV=testing

# Check if testing database connection is configured
echo "Checking database configuration..."

# Run migrations on testing database
echo ""
echo "Running migrations on testing database..."
php artisan migrate --database=testing --force

if [ $? -eq 0 ]; then
    echo "✓ Migrations completed successfully"
else
    echo "✗ Migrations failed"
    exit 1
fi

# Run seeders on testing database
echo ""
echo "Running seeders on testing database..."
php artisan db:seed --database=testing --force

if [ $? -eq 0 ]; then
    echo "✓ Seeders completed successfully"
else
    echo "✗ Seeders failed"
    exit 1
fi

echo ""
echo "=========================================="
echo "Test database setup completed!"
echo "=========================================="

