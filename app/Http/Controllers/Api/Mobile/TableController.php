<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Table;
use App\Models\Area;
use Illuminate\Http\Request;

class TableController extends Controller
{
    /**
     * Get all tables
     */
    public function index(Request $request)
    {
        $branch = $request->user()->branch;
        
        $query = Table::with(['area', 'activeOrder'])
            ->where('branch_id', $branch->id);

        // Filter by area
        if ($request->has('area_id')) {
            $query->where('area_id', $request->area_id);
        }

        // Filter by status
        if ($request->has('status')) {
            $status = $request->status;
            if ($status === 'available') {
                $query->whereDoesntHave('activeOrder');
            } elseif ($status === 'occupied') {
                $query->whereHas('activeOrder');
            }
        }

        $tables = $query->get()->map(function($table) {
            $activeOrder = $table->activeOrder;
            
            return [
                'id' => $table->id,
                'table_code' => $table->table_code,
                'capacity' => $table->capacity,
                'area_id' => $table->area_id,
                'area_name' => $table->area?->area_name,
                'status' => $activeOrder ? 'occupied' : 'available',
                'active_order' => $activeOrder ? [
                    'id' => $activeOrder->id,
                    'order_number' => $activeOrder->order_number,
                    'formatted_order_number' => $activeOrder->show_formatted_order_number,
                    'status' => $activeOrder->order_status->value,
                ] : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'tables' => $tables,
                'total' => $tables->count()
            ]
        ]);
    }

    /**
     * Get single table
     */
    public function show(Request $request, $id)
    {
        $branch = $request->user()->branch;
        
        $table = Table::with(['area', 'activeOrder.items', 'activeOrder.waiter'])
            ->where('branch_id', $branch->id)
            ->findOrFail($id);

        if (!$table) {
            return response()->json([
                'success' => false,
                'message' => 'Table not found'
            ], 404);
        }

        $activeOrder = $table->activeOrder;

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $table->id,
                'table_code' => $table->table_code,
                'capacity' => $table->capacity,
                'area_id' => $table->area_id,
                'area_name' => $table->area?->area_name,
                'status' => $activeOrder ? 'occupied' : 'available',
                'active_order' => $activeOrder ? [
                    'id' => $activeOrder->id,
                    'order_number' => $activeOrder->order_number,
                    'formatted_order_number' => $activeOrder->show_formatted_order_number,
                    'status' => $activeOrder->order_status->value,
                    'waiter' => $activeOrder->waiter ? [
                        'id' => $activeOrder->waiter->id,
                        'name' => $activeOrder->waiter->name,
                    ] : null,
                ] : null,
            ]
        ]);
    }

    /**
     * Get table's active order
     */
    public function activeOrder(Request $request, $id)
    {
        $branch = $request->user()->branch;
        
        $table = Table::where('branch_id', $branch->id)
            ->findOrFail($id);

        if (!$table) {
            return response()->json([
                'success' => false,
                'message' => 'Table not found'
            ], 404);
        }

        $activeOrder = $table->activeOrder;

        if (!$activeOrder) {
            return response()->json([
                'success' => false,
                'message' => 'No active order for this table'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'order' => [
                    'id' => $activeOrder->id,
                    'order_number' => $activeOrder->order_number,
                    'formatted_order_number' => $activeOrder->show_formatted_order_number,
                    'status' => $activeOrder->order_status->value,
                    'waiter' => $activeOrder->waiter ? [
                        'id' => $activeOrder->waiter->id,
                        'name' => $activeOrder->waiter->name,
                    ] : null,
                ]
            ]
        ]);
    }

    /**
     * Lock table
     */
    public function lock(Request $request, $id)
    {
        $branch = $request->user()->branch;
        
        $table = Table::where('branch_id', $branch->id)
            ->findOrFail($id);

        if (!$table) {
            return response()->json([
                'success' => false,
                'message' => 'Table not found'
            ], 404);
        }

        $userId = $request->user()->id;
        $result = $table->lockForUser($userId);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'data' => [
                    'locked_by' => $result['locked_by'] ?? null,
                    'locked_at' => $result['locked_at'] ?? null,
                ]
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => 'Table locked successfully',
            'data' => [
                'session_token' => $result['session_token'] ?? null,
            ]
        ]);
    }

    /**
     * Unlock table
     */
    public function unlock(Request $request, $id)
    {
        $branch = $request->user()->branch;
        
        $table = Table::where('branch_id', $branch->id)
            ->findOrFail($id);

        if (!$table) {
            return response()->json([
                'success' => false,
                'message' => 'Table not found'
            ], 404);
        }

        $userId = $request->user()->id;
        $forceUnlock = $request->get('force', false);
        
        $result = $table->unlock($userId, $forceUnlock);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Table unlocked successfully'
        ]);
    }

    /**
     * Get all areas
     */
    public function areas(Request $request)
    {
        $branch = $request->user()->branch;
        
        $areas = Area::where('branch_id', $branch->id)
            ->withCount('tables')
            ->get()
            ->map(function($area) {
                return [
                    'id' => $area->id,
                    'area_name' => $area->area_name,
                    'tables_count' => $area->tables_count,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'areas' => $areas,
                'total' => $areas->count()
            ]
        ]);
    }
}

