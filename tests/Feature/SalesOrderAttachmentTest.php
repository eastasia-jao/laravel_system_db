<?php

namespace Tests\Feature;

use App\Models\PendingSale;
use App\Models\Product;
use App\Models\StoreHub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SalesOrderAttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_wholesale_and_online_orders_can_attach_documents_for_inventory_preview(): void
    {
        Storage::fake('local');
        $hub = StoreHub::create(['name' => 'Head Office', 'code' => 'HO', 'status' => 'active', 'is_head_office' => true]);
        $product = Product::create([
            'item_id' => 'ATTACH-1',
            'name' => 'Attachment Product',
            'store_hub_id' => $hub->id,
            'sales_price' => 100,
            'stock' => 20,
            'status' => 'active',
        ]);
        $salesUser = User::factory()->create(['role' => 'admin', 'hub_id' => $hub->id]);
        $inventoryUser = User::factory()->create(['role' => 'inventory_staff', 'hub_id' => $hub->id]);
        $channels = [
            'wholesale' => UploadedFile::fake()->createWithContent(
                'wholesale-order.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a5ZkAAAAASUVORK5CYII=')
            ),
            'online' => UploadedFile::fake()->createWithContent('online-order.pdf', "%PDF-1.4\nOrder document\n%%EOF"),
        ];

        $this->actingAs($salesUser)->get(route('hub.dashboard', $hub))
            ->assertOk()
            ->assertSee('Wholesale order details')
            ->assertSee('Online order details')
            ->assertSee('Payment reconciliation')
            ->assertSee('Proof / Received Amount (Total Amount)')
            ->assertSee('received total − sub total')
            ->assertSee('id="onlineProofAmount" class="form-control bg-white fw-bold online-input" value="0.00" readonly disabled', false)
            ->assertDontSee('name="grand_total" id="onlineGrandTotal" class="form-control', false)
            ->assertSee('name="delivery_fee" id="onlineDeliveryFee"', false)
            ->assertSee('name="proof_amount" id="onlineProofAmount"', false)
            ->assertSee('Order Attachment (Image/PDF)');

        foreach ($channels as $channel => $attachment) {
            $this->post(route('sales.storeMultiChannelSale'), [
                'store_hub_id' => $hub->id,
                'sales_channel' => $channel,
                'customer_name' => 'Attachment Customer',
                'order_date' => '2026-10-02',
                'mode_of_payment' => 'CASH',
                'delivery_fee' => $channel === 'online' ? 12 : 0,
                'proof_amount' => $channel === 'online' ? 112 : 0,
                'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100]],
                'order_slip' => $attachment,
            ])->assertSessionHasNoErrors()->assertSessionHas('success');
        }

        $sales = PendingSale::orderBy('id')->get();
        $this->assertCount(2, $sales);
        foreach ($sales as $sale) {
            $this->assertNotEmpty($sale->order_slip);
            Storage::disk('local')->assertExists($sale->order_slip);
        }
        $this->assertSame(12, (int) $sales[1]->delivery_fee);
        $this->assertSame(112, (int) $sales[1]->proof_amount);

        $this->actingAs($inventoryUser)->get(route('sales.pending'))
            ->assertOk()
            ->assertDontSee('Preview Order Attachment')
            ->assertSee('class="order-slip-preview"', false)
            ->assertSee('class="order-slip-pdf"', false)
            ->assertSee('class="order-slip-image"', false);

        $this->get(route('sales.order-slip', $sales[0]->id))->assertOk();
        $this->get(route('sales.order-slip', $sales[1]->id))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }
}
