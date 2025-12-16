<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Restaurant;
use App\Models\Branch;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

echo "=== Creating Mobile App Test Users ===\n\n";

// Get first restaurant and branch
$restaurant = Restaurant::first();
if (!$restaurant) {
    echo "ERROR: No restaurant found. Please create a restaurant first.\n";
    exit(1);
}

$branch = Branch::where('restaurant_id', $restaurant->id)->first();
if (!$branch) {
    echo "ERROR: No branch found for restaurant ID {$restaurant->id}.\n";
    exit(1);
}

echo "Using Restaurant ID: {$restaurant->id}\n";
echo "Using Branch ID: {$branch->id}\n\n";

// Get or create roles
$waiterRole = Role::where('name', 'Waiter_' . $restaurant->id)->first();
$chefRole = Role::where('name', 'Chef_' . $restaurant->id)->first();
$cashierRole = Role::where('name', 'Cashier_' . $restaurant->id)->first();

// Create Cashier role if it doesn't exist
if (!$cashierRole) {
    $cashierRole = Role::create([
        'name' => 'Cashier_' . $restaurant->id,
        'display_name' => 'Cashier',
        'guard_name' => 'web',
        'restaurant_id' => $restaurant->id
    ]);
    echo "✓ Created Cashier role\n";
}

if (!$waiterRole) {
    echo "ERROR: Waiter role not found. Please run database seeders.\n";
    exit(1);
}

if (!$chefRole) {
    echo "ERROR: Chef role not found. Please run database seeders.\n";
    exit(1);
}

// Create test users
$users = [
    [
        'name' => 'Mobile Waiter',
        'email' => 'waiter.mobile@test.com',
        'password' => 'password123',
        'role' => $waiterRole,
        'role_name' => 'Waiter'
    ],
    [
        'name' => 'Mobile Chef',
        'email' => 'chef.mobile@test.com',
        'password' => 'password123',
        'role' => $chefRole,
        'role_name' => 'Chef'
    ],
    [
        'name' => 'Mobile Cashier',
        'email' => 'cashier.mobile@test.com',
        'password' => 'password123',
        'role' => $cashierRole,
        'role_name' => 'Cashier'
    ],
];

foreach ($users as $userData) {
    // Check if user already exists
    $existingUser = User::where('email', $userData['email'])->first();
    
    if ($existingUser) {
        echo "⚠ User {$userData['email']} already exists. Updating...\n";
        
        // Update existing user
        $existingUser->update([
            'name' => $userData['name'],
            'password' => Hash::make($userData['password']),
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
        ]);
        
        // Sync role
        $existingUser->syncRoles([$userData['role']]);
        
        echo "✓ Updated user: {$userData['name']} ({$userData['email']})\n";
        echo "  Role: {$userData['role_name']}\n";
        echo "  Password: {$userData['password']}\n\n";
    } else {
        // Create new user
        $user = User::create([
            'name' => $userData['name'],
            'email' => $userData['email'],
            'password' => Hash::make($userData['password']),
            'restaurant_id' => $restaurant->id,
            'branch_id' => $branch->id,
        ]);
        
        // Assign role
        $user->assignRole($userData['role']);
        
        echo "✓ Created user: {$userData['name']} ({$userData['email']})\n";
        echo "  Role: {$userData['role_name']}\n";
        echo "  Password: {$userData['password']}\n\n";
    }
}

echo "=== Test Users Created Successfully ===\n\n";
echo "You can now use these credentials to test the mobile app:\n\n";
echo "WAITER:\n";
echo "  Email: waiter.mobile@test.com\n";
echo "  Password: password123\n\n";
echo "CHEF:\n";
echo "  Email: chef.mobile@test.com\n";
echo "  Password: password123\n\n";
echo "CASHIER:\n";
echo "  Email: cashier.mobile@test.com\n";
echo "  Password: password123\n\n";

