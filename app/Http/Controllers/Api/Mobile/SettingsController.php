<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\Branch;
use App\Models\OrderType;
use App\Models\RestaurantTax;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    /**
     * Get app settings
     */
    public function index(Request $request)
    {
        $restaurant = $request->user()->restaurant;
        $branch = $request->user()->branch;

        return response()->json([
            'success' => true,
            'data' => [
                'app_name' => config('app.name'),
                'currency' => $restaurant->currency ?? 'USD',
                'currency_symbol' => $restaurant->currency_symbol ?? '$',
                'timezone' => config('app.timezone'),
            ]
        ]);
    }

    /**
     * Get restaurant info
     */
    public function restaurant(Request $request)
    {
        $restaurant = $request->user()->restaurant;

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $restaurant->id,
                'name' => $restaurant->name,
                'email' => $restaurant->email,
                'phone' => $restaurant->phone,
                'address' => $restaurant->address,
                'currency' => $restaurant->currency ?? 'USD',
                'currency_symbol' => $restaurant->currency_symbol ?? '$',
            ]
        ]);
    }

    /**
     * Get branch info
     */
    public function branch(Request $request)
    {
        $branch = $request->user()->branch;

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $branch->id,
                'name' => $branch->name,
                'address' => $branch->address,
                'phone' => $branch->phone,
            ]
        ]);
    }

    /**
     * Get tax rates
     */
    public function taxRates(Request $request)
    {
        $restaurant = $request->user()->restaurant;
        $taxes = RestaurantTax::where('restaurant_id', $restaurant->id)->get();

        return response()->json([
            'success' => true,
            'data' => [
                'taxes' => $taxes->map(function($tax) {
                    return [
                        'id' => $tax->id,
                        'name' => $tax->tax_name,
                        'rate' => (float)$tax->tax_rate,
                        'type' => $tax->tax_type ?? 'percent',
                    ];
                }),
            ]
        ]);
    }

    /**
     * Get order types
     */
    public function orderTypes(Request $request)
    {
        $branch = $request->user()->branch;
        $orderTypes = OrderType::where('branch_id', $branch->id)->get();

        return response()->json([
            'success' => true,
            'data' => [
                'order_types' => $orderTypes->map(function($type) {
                    return [
                        'id' => $type->id,
                        'name' => $type->order_type_name,
                        'type' => $type->type,
                        'slug' => $type->slug,
                    ];
                }),
            ]
        ]);
    }
}

