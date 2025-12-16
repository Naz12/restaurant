<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    /**
     * Get all payments
     */
    public function index(Request $request)
    {
        $branch = $request->user()->branch;
        $query = Payment::with(['order.table', 'order.waiter'])
            ->where('branch_id', $branch->id);

        // Filter by order
        if ($request->has('order_id')) {
            $query->where('order_id', $request->order_id);
        }

        // Filter by payment method
        if ($request->has('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $payments = $query->latest()->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => [
                'payments' => $payments->map(function($payment) {
                    return $this->formatPayment($payment);
                }),
                'pagination' => [
                    'current_page' => $payments->currentPage(),
                    'last_page' => $payments->lastPage(),
                    'per_page' => $payments->perPage(),
                    'total' => $payments->total(),
                ]
            ]
        ]);
    }

    /**
     * Get single payment
     */
    public function show($id)
    {
        $payment = Payment::with(['order.table', 'order.waiter', 'order.items'])
            ->find($id);

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatPayment($payment, true)
        ]);
    }

    /**
     * Create payment
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:orders,id',
            'payment_method' => 'required|in:cash,card,split',
            'amount' => 'required|numeric|min:0.01',
            'tip_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
            'split_payments' => 'required_if:payment_method,split|array',
            'split_payments.*.method' => 'required_with:split_payments|in:cash,card',
            'split_payments.*.amount' => 'required_with:split_payments|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $order = Order::find($request->order_id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        // Check if order is already fully paid
        $totalPaid = $order->payments->sum('amount');
        $remaining = $order->total - $totalPaid;

        if ($remaining <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Order is already fully paid'
            ], 400);
        }

        DB::beginTransaction();
        try {
            if ($request->payment_method === 'split') {
                // Handle split payment
                $totalSplitAmount = collect($request->split_payments)->sum('amount');
                
                if ($totalSplitAmount > $remaining) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Split payment amount exceeds remaining balance'
                    ], 400);
                }

                $branch = $request->user()->branch;
                $restaurant = $request->user()->restaurant;
                foreach ($request->split_payments as $split) {
                    Payment::create([
                        'order_id' => $order->id,
                        'branch_id' => $branch->id,
                        'restaurant_id' => $restaurant->id,
                        'payment_method' => $split['method'],
                        'amount' => $split['amount'],
                        'notes' => $request->notes,
                    ]);
                }

                $paymentAmount = $totalSplitAmount;
            } else {
                // Single payment
                $paymentAmount = min($request->amount, $remaining);
                
                $branch = $request->user()->branch;
                $restaurant = $request->user()->restaurant;
                $payment = Payment::create([
                    'order_id' => $order->id,
                    'branch_id' => $branch->id,
                    'restaurant_id' => $restaurant->id,
                    'payment_method' => $request->payment_method,
                    'amount' => $paymentAmount,
                    'tip_amount' => $request->tip_amount ?? 0,
                    'notes' => $request->notes,
                ]);
            }

            // Update order tip if provided
            if ($request->tip_amount) {
                $order->update(['tip_amount' => $request->tip_amount]);
            }

            // Check if order is fully paid
            $newTotalPaid = $order->fresh()->payments->sum('amount');
            if ($newTotalPaid >= $order->total) {
                $order->update(['order_status' => \App\Enums\OrderStatus::SERVED]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment processed successfully',
                'data' => [
                    'payment_amount' => $paymentAmount,
                    'remaining_balance' => $order->fresh()->total - $order->fresh()->payments->sum('amount'),
                    'order' => [
                        'id' => $order->id,
                        'total' => (float)$order->total,
                        'paid' => (float)$newTotalPaid,
                        'remaining' => (float)($order->total - $newTotalPaid),
                    ]
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to process payment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payments for an order
     */
    public function orderPayments($orderId)
    {
        $order = Order::find($orderId);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        $payments = $order->payments;

        return response()->json([
            'success' => true,
            'data' => [
                'order' => [
                    'id' => $order->id,
                    'total' => (float)$order->total,
                    'paid' => (float)$payments->sum('amount'),
                    'remaining' => (float)($order->total - $payments->sum('amount')),
                ],
                'payments' => $payments->map(function($payment) {
                    return $this->formatPayment($payment);
                }),
            ]
        ]);
    }

    /**
     * Format payment for API response
     */
    private function formatPayment($payment, $detailed = false)
    {
        $data = [
            'id' => $payment->id,
            'order_id' => $payment->order_id,
            'order_number' => $payment->order->order_number ?? null,
            'formatted_order_number' => $payment->order->show_formatted_order_number ?? null,
            'payment_method' => $payment->payment_method,
            'amount' => (float)$payment->amount,
            'tip_amount' => (float)($payment->tip_amount ?? 0),
            'created_at' => $payment->created_at->toISOString(),
        ];

        if ($detailed) {
            $data['order'] = [
                'id' => $payment->order->id,
                'order_number' => $payment->order->order_number,
                'formatted_order_number' => $payment->order->show_formatted_order_number,
                'total' => (float)$payment->order->total,
            ];
            $data['table'] = $payment->order->table ? [
                'id' => $payment->order->table->id,
                'table_code' => $payment->order->table->table_code,
            ] : null;
            $data['waiter'] = $payment->order->waiter ? [
                'id' => $payment->order->waiter->id,
                'name' => $payment->order->waiter->name,
            ] : null;
            $data['notes'] = $payment->notes;
        }

        return $data;
    }

    /**
     * Get payment receipt data
     */
    public function receipt($id)
    {
        $payment = Payment::with(['order.table', 'order.waiter', 'order.items'])
            ->find($id);

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'payment' => $this->formatPayment($payment, true),
                'receipt_data' => [
                    'payment_id' => $payment->id,
                    'order_number' => $payment->order->order_number ?? null,
                    'formatted_order_number' => $payment->order->show_formatted_order_number ?? null,
                    'amount' => (float)$payment->amount,
                    'method' => $payment->payment_method,
                    'date' => $payment->created_at->format('Y-m-d H:i:s'),
                    'table' => $payment->order->table ? $payment->order->table->table_code : null,
                ],
            ]
        ]);
    }
}

