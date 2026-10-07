<?php

namespace Tests\Feature;

use App\Models\PendingSale;
use App\Models\Product;
use App\Models\ProductStockAllocation;
use App\Models\SalesTransaction;
use App\Models\StoreHub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SalesWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_tiktok_customer_lookup_searches_existing_customers_by_name(): void
    {
        $hub = StoreHub::create([
            'name' => 'Customer Lookup Head Office',
            'code' => 'CUSTOMER-LOOKUP',
            'status' => 'active',
            'is_head_office' => true,
        ]);
        $admin = User::factory()->create(['hub_id' => $hub->id, 'role' => 'admin']);

        foreach (range(1, 35) as $index) {
            SalesTransaction::create([
                'store_hub_id' => $hub->id,
                'channel_type' => 'tiktok',
                'order_date' => now()->subDays($index)->toDateString(),
                'order_number' => 'TIKTOK-CUSTOMER-'.$index,
                'customer_name' => $index === 35 ? 'Older TikTok Customer' : 'Recent TikTok Customer '.$index,
            ]);
        }
        SalesTransaction::create([
            'store_hub_id' => $hub->id,
            'channel_type' => 'online',
            'order_date' => now()->toDateString(),
            'order_number' => 'ONLINE-CUSTOMER-1',
            'customer_name' => 'Older Online Customer',
        ]);

        $this->actingAs($admin)
            ->getJson(route('hub.sales.customers', [
                'hubId' => $hub->id,
                'channel' => 'tiktok',
                'q' => 'Older TikTok',
            ]))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['name' => 'Older TikTok Customer']);

        $dashboard = $this->get(route('hub.dashboard', $hub->id))->assertOk();
        $dashboard->assertSee('loadCustomerOptions(customerInput.value)', false)
            ->assertSee('setTimeout(', false)
            ->assertSee("url.searchParams.set('q', search.trim())", false);
    }

    public function test_assigned_marketplace_staff_can_view_only_approved_orders_for_the_selected_channel(): void
    {
        $hub = StoreHub::create([
            'name' => 'Marketplace Orders Head Office',
            'code' => 'MARKET-ORDERS',
            'status' => 'active',
            'is_head_office' => true,
        ]);
        $staff = User::factory()->create([
            'role' => 'sales_marketing_staff',
            'hub_id' => $hub->id,
            'sales_channels' => ['shopee', 'lazada', 'tiktok'],
        ]);
        $product = Product::create([
            'store_hub_id' => $hub->id,
            'item_id' => 'MARKET-ITEM',
            'name' => 'Marketplace order product',
            'stock' => 10,
            'status' => 'active',
        ]);
        $shopeeOrder = SalesTransaction::create([
            'user_id' => $staff->id,
            'store_hub_id' => $hub->id,
            'channel_type' => 'shopee',
            'order_number' => 'SHOPEE-APPROVED-001',
            'customer_name' => 'Shopee Customer',
            'order_date' => '2026-10-06',
            'grand_total' => 175,
            'status' => 'confirmed',
            'mode_of_payment' => 'COD',
        ]);
        $shopeeOrder->items()->create([
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 87.5,
            'line_total' => 175,
        ]);
        SalesTransaction::create([
            'user_id' => $staff->id,
            'store_hub_id' => $hub->id,
            'channel_type' => 'lazada',
            'order_number' => 'LAZADA-APPROVED-001',
            'customer_name' => 'Lazada Customer',
            'order_date' => '2026-10-06',
            'grand_total' => 250,
            'status' => 'confirmed',
        ]);
        SalesTransaction::create([
            'user_id' => $staff->id,
            'store_hub_id' => $hub->id,
            'channel_type' => 'shopee',
            'order_number' => 'SHOPEE-PENDING-001',
            'customer_name' => 'Pending Customer',
            'order_date' => '2026-10-06',
            'grand_total' => 90,
            'status' => 'pending',
        ]);

        $this->actingAs($staff)
            ->get(route('hub.marketplace-orders', ['hub' => $hub->id, 'channel' => 'shopee']))
            ->assertOk()
            ->assertSee('Shopee Approved Orders')
            ->assertSee('Order Number')
            ->assertSee('Customer Name')
            ->assertSee('Order Date')
            ->assertSee('SHOPEE-APPROVED-001')
            ->assertSee('View Order Items')
            ->assertSee('id="marketplace-order-items-'.$shopeeOrder->id.'"', false)
            ->assertSee('Payment method: COD')
            ->assertSee('Marketplace order product')
            ->assertDontSee('<th>Items</th>', false)
            ->assertDontSee('LAZADA-APPROVED-001')
            ->assertDontSee('SHOPEE-PENDING-001');

        $hubDashboard = $this->get(route('hub.dashboard', $hub->id));
        $hubDashboard
            ->assertOk()
            ->assertSee('Shopee Orders')
            ->assertSee('Lazada Orders')
            ->assertSee('Shopee Returns')
            ->assertSee('Lazada Returns')
            ->assertSee('TikTok Orders')
            ->assertSee('TikTok Returns');
        $hubDashboardContent = $hubDashboard->getContent();
        $this->assertLessThan(
            strpos($hubDashboardContent, 'TikTok Returns'),
            strpos($hubDashboardContent, 'TikTok Orders')
        );

        $this->get(route('hub.marketplace-returns', ['hub' => $hub->id, 'channel' => 'shopee']))
            ->assertOk()
            ->assertSee('Shopee Returns');
        $this->get(route('hub.marketplace-returns', ['hub' => $hub->id, 'channel' => 'lazada']))
            ->assertOk()
            ->assertSee('Lazada Returns');

        $unassignedStaff = User::factory()->create([
            'role' => 'sales_marketing_staff',
            'hub_id' => $hub->id,
            'sales_channels' => ['online'],
        ]);
        $this->actingAs($unassignedStaff)
            ->get(route('hub.marketplace-orders', ['hub' => $hub->id, 'channel' => 'shopee']))
            ->assertForbidden();
    }

    public function test_marketplace_order_pages_have_bottom_left_results_and_bottom_right_numeric_pagination(): void
    {
        $hub = StoreHub::create([
            'name' => 'Marketplace Pagination Head Office',
            'code' => 'MARKET-PAGINATION',
            'status' => 'active',
            'is_head_office' => true,
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        foreach (['shopee', 'lazada', 'tiktok'] as $channel) {
            foreach (range(1, 16) as $number) {
                SalesTransaction::create([
                    'user_id' => $admin->id,
                    'store_hub_id' => $hub->id,
                    'channel_type' => $channel,
                    'order_number' => strtoupper($channel).'-PAGE-'.$number,
                    'customer_name' => 'Pagination Customer',
                    'order_date' => '2026-10-06',
                    'status' => 'confirmed',
                ]);
            }

            $response = $this->actingAs($admin)->get(route('hub.marketplace-orders', [
                'hub' => $hub->id,
                'channel' => $channel,
            ]));
            $response->assertOk()
                ->assertSee('Showing')
                ->assertSee('of')
                ->assertSee('results')
                ->assertViewHas('orders', fn ($orders) => $orders->total() === 16 && $orders->hasPages())
                ->assertSee('aria-label="Pagination Navigation"', false)
                ->assertSee('page=2', false);
        }
    }

    public function test_shopee_and_lazada_generate_invoice_numbers_when_left_blank(): void
    {
        $hub = StoreHub::create([
            'name' => 'Marketplace Head Office', 'code' => 'MKT-HO', 'status' => 'active', 'is_head_office' => true,
        ]);
        $user = User::factory()->create(['hub_id' => $hub->id, 'role' => 'admin']);
        $product = Product::create([
            'item_id' => 'MKT-001', 'name' => 'Marketplace Product', 'barcode' => 'BARCODE-MKT-001', 'sales_price' => 100,
            'shopee_price' => 100, 'lazada_price' => 100, 'stock' => 10, 'status' => 'active', 'store_hub_id' => $hub->id,
        ]);
        $this->actingAs($user)
            ->getJson(route('hub.products.search.ajax', ['hubId' => $hub->id, 'q' => 'BARCODE-MKT-001']))
            ->assertOk()
            ->assertJsonPath('0.barcode', 'BARCODE-MKT-001');

        foreach (['shopee' => 'SHOPEE-000001', 'lazada' => 'LAZADA-000001'] as $channel => $expectedInvoice) {
            $this->actingAs($user)->post(route('sales.storeMultiChannelSale'), [
                'store_hub_id' => $hub->id,
                'sales_channel' => $channel,
                'placed_order_date' => '2026-09-28',
                'order_number' => '',
                'customer_name' => ucfirst($channel).' Customer',
                'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100]],
            ])->assertSessionHasNoErrors()->assertSessionHas('success');

            $this->assertDatabaseHas('pending_sales', [
                'sales_channel' => $channel,
                'invoice_number' => $expectedInvoice,
            ]);
        }

        $this->actingAs($user)->get(route('hub.dashboard', $hub))
            ->assertOk()
            ->assertSee('SPayLater')
            ->assertSee('PayLater')
            ->assertSee('Mixedcard')
            ->assertSee('Credit/Debit Card')
            ->assertSee('ShopeePay Balance')
            ->assertSee('Online/Offline Payment')
            ->assertSee('QRph')
            ->assertSee('Specify MOP')
            ->assertSee('marketplace-mop-menu', false);

        $channelMopOrder = [
            'store_hub_id' => $hub->id,
            'placed_order_date' => '2026-09-28',
            'customer_name' => 'Channel MOP Customer',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100]],
        ];
        $this->post(route('sales.storeMultiChannelSale'), $channelMopOrder + [
            'sales_channel' => 'lazada', 'mode_of_payment' => 'SPAYLATER',
        ])->assertSessionHasErrors('mode_of_payment');
        $this->post(route('sales.storeMultiChannelSale'), $channelMopOrder + [
            'sales_channel' => 'shopee', 'mode_of_payment' => 'PAYLATER',
        ])->assertSessionHasErrors('mode_of_payment');
        $this->post(route('sales.storeMultiChannelSale'), $channelMopOrder + [
            'sales_channel' => 'lazada', 'mode_of_payment' => 'PAYLATER',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertDatabaseHas('pending_sales', [
            'customer_name' => 'Channel MOP Customer', 'sales_channel' => 'lazada', 'mode_of_payment' => 'PAYLATER',
        ]);

        $otherMopOrder = [
            'store_hub_id' => $hub->id,
            'sales_channel' => 'shopee',
            'placed_order_date' => '2026-09-28',
            'customer_name' => 'Other MOP Customer',
            'mode_of_payment' => 'OTHERS',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100]],
        ];
        $this->post(route('sales.storeMultiChannelSale'), $otherMopOrder)
            ->assertSessionHasErrors('mode_of_payment_others');
        $this->post(route('sales.storeMultiChannelSale'), $otherMopOrder + ['mode_of_payment_others' => 'Cash on pickup'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');
        $this->assertDatabaseHas('pending_sales', [
            'customer_name' => 'Other MOP Customer',
            'mode_of_payment' => 'Cash on pickup',
        ]);
    }

    public function test_inventory_staff_can_cover_only_their_assigned_sales_channels(): void
    {
        $hub = StoreHub::create([
            'name' => 'Head Office',
            'code' => 'HO-COVER',
            'status' => 'active',
            'is_head_office' => true,
        ]);
        $product = Product::create([
            'item_id' => 'COVER-001',
            'name' => 'Coverage Product',
            'sales_price' => 100,
            'shopee_price' => 100,
            'stock' => 10,
            'status' => 'active',
            'store_hub_id' => $hub->id,
        ]);
        $unassignedInventory = User::factory()->create([
            'hub_id' => $hub->id,
            'role' => 'inventory_staff',
            'sales_channels' => [],
        ]);
        $assignedInventory = User::factory()->create([
            'hub_id' => $hub->id,
            'role' => 'inventory_staff',
            'sales_channels' => ['shopee'],
        ]);
        $order = [
            'store_hub_id' => $hub->id,
            'placed_order_date' => '2026-09-17',
            'order_number' => 'COVER-ORDER-001',
            'customer_name' => 'Coverage Customer',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 100,
            ]],
        ];

        $this->actingAs($unassignedInventory)
            ->post(route('sales.storeMultiChannelSale'), $order + ['sales_channel' => 'shopee'])
            ->assertForbidden();

        $this->actingAs($assignedInventory)
            ->get(route('hub.dashboard', $hub))
            ->assertOk()
            ->assertSee('Record Multi-Channel Sale')
            ->assertSee('Shipping Service Fee')
            ->assertDontSee('TikTok Tracking Details')
            ->assertSee('Shopee Sales')
            ->assertDontSee('Lazada Sales');

        $this->post(route('sales.storeMultiChannelSale'), $order + ['sales_channel' => 'lazada'])
            ->assertForbidden();

        $this->post(route('sales.storeMultiChannelSale'), $order + ['sales_channel' => 'shopee'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('pending_sales', [
            'submitted_by' => $assignedInventory->id,
            'sales_channel' => 'shopee',
            'invoice_number' => 'COVER-ORDER-001',
        ]);
    }


    public function test_pending_sale_can_be_recorded_and_confirmed(): void
    {
        $hub = StoreHub::create([
            'name' => 'Test Hub',
            'code' => 'test-hub',
            'status' => 'active',
            'is_head_office' => true,
        ]);
        $user = User::factory()->create([
            'hub_id' => $hub->id,
            'role' => 'admin',
        ]);
        $product = Product::create([
            'item_id' => 'TEST-001',
            'name' => 'Test Product',
            'sales_price' => 100,
            'stock' => 10,
            'status' => 'active',
            'store_hub_id' => $hub->id,
        ]);
        ProductStockAllocation::create(['product_id' => $product->id, 'wholesale' => 10]);

        $otherHub = StoreHub::create([
            'name' => 'Other Hub',
            'code' => 'other-hub',
            'status' => 'active',
        ]);

        $this->actingAs($user)->post(route('sales.storeMultiChannelSale'), [
            'store_hub_id' => $otherHub->id,
            'sales_channel' => 'Wholesale',
            'placed_order_date' => '2026-08-28',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
            ]],
        ])->assertNotFound();

        $response = $this->actingAs($user)->post(route('sales.storeMultiChannelSale'), [
            'store_hub_id' => $hub->id,
            'sales_channel' => 'Wholesale',
            'placed_order_date' => '2026-08-28',
            'customer_name' => 'Test Customer',
            'order_number' => 'ORDER-001',
            'mode_of_payment' => 'Check',
            'bank_name' => 'Test Bank',
            'check_number' => 'CHECK-001',
            'proof_of_payment' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'),
            'payment_status' => 'partial',
            'amount_paid' => 50,
            'delivery_status' => 'shipped',
            'shipping_fee_amount' => 25,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 2,
                'unit_price' => 100,
            ]],
        ]);

        $response->assertSessionHasNoErrors()->assertSessionHas('success');

        $pendingSale = PendingSale::sole();
        $this->assertSame('Test Bank', $pendingSale->bank_name);
        $this->assertSame('25.00', $pendingSale->shipping_fee_amount);
        $this->assertSame('partial', $pendingSale->payment_status);
        $this->assertSame('shipped', $pendingSale->delivery_status);
        $this->assertSame(10, $product->fresh()->stock);

        $pendingReport = $this->actingAs($user)
            ->get(route('hub.report', ['hub' => $hub->id, 'channel' => 'wholesale']));

        $pendingReport
            ->assertOk()
            ->assertDontSee('ORDER-001');
        $this->assertSame(0.0, (float) $pendingReport->viewData('totalSales'));
        $this->assertSame(0, $pendingReport->viewData('totalTransactions'));

        $this->actingAs($user)
            ->get(route('hub.sales.pending', $hub->id))
            ->assertOk()
            ->assertSee('ORDER-001')
            ->assertSee('READY')
            ->assertSee('Test Product')
            ->assertSee('Review Inventory (1)')
            ->assertSee('data-bs-target="#itemsModal'.$pendingSale->id.'"', false)
            ->assertSee('Order Items')
            ->assertSee('ORDER-001')
            ->assertSee('Available<br>(Wholesale)', false)
            ->assertSee('Verify')
            ->assertSee('Deduct Stock');

        $this->assertSame('partial', $pendingSale->fresh()->payment_status);
        $this->assertSame('50.00', $pendingSale->fresh()->amount_paid);
        $this->assertSame('shipped', $pendingSale->fresh()->delivery_status);

        $response = $this->actingAs($user)->post(route('sales.confirmPending', $pendingSale));

        $response->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame('confirmed', $pendingSale->fresh()->status);
        $this->assertSame(8, $product->fresh()->stock);

        $transaction = SalesTransaction::with('items')->sole();
        $this->assertSame($user->id, $transaction->user_id);
        $this->assertSame('ORDER-001', $transaction->order_number);
        $this->assertSame('confirmed', $transaction->status);
        $this->assertSame('partial', $transaction->payment_status);
        $this->assertSame('50.00', $transaction->amount_paid);
        $this->assertSame('shipped', $transaction->delivery_status);
        $this->assertCount(1, $transaction->items);

        $this->actingAs($user)->patch(route('sales.status.update', $transaction->id), [
            'payment_status' => 'paid',
            'amount_paid' => 0,
            'delivery_status' => 'delivered',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame('paid', $transaction->fresh()->payment_status);
        $this->assertSame($transaction->grand_total, $transaction->fresh()->amount_paid);
        $this->assertSame('delivered', $transaction->fresh()->delivery_status);

        $confirmedReport = $this->actingAs($user)
            ->get(route('hub.report', ['hub' => $hub->id, 'channel' => 'wholesale']));
        $this->assertSame(1, $confirmedReport->viewData('totalTransactions'));
        $confirmedReport->assertSee('ORDER-001');

        $this->actingAs($user)
            ->get(route('hub.sales.pending', $hub->id))
            ->assertOk()
            ->assertDontSee('ORDER-001');

        $this->actingAs($user)
            ->post(route('sales.confirmPending', $pendingSale))
            ->assertSessionHas('error');
        $this->assertSame(8, $product->fresh()->stock);
        $this->assertSame(1, SalesTransaction::count());
    }

    public function test_inventory_staff_can_reject_an_order_without_deducting_stock(): void
    {
        $hub = StoreHub::create([
            'name' => 'Verification Hub',
            'code' => 'verification-hub',
            'status' => 'active',
        ]);
        $salesStaff = User::factory()->create([
            'hub_id' => $hub->id,
            'role' => 'sales_marketing_staff',
            'sales_channels' => ['shopee'],
        ]);
        $inventoryStaff = User::factory()->create([
            'hub_id' => $hub->id,
            'role' => 'inventory_staff',
        ]);
        $product = Product::create([
            'item_id' => 'REJECT-001',
            'name' => 'Rejected Product',
            'sales_price' => 100,
            'stock' => 5,
            'status' => 'active',
            'store_hub_id' => $hub->id,
        ]);

        $this->actingAs($salesStaff)->post(route('sales.storeMultiChannelSale'), [
            'store_hub_id' => $hub->id,
            'sales_channel' => 'Shopee',
            'placed_order_date' => '2026-08-28',
            'order_number' => 'REJECT-ORDER-001',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 2,
            ]],
        ])->assertSessionHas('success');

        $pendingSale = PendingSale::sole();

        $this->actingAs($salesStaff)
            ->get(route('hub.sales.pending', $hub->id))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('notification_error');

        $this->actingAs($inventoryStaff)
            ->get(route('hub.sales.pending', $hub->id))
            ->assertOk()
            ->assertSee('REJECT-ORDER-001');

        $this->actingAs($inventoryStaff)->post(route('sales.rejectPending', $pendingSale), [
            'rejection_reason' => 'The requested quantity could not be verified.',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame('rejected', $pendingSale->fresh()->status);
        $this->assertSame($inventoryStaff->id, $pendingSale->fresh()->rejected_by);
        $this->assertSame(5, $product->fresh()->stock);
        $this->assertSame(0, SalesTransaction::count());

        $this->actingAs($salesStaff)
            ->get(route('sales.rejected', ['hub_id' => $hub->id]))
            ->assertOk()
            ->assertSee('The requested quantity could not be verified.')
            ->assertSee('Order Items')
            ->assertSee('Rejected Product');
    }

    public function test_mixed_stock_order_waits_for_stock_without_partial_deductions(): void
    {
        $hub = StoreHub::create([
            'name' => 'Stock Check Hub',
            'code' => 'stock-check-hub',
            'status' => 'active',
        ]);
        $inventoryStaff = User::factory()->create([
            'hub_id' => $hub->id,
            'role' => 'inventory_staff',
        ]);

        $products = collect([
            ['item_id' => 'MIX-001', 'name' => 'Product Item 1', 'stock' => 10],
            ['item_id' => 'MIX-002', 'name' => 'Product Item 2', 'stock' => 5],
            ['item_id' => 'MIX-003', 'name' => 'Product Item 3', 'stock' => 0],
        ])->map(fn ($data) => Product::create($data + [
            'sales_price' => 100,
            'status' => 'active',
            'store_hub_id' => $hub->id,
        ]))->each(fn (Product $product) => ProductStockAllocation::create([
            'product_id' => $product->id,
            'online' => $product->stock,
        ]));

        $pendingSale = PendingSale::create([
            'store_hub_id' => $hub->id,
            'sales_channel' => 'Online',
            'placed_order_date' => '2026-09-03',
            'invoice_number' => 'MIXED-STOCK-001',
            'items' => [
                ['product_id' => $products[0]->id, 'product_name' => $products[0]->name, 'quantity' => 10, 'unit_price' => 100],
                ['product_id' => $products[1]->id, 'product_name' => $products[1]->name, 'quantity' => 5, 'unit_price' => 100],
                ['product_id' => $products[2]->id, 'product_name' => $products[2]->name, 'quantity' => 1, 'unit_price' => 100],
            ],
            'status' => 'pending',
            'inventory_status' => 'awaiting_stock',
            'submitted_by' => $inventoryStaff->id,
        ]);

        $this->actingAs($inventoryStaff)
            ->get(route('hub.sales.pending', $hub->id))
            ->assertOk()
            ->assertSee('AWAITING STOCK')
            ->assertSee('Product Item 3')
            ->assertSee('SHORT 1');

        $this->actingAs($inventoryStaff)
            ->post(route('sales.confirmPending', $pendingSale))
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'Awaiting stock'));

        $this->assertSame('pending', $pendingSale->fresh()->status);
        $this->assertSame('awaiting_stock', $pendingSale->fresh()->inventory_status);
        $this->assertSame([10, 5, 0], $products->map(fn ($product) => $product->fresh()->stock)->all());
        $this->assertSame(0, SalesTransaction::count());

        $products[2]->update(['stock' => 1]);
        $products[2]->stockAllocation->update(['online' => 1]);

        $this->actingAs($inventoryStaff)
            ->get(route('hub.sales.pending', $hub->id))
            ->assertOk()
            ->assertSee('READY');

        $this->actingAs($inventoryStaff)
            ->post(route('sales.confirmPending', $pendingSale))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertSame('confirmed', $pendingSale->fresh()->status);
        $this->assertSame('verified', $pendingSale->fresh()->inventory_status);
        $this->assertSame([0, 0, 0], $products->map(fn ($product) => $product->fresh()->stock)->all());
        $this->assertSame(1, SalesTransaction::count());
    }

    public function test_inventory_staff_can_confirm_pending_sales_from_another_store_hub(): void
    {
        $assignedHub = StoreHub::create([
            'name' => 'Assigned Inventory Hub',
            'code' => 'ASSIGNED-INV',
            'status' => 'active',
        ]);
        $saleHub = StoreHub::create([
            'name' => 'Walk-In Branch Hub',
            'code' => 'SALE-BRANCH',
            'status' => 'active',
        ]);
        $inventoryStaff = User::factory()->create([
            'hub_id' => $assignedHub->id,
            'role' => 'inventory_staff',
        ]);
        $salesAssociate = User::factory()->create([
            'hub_id' => $saleHub->id,
            'role' => 'sales_associate',
        ]);
        $product = Product::create([
            'item_id' => 'CROSS-HUB-001',
            'name' => 'Cross Hub Product',
            'sales_price' => 100,
            'stock' => 5,
            'status' => 'active',
            'store_hub_id' => $saleHub->id,
        ]);
        $pendingSale = PendingSale::create([
            'store_hub_id' => $saleHub->id,
            'sales_channel' => 'walk_in',
            'placed_order_date' => '2026-09-28',
            'invoice_number' => 'CROSS-HUB-ORDER',
            'customer_name' => 'Branch Customer',
            'items' => [[
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => 2,
                'unit_price' => 100,
            ]],
            'status' => 'pending',
            'inventory_status' => 'ready',
            'submitted_by' => $salesAssociate->id,
        ]);

        $this->actingAs($inventoryStaff)
            ->post(route('sales.confirmPending', $pendingSale))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertSame('confirmed', $pendingSale->fresh()->status);
        $this->assertSame($saleHub->id, $pendingSale->fresh()->store_hub_id);
        $this->assertSame(3, $product->fresh()->stock);
        $this->assertDatabaseHas('sales_transactions', [
            'store_hub_id' => $saleHub->id,
            'order_number' => 'CROSS-HUB-ORDER',
            'status' => 'confirmed',
        ]);
    }

    public function test_channel_reports_use_tiktok_and_online_financial_formulas(): void
    {
        $hub = StoreHub::create([
            'name' => 'Report Hub',
            'code' => 'report-hub',
            'status' => 'active',
            'is_head_office' => true,
        ]);
        $admin = User::factory()->create([
            'hub_id' => $hub->id,
            'role' => 'admin',
        ]);
        $product = Product::create([
            'item_id' => 'REPORT-001',
            'name' => 'Report Product',
            'sales_price' => 100,
            'tiktok_price' => 120,
            'stock' => 20,
            'status' => 'active',
            'store_hub_id' => $hub->id,
        ]);
        ProductStockAllocation::create(['product_id' => $product->id, 'tiktok' => 20, 'online' => 20]);

        $this->actingAs($admin)->post(route('sales.storeMultiChannelSale'), [
            'store_hub_id' => $hub->id,
            'sales_channel' => 'tiktok',
            'placed_order_date' => '2026-09-03',
            'customer_name' => 'TikTok Customer',
            'order_number' => 'TIKTOK-REPORT-001',
            'sales_after_transaction_fee' => 150,
            'refund_shipping_fee' => 5,
            'drop_off_date' => '2026-09-04',
            'note' => 'TikTok report note',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 2,
                'unit_price' => 100,
                'discount_percentage' => 10,
            ]],
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $tiktokPending = PendingSale::where('invoice_number', 'TIKTOK-REPORT-001')->sole();
        $this->assertSame('10.80', $tiktokPending->shipping_service_fee);
        $this->actingAs($admin)
            ->get(route('sales.pending'))
            ->assertOk()
            ->assertSee('TIKTOK-REPORT-001')
            ->assertDontSee('Order Summary');

        $this->actingAs($admin)
            ->post(route('sales.confirmPending', $tiktokPending))
            ->assertSessionHas('success');
        $tiktokTransaction = SalesTransaction::where('order_number', 'TIKTOK-REPORT-001')->sole();
        $tiktokTransaction = SalesTransaction::where('order_number', 'TIKTOK-REPORT-001')->sole();
        $this->assertSame('150.00', $tiktokTransaction->sales_after_transaction_fee);
        $this->assertSame('10.80', $tiktokTransaction->shipping_service_fee);
        $this->assertSame('0.00', $tiktokTransaction->refund_shipping_fee);

        $this->actingAs($admin)->get(route('hub.report', [
            'hub' => $hub->id,
            'channel' => 'tiktok',
        ]))->assertNotFound();

        $this->actingAs($admin)->post(route('sales.storeMultiChannelSale'), [
            'store_hub_id' => $hub->id,
            'sales_channel' => 'online',
            'placed_order_date' => '2026-09-03',
            'customer_name' => 'Online Customer',
            'order_number' => 'ONLINE-REPORT-001',
            'online_mop' => 'GCASH',
            'proof_of_payment' => UploadedFile::fake()->create('online-proof.pdf', 10, 'application/pdf'),
            'delivery_fee' => 30,
            'proof_amount' => 230,
            'note' => 'Reconciled payment',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 2,
                'unit_price' => 100,
            ]],
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $onlinePending = PendingSale::where('invoice_number', 'ONLINE-REPORT-001')->sole();
        $this->actingAs($admin)
            ->post(route('sales.confirmPending', $onlinePending))
            ->assertSessionHas('success');

        $onlineReport = $this->actingAs($admin)->get(route('hub.report', [
            'hub' => $hub->id,
            'channel' => 'online',
        ]));
        $onlineReport
            ->assertOk()
            ->assertSee('Payment reconciliation')
            ->assertSee('Reconciliation Difference')
            ->assertSee('ONLINE-REPORT-001', false)
            ->assertSee('Reconciled payment')
            ->assertSee('Difference')
            ->assertDontSee('Difference ₱');
        $this->assertSame(230.0, (float) $onlineReport->viewData('metrics')['proof_amount']);
        $this->assertSame(0.0, (float) $onlineReport->viewData('metrics')['difference']);
    }
}
