<?php

namespace Tests\Feature;

use App\Models\PendingSale;
use App\Models\Product;
use App\Models\StoreHub;
use App\Models\User;
use App\Support\S3ErrorDiagnostics;
use Aws\Command;
use Aws\S3\Exception\S3Exception;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SalesOrderAttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_payment_proof_upload_is_written_to_application_logs(): void
    {
        config(['filesystems.attachment_disks.sales' => 'supabase']);
        $hub = StoreHub::create(['name' => 'Head Office', 'code' => 'HO-LOG', 'status' => 'active', 'is_head_office' => true]);
        $product = Product::create([
            'item_id' => 'ATTACH-LOG',
            'name' => 'Attachment Product',
            'store_hub_id' => $hub->id,
            'sales_price' => 100,
            'stock' => 20,
            'status' => 'active',
        ]);
        $salesUser = User::factory()->create(['role' => 'admin', 'hub_id' => $hub->id]);
        Storage::shouldReceive('disk')
            ->with('supabase')
            ->andThrow(new \RuntimeException('Simulated Supabase PutObject rejection.'));
        Log::spy();

        $this->actingAs($salesUser)->from(route('hub.dashboard', $hub))
            ->post(route('sales.storeMultiChannelSale'), [
                'store_hub_id' => $hub->id,
                'sales_channel' => 'wholesale',
                'customer_name' => 'Attachment Customer',
                'order_date' => '2026-10-20',
                'mode_of_payment' => 'GCASH',
                'proof_of_payment' => UploadedFile::fake()->create('payment-proof.pdf', 10, 'application/pdf'),
                'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100]],
            ])
            ->assertRedirect(route('hub.dashboard', $hub))
            ->assertSessionHas('error', 'Failed to submit multi-channel sale: Simulated Supabase PutObject rejection.');

        Log::shouldHaveReceived('error')->once()->withArgs(function ($message, $context) use ($hub, $salesUser) {
            return $message === 'Failed to submit multi-channel sale.'
                && $context['user_id'] === $salesUser->id
                && (int) $context['hub_id'] === $hub->id
                && $context['channel'] === 'wholesale'
                && $context['exception'] instanceof \RuntimeException;
        });
    }

    public function test_s3_error_diagnostics_extract_only_safe_response_details(): void
    {
        $exception = new S3Exception('PutObject failed', new Command('PutObject'), [
            'response' => new Response(400, [
                'Content-Type' => 'text/plain',
                'x-request-id' => 'supabase-request-123',
            ], 'Project not specified.'),
        ]);

        $this->assertSame([
            'http_status' => 400,
            'content_type' => 'text/plain',
            'request_id' => 'supabase-request-123',
            'aws_request_id' => null,
            'response_body' => 'Project not specified.',
        ], S3ErrorDiagnostics::from($exception));

        $this->assertSame([], S3ErrorDiagnostics::from(new \RuntimeException('Not an AWS error.')));
    }

    public function test_wholesale_and_online_orders_can_attach_documents_for_inventory_preview(): void
    {
        config(['filesystems.attachment_disks.sales' => 'sales-attachments']);
        Storage::fake('sales-attachments');
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
            ->assertSee('id="onlineDifference"', false)
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
            Storage::disk('sales-attachments')->assertExists($sale->order_slip);
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
