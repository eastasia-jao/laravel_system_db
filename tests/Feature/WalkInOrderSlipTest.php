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
        $this->assertSame('WALK-IN001', $sale->invoice_number);
        Storage::disk('local')->assertExists($sale->order_slip);
        $this->assertEquals(10, $product->fresh()->stock);
        $inventory = User::factory()->create(['role' => 'inventory_staff', 'hub_id' => $hub->id]);
        $this->actingAs($inventory)->get(route('sales.pending'))->assertOk()
            ->assertSee('Open full size')
            ->assertDontSee('Preview Order Attachment')
            ->assertSee('Awaiting Inventory Verification')
            ->assertSee('review-items-modal', false)
            ->assertSee('Order Items')
            ->assertSee('Order Summary');
        $this->get(route('sales.order-slip', $sale->id))->assertOk()->assertHeader('Content-Type', 'image/png');
        $sale->update(['invoice_number' => null]);
        $this->post(route('sales.confirmPending', $sale->id))->assertSessionHas('success');
        $this->assertEquals(8, $product->fresh()->stock);
        $this->assertSame($sale->order_slip, SalesTransaction::sole()->order_slip);
        $this->assertSame('WALK-IN'.str_pad((string) $sale->id, 3, '0', STR_PAD_LEFT), SalesTransaction::sole()->order_number);
        $salesUser = User::factory()->create(['role' => 'sales_marketing_staff', 'hub_id' => $hub->id]);
        $this->actingAs($salesUser)->get(route('sales.order-slip', $sale->id))->assertForbidden();
    }

    public function test_branch_walk_in_sale_can_select_existing_or_enter_new_customer_and_formats_name(): void
    {
        $hub = StoreHub::create(['name' => 'Branch', 'code' => 'BR-CUSTOMER', 'status' => 'active', 'is_head_office' => false]);
        $additionalHub = StoreHub::create(['name' => 'Second Branch', 'code' => 'BR-CUSTOMER-2', 'status' => 'active', 'is_head_office' => false]);
        $unassignedHub = StoreHub::create(['name' => 'Unassigned Branch', 'code' => 'BR-CUSTOMER-3', 'status' => 'active', 'is_head_office' => false]);
        $staff = User::factory()->create(['role' => 'sales_associate', 'hub_id' => $hub->id]);
        $staff->assignedStoreHubs()->attach($additionalHub->id);
        $product = Product::create(['item_id' => 'CUSTOMER-1', 'name' => 'Customer Product', 'store_hub_id' => $hub->id, 'sales_price' => 100, 'stock' => 10, 'status' => 'active']);
        $additionalProduct = Product::create(['item_id' => 'CUSTOMER-2', 'name' => 'Second Branch Product', 'store_hub_id' => $additionalHub->id, 'sales_price' => 125, 'stock' => 7, 'status' => 'active']);
        SalesTransaction::create([
            'user_id' => $staff->id,
            'store_hub_id' => $hub->id,
            'channel_type' => 'walk_in',
            'customer_name' => 'Existing Customer',
            'contact_number' => '+639123456789',
            'order_number' => 'EXISTING-001',
            'order_date' => '2026-09-28',
            'status' => 'completed',
        ]);

        $this->actingAs($staff)->get(route('hub.dashboard', $hub->id))
            ->assertOk()
            ->assertSee('list="existingWalkInCustomers"', false)
            ->assertSee('name="location" id="walkInLocation"', false)
            ->assertSee('value="'.$additionalHub->name.'"', false)
            ->assertSee('Choose an existing Walk-In customer or enter a new customer name.');

        $this->get(route('hub.sales.customers', ['hubId' => $hub->id, 'channel' => 'walk_in']))
            ->assertOk()
            ->assertJsonFragment(['name' => 'Existing Customer', 'contact_number' => '+639123456789']);
        $this->getJson(route('hub.products.search.ajax', ['hubId' => $additionalHub->id, 'q' => 'CUSTOMER-2', 'active_only' => 1]))
            ->assertOk()
            ->assertJsonFragment(['id' => $additionalProduct->id]);
        $this->getJson(route('hub.products.search.ajax', ['hubId' => $unassignedHub->id, 'q' => 'CUSTOMER-2', 'active_only' => 1]))
            ->assertForbidden();

        $this->post(route('sales.storeMultiChannelSale'), [
            'store_hub_id' => $additionalHub->id,
            'sales_channel' => 'walk_in',
            'location' => 'Incorrect location value',
            'customer_name' => '   jANE    DOE ',
            'mode_of_payment' => 'CASH',
            'order_date' => '2026-09-28',
            'items' => [['product_id' => $additionalProduct->id, 'quantity' => 1, 'unit_price' => 125]],
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertDatabaseHas('pending_sales', [
            'store_hub_id' => $additionalHub->id,
            'customer_name' => 'Jane Doe',
            'location' => $additionalHub->name,
        ]);
    }
}
