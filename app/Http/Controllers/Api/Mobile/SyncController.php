<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Kot;
use App\Models\Payment;
use App\Models\MenuItem;
use App\Models\ItemCategory;
use App\Models\Table;
use App\Models\Area;
use App\Models\OrderType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SyncController extends Controller
{
    /**
     * Pull latest data from server
     */
    public function pull(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'last_sync' => 'nullable|date',
            'sync_types' => 'nullable|array',
            'sync_types.*' => 'in:menu,tables,orders,kots,payments',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $lastSync = $request->last_sync ? Carbon::parse($request->last_sync) : Carbon::now()->subYear();
        $syncTypes = $request->sync_types ?? ['menu', 'tables', 'orders', 'kots', 'payments'];

        $data = [];

        if (in_array('menu', $syncTypes)) {
            $data['menu'] = [
                'items' => MenuItem::with(['category', 'variations', 'modifierGroups.options'])
                    ->where('is_available', true)
                    ->where('updated_at', '>=', $lastSync)
                    ->get()
                    ->map(function($item) {
                        return [
                            'id' => $item->id,
                            'item_name' => $item->item_name,
                            'price' => (float)$item->price,
                            'category_id' => $item->item_category_id,
                        ];
                    }),
                'categories' => ItemCategory::where('updated_at', '>=', $lastSync)
                    ->get()
                    ->map(function($category) {
                        return [
                            'id' => $category->id,
                            'category_name' => $category->category_name,
                        ];
                    }),
            ];
        }

        if (in_array('tables', $syncTypes)) {
            $data['tables'] = Table::with('area')
                ->where('updated_at', '>=', $lastSync)
                ->get()
                ->map(function($table) {
                    return [
                        'id' => $table->id,
                        'table_code' => $table->table_code,
                        'capacity' => $table->capacity,
                        'area_id' => $table->area_id,
                    ];
                });
            $data['areas'] = Area::where('updated_at', '>=', $lastSync)
                ->get()
                ->map(function($area) {
                    return [
                        'id' => $area->id,
                        'area_name' => $area->area_name,
                    ];
                });
        }

        $branch = $request->user()->branch;
        if (in_array('orders', $syncTypes)) {
            $data['orders'] = Order::with(['table', 'waiter', 'items'])
                ->where('branch_id', $branch->id)
                ->where('updated_at', '>=', $lastSync)
                ->get()
                ->map(function($order) {
                    return [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'status' => $order->order_status->value,
                        'table_id' => $order->table_id,
                    ];
                });
        }

        if (in_array('kots', $syncTypes)) {
            $data['kots'] = Kot::with(['order', 'kotPlace'])
                ->where('branch_id', $branch->id)
                ->where('updated_at', '>=', $lastSync)
                ->get()
                ->map(function($kot) {
                    return [
                        'id' => $kot->id,
                        'kot_number' => $kot->kot_number,
                        'status' => $kot->status,
                        'order_id' => $kot->order_id,
                    ];
                });
        }

        if (in_array('payments', $syncTypes)) {
            $data['payments'] = Payment::with('order')
                ->where('branch_id', $branch->id)
                ->where('updated_at', '>=', $lastSync)
                ->get()
                ->map(function($payment) {
                    return [
                        'id' => $payment->id,
                        'order_id' => $payment->order_id,
                        'amount' => (float)$payment->amount,
                        'payment_method' => $payment->payment_method,
                    ];
                });
        }

        $data['order_types'] = OrderType::where('branch_id', $branch->id)
            ->get()
            ->map(function($type) {
                return [
                    'id' => $type->id,
                    'type' => $type->type,
                    'slug' => $type->slug,
                    'order_type_name' => $type->order_type_name,
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Data pulled successfully',
            'data' => $data,
            'sync_timestamp' => Carbon::now()->toISOString(),
        ]);
    }

    /**
     * Push offline changes to server
     */
    public function push(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'orders' => 'nullable|array',
            'orders.*.id' => 'nullable|string', // Temporary ID from mobile
            'orders.*.table_id' => 'nullable|exists:tables,id',
            'orders.*.order_type_id' => 'required|exists:order_types,id',
            'orders.*.items' => 'required|array|min:1',
            'kots' => 'nullable|array',
            'kots.*.id' => 'nullable|string',
            'kots.*.order_id' => 'required|exists:orders,id',
            'kots.*.status' => 'required|in:pending,in_kitchen,ready',
            'payments' => 'nullable|array',
            'payments.*.id' => 'nullable|string',
            'payments.*.order_id' => 'required|exists:orders,id',
            'payments.*.amount' => 'required|numeric|min:0.01',
            'payments.*.payment_method' => 'required|in:cash,card',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $results = [
            'orders' => [],
            'kots' => [],
            'payments' => [],
        ];

        DB::beginTransaction();
        try {
            // Sync orders
            if (!empty($request->orders)) {
                foreach ($request->orders as $orderData) {
                    try {
                        // Check if order already exists (by temp ID or other identifier)
                        $order = null;
                        if (isset($orderData['temp_id'])) {
                            // You might want to store temp_id mapping in a separate table
                            // For now, we'll create new orders
                        }

                        $branch = $request->user()->branch;
                        $restaurant = $request->user()->restaurant;
                        if (!$order) {
                            // Create new order (simplified - you may need full order creation logic)
                            $orderNumberData = Order::generateOrderNumber($branch);
                            $order = Order::create([
                                'branch_id' => $branch->id,
                                'restaurant_id' => $restaurant->id,
                                'table_id' => $orderData['table_id'] ?? null,
                                'order_type_id' => $orderData['order_type_id'],
                                'waiter_id' => $request->user()->id,
                                'order_number' => $orderNumberData['order_number'],
                                'formatted_order_number' => $orderNumberData['formatted_order_number'],
                                'order_status' => \App\Enums\OrderStatus::PLACED,
                                'date_time' => now(),
                            ]);
                        }

                        $results['orders'][] = [
                            'temp_id' => $orderData['temp_id'] ?? null,
                            'server_id' => $order->id,
                            'status' => 'success',
                        ];
                    } catch (\Exception $e) {
                        $results['orders'][] = [
                            'temp_id' => $orderData['temp_id'] ?? null,
                            'status' => 'error',
                            'message' => $e->getMessage(),
                        ];
                    }
                }
            }

            // Sync KOTs
            if (!empty($request->kots)) {
                foreach ($request->kots as $kotData) {
                    try {
                        $kot = Kot::where('order_id', $kotData['order_id'])->first();
                        
                        if ($kot) {
                            $kot->update(['status' => $kotData['status']]);
                        }

                        $results['kots'][] = [
                            'temp_id' => $kotData['temp_id'] ?? null,
                            'server_id' => $kot->id ?? null,
                            'status' => 'success',
                        ];
                    } catch (\Exception $e) {
                        $results['kots'][] = [
                            'temp_id' => $kotData['temp_id'] ?? null,
                            'status' => 'error',
                            'message' => $e->getMessage(),
                        ];
                    }
                }
            }

            // Sync payments
            if (!empty($request->payments)) {
                foreach ($request->payments as $paymentData) {
                    try {
                        $branch = $request->user()->branch;
                        $restaurant = $request->user()->restaurant;
                        $payment = Payment::create([
                            'order_id' => $paymentData['order_id'],
                            'branch_id' => $branch->id,
                            'restaurant_id' => $restaurant->id,
                            'payment_method' => $paymentData['payment_method'],
                            'amount' => $paymentData['amount'],
                        ]);

                        $results['payments'][] = [
                            'temp_id' => $paymentData['temp_id'] ?? null,
                            'server_id' => $payment->id,
                            'status' => 'success',
                        ];
                    } catch (\Exception $e) {
                        $results['payments'][] = [
                            'temp_id' => $paymentData['temp_id'] ?? null,
                            'status' => 'error',
                            'message' => $e->getMessage(),
                        ];
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data synced successfully',
                'data' => $results,
                'sync_timestamp' => Carbon::now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sync status
     */
    public function status(Request $request)
    {
        $branch = $request->user()->branch;
        $lastOrder = Order::where('branch_id', $branch->id)->latest()->first();
        $lastKot = Kot::where('branch_id', $branch->id)->latest()->first();
        $lastPayment = Payment::where('branch_id', $branch->id)->latest()->first();

        return response()->json([
            'success' => true,
            'data' => [
                'last_order_updated' => $lastOrder?->updated_at->toISOString(),
                'last_kot_updated' => $lastKot?->updated_at->toISOString(),
                'last_payment_updated' => $lastPayment?->updated_at->toISOString(),
                'server_time' => Carbon::now()->toISOString(),
            ]
        ]);
    }
}

