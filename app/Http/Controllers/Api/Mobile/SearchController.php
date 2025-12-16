<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Table;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SearchController extends Controller
{
    /**
     * Search menu items
     */
    public function menu(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $branch = $request->user()->branch;
        $query = $request->input('query');

        $items = MenuItem::where('branch_id', $branch->id)
            ->where('is_available', true)
            ->where(function($q) use ($query) {
                $q->where('item_name', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%");
            })
            ->with(['category'])
            ->limit(50)
            ->get()
            ->map(function($item) {
                return [
                    'id' => $item->id,
                    'item_name' => $item->item_name,
                    'price' => (float)$item->price,
                    'category' => $item->category ? $item->category->category_name : null,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $items,
                'count' => $items->count(),
            ]
        ]);
    }

    /**
     * Search orders
     */
    public function orders(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $branch = $request->user()->branch;
        $query = $request->input('query');

        $orders = Order::where('branch_id', $branch->id)
            ->where(function($q) use ($query) {
                $q->where('order_number', 'like', "%{$query}%")
                  ->orWhere('formatted_order_number', 'like', "%{$query}%");
            })
            ->with(['table', 'waiter'])
            ->limit(20)
            ->latest()
            ->get()
            ->map(function($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'formatted_order_number' => $order->show_formatted_order_number,
                    'status' => $order->order_status->value,
                    'table' => $order->table ? $order->table->table_code : null,
                    'waiter' => $order->waiter ? $order->waiter->name : null,
                    'total' => (float)$order->total,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'orders' => $orders,
                'count' => $orders->count(),
            ]
        ]);
    }

    /**
     * Search tables
     */
    public function tables(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $branch = $request->user()->branch;
        $query = $request->input('query');

        $tables = Table::where('branch_id', $branch->id)
            ->where(function($q) use ($query) {
                $q->where('table_code', 'like', "%{$query}%");
            })
            ->with(['area'])
            ->limit(50)
            ->get()
            ->map(function($table) {
                return [
                    'id' => $table->id,
                    'table_code' => $table->table_code,
                    'area_name' => $table->area ? $table->area->area_name : null,
                    'capacity' => $table->capacity,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'tables' => $tables,
                'count' => $tables->count(),
            ]
        ]);
    }
}

