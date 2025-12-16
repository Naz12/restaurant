<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\WaiterRequest;
use App\Models\Table;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class WaiterRequestController extends Controller
{
    /**
     * Get all waiter requests
     */
    public function index(Request $request)
    {
        $branch = $request->user()->branch;
        $query = WaiterRequest::with(['table.area'])
            ->where('branch_id', $branch->id);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        } else {
            // Default to pending requests
            $query->where('status', 'pending');
        }

        $requests = $query->latest()->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => [
                'requests' => $requests->map(function($req) {
                    return $this->formatRequest($req);
                }),
                'pagination' => [
                    'current_page' => $requests->currentPage(),
                    'last_page' => $requests->lastPage(),
                    'per_page' => $requests->perPage(),
                    'total' => $requests->total(),
                ]
            ]
        ]);
    }

    /**
     * Create waiter request
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'table_id' => 'required|exists:tables,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $table = Table::find($request->table_id);
        $branch = $request->user()->branch;

        // Check if there's already a pending request for this table
        $existingRequest = WaiterRequest::where('table_id', $request->table_id)
            ->where('status', 'pending')
            ->first();

        if ($existingRequest) {
            return response()->json([
                'success' => false,
                'message' => 'There is already a pending request for this table'
            ], 409);
        }

        $waiterRequest = WaiterRequest::create([
            'table_id' => $request->table_id,
            'branch_id' => $branch->id,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Waiter request created successfully',
            'data' => $this->formatRequest($waiterRequest)
        ], 201);
    }

    /**
     * Respond to waiter request
     */
    public function respond(Request $request, $id)
    {
        $waiterRequest = WaiterRequest::find($id);

        if (!$waiterRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Request not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:accepted,completed,cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $waiterRequest->update([
            'status' => $request->status,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Request updated successfully',
            'data' => $this->formatRequest($waiterRequest->fresh())
        ]);
    }

    /**
     * Cancel waiter request
     */
    public function destroy($id)
    {
        $waiterRequest = WaiterRequest::find($id);

        if (!$waiterRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Request not found'
            ], 404);
        }

        $waiterRequest->update(['status' => 'cancelled']);

        return response()->json([
            'success' => true,
            'message' => 'Request cancelled successfully'
        ]);
    }

    /**
     * Format waiter request for API response
     */
    private function formatRequest($request)
    {
        return [
            'id' => $request->id,
            'table' => $request->table ? [
                'id' => $request->table->id,
                'table_code' => $request->table->table_code,
                'area_name' => $request->table->area?->area_name,
            ] : null,
            'status' => $request->status,
            'created_at' => $request->created_at->toISOString(),
            'updated_at' => $request->updated_at->toISOString(),
        ];
    }
}

