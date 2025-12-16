<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Kot;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class UpdateController extends Controller
{
    /**
     * Poll for updates
     */
    public function poll(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'last_sync' => 'nullable|date',
            'types' => 'nullable|array',
            'types.*' => 'in:orders,kots,payments',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $lastSync = $request->last_sync ? Carbon::parse($request->last_sync) : Carbon::now()->subDay();
        $types = $request->types ?? ['orders', 'kots', 'payments'];
        $branch = $request->user()->branch;

        $updates = [];

        if (in_array('orders', $types)) {
            $orders = Order::where('branch_id', $branch->id)
                ->where('updated_at', '>', $lastSync)
                ->get()
                ->map(function($order) {
                    return [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'status' => $order->order_status->value,
                        'updated_at' => $order->updated_at->toISOString(),
                    ];
                });
            $updates['orders'] = $orders;
        }

        if (in_array('kots', $types)) {
            $kots = Kot::where('branch_id', $branch->id)
                ->where('updated_at', '>', $lastSync)
                ->get()
                ->map(function($kot) {
                    return [
                        'id' => $kot->id,
                        'kot_number' => $kot->kot_number,
                        'order_id' => $kot->order_id,
                        'status' => $kot->status,
                        'updated_at' => $kot->updated_at->toISOString(),
                    ];
                });
            $updates['kots'] = $kots;
        }

        if (in_array('payments', $types)) {
            $payments = Payment::where('branch_id', $branch->id)
                ->where('updated_at', '>', $lastSync)
                ->get()
                ->map(function($payment) {
                    return [
                        'id' => $payment->id,
                        'order_id' => $payment->order_id,
                        'amount' => (float)$payment->amount,
                        'updated_at' => $payment->updated_at->toISOString(),
                    ];
                });
            $updates['payments'] = $payments;
        }

        return response()->json([
            'success' => true,
            'data' => $updates,
            'sync_timestamp' => Carbon::now()->toISOString(),
        ]);
    }

    /**
     * Get recent order updates
     */
    public function orders(Request $request)
    {
        $branch = $request->user()->branch;
        $lastSync = $request->last_sync ? Carbon::parse($request->last_sync) : Carbon::now()->subHour();

        $orders = Order::where('branch_id', $branch->id)
            ->where('updated_at', '>', $lastSync)
            ->with(['table', 'waiter'])
            ->get()
            ->map(function($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->order_status->value,
                    'table' => $order->table ? $order->table->table_code : null,
                    'updated_at' => $order->updated_at->toISOString(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'orders' => $orders,
                'count' => $orders->count(),
            ],
            'sync_timestamp' => Carbon::now()->toISOString(),
        ]);
    }

    /**
     * Get recent KOT updates
     */
    public function kots(Request $request)
    {
        $branch = $request->user()->branch;
        $lastSync = $request->last_sync ? Carbon::parse($request->last_sync) : Carbon::now()->subHour();

        $kots = Kot::where('branch_id', $branch->id)
            ->where('updated_at', '>', $lastSync)
            ->with(['order'])
            ->get()
            ->map(function($kot) {
                return [
                    'id' => $kot->id,
                    'kot_number' => $kot->kot_number,
                    'order_id' => $kot->order_id,
                    'status' => $kot->status,
                    'updated_at' => $kot->updated_at->toISOString(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'kots' => $kots,
                'count' => $kots->count(),
            ],
            'sync_timestamp' => Carbon::now()->toISOString(),
        ]);
    }

    /**
     * Get recent payment updates
     */
    public function payments(Request $request)
    {
        $branch = $request->user()->branch;
        $lastSync = $request->last_sync ? Carbon::parse($request->last_sync) : Carbon::now()->subHour();

        $payments = Payment::where('branch_id', $branch->id)
            ->where('updated_at', '>', $lastSync)
            ->with(['order'])
            ->get()
            ->map(function($payment) {
                return [
                    'id' => $payment->id,
                    'order_id' => $payment->order_id,
                    'amount' => (float)$payment->amount,
                    'method' => $payment->payment_method,
                    'updated_at' => $payment->updated_at->toISOString(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'payments' => $payments,
                'count' => $payments->count(),
            ],
            'sync_timestamp' => Carbon::now()->toISOString(),
        ]);
    }

    /**
     * Acknowledge received updates
     */
    public function acknowledge(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'update_ids' => 'required|array',
            'update_ids.*.type' => 'required|in:order,kot,payment',
            'update_ids.*.id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // In a real implementation, you might want to track acknowledgments
        // For now, we'll just return success

        return response()->json([
            'success' => true,
            'message' => 'Updates acknowledged',
            'acknowledged_count' => count($request->update_ids),
        ]);
    }
}

