# Testing Setup Guide

## Automatic Test Database Configuration

The test suite is configured to **automatically use a separate testing database** to protect your production data.

## Quick Start

1. **Run the setup script:**
   ```bash
   ./setup-test-db.sh
   ```

   This script will:
   - Create a test database (if it doesn't exist)
   - Run all migrations on the test database
   - Run seeders on the test database
   - **Never touch your production database**

2. **Run tests:**
   ```bash
   php artisan test
   # or
   ./vendor/bin/phpunit
   ```

## How It Works

### Automatic Database Switching

1. **PHPUnit Configuration** (`phpunit.xml`):
   - Sets `APP_ENV=testing`
   - Sets `DB_CONNECTION=testing`
   - All tests automatically use the testing database

2. **TestCase Base Class** (`tests/TestCase.php`):
   - Forces all tests to use `testing` database connection
   - Ensures production database is never accessed

3. **Database Configuration** (`config/database.php`):
   - `testing` connection configured
   - Defaults to `{main_database}_test` if not configured
   - Uses separate credentials if `DB_TEST_*` env vars are set

### Test Database Naming

The test database name is determined by:

1. **If `DB_TEST_DATABASE` is set in `.env`:**
   - Uses that exact name (must contain 'test' for safety)

2. **If not set:**
   - Uses `{DB_DATABASE}_test`
   - Example: If production DB is `restaurant`, test DB is `restaurant_test`

### Safety Features

1. **Database name validation:**
   - Setup script checks that test database name contains 'test'
   - Prevents accidental use of production database

2. **Separate connection:**
   - Tests use `testing` connection
   - Production uses default connection
   - No cross-contamination possible

3. **Environment isolation:**
   - `APP_ENV=testing` for all tests
   - Production checks are bypassed automatically

## Configuration

### Option 1: Use Default (Recommended)

No configuration needed! The system will:
- Use your main database credentials
- Create/use `{your_database}_test` database
- Automatically switch during tests

### Option 2: Custom Test Database

Add to your `.env` file:

```env
# Test Database Configuration
DB_TEST_DRIVER=mysql
DB_TEST_HOST=127.0.0.1
DB_TEST_PORT=3306
DB_TEST_DATABASE=my_custom_test_db
DB_TEST_USERNAME=test_user
DB_TEST_PASSWORD=test_password
```

**Important:** The database name must contain 'test' for safety!

## Running Tests

### All Tests
```bash
php artisan test
```

### Specific Test File
```bash
php artisan test tests/Feature/Api/MobileApiTest.php
```

### With Coverage
```bash
php artisan test --coverage
```

## Manual Database Setup

If you prefer to set up manually:

```bash
# Set environment
export APP_ENV=testing

# Create database (replace with your test DB name)
mysql -u root -p -e "CREATE DATABASE restaurant_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Run migrations
php artisan migrate --database=testing --force

# Run seeders
php artisan db:seed --database=testing --force
```

## Troubleshooting

### "Database doesn't exist" error

Run the setup script:
```bash
./setup-test-db.sh
```

Or create the database manually:
```sql
CREATE DATABASE your_database_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### "Access denied" error

Make sure your database user has CREATE DATABASE permission, or create the database manually first.

### Tests still using production database

Check:
1. `phpunit.xml` has `DB_CONNECTION=testing`
2. `tests/TestCase.php` sets database to 'testing'
3. `.env` doesn't override `DB_CONNECTION` in testing

### Production data was affected

This should never happen, but if it does:
1. Check that test database name contains 'test'
2. Verify `phpunit.xml` has `DB_CONNECTION=testing`
3. Check that `tests/TestCase.php` is forcing testing connection

## Best Practices

1. **Always use the setup script** for initial setup
2. **Never run tests without `APP_ENV=testing`**
3. **Use separate test database** (automatic)
4. **Regular backups** of production (unrelated to testing)
5. **Review test database name** before first run

## Production Safety Guarantee

✅ Tests **never** touch production database  
✅ Automatic database switching  
✅ Name validation prevents mistakes  
✅ Separate connection configuration  
✅ Environment isolation  

Your production data is **100% safe** when running tests!

