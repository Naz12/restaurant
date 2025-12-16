<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Kot;
use App\Models\KotItem;
use App\Models\KotPlace;
use App\Models\KotCancelReason;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class KotController extends Controller
{
    /**
     * Get all KOTs
     */
    public function index(Request $request)
    {
        $branch = $request->user()->branch;
        $query = Kot::with(['order.table', 'order.waiter', 'items.menuItem', 'kotPlace'])
            ->where('branch_id', $branch->id);

        // Filter by kitchen place
        if ($request->has('kitchen_place_id')) {
            $query->where('kitchen_place_id', $request->kitchen_place_id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        // Filter by order status
        if ($request->has('filter_orders')) {
            $filter = $request->filter_orders;
            if ($filter === 'pending_confirmation') {
                $query->whereHas('order', function($q) {
                    $q->where('order_status', 'placed');
                })->where('status', 'pending');
            } elseif ($filter === 'in_kitchen') {
                $query->where('status', 'in_kitchen');
            } elseif ($filter === 'ready') {
                $query->where('status', 'ready');
            }
        }

        $kots = $query->latest()->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => [
                'kots' => $kots->map(function($kot) {
                    return $this->formatKot($kot);
                }),
                'pagination' => [
                    'current_page' => $kots->currentPage(),
                    'last_page' => $kots->lastPage(),
                    'per_page' => $kots->perPage(),
                    'total' => $kots->total(),
                ]
            ]
        ]);
    }

    /**
     * Get single KOT
     */
    public function show($id)
    {
        $kot = Kot::with([
            'order.table.area',
            'order.waiter',
            'items.menuItem',
            'items.menuItemVariation',
            'items.modifierOptions',
            'kotPlace',
            'cancelReason'
        ])->find($id);

        if (!$kot) {
            return response()->json([
                'success' => false,
                'message' => 'KOT not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatKot($kot, true)
        ]);
    }

    /**
     * Confirm KOT (move from pending to in_kitchen)
     */
    public function confirm(Request $request, $id)
    {
        $kot = Kot::find($id);

        if (!$kot) {
            return response()->json([
                'success' => false,
                'message' => 'KOT not found'
            ], 404);
        }

        if ($kot->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'KOT is not in pending status'
            ], 400);
        }

        $kot->update(['status' => 'in_kitchen']);

        return response()->json([
            'success' => true,
            'message' => 'KOT confirmed successfully',
            'data' => $this->formatKot($kot->fresh())
        ]);
    }

    /**
     * Mark KOT as ready
     */
    public function ready(Request $request, $id)
    {
        $kot = Kot::find($id);

        if (!$kot) {
            return response()->json([
                'success' => false,
                'message' => 'KOT not found'
            ], 404);
        }

        if (!in_array($kot->status, ['pending', 'in_kitchen'])) {
            return response()->json([
                'success' => false,
                'message' => 'KOT cannot be marked as ready from current status'
            ], 400);
        }

        $kot->update(['status' => 'ready']);

        return response()->json([
            'success' => true,
            'message' => 'KOT marked as ready',
            'data' => $this->formatKot($kot->fresh())
        ]);
    }

    /**
     * Update KOT item status
     */
    public function updateItemStatus(Request $request, $kotId, $itemId)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,preparing,ready,cancelled'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $kot = Kot::find($kotId);
        $kotItem = KotItem::where('kot_id', $kotId)->find($itemId);

        if (!$kot || !$kotItem) {
            return response()->json([
                'success' => false,
                'message' => 'KOT or item not found'
            ], 404);
        }

        $kotItem->update(['status' => $request->status]);

        // Update KOT status if all items are ready
        $allItemsReady = $kot->items()->where('status', '!=', 'ready')->where('status', '!=', 'cancelled')->count() === 0;
        if ($allItemsReady && $kot->status !== 'ready') {
            $kot->update(['status' => 'ready']);
        }

        return response()->json([
            'success' => true,
            'message' => 'KOT item status updated successfully',
            'data' => $this->formatKot($kot->fresh())
        ]);
    }

    /**
     * Cancel KOT
     */
    public function cancel(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'cancel_reason_id' => 'required|exists:kot_cancel_reasons,id',
            'cancel_note' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $kot = Kot::find($id);

        if (!$kot) {
            return response()->json([
                'success' => false,
                'message' => 'KOT not found'
            ], 404);
        }

        if ($kot->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'KOT is already cancelled'
            ], 400);
        }

        $kot->update([
            'status' => 'cancelled',
            'cancel_reason_id' => $request->cancel_reason_id,
            'cancel_note' => $request->cancel_note,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'KOT cancelled successfully',
            'data' => $this->formatKot($kot->fresh())
        ]);
    }

    /**
     * Cancel KOT item
     */
    public function cancelItem(Request $request, $kotId, $itemId)
    {
        $validator = Validator::make($request->all(), [
            'cancel_reason_id' => 'required|exists:kot_cancel_reasons,id',
            'cancel_note' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $kot = Kot::find($kotId);
        $kotItem = KotItem::where('kot_id', $kotId)->find($itemId);

        if (!$kot || !$kotItem) {
            return response()->json([
                'success' => false,
                'message' => 'KOT or item not found'
            ], 404);
        }

        $kotItem->update([
            'status' => 'cancelled',
            'cancel_reason_id' => $request->cancel_reason_id,
            'cancel_note' => $request->cancel_note,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'KOT item cancelled successfully',
            'data' => $this->formatKot($kot->fresh())
        ]);
    }

    /**
     * Get KOT places (kitchen stations)
     */
    public function places(Request $request)
    {
        $branch = $request->user()->branch;
        $places = KotPlace::where('branch_id', $branch->id)
            ->get()
            ->map(function($place) {
                return [
                    'id' => $place->id,
                    'name' => $place->name,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'places' => $places,
                'total' => $places->count()
            ]
        ]);
    }

    /**
     * Get cancel reasons
     */
    public function cancelReasons()
    {
        $reasons = KotCancelReason::where('cancel_kot', true)
            ->get()
            ->map(function($reason) {
                return [
                    'id' => $reason->id,
                    'reason' => $reason->reason,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'reasons' => $reasons,
                'total' => $reasons->count()
            ]
        ]);
    }

    /**
     * Get KOT print data
     */
    public function printData($id)
    {
        $kot = Kot::with([
            'order.table.area',
            'order.waiter',
            'items.menuItem',
            'items.menuItemVariation',
            'items.modifierOptions',
            'kotPlace'
        ])->find($id);

        if (!$kot) {
            return response()->json([
                'success' => false,
                'message' => 'KOT not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'kot' => $this->formatKot($kot, true),
                'print_data' => [
                    'kot_number' => $kot->kot_number,
                    'order_number' => $kot->order->order_number,
                    'table' => $kot->table ? $kot->table->table_code : null,
                    'waiter' => $kot->order->waiter ? $kot->order->waiter->name : null,
                    'kitchen_place' => $kot->kotPlace ? $kot->kotPlace->name : null,
                    'created_at' => $kot->created_at->format('Y-m-d H:i:s'),
                ],
            ]
        ]);
    }

    /**
     * Format KOT for API response
     */
    private function formatKot($kot, $detailed = false)
    {
        $data = [
            'id' => $kot->id,
            'kot_number' => $kot->kot_number,
            'status' => $kot->status,
            'token_number' => $kot->token_number,
            'order' => [
                'id' => $kot->order->id,
                'order_number' => $kot->order->order_number,
                'formatted_order_number' => $kot->order->show_formatted_order_number,
            ],
            'table' => $kot->table ? [
                'id' => $kot->table->id,
                'table_code' => $kot->table->table_code,
                'area_name' => $kot->table->area?->area_name,
            ] : null,
            'waiter' => $kot->order->waiter ? [
                'id' => $kot->order->waiter->id,
                'name' => $kot->order->waiter->name,
            ] : null,
            'kitchen_place' => $kot->kotPlace ? [
                'id' => $kot->kotPlace->id,
                'name' => $kot->kotPlace->name,
            ] : null,
            'created_at' => $kot->created_at->toISOString(),
        ];

        if ($detailed) {
            $data['items'] = $kot->items->map(function($item) {
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
                    'note' => $item->note,
                    'status' => $item->status,
                    'modifiers' => $item->modifierOptions->map(function($modifier) {
                        return [
                            'id' => $modifier->id,
                            'name' => $modifier->name,
                        ];
                    }),
                ];
            });
            $data['cancel_reason'] = $kot->cancelReason ? [
                'id' => $kot->cancelReason->id,
                'reason' => $kot->cancelReason->reason,
            ] : null;
            $data['cancel_note'] = $kot->cancel_note;
        } else {
            $data['items_count'] = $kot->items->count();
        }

        return $data;
    }
}

