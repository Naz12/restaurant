<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CustomerController extends Controller
{
    /**
     * Get customers list
     */
    public function index(Request $request)
    {
        $restaurant = $request->user()->restaurant;
        $query = Customer::where('restaurant_id', $restaurant->id);

        if ($request->has('search')) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%")
                  ->orWhere('phone', 'like', "%{$request->search}%");
            });
        }

        $customers = $query->limit(50)->get();

        return response()->json([
            'success' => true,
            'data' => [
                'customers' => $customers->map(function($customer) {
                    return [
                        'id' => $customer->id,
                        'name' => $customer->name,
                        'email' => $customer->email,
                        'phone' => $customer->phone,
                    ];
                }),
                'count' => $customers->count(),
            ]
        ]);
    }

    /**
     * Get customer details
     */
    public function show($id)
    {
        $customer = Customer::find($id);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'address' => $customer->address,
            ]
        ]);
    }

    /**
     * Get customer for order
     */
    public function forOrder($orderId)
    {
        $order = Order::with('customer')->find($orderId);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        if (!$order->customer) {
            return response()->json([
                'success' => false,
                'message' => 'No customer associated with this order'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $order->customer->id,
                'name' => $order->customer->name,
                'email' => $order->customer->email,
                'phone' => $order->customer->phone,
            ]
        ]);
    }
}

