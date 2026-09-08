<?php

namespace Tests\Feature;

use App\Models\PendingSale;
use App\Models\Product;
use App\Models\SalesTransaction;
use App\Models\StoreHub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WalkInOrderSlipTest extends TestCase
{
    use RefreshDatabase;

    public function test_walk_in_order_slip_is_available_during_verification_and_preserved(): void
    {
        Storage::fake('local');
        $hub = StoreHub::create(['name' => 'Branch', 'code' => 'BR', 'status' => 'active']);
        $admin = User::factory()->create(['role' => 'admin', 'hub_id' => $hub->id]);
        $product = Product::create(['item_id' => 'SLIP-1', 'name' => 'Slip Product', 'store_hub_id' => $hub->id, 'sales_price' => 100, 'stock' => 10, 'status' => 'active']);
        $this->actingAs($admin)->get(route('hub.dashboard', $hub->id))->assertOk()
            ->assertSee('name="order_slip"', false)->assertSee('Submit for Inventory Verification')
            ->assertDontSee('process-walk-in-sale.php');
        $payload = [
            'store_hub_id' => $hub->id, 'sales_channel' => 'walk_in', 'customer_name' => 'Customer',
            'mode_of_payment' => 'CASH', 'order_date' => '2026-09-08',
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 100]],
        ];
        $this->post(route('sales.storeMultiChannelSale'), $payload + [
            'order_slip' => UploadedFile::fake()->create('invalid.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('order_slip');
        $this->assertDatabaseCount('pending_sales', 0);
        $this->post(route('sales.storeMultiChannelSale'), $payload + [
            'order_slip' => UploadedFile::fake()->createWithContent('order-slip.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a5ZkAAAAASUVORK5CYII=')),
        ])->assertSessionHasNoErrors()->assertSessionHas('success');
        $sale = PendingSale::sole();
        Storage::disk('local')->assertExists($sale->order_slip);
        $this->assertEquals(10, $product->fresh()->stock);
        $inventory = User::factory()->create(['role' => 'inventory_staff', 'hub_id' => $hub->id]);
        $this->actingAs($inventory)->get(route('sales.pending'))->assertOk()->assertSee('View full-size order slip');
        $this->get(route('sales.order-slip', $sale->id))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->post(route('sales.confirmPending', $sale->id))->assertSessionHas('success');
        $this->assertEquals(8, $product->fresh()->stock);
        $this->assertSame($sale->order_slip, SalesTransaction::sole()->order_slip);
        $salesUser = User::factory()->create(['role' => 'sales_marketing_staff', 'hub_id' => $hub->id]);
        $this->actingAs($salesUser)->get(route('sales.order-slip', $sale->id))->assertForbidden();
    }
}
