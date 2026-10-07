<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductReplacement;
use App\Models\ProductStockAllocation;
use App\Models\InventoryTransaction;
use App\Models\SalesTransaction;
use App\Models\StoreHub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExchangePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_supported_channels_require_full_credit_and_can_exchange_into_multiple_products(): void
    {
        $hub = StoreHub::create(['name' => 'Exchange Hub', 'code' => 'EX', 'status' => 'active', 'is_head_office' => true]);
        $admin = User::factory()->create(['role' => 'admin']);
        $routes = [
            'walk_in' => 'hub.report.walk-in.replacement.store',
            'online' => 'hub.report.online.replacement.store',
            'wholesale' => 'hub.report.wholesale.replace',
            'tiktok' => 'hub.report.tiktok.replacement.store',
        ];

        foreach ($routes as $channel => $routeName) {
            $prefix = strtoupper($channel);
            $original = $this->product($hub, $prefix.'-ORIGINAL', 1000);
            $lower = $this->product($hub, $prefix.'-LOWER', 800);
            $addOn = $this->product($hub, $prefix.'-ADDON', 300);
            if ($channel !== 'walk_in') {
                ProductStockAllocation::create(['product_id' => $original->id, $channel => 1]);
                ProductStockAllocation::create(['product_id' => $lower->id, $channel => 5]);
                ProductStockAllocation::create(['product_id' => $addOn->id, $channel => 5]);
            }
            $sale = SalesTransaction::create([
                'user_id' => $admin->id, 'store_hub_id' => $hub->id, 'channel_type' => $channel,
                'customer_name' => 'Exchange Customer', 'order_number' => $prefix.'-ORDER', 'order_date' => '2026-09-25',
                'sub_total' => 1000, 'total_amount' => 1000, 'grand_total' => 1000,
                'status' => 'confirmed', 'payment_status' => 'paid', 'amount_paid' => 1000,
            ]);
            $item = $sale->items()->create(['product_id' => $original->id, 'quantity' => 1, 'unit_price' => 1000, 'line_total' => 1000]);
            $url = route($routeName, [$hub, $sale, $item]);

            if ($channel === 'online') {
                $original->increment('stock');
                $original->stockAllocation->increment($channel);
                InventoryTransaction::create([
                    'type' => 'return',
                    'reference' => $sale->order_number,
                    'store_hub_id' => $hub->id,
                    'product_id' => $original->id,
                    'sales_transaction_id' => $sale->id,
                    'transaction_item_id' => $item->id,
                    'channel' => $channel,
                    'condition' => 'good',
                    'quantity' => 1,
                    'occurred_on' => '2026-09-26',
                    'created_by' => $admin->id,
                ]);
            }

            $this->actingAs($admin)->post($url, [
                'replacement_product_id' => $lower->id, 'quantity' => 1, 'replacement_quantity' => 1,
            ])->assertSessionHasErrors('replacement_product_id');

            $this->post($url, [
                'replacement_product_id' => $lower->id, 'quantity' => 1, 'replacement_quantity' => 1,
                'additional_items' => [['product_id' => $addOn->id, 'quantity' => 1, 'discount_percentage' => 0]],
                'exchange_payment_amount' => 100,
                'exchange_payment_method' => 'CASH',
            ])->assertSessionHasNoErrors()->assertSessionHas('success');

            $exchange = ProductReplacement::where('transaction_id', $sale->id)->get();
            $this->assertCount(2, $exchange);
            $this->assertCount(1, $exchange->pluck('exchange_reference')->unique());
            $this->assertSame('1000.00', $exchange->first()->exchange_credit);
            $this->assertSame('1100.00', $exchange->first()->exchange_total);
            $this->assertSame('100.00', $exchange->first()->additional_payment_due);

            if ($channel !== 'online') {
                $original->increment('stock');
                if ($channel !== 'walk_in') {
                    $original->stockAllocation->increment($channel);
                }
                InventoryTransaction::create([
                    'type' => 'return',
                    'reference' => $sale->order_number,
                    'store_hub_id' => $hub->id,
                    'product_id' => $original->id,
                    'sales_transaction_id' => $sale->id,
                    'transaction_item_id' => $item->id,
                    'channel' => $channel,
                    'condition' => 'good',
                    'quantity' => 1,
                    'occurred_on' => '2026-09-26',
                    'created_by' => $admin->id,
                ]);
            }
            $this->post(route('wholesale-replacements.approve', $exchange->first()))
                ->assertSessionHasNoErrors()->assertSessionHas('success');
            $this->assertSame(11, $original->fresh()->stock);
            $this->assertSame(9, $lower->fresh()->stock);
            $this->assertSame(9, $addOn->fresh()->stock);
            $this->assertSame('1100.00', $sale->fresh()->grand_total);
            $this->assertSame('1100.00', $sale->fresh()->amount_paid);
            $this->assertDatabaseHas('sales_payment_records', [
                'sales_transaction_id' => $sale->id,
                'amount' => 100,
                'mode_of_payment' => 'CASH',
            ]);
            if (in_array($channel, ['walk_in', 'wholesale'], true)) {
                $this->assertSame('paid', $sale->fresh()->payment_status);
            }
        }
    }

    public function test_shopee_and_lazada_orders_cannot_use_the_exchange_endpoint(): void
    {
        $hub = StoreHub::create(['name' => 'Marketplace Hub', 'code' => 'MP', 'status' => 'active', 'is_head_office' => true]);
        $admin = User::factory()->create(['role' => 'admin']);
        $original = $this->product($hub, 'MP-ORIGINAL', 1000);
        $replacement = $this->product($hub, 'MP-REPLACEMENT', 1000);

        foreach (['shopee', 'lazada'] as $channel) {
            $sale = SalesTransaction::create([
                'user_id' => $admin->id, 'store_hub_id' => $hub->id, 'channel_type' => $channel,
                'customer_name' => 'Marketplace Customer', 'order_number' => strtoupper($channel).'-NO-EXCHANGE',
                'order_date' => '2026-09-25', 'sub_total' => 1000, 'total_amount' => 1000,
                'grand_total' => 1000, 'status' => 'confirmed',
            ]);
            $item = $sale->items()->create(['product_id' => $original->id, 'quantity' => 1, 'unit_price' => 1000, 'line_total' => 1000]);

            $this->actingAs($admin)->post(route('hub.report.online.replacement.store', [$hub, $sale, $item]), [
                'replacement_product_id' => $replacement->id, 'quantity' => 1, 'replacement_quantity' => 1,
            ])->assertNotFound();
        }

        $this->assertDatabaseCount('product_replacements', 0);
    }

    private function product(StoreHub $hub, string $itemId, float $price): Product
    {
        return Product::create([
            'store_hub_id' => $hub->id, 'item_id' => $itemId, 'name' => $itemId,
            'stock' => 10, 'sales_price' => $price, 'wholesale_price' => $price, 'status' => 'active',
        ]);
    }
}
