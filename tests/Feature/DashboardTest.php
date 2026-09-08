<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\SalesTransaction;
use App\Models\StoreHub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_filters_real_sales_products_and_inventory(): void
    {
        $hub = StoreHub::create(['name' => 'Dashboard Branch', 'code' => 'DB', 'status' => 'active']);
        $other = StoreHub::create(['name' => 'Other Branch', 'code' => 'OTH', 'status' => 'active']);
        $user = User::factory()->create(['role' => 'admin']);
        $product = Product::create(['store_hub_id' => $hub->id, 'item_id' => 'DASH-1', 'name' => 'Real Bestseller', 'stock' => 3, 'status' => 'active']);
        foreach ([['2026-09-08', 100, 'completed', $hub->id], ['2026-09-07', 50, 'completed', $hub->id], ['2026-09-09', 900, 'completed', $hub->id], ['2026-09-08', 800, 'pending', $hub->id], ['2026-09-08', 700, 'completed', $other->id]] as [$date, $total, $status, $store]) {
            $sale = SalesTransaction::create(['user_id' => $user->id, 'store_hub_id' => $store, 'customer_name' => 'Dashboard Customer', 'channel_type' => 'walk_in', 'order_number' => uniqid(), 'order_date' => $date, 'grand_total' => $total, 'status' => $status, 'mode_of_payment' => 'GCASH']);
            $sale->items()->create(['product_id' => $product->id, 'quantity' => 1, 'unit_price' => $total, 'line_total' => $total]);
        }
        $response = $this->actingAs($user)->get(route('dashboard', ['hub_id' => $hub->id, 'date' => '2026-09-08']));
        $response->assertOk()->assertSee('Real Bestseller')->assertSee('Low stock');
        $this->assertEquals(100, $response->viewData('salesTotals')['Daily']);
        $this->assertEquals(150, $response->viewData('salesTotals')['Weekly']);
        $this->assertEquals(150, $response->viewData('matrix')->first()['amounts']['GCash']);
        $this->assertEquals(2, $response->viewData('topProducts')->first()['units']);
        $this->assertEquals(1, $response->viewData('alertCount'));

        $staff = User::factory()->create(['role' => 'sales_associate', 'hub_id' => $hub->id]);
        $this->actingAs($staff)->get(route('dashboard', ['hub_id' => $other->id]))->assertForbidden();
        $this->get(route('dashboard'))->assertOk()->assertViewHas('dashboardHubs', fn ($hubs) => $hubs->modelKeys() === [$hub->id]);
    }
}
