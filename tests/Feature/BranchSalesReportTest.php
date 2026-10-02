<?php

namespace Tests\Feature;

use App\Models\{InventoryTransaction, Product, StoreHub, User, SalesTransaction};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchSalesReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_shopee_and_lazada_reports_show_invoice_and_returned_item_status(): void
    {
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
            ->assertSee('GCASH')
            ->assertSee('OTHER')
            ->assertSee('₱180.00');
        $this->assertSame(['OTHER'], $response->viewData('paymentBreakdown')->pluck('payment_method')->all());

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

        foreach (['shopee', 'lazada', 'tiktok', 'online', 'wholesale', 'walk_in'] as $channel) {
            $sale = SalesTransaction::create([
                'store_hub_id' => $hub->id,
                'channel_type' => $channel,
                'order_date' => '2026-09-28',
                'date_of_arrangement' => in_array($channel, ['shopee', 'lazada'], true) ? '2026-09-28' : null,
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

            if ($channel === 'tiktok') {
                $response
                    ->assertSee('Activity overview')
                    ->assertSee('Operations summary')
                    ->assertSee('tiktokReportPreviewCanvas', false)
                    ->assertSee('tiktok-overview-card is-danger', false)
                    ->assertSee('Approved item replacements');
            } elseif (in_array($channel, ['shopee', 'lazada'], true)) {
                $response
                    ->assertSee('Activity overview')
                    ->assertSee('marketplaceReportPreviewCanvas', false)
                    ->assertSee('sales-summary-card is-danger', false)
                    ->assertSee('sales-summary-icon', false);
            } elseif ($channel === 'online') {
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
