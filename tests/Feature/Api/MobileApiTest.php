<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Restaurant;
use App\Models\Branch;
use App\Models\Table;
use App\Models\Area;
use App\Models\MenuItem;
use App\Models\ItemCategory;
use App\Models\OrderType;
use App\Models\Order;
use App\Models\Kot;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $restaurant;
    protected $branch;
    protected $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test restaurant
        $this->restaurant = Restaurant::factory()->create([
            'is_active' => true,
            'approval_status' => 'Approved',
        ]);

        // Create test branch
        $this->branch = Branch::factory()->create([
            'restaurant_id' => $this->restaurant->id,
        ]);

        // Create test user
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'restaurant_id' => $this->restaurant->id,
            'branch_id' => $this->branch->id,
        ]);

        // Set session for helper functions
        session(['user' => $this->user]);
        session(['restaurant' => $this->restaurant]);
        session(['branch' => $this->branch]);
    }

    protected function authenticate()
    {
        $response = $this->postJson('/api/mobile/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $this->token = $response->json('data.token');
        return $this->token;
    }

    protected function withAuth($method, $url, $data = [])
    {
        if (!$this->token) {
            $this->authenticate();
        }

        return $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ])->{$method}($url, $data);
    }

    /** @test */
    public function test_login_success()
    {
        $response = $this->postJson('/api/mobile/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'restaurant_id',
                        'branch_id',
                    ],
                    'token',
                    'token_type',
                ]
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    /** @test */
    public function test_login_failure()
    {
        $response = $this->postJson('/api/mobile/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
            ]);
    }

    /** @test */
    public function test_get_authenticated_user()
    {
        $this->authenticate();

        $response = $this->withAuth('get', '/api/mobile/auth/user');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                    ]
                ]
            ]);
    }

    /** @test */
    public function test_get_menu_items()
    {
        $category = ItemCategory::factory()->create([
            'branch_id' => $this->branch->id,
        ]);

        $menuItem = MenuItem::factory()->create([
            'branch_id' => $this->branch->id,
            'item_category_id' => $category->id,
            'status' => 'active',
        ]);

        $this->authenticate();

        $response = $this->withAuth('get', '/api/mobile/menu/items');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'items',
                    'total',
                ]
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    /** @test */
    public function test_get_menu_categories()
    {
        ItemCategory::factory()->count(3)->create([
            'branch_id' => $this->branch->id,
        ]);

        $this->authenticate();

        $response = $this->withAuth('get', '/api/mobile/menu/categories');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'categories',
                    'total',
                ]
            ]);
    }

    /** @test */
    public function test_get_tables()
    {
        $area = Area::factory()->create([
            'branch_id' => $this->branch->id,
        ]);

        Table::factory()->count(3)->create([
            'branch_id' => $this->branch->id,
            'area_id' => $area->id,
        ]);

        $this->authenticate();

        $response = $this->withAuth('get', '/api/mobile/tables');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'tables',
                    'total',
                ]
            ]);
    }

    /** @test */
    public function test_create_order()
    {
        $orderType = OrderType::factory()->create([
            'branch_id' => $this->branch->id,
        ]);

        $category = ItemCategory::factory()->create([
            'branch_id' => $this->branch->id,
        ]);

        $menuItem = MenuItem::factory()->create([
            'branch_id' => $this->branch->id,
            'item_category_id' => $category->id,
            'status' => 'active',
            'price' => 100,
        ]);

        $table = Table::factory()->create([
            'branch_id' => $this->branch->id,
        ]);

        $this->authenticate();

        $response = $this->withAuth('post', '/api/mobile/orders', [
            'table_id' => $table->id,
            'order_type_id' => $orderType->id,
            'number_of_pax' => 2,
            'items' => [
                [
                    'menu_item_id' => $menuItem->id,
                    'quantity' => 2,
                    'modifiers' => [],
                ]
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'order_number',
                    'items',
                ]
            ]);
    }

    /** @test */
    public function test_get_orders()
    {
        $orderType = OrderType::factory()->create([
            'branch_id' => $this->branch->id,
        ]);

        Order::factory()->count(3)->create([
            'branch_id' => $this->branch->id,
            'restaurant_id' => $this->restaurant->id,
            'order_type_id' => $orderType->id,
            'waiter_id' => $this->user->id,
        ]);

        $this->authenticate();

        $response = $this->withAuth('get', '/api/mobile/orders');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'orders',
                    'pagination',
                ]
            ]);
    }

    /** @test */
    public function test_get_kots()
    {
        $orderType = OrderType::factory()->create([
            'branch_id' => $this->branch->id,
        ]);

        $order = Order::factory()->create([
            'branch_id' => $this->branch->id,
            'restaurant_id' => $this->restaurant->id,
            'order_type_id' => $orderType->id,
        ]);

        Kot::factory()->count(2)->create([
            'branch_id' => $this->branch->id,
            'order_id' => $order->id,
        ]);

        $this->authenticate();

        $response = $this->withAuth('get', '/api/mobile/kots');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'kots',
                    'pagination',
                ]
            ]);
    }

    /** @test */
    public function test_create_payment()
    {
        $orderType = OrderType::factory()->create([
            'branch_id' => $this->branch->id,
        ]);

        $order = Order::factory()->create([
            'branch_id' => $this->branch->id,
            'restaurant_id' => $this->restaurant->id,
            'order_type_id' => $orderType->id,
            'total' => 100,
        ]);

        $this->authenticate();

        $response = $this->withAuth('post', '/api/mobile/payments', [
            'order_id' => $order->id,
            'payment_method' => 'cash',
            'amount' => 100,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'payment_amount',
                    'remaining_balance',
                ]
            ]);
    }

    /** @test */
    public function test_sync_pull()
    {
        $this->authenticate();

        $response = $this->withAuth('post', '/api/mobile/sync/pull', [
            'sync_types' => ['menu', 'tables'],
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'sync_timestamp',
            ]);
    }

    /** @test */
    public function test_sync_status()
    {
        $this->authenticate();

        $response = $this->withAuth('get', '/api/mobile/sync/status');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'server_time',
                ]
            ]);
    }

    /** @test */
    public function test_logout()
    {
        $this->authenticate();

        $response = $this->withAuth('post', '/api/mobile/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }
}

