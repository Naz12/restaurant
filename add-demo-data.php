<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Restaurant;
use App\Models\Branch;
use App\Models\Area;
use App\Models\Table;
use App\Models\Menu;
use App\Models\ItemCategory;
use App\Models\MenuItem;
use App\Models\MenuItemVariation;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use Illuminate\Support\Str;

echo "=== Adding Demo Data to Restaurant ===\n\n";

// Get Demo Restaurant and Branch
$restaurant = Restaurant::find(1);
if (!$restaurant) {
    echo "ERROR: Demo Restaurant (ID: 1) not found.\n";
    exit(1);
}

$branch = Branch::where('restaurant_id', 1)->first();
if (!$branch) {
    echo "ERROR: Branch not found for Restaurant ID 1.\n";
    exit(1);
}

echo "Restaurant: {$restaurant->name}\n";
echo "Branch: {$branch->name}\n\n";

// ============================================
// 1. CREATE AREAS
// ============================================
echo "Creating Areas...\n";

$areas = [
    ['name' => 'Main Dining', 'description' => 'Main dining area'],
    ['name' => 'Outdoor Patio', 'description' => 'Outdoor seating area'],
    ['name' => 'Private Room', 'description' => 'Private dining room'],
    ['name' => 'Bar Area', 'description' => 'Bar and high-top tables'],
];

$createdAreas = [];
foreach ($areas as $areaData) {
    $area = Area::firstOrCreate(
        [
            'area_name' => $areaData['name'],
            'branch_id' => $branch->id,
        ],
        [
            'description' => $areaData['description'] ?? null,
        ]
    );
    $createdAreas[] = $area;
    echo "  ✓ Area: {$area->area_name}\n";
}
echo "\n";

// ============================================
// 2. CREATE TABLES
// ============================================
echo "Creating Tables...\n";

$tableData = [
    // Main Dining
    ['code' => 'M-1', 'capacity' => 2, 'area' => 'Main Dining'],
    ['code' => 'M-2', 'capacity' => 4, 'area' => 'Main Dining'],
    ['code' => 'M-3', 'capacity' => 4, 'area' => 'Main Dining'],
    ['code' => 'M-4', 'capacity' => 6, 'area' => 'Main Dining'],
    ['code' => 'M-5', 'capacity' => 6, 'area' => 'Main Dining'],
    ['code' => 'M-6', 'capacity' => 8, 'area' => 'Main Dining'],
    
    // Outdoor Patio
    ['code' => 'O-1', 'capacity' => 2, 'area' => 'Outdoor Patio'],
    ['code' => 'O-2', 'capacity' => 4, 'area' => 'Outdoor Patio'],
    ['code' => 'O-3', 'capacity' => 4, 'area' => 'Outdoor Patio'],
    ['code' => 'O-4', 'capacity' => 6, 'area' => 'Outdoor Patio'],
    
    // Private Room
    ['code' => 'P-1', 'capacity' => 10, 'area' => 'Private Room'],
    ['code' => 'P-2', 'capacity' => 12, 'area' => 'Private Room'],
    
    // Bar Area
    ['code' => 'B-1', 'capacity' => 2, 'area' => 'Bar Area'],
    ['code' => 'B-2', 'capacity' => 2, 'area' => 'Bar Area'],
    ['code' => 'B-3', 'capacity' => 4, 'area' => 'Bar Area'],
];

$createdTables = [];
foreach ($tableData as $tableInfo) {
    $area = Area::where('area_name', $tableInfo['area'])
                ->where('branch_id', $branch->id)
                ->first();
    
    if (!$area) {
        echo "  ⚠ Area '{$tableInfo['area']}' not found, skipping table {$tableInfo['code']}\n";
        continue;
    }
    
    // Check if table already exists
    $existingTable = Table::where('table_code', $tableInfo['code'])
                          ->where('branch_id', $branch->id)
                          ->first();
    
    if ($existingTable) {
        echo "  ⚠ Table {$tableInfo['code']} already exists, skipping\n";
        continue;
    }
    
    Table::withoutEvents(function () use ($branch, $area, $tableInfo, &$createdTables) {
        $table = Table::create([
            'table_code' => $tableInfo['code'],
            'area_id' => $area->id,
            'seating_capacity' => $tableInfo['capacity'],
            'capacity' => $tableInfo['capacity'], // Some systems use 'capacity' instead
            'status' => 'active',
            'hash' => md5(microtime() . rand(1, 99999999)),
            'branch_id' => $branch->id,
        ]);
        
        try {
            $table->generateQrCode();
        } catch (\Exception $e) {
            // Continue even if QR code generation fails
        }
        
        $createdTables[] = $table;
        echo "  ✓ Table: {$table->table_code} (Capacity: {$tableInfo['capacity']}, Area: {$tableInfo['area']})\n";
    });
}
echo "\n";

// ============================================
// 3. CREATE OR GET MENU
// ============================================
echo "Getting/Creating Menu...\n";

$menu = Menu::firstOrCreate(
    [
        'branch_id' => $branch->id,
    ],
    [
        'menu_name' => 'Main Menu',
    ]
);
echo "  ✓ Menu: {$menu->menu_name} (ID: {$menu->id})\n\n";

// ============================================
// 4. CREATE MENU CATEGORIES
// ============================================
echo "Creating Menu Categories...\n";

$categories = [
    ['name' => 'Appetizers', 'description' => 'Start your meal right'],
    ['name' => 'Main Courses', 'description' => 'Our signature dishes'],
    ['name' => 'Pizza', 'description' => 'Wood-fired pizzas'],
    ['name' => 'Pasta', 'description' => 'Fresh pasta dishes'],
    ['name' => 'Salads', 'description' => 'Fresh and healthy'],
    ['name' => 'Desserts', 'description' => 'Sweet endings'],
    ['name' => 'Beverages', 'description' => 'Drinks and refreshments'],
    ['name' => 'Specials', 'description' => 'Chef\'s specials'],
];

$createdCategories = [];
foreach ($categories as $catData) {
    $category = ItemCategory::firstOrCreate(
        [
            'category_name' => $catData['name'],
            'branch_id' => $branch->id,
        ],
        [
            'description' => $catData['description'] ?? null,
        ]
    );
    $createdCategories[] = $category;
    echo "  ✓ Category: {$category->category_name}\n";
}
echo "\n";

// ============================================
// 5. CREATE MODIFIER GROUPS
// ============================================
echo "Creating Modifier Groups...\n";

$modifierGroups = [
    [
        'name' => 'Size',
        'options' => [
            ['name' => 'Small', 'price' => 0],
            ['name' => 'Medium', 'price' => 2.00],
            ['name' => 'Large', 'price' => 4.00],
        ]
    ],
    [
        'name' => 'Add-ons',
        'options' => [
            ['name' => 'Extra Cheese', 'price' => 1.50],
            ['name' => 'Extra Meat', 'price' => 3.00],
            ['name' => 'Extra Vegetables', 'price' => 1.00],
        ]
    ],
    [
        'name' => 'Spice Level',
        'options' => [
            ['name' => 'Mild', 'price' => 0],
            ['name' => 'Medium', 'price' => 0],
            ['name' => 'Hot', 'price' => 0],
            ['name' => 'Extra Hot', 'price' => 0],
        ]
    ],
    [
        'name' => 'Dressing',
        'options' => [
            ['name' => 'Ranch', 'price' => 0],
            ['name' => 'Caesar', 'price' => 0],
            ['name' => 'Italian', 'price' => 0],
            ['name' => 'Balsamic', 'price' => 0],
        ]
    ],
];

$createdModifierGroups = [];
foreach ($modifierGroups as $groupData) {
    $modifierGroup = ModifierGroup::firstOrCreate(
        [
            'name' => $groupData['name'],
            'branch_id' => $branch->id,
        ]
    );
    
    // Create options for this group
    foreach ($groupData['options'] as $optionData) {
        ModifierOption::firstOrCreate(
            [
                'name' => $optionData['name'],
                'modifier_group_id' => $modifierGroup->id,
            ],
            [
                'price' => $optionData['price'],
            ]
        );
    }
    
    $createdModifierGroups[] = $modifierGroup;
    echo "  ✓ Modifier Group: {$modifierGroup->name} (with " . count($groupData['options']) . " options)\n";
}
echo "\n";

// ============================================
// 6. CREATE MENU ITEMS
// ============================================
echo "Creating Menu Items...\n";

$menuItems = [
    // Appetizers
    [
        'name' => 'Bruschetta',
        'description' => 'Toasted bread with fresh tomatoes, basil, and garlic',
        'price' => 8.99,
        'category' => 'Appetizers',
        'veg_non_veg' => 'veg',
        'has_variations' => false,
        'modifiers' => [],
    ],
    [
        'name' => 'Chicken Wings',
        'description' => 'Crispy chicken wings with your choice of sauce',
        'price' => 12.99,
        'category' => 'Appetizers',
        'veg_non_veg' => 'non-veg',
        'has_variations' => true,
        'variations' => [
            ['name' => '6 Pieces', 'price' => 12.99],
            ['name' => '12 Pieces', 'price' => 22.99],
        ],
        'modifiers' => ['Spice Level'],
    ],
    [
        'name' => 'Mozzarella Sticks',
        'description' => 'Breaded mozzarella with marinara sauce',
        'price' => 9.99,
        'category' => 'Appetizers',
        'veg_non_veg' => 'veg',
        'has_variations' => false,
        'modifiers' => [],
    ],
    
    // Main Courses
    [
        'name' => 'Grilled Salmon',
        'description' => 'Fresh salmon with lemon butter sauce and vegetables',
        'price' => 24.99,
        'category' => 'Main Courses',
        'veg_non_veg' => 'non-veg',
        'has_variations' => false,
        'modifiers' => [],
    ],
    [
        'name' => 'Ribeye Steak',
        'description' => '12oz ribeye steak cooked to perfection',
        'price' => 32.99,
        'category' => 'Main Courses',
        'veg_non_veg' => 'non-veg',
        'has_variations' => true,
        'variations' => [
            ['name' => '8oz', 'price' => 28.99],
            ['name' => '12oz', 'price' => 32.99],
            ['name' => '16oz', 'price' => 38.99],
        ],
        'modifiers' => ['Spice Level'],
    ],
    [
        'name' => 'Vegetable Stir Fry',
        'description' => 'Fresh mixed vegetables in savory sauce',
        'price' => 16.99,
        'category' => 'Main Courses',
        'veg_non_veg' => 'veg',
        'has_variations' => false,
        'modifiers' => ['Add-ons'],
    ],
    
    // Pizza
    [
        'name' => 'Margherita Pizza',
        'description' => 'Classic pizza with tomato, mozzarella, and basil',
        'price' => 14.99,
        'category' => 'Pizza',
        'veg_non_veg' => 'veg',
        'has_variations' => true,
        'variations' => [
            ['name' => 'Small (10")', 'price' => 12.99],
            ['name' => 'Medium (12")', 'price' => 14.99],
            ['name' => 'Large (16")', 'price' => 18.99],
        ],
        'modifiers' => ['Add-ons'],
    ],
    [
        'name' => 'Pepperoni Pizza',
        'description' => 'Classic pepperoni with mozzarella cheese',
        'price' => 16.99,
        'category' => 'Pizza',
        'veg_non_veg' => 'non-veg',
        'has_variations' => true,
        'variations' => [
            ['name' => 'Small (10")', 'price' => 14.99],
            ['name' => 'Medium (12")', 'price' => 16.99],
            ['name' => 'Large (16")', 'price' => 20.99],
        ],
        'modifiers' => ['Add-ons'],
    ],
    [
        'name' => 'Vegetarian Supreme',
        'description' => 'Loaded with fresh vegetables',
        'price' => 17.99,
        'category' => 'Pizza',
        'veg_non_veg' => 'veg',
        'has_variations' => true,
        'variations' => [
            ['name' => 'Small (10")', 'price' => 15.99],
            ['name' => 'Medium (12")', 'price' => 17.99],
            ['name' => 'Large (16")', 'price' => 21.99],
        ],
        'modifiers' => ['Add-ons'],
    ],
    
    // Pasta
    [
        'name' => 'Spaghetti Carbonara',
        'description' => 'Creamy pasta with bacon and parmesan',
        'price' => 18.99,
        'category' => 'Pasta',
        'veg_non_veg' => 'non-veg',
        'has_variations' => false,
        'modifiers' => ['Add-ons'],
    ],
    [
        'name' => 'Fettuccine Alfredo',
        'description' => 'Rich and creamy alfredo sauce',
        'price' => 17.99,
        'category' => 'Pasta',
        'veg_non_veg' => 'veg',
        'has_variations' => false,
        'modifiers' => ['Add-ons'],
    ],
    
    // Salads
    [
        'name' => 'Caesar Salad',
        'description' => 'Fresh romaine lettuce with caesar dressing',
        'price' => 12.99,
        'category' => 'Salads',
        'veg_non_veg' => 'veg',
        'has_variations' => true,
        'variations' => [
            ['name' => 'Regular', 'price' => 12.99],
            ['name' => 'Large', 'price' => 16.99],
        ],
        'modifiers' => ['Dressing', 'Add-ons'],
    ],
    [
        'name' => 'Garden Salad',
        'description' => 'Mixed greens with fresh vegetables',
        'price' => 10.99,
        'category' => 'Salads',
        'veg_non_veg' => 'veg',
        'has_variations' => false,
        'modifiers' => ['Dressing'],
    ],
    
    // Desserts
    [
        'name' => 'Chocolate Lava Cake',
        'description' => 'Warm chocolate cake with molten center',
        'price' => 8.99,
        'category' => 'Desserts',
        'veg_non_veg' => 'veg',
        'has_variations' => false,
        'modifiers' => [],
    ],
    [
        'name' => 'Tiramisu',
        'description' => 'Classic Italian dessert',
        'price' => 9.99,
        'category' => 'Desserts',
        'veg_non_veg' => 'veg',
        'has_variations' => false,
        'modifiers' => [],
    ],
    [
        'name' => 'Ice Cream Sundae',
        'description' => 'Vanilla ice cream with chocolate sauce',
        'price' => 7.99,
        'category' => 'Desserts',
        'veg_non_veg' => 'veg',
        'has_variations' => true,
        'variations' => [
            ['name' => 'Single Scoop', 'price' => 7.99],
            ['name' => 'Double Scoop', 'price' => 11.99],
        ],
        'modifiers' => [],
    ],
    
    // Beverages
    [
        'name' => 'Coca Cola',
        'description' => 'Classic cola drink',
        'price' => 2.99,
        'category' => 'Beverages',
        'veg_non_veg' => 'veg',
        'has_variations' => true,
        'variations' => [
            ['name' => 'Small', 'price' => 2.99],
            ['name' => 'Medium', 'price' => 3.99],
            ['name' => 'Large', 'price' => 4.99],
        ],
        'modifiers' => [],
    ],
    [
        'name' => 'Fresh Orange Juice',
        'description' => 'Freshly squeezed orange juice',
        'price' => 4.99,
        'category' => 'Beverages',
        'veg_non_veg' => 'veg',
        'has_variations' => false,
        'modifiers' => [],
    ],
    [
        'name' => 'Coffee',
        'description' => 'Hot brewed coffee',
        'price' => 3.99,
        'category' => 'Beverages',
        'veg_non_veg' => 'veg',
        'has_variations' => false,
        'modifiers' => [],
    ],
];

$createdMenuItems = [];
foreach ($menuItems as $itemData) {
    // ItemCategory uses translations, so we need to check differently
    $category = ItemCategory::where('branch_id', $branch->id)
                            ->get()
                            ->first(function($cat) use ($itemData) {
                                return $cat->category_name === $itemData['category'];
                            });
    
    if (!$category) {
        echo "  ⚠ Category '{$itemData['category']}' not found, skipping {$itemData['name']}\n";
        continue;
    }
    
    // Check if item already exists
    $existingItem = MenuItem::where('item_name', $itemData['name'])
                           ->where('branch_id', $branch->id)
                           ->first();
    
    if ($existingItem) {
        echo "  ⚠ Menu item '{$itemData['name']}' already exists, skipping\n";
        continue;
    }
    
    $menuItem = MenuItem::create([
        'item_name' => $itemData['name'],
        'description' => $itemData['description'] ?? null,
        'price' => $itemData['price'],
        'menu_id' => $menu->id,
        'item_category_id' => $category->id,
        'branch_id' => $branch->id,
        'veg_non_veg' => $itemData['veg_non_veg'] ?? 'veg',
        'is_available' => true,
        'show_on_customer_site' => true,
    ]);
    
    // Add variations if any
    if (!empty($itemData['variations'])) {
        foreach ($itemData['variations'] as $variationData) {
            MenuItemVariation::create([
                'menu_item_id' => $menuItem->id,
                'variation' => $variationData['name'],
                'name' => $variationData['name'],
                'price' => $variationData['price'],
            ]);
        }
    }
    
    // Attach modifier groups if any
    if (!empty($itemData['modifiers'])) {
        foreach ($itemData['modifiers'] as $modifierGroupName) {
            $modifierGroup = ModifierGroup::where('name', $modifierGroupName)
                                         ->where('branch_id', $branch->id)
                                         ->first();
            if ($modifierGroup) {
                $menuItem->modifierGroups()->attach($modifierGroup->id);
            }
        }
    }
    
    $createdMenuItems[] = $menuItem;
    $variationsText = !empty($itemData['variations']) ? ' (' . count($itemData['variations']) . ' variations)' : '';
    $modifiersText = !empty($itemData['modifiers']) ? ' [Modifiers: ' . implode(', ', $itemData['modifiers']) . ']' : '';
    echo "  ✓ Menu Item: {$menuItem->item_name} - \${$itemData['price']}{$variationsText}{$modifiersText}\n";
}
echo "\n";

// ============================================
// SUMMARY
// ============================================
echo "=== Summary ===\n";
echo "Menu: {$menu->menu_name} (ID: {$menu->id})\n";
echo "Areas Created: " . count($createdAreas) . "\n";
echo "Tables Created: " . count($createdTables) . "\n";
echo "Categories Created: " . count($createdCategories) . "\n";
echo "Modifier Groups Created: " . count($createdModifierGroups) . "\n";
echo "Menu Items Created: " . count($createdMenuItems) . "\n";
echo "\n";
echo "✓ Demo data added successfully!\n";
echo "\n";
echo "You can now test the mobile app with:\n";
echo "- " . count($createdTables) . " tables across " . count($createdAreas) . " areas\n";
echo "- " . count($createdMenuItems) . " menu items in " . count($createdCategories) . " categories\n";
echo "- Items with variations and modifiers for comprehensive testing\n";

