<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportController extends Controller
{
    /**
     * Get waiter's orders for today
     */
    public function myOrders(Request $request)
    {
        $user = $request->user();
        $branch = $user->branch;
        $startDate = $request->date ? Carbon::parse($request->date)->startOfDay() : Carbon::today()->startOfDay();
        $endDate = $startDate->copy()->endOfDay();

        $orders = Order::where('branch_id', $branch->id)
            ->where('waiter_id', $user->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with(['table', 'items'])
            ->get();

        $totalOrders = $orders->count();
        $totalAmount = $orders->sum('total');
        $totalItems = $orders->sum(function($order) {
            return $order->items->sum('quantity');
        });

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $startDate->format('Y-m-d'),
                'summary' => [
                    'total_orders' => $totalOrders,
                    'total_amount' => (float)$totalAmount,
                    'total_items' => $totalItems,
                ],
                'orders' => $orders->map(function($order) {
                    return [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'table' => $order->table ? $order->table->table_code : null,
                        'status' => $order->order_status->value,
                        'total' => (float)$order->total,
                        'created_at' => $order->created_at->toISOString(),
                    ];
                }),
            ]
        ]);
    }

    /**
     * Get cashier's payments for today
     */
    public function myPayments(Request $request)
    {
        $user = $request->user();
        $branch = $user->branch;
        $startDate = $request->date ? Carbon::parse($request->date)->startOfDay() : Carbon::today()->startOfDay();
        $endDate = $startDate->copy()->endOfDay();

        $payments = Payment::where('branch_id', $branch->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with(['order.table', 'order.waiter'])
            ->get();

        $totalPayments = $payments->count();
        $totalAmount = $payments->sum('amount');
        $totalTips = $payments->sum('tip_amount');

        $byMethod = $payments->groupBy('payment_method')->map(function($group) {
            return [
                'count' => $group->count(),
                'amount' => (float)$group->sum('amount'),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $startDate->format('Y-m-d'),
                'summary' => [
                    'total_payments' => $totalPayments,
                    'total_amount' => (float)$totalAmount,
                    'total_tips' => (float)$totalTips,
                    'by_method' => $byMethod,
                ],
                'payments' => $payments->map(function($payment) {
                    return [
                        'id' => $payment->id,
                        'order_number' => $payment->order->order_number ?? null,
                        'method' => $payment->payment_method,
                        'amount' => (float)$payment->amount,
                        'tip_amount' => (float)($payment->tip_amount ?? 0),
                        'created_at' => $payment->created_at->toISOString(),
                    ];
                }),
            ]
        ]);
    }

    /**
     * Get shift summary
     */
    public function shiftSummary(Request $request)
    {
        $user = $request->user();
        $branch = $user->branch;
        $startDate = $request->start_date ? Carbon::parse($request->start_date)->startOfDay() : Carbon::today()->startOfDay();
        $endDate = $request->end_date ? Carbon::parse($request->end_date)->endOfDay() : $startDate->copy()->endOfDay();

        $orders = Order::where('branch_id', $branch->id)
            ->where('waiter_id', $user->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $payments = Payment::where('branch_id', $branch->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'start' => $startDate->toISOString(),
                    'end' => $endDate->toISOString(),
                ],
                'orders' => [
                    'total' => $orders->count(),
                    'total_amount' => (float)$orders->sum('total'),
                    'by_status' => $orders->groupBy('order_status')->map(function($group) {
                        return [
                            'count' => $group->count(),
                            'amount' => (float)$group->sum('total'),
                        ];
                    }),
                ],
                'payments' => [
                    'total' => $payments->count(),
                    'total_amount' => (float)$payments->sum('amount'),
                    'total_tips' => (float)$payments->sum('tip_amount'),
                ],
            ]
        ]);
    }

    /**
     * Get daily summary
     */
    public function dailySummary(Request $request)
    {
        $branch = $request->user()->branch;
        $date = $request->date ? Carbon::parse($request->date) : Carbon::today();
        $startDate = $date->copy()->startOfDay();
        $endDate = $date->copy()->endOfDay();

        $orders = Order::where('branch_id', $branch->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $payments = Payment::where('branch_id', $branch->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $date->format('Y-m-d'),
                'orders' => [
                    'total' => $orders->count(),
                    'total_amount' => (float)$orders->sum('total'),
                    'by_status' => $orders->groupBy('order_status')->map(function($group) {
                        return [
                            'count' => $group->count(),
                            'amount' => (float)$group->sum('total'),
                        ];
                    }),
                ],
                'payments' => [
                    'total' => $payments->count(),
                    'total_amount' => (float)$payments->sum('amount'),
                    'total_tips' => (float)$payments->sum('tip_amount'),
                    'by_method' => $payments->groupBy('payment_method')->map(function($group) {
                        return [
                            'count' => $group->count(),
                            'amount' => (float)$group->sum('amount'),
                        ];
                    }),
                ],
            ]
        ]);
    }
}

