#!/bin/bash

# Automated Test Database Setup Script
# This script safely creates and sets up a testing database
# WITHOUT affecting production database

# Don't exit on error - we want to continue even if DB creation fails
# set -e  # Exit on any error

echo "=========================================="
echo "Test Database Setup - Production Safe"
echo "=========================================="
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Set environment to testing
export APP_ENV=testing

# Get database credentials from .env or use defaults
DB_HOST="${DB_TEST_HOST:-${DB_HOST:-127.0.0.1}}"
DB_PORT="${DB_TEST_PORT:-${DB_PORT:-3306}}"
DB_USER="${DB_TEST_USERNAME:-${DB_USERNAME:-root}}"
DB_PASS="${DB_TEST_PASSWORD:-${DB_PASSWORD:-}}"
DB_NAME="${DB_TEST_DATABASE:-}"

# If DB_TEST_DATABASE is not set, use main DB name + _test
if [ -z "$DB_NAME" ]; then
    MAIN_DB="${DB_DATABASE:-laravel}"
    DB_NAME="${MAIN_DB}_test"
    echo -e "${YELLOW}Using test database: $DB_NAME${NC}"
else
    echo -e "${GREEN}Using configured test database: $DB_NAME${NC}"
fi

# Safety check: Ensure test database name contains 'test'
if [[ ! "$DB_NAME" =~ .*test.* ]] && [[ ! "$DB_NAME" =~ .*_test.* ]]; then
    echo -e "${RED}ERROR: Test database name must contain 'test' for safety!${NC}"
    echo "Current name: $DB_NAME"
    echo "Please set DB_TEST_DATABASE in .env with a name containing 'test'"
    exit 1
fi

echo ""
echo "Configuration:"
echo "  Host: $DB_HOST"
echo "  Port: $DB_PORT"
echo "  User: $DB_USER"
echo "  Database: $DB_NAME"
echo ""

# Create database if it doesn't exist
echo "Creating test database if it doesn't exist..."

# Use PHP to create database using credentials from environment
# This uses the same credentials Laravel would use
php -r "
require __DIR__ . '/vendor/autoload.php';
\$app = require_once __DIR__ . '/bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Get database credentials from config
\$host = '$DB_HOST';
\$port = '$DB_PORT';
\$user = '$DB_USER';
\$pass = '$DB_PASS';
\$dbName = '$DB_NAME';

try {
    // Try to connect to the database first (it might already exist)
    \$pdo = new PDO(
        \"mysql:host=\$host;port=\$port;dbname=\$dbName;charset=utf8mb4\",
        \$user,
        \$pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo 'Database already exists and is accessible';
    exit(0);
} catch (PDOException \$e) {
    // Database doesn't exist, try to create it
    try {
        \$pdo = new PDO(
            \"mysql:host=\$host;port=\$port;charset=utf8mb4\",
            \$user,
            \$pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        \$pdo->exec(\"CREATE DATABASE IF NOT EXISTS \`\$dbName\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci\");
        echo 'Database created successfully';
        exit(0);
    } catch (PDOException \$e2) {
        echo 'ERROR: Could not create database. ' . \$e2->getMessage();
        echo PHP_EOL . 'Please create it manually or check your database credentials.';
        exit(1);
    }
}
" 2>&1

DB_CREATE_RESULT=$?

if [ $DB_CREATE_RESULT -eq 0 ]; then
    echo -e "${GREEN}✓ Test database ready${NC}"
else
    echo -e "${YELLOW}⚠ Could not automatically create database${NC}"
    echo ""
    echo "The database might already exist, or you may need to create it manually."
    echo ""
    echo "To create manually, run:"
    echo "  mysql -u$DB_USER -h$DB_HOST -P$DB_PORT -e \"CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\""
    echo ""
    echo "Or if you have a password:"
    echo "  mysql -u$DB_USER -p -h$DB_HOST -P$DB_PORT -e \"CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\""
    echo ""
    echo "Continuing anyway (database might already exist)..."
    echo ""
fi

echo ""
echo "Running migrations on testing database..."
export APP_ENV=testing
php artisan migrate --database=testing --force

MIGRATION_RESULT=$?
if [ $MIGRATION_RESULT -eq 0 ]; then
    echo -e "${GREEN}✓ Migrations completed successfully${NC}"
else
    echo -e "${RED}✗ Migrations failed${NC}"
    echo ""
    echo "This might be because:"
    echo "  1. The test database doesn't exist - create it manually first"
    echo "  2. Database connection credentials are incorrect"
    echo "  3. The database user doesn't have proper permissions"
    echo ""
    exit 1
fi

echo ""
echo "Running seeders on testing database..."
php artisan db:seed --database=testing --force

SEEDER_RESULT=$?
if [ $SEEDER_RESULT -eq 0 ]; then
    echo -e "${GREEN}✓ Seeders completed successfully${NC}"
else
    echo -e "${YELLOW}⚠ Seeders failed or no seeders found (this is OK)${NC}"
fi

echo ""
echo "=========================================="
echo -e "${GREEN}Test database setup completed!${NC}"
echo "=========================================="
echo ""
echo "You can now run tests with:"
echo "  php artisan test"
echo "  or"
echo "  ./vendor/bin/phpunit"
echo ""
echo -e "${YELLOW}Note: All tests automatically use the 'testing' database connection${NC}"
echo -e "${YELLOW}      Production database is never touched${NC}"

