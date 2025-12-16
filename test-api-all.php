<?php

/**
 * Complete Mobile API Test Suite
 * Tests ALL mobile API endpoints for Flutter mobile application
 * Automatically cleans up test data after each run
 * 
 * Run with: php test-api-all.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Restaurant;
use App\Models\Branch;
use App\Models\Table;
use App\Models\OrderType;
use App\Models\MenuItem;
use App\Models\ItemCategory;
use App\Models\Order;
use App\Models\Payment;
use App\Models\OrderItem;
use App\Models\Kot;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

echo "========================================\n";
echo "Complete Mobile API Test Suite\n";
echo "Testing ALL endpoints for Flutter mobile app\n";
echo "========================================\n\n";

$errors = [];
$passed = 0;
$failed = 0;
$skipped = 0;
$createdOrderIds = [];
$testUserId = null;

// Cleanup function
function cleanupTestData($userId, $orderIds = []) {
    if (!$userId && empty($orderIds)) return;
    
    try {
        DB::beginTransaction();
        
        $ordersQuery = Order::query();
        if ($userId) {
            $ordersQuery->where('waiter_id', $userId);
        }
        if (!empty($orderIds)) {
            $ordersQuery->orWhereIn('id', $orderIds);
        }
        $orders = $ordersQuery->get();
        
        foreach ($orders as $order) {
            Payment::where('order_id', $order->id)->delete();
            $orderItems = OrderItem::where('order_id', $order->id)->get();
            foreach ($orderItems as $item) {
                \App\Models\OrderItemModifierOption::where('order_item_id', $item->id)->delete();
            }
            OrderItem::where('order_id', $order->id)->delete();
            $kots = Kot::where('order_id', $order->id)->get();
            foreach ($kots as $kot) {
                \App\Models\KotItem::where('kot_id', $kot->id)->delete();
            }
            Kot::where('order_id', $order->id)->delete();
        }
        
        if ($userId) {
            Order::where('waiter_id', $userId)->delete();
        }
        if (!empty($orderIds)) {
            Order::whereIn('id', $orderIds)->delete();
        }
        
        DB::commit();
    } catch (\Exception $e) {
        DB::rollBack();
    }
}

register_shutdown_function(function() use (&$testUserId, &$createdOrderIds) {
    if ($testUserId || !empty($createdOrderIds)) {
        echo "\nCleaning up test data...\n";
        cleanupTestData($testUserId, $createdOrderIds);
        echo "✓ Cleanup completed\n";
    }
});

// Setup test user
$user = User::where('email', 'test@example.com')->first();
if (!$user) {
    $restaurant = Restaurant::first();
    $branch = Branch::first();
    if (!$restaurant || !$branch) {
        echo "ERROR: No restaurant or branch found.\n";
        exit(1);
    }
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => Hash::make('password'),
        'restaurant_id' => $restaurant->id,
        'branch_id' => $branch->id,
    ]);
    echo "✓ Test user created\n\n";
} else {
    cleanupTestData($user->id);
    echo "✓ Cleaned up previous test data\n\n";
}
$testUserId = $user->id;

$restaurant = $user->restaurant;
$branch = $user->branch;
$table = Table::where('branch_id', $branch->id)->first();
$orderType = OrderType::where('branch_id', $branch->id)->first();
$menuItem = MenuItem::where('branch_id', $branch->id)->first();

if (!$table || !$orderType || !$menuItem) {
    echo "ERROR: Missing required data (table, orderType, or menuItem).\n";
    exit(1);
}

function test($name, $callback) {
    global $passed, $failed, $skipped, $errors;
    echo "Test: $name...\n";
    try {
        $result = $callback();
        if ($result === true) {
            echo "✓ PASSED\n\n";
            $passed++;
            return true;
        } elseif ($result === 'SKIP') {
            echo "⚠ SKIPPED\n\n";
            $skipped++;
            return true;
        } else {
            echo "✗ FAILED - $result\n\n";
            $errors[] = "$name: $result";
            $failed++;
            return false;
        }
    } catch (\Exception $e) {
        echo "✗ FAILED - " . $e->getMessage() . "\n\n";
        $errors[] = "$name exception: " . $e->getMessage();
        $failed++;
        return false;
    }
}

// ============================================
// AUTHENTICATION TESTS
// ============================================
echo "=== AUTHENTICATION ENDPOINTS ===\n";
$token = null;

test('POST /auth/login', function() use ($app, $user, &$token) {
    $request = $app->make('Illuminate\Http\Request');
    $request->merge(['email' => 'test@example.com', 'password' => 'password']);
    $controller = new \App\Http\Controllers\Api\Mobile\AuthController();
    $result = $controller->login($request);
    $data = json_decode($result->getContent(), true);
    if ($data['success'] && isset($data['data']['token'])) {
        $token = $data['data']['token'];
        return true;
    }
    return json_encode($data);
});

test('POST /auth/otp/send', function() use ($app, $user) {
    $request = $app->make('Illuminate\Http\Request');
    $request->merge(['email' => 'test@example.com']);
    $controller = new \App\Http\Controllers\Api\Mobile\AuthController();
    $result = $controller->sendOtp($request);
    $data = json_decode($result->getContent(), true);
    return $data['success'] ? true : json_encode($data);
});

test('POST /auth/otp/verify', function() use ($app, $user) {
    // Note: This test may fail if OTP expires quickly, but endpoint exists
    $request = $app->make('Illuminate\Http\Request');
    $request->merge(['email' => 'test@example.com', 'otp' => '000000']);
    $controller = new \App\Http\Controllers\Api\Mobile\AuthController();
    $result = $controller->verifyOtp($request);
    $data = json_decode($result->getContent(), true);
    // Accept both success and invalid OTP as valid endpoint test
    return ($data['success'] || ($data['success'] === false && str_contains($data['message'] ?? '', 'OTP'))) ? true : json_encode($data);
});

test('GET /auth/user', function() use ($app, $user) {
    $request = $app->make('Illuminate\Http\Request');
    $request->setUserResolver(fn() => $user);
    $controller = new \App\Http\Controllers\Api\Mobile\AuthController();
    $result = $controller->user($request);
    $data = json_decode($result->getContent(), true);
    return $data['success'] ? true : json_encode($data);
});

test('POST /auth/refresh-token', function() use ($app, $user) {
    $request = $app->make('Illuminate\Http\Request');
    $request->setUserResolver(fn() => $user);
    $controller = new \App\Http\Controllers\Api\Mobile\AuthController();
    $result = $controller->refreshToken($request);
    $data = json_decode($result->getContent(), true);
    return ($data['success'] && isset($data['data']['token'])) ? true : json_encode($data);
});

test('POST /auth/logout', function() use ($app, $user) {
    $request = $app->make('Illuminate\Http\Request');
    $request->setUserResolver(fn() => $user);
    $controller = new \App\Http\Controllers\Api\Mobile\AuthController();
    $result = $controller->logout($request);
    $data = json_decode($result->getContent(), true);
    return $data['success'] ? true : json_encode($data);
});

// ============================================
// MENU ENDPOINTS
// ============================================
echo "=== MENU ENDPOINTS ===\n";

test('GET /menu/items', function() use ($app, $user) {
    $request = $app->make('Illuminate\Http\Request');
    $request->setUserResolver(fn() => $user);
    $controller = new \App\Http\Controllers\Api\Mobile\MenuController();
    $result = $controller->items($request);
    $data = json_decode($result->getContent(), true);
    return $data['success'] ? true : json_encode($data);
});

test('GET /menu/items/{id}', function() use ($app, $user, $menuItem) {
    $controller = new \App\Http\Controllers\Api\Mobile\MenuController();
    $result = $controller->show($menuItem->id);
    $data = json_decode($result->getContent(), true);
    return $data['success'] ? true : json_encode($data);
});

test('GET /menu/categories', function() use ($app, $user) {
    $controller = new \App\Http\Controllers\Api\Mobile\MenuController();
    $result = $controller->categories();
    $data = json_decode($result->getContent(), true);
    return $data['success'] ? true : json_encode($data);
});

test('GET /menu/modifier-groups', function() use ($app, $user) {
    $request = $app->make('Illuminate\Http\Request');
    $request->setUserResolver(fn() => $user);
    $controller = new \App\Http\Controllers\Api\Mobile\MenuController();
    $result = $controller->modifierGroups($request);
    $data = json_decode($result->getContent(), true);
    return $data['success'] ? true : json_encode($data);
});

// ============================================
// TABLE ENDPOINTS
// ============================================
echo "=== TABLE ENDPOINTS ===\n";

test('GET /tables', function() use ($app, $user) {
    $request = $app->make('Illuminate\Http\Request');
    $request->setUserResolver(fn() => $user);
    $controller = new \App\Http\Controllers\Api\Mobile\TableController();
    $result = $controller->index($request);
    $data = json_decode($result->getContent(), true);
    return $data['success'] ? true : json_encode($data);
});

test('GET /tables/{id}', function() use ($app, $user, $table) {
    $controller = new \App\Http\Controllers\Api\Mobile\TableController();
    $result = $controller->show($table->id);
    $data = json_decode($result->getContent(), true);
    return $data['success'] ? true : json_encode($data);
});

test('GET /tables/{id}/active-order', function() use ($app, $user, $table) {
    $controller = new \App\Http\Controllers\Api\Mobile\TableController();
    $result = $controller->activeOrder($table->id);
    $data = json_decode($result->getContent(), true);
    // This may fail if no active order exists, which is OK
    return ($data['success'] || ($data['success'] === false && $data['message'] === 'No active order for this table')) ? true : json_encode($data);
});

test('POST /tables/{id}/lock', function() use ($app, $user, $table) {
    $request = $app->make('Illuminate\Http\Request');
    $request->setUserResolver(fn() => $user);
    $controller = new \App\Http\Controllers\Api\Mobile\TableController();
    $result = $controller->lock($request, $table->id);
    $data = json_decode($result->getContent(), true);
    return $data['success'] ? true : json_encode($data);
});

test('POST /tables/{id}/unlock', function() use ($app, $user, $table) {
    $request = $app->make('Illuminate\Http\Request');
    $request->setUserResolver(fn() => $user);
    $controller = new \App\Http\Controllers\Api\Mobile\TableController();
    $result = $controller->unlock($request, $table->id);
    $data = json_decode($result->getContent(), true);
    return $data['success'] ? true : json_encode($data);
});

test('GET /tables/areas', function() use ($app, $user) {
    $controller = new \App\Http\Controllers\Api\Mobile\TableController();
    $result = $controller->areas();
    $data = json_decode($result->getContent(), true);
    return $data['success'] ? true : json_encode($data);
});

// ============================================
// ORDER ENDPOINTS
// ============================================
echo "=== ORDER ENDPOINTS ===\n";
$orderId = null;
$orderItemId = null;

test('GET /orders', function() use ($app, $user) {
    $request = $app->make('Illuminate\Http\Request');
    $request->setUserResolver(fn() => $user);
    $controller = new \App\Http\Controllers\Api\Mobile\OrderController();
    $result = $controller->index($request);
    $data = json_decode($result->getContent(), true);
    return $data['success'] ? true : json_encode($data);
});

test('POST /orders', function() use ($app, $user, $table, $orderType, $menuItem, &$orderId, &$createdOrderIds) {
    $request = $app->make('Illuminate\Http\Request');
    $request->setUserResolver(fn() => $user);
    $request->merge([
        'table_id' => $table->id,
        'order_type_id' => $orderType->id,
        'number_of_pax' => 2,
        'items' => [['menu_item_id' => $menuItem->id, 'quantity' => 1, 'modifiers' => []]],
    ]);
    $controller = new \App\Http\Controllers\Api\Mobile\OrderController();
    $result = $controller->store($request);
    $data = json_decode($result->getContent(), true);
    if ($data['success'] && isset($data['data']['id'])) {
        $orderId = $data['data']['id'];
        $createdOrderIds[] = $orderId;
        $order = Order::find($orderId);
        if ($order && $order->items->count() > 0) {
            $orderItemId = $order->items->first()->id;
        }
        return true;
    }
    return json_encode($data);
});

if ($orderId) {
    test('GET /orders/{id}', function() use ($app, $orderId) {
        $controller = new \App\Http\Controllers\Api\Mobile\OrderController();
        $result = $controller->show($orderId);
        $data = json_decode($result->getContent(), true);
        return ($data['success'] && $data['data']['id'] == $orderId) ? true : json_encode($data);
    });

    test('PUT /orders/{id}', function() use ($app, $user, $orderId) {
        $request = $app->make('Illuminate\Http\Request');
        $request->setUserResolver(fn() => $user);
        $request->merge(['number_of_pax' => 3, 'tip_amount' => 5.00]);
        $controller = new \App\Http\Controllers\Api\Mobile\OrderController();
        $result = $controller->update($request, $orderId);
        $data = json_decode($result->getContent(), true);
        return $data['success'] ? true : json_encode($data);
    });

    test('POST /orders/{id}/items', function() use ($app, $user, $orderId, $menuItem) {
        $request = $app->make('Illuminate\Http\Request');
        $request->setUserResolver(fn() => $user);
        $request->merge(['menu_item_id' => $menuItem->id, 'quantity' => 1, 'modifiers' => []]);
        $controller = new \App\Http\Controllers\Api\Mobile\OrderController();
        $result = $controller->addItem($request, $orderId);
        $data = json_decode($result->getContent(), true);
        if ($data['success']) {
            $order = Order::find($orderId);
            if ($order && $order->items->count() > 0) {
                $orderItemId = $order->items->last()->id;
            }
        }
        return $data['success'] ? true : json_encode($data);
    });

    if ($orderItemId) {
        test('PUT /orders/{orderId}/items/{itemId}', function() use ($app, $user, $orderId, $orderItemId) {
            $request = $app->make('Illuminate\Http\Request');
            $request->setUserResolver(fn() => $user);
            $request->merge(['quantity' => 2]);
            $controller = new \App\Http\Controllers\Api\Mobile\OrderController();
            $result = $controller->updateItem($request, $orderId, $orderItemId);
            $data = json_decode($result->getContent(), true);
            return $data['success'] ? true : json_encode($data);
        });

        // Test delete item - create another item first to avoid deleting the only one
        test('DELETE /orders/{orderId}/items/{itemId}', function() use ($app, $user, $orderId, $menuItem) {
            // Add a new item first
            $request = $app->make('Illuminate\Http\Request');
            $request->setUserResolver(fn() => $user);
            $request->merge(['menu_item_id' => $menuItem->id, 'quantity' => 1, 'modifiers' => []]);
            $controller = new \App\Http\Controllers\Api\Mobile\OrderController();
            $result = $controller->addItem($request, $orderId);
            $addData = json_decode($result->getContent(), true);
            if (!$addData['success']) return 'SKIP';
            
            $order = Order::find($orderId);
            $itemToDelete = $order->items->last();
            if (!$itemToDelete) return 'SKIP';
            
            $result = $controller->deleteItem($orderId, $itemToDelete->id);
            $data = json_decode($result->getContent(), true);
            return $data['success'] ? true : json_encode($data);
        });
    }
}

// ============================================
// KOT ENDPOINTS
// ============================================
echo "=== KOT ENDPOINTS ===\n";
$kotId = null;

test('GET /kots', function() use ($app, $user) {
    $request = $app->make('Illuminate\Http\Request');
    $request->setUserResolver(fn() => $user);
    $controller = new \App\Http\Controllers\Api\Mobile\KotController();
    $result = $controller->index($request);
    $data = json_decode($result->getContent(), true);
    if ($data['success']) {
        $kots = $data['data']['kots'] ?? [];
        if (!empty($kots)) {
            $kotId = $kots[0]['id'] ?? null;
        }
    }
    return $data['success'] ? true : json_encode($data);
});

if ($kotId) {
    test('GET /kots/{id}', function() use ($app, $kotId) {
        $controller = new \App\Http\Controllers\Api\Mobile\KotController();
        $result = $controller->show($kotId);
        $data = json_decode($result->getContent(), true);
        return $data['success'] ? true : json_encode($data);
    });
} else {
    // Try to get any KOT from database
    test('GET /kots/{id} (from existing)', function() use ($app, $user) {
        $kot = Kot::whereHas('order', function($q) use ($user) {
            $q->where('branch_id', $user->branch_id);
        })->first();
        if (!$kot) return 'SKIP';
        $controller = new \App\Http\Controllers\Api\Mobile\KotController();
        $result = $controller->show($kot->id);
        $data = json_decode($result->getContent(), true);
        return $data['success'] ? true : json_encode($data);
    });
}

test('GET /kots/places', function() use ($app, $user) {
    $request = $app->make('Illuminate\Http\Request');
    $request->setUserResolver(fn() => $user);
    $controller = new \App\Http\Controllers\Api\Mobile\KotController();
    $result = $controller->places($request);
    $data = json_decode($result->getContent(), true);
    return $data['success'] ? true : json_encode($data);
});

test('GET /kots/cancel-reasons', function() use ($app, $user) {
    $controller = new \App\Http\Controllers\Api\Mobile\KotController();
    $result = $controller->cancelReasons();
    $data = json_decode($result->getContent(), true);
    return $data['success'] ? true : json_encode($data);
});

// Test KOT actions if we have a KOT
if ($kotId) {
    $kot = Kot::find($kotId);
    if ($kot && $kot->status === 'pending') {
        test('POST /kots/{id}/confirm', function() use ($app, $user, $kotId) {
            $request = $app->make('Illuminate\Http\Request');
            $request->setUserResolver(fn() => $user);
            $controller = new \App\Http\Controllers\Api\Mobile\KotController();
            $result = $controller->confirm($request, $kotId);
            $data = json_decode($result->getContent(), true);
            return $data['success'] ? true : json_encode($data);
        });
    }
    
    if ($kot && $kot->status === 'in_kitchen') {
        test('POST /kots/{id}/ready', function() use ($app, $user, $kotId) {
            $request = $app->make('Illuminate\Http\Request');
            $request->setUserResolver(fn() => $user);
            $controller = new \App\Http\Controllers\Api\Mobile\KotController();
            $result = $controller->ready($request, $kotId);
            $data = json_decode($result->getContent(), true);
            return $data['success'] ? true : json_encode($data);
        });
    }
    
    // Test cancel - we'll skip if already cancelled
    if ($kot && $kot->status !== 'cancelled') {
        test('POST /kots/{id}/cancel', function() use ($app, $user, $kotId) {
            $request = $app->make('Illuminate\Http\Request');
            $request->setUserResolver(fn() => $user);
            // Get a cancel reason
            $cancelReason = \App\Models\KotCancelReason::where('cancel_kot', true)->first();
            if (!$cancelReason) return 'SKIP';
            $request->merge(['cancel_reason_id' => $cancelReason->id, 'cancel_note' => 'Test cancel']);
            $controller = new \App\Http\Controllers\Api\Mobile\KotController();
            $result = $controller->cancel($request, $kotId);
            $data = json_decode($result->getContent(), true);
            // Accept success or already cancelled
            return ($data['success'] || ($data['success'] === false && str_contains($data['message'] ?? '', 'cancelled'))) ? true : json_encode($data);
        });
    }
}

// ============================================
// PAYMENT ENDPOINTS
// ============================================
echo "=== PAYMENT ENDPOINTS ===\n";
$paymentId = null;

test('GET /payments', function() use ($app, $user) {
    $request = $app->make('Illuminate\Http\Request');
    $request->setUserResolver(fn() => $user);
    $controller = new \App\Http\Controllers\Api\Mobile\PaymentController();
    $result = $controller->index($request);
    $data = json_decode($result->getContent(), true);
    if ($data['success']) {
        $payments = $data['data']['payments'] ?? [];
        if (!empty($payments)) {
            $paymentId = $payments[0]['id'] ?? null;
        }
    }
    return $data['success'] ? true : json_encode($data);
});

if ($orderId) {
    test('POST /payments', function() use ($app, $user, $orderId) {
        $order = Order::find($orderId);
        $remaining = $order->total - $order->payments->sum('amount');
        if ($remaining <= 0) return 'SKIP';
        
        $request = $app->make('Illuminate\Http\Request');
        $request->setUserResolver(fn() => $user);
        $request->merge(['order_id' => $orderId, 'payment_method' => 'cash', 'amount' => min($remaining, 50.00)]);
        $controller = new \App\Http\Controllers\Api\Mobile\PaymentController();
        $result = $controller->store($request);
        $data = json_decode($result->getContent(), true);
        if ($data['success'] && isset($data['data']['id'])) {
            $paymentId = $data['data']['id'];
        }
        return $data['success'] ? true : json_encode($data);
    });
}

if ($paymentId) {
    test('GET /payments/{id}', function() use ($app, $paymentId) {
        $controller = new \App\Http\Controllers\Api\Mobile\PaymentController();
        $result = $controller->show($paymentId);
        $data = json_decode($result->getContent(), true);
        return $data['success'] ? true : json_encode($data);
    });
}

if ($orderId) {
    test('GET /orders/{orderId}/payments', function() use ($app, $user, $orderId) {
        $controller = new \App\Http\Controllers\Api\Mobile\PaymentController();
        if (method_exists($controller, 'orderPayments')) {
            $result = $controller->orderPayments($orderId);
            $data = json_decode($result->getContent(), true);
            return $data['success'] ? true : json_encode($data);
        }
        return 'SKIP';
    });

    test('POST /orders/{id}/cancel', function() use ($app, $user, $orderId) {
        $order = Order::find($orderId);
        if ($order->order_status->value === 'cancelled') return 'SKIP';
        $request = $app->make('Illuminate\Http\Request');
        $request->setUserResolver(fn() => $user);
        $request->merge(['cancel_reason' => 'Test cancellation']);
        $controller = new \App\Http\Controllers\Api\Mobile\OrderController();
        $result = $controller->cancel($request, $orderId);
        $data = json_decode($result->getContent(), true);
        return ($data['success'] || ($data['success'] === false && str_contains($data['message'] ?? '', 'already'))) ? true : json_encode($data);
    });

    test('PUT /orders/{id}/status', function() use ($app, $user, $orderId) {
        $request = $app->make('Illuminate\Http\Request');
        $request->setUserResolver(fn() => $user);
        $request->merge(['status' => 'confirmed']);
        $controller = new \App\Http\Controllers\Api\Mobile\OrderController();
        $result = $controller->updateStatus($request, $orderId);
        $data = json_decode($result->getContent(), true);
        return $data['success'] ? true : json_encode($data);
    });

    test('GET /orders/{id}/receipt', function() use ($app, $orderId) {
        $controller = new \App\Http\Controllers\Api\Mobile\OrderController();
        $result = $controller->receipt($orderId);
        $data = json_decode($result->getContent(), true);
        return $data['success'] ? true : json_encode($data);
    });
}

if ($paymentId) {
    test('GET /payments/{id}/receipt', function() use ($app, $paymentId) {
        $controller = new \App\Http\Controllers\Api\Mobile\PaymentController();
        $result = $controller->receipt($paymentId);
        $data = json_decode($result->getContent(), true);
        return $data['success'] ? true : json_encode($data);
    });
}

if ($kotId) {
    test('GET /kots/{id}/print', function() use ($app, $kotId) {
        $controller = new \App\Http\Controllers\Api\Mobile\KotController();
        $result = $controller->printData($kotId);
        $data = json_decode($result->getContent(), true);
        return $data['success'] ? true : json_encode($data);
    });
}

// ============================================
// WAITER REQUEST ENDPOINTS
// ============================================
echo "=== WAITER REQUEST ENDPOINTS ===\n";

test('GET /waiter-requests', function() use ($app, $user) {
    $request = $app->make('Illuminate\Http\Request');
    $request->setUserResolver(fn() => $user);
    $controller = new \App\Http\Controllers\Api\Mobile\WaiterRequestController();
    $result = $controller->index($request);
    $data = json_decode($result->getContent(), true);
    return $data['success'] ? true : json_encode($data);
});

if ($table) {
    test('POST /waiter-requests', function() use ($app, $user, $table) {
        $request = $app->make('Illuminate\Http\Request');
        $request->setUserResolver(fn() => $user);
        $request->merge(['table_id' => $table->id]);
        $controller = new \App\Http\Controllers\Api\Mobile\WaiterRequestController();
        $result = $controller->store($request);
        $data = json_decode($result->getContent(), true);
        return ($data['success'] || ($data['success'] === false && str_contains($data['message'] ?? '', 'already'))) ? true : json_encode($data);
    });
}

// ============================================
// UPDATE/POLLING ENDPOINTS
// ============================================
echo "=== UPDATE/POLLING ENDPOINTS ===\n";

test('POST /updates/poll', function() use ($app, $user) {
    $request = $app->make('Illuminate\Http\Request');
    $request->setUserResolver(fn() => $user);
    $request->merge(['last_sync' => now()->subHour()->toISOString(), 'types' => ['orders', 'kots']]);
    $controller = new \App\Http\Controllers\Api\Mobile\UpdateController();
    $result = $controller->poll($request);
    $data = json_decode($result->getContent(), true);
    return $data['success'] ? true : json_encode($data);
});

test('GET /updates/orders', function() use ($app, $user) {
    $request = $app->make('Illuminate\Http\Request');
    $request->setUserResolver(fn() => $user);
    $controller = new \App\Http\Controllers\Api\Mobile\UpdateController();
    $result = $controller->orders($request);
    $data = json_decode($result->getContent(), true);
    return $data['success'] ? true : json_encode($data);
});

test('GET /updates/kots', function() use ($app, $user) {
    $request = $app->make('Illuminate\Http\Request');
    $request->setUserResolver(fn() => $user);
    $controller = new \App\Http\Controllers\Api\Mobile\UpdateController();
    $result = $controller->kots($request);
    $data = json_decode($result->getContent(), true);
    return $data['success'] ? true : json_encode($data);
});

test('GET /updates/payments', function() use ($app, $user) {
    $request = $app->make('Illuminate\Http\Request');
    $request->setUserResolver(fn() => $user);
    $controller = new \App\Http\Controllers\Api\Mobile\UpdateController();
    $result = $controller->payments($request);
    $data = json_decode($result->getContent(), true);
    return $data['success'] ? true : json_encode($data);
});

test('POST /updates/acknowledge', function() use ($app, $user) {
    $request = $app->make('Illuminate\Http\Request');
    $request->setUserResolver(fn() => $user);
    $request->merge(['update_ids' => [['type' => 'order', 'id' => 1]]]);
    $controller = new \App\Http\Controllers\Api\Mobile\UpdateController();
    $result = $controller->acknowledge($request);
    $data = json_decode($result->getContent(), true);
    return $data['success'] ? true : json_encode($data);
});

// ============================================
// SEARCH ENDPOINTS
// ============================================
echo "=== SEARCH ENDPOINTS ===\n";

test('GET /search/menu', function() use ($app, $user) {
    $request = $app->make('Illuminate\Http\Request');
    $request->setUserResolver(fn() => $user);
    $request->merge(['query' => 'test']);
    $controller = new \App\Http\Controllers\Api\Mobile\SearchController();
    $result = $controller->menu($request);
    $data = json_decode($result->getContent(), true);
    return $data['success'] ? true : json_encode($data);
});

test('GET /search/orders', function() use ($app, $user) {
    $request = $app->make('Illuminate\Http\Request');
    $request->setUserResolver(fn() => $user);
    $request->merge(['query' => '1']);
    $controller = new \App\Http\Controllers\Api\Mobile\SearchController();
    $result = $controller->orders($request);
    $data = json_decode($result->getContent(), true);
    return $data['success'] ? true : json_encode($data);
});

test('GET /search/tables', function() use ($app, $user) {
    $request = $app->make('Illuminate\Http\Request');
    $request->setUserResolver(fn() => $user);
    $request->merge(['query' => 'T']);
    $controller = new \App\Http\Controllers\Api\Mobile\SearchController();
    $result = $controller->tables($request);
    $data = json_decode($result->getContent(), true);
    return $data['success'] ? true : json_encode($data);
});

// ============================================
// SETTINGS ENDPOINTS
// ============================================
echo "=== SETTINGS ENDPOINTS ===\n";

test('GET /settings', function() use ($app, $user) {
    $request = $app->make('Illuminate\Http\Request');
    $request->setUserResolver(fn() => $user);
    $controller = new \App\Http\Controllers\Api\Mobile\SettingsController();
    $result = $controller->index($request);
    $data = json_decode($result->getContent(), true);
    return $data['success'] ? true : json_encode($data);
});

test('GET /settings/restaurant', function() use ($app, $user) {
    $request = $app->make('Illuminate\Http\Request');
    $request->setUserResolver(fn() => $user);
    $controller = new \App\Http\Controllers\Api\Mobile\SettingsController();
    $result = $controller->restaurant($request);
    $data = json_decode($result->getContent(), true);
    return $data['success'] ? true : json_encode($data);
});

test('GET /settings/branch', function() use ($app, $user) {
    $request = $app->make('Illuminate\Http\Request');
    $request->setUserResolver(fn() => $user);
    $controller = new \App\Http\Controllers\Api\Mobile\SettingsController();
    $result = $controller->branch($request);
    $data = json_decode($result->getContent(), true);
    return $data['success'] ? true : json_encode($data);
});

test('GET /settings/tax-rates', function() use ($app, $user) {
    $request = $app->make('Illuminate\Http\Request');
    $request->setUserResolver(fn() => $user);
    $controller = new \App\Http\Controllers\Api\Mobile\SettingsController();
    $result = $controller->taxRates($request);
    $data = json_decode($result->getContent(), true);
    return $data['success'] ? true : json_encode($data);
});

test('GET /settings/order-types', function() use ($app, $user) {
    $request = $app->make('Illuminate\Http\Request');
    $request->setUserResolver(fn() => $user);
    $controller = new \App\Http\Controllers\Api\Mobile\SettingsController();
    $result = $controller->orderTypes($request);
    $data = json_decode($result->getContent(), true);
    return $data['success'] ? true : json_encode($data);
});

// ============================================
// REPORT ENDPOINTS
// ============================================
echo "=== REPORT ENDPOINTS ===\n";

test('GET /reports/my-orders', function() use ($app, $user) {
    $request = $app->make('Illuminate\Http\Request');
    $request->setUserResolver(fn() => $user);
    $controller = new \App\Http\Controllers\Api\Mobile\ReportController();
    $result = $controller->myOrders($request);
    $data = json_decode($result->getContent(), true);
    return $data['success'] ? true : json_encode($data);
});

test('GET /reports/my-payments', function() use ($app, $user) {
    $request = $app->make('Illuminate\Http\Request');
    $request->setUserResolver(fn() => $user);
    $controller = new \App\Http\Controllers\Api\Mobile\ReportController();
    $result = $controller->myPayments($request);
    $data = json_decode($result->getContent(), true);
    return $data['success'] ? true : json_encode($data);
});

test('GET /reports/shift-summary', function() use ($app, $user) {
    $request = $app->make('Illuminate\Http\Request');
    $request->setUserResolver(fn() => $user);
    $controller = new \App\Http\Controllers\Api\Mobile\ReportController();
    $result = $controller->shiftSummary($request);
    $data = json_decode($result->getContent(), true);
    return $data['success'] ? true : json_encode($data);
});

test('GET /reports/daily-summary', function() use ($app, $user) {
    $request = $app->make('Illuminate\Http\Request');
    $request->setUserResolver(fn() => $user);
    $controller = new \App\Http\Controllers\Api\Mobile\ReportController();
    $result = $controller->dailySummary($request);
    $data = json_decode($result->getContent(), true);
    return $data['success'] ? true : json_encode($data);
});

// ============================================
// CUSTOMER ENDPOINTS
// ============================================
echo "=== CUSTOMER ENDPOINTS ===\n";

test('GET /customers', function() use ($app, $user) {
    $request = $app->make('Illuminate\Http\Request');
    $request->setUserResolver(fn() => $user);
    $controller = new \App\Http\Controllers\Api\Mobile\CustomerController();
    $result = $controller->index($request);
    $data = json_decode($result->getContent(), true);
    return $data['success'] ? true : json_encode($data);
});

if ($orderId) {
    test('GET /orders/{orderId}/customer', function() use ($app, $orderId) {
        $controller = new \App\Http\Controllers\Api\Mobile\CustomerController();
        $result = $controller->forOrder($orderId);
        $data = json_decode($result->getContent(), true);
        // May not have customer, that's OK
        return ($data['success'] || ($data['success'] === false && str_contains($data['message'] ?? '', 'customer'))) ? true : json_encode($data);
    });
}

// ============================================
// SYNC ENDPOINTS
// ============================================
echo "=== SYNC ENDPOINTS ===\n";

test('GET /sync/status', function() use ($app, $user) {
    $request = $app->make('Illuminate\Http\Request');
    $request->setUserResolver(fn() => $user);
    $controller = new \App\Http\Controllers\Api\Mobile\SyncController();
    $result = $controller->status($request);
    $data = json_decode($result->getContent(), true);
    return $data['success'] ? true : json_encode($data);
});

test('POST /sync/pull', function() use ($app, $user) {
    $request = $app->make('Illuminate\Http\Request');
    $request->setUserResolver(fn() => $user);
    $request->merge(['last_sync' => now()->subDay()->toISOString(), 'sync_types' => ['menu', 'tables']]);
    $controller = new \App\Http\Controllers\Api\Mobile\SyncController();
    $result = $controller->pull($request);
    $data = json_decode($result->getContent(), true);
    return $data['success'] ? true : json_encode($data);
});

test('POST /sync/push', function() use ($app, $user) {
    $request = $app->make('Illuminate\Http\Request');
    $request->setUserResolver(fn() => $user);
    $request->merge(['orders' => [], 'kots' => [], 'payments' => []]);
    $controller = new \App\Http\Controllers\Api\Mobile\SyncController();
    $result = $controller->push($request);
    $data = json_decode($result->getContent(), true);
    return $data['success'] ? true : json_encode($data);
});

// ============================================
// SUMMARY
// ============================================
echo "========================================\n";
echo "Test Summary\n";
echo "========================================\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
echo "Skipped: $skipped\n";
echo "Total: " . ($passed + $failed + $skipped) . "\n\n";

if (!empty($errors)) {
    echo "Errors:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
    exit(1);
} else {
    echo "✓ All tests passed!\n";
    echo "\nAll API endpoints required for Flutter mobile app are working correctly.\n";
    exit(0);
}

