<?php

namespace Tests\Feature;

use App\Models\{InventoryTransaction, Product, ProductReplacement, StoreHub, User, SalesTransaction};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchSalesReportTest extends TestCase
{
    use RefreshDatabase;

    private function legacy_shopee_and_lazada_reports_show_invoice_and_returned_item_status(): void
    {
        $this->markTestSkipped('Shopee and Lazada sales reports are intentionally hidden from the sales report area.');

        $hub = StoreHub::create(['name' => 'Head Office', 'code' => 'HO-MKT', 'status' => 'active', 'is_head_office' => true]);
        $user = User::factory()->create(['role' => 'admin']);
        $product = Product::create(['store_hub_id' => $hub->id, 'item_id' => 'MKT-RET-1', 'name' => 'Returned Marketplace Item', 'stock' => 10, 'status' => 'active']);

        foreach (['shopee', 'lazada'] as $channel) {
            $invoice = strtoupper($channel).'-000001';
            $condition = $channel === 'shopee' ? 'good' : 'damaged';
            $sale = SalesTransaction::create([
                'store_hub_id' => $hub->id, 'channel_type' => $channel, 'order_date' => '2026-09-28',
                'date_of_arrangement' => '2026-09-29',
                'order_number' => $invoice, 'customer_name' => ucfirst($channel).' Customer', 'status' => 'completed',
                'grand_total' => 200,
            ]);
            $sale->items()->create([
                'product_id' => $product->id, 'quantity' => 2, 'returned_quantity' => 1,
                'return_status' => 'received', 'return_condition' => $condition, 'unit_price' => 100,
                'discount_percentage' => 10, 'line_total' => 180,
            ]);
            InventoryTransaction::create([
                'type' => 'return', 'store_hub_id' => $hub->id, 'product_id' => $product->id,
                'sales_transaction_id' => $sale->id, 'transaction_item_id' => $sale->items()->first()->id,
                'channel' => $channel, 'quantity' => 1,
                'condition' => $condition, 'refund_amount' => 50, 'occurred_on' => '2026-09-28', 'created_by' => $user->id,
            ]);

            $this->actingAs($user)->get(route('hub.report', [
                'hub' => $hub->id,
                'channel' => $channel,
                'date_from' => '2026-09-29',
                'date_to' => '2026-09-29',
            ]))
                ->assertOk()
                ->assertSee('Total Gross Sales')
                ->assertSee('Total Discount')
                ->assertSee('₱10.00')
                ->assertSee('Total Transactions')
                ->assertSee('Total Return Items')
                ->assertSee('Preview / Save PNG')
                ->assertSee('marketplaceReportPreviewCanvas', false)
                ->assertSee('Total Discount')
                ->assertSee('new-customer-badge', false)
                ->assertSee('>NEW</span>', false)
                ->assertDontSee('Print / Save PDF')
                ->assertDontSee('Discounts')
                ->assertSee('Invoice No.')
                ->assertSee('Date of Arrangement')
                ->assertSee('Date of Arrangement From')
                ->assertSee('Sep 29, 2026')
                ->assertSee($invoice)
                ->assertSee('View Order')
                ->assertSee('PARTIALLY RETURNED')
                ->assertSee('Refund Recorded')
                ->assertSee('1 of 2 returned')
                ->assertSee(($condition === 'good' ? 'Good' : 'Damaged').': 1');

            $this->get(route('hub.report', [
                'hub' => $hub->id,
                'channel' => $channel,
                'date_from' => '2026-09-28',
                'date_to' => '2026-09-28',
            ]))
                ->assertOk()
                ->assertSee('No '.ucfirst($channel).' sales found.')
                ->assertDontSee($invoice);
        }
    }

    public function test_marketplace_channels_are_excluded_from_sales_reports(): void
    {
        $hub = StoreHub::create(['name' => 'Head Office', 'code' => 'HO-MKT', 'status' => 'active', 'is_head_office' => true]);
        $user = User::factory()->create(['role' => 'admin']);
        $product = Product::create(['store_hub_id' => $hub->id, 'item_id' => 'MKT-RET-1', 'name' => 'Reportable Item', 'stock' => 10, 'status' => 'active']);

        foreach (['shopee', 'lazada', 'tiktok'] as $channel) {
            $sale = SalesTransaction::create([
                'store_hub_id' => $hub->id,
                'channel_type' => $channel,
                'order_date' => '2026-09-28',
                'order_number' => strtoupper($channel).'-000001',
                'customer_name' => ucfirst($channel).' Customer',
                'status' => 'completed',
                'grand_total' => 200,
            ]);
            $sale->items()->create(['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 200, 'line_total' => 200]);
        }

        $onlineSale = SalesTransaction::create([
            'store_hub_id' => $hub->id,
            'channel_type' => 'online',
            'order_date' => '2026-09-28',
            'order_number' => 'ONLINE-REPORTABLE',
            'customer_name' => 'Online Customer',
            'status' => 'completed',
            'grand_total' => 125,
        ]);
        $onlineSale->items()->create(['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 125, 'line_total' => 125]);

        $response = $this->actingAs($user)->get(route('hub.report', [
            'hub' => $hub->id,
            'channel' => 'all',
            'date_from' => '2026-09-28',
            'date_to' => '2026-09-28',
        ]));

        $response->assertOk()
            ->assertSee('All Channels Sales Report')
            ->assertSee('ONLINE')
            ->assertSee('125.00')
            ->assertDontSee('SHOPEE-000001')
            ->assertDontSee('LAZADA-000001')
            ->assertDontSee('TIKTOK-000001')
            ->assertDontSee('channel=shopee', false)
            ->assertDontSee('channel=lazada', false)
            ->assertDontSee('channel=tiktok', false);
        $this->assertSame('all', $response->viewData('channel'));
        $this->assertSame(['online'], $response->viewData('salesByChannel')->pluck('channel_type')->all());
    }

    public function test_branch_report_only_shows_walk_in_orders_with_view_buttons(): void
    {
        $hub = StoreHub::create(['name' => 'Branch', 'code' => 'branch', 'status' => 'active', 'is_head_office' => false]);
        $user = User::factory()->create(['role' => 'admin']);
        foreach (['walk_in', 'tiktok'] as $channel) {
            SalesTransaction::create(['store_hub_id' => $hub->id, 'channel_type' => $channel, 'order_date' => '2026-09-08', 'order_number' => $channel.'-order', 'customer_name' => $channel.' customer']);
        }
        foreach (['all', 'tiktok', 'walk_in'] as $channel) {
            $response = $this->actingAs($user)->get(route('hub.report', ['hub' => $hub->id, 'channel' => $channel]));
            $response->assertOk()->assertSee('Walk-In Sales Report')->assertSee('View order')
                ->assertSee('walk_in customer')->assertDontSee('tiktok customer');
            $this->assertSame('walk_in', $response->viewData('channel'));
            $this->assertSame(1, $response->viewData('totalTransactions'));
            $document = new \DOMDocument;
            @$document->loadHTML($response->getContent());
            $xpath = new \DOMXPath($document);
            $this->assertSame(0, $xpath->query('//a[contains(@href,"channel=tiktok") or contains(@href,"channel=all") or contains(@href,"channel=wholesale")]')->length);
            $this->assertSame(1, $xpath->query('//button[@data-bs-toggle="modal" and starts-with(@data-bs-target,"#walk-in-order-")]')->length);
        }
    }

    public function test_branch_walk_in_report_uses_daily_summary_labels_and_payment_breakdown(): void
    {
        $this->travelTo('2026-09-29 09:00:00');
        $hub = StoreHub::create(['name' => 'Branch', 'code' => 'branch-daily', 'status' => 'active', 'is_head_office' => false]);
        $user = User::factory()->create(['role' => 'admin']);
        $product = Product::create(['store_hub_id' => $hub->id, 'item_id' => 'DAILY-1', 'name' => 'Daily Item', 'stock' => 10, 'status' => 'active']);
        $sale = SalesTransaction::create([
            'store_hub_id' => $hub->id,
            'channel_type' => 'walk_in',
            'order_date' => '2026-09-29',
            'order_number' => 'DAILY-SALE-1',
            'customer_name' => 'Daily Customer',
            'mode_of_payment' => 'GCASH',
            'grand_total' => 180,
        ]);
        $sale->items()->create([
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 100,
            'discount_percentage' => 10,
            'line_total' => 180,
        ]);
        SalesTransaction::create([
            'store_hub_id' => $hub->id,
            'channel_type' => 'walk_in',
            'order_date' => '2026-09-29',
            'order_number' => 'DAILY-SALE-OTHER',
            'customer_name' => 'Other Customer',
            'mode_of_payment' => 'BANK OF NAUJAN',
            'custom_mop' => 'BANK OF NAUJAN',
            'grand_total' => 50,
            'amount_paid' => 50,
        ]);

        $response = $this->actingAs($user)->get(route('hub.report', ['hub' => $hub->id, 'channel' => 'walk_in']));
        $response
            ->assertOk()
            ->assertSee('Today: Sep 29, 2026')
            ->assertSee('Total Gross Sales')
            ->assertSee('Total Discounts')
            ->assertSee('Daily Total Sales')
            ->assertSee('Daily Transactions')
            ->assertSee('Daily Total Purchased Items')
            ->assertSee('data-branch-daily-mop', false)
            ->assertSee('Sales by Payment Method')
            ->assertSee('GCASH')
            ->assertSee('OTHER')
            ->assertSee('₱180.00');
        $this->assertSame(['OTHER'], $response->viewData('paymentBreakdown')->pluck('payment_method')->all());

        $this->travelBack();
    }

    public function test_branch_daily_purchased_items_exclude_returns_and_count_approved_replacements(): void
    {
        $this->travelTo('2026-09-29 09:00:00');
        $hub = StoreHub::create(['name' => 'Branch', 'code' => 'branch-items', 'status' => 'active', 'is_head_office' => false]);
        $user = User::factory()->create(['role' => 'admin']);
        $originalProduct = Product::create(['store_hub_id' => $hub->id, 'item_id' => 'ITEM-ORIGINAL', 'name' => 'Original Item', 'stock' => 10, 'status' => 'active']);
        $replacementProduct = Product::create(['store_hub_id' => $hub->id, 'item_id' => 'ITEM-REPLACEMENT', 'name' => 'Replacement Item', 'stock' => 10, 'status' => 'active']);

        $returnedSale = SalesTransaction::create([
            'user_id' => $user->id,
            'store_hub_id' => $hub->id,
            'channel_type' => 'walk_in',
            'order_date' => '2026-09-29',
            'order_number' => 'RETURNED-ITEMS',
            'customer_name' => 'Returned Items Customer',
            'grand_total' => 300,
        ]);
        $returnedSale->items()->create([
            'product_id' => $originalProduct->id,
            'quantity' => 3,
            'returned_quantity' => 2,
            'return_status' => 'received',
            'unit_price' => 100,
            'line_total' => 300,
        ]);

        $replacementSale = SalesTransaction::create([
            'user_id' => $user->id,
            'store_hub_id' => $hub->id,
            'channel_type' => 'walk_in',
            'order_date' => '2026-09-29',
            'order_number' => 'REPLACED-ITEMS',
            'customer_name' => 'Replacement Items Customer',
            'grand_total' => 300,
        ]);
        $replacementItem = $replacementSale->items()->create([
            'product_id' => $originalProduct->id,
            'quantity' => 3,
            'returned_quantity' => 2,
            'return_status' => 'received',
            'unit_price' => 100,
            'line_total' => 300,
        ]);
        ProductReplacement::create([
            'transaction_id' => $replacementSale->id,
            'transaction_item_id' => $replacementItem->id,
            'original_product_id' => $originalProduct->id,
            'replacement_product_id' => $replacementProduct->id,
            'quantity' => 2,
            'replacement_quantity' => 3,
            'original_unit_price' => 100,
            'replacement_unit_price' => 100,
            'status' => 'approved',
            'created_by' => $user->id,
        ]);

        $pendingReplacementSale = SalesTransaction::create([
            'user_id' => $user->id,
            'store_hub_id' => $hub->id,
            'channel_type' => 'walk_in',
            'order_date' => '2026-09-29',
            'order_number' => 'PENDING-REPLACEMENT',
            'customer_name' => 'Pending Replacement Customer',
            'grand_total' => 200,
        ]);
        $pendingReplacementItem = $pendingReplacementSale->items()->create([
            'product_id' => $originalProduct->id,
            'quantity' => 2,
            'returned_quantity' => 1,
            'return_status' => 'received',
            'unit_price' => 100,
            'line_total' => 200,
        ]);
        ProductReplacement::create([
            'transaction_id' => $pendingReplacementSale->id,
            'transaction_item_id' => $pendingReplacementItem->id,
            'original_product_id' => $originalProduct->id,
            'replacement_product_id' => $replacementProduct->id,
            'quantity' => 1,
            'replacement_quantity' => 2,
            'original_unit_price' => 100,
            'replacement_unit_price' => 100,
            'status' => 'pending',
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('hub.report', [
            'hub' => $hub->id,
            'channel' => 'walk_in',
        ]))->assertOk();

        $this->assertSame(6, (int) $response->viewData('totalPurchasedItems'));
        $this->travelBack();
    }

    public function test_all_channels_report_has_preview_and_png_export_instead_of_print_pdf(): void
    {
        $hub = StoreHub::create(['name' => 'Head Office', 'code' => 'HO-ALL-REPORT', 'status' => 'active', 'is_head_office' => true]);
        $admin = User::factory()->create(['role' => 'admin']);
        SalesTransaction::create([
            'store_hub_id' => $hub->id,
            'channel_type' => 'online',
            'order_date' => '2026-09-28',
            'order_number' => 'ALL-CHANNEL-ONLINE-001',
            'customer_name' => 'All Channel Customer',
            'status' => 'completed',
            'grand_total' => 125,
        ]);

        $this->actingAs($admin)
            ->get(route('hub.report', [
                'hub' => $hub->id,
                'channel' => 'all',
                'date_from' => '2026-09-28',
                'date_to' => '2026-09-28',
            ]))
            ->assertOk()
            ->assertSee('Preview / Save PNG')
            ->assertSee('All Channels Sales Report Preview')
            ->assertSee('allChannelsReportPreviewCanvas', false)
            ->assertSee('saveAllChannelsPng', false)
            ->assertSee('Activity overview')
            ->assertSee('Sales by channel')
            ->assertSee('ONLINE')
            ->assertSee('₱125.00')
            ->assertDontSee('Print / Save PDF')
            ->assertDontSee('onclick="window.print()"', false);
    }

    public function test_new_customer_badge_is_available_in_every_sales_channel_report(): void
    {
        $hub = StoreHub::create(['name' => 'All Channel Hub', 'code' => 'ALL-CHANNELS', 'status' => 'active', 'is_head_office' => true]);
        $user = User::factory()->create(['role' => 'admin']);

        foreach (['online', 'wholesale', 'walk_in'] as $channel) {
            $sale = SalesTransaction::create([
                'store_hub_id' => $hub->id,
                'channel_type' => $channel,
                'order_date' => '2026-09-28',
                'date_of_arrangement' => null,
                'order_number' => strtoupper($channel).'-NEW',
                'customer_name' => ucfirst($channel).' First Customer',
                'contact_number' => '0917000000'.(string) (strlen($channel) % 10),
                'status' => 'completed',
                'grand_total' => 100,
            ]);

            $response = $this->actingAs($user)
                ->get(route('hub.report', [
                    'hub' => $hub->id,
                    'channel' => $channel,
                    'date_from' => '2026-09-28',
                    'date_to' => '2026-09-28',
                ]))
                ->assertOk()
                ->assertSee('>NEW</span>', false)
                ->assertSee('sales-metric-icon', false);

            if ($channel === 'online') {
                $response
                    ->assertSee('Activity overview')
                    ->assertSee('onlineReportPreviewCanvas', false)
                    ->assertSee('sales-summary-card is-info', false)
                    ->assertSee('sales-summary-icon', false);
            } elseif ($channel === 'wholesale') {
                $response
                    ->assertSee('Activity overview')
                    ->assertSee('wholesalePreviewCanvas', false)
                    ->assertSee('sales-summary-card')
                    ->assertSee('sales-summary-icon', false);
            } elseif ($channel === 'walk_in') {
                $response
                    ->assertSee('Activity overview')
                    ->assertSee('walkInReportPreviewCanvas', false)
                    ->assertSee('walk-in-report-summary-heading', false)
                    ->assertSee('fa-user-plus', false);
            }

            $this->assertSame([$sale->id], $response->viewData('newCustomerSaleIds'));
        }
    }
}
