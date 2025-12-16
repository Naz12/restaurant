<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PrintJobController;
use App\Http\Middleware\DesktopUniqueKeyMiddleware;
use App\Http\Middleware\CorsMiddleware;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

// Mobile API Routes
Route::prefix('mobile')->group(function () {
    // Public routes (no authentication)
    Route::post('/auth/login', [\App\Http\Controllers\Api\Mobile\AuthController::class, 'login']);
    Route::post('/auth/otp/send', [\App\Http\Controllers\Api\Mobile\AuthController::class, 'sendOtp']);
    Route::post('/auth/otp/verify', [\App\Http\Controllers\Api\Mobile\AuthController::class, 'verifyOtp']);

    // Protected routes (require authentication)
    Route::middleware(['auth:sanctum'])->group(function () {
        // Authentication
        Route::get('/auth/user', [\App\Http\Controllers\Api\Mobile\AuthController::class, 'user']);
        Route::post('/auth/logout', [\App\Http\Controllers\Api\Mobile\AuthController::class, 'logout']);
        Route::post('/auth/refresh-token', [\App\Http\Controllers\Api\Mobile\AuthController::class, 'refreshToken']);

        // Menu
        Route::get('/menu/items', [\App\Http\Controllers\Api\Mobile\MenuController::class, 'items']);
        Route::get('/menu/items/{id}', [\App\Http\Controllers\Api\Mobile\MenuController::class, 'show']);
        Route::get('/menu/categories', [\App\Http\Controllers\Api\Mobile\MenuController::class, 'categories']);
        Route::get('/menu/modifier-groups', [\App\Http\Controllers\Api\Mobile\MenuController::class, 'modifierGroups']);

        // Tables
        Route::get('/tables', [\App\Http\Controllers\Api\Mobile\TableController::class, 'index']);
        Route::get('/tables/{id}', [\App\Http\Controllers\Api\Mobile\TableController::class, 'show']);
        Route::get('/tables/{id}/active-order', [\App\Http\Controllers\Api\Mobile\TableController::class, 'activeOrder']);
        Route::post('/tables/{id}/lock', [\App\Http\Controllers\Api\Mobile\TableController::class, 'lock']);
        Route::post('/tables/{id}/unlock', [\App\Http\Controllers\Api\Mobile\TableController::class, 'unlock']);
        Route::get('/tables/areas', [\App\Http\Controllers\Api\Mobile\TableController::class, 'areas']);

        // Orders
        Route::get('/orders', [\App\Http\Controllers\Api\Mobile\OrderController::class, 'index']);
        Route::post('/orders', [\App\Http\Controllers\Api\Mobile\OrderController::class, 'store']);
        Route::get('/orders/{id}', [\App\Http\Controllers\Api\Mobile\OrderController::class, 'show']);
        Route::put('/orders/{id}', [\App\Http\Controllers\Api\Mobile\OrderController::class, 'update']);
        Route::post('/orders/{id}/cancel', [\App\Http\Controllers\Api\Mobile\OrderController::class, 'cancel']);
        Route::put('/orders/{id}/status', [\App\Http\Controllers\Api\Mobile\OrderController::class, 'updateStatus']);
        Route::get('/orders/{id}/receipt', [\App\Http\Controllers\Api\Mobile\OrderController::class, 'receipt']);
        Route::post('/orders/{id}/items', [\App\Http\Controllers\Api\Mobile\OrderController::class, 'addItem']);
        Route::put('/orders/{orderId}/items/{itemId}', [\App\Http\Controllers\Api\Mobile\OrderController::class, 'updateItem']);
        Route::delete('/orders/{orderId}/items/{itemId}', [\App\Http\Controllers\Api\Mobile\OrderController::class, 'deleteItem']);

        // KOTs
        Route::get('/kots', [\App\Http\Controllers\Api\Mobile\KotController::class, 'index']);
        Route::get('/kots/{id}', [\App\Http\Controllers\Api\Mobile\KotController::class, 'show']);
        Route::get('/kots/{id}/print', [\App\Http\Controllers\Api\Mobile\KotController::class, 'printData']);
        Route::post('/kots/{id}/confirm', [\App\Http\Controllers\Api\Mobile\KotController::class, 'confirm']);
        Route::post('/kots/{id}/ready', [\App\Http\Controllers\Api\Mobile\KotController::class, 'ready']);
        Route::post('/kots/{id}/cancel', [\App\Http\Controllers\Api\Mobile\KotController::class, 'cancel']);
        Route::put('/kots/{kotId}/items/{itemId}/status', [\App\Http\Controllers\Api\Mobile\KotController::class, 'updateItemStatus']);
        Route::post('/kots/{kotId}/items/{itemId}/cancel', [\App\Http\Controllers\Api\Mobile\KotController::class, 'cancelItem']);
        Route::get('/kots/places', [\App\Http\Controllers\Api\Mobile\KotController::class, 'places']);
        Route::get('/kots/cancel-reasons', [\App\Http\Controllers\Api\Mobile\KotController::class, 'cancelReasons']);

        // Payments
        Route::get('/payments', [\App\Http\Controllers\Api\Mobile\PaymentController::class, 'index']);
        Route::post('/payments', [\App\Http\Controllers\Api\Mobile\PaymentController::class, 'store']);
        Route::get('/payments/{id}', [\App\Http\Controllers\Api\Mobile\PaymentController::class, 'show']);
        Route::get('/payments/{id}/receipt', [\App\Http\Controllers\Api\Mobile\PaymentController::class, 'receipt']);
        Route::get('/orders/{orderId}/payments', [\App\Http\Controllers\Api\Mobile\PaymentController::class, 'orderPayments']);

        // Waiter Requests
        Route::get('/waiter-requests', [\App\Http\Controllers\Api\Mobile\WaiterRequestController::class, 'index']);
        Route::post('/waiter-requests', [\App\Http\Controllers\Api\Mobile\WaiterRequestController::class, 'store']);
        Route::put('/waiter-requests/{id}/respond', [\App\Http\Controllers\Api\Mobile\WaiterRequestController::class, 'respond']);
        Route::delete('/waiter-requests/{id}', [\App\Http\Controllers\Api\Mobile\WaiterRequestController::class, 'destroy']);

        // Updates (Real-time polling)
        Route::post('/updates/poll', [\App\Http\Controllers\Api\Mobile\UpdateController::class, 'poll']);
        Route::get('/updates/orders', [\App\Http\Controllers\Api\Mobile\UpdateController::class, 'orders']);
        Route::get('/updates/kots', [\App\Http\Controllers\Api\Mobile\UpdateController::class, 'kots']);
        Route::get('/updates/payments', [\App\Http\Controllers\Api\Mobile\UpdateController::class, 'payments']);
        Route::post('/updates/acknowledge', [\App\Http\Controllers\Api\Mobile\UpdateController::class, 'acknowledge']);

        // Search
        Route::get('/search/menu', [\App\Http\Controllers\Api\Mobile\SearchController::class, 'menu']);
        Route::get('/search/orders', [\App\Http\Controllers\Api\Mobile\SearchController::class, 'orders']);
        Route::get('/search/tables', [\App\Http\Controllers\Api\Mobile\SearchController::class, 'tables']);

        // Settings
        Route::get('/settings', [\App\Http\Controllers\Api\Mobile\SettingsController::class, 'index']);
        Route::get('/settings/restaurant', [\App\Http\Controllers\Api\Mobile\SettingsController::class, 'restaurant']);
        Route::get('/settings/branch', [\App\Http\Controllers\Api\Mobile\SettingsController::class, 'branch']);
        Route::get('/settings/tax-rates', [\App\Http\Controllers\Api\Mobile\SettingsController::class, 'taxRates']);
        Route::get('/settings/order-types', [\App\Http\Controllers\Api\Mobile\SettingsController::class, 'orderTypes']);

        // Reports
        Route::get('/reports/my-orders', [\App\Http\Controllers\Api\Mobile\ReportController::class, 'myOrders']);
        Route::get('/reports/my-payments', [\App\Http\Controllers\Api\Mobile\ReportController::class, 'myPayments']);
        Route::get('/reports/shift-summary', [\App\Http\Controllers\Api\Mobile\ReportController::class, 'shiftSummary']);
        Route::get('/reports/daily-summary', [\App\Http\Controllers\Api\Mobile\ReportController::class, 'dailySummary']);

        // Customers
        Route::get('/customers', [\App\Http\Controllers\Api\Mobile\CustomerController::class, 'index']);
        Route::get('/customers/{id}', [\App\Http\Controllers\Api\Mobile\CustomerController::class, 'show']);
        Route::get('/orders/{orderId}/customer', [\App\Http\Controllers\Api\Mobile\CustomerController::class, 'forOrder']);

        // Sync
        Route::post('/sync/pull', [\App\Http\Controllers\Api\Mobile\SyncController::class, 'pull']);
        Route::post('/sync/push', [\App\Http\Controllers\Api\Mobile\SyncController::class, 'push']);
        Route::get('/sync/status', [\App\Http\Controllers\Api\Mobile\SyncController::class, 'status']);
    });
});

// called by Electron every X seconds
Route::middleware(DesktopUniqueKeyMiddleware::class)->group(function () {
    Route::get('/test-connection', [PrintJobController::class, 'testConnection']);

    //Single job pull
    Route::get('/print-jobs/pull', [PrintJobController::class, 'pull']);

    //Multiple job pull
    Route::get('/print-jobs/pull-multiple', [PrintJobController::class, 'pullMultiple']);

    Route::get('/printer-details', [PrintJobController::class, 'printerDetails']);
    // mark a job done/failed
    Route::patch('/print-jobs/{printJob}', [PrintJobController::class, 'update']);
    Route::get('/print-jobs/printer/{printerId}/jobs', [PrintJobController::class, 'getPrinterJobs']);

    // Mark print job as completed
    Route::post('/print-jobs/{printJobId}/complete', [PrintJobController::class, 'complete']);
    Route::post('/print-jobs/{printJobId}/failed', [PrintJobController::class, 'failed']);
    Route::get('/print-jobs/pending/{printId}', [PrintJobController::class, 'pending']);
});

// Pusher connection logging (optional - for monitoring usage)
Route::post('/log-pusher-connection', function (Request $request) {
    // Simple logging - you can enhance this to store in database
    \Illuminate\Support\Facades\Log::info('Pusher connection', [
        'socket_id' => $request->socket_id,
        'connection_id' => $request->connection_id,
        'component' => $request->component,
        'timestamp' => $request->timestamp,
        'ip' => $request->ip()
    ]);

    return response()->json(['status' => 'logged']);
});

// Temporarily disable Pusher due to quota issues
Route::post('/disable-pusher-temporarily', function (Request $request) {
    $pusherSetting = \App\Models\PusherSetting::first();
    if ($pusherSetting) {
        $pusherSetting->update(['is_enabled_pusher_broadcast' => false]);
        \Illuminate\Support\Facades\Log::info('Pusher temporarily disabled due to quota issues');
    }

    return response()->json(['status' => 'disabled']);
});

// Force disconnect all Pusher connections (for quota cleanup)
Route::post('/force-disconnect-pusher', function (Request $request) {
    // Log the disconnect request
    \Illuminate\Support\Facades\Log::info('Force disconnect Pusher connections requested', [
        'ip' => $request->ip(),
        'user_agent' => $request->userAgent()
    ]);

    return response()->json([
        'status' => 'disconnected',
        'message' => 'All connections should be disconnected. Reload pages to reconnect.'
    ]);
});
