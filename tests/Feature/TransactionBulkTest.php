<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductStockAllocation;
use App\Models\InventoryTransaction;
use App\Models\SalesTransaction;
use App\Models\StoreHub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionBulkTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_worksheets_download_as_csv_with_excel_safe_identifiers(): void
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
                ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
                ->assertHeader('Content-Disposition', 'attachment; filename='.\Illuminate\Support\Str::upper(\Illuminate\Support\Str::slug($hub->code, '_')).'_Download_'.($isHeadOffice ? 'StockTrf_HO2B' : 'StockTrf_B2B').'_'.now()->format('Ymd_His').'.csv');

            $lines = preg_split('/\r\n|\n|\r/', ltrim($response->streamedContent(), "\xEF\xBB\xBF"));
            $this->assertSame(
                ['Name / Description', 'Barcode', 'Item ID', 'Physical Stocks Qty', 'Physical Actual Pullout'],
                str_getcsv($lines[0])
            );
            $rows = array_map('str_getcsv', array_slice($lines, 1));
            if ($isHeadOffice) {
                $this->assertSame('="000012345678901234567890"', $rows[0][2]);
                $this->assertSame('="00007661234567891234567890"', $rows[0][1]);
                $this->assertSame('8', $rows[0][3]);
            } else {
                $this->assertSame(
                    ['="1"', '="2"', '="10"', '="000012345678901234567890"'],
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
            ->assertSee('Return record')
            ->assertSee('Recorded by')
            ->assertSee('Store Hub')
            ->assertSee('Channel')
            ->assertDontSee('<strong>Transfer</strong>', false);

        $this->actingAs($admin)->get(route('inventory-transactions.return.create', ['hub_id' => $branch->id]))
            ->assertOk()
            ->assertSee('Return Items')
            ->assertSee('class="transaction-page-heading mb-4"', false)
            ->assertDontSee('Returned Items History')
            ->assertSee('value="walk_in" selected', false)
            ->assertDontSee('value="shopee"', false)
            ->assertSee('class="return-actions"', false)
            ->assertSee('id="returnAttachmentPreview"', false)
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
            ->assertSee(route('inventory-transactions.return.create', ['hub_id' => $branch->id]), false);
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
        $this->assertSame(1, (int) $product->fresh()->stock);
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

            $this->assertSame(10, $product->fresh()->stock, "{$channel} should restore both returned units to physical stock.");
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
            ->assertSee('class="transaction-page-heading mb-4"', false)
            ->assertSee('Reference Document No.')
            ->assertSee('id="sponsorItemsHeader"', false)
            ->assertSee('aria-label="Product"', false)
            ->assertSee('aria-label="Quantity"', false)
            ->assertSee('readonly', false)
            ->assertSee('SW-HEAD-OFFICE-'.now()->format('Ymd').'-', false);
        $this->assertSame($hub->id, $formResponse->viewData('hubId'));
        $formResponse
            ->assertDontSee('<option value="'.$inactiveHub->id.'"', false);

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
            ->assertSee('Submitted by')
            ->assertSee('Sales Channel')
            ->assertDontSee('<strong>Transfer</strong>', false)
            ->assertDontSee('<strong>Reference / Notes</strong>', false)
            ->assertDontSee('<strong>Condition</strong>', false);
    }

    public function test_transaction_log_paginates_twenty_grouped_display_rows(): void
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

        $firstPage = $this->actingAs($admin)->get(route('inventory-transactions.index', ['hub_id' => $hub->id]))
            ->assertOk()
            ->viewData('transactions');
        $this->assertSame(20, $firstPage->count());
        $this->assertSame(25, $firstPage->total());

        $secondPage = $this->get(route('inventory-transactions.index', ['hub_id' => $hub->id, 'page' => 2]))
            ->assertOk()
            ->viewData('transactions');
        $this->assertSame(5, $secondPage->count());
        $this->assertSame(25, $secondPage->total());
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
