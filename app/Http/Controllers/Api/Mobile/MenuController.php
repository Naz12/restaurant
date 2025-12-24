<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\ItemCategory;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    /**
     * Get all menu items
     */
    public function items(Request $request)
    {
        $query = MenuItem::with(['category', 'variations', 'modifierGroups.options'])
            ->withCount('variations', 'modifierGroups');

        // Filter by category if provided
        if ($request->has('category_id')) {
            $query->where('item_category_id', $request->category_id);
        }

        // Search by name
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('item_name', 'like', "%{$search}%")
                  ->orWhereHas('translations', function($tq) use ($search) {
                      $tq->where('item_name', 'like', "%{$search}%");
                  });
            });
        }

        // Order type context for pricing (optional)
        $orderTypeId = $request->get('order_type_id');
        $deliveryAppId = $request->get('delivery_app_id');

        $items = $query->get()->map(function($item) use ($orderTypeId, $deliveryAppId) {
            // Set context for pricing
            if ($orderTypeId) {
                $item->setContextualPricing($orderTypeId, $deliveryAppId);
            }

            return [
                'id' => $item->id,
                'item_name' => $item->item_name,
                'description' => $item->description,
                'price' => $orderTypeId ? $item->contextual_price : (float)$item->price,
                'image' => $item->item_photo_url,
                'category_id' => $item->item_category_id,
                'category_name' => $item->category?->category_name,
                'veg_non_veg' => $item->veg_non_veg,
                'variations_count' => (int)$item->variations_count,
                'modifier_groups_count' => (int)$item->modifier_groups_count,
                'has_variations' => $item->variations_count > 0,
                'variations' => $item->variations->map(function($variation) use ($orderTypeId, $deliveryAppId, $item) {
                    $variationPrice = $orderTypeId 
                        ? $item->getVariationPrice($variation->id)
                        : (float)$variation->price;
                    
                    return [
                        'id' => $variation->id,
                        'name' => $variation->name,
                        'price' => $variationPrice,
                    ];
                }),
                'modifier_groups' => $item->modifierGroups->map(function($group) {
                    return [
                        'id' => $group->id,
                        'name' => $group->name,
                        'description' => $group->description,
                        'is_required' => $group->pivot->is_required ?? false,
                        'min_selections' => $group->pivot->min_selections ?? 0,
                        'max_selections' => $group->pivot->max_selections ?? null,
                        'options' => $group->options->map(function($option) {
                            return [
                                'id' => $option->id,
                                'name' => $option->name,
                                'price' => (float)$option->price,
                            ];
                        }),
                    ];
                }),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $items,
                'total' => $items->count()
            ]
        ]);
    }

    /**
     * Get single menu item
     */
    public function show($id)
    {
        $item = MenuItem::with(['category', 'variations', 'modifierGroups.options'])
            ->withCount('variations', 'modifierGroups')
            ->find($id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Menu item not found'
            ], 404);
        }

        // Set context for pricing if provided
        $orderTypeId = request()->get('order_type_id');
        $deliveryAppId = request()->get('delivery_app_id');
        
        if ($orderTypeId) {
            $item->setContextualPricing($orderTypeId, $deliveryAppId);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $item->id,
                'item_name' => $item->item_name,
                'description' => $item->description,
                'price' => $orderTypeId ? $item->contextual_price : (float)$item->price,
                'image' => $item->item_photo_url,
                'category_id' => $item->item_category_id,
                'category_name' => $item->category?->category_name,
                'veg_non_veg' => $item->veg_non_veg,
                'variations_count' => (int)$item->variations_count,
                'modifier_groups_count' => (int)$item->modifier_groups_count,
                'has_variations' => $item->variations_count > 0,
                'variations' => $item->variations->map(function($variation) use ($orderTypeId, $deliveryAppId, $item) {
                    $variationPrice = $orderTypeId 
                        ? $item->getVariationPrice($variation->id)
                        : (float)$variation->price;
                    
                    return [
                        'id' => $variation->id,
                        'name' => $variation->name,
                        'price' => $variationPrice,
                    ];
                }),
                'modifier_groups' => $item->modifierGroups->map(function($group) {
                    return [
                        'id' => $group->id,
                        'name' => $group->name,
                        'description' => $group->description,
                        'is_required' => $group->pivot->is_required ?? false,
                        'min_selections' => $group->pivot->min_selections ?? 0,
                        'max_selections' => $group->pivot->max_selections ?? null,
                        'options' => $group->options->map(function($option) {
                            return [
                                'id' => $option->id,
                                'name' => $option->name,
                                'price' => (float)$option->price,
                            ];
                        }),
                    ];
                }),
            ]
        ]);
    }

    /**
     * Get all categories
     */
    public function categories()
    {
        $categories = ItemCategory::withCount('items')
            ->orderBy('sort_order', 'asc')
            ->get()
            ->map(function($category) {
                return [
                    'id' => $category->id,
                    'category_name' => $category->category_name,
                    'items_count' => $category->items_count,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'categories' => $categories,
                'total' => $categories->count()
            ]
        ]);
    }

    /**
     * Get all modifier groups
     */
    public function modifierGroups()
    {
        $groups = ModifierGroup::with('options')
            ->get()
            ->map(function($group) {
                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'description' => $group->description,
                    'options' => $group->options->map(function($option) {
                        return [
                            'id' => $option->id,
                            'name' => $option->name,
                            'price' => (float)$option->price,
                        ];
                    }),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'modifier_groups' => $groups,
                'total' => $groups->count()
            ]
        ]);
    }
}

