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

class HeadOfficeWalkInProofTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_every_non_cash_payment_requires_proof_but_cash_and_cod_do_not(): void
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
            ['online', 'online_mop', 'PAYMAYA', 'proof_of_payment'],
            ['wholesale', 'mode_of_payment', 'BANK_TRANSFER', 'proof_of_payment'],
        ] as [$channel, $field, $method, $errorField]) {
            $this->post(route('sales.storeMultiChannelSale'), $base + [
                'channel_type' => $channel, $field => $method,
            ])->assertSessionHasErrors($errorField);
        }

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
        Storage::fake('public');
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
            'quotation_proofs' => [UploadedFile::fake()->create('quote.pdf', 10, 'application/pdf')],
            'walkin_payment_proofs' => [UploadedFile::fake()->create('payment.pdf', 10, 'application/pdf')],
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $pending = PendingSale::sole();
        $this->assertSame('WALK-IN002', $pending->invoice_number);
        $this->assertSame('BANK_TRANSFER', $pending->mode_of_payment);
        $this->assertSame('123 Sample Street', $pending->delivery_address);
        $this->assertCount(1, $pending->quotation_proofs);
        $this->assertCount(1, $pending->walkin_payment_proofs);
        Storage::disk('public')->assertExists($pending->quotation_proofs[0]);
        Storage::disk('public')->assertExists($pending->walkin_payment_proofs[0]);

        $this->post(route('sales.confirmPending', $pending))->assertSessionHas('success');
        $sale = SalesTransaction::sole();
        $this->assertSame('123 Sample Street', $sale->address);
        $this->assertSame($pending->quotation_proofs, $sale->quotation_proofs);
        $this->assertSame($pending->walkin_payment_proofs, $sale->walkin_payment_proofs);
    }
}
