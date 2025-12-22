<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemModifierOption;
use App\Models\Table;
use App\Models\MenuItem;
use App\Models\MenuItemVariation;
use App\Models\ModifierOption;
use App\Models\OrderType;
use App\Models\RestaurantCharge;
use App\Models\Tax;
use App\Enums\OrderStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    /**
     * Get all orders
     */
    public function index(Request $request)
    {
        $branch = $request->user()->branch;
        $query = Order::with(['table', 'waiter', 'items.menuItem', 'items.modifierOptions'])
            ->where('branch_id', $branch->id);

        // Filter by status
        if ($request->has('status')) {
            $query->where('order_status', $request->status);
        }

        // Filter by table
        if ($request->has('table_id')) {
            $query->where('table_id', $request->table_id);
        }

        // Filter by waiter
        if ($request->has('waiter_id')) {
            $query->where('waiter_id', $request->waiter_id);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $orders = $query->latest()->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => [
                'orders' => $orders->map(function($order) {
                    return $this->formatOrder($order);
                })->values()->toArray(),
                'pagination' => [
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                ]
            ]
        ]);
    }

    /**
     * Get single order
     */
    public function show($id)
    {
        $order = Order::with([
            'table.area',
            'waiter',
            'items.menuItem',
            'items.menuItemVariation',
            'items.modifierOptions',
            'taxes',
            'charges',
            'payments',
            'kot'
        ])->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatOrder($order)
        ]);
    }

    /**
     * Create new order
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'table_id' => 'nullable|exists:tables,id',
            'order_type_id' => 'required|exists:order_types,id',
            'waiter_id' => 'nullable|exists:users,id',
            'number_of_pax' => 'nullable|integer|min:1',
            'items' => 'required|array|min:1',
            'items.*.menu_item_id' => 'required|exists:menu_items,id',
            'items.*.variation_id' => 'nullable|exists:menu_item_variations,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.modifiers' => 'nullable|array',
            'items.*.modifiers.*' => 'exists:modifier_options,id',
            'items.*.note' => 'nullable|string|max:500',
            'discount_type' => 'nullable|in:percent,fixed',
            'discount_value' => 'nullable|numeric|min:0',
            'tip_amount' => 'nullable|numeric|min:0',
            'delivery_fee' => 'nullable|numeric|min:0',
            'order_note' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $branch = $request->user()->branch;
            $restaurant = $request->user()->restaurant;
            $orderNumberData = Order::generateOrderNumber($branch);

            $order = Order::create([
                'branch_id' => $branch->id,
                'restaurant_id' => $restaurant->id,
                'table_id' => $request->table_id,
                'order_type_id' => $request->order_type_id,
                'waiter_id' => $request->waiter_id ?? $request->user()->id,
                'number_of_pax' => $request->number_of_pax ?? 1,
                'order_number' => $orderNumberData['order_number'],
                'formatted_order_number' => $orderNumberData['formatted_order_number'],
                'order_status' => OrderStatus::PLACED,
                'order_type' => OrderType::find($request->order_type_id)->type ?? 'dine_in',
                'discount_type' => $request->discount_type,
                'discount_value' => $request->discount_value ?? 0,
                'discount_amount' => 0, // Will be calculated
                'tip_amount' => $request->tip_amount ?? 0,
                'delivery_fee' => $request->delivery_fee ?? 0,
                'order_note' => $request->order_note,
                'date_time' => now(),
                'sub_total' => 0, // Will be calculated
                'total_tax_amount' => 0,
                'total' => 0, // Will be calculated
            ]);

            $subtotal = 0;

            // Create order items
            foreach ($request->items as $itemData) {
                $menuItem = MenuItem::find($itemData['menu_item_id']);
                $variation = isset($itemData['variation_id']) 
                    ? MenuItemVariation::find($itemData['variation_id']) 
                    : null;

                // Get price
                $basePrice = $variation 
                    ? (float)$variation->price 
                    : (float)$menuItem->price;

                // Calculate modifier prices
                $modifierPrice = 0;
                if (!empty($itemData['modifiers'])) {
                    $modifierOptions = ModifierOption::whereIn('id', $itemData['modifiers'])->get();
                    $modifierPrice = $modifierOptions->sum('price');
                }

                $itemTotal = ($basePrice + $modifierPrice) * $itemData['quantity'];
                $subtotal += $itemTotal;

                $orderItem = OrderItem::create([
                    'order_id' => $order->id,
                    'menu_item_id' => $itemData['menu_item_id'],
                    'menu_item_variation_id' => $itemData['variation_id'] ?? null,
                    'quantity' => $itemData['quantity'],
                    'price' => $basePrice,
                    'amount' => $itemTotal,
                    'note' => $itemData['note'] ?? null,
                ]);

                // Attach modifiers
                if (!empty($itemData['modifiers'])) {
                    foreach ($itemData['modifiers'] as $modifierId) {
                        OrderItemModifierOption::create([
                            'order_item_id' => $orderItem->id,
                            'modifier_option_id' => $modifierId,
                        ]);
                    }
                }
            }

            // Calculate discount
            $discountAmount = 0;
            if ($request->discount_type && $request->discount_value) {
                if ($request->discount_type === 'percent') {
                    $discountAmount = ($subtotal * $request->discount_value) / 100;
                } else {
                    $discountAmount = min($request->discount_value, $subtotal);
                }
            }

            // Calculate taxes (simplified - you may need to adjust based on your tax logic)
            $taxAmount = 0;
            // Add tax calculation here if needed

            // Calculate total
            $total = $subtotal - $discountAmount + $taxAmount + ($request->tip_amount ?? 0) + ($request->delivery_fee ?? 0);

            // Update order with calculated amounts
            $order->update([
                'sub_total' => $subtotal,
                'discount_amount' => $discountAmount,
                'total_tax_amount' => $taxAmount,
                'total' => $total,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'data' => $this->formatOrder($order->fresh())
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create order: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update order
     */
    public function update(Request $request, $id)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        // Check if order can be updated
        if (in_array($order->order_status, [OrderStatus::DELIVERED, OrderStatus::CANCELLED])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update order with status: ' . $order->order_status->value
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'waiter_id' => 'nullable|exists:users,id',
            'number_of_pax' => 'nullable|integer|min:1',
            'discount_type' => 'nullable|in:percent,fixed',
            'discount_value' => 'nullable|numeric|min:0',
            'tip_amount' => 'nullable|numeric|min:0',
            'delivery_fee' => 'nullable|numeric|min:0',
            'order_note' => 'nullable|string|max:1000',
            'order_status' => 'nullable|in:placed,confirmed,preparing,ready_for_pickup,served',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $updateData = [];
        if ($request->has('waiter_id')) $updateData['waiter_id'] = $request->waiter_id;
        if ($request->has('number_of_pax')) $updateData['number_of_pax'] = $request->number_of_pax;
        if ($request->has('discount_type')) $updateData['discount_type'] = $request->discount_type;
        if ($request->has('discount_value')) $updateData['discount_value'] = $request->discount_value;
        if ($request->has('tip_amount')) $updateData['tip_amount'] = $request->tip_amount;
        if ($request->has('delivery_fee')) $updateData['delivery_fee'] = $request->delivery_fee;
        if ($request->has('order_note')) $updateData['order_note'] = $request->order_note;
        if ($request->has('order_status')) {
            $updateData['order_status'] = OrderStatus::from($request->order_status);
        }

        // Recalculate totals if discount changed
        if ($request->has('discount_type') || $request->has('discount_value')) {
            $subtotal = $order->items->sum('amount');
            $discountAmount = 0;
            if ($updateData['discount_type'] ?? $order->discount_type) {
                $discountValue = $updateData['discount_value'] ?? $order->discount_value;
                if (($updateData['discount_type'] ?? $order->discount_type) === 'percent') {
                    $discountAmount = ($subtotal * $discountValue) / 100;
                } else {
                    $discountAmount = min($discountValue, $subtotal);
                }
            }
            $updateData['discount_amount'] = $discountAmount;
            $updateData['total'] = $subtotal - $discountAmount + $order->total_tax_amount + ($updateData['tip_amount'] ?? $order->tip_amount) + ($updateData['delivery_fee'] ?? $order->delivery_fee);
        }

        $order->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Order updated successfully',
            'data' => $this->formatOrder($order->fresh())
        ]);
    }

    /**
     * Add item to order
     */
    public function addItem(Request $request, $id)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'menu_item_id' => 'required|exists:menu_items,id',
            'variation_id' => 'nullable|exists:menu_item_variations,id',
            'quantity' => 'required|integer|min:1',
            'modifiers' => 'nullable|array',
            'modifiers.*' => 'exists:modifier_options,id',
            'note' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $menuItem = MenuItem::find($request->menu_item_id);
            $variation = $request->variation_id 
                ? MenuItemVariation::find($request->variation_id) 
                : null;

            $basePrice = $variation ? (float)$variation->price : (float)$menuItem->price;

            $modifierPrice = 0;
            if (!empty($request->modifiers)) {
                $modifierOptions = ModifierOption::whereIn('id', $request->modifiers)->get();
                $modifierPrice = $modifierOptions->sum('price');
            }

            $itemTotal = ($basePrice + $modifierPrice) * $request->quantity;

            $orderItem = OrderItem::create([
                'order_id' => $order->id,
                'menu_item_id' => $request->menu_item_id,
                'menu_item_variation_id' => $request->variation_id,
                'quantity' => $request->quantity,
                'price' => $basePrice,
                'amount' => $itemTotal,
                'note' => $request->note,
            ]);

            if (!empty($request->modifiers)) {
                foreach ($request->modifiers as $modifierId) {
                    OrderItemModifierOption::create([
                        'order_item_id' => $orderItem->id,
                        'modifier_option_id' => $modifierId,
                    ]);
                }
            }

            // Recalculate order totals
            $subtotal = $order->items->sum('amount');
            $discountAmount = $order->discount_amount;
            if ($order->discount_type === 'percent') {
                $discountAmount = ($subtotal * $order->discount_value) / 100;
            }
            
            $order->update([
                'sub_total' => $subtotal,
                'discount_amount' => $discountAmount,
                'total' => $subtotal - $discountAmount + $order->total_tax_amount + $order->tip_amount + $order->delivery_fee,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Item added to order successfully',
                'data' => $this->formatOrder($order->fresh())
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to add item: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update order item
     */
    public function updateItem(Request $request, $orderId, $itemId)
    {
        $order = Order::find($orderId);
        $orderItem = OrderItem::where('order_id', $orderId)->find($itemId);

        if (!$order || !$orderItem) {
            return response()->json([
                'success' => false,
                'message' => 'Order or item not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'quantity' => 'nullable|integer|min:1',
            'modifiers' => 'nullable|array',
            'modifiers.*' => 'exists:modifier_options,id',
            'note' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            if ($request->has('quantity')) {
                $orderItem->quantity = $request->quantity;
            }
            if ($request->has('note')) {
                $orderItem->note = $request->note;
            }

            // Update modifiers if provided
            if ($request->has('modifiers')) {
                $orderItem->modifierOptions()->sync($request->modifiers);
                
                $modifierPrice = ModifierOption::whereIn('id', $request->modifiers)->sum('price');
            } else {
                $modifierPrice = $orderItem->modifierOptions->sum('price');
            }

            $basePrice = $orderItem->price;
            $orderItem->amount = ($basePrice + $modifierPrice) * $orderItem->quantity;
            $orderItem->save();

            // Recalculate order totals
            $subtotal = $order->items->sum('amount');
            $discountAmount = $order->discount_amount;
            if ($order->discount_type === 'percent') {
                $discountAmount = ($subtotal * $order->discount_value) / 100;
            }
            
            $order->update([
                'sub_total' => $subtotal,
                'discount_amount' => $discountAmount,
                'total' => $subtotal - $discountAmount + $order->total_tax_amount + $order->tip_amount + $order->delivery_fee,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order item updated successfully',
                'data' => $this->formatOrder($order->fresh())
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update item: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel order
     */
    public function cancel(Request $request, $id)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        if ($order->order_status === OrderStatus::CANCELLED) {
            return response()->json([
                'success' => false,
                'message' => 'Order is already cancelled'
            ], 400);
        }

        if (in_array($order->order_status, [OrderStatus::DELIVERED, OrderStatus::SERVED])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot cancel order with status: ' . $order->order_status->value
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'cancel_reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $order->update([
                'order_status' => OrderStatus::CANCELLED,
                'order_note' => ($order->order_note ? $order->order_note . ' | ' : '') . 'Cancelled: ' . ($request->cancel_reason ?? 'No reason provided'),
            ]);

            // Cancel all KOTs for this order
            \App\Models\Kot::where('order_id', $order->id)->update(['status' => 'cancelled']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order cancelled successfully',
                'data' => $this->formatOrder($order->fresh())
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel order: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update order status
     */
    public function updateStatus(Request $request, $id)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:placed,confirmed,preparing,ready_for_pickup,served',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $order->update([
            'order_status' => OrderStatus::from($request->status),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Order status updated successfully',
            'data' => $this->formatOrder($order->fresh())
        ]);
    }

    /**
     * Get order receipt data
     */
    public function receipt($id)
    {
        $order = Order::with(['table.area', 'waiter', 'items.menuItem', 'items.modifierOptions', 'payments'])
            ->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        $totalPaid = $order->payments->sum('amount');
        $remaining = $order->total - $totalPaid;

        return response()->json([
            'success' => true,
            'data' => [
                'order' => $this->formatOrder($order),
                'payment_summary' => [
                    'total' => (float)$order->total,
                    'paid' => (float)$totalPaid,
                    'remaining' => (float)$remaining,
                    'payments' => $order->payments->map(function($payment) {
                        return [
                            'id' => $payment->id,
                            'method' => $payment->payment_method,
                            'amount' => (float)$payment->amount,
                            'tip_amount' => (float)($payment->tip_amount ?? 0),
                            'created_at' => $payment->created_at->toISOString(),
                        ];
                    }),
                ],
                'receipt_data' => [
                    'order_number' => $order->order_number,
                    'formatted_order_number' => $order->show_formatted_order_number,
                    'date' => $order->date_time->format('Y-m-d H:i:s'),
                    'table' => $order->table ? $order->table->table_code : null,
                    'waiter' => $order->waiter ? $order->waiter->name : null,
                ],
            ]
        ]);
    }

    /**
     * Delete order item
     */
    public function deleteItem($orderId, $itemId)
    {
        $order = Order::find($orderId);
        $orderItem = OrderItem::where('order_id', $orderId)->find($itemId);

        if (!$order || !$orderItem) {
            return response()->json([
                'success' => false,
                'message' => 'Order or item not found'
            ], 404);
        }

        DB::beginTransaction();
        try {
            $orderItem->modifierOptions()->detach();
            $orderItem->delete();

            // Recalculate order totals
            $subtotal = $order->items->sum('amount');
            $discountAmount = $order->discount_amount;
            if ($order->discount_type === 'percent') {
                $discountAmount = ($subtotal * $order->discount_value) / 100;
            }
            
            $order->update([
                'sub_total' => $subtotal,
                'discount_amount' => $discountAmount,
                'total' => $subtotal - $discountAmount + $order->total_tax_amount + $order->tip_amount + $order->delivery_fee,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order item deleted successfully',
                'data' => $this->formatOrder($order->fresh())
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete item: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Format order for API response
     */
    private function formatOrder($order)
    {
        return [
            'id' => $order->id,
            'order_number' => (string)$order->order_number,
            'formatted_order_number' => $order->show_formatted_order_number,
            'status' => $order->order_status->value,
            'table' => $order->table ? [
                'id' => $order->table->id,
                'table_code' => $order->table->table_code,
                'area_name' => $order->table->area?->area_name,
            ] : null,
            'waiter' => $order->waiter ? [
                'id' => $order->waiter->id,
                'name' => $order->waiter->name,
            ] : null,
            'number_of_pax' => $order->number_of_pax,
            'sub_total' => (float)$order->sub_total,
            'discount_type' => $order->discount_type,
            'discount_value' => (float)$order->discount_value,
            'discount_amount' => (float)$order->discount_amount,
            'tip_amount' => (float)$order->tip_amount,
            'delivery_fee' => (float)$order->delivery_fee,
            'total_tax_amount' => (float)$order->total_tax_amount,
            'total' => (float)$order->total,
            'order_note' => $order->order_note,
            'items' => $order->items->map(function($item) {
                return [
                    'id' => $item->id,
                    'menu_item' => [
                        'id' => $item->menuItem->id,
                        'name' => $item->menuItem->item_name,
                    ],
                    'variation' => $item->menuItemVariation ? [
                        'id' => $item->menuItemVariation->id,
                        'name' => $item->menuItemVariation->name,
                    ] : null,
                    'quantity' => $item->quantity,
                    'price' => (float)$item->price,
                    'amount' => (float)$item->amount,
                    'modifiers' => $item->modifierOptions->map(function($modifier) {
                        return [
                            'id' => $modifier->id,
                            'name' => $modifier->name,
                            'price' => (float)$modifier->price,
                        ];
                    })->values()->toArray(),
                    'note' => $item->note,
                ];
            })->values()->toArray(),
            'created_at' => $order->created_at->toISOString(),
            'updated_at' => $order->updated_at->toISOString(),
        ];
    }
}

