<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Restaurant;
use App\Models\Branch;
use App\Models\User;
use App\Models\Table;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemModifierOption;
use App\Models\MenuItem;
use App\Models\MenuItemVariation;
use App\Models\ModifierOption;
use App\Models\Kot;
use App\Models\KotItem;
use App\Models\Payment;
use App\Models\OrderType;
use App\Models\WaiterRequest;
use App\Enums\OrderStatus;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

echo "=== Populating Demo Restaurant with Test Data ===\n\n";

// Get Demo Restaurant and Branch
$restaurant = Restaurant::find(1);
if (!$restaurant) {
    echo "ERROR: Demo Restaurant (ID: 1) not found.\n";
    exit(1);
}

$branch = Branch::where('restaurant_id', 1)->first();
if (!$branch) {
    echo "ERROR: Branch not found for Restaurant ID 1.\n";
    exit(1);
}

echo "Restaurant: {$restaurant->name}\n";
echo "Branch: {$branch->name}\n\n";

// Get test users
$waiter = User::where('email', 'waiter.mobile@test.com')->first();
$chef = User::where('email', 'chef.mobile@test.com')->first();
$cashier = User::where('email', 'cashier.mobile@test.com')->first();

if (!$waiter || !$chef || !$cashier) {
    echo "ERROR: Test users not found. Please run create-mobile-test-users.php first.\n";
    exit(1);
}

// Get required data
$tables = Table::where('branch_id', $branch->id)->get();
$menuItems = MenuItem::where('branch_id', $branch->id)->where('is_available', true)->get();
$orderType = OrderType::where('branch_id', $branch->id)->first();

if ($tables->isEmpty() || $menuItems->isEmpty() || !$orderType) {
    echo "ERROR: Missing required data (tables, menu items, or order type).\n";
    echo "Please run add-demo-data.php first.\n";
    exit(1);
}

echo "Found:\n";
echo "  - " . $tables->count() . " tables\n";
echo "  - " . $menuItems->count() . " menu items\n";
echo "  - Order Type: " . ($orderType->name ?? 'N/A') . "\n\n";

// Clean up existing test orders (optional - comment out if you want to keep existing data)
echo "Cleaning up old test orders...\n";
$oldOrders = Order::where('branch_id', $branch->id)
    ->whereIn('waiter_id', [$waiter->id, $chef->id, $cashier->id])
    ->where('created_at', '>=', Carbon::today()->subDays(7))
    ->get();

foreach ($oldOrders as $order) {
    Payment::where('order_id', $order->id)->delete();
    $orderItems = OrderItem::where('order_id', $order->id)->get();
    foreach ($orderItems as $item) {
        OrderItemModifierOption::where('order_item_id', $item->id)->delete();
    }
    OrderItem::where('order_id', $order->id)->delete();
    $kots = Kot::where('order_id', $order->id)->get();
    foreach ($kots as $kot) {
        \App\Models\KotItem::where('kot_id', $kot->id)->delete();
    }
    Kot::where('order_id', $order->id)->delete();
    WaiterRequest::where('table_id', $order->table_id)->delete();
}
Order::whereIn('id', $oldOrders->pluck('id'))->delete();
echo "✓ Cleaned up " . $oldOrders->count() . " old orders\n\n";

// ============================================
// CREATE ORDERS WITH DIFFERENT STATUSES
// ============================================
echo "Creating Orders...\n";

$orders = [];

// 1. Active Order (KOT status - in kitchen)
$table1 = $tables->random();
$order1 = Order::create([
    'order_number' => 'ORD-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT),
    'table_id' => $table1->id,
    'waiter_id' => $waiter->id,
    'branch_id' => $branch->id,
    'order_type_id' => $orderType->id,
    'number_of_pax' => rand(2, 6),
    'order_status' => OrderStatus::PREPARING,
    'date_time' => Carbon::now()->subMinutes(30),
    'sub_total' => 0,
    'total_tax_amount' => 0,
    'total' => 0,
    'created_at' => Carbon::now()->subMinutes(30),
]);

$items1 = $menuItems->random(rand(2, 4));
$subtotal1 = 0;
foreach ($items1 as $menuItem) {
    $variation = $menuItem->variations->isNotEmpty() ? $menuItem->variations->random() : null;
    $price = $variation ? (float)$variation->price : (float)$menuItem->price;
    $quantity = rand(1, 3);
    $itemTotal = $price * $quantity;
    $subtotal1 += $itemTotal;
    
    $orderItem = OrderItem::create([
        'order_id' => $order1->id,
        'menu_item_id' => $menuItem->id,
        'menu_item_variation_id' => $variation?->id,
        'quantity' => $quantity,
        'price' => $price,
        'amount' => $itemTotal,
        'total' => $itemTotal,
    ]);
    
    // Create KOT for this item
    $kot = Kot::firstOrCreate(
        [
            'order_id' => $order1->id,
            'branch_id' => $branch->id,
        ],
        [
            'kot_number' => Kot::generateKotNumber($branch),
            'status' => 'in_kitchen',
            'created_at' => Carbon::now()->subMinutes(25),
        ]
    );
    
    KotItem::create([
        'kot_id' => $kot->id,
        'order_item_id' => $orderItem->id,
        'menu_item_id' => $menuItem->id,
        'quantity' => $quantity,
    ]);
}

$tax1 = $subtotal1 * 0.1; // 10% tax
$total1 = $subtotal1 + $tax1;
$order1->update([
    'sub_total' => $subtotal1,
    'total_tax_amount' => $tax1,
    'total' => $total1,
]);

$orders[] = $order1;
echo "  ✓ Order #{$order1->order_number} - Status: KOT (in kitchen) - Table: {$table1->table_code}\n";

// 2. Order with Ready KOT
$table2 = $tables->where('id', '!=', $table1->id)->random();
$order2 = Order::create([
    'order_number' => 'ORD-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT),
    'table_id' => $table2->id,
    'waiter_id' => $waiter->id,
    'branch_id' => $branch->id,
    'order_type_id' => $orderType->id,
    'number_of_pax' => rand(2, 4),
    'order_status' => OrderStatus::PREPARING,
    'date_time' => Carbon::now()->subMinutes(45),
    'sub_total' => 0,
    'total_tax_amount' => 0,
    'total' => 0,
    'created_at' => Carbon::now()->subMinutes(45),
]);

$items2 = $menuItems->random(rand(2, 3));
$subtotal2 = 0;
foreach ($items2 as $menuItem) {
    $variation = $menuItem->variations->isNotEmpty() ? $menuItem->variations->random() : null;
    $price = $variation ? (float)$variation->price : (float)$menuItem->price;
    $quantity = rand(1, 2);
    $itemTotal = $price * $quantity;
    $subtotal2 += $itemTotal;
    
    $orderItem = OrderItem::create([
        'order_id' => $order2->id,
        'menu_item_id' => $menuItem->id,
        'menu_item_variation_id' => $variation?->id,
        'quantity' => $quantity,
        'price' => $price,
        'amount' => $itemTotal,
        'total' => $itemTotal,
    ]);
    
    // Create KOT with ready status
    $kot = Kot::firstOrCreate(
        [
            'order_id' => $order2->id,
            'branch_id' => $branch->id,
        ],
        [
            'kot_number' => Kot::generateKotNumber($branch),
            'status' => 'ready',
            'created_at' => Carbon::now()->subMinutes(40),
        ]
    );
    
    KotItem::create([
        'kot_id' => $kot->id,
        'order_item_id' => $orderItem->id,
        'menu_item_id' => $menuItem->id,
        'quantity' => $quantity,
    ]);
}

$tax2 = $subtotal2 * 0.1;
$total2 = $subtotal2 + $tax2;
$order2->update([
    'sub_total' => $subtotal2,
    'total_tax_amount' => $tax2,
    'total' => $total2,
]);

$orders[] = $order2;
echo "  ✓ Order #{$order2->order_number} - Status: KOT (ready) - Table: {$table2->table_code}\n";

// 3. Billed Order (completed with payment)
$table3 = $tables->whereNotIn('id', [$table1->id, $table2->id])->random();
$order3 = Order::create([
    'order_number' => 'ORD-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT),
    'table_id' => $table3->id,
    'waiter_id' => $waiter->id,
    'branch_id' => $branch->id,
    'order_type_id' => $orderType->id,
    'number_of_pax' => rand(2, 5),
    'order_status' => OrderStatus::SERVED,
    'date_time' => Carbon::now()->subHours(2),
    'sub_total' => 0,
    'total_tax_amount' => 0,
    'total' => 0,
    'created_at' => Carbon::now()->subHours(2),
]);

$items3 = $menuItems->random(rand(3, 5));
$subtotal3 = 0;
foreach ($items3 as $menuItem) {
    $variation = $menuItem->variations->isNotEmpty() ? $menuItem->variations->random() : null;
    $price = $variation ? (float)$variation->price : (float)$menuItem->price;
    $quantity = rand(1, 2);
    $itemTotal = $price * $quantity;
    $subtotal3 += $itemTotal;
    
    OrderItem::create([
        'order_id' => $order3->id,
        'menu_item_id' => $menuItem->id,
        'menu_item_variation_id' => $variation?->id,
        'quantity' => $quantity,
        'price' => $price,
        'amount' => $itemTotal,
        'total' => $itemTotal,
    ]);
}

$tax3 = $subtotal3 * 0.1;
$total3 = $subtotal3 + $tax3;
$order3->update([
    'sub_total' => $subtotal3,
    'total_tax_amount' => $tax3,
    'total' => $total3,
]);

// Create payment for this order
Payment::create([
    'order_id' => $order3->id,
    'branch_id' => $branch->id,
    'payment_method' => 'cash',
    'amount' => $total3,
    'status' => 'completed',
    'created_by' => $cashier->id,
    'created_at' => Carbon::now()->subHours(1),
]);

$orders[] = $order3;
echo "  ✓ Order #{$order3->order_number} - Status: BILLED (paid) - Table: {$table3->table_code}\n";

// 4. New Order (just placed)
$table4 = $tables->whereNotIn('id', [$table1->id, $table2->id, $table3->id])->random();
$order4 = Order::create([
    'order_number' => 'ORD-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT),
    'table_id' => $table4->id,
    'waiter_id' => $waiter->id,
    'branch_id' => $branch->id,
    'order_type_id' => $orderType->id,
    'number_of_pax' => rand(2, 4),
    'order_status' => OrderStatus::PLACED,
    'date_time' => Carbon::now()->subMinutes(5),
    'sub_total' => 0,
    'total_tax_amount' => 0,
    'total' => 0,
    'created_at' => Carbon::now()->subMinutes(5),
]);

$items4 = $menuItems->random(rand(2, 3));
$subtotal4 = 0;
foreach ($items4 as $menuItem) {
    $variation = $menuItem->variations->isNotEmpty() ? $menuItem->variations->random() : null;
    $price = $variation ? (float)$variation->price : (float)$menuItem->price;
    $quantity = rand(1, 2);
    $itemTotal = $price * $quantity;
    $subtotal4 += $itemTotal;
    
    OrderItem::create([
        'order_id' => $order4->id,
        'menu_item_id' => $menuItem->id,
        'menu_item_variation_id' => $variation?->id,
        'quantity' => $quantity,
        'price' => $price,
        'amount' => $itemTotal,
        'total' => $itemTotal,
    ]);
}

$tax4 = $subtotal4 * 0.1;
$total4 = $subtotal4 + $tax4;
$order4->update([
    'sub_total' => $subtotal4,
    'total_tax_amount' => $tax4,
    'total' => $total4,
]);

$orders[] = $order4;
echo "  ✓ Order #{$order4->order_number} - Status: PLACED (new) - Table: {$table4->table_code}\n";

// 5. Order with Partial Payment
$table5 = $tables->whereNotIn('id', [$table1->id, $table2->id, $table3->id, $table4->id])->random();
$order5 = Order::create([
    'order_number' => 'ORD-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT),
    'table_id' => $table5->id,
    'waiter_id' => $waiter->id,
    'branch_id' => $branch->id,
    'order_type_id' => $orderType->id,
    'number_of_pax' => rand(3, 6),
    'order_status' => OrderStatus::SERVED,
    'date_time' => Carbon::now()->subHours(3),
    'sub_total' => 0,
    'total_tax_amount' => 0,
    'total' => 0,
    'created_at' => Carbon::now()->subHours(3),
]);

$items5 = $menuItems->random(rand(4, 6));
$subtotal5 = 0;
foreach ($items5 as $menuItem) {
    $variation = $menuItem->variations->isNotEmpty() ? $menuItem->variations->random() : null;
    $price = $variation ? (float)$variation->price : (float)$menuItem->price;
    $quantity = rand(1, 3);
    $itemTotal = $price * $quantity;
    $subtotal5 += $itemTotal;
    
    OrderItem::create([
        'order_id' => $order5->id,
        'menu_item_id' => $menuItem->id,
        'menu_item_variation_id' => $variation?->id,
        'quantity' => $quantity,
        'price' => $price,
        'amount' => $itemTotal,
        'total' => $itemTotal,
    ]);
}

$tax5 = $subtotal5 * 0.1;
$total5 = $subtotal5 + $tax5;
$order5->update([
    'sub_total' => $subtotal5,
    'total_tax_amount' => $tax5,
    'total' => $total5,
]);

// Create partial payment
$partialAmount = $total5 * 0.6; // 60% paid
Payment::create([
    'order_id' => $order5->id,
    'branch_id' => $branch->id,
    'payment_method' => 'card',
    'amount' => $partialAmount,
    'status' => 'completed',
    'created_by' => $cashier->id,
    'created_at' => Carbon::now()->subHours(2),
]);

$orders[] = $order5;
echo "  ✓ Order #{$order5->order_number} - Status: BILLED (partial payment) - Table: {$table5->table_code}\n";

// ============================================
// CREATE WAITER REQUESTS
// ============================================
echo "\nCreating Waiter Requests...\n";

$requestTypes = ['bill', 'service', 'water', 'assistance'];
$availableTables = $tables->whereNotIn('id', array_column($orders, 'table_id'))->take(3);

foreach ($availableTables as $table) {
    $requestType = $requestTypes[array_rand($requestTypes)];
    $request = WaiterRequest::create([
        'table_id' => $table->id,
        'branch_id' => $branch->id,
        'request_type' => $requestType,
        'created_at' => Carbon::now()->subMinutes(rand(5, 30)),
    ]);
    echo "  ✓ Waiter Request - Table: {$table->table_code}, Type: {$requestType}\n";
}

// Note: Waiter requests are created as pending by default

// ============================================
// SUMMARY
// ============================================
echo "\n=== Summary ===\n";
echo "Orders Created: " . count($orders) . "\n";
echo "  - PREPARING (in kitchen): 1\n";
echo "  - PREPARING (ready): 1\n";
echo "  - SERVED (fully paid): 1\n";
echo "  - PLACED (new): 1\n";
echo "  - SERVED (partial payment): 1\n";
echo "Payments Created: 2\n";
echo "Waiter Requests Created: " . WaiterRequest::where('branch_id', $branch->id)->where('created_at', '>=', Carbon::today())->count() . "\n";
echo "\n✓ Demo data populated successfully!\n";
echo "\nYou can now test the mobile app with:\n";
echo "- Active orders in different statuses\n";
echo "- KOTs in kitchen and ready states\n";
echo "- Completed orders with payments\n";
echo "- Pending waiter requests\n";
echo "- Tables with various order statuses\n";

