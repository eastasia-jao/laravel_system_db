<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductStockAllocation;
use App\Models\FullyBookedOrder;
use App\Models\InventoryTransaction;
use App\Models\NationalPullout;
use App\Models\ProductReplacement;
use App\Models\SalesTransaction;
use App\Models\StoreHub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use App\Support\XlsxTable;

class TransactionBulkTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_log_centers_channel_source_and_displays_channel_icon(): void
    {
        $hub = StoreHub::create([
            'name' => 'Channel Icon Hub',
            'code' => 'CHANNEL-ICON',
            'status' => 'active',
            'is_head_office' => true,
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $product = Product::create([
            'name' => 'TikTok Product',
            'item_id' => 'TIKTOK-ICON-1',
            'store_hub_id' => $hub->id,
            'stock' => 4,
            'status' => 'active',
        ]);
        $sale = SalesTransaction::create([
            'user_id' => $admin->id,
            'store_hub_id' => $hub->id,
            'channel_type' => 'tiktok',
            'order_date' => '2026-10-20',
            'order_number' => 'TIKTOK-ICON-ORDER',
            'customer_name' => 'TikTok Customer',
        ]);
        InventoryTransaction::create([
            'type' => 'sold',
            'store_hub_id' => $hub->id,
            'product_id' => $product->id,
            'sales_transaction_id' => $sale->id,
            'reference' => $sale->order_number,
            'channel' => 'tiktok',
            'quantity' => 1,
            'occurred_on' => '2026-10-20',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('inventory-transactions.index', ['hub_id' => $hub->id]))
            ->assertOk()
            ->assertDontSee('transaction-log-hub-column', false)
            ->assertSee('<th class="channel-source-column">Channel / Source</th>', false)
            ->assertSee('channel-source-icon tiktok', false)
            ->assertSee('fa-brands fa-tiktok', false)
            ->assertSee('TikTok');

        $this->get(route('inventory-transactions.index'))
            ->assertOk()
            ->assertSee('<th class="transaction-log-hub-column">Hub</th>', false)
            ->assertSee('<td class="transaction-log-hub-column">'.$hub->name.'</td>', false);
    }

    public function test_transaction_log_uses_aligned_columns_and_a_structured_transfer_route(): void
    {
        $source = StoreHub::create([
            'name' => 'Transfer Source Hub', 'code' => 'TRANSFER-SOURCE', 'status' => 'active', 'is_head_office' => true,
        ]);
        $target = StoreHub::create([
            'name' => 'Transfer Destination Hub', 'code' => 'TRANSFER-TARGET', 'status' => 'active', 'is_head_office' => false,
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $product = Product::create([
            'name' => 'Transfer Layout Product', 'item_id' => 'TRANSFER-LAYOUT-1',
            'store_hub_id' => $source->id, 'stock' => 5, 'status' => 'active',
        ]);

        InventoryTransaction::create([
            'type' => 'stock_transfer',
            'store_hub_id' => $source->id,
            'source_hub_id' => $source->id,
            'target_hub_id' => $target->id,
            'product_id' => $product->id,
            'reference' => 'TRF-LAYOUT-001',
            'source' => 'stock_transfer',
            'quantity' => 2,
            'occurred_on' => '2026-10-28',
            'created_by' => $admin->id,
            'status' => 'approved',
        ]);

        $this->actingAs($admin)
            ->get(route('inventory-transactions.index', ['hub_id' => $source->id]))
            ->assertOk()
            ->assertSee('<col class="log-column-details">', false)
            ->assertSee('<col class="log-column-transfer">', false)
            ->assertSee('<th>Transfer Route</th>', false)
            ->assertSee('.log-transfer-route { display: flex; width: fit-content; max-width: 100%;', false)
            ->assertSee('class="log-transfer-cell"', false)
            ->assertSee('class="log-transfer-route"', false)
            ->assertSee('aria-label="Transfer from TRANSFER SOURCE HUB (TRANSFER-SOURCE) to TRANSFER DESTINATION HUB (TRANSFER-TARGET)"', false)
            ->assertSee('<span class="log-transfer-route-hub" title="TRANSFER SOURCE HUB">TRANSFER-SOURCE</span>', false)
            ->assertSee('<span class="log-transfer-route-hub" title="TRANSFER DESTINATION HUB">TRANSFER-TARGET</span>', false)
            ->assertSee('fa-arrow-right-long', false);
    }

    public function test_transaction_log_replacement_uses_product_descriptions_with_name_fallback(): void
    {
        $hub = StoreHub::create([
            'name' => 'Replacement Description Hub', 'code' => 'REPL-DESC', 'status' => 'active', 'is_head_office' => true,
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $original = Product::create([
            'name' => 'Original Product Name', 'description' => 'Original product description',
            'item_id' => 'REPL-ORIGINAL', 'store_hub_id' => $hub->id, 'stock' => 5, 'status' => 'active',
        ]);
        $replacement = Product::create([
            'name' => 'Replacement Product Name', 'description' => '',
            'item_id' => 'REPL-NEW', 'store_hub_id' => $hub->id, 'stock' => 5, 'status' => 'active',
        ]);
        $sale = SalesTransaction::create([
            'user_id' => $admin->id, 'store_hub_id' => $hub->id, 'order_number' => 'REPL-DESC-ORDER',
            'customer_name' => 'Replacement Customer', 'order_date' => '2026-10-28',
            'channel_type' => 'online', 'status' => 'completed',
        ]);
        $item = $sale->items()->create([
            'product_id' => $original->id, 'quantity' => 1, 'unit_price' => 100, 'line_total' => 100,
        ]);
        $record = ProductReplacement::create([
            'transaction_id' => $sale->id, 'transaction_item_id' => $item->id,
            'original_product_id' => $original->id, 'replacement_product_id' => $replacement->id,
            'quantity' => 1, 'replacement_quantity' => 1, 'status' => 'approved',
        ]);
        InventoryTransaction::create([
            'type' => 'replacement_out', 'store_hub_id' => $hub->id, 'product_id' => $replacement->id,
            'product_replacement_id' => $record->id, 'reference' => $sale->order_number,
            'quantity' => 1, 'occurred_on' => '2026-10-28', 'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('inventory-transactions.index', ['hub_id' => $hub->id, 'type' => 'replacement']))
            ->assertOk()
            ->assertSee('<strong>Original product description → Replacement Product Name</strong>', false)
            ->assertDontSee('<strong>Original Product Name → Replacement Product Name</strong>', false);
    }

    public function test_transaction_log_uses_distinct_badge_colors_for_fully_booked_restock_and_national_pullout(): void
    {
        $hub = StoreHub::create([
            'name' => 'Transaction Color Hub', 'code' => 'COLOR-HUB', 'status' => 'active', 'is_head_office' => true,
        ]);
        $admin = User::factory()->create(['role' => 'admin', 'hub_id' => $hub->id]);
        $product = Product::create([
            'name' => 'Transaction Color Product', 'item_id' => 'COLOR-1',
            'store_hub_id' => $hub->id, 'stock' => 12, 'status' => 'active',
        ]);
        $fullyBookedOrder = FullyBookedOrder::create([
            'order_number' => 'FB-COLOR-1',
            'store_hub_id' => $hub->id,
            'sales_staff_id' => $admin->id,
            'submitted_by' => $admin->id,
            'attachment_path' => 'fully-booked-orders/color.pdf',
            'original_filename' => 'color.pdf',
            'mime_type' => 'application/pdf',
            'status' => 'reviewed',
        ]);
        InventoryTransaction::create([
            'type' => 'sponsor_workshop',
            'store_hub_id' => $hub->id,
            'product_id' => $product->id,
            'channel' => 'fully_booked',
            'reference' => $fullyBookedOrder->order_number,
            'quantity' => 1,
            'occurred_on' => '2026-10-28',
            'created_by' => $admin->id,
        ]);
        InventoryTransaction::create([
            'type' => 'restock',
            'store_hub_id' => $hub->id,
            'product_id' => $product->id,
            'reference' => 'RST-COLOR-1',
            'source' => 'warehouse',
            'quantity' => 2,
            'occurred_on' => '2026-10-27',
            'created_by' => $admin->id,
        ]);
        $pullout = NationalPullout::create([
            'po_number' => 'PO-COLOR-1',
            'occurred_on' => '2026-10-26',
            'store_hub_id' => $hub->id,
            'created_by' => $admin->id,
        ]);
        $pullout->items()->create([
            'item_id' => 'COLOR-NB-1',
            'product_name' => 'National pullout product',
            'quantity' => 3,
            'purpose' => 'National Bookstore Pullout',
            'physical_stock' => 6,
            'actual_pullout' => 2,
        ]);

        $this->actingAs($admin)
            ->get(route('inventory-transactions.index', ['hub_id' => $hub->id]))
            ->assertOk()
            ->assertSee('class="log-type-badge fully-booked"', false)
            ->assertSee('class="log-type-badge restock"', false)
            ->assertSee('class="log-type-badge national-bookstore"', false)
            ->assertSee('.log-type-badge.fully-booked { color: #3730a3; background: #e0e7ff; }', false)
            ->assertSee('.log-type-badge.restock { color: #0f766e; background: #ccfbf1; }', false)
            ->assertSee('.log-type-badge.national-bookstore { color: #9a3412; background: #ffedd5; }', false);
    }

    public function test_transaction_worksheets_download_as_excel_with_exact_identifiers(): void
    {
        foreach ([true, false] as $isHeadOffice) {
            $hub = StoreHub::create([
                'name' => $isHeadOffice ? 'Head Office' : 'Branch',
                'code' => $isHeadOffice ? 'HEAD-OFFICE' : 'BRANCH',
                'status' => 'active',
                'is_head_office' => $isHeadOffice,
            ]);
            Product::create([
                'name' => 'Long Barcode Product',
                'item_id' => '000012345678901234567890',
                'barcode' => '00007661234567891234567890',
                'store_hub_id' => $hub->id,
                'stock' => 8,
                'status' => 'active',
            ]);
            $type = $isHeadOffice ? 'stock_transfer' : 'branch_transfer';
            if (! $isHeadOffice) {
                foreach (['10', '2', '1'] as $itemId) {
                    Product::create([
                        'name' => 'Product '.$itemId,
                        'item_id' => $itemId,
                        'store_hub_id' => $hub->id,
                        'stock' => 1,
                        'status' => 'active',
                    ]);
                }
            }

            $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
                ->get(route('inventory-transactions.product-worksheet', [
                    'hub_id' => $hub->id,
                    'type' => $type,
                ]))
                ->assertOk()
                ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->assertHeader('Content-Disposition', 'attachment; filename="'.\Illuminate\Support\Str::upper(\Illuminate\Support\Str::slug($hub->code, '_')).'_Download_'.($isHeadOffice ? 'StockTrf_HO2B' : 'StockTrf_B2B').'_'.now()->format('Ymd_His').'.xlsx"');

            $file = UploadedFile::fake()->createWithContent('worksheet.xlsx', $response->getContent());
            $rows = XlsxTable::read($file->getRealPath());
            $this->assertSame(
                ['Name / Description', 'Barcode', 'Item ID', 'Physical Stocks Qty', 'Physical Actual Pullout'],
                $rows[0]
            );
            $rows = array_slice($rows, 1);
            if ($isHeadOffice) {
                $this->assertSame('000012345678901234567890', $rows[0][2]);
                $this->assertSame('00007661234567891234567890', $rows[0][1]);
                $this->assertSame('8', $rows[0][3]);
            } else {
                $this->assertSame(
                    ['1', '2', '10', '000012345678901234567890'],
                    array_column($rows, 2)
                );
            }
        }
    }

    public function test_branch_return_items_page_records_and_lists_branch_returns(): void
    {
        $branch = StoreHub::create(['name' => 'Return Branch', 'code' => 'RETURN-BR', 'status' => 'active', 'is_head_office' => false]);
        $admin = User::factory()->create(['role' => 'sales_associate', 'hub_id' => $branch->id]);
        $product = Product::create([
            'name' => 'Returned Brush', 'item_id' => 'RETURN-BRUSH',
            'store_hub_id' => $branch->id, 'stock' => 5, 'status' => 'active',
        ]);
        $sale = SalesTransaction::create([
            'store_hub_id' => $branch->id, 'channel_type' => 'walk_in', 'order_date' => '2026-09-29',
            'order_number' => 'BRANCH-RETURN-1', 'customer_name' => 'Branch Customer',
        ]);
        $item = $sale->items()->create([
            'product_id' => $product->id, 'quantity' => 2, 'unit_price' => 100, 'line_total' => 200,
        ]);
        InventoryTransaction::create([
            'type' => 'return', 'store_hub_id' => $branch->id, 'product_id' => $product->id,
            'sales_transaction_id' => $sale->id, 'transaction_item_id' => $item->id,
            'reference' => $sale->order_number, 'channel' => 'walk_in', 'condition' => 'good',
            'quantity' => 1, 'occurred_on' => '2026-09-29', 'created_by' => $admin->id,
        ]);

        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('inventory-transactions.index', [
            'hub_id' => $branch->id,
            'type' => 'return',
        ]))->assertOk()
            ->assertSee('Stock movement')
            ->assertSee('Event operations')
            ->assertSee('Inventory updates')
            ->assertSee('HO ↔ Branch Transfer')
            ->assertSee('Branch ↔ Branch Transfer')
            ->assertSee('Return record')
            ->assertSee('Recorded by')
            ->assertSee('Store Hub')
            ->assertSee('Channel')
            ->assertDontSee('<strong>Transfer</strong>', false);

        $this->actingAs($admin)->get(route('inventory-transactions.return.create', ['hub_id' => $branch->id]))
            ->assertOk()
            ->assertSee('Return Items')
            ->assertSee('class="page-hero mb-3"', false)
            ->assertDontSee('Returned Items History')
            ->assertSee('value="walk_in" selected', false)
            ->assertDontSee('value="shopee"', false)
            ->assertSee('class="return-actions"', false)
            ->assertSee('id="returnAttachmentPreview"', false)
            ->assertSee('id="returnItemSearch"', false)
            ->assertSee('Search product name or Item ID')
            ->assertSee('showFullyBookedAttachment', false)
            ->assertSee('<i class="fa-solid fa-check me-1" aria-hidden="true"></i>Save Return Items', false);
        $returnFormHtml = $this->get(route('inventory-transactions.return.create', ['hub_id' => $branch->id]))
            ->assertOk()->getContent();
        $this->assertLessThan(
            strpos($returnFormHtml, 'class="return-actions"'),
            strpos($returnFormHtml, 'id="returnNotes"'),
            'Return action buttons should appear immediately after the Notes panel in the right-side layout.'
        );

        $this->post(route('inventory-transactions.return.store'), [
            'type' => 'return', 'store_hub_id' => $branch->id, 'occurred_on' => '2026-09-29',
            'channel' => 'walk_in', 'reference' => $sale->order_number,
            'sales_transaction_id' => $sale->id,
            'items' => [[
                'product_id' => $product->id, 'transaction_item_id' => $item->id,
                'good_quantity' => 0, 'damaged_quantity' => 1,
            ]],
        ])->assertSessionHasNoErrors()
            ->assertRedirect(route('inventory-transactions.return.create', ['hub_id' => $branch->id]))
            ->assertSessionHas('success', 'Return items recorded successfully.');

        $this->get(route('hub.dashboard', $branch->id))
            ->assertOk()
            ->assertSee(route('hub.branch-returns', ['hub' => $branch->id]), false)
            ->assertSee(route('inventory-transactions.return.create', ['hub_id' => $branch->id]), false);

        $this->get(route('hub.branch-returns', ['hub' => $branch->id]))
            ->assertOk()
            ->assertSee('Walk-In Returns')
            ->assertSee('BRANCH-RETURN-1')
            ->assertSee('Returned Brush')
            ->assertSee('Bad: 1')
            ->assertDontSee('No Walk-In orders found');
    }

    public function test_fully_booked_returns_load_order_numbers_without_a_customer(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $hub = StoreHub::create([
            'name' => 'Fully Booked Returns', 'code' => 'FB-RET', 'status' => 'active', 'is_head_office' => true,
        ]);
        $sale = SalesTransaction::create([
            'store_hub_id' => $hub->id,
            'channel_type' => 'fully_booked',
            'status' => 'confirmed',
            'order_date' => '2026-09-30',
            'order_number' => 'FB-ORDER-1001',
            'customer_name' => 'Fully Booked Account',
        ]);

        $this->getJson(route('inventory-transactions.return.sales', [
            'hub_id' => $hub->id,
            'channel' => 'fully_booked',
            'date' => '2026-09-30',
        ]))
            ->assertOk()
            ->assertJsonMissingPath('customers')
            ->assertJsonPath('orders.0.id', $sale->id)
            ->assertJsonPath('orders.0.order_number', 'FB-ORDER-1001');

        $this->get(route('inventory-transactions.return.create', ['hub_id' => $hub->id]))
            ->assertOk()
            ->assertSee("channel.value === 'fully_booked'", false)
            ->assertSee('Select order number');
    }

    public function test_fully_booked_returns_do_not_create_refunds(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $hub = StoreHub::create(['name' => 'Fully Booked Refunds', 'code' => 'FB-REFUND', 'status' => 'active', 'is_head_office' => true]);
        $product = Product::create(['name' => 'Fully Booked Return Product', 'item_id' => 'FB-RETURN', 'store_hub_id' => $hub->id, 'stock' => 0, 'status' => 'active']);
        $sale = SalesTransaction::create([
            'store_hub_id' => $hub->id, 'channel_type' => 'fully_booked', 'status' => 'confirmed',
            'order_date' => '2026-09-30', 'order_number' => 'FB-ORDER-REFUND', 'customer_name' => 'Fully Booked Account',
        ]);
        $item = $sale->items()->create(['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 100, 'line_total' => 200]);

        $this->post(route('inventory-transactions.return.store'), [
            'type' => 'return', 'store_hub_id' => $hub->id, 'occurred_on' => '2026-09-30',
            'channel' => 'fully_booked', 'reference' => $sale->order_number, 'sales_transaction_id' => $sale->id,
            'items' => [[
                'product_id' => $product->id, 'transaction_item_id' => $item->id,
                'good_quantity' => 0, 'damaged_quantity' => 1, 'refund_amount' => 999,
            ]],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('inventory_transactions', [
            'type' => 'return', 'transaction_item_id' => $item->id, 'condition' => 'damaged', 'quantity' => 1, 'refund_amount' => 0,
        ]);
        $this->assertSame(0, (int) $product->fresh()->stock);
        $this->assertSame(1, (int) $product->fresh()->damaged_stock);
    }

    public function test_good_and_damaged_returns_restore_the_correct_channel_and_physical_balances(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $hub = StoreHub::create(['name' => 'Returns Hub', 'code' => 'RETURNS', 'status' => 'active', 'is_head_office' => true]);

        foreach (['online', 'wholesale', 'shopee', 'lazada', 'tiktok'] as $channel) {
            $product = Product::create([
                'name' => ucfirst($channel).' Return Product', 'item_id' => strtoupper($channel).'-RETURN',
                'store_hub_id' => $hub->id, 'stock' => 8, 'status' => 'active',
            ]);
            ProductStockAllocation::create(['product_id' => $product->id, $channel => 8]);
            $sale = SalesTransaction::create([
                'store_hub_id' => $hub->id, 'channel_type' => $channel, 'status' => 'confirmed',
                'order_date' => '2026-09-23', 'order_number' => strtoupper($channel).'-RETURN-ORDER',
                'customer_name' => 'Return Customer',
            ]);
            $item = $sale->items()->create([
                'product_id' => $product->id, 'quantity' => 2, 'unit_price' => 100, 'line_total' => 200,
            ]);

            $this->post(route('inventory-transactions.store'), [
                'type' => 'return', 'store_hub_id' => $hub->id, 'occurred_on' => '2026-09-23',
                'channel' => $channel, 'reference' => $sale->order_number,
                'sales_transaction_id' => $sale->id,
                'items' => [[
                    'product_id' => $product->id, 'transaction_item_id' => $item->id,
                    'good_quantity' => 1, 'damaged_quantity' => 1,
                ]],
            ])->assertSessionHasNoErrors()
                ->assertRedirect(route('inventory-transactions.index', ['hub_id' => $hub->id, 'type' => 'return']))
                ->assertSessionHas('success', 'Return items recorded successfully.');

            $this->assertSame(9, $product->fresh()->stock, "{$channel} should restore only the good unit to usable stock.");
            $this->assertSame(1, $product->fresh()->damaged_stock, "{$channel} should track the damaged unit separately.");
            $this->assertSame(9, (int) $product->stockAllocation->fresh()->{$channel}, "{$channel} should restore only the good unit to its allocation.");
        }
    }

    public function test_shopee_and_lazada_returns_do_not_create_automatic_refunds(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        $hub = StoreHub::create(['name' => 'Marketplace Returns', 'code' => 'MKT-RET', 'status' => 'active', 'is_head_office' => true]);

        foreach (['shopee', 'lazada'] as $channel) {
            $submitter = User::factory()->create([
                'role' => 'sales_marketing_staff',
                'hub_id' => $hub->id,
                'sales_channels' => [$channel],
            ]);
            $product = Product::create([
                'name' => ucfirst($channel).' Refund Product', 'item_id' => strtoupper($channel).'-REFUND',
                'store_hub_id' => $hub->id, 'stock' => 0, 'status' => 'active',
            ]);
            $sale = SalesTransaction::create([
                'user_id' => $submitter->id,
                'store_hub_id' => $hub->id, 'channel_type' => $channel, 'status' => 'confirmed',
                'order_date' => '2026-09-23', 'order_number' => strtoupper($channel).'-REFUND-ORDER',
                'customer_name' => 'Refund Customer',
            ]);
            $item = $sale->items()->create([
                'product_id' => $product->id, 'quantity' => 2, 'unit_price' => 100,
                'discount_percentage' => 10, 'line_total' => 180,
            ]);

            $this->post(route('inventory-transactions.store'), [
                'type' => 'return', 'store_hub_id' => $hub->id, 'occurred_on' => '2026-09-23',
                'channel' => $channel, 'reference' => $sale->order_number,
                'sales_transaction_id' => $sale->id,
                'items' => [[
                    'product_id' => $product->id, 'transaction_item_id' => $item->id,
                    'good_quantity' => 0, 'damaged_quantity' => 1,
                ]],
            ])->assertSessionHasNoErrors();

            $this->assertDatabaseHas('inventory_transactions', [
                'sales_transaction_id' => $sale->id,
                'transaction_item_id' => $item->id,
                'channel' => $channel,
                'condition' => 'damaged',
                'quantity' => 1,
                'refund_amount' => 0,
            ]);

            $returnsUrl = route('hub.marketplace-returns', ['hub' => $hub->id, 'channel' => $channel]);
            $returnNotification = $submitter->notifications()->where('data->event', 'return_recorded')->sole();
            $this->assertSame($returnsUrl, $returnNotification->data['url']);

            $legacyNotification = $submitter->notifications()->create([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'type' => \App\Notifications\InventoryWorkflowNotification::class,
                'data' => [
                    'event' => 'return_recorded',
                    'hub_id' => $hub->id,
                    'url' => route('hub.report', ['hub' => $hub->id, 'channel' => $channel]),
                ],
            ]);
            $this->actingAs($submitter)->get(route('notifications.read', $legacyNotification->id))
                ->assertRedirect($returnsUrl);
            $this->actingAs($admin);
        }
    }

    public function test_return_order_details_hide_refund_total_for_marketplace_tiktok_and_fully_booked_channels(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        $hub = StoreHub::create(['name' => 'Returns Log', 'code' => 'RETURNS-LOG', 'status' => 'active', 'is_head_office' => true]);
        $product = Product::create([
            'name' => 'Return log item',
            'item_id' => 'RETURN-LOG-ITEM',
            'store_hub_id' => $hub->id,
            'stock' => 0,
            'status' => 'active',
        ]);

        foreach (['shopee', 'lazada', 'tiktok', 'fully_booked'] as $channel) {
            $sale = SalesTransaction::create([
                'store_hub_id' => $hub->id,
                'channel_type' => $channel,
                'status' => 'confirmed',
                'order_date' => '2026-10-06',
                'order_number' => strtoupper($channel).'-RETURN-LOG',
                'customer_name' => 'Return log customer',
            ]);
            $item = $sale->items()->create([
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 100,
                'line_total' => 100,
            ]);
            InventoryTransaction::create([
                'type' => 'return',
                'reference' => $sale->order_number,
                'store_hub_id' => $hub->id,
                'product_id' => $product->id,
                'sales_transaction_id' => $sale->id,
                'transaction_item_id' => $item->id,
                'channel' => $channel,
                'quantity' => 1,
                'condition' => 'good',
                'refund_amount' => 50,
                'occurred_on' => '2026-10-06',
                'created_by' => $admin->id,
            ]);
        }

        $response = $this->get(route('inventory-transactions.index', [
            'hub_id' => $hub->id,
            'type' => 'return',
        ]))->assertOk();

        $response->assertSee('Return Order Details')
            ->assertDontSee('Refund total');
    }

    public function test_return_order_details_consolidate_conditions_and_scroll_when_many_items(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $hub = StoreHub::create(['name' => 'Return Details Hub', 'code' => 'RETURN-DETAILS', 'status' => 'active', 'is_head_office' => true]);
        $product = Product::create([
            'name' => 'Scroll Return Product',
            'item_id' => 'SCROLL-RETURN-1',
            'store_hub_id' => $hub->id,
            'stock' => 0,
            'status' => 'active',
        ]);
        $batchId = (string) \Illuminate\Support\Str::uuid();
        foreach (['good' => 3, 'damaged' => 2] as $condition => $quantity) {
            InventoryTransaction::create([
                'type' => 'return',
                'reference' => 'EVENT-RETURN-SCROLL',
                'store_hub_id' => $hub->id,
                'product_id' => $product->id,
                'channel' => 'event',
                'condition' => $condition,
                'quantity' => $quantity,
                'occurred_on' => '2026-10-16',
                'created_by' => $admin->id,
                'transfer_batch_id' => $batchId,
            ]);
        }

        $response = $this->actingAs($admin)->get(route('inventory-transactions.index', [
            'hub_id' => $hub->id,
            'type' => 'return',
        ]))->assertOk()
            ->assertSee('return-detail-items')
            ->assertSee('max-height: min(46vh, 420px)')
            ->assertSee('Good: 3')
            ->assertSee('Damaged: 2')
            ->assertSee('Total Qty');

        $this->assertSame(1, substr_count($response->getContent(), '<strong>Scroll Return Product</strong>'));
    }

    public function test_tiktok_returns_do_not_create_automatic_refunds(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        $hub = StoreHub::create(['name' => 'TikTok Returns', 'code' => 'TT-RET', 'status' => 'active', 'is_head_office' => true]);
        $product = Product::create([
            'name' => 'TikTok Return Product',
            'item_id' => 'TT-RETURN-1',
            'store_hub_id' => $hub->id,
            'stock' => 0,
            'status' => 'active',
        ]);
        $submitter = User::factory()->create([
            'role' => 'sales_marketing_staff',
            'hub_id' => $hub->id,
            'sales_channels' => ['tiktok'],
        ]);
        $sale = SalesTransaction::create([
            'user_id' => $submitter->id,
            'store_hub_id' => $hub->id,
            'channel_type' => 'tiktok',
            'status' => 'confirmed',
            'order_date' => '2026-09-23',
            'order_number' => 'TT-RETURN-ORDER',
            'customer_name' => 'TikTok Customer',
        ]);
        $item = $sale->items()->create([
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 100,
            'discount_percentage' => 10,
            'line_total' => 180,
        ]);

        $this->post(route('inventory-transactions.store'), [
            'type' => 'return',
            'store_hub_id' => $hub->id,
            'occurred_on' => '2026-09-23',
            'channel' => 'tiktok',
            'reference' => $sale->order_number,
            'sales_transaction_id' => $sale->id,
            'items' => [[
                'product_id' => $product->id,
                'transaction_item_id' => $item->id,
                'good_quantity' => 0,
                'damaged_quantity' => 1,
                'refund_amount' => 90,
            ]],
        ])->assertSessionHasNoErrors()
            ->assertRedirect(route('inventory-transactions.index', ['hub_id' => $hub->id, 'type' => 'return']))
            ->assertSessionHas('success', 'Return items recorded successfully.');

        $this->assertDatabaseHas('inventory_transactions', [
            'sales_transaction_id' => $sale->id,
            'transaction_item_id' => $item->id,
            'channel' => 'tiktok',
            'condition' => 'damaged',
            'quantity' => 1,
            'refund_amount' => 0,
        ]);
        $this->assertSame(1, (int) $product->fresh()->stock);
        $returnNotification = $submitter->notifications()->firstOrFail();
        $this->assertSame(route('hub.tiktok-returns', ['hub' => $hub->id]), $returnNotification->data['url']);
        $this->assertSame('tiktok', $returnNotification->data['channel']);
        $this->assertStringNotContainsString('sales report', strtolower($returnNotification->data['message']));
        $this->get(route('inventory-transactions.index', ['hub_id' => $hub->id, 'type' => 'return']))
            ->assertOk()
            ->assertSee('Return items recorded successfully.');

        $this->post(route('inventory-transactions.store'), [
            'type' => 'return',
            'store_hub_id' => $hub->id,
            'occurred_on' => '2026-09-23',
            'channel' => 'tiktok',
            'reference' => $sale->order_number,
            'sales_transaction_id' => $sale->id,
            'items' => [[
                'product_id' => $product->id,
                'transaction_item_id' => $item->id,
                'good_quantity' => 0,
                'damaged_quantity' => 0,
                'refund_amount' => 50,
            ]],
        ])->assertSessionHasErrors('items');
    }

    public function test_automatic_refund_is_limited_to_items_with_returned_quantities(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $hub = StoreHub::create(['name' => 'Walk-In Refunds', 'code' => 'WALKIN-REFUNDS', 'status' => 'active']);
        $returnedProduct = Product::create([
            'name' => 'Returned product', 'item_id' => 'RETURNED-1',
            'store_hub_id' => $hub->id, 'stock' => 0, 'status' => 'active',
        ]);
        $untouchedProduct = Product::create([
            'name' => 'Untouched product', 'item_id' => 'UNTOUCHED-1',
            'store_hub_id' => $hub->id, 'stock' => 0, 'status' => 'active',
        ]);
        $sale = SalesTransaction::create([
            'store_hub_id' => $hub->id, 'channel_type' => 'walk_in', 'status' => 'confirmed',
            'order_date' => '2026-09-23', 'order_number' => 'WALK-IN001',
            'customer_name' => 'Refund Customer',
        ]);
        $returnedItem = $sale->items()->create([
            'product_id' => $returnedProduct->id, 'quantity' => 2, 'unit_price' => 100, 'line_total' => 200,
        ]);
        $untouchedItem = $sale->items()->create([
            'product_id' => $untouchedProduct->id, 'quantity' => 2, 'unit_price' => 50, 'line_total' => 100,
        ]);

        $this->post(route('inventory-transactions.store'), [
            'type' => 'return', 'store_hub_id' => $hub->id, 'occurred_on' => '2026-09-23',
            'channel' => 'walk_in', 'reference' => $sale->order_number,
            'sales_transaction_id' => $sale->id,
            'items' => [
                [
                    'product_id' => $returnedProduct->id, 'transaction_item_id' => $returnedItem->id,
                    'good_quantity' => 1, 'damaged_quantity' => 0, 'refund_amount' => 0,
                ],
                [
                    'product_id' => $untouchedProduct->id, 'transaction_item_id' => $untouchedItem->id,
                    'good_quantity' => 0, 'damaged_quantity' => 0, 'refund_amount' => 0,
                ],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('inventory_transactions', [
            'transaction_item_id' => $returnedItem->id,
            'quantity' => 1,
            'refund_amount' => 100,
        ]);
        $this->assertDatabaseMissing('inventory_transactions', [
            'transaction_item_id' => $untouchedItem->id,
        ]);
        $this->assertSame(1, (int) $returnedProduct->fresh()->stock);
        $this->assertSame(0, (int) $untouchedProduct->fresh()->stock);
    }

    public function test_large_json_submission_and_invalid_catalog_items(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $hub = StoreHub::create(['name' => 'HO', 'code' => 'HO', 'status' => 'active', 'is_head_office' => true]);
        $product = Product::create(['name' => 'Bulk Product', 'item_id' => '001', 'store_hub_id' => $hub->id, 'stock' => 0, 'status' => 'active']);
        $payload = ['type' => 'restock', 'store_hub_id' => $hub->id, 'occurred_on' => '2026-09-10', 'restock_source' => 'warehouse_request'];
        $items = array_fill(0, 1500, ['product_id' => $product->id, 'quantity' => 1]);
        $this->postJson(route('inventory-transactions.store'), $payload + ['items_json' => json_encode($items)])
            ->assertRedirect();
        $this->assertEquals(1500, $product->fresh()->stock);
        $this->assertDatabaseCount('inventory_transactions', 1);
        $items[] = ['product_id' => 999999, 'quantity' => 1];
        $this->postJson(route('inventory-transactions.store'), $payload + ['items_json' => json_encode($items)])
            ->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->assertEquals(1500, $product->fresh()->stock);
        $product->update(['status' => 'inactive']);
        $this->postJson(route('inventory-transactions.store'), $payload + ['items_json' => json_encode([['product_id' => $product->id, 'quantity' => 1]])])
            ->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->postJson(route('inventory-transactions.store'), $payload + ['items_json' => 'broken'])
            ->assertUnprocessable()->assertJsonValidationErrors('items_json');
    }

    public function test_bulk_controls_render_on_supported_transaction_forms(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach (['transfer', 'branch-transfer', 'sponsor', 'restock'] as $name) {
            $this->get(route("inventory-transactions.$name.create"))->assertOk()
                ->assertSee('Import Items (CSV)')->assertSee('Export Selected Items');
        }

        $this->get(route('inventory-transactions.return.create'))->assertOk();
    }

    public function test_sponsor_workshop_form_and_submission_use_an_automated_reference_number(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $hub = StoreHub::create([
            'name' => 'Head Office', 'code' => 'HEAD-OFFICE', 'status' => 'active', 'is_head_office' => true,
        ]);
        $inactiveHub = StoreHub::create([
            'name' => 'Deactivated Hub', 'code' => 'OLD-HUB', 'status' => 'inactive', 'is_head_office' => false,
        ]);
        $this->assertSame('inactive', $inactiveHub->fresh()->status);
        $this->assertSame(['active'], StoreHub::where('status', 'active')->pluck('status')->all());
        $product = Product::create([
            'name' => 'Workshop Product', 'item_id' => 'WORKSHOP-1',
            'store_hub_id' => $hub->id, 'stock' => 10, 'status' => 'active',
        ]);
        $secondProduct = Product::create([
            'name' => 'Second Workshop Product', 'item_id' => 'WORKSHOP-2',
            'store_hub_id' => $hub->id, 'stock' => 6, 'status' => 'active',
        ]);

        $formResponse = $this->get(route('inventory-transactions.sponsor.create', ['hub_id' => $inactiveHub->id]))
            ->assertOk()
            ->assertSee('<title>Event</title>', false)
            ->assertSee('<h1 class="topbar-title">Event</h1>', false)
            ->assertDontSee('Event / Fully Booked')
            ->assertSee('class="page-hero mb-3"', false)
            ->assertSee('href="'.route('event-inventory.index', ['hub_id' => $hub->id]).'" class="btn btn-outline-secondary">Cancel</a>', false)
            ->assertSee('Reference Document No.')
            ->assertSee('id="sponsorItemsHeader"', false)
            ->assertSee('aria-label="Product"', false)
            ->assertSee('aria-label="Actual Released Qty"', false)
            ->assertSee('aria-label="Requested Qty (record only)"', false)
            ->assertSee('readonly', false)
            ->assertSee('SW-HEAD-OFFICE-'.now()->format('Ymd').'-', false);
        $this->assertSame($hub->id, $formResponse->viewData('hubId'));
        $formResponse
            ->assertDontSee('<option value="'.$inactiveHub->id.'"', false);

        $this->get(route('inventory-transactions.sponsor.create', [
            'hub_id' => $hub->id,
            'activity_type' => 'event',
            'return_to' => 'transaction_logs',
        ]))->assertOk()
            ->assertSee('href="'.route('inventory-transactions.index', ['hub_id' => $hub->id]).'" class="btn btn-outline-secondary">Cancel</a>', false)
            ->assertDontSee('href="'.route('event-inventory.index', ['hub_id' => $hub->id]).'" class="btn btn-outline-secondary">Cancel</a>', false);

        $response = $this->post(route('inventory-transactions.store'), [
            'type' => 'sponsor_workshop',
            'store_hub_id' => $hub->id,
            'occurred_on' => '2026-09-30',
            'source' => 'Community Workshop',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
                ['product_id' => $secondProduct->id, 'quantity' => 3],
            ],
        ])->assertSessionHasNoErrors();

        $transaction = InventoryTransaction::where('type', 'sponsor_workshop')->firstOrFail();
        $this->assertMatchesRegularExpression(
            '/^SW-HEAD-OFFICE-\d{8}-\d{6}-[A-F0-9]{6}$/',
            $transaction->reference
        );
        $this->assertSame(8, $product->fresh()->stock);
        $this->assertSame(3, $secondProduct->fresh()->stock);
        $logsUrl = route('inventory-transactions.index', [
            'hub_id' => $hub->id,
            'sponsor_workshop_saved' => 1,
            'reference' => $transaction->reference,
        ]);
        $response->assertRedirect($logsUrl);
        $this->get($logsUrl)
            ->assertOk()
            ->assertSee('id="sponsor-workshop-success-popup"', false)
            ->assertSee('.branch-transfer-success-popup', false)
            ->assertSee('Items recorded')
            ->assertSee($transaction->reference);

        $this->assertSame(2, InventoryTransaction::where('type', 'sponsor_workshop')
            ->where('reference', $transaction->reference)->count());
        $logContent = $this->get(route('inventory-transactions.index', [
            'hub_id' => $hub->id,
            'type' => 'sponsor_workshop',
        ]))->assertOk()->getContent();
        $this->assertSame(1, substr_count($logContent, '<strong>'.$transaction->reference.'</strong>'));
        $this->assertStringContainsString('Workshop Product', $logContent);
        $this->assertStringContainsString('Second Workshop Product', $logContent);
    }

    public function test_sponsor_workshop_pullout_can_consume_damaged_stock_separately_from_good_stock(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $hub = StoreHub::create([
            'name' => 'Event Pullout Hub',
            'code' => 'EVENT-PULLOUT',
            'status' => 'active',
            'is_head_office' => true,
        ]);
        $product = Product::create([
            'name' => 'Event Pullout Product',
            'item_id' => 'EVENT-PULLOUT-1',
            'barcode' => '987654321',
            'store_hub_id' => $hub->id,
            'stock' => 5,
            'damaged_stock' => 2,
            'status' => 'active',
        ]);

        $eventForm = $this->actingAs($admin)
            ->get(route('inventory-transactions.sponsor.create', ['hub_id' => $hub->id]))
            ->assertOk()
            ->assertSee('name="items[0][stock_condition]"', false)
            ->assertSee('<option value="damaged">Damaged</option>', false);
        $eventForm->assertViewHas('sponsorProductOptions', fn ($options) =>
            $options->contains(fn ($option) => $option['good_stock_available'] === 5
                && $option['damaged_stock_available'] === 2
                && $option['label'] === 'EVENT-PULLOUT-1 — Event Pullout Product · 987654321 (stock: 5)')
        );
        $eventForm->assertSee('setupTransactionSuggestions(', false)
            ->assertSee('stockForCondition(product, condition) > 0', false)
            ->assertSee('labelForCondition(product, condition)', false);
        $formHtml = $eventForm->getContent();
        $this->assertLessThan(
            strpos($formHtml, 'name="items[0][quantity]"'),
            strpos($formHtml, 'name="items[0][pullout_qty]"'),
            'The record-only Requested Qty field should appear before Actual Released Qty.'
        );

        $this->post(route('inventory-transactions.store'), [
            'type' => 'sponsor_workshop',
            'store_hub_id' => $hub->id,
            'occurred_on' => '2026-10-14',
            'source' => 'Community Event',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'pullout_qty' => 3, 'stock_condition' => 'good'],
                ['product_id' => $product->id, 'quantity' => 1, 'pullout_qty' => 2, 'stock_condition' => 'damaged'],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(3, (int) $product->fresh()->stock);
        $this->assertSame(1, (int) $product->fresh()->damaged_stock);
        $this->assertDatabaseHas('inventory_transactions', [
            'type' => 'sponsor_workshop',
            'product_id' => $product->id,
            'condition' => 'good',
            'quantity' => 2,
            'pullout_qty' => 3,
        ]);
        $this->assertDatabaseHas('inventory_transactions', [
            'type' => 'sponsor_workshop',
            'product_id' => $product->id,
            'condition' => 'damaged',
            'quantity' => 1,
            'pullout_qty' => 2,
        ]);
        $eventReference = InventoryTransaction::where('type', 'sponsor_workshop')->value('reference');
        $eventLogs = $this->get(route('inventory-transactions.index', [
            'hub_id' => $hub->id,
            'type' => 'sponsor_workshop',
        ]))->assertOk()
            ->assertSee('<strong>'.$eventReference.'</strong>', false)
            ->assertDontSee('total pulled out')
            ->assertSee('event-pullout-detail-items', false)
            ->assertSee('Good')
            ->assertSee('Requested 3')
            ->assertSee('Actual 2')
            ->assertSee('Damaged')
            ->assertSee('Requested 2')
            ->assertSee('Actual 1');
        $this->assertSame(
            1,
            substr_count($eventLogs->getContent(), '<div class="transfer-detail-item-name">Event Pullout Product</div>')
        );

        $this->post(route('inventory-transactions.store'), [
            'type' => 'sponsor_workshop',
            'store_hub_id' => $hub->id,
            'occurred_on' => '2026-10-14',
            'source' => 'Community Event',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 2,
                'stock_condition' => 'damaged',
            ]],
        ])->assertSessionHasErrors('items');

        $this->assertSame(3, (int) $product->fresh()->stock);
        $this->assertSame(1, (int) $product->fresh()->damaged_stock);
    }

    public function test_restock_form_uses_compact_items_and_an_automated_reference_number(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $hub = StoreHub::create([
            'name' => 'Head Office', 'code' => 'HEAD-OFFICE', 'status' => 'active', 'is_head_office' => true,
        ]);
        $product = Product::create([
            'name' => 'Restock Product', 'item_id' => 'RESTOCK-1',
            'store_hub_id' => $hub->id, 'stock' => 5, 'status' => 'active',
        ]);
        $secondProduct = Product::create([
            'name' => 'Second Restock Product', 'item_id' => 'RESTOCK-2',
            'store_hub_id' => $hub->id, 'stock' => 3, 'status' => 'active',
        ]);
        $sourceBranch = StoreHub::create([
            'name' => 'Source Branch', 'code' => 'BRANCH', 'status' => 'active', 'is_head_office' => false,
        ]);
        $branchProduct = Product::create([
            'name' => 'Restock Product', 'item_id' => 'RESTOCK-1',
            'store_hub_id' => $sourceBranch->id, 'stock' => 5, 'status' => 'active',
        ]);

        $this->get(route('inventory-transactions.restock.create', ['hub_id' => $hub->id]))
            ->assertOk()
            ->assertSee('Restock / Added from Request (Warehouse HO)')
            ->assertSee('class="restock-page-heading mb-4"', false)
            ->assertSee('Reference Document No.')
            ->assertSee('id="restockItemsHeader"', false)
            ->assertSee('aria-label="Product"', false)
            ->assertSee('aria-label="Quantity"', false)
            ->assertSee('readonly', false)
            ->assertSee('RST-HEAD-OFFICE-'.now()->format('Ymd').'-', false)
            ->assertDontSee('Added From')
            ->assertDontSee('Added from Stock Transfer (Store Branch &gt; Head Office)');

        $this->post(route('inventory-transactions.store'), [
            'type' => 'restock',
            'store_hub_id' => $hub->id,
            'occurred_on' => '2026-09-30',
            'restock_source' => 'stock_transfer',
            'source_hub_id' => $sourceBranch->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertSessionHasErrors('restock_source');
        $this->assertSame(5, $product->fresh()->stock);
        $this->assertSame(5, $branchProduct->fresh()->stock);

        $response = $this->post(route('inventory-transactions.store'), [
            'type' => 'restock',
            'store_hub_id' => $hub->id,
            'occurred_on' => '2026-09-30',
            'restock_source' => 'warehouse_request',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
                ['product_id' => $secondProduct->id, 'quantity' => 4],
            ],
        ])->assertSessionHasNoErrors();

        $transaction = InventoryTransaction::where('type', 'restock')->firstOrFail();
        $this->assertMatchesRegularExpression(
            '/^RST-HEAD-OFFICE-\d{8}-\d{6}-[A-F0-9]{6}$/',
            $transaction->reference
        );
        $this->assertSame(2, InventoryTransaction::where('type', 'restock')
            ->where('reference', $transaction->reference)->count());
        $this->assertSame(7, $product->fresh()->stock);
        $this->assertSame(7, $secondProduct->fresh()->stock);

        $logsUrl = route('inventory-transactions.index', [
            'hub_id' => $hub->id,
            'restock_saved' => 1,
            'reference' => $transaction->reference,
        ]);
        $response->assertRedirect($logsUrl);
        $this->get($logsUrl)
            ->assertOk()
            ->assertSee('id="restock-success-popup"', false)
            ->assertSee($transaction->reference);

        $logContent = $this->get(route('inventory-transactions.index', [
            'hub_id' => $hub->id,
            'type' => 'restock',
        ]))->assertOk()->getContent();
        $this->assertSame(1, substr_count($logContent, '<strong>'.$transaction->reference.'</strong>'));
        $this->assertStringContainsString('2 item line(s) · 6 total added', $logContent);
        $this->assertStringContainsString('Restock Product', $logContent);
        $this->assertStringContainsString('Second Restock Product', $logContent);
    }

    public function test_legacy_restock_rows_from_one_submission_are_grouped_in_transaction_logs(): void
    {
        $hub = StoreHub::create([
            'name' => 'Legacy Restock HO', 'code' => 'LEGACY-HO', 'status' => 'active', 'is_head_office' => true,
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $createdAt = now()->startOfSecond();
        $products = collect(['Legacy Product One', 'Legacy Product Two'])->map(function ($name, $index) use ($hub, $admin, $createdAt) {
            $product = Product::create([
                'name' => $name, 'item_id' => 'LEGACY-RESTOCK-'.$index,
                'store_hub_id' => $hub->id, 'stock' => 4, 'status' => 'active',
            ]);
            InventoryTransaction::create([
                'type' => 'restock',
                'store_hub_id' => $hub->id,
                'product_id' => $product->id,
                'source' => 'warehouse_request',
                'quantity' => 2,
                'occurred_on' => '2026-09-30',
                'created_by' => $admin->id,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            return $product;
        });

        $content = $this->actingAs($admin)->get(route('inventory-transactions.index', [
            'hub_id' => $hub->id,
            'type' => 'restock',
        ]))->assertOk()->getContent();
        $this->assertSame(1, substr_count($content, '<strong>Restock</strong>'));
        $this->assertStringContainsString('2 item line(s) · 4 total added', $content);
        $this->assertStringContainsString('Legacy Product One', $content);
        $this->assertStringContainsString('Legacy Product Two', $content);
    }

    public function test_transaction_log_detail_items_are_sorted_naturally_by_item_id(): void
    {
        $hub = StoreHub::create([
            'name' => 'Sorted Detail Hub', 'code' => 'SORTED-DETAIL', 'status' => 'active', 'is_head_office' => true,
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        foreach ([
            ['name' => 'Item Ten', 'item_id' => '10'],
            ['name' => 'Item Two', 'item_id' => '2'],
        ] as $item) {
            $product = Product::create([
                ...$item,
                'store_hub_id' => $hub->id,
                'stock' => 5,
                'status' => 'active',
            ]);
            InventoryTransaction::create([
                'type' => 'restock',
                'store_hub_id' => $hub->id,
                'product_id' => $product->id,
                'reference' => 'SORTED-DETAIL-RESTOCK',
                'quantity' => 1,
                'occurred_on' => '2026-10-15',
                'created_by' => $admin->id,
            ]);
        }

        $this->actingAs($admin)
            ->get(route('inventory-transactions.index', ['hub_id' => $hub->id, 'type' => 'restock']))
            ->assertOk()
            ->assertSeeInOrder(['Item ID: 2', 'Item ID: 10'], false);
    }

    public function test_sold_order_details_use_compact_order_metadata_cards(): void
    {
        $hub = StoreHub::create([
            'name' => 'Sales Head Office', 'code' => 'SALES-HO', 'status' => 'active', 'is_head_office' => true,
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $product = Product::create([
            'name' => 'Sold Product', 'item_id' => 'SOLD-1',
            'store_hub_id' => $hub->id, 'stock' => 8, 'status' => 'active',
        ]);
        $sale = SalesTransaction::create([
            'user_id' => $admin->id,
            'store_hub_id' => $hub->id,
            'channel_type' => 'tiktok',
            'customer_name' => 'Sales Customer',
            'order_number' => 'SOLD-ORDER-1',
            'order_date' => '2026-09-30',
            'status' => 'completed',
        ]);
        $sale->items()->create([
            'product_id' => $product->id, 'quantity' => 2, 'unit_price' => 100, 'line_total' => 200,
        ]);
        InventoryTransaction::create([
            'type' => 'sold',
            'store_hub_id' => $hub->id,
            'product_id' => $product->id,
            'sales_transaction_id' => $sale->id,
            'reference' => $sale->order_number,
            'channel' => 'tiktok',
            'quantity' => 2,
            'occurred_on' => '2026-09-30',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->get(route('inventory-transactions.index', [
            'hub_id' => $hub->id,
            'type' => 'sold',
        ]))->assertOk()
            ->assertSee('Sold Order Details')
            ->assertSee('modal-dialog-centered modal-dialog-scrollable', false)
            ->assertSee('Submitted by')
            ->assertSee('Sales Channel')
            ->assertDontSee('<strong>Transfer</strong>', false)
            ->assertDontSee('<strong>Reference / Notes</strong>', false)
            ->assertDontSee('<strong>Condition</strong>', false);
    }

    public function test_transaction_log_paginates_fifteen_grouped_display_rows(): void
    {
        $hub = StoreHub::create([
            'name' => 'Pagination Hub', 'code' => 'PAGE-HUB', 'status' => 'active', 'is_head_office' => true,
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $product = Product::create([
            'name' => 'Pagination Product', 'item_id' => 'PAGE-1',
            'store_hub_id' => $hub->id, 'stock' => 50, 'status' => 'active',
        ]);
        foreach (range(1, 25) as $index) {
            InventoryTransaction::create([
                'type' => 'sponsor_workshop',
                'store_hub_id' => $hub->id,
                'product_id' => $product->id,
                'reference' => 'SW-PAGE-'.$index,
                'source' => 'Pagination test',
                'quantity' => 1,
                'occurred_on' => '2026-09-30',
                'created_by' => $admin->id,
            ]);
        }

        $firstResponse = $this->actingAs($admin)->get(route('inventory-transactions.index', [
            'hub_id' => $hub->id,
            'type' => 'sponsor_workshop',
        ]))
            ->assertOk()
            ->assertSee('Transaction activity')
            ->assertSee('15 per page')
            ->assertSee('pagination-summary')
            ->assertDontSee('Showing 1–15 of 25 logs')
            ->assertSee('class="log-modal-row"', false);
        $firstPage = $firstResponse->viewData('transactions');
        $this->assertSame(1, substr_count($firstResponse->getContent(), 'pagination-summary'));
        $this->assertSame(15, $firstPage->count());
        $this->assertSame(25, $firstPage->total());

        $secondResponse = $this->get(route('inventory-transactions.index', [
            'hub_id' => $hub->id,
            'type' => 'sponsor_workshop',
            'page' => 2,
        ]))->assertOk()
            ->assertSee('pagination-summary')
            ->assertDontSee('Showing 16–25 of 25 logs');
        $secondPage = $secondResponse
            ->viewData('transactions');
        $this->assertSame(10, $secondPage->count());
        $this->assertSame(25, $secondPage->total());
        $this->assertStringContainsString('type=sponsor_workshop', $secondPage->url(1));
    }

    public function test_transaction_logs_are_sorted_by_date_then_time_descending(): void
    {
        $hub = StoreHub::create([
            'name' => 'Sorted Logs Hub', 'code' => 'SORTED-LOGS', 'status' => 'active', 'is_head_office' => true,
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $product = Product::create([
            'name' => 'Sorted Logs Product', 'item_id' => 'SORTED-1',
            'store_hub_id' => $hub->id, 'stock' => 10, 'status' => 'active',
        ]);

        foreach ([
            ['reference' => 'OLDER-DATE-LATE-CREATED', 'occurred_on' => '2026-10-13', 'created_at' => '2026-10-14 11:00:00'],
            ['reference' => 'NEWER-DATE-EARLY-TIME', 'occurred_on' => '2026-10-14', 'created_at' => '2026-10-14 08:00:00'],
            ['reference' => 'NEWER-DATE-LATE-TIME', 'occurred_on' => '2026-10-14', 'created_at' => '2026-10-14 15:30:00'],
        ] as $attributes) {
            $transaction = InventoryTransaction::create($attributes + [
                'type' => 'sponsor_workshop',
                'store_hub_id' => $hub->id,
                'product_id' => $product->id,
                'source' => 'Sorting test',
                'quantity' => 1,
                'created_by' => $admin->id,
                'updated_at' => $attributes['created_at'],
            ]);
            $transaction->forceFill([
                'created_at' => $attributes['created_at'],
                'updated_at' => $attributes['created_at'],
            ])->saveQuietly();
        }

        $response = $this->actingAs($admin)->get(route('inventory-transactions.index', [
            'hub_id' => $hub->id,
            'type' => 'sponsor_workshop',
        ]))->assertOk();

        $response->assertSeeInOrder([
            'NEWER-DATE-LATE-TIME',
            'NEWER-DATE-EARLY-TIME',
            'OLDER-DATE-LATE-CREATED',
        ])->assertSee('Date / Time')
            ->assertSee('03:30 PM')
            ->assertSee('08:00 AM');
    }

    public function test_branch_transfer_starts_with_the_only_item_delete_button_disabled(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get(route('inventory-transactions.branch-transfer.create'))
            ->assertOk()
            ->assertSee('remove-transfer-item" aria-label="Remove item" disabled', false)
            ->assertDontSee('Submit for Approval')
            ->assertSee('transfer-items-header', false)
            ->assertSee('aria-label="Product"', false)
            ->assertSee('aria-label="Quantity"', false)
            ->assertSee('rows.length <= 1', false);
    }

    public function test_inventory_workflows_redirect_to_logs_with_popup_messages(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $hub = StoreHub::create([
            'name' => 'Workflow Hub', 'code' => 'WORKFLOW', 'status' => 'active', 'is_head_office' => true,
        ]);
        $product = Product::create([
            'name' => 'Workflow Product', 'item_id' => 'WORKFLOW-1', 'store_hub_id' => $hub->id,
            'stock' => 10, 'status' => 'active',
        ]);
        $logsUrl = route('inventory-transactions.index', ['hub_id' => $hub->id]);

        $sponsorResponse = $this->post(route('inventory-transactions.store'), [
            'type' => 'sponsor_workshop', 'store_hub_id' => $hub->id,
            'occurred_on' => '2026-09-21', 'source' => 'Community Workshop',
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])->assertSessionHasNoErrors();
        $sponsorReference = InventoryTransaction::where('type', 'sponsor_workshop')->value('reference');
        $sponsorLogsUrl = route('inventory-transactions.index', [
            'hub_id' => $hub->id,
            'sponsor_workshop_saved' => 1,
            'reference' => $sponsorReference,
        ]);
        $sponsorResponse->assertRedirect($sponsorLogsUrl);
        $this->get($sponsorLogsUrl)->assertOk()
            ->assertSee('id="sponsor-workshop-success-popup"', false)
            ->assertSee($sponsorReference);

        $restockResponse = $this->post(route('inventory-transactions.store'), [
            'type' => 'restock', 'store_hub_id' => $hub->id,
            'occurred_on' => '2026-09-21', 'restock_source' => 'warehouse_request',
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
        ])->assertSessionHasNoErrors();
        $restockReference = InventoryTransaction::where('type', 'restock')->value('reference');
        $restockLogsUrl = route('inventory-transactions.index', [
            'hub_id' => $hub->id,
            'restock_saved' => 1,
            'reference' => $restockReference,
        ]);
        $restockResponse->assertRedirect($restockLogsUrl);
        $this->get($restockLogsUrl)->assertOk()
            ->assertSee('id="restock-success-popup"', false)
            ->assertSee($restockReference);
        $this->assertDatabaseHas('inventory_transactions', [
            'reference' => $restockReference,
            'type' => 'restock',
        ]);
        $this->assertTrue((bool) $restockReference);

        $sale = SalesTransaction::create([
            'store_hub_id' => $hub->id, 'channel_type' => 'online', 'order_date' => '2026-09-21',
            'order_number' => 'RETURN-WORKFLOW-1', 'customer_name' => 'Return Customer',
        ]);
        $item = $sale->items()->create([
            'product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100, 'line_total' => 100,
        ]);

        $this->post(route('inventory-transactions.store'), [
            'type' => 'return', 'store_hub_id' => $hub->id, 'occurred_on' => '2026-09-21',
            'channel' => 'online', 'reference' => $sale->order_number,
            'sales_transaction_id' => $sale->id,
            'items' => [[
                'product_id' => $product->id, 'transaction_item_id' => $item->id,
                'good_quantity' => 1, 'damaged_quantity' => 0,
            ]],
        ])->assertSessionHasNoErrors()
            ->assertRedirect(route('inventory-transactions.index', ['hub_id' => $hub->id, 'type' => 'return']))
            ->assertSessionHas('success', 'Return items recorded successfully.');
    }
}
