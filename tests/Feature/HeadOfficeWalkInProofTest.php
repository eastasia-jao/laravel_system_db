<?php

namespace Tests\Feature;

use App\Models\PendingSale;
use App\Models\Product;
use App\Models\SalesPaymentRecord;
use App\Models\SalesTransaction;
use App\Models\StoreHub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HeadOfficeWalkInProofTest extends TestCase
{
    use RefreshDatabase;

    public function test_wholesale_record_sale_saves_payment_proof_to_the_configured_attachment_disk(): void
    {
        config(['filesystems.attachment_disks.sales' => 'wholesale-attachments']);
        Storage::fake('public');
        Storage::fake('wholesale-attachments');
        $hub = StoreHub::create(['name' => 'Head Office', 'code' => 'HO-WH-PROOF', 'status' => 'active', 'is_head_office' => true]);
        $user = User::factory()->create(['hub_id' => $hub->id, 'role' => 'admin']);
        $product = Product::create([
            'item_id' => 'WHOLESALE-PROOF-001', 'name' => 'Wholesale Brush', 'sales_price' => 100,
            'stock' => 5, 'status' => 'active', 'store_hub_id' => $hub->id,
        ]);

        $this->actingAs($user)->post(route('sales.storeMultiChannelSale'), [
            'store_hub_id' => $hub->id,
            'channel_type' => 'wholesale',
            'order_date' => '2026-10-20',
            'customer_name' => 'Wholesale Proof Customer',
            'mode_of_payment' => 'BANK_TRANSFER',
            'proof_of_payment' => UploadedFile::fake()->create('wholesale-proof.pdf', 10, 'application/pdf'),
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100]],
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $pendingSale = PendingSale::where('sales_channel', 'wholesale')->sole();
        $this->assertNotNull($pendingSale->payment_proof);
        Storage::disk('wholesale-attachments')->assertExists($pendingSale->payment_proof);
        Storage::disk('public')->assertMissing($pendingSale->payment_proof);
    }

    public function test_walk_in_payment_record_proof_is_served_from_the_configured_attachment_disk(): void
    {
        config(['filesystems.attachment_disks.sales' => 'sales-attachments']);
        Storage::fake('sales-attachments');
        $hub = StoreHub::create(['name' => 'Head Office', 'code' => 'HO-WALKIN-PROOF', 'status' => 'active', 'is_head_office' => true]);
        $user = User::factory()->create(['hub_id' => $hub->id, 'role' => 'admin']);
        $sale = SalesTransaction::create([
            'user_id' => $user->id,
            'store_hub_id' => $hub->id,
            'channel_type' => 'walk_in',
            'order_number' => 'WALK-IN-PROOF-1',
            'customer_name' => 'Walk-In Proof Customer',
            'order_date' => '2026-10-20',
            'grand_total' => 100,
        ]);
        $path = 'proofs_of_payment/walk-in-payment.pdf';
        Storage::disk('sales-attachments')->put($path, "%PDF-1.4\nPayment\n%%EOF");
        $paymentRecord = SalesPaymentRecord::create([
            'sales_transaction_id' => $sale->id,
            'amount' => 100,
            'mode_of_payment' => 'BANK_TRANSFER',
            'walkin_payment_proofs' => [$path],
            'recorded_by' => $user->id,
        ]);

        $this->actingAs($user)->get(route('hub.report.payment-record-attachment', [
            'hub' => $hub->id,
            'paymentRecord' => $paymentRecord->id,
            'index' => 0,
        ]))->assertOk()->assertStreamedContent("%PDF-1.4\nPayment\n%%EOF");
    }

    public function test_walk_in_and_online_check_payments_require_and_save_check_details(): void
    {
        $hub = StoreHub::create(['name' => 'Head Office', 'code' => 'HO-CHECK', 'status' => 'active', 'is_head_office' => true]);
        $user = User::factory()->create(['hub_id' => $hub->id, 'role' => 'admin']);
        $product = Product::create([
            'item_id' => 'CHECK-001', 'name' => 'Brush', 'sales_price' => 100,
            'stock' => 5, 'status' => 'active', 'store_hub_id' => $hub->id,
        ]);
        $this->actingAs($user);

        foreach ([['walk_in', 'walkin_mop', 'DATED_CHECK'], ['online', 'online_mop', 'POST_DATED_CHECK']] as [$channel, $field, $method]) {
            $payload = [
                'store_hub_id' => $hub->id, 'channel_type' => $channel, 'order_date' => '2026-09-24',
                'customer_name' => 'Check Customer', $field => $method,
                'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100]],
            ];
            $this->post(route('sales.storeMultiChannelSale'), $payload)
                ->assertSessionHasErrors(['check_number', 'check_date', 'bank_name']);

            $this->post(route('sales.storeMultiChannelSale'), $payload + [
                'check_number' => 'CHK-123', 'check_date' => '2026-10-01', 'bank_name' => 'OTHERS',
            ])->assertSessionHasErrors('custom_bank_name');

            $proof = $channel === 'walk_in'
                ? ['walkin_payment_proofs' => [UploadedFile::fake()->create('payment.pdf', 10, 'application/pdf')]]
                : ['proof_of_payment' => UploadedFile::fake()->create('payment.pdf', 10, 'application/pdf')];
            $this->post(route('sales.storeMultiChannelSale'), $payload + $proof + [
                'check_number' => 'CHK-123', 'check_date' => '2026-10-01',
                'bank_name' => 'OTHERS', 'custom_bank_name' => 'Local Bank',
            ])->assertSessionHasNoErrors()->assertSessionHas('success');

            $sale = PendingSale::where('sales_channel', $channel)->sole();
            $this->assertSame($method, $sale->mode_of_payment);
            $this->assertSame('CHK-123', $sale->check_number);
            $this->assertSame('OTHERS', $sale->bank_name);
            $this->assertSame('Local Bank', $sale->custom_bank_name);
        }
    }

    public function test_online_payment_proof_is_optional_while_walk_in_and_wholesale_rules_remain(): void
    {
        $hub = StoreHub::create(['name' => 'Head Office', 'code' => 'HO-PAY', 'status' => 'active', 'is_head_office' => true]);
        $user = User::factory()->create(['hub_id' => $hub->id, 'role' => 'admin']);
        $product = Product::create([
            'item_id' => 'PAY-001', 'name' => 'Brush', 'sales_price' => 100,
            'stock' => 5, 'status' => 'active', 'store_hub_id' => $hub->id,
        ]);
        $this->actingAs($user);
        $base = [
            'store_hub_id' => $hub->id, 'order_date' => '2026-09-24',
            'customer_name' => 'Payment Customer',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100]],
        ];

        foreach ([
            ['walk_in', 'walkin_mop', 'GCASH', 'walkin_payment_proofs'],
            ['wholesale', 'mode_of_payment', 'BANK_TRANSFER', 'proof_of_payment'],
        ] as [$channel, $field, $method, $errorField]) {
            $this->post(route('sales.storeMultiChannelSale'), $base + [
                'channel_type' => $channel, $field => $method,
            ])->assertSessionHasErrors($errorField);
        }

        $this->post(route('sales.storeMultiChannelSale'), $base + [
            'channel_type' => 'online', 'online_mop' => 'PAYMAYA',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');
        $onlineSale = PendingSale::where('sales_channel', 'online')->sole();
        $this->assertNull($onlineSale->payment_proof);

        $this->post(route('sales.storeMultiChannelSale'), $base + [
            'channel_type' => 'walk_in', 'walkin_mop' => 'CASH',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->post(route('sales.storeMultiChannelSale'), $base + [
            'channel_type' => 'shopee', 'mode_of_payment' => 'GCASH',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->post(route('sales.storeMultiChannelSale'), $base + [
            'channel_type' => 'lazada', 'mode_of_payment' => 'GCASH',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');
    }

    public function test_walk_in_product_description_search_and_proofs_survive_verification(): void
    {
        config(['filesystems.attachment_disks.sales' => 'sales-attachments']);
        Storage::fake('sales-attachments');
        $hub = StoreHub::create(['name' => 'Head Office', 'code' => 'HO-PROOF', 'status' => 'active', 'is_head_office' => true]);
        $user = User::factory()->create(['hub_id' => $hub->id, 'role' => 'admin']);
        $product = Product::create([
            'item_id' => 'WALK-001', 'name' => 'Brush', 'description' => 'Fine sable bristles',
            'sales_price' => 100, 'stock' => 5, 'status' => 'active', 'store_hub_id' => $hub->id,
        ]);

        $this->actingAs($user)->get(route('hub.products.search.ajax', $hub->id).'?q=sable&active_only=1&sort=item_id')
            ->assertOk()->assertJsonFragment(['id' => $product->id, 'description' => 'Fine sable bristles']);

        $payload = [
            'store_hub_id' => $hub->id, 'channel_type' => 'walk_in', 'order_date' => '2026-09-24',
            'customer_name' => 'Walk-In Customer', 'address' => '123 Sample Street', 'walkin_mop' => 'BANK_TRANSFER',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100]],
        ];

        $this->post(route('sales.storeMultiChannelSale'), $payload)
            ->assertSessionHasErrors('walkin_payment_proofs');

        $this->post(route('sales.storeMultiChannelSale'), $payload + [
            'quotation_proofs' => array_fill(0, 4, UploadedFile::fake()->create('quote.pdf', 10, 'application/pdf')),
            'walkin_payment_proofs' => [UploadedFile::fake()->create('payment.pdf', 10, 'application/pdf')],
        ])->assertSessionHasErrors('quotation_proofs');

        $this->post(route('sales.storeMultiChannelSale'), $payload + [
            'quotation_proofs' => [UploadedFile::fake()->createWithContent('quote.pdf', "%PDF-1.4\nQuotation\n%%EOF")],
            'walkin_payment_proofs' => [UploadedFile::fake()->createWithContent('payment.pdf', "%PDF-1.4\nPayment\n%%EOF")],
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $pending = PendingSale::sole();
        $this->assertSame('WALK-IN002', $pending->invoice_number);
        $this->assertSame('BANK_TRANSFER', $pending->mode_of_payment);
        $this->assertSame('123 Sample Street', $pending->delivery_address);
        $this->assertCount(1, $pending->quotation_proofs);
        $this->assertCount(1, $pending->walkin_payment_proofs);
        Storage::disk('sales-attachments')->assertExists($pending->quotation_proofs[0]);
        Storage::disk('sales-attachments')->assertExists($pending->walkin_payment_proofs[0]);
        $this->get(route('sales.pending-attachment', [
            'id' => $pending->id, 'type' => 'quotation-proof', 'index' => 0,
        ]))->assertOk();
        $this->get(route('sales.pending-attachment', [
            'id' => $pending->id, 'type' => 'walkin-payment-proof', 'index' => 0,
        ]))->assertOk();

        $this->post(route('sales.confirmPending', $pending))->assertSessionHas('success');
        $sale = SalesTransaction::sole();
        $this->assertSame('123 Sample Street', $sale->address);
        $this->assertSame($pending->quotation_proofs, $sale->quotation_proofs);
        $this->assertSame($pending->walkin_payment_proofs, $sale->walkin_payment_proofs);
        $this->get(route('hub.report.sales-attachment', [
            'hub' => $hub->id, 'transaction' => $sale->id, 'type' => 'quotation-proof', 'index' => 0,
        ]))->assertOk();
        $this->get(route('hub.report.sales-attachment', [
            'hub' => $hub->id, 'transaction' => $sale->id, 'type' => 'walkin-payment-proof', 'index' => 0,
        ]))->assertOk();
        $this->get(route('hub.report', ['hub' => $hub->id, 'channel' => 'walk_in']))
            ->assertOk()
            ->assertSee('.online-proof-arrow,', false)
            ->assertSee("[data-online-proof-gallery]:not([data-proof-gallery-ready])", false);
    }
}
