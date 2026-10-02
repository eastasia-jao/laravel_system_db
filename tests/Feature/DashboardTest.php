<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductStockAllocation;
use App\Models\SalesTransaction;
use App\Models\StoreHub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketing_staff_can_scope_every_dashboard_widget_to_shopee_or_lazada(): void
    {
        $hub = StoreHub::create(['name' => 'Marketplace Dashboard', 'code' => 'MKT-D', 'status' => 'active', 'is_head_office' => true]);
        $staff = User::factory()->create(['role' => 'sales_marketing_staff', 'hub_id' => $hub->id, 'sales_channels' => ['shopee', 'lazada']]);
        $shopeeProduct = Product::create(['store_hub_id' => $hub->id, 'item_id' => 'SH-D', 'name' => 'Shopee Dashboard Product', 'stock' => 10, 'status' => 'active']);
        $lazadaProduct = Product::create(['store_hub_id' => $hub->id, 'item_id' => 'LZ-D', 'name' => 'Lazada Dashboard Product', 'stock' => 10, 'status' => 'active']);
        SalesTransaction::create([
            'user_id' => $staff->id, 'store_hub_id' => $hub->id, 'customer_name' => 'Earlier Shopee Customer',
            'contact_number' => '+63 917 123 4567', 'channel_type' => 'shopee', 'order_number' => 'SP-OLD',
            'order_date' => '2025-09-28', 'grand_total' => 75, 'status' => 'completed',
        ]);

        foreach ([
            ['shopee', 'SPAYLATER', 120, $shopeeProduct],
            ['lazada', 'PAYLATER', 230, $lazadaProduct],
        ] as [$channel, $mop, $total, $product]) {
            $sale = SalesTransaction::create([
                'user_id' => $staff->id, 'store_hub_id' => $hub->id, 'customer_name' => ucfirst($channel).' Customer',
                'contact_number' => $channel === 'shopee' ? '09171234567' : '09170000002',
                'channel_type' => $channel, 'order_number' => strtoupper($channel).'-DASH', 'order_date' => '2026-09-28',
                'grand_total' => $total, 'status' => 'completed', 'mode_of_payment' => $mop,
            ]);
            $sale->items()->create(['product_id' => $product->id, 'quantity' => 1, 'unit_price' => $total, 'line_total' => $total]);
        }

        $shopee = $this->actingAs($staff)->get(route('dashboard', ['hub_id' => $hub->id, 'date' => '2026-09-28', 'channel' => 'shopee']));
        $shopee->assertOk()->assertSee('Sales channel')->assertSee('Shopee Payment Overview')->assertSee('Total collected')->assertSee('1 transaction')->assertSee('Shopee Dashboard Product')->assertDontSee('Lazada Dashboard Product')
            ->assertSee('<h3 class="mb-1">Sales Channels</h3>', false)
            ->assertSee('row g-4 align-items-stretch mb-4', false)
            ->assertSee('col-xl-6', false)
            ->assertSee('Shopee');
        $this->assertEquals(120, $shopee->viewData('salesTotals')['Daily']);
        $this->assertContains('SPayLater', $shopee->viewData('paymentColumns'));
        $this->assertNotContains('PayLater', $shopee->viewData('paymentColumns'));
        $this->assertSame(75.0, $shopee->viewData('marketplaceSalesComparison')['shopee']['last_year']);
        $this->assertSame(120.0, $shopee->viewData('marketplaceSalesComparison')['shopee']['this_year']);
        $this->assertSame(1, $shopee->viewData('marketplaceCustomerComparison')['shopee']['last_year']);
        $this->assertSame(1, $shopee->viewData('marketplaceCustomerComparison')['shopee']['this_year']);
        $this->assertSame(['shopee'], $shopee->viewData('channelSummaries')->pluck('key')->all());
        $this->assertSame(1, substr_count($shopee->getContent(), 'Shopee Payment Overview'));
        $shopeeSummary = $shopee->viewData('channelSummaries')->first();
        $this->assertSame(1, $shopeeSummary['total_customers']);
        $this->assertSame(0, $shopeeSummary['new_customers']);

        $lazada = $this->get(route('dashboard', ['hub_id' => $hub->id, 'date' => '2026-09-28', 'channel' => 'lazada']));
        $lazada->assertOk()->assertSee('Lazada Payment Overview')->assertSee('Total collected')->assertSee('1 transaction')->assertSee('Lazada Dashboard Product')->assertDontSee('Shopee Dashboard Product')
            ->assertSee('<h3 class="mb-1">Sales Channels</h3>', false)
            ->assertSee('row g-4 align-items-stretch mb-4', false)
            ->assertSee('col-xl-6', false)
            ->assertSee('Lazada');
        $this->assertEquals(230, $lazada->viewData('salesTotals')['Daily']);
        $this->assertContains('PayLater', $lazada->viewData('paymentColumns'));
        $this->assertNotContains('SPayLater', $lazada->viewData('paymentColumns'));
        $this->assertSame(1, substr_count($lazada->getContent(), 'Lazada Payment Overview'));
        $this->assertSame(1, $lazada->viewData('channelSummaries')->first()['total_customers']);
        $this->assertSame(1, $lazada->viewData('channelSummaries')->first()['new_customers']);
        $this->assertSame(0, $lazada->viewData('marketplaceCustomerComparison')['lazada']['last_year']);
        $this->assertSame(1, $lazada->viewData('marketplaceCustomerComparison')['lazada']['this_year']);

        $allAssigned = $this->get(route('dashboard', ['hub_id' => $hub->id, 'date' => '2026-09-28']));
        $allAssigned->assertOk()
            ->assertSee('onchange="submitDashboardFilters(this.form)"', false)
            ->assertDontSee('All assigned channels')
            ->assertDontSee('Select a sales channel')
            ->assertSee('value="shopee" selected', false)
            ->assertSee('Shopee Payment Overview')
            ->assertDontSee('Lazada Payment Overview')
            ->assertDontSee('Shopee &amp; Lazada Sales')
            ->assertDontSee('marketplace-dual-chart', false)
            ->assertSee('Shopee Dashboard Product')
            ->assertDontSee('Lazada Dashboard Product')
            ->assertSee('<h3 class="mb-1">Sales Channels</h3>', false)
            ->assertSee('Sales · selected range');
        $this->assertEquals(120, $allAssigned->viewData('salesTotals')['Daily']);
        $this->assertSame(['shopee'], $allAssigned->viewData('marketplaceOverviews')->pluck('channel')->all());
        $this->assertSame(75.0, $allAssigned->viewData('marketplaceSalesComparison')['shopee']['last_year']);
        $this->assertSame(120.0, $allAssigned->viewData('marketplaceSalesComparison')['shopee']['this_year']);

        $this->actingAs($staff)->get(route('dashboard', [
            'hub_id' => $hub->id,
            'date' => '2026-09-28',
            'channel' => 'tiktok',
        ]))->assertForbidden();

        $staff->sales_channels = ['shopee', 'lazada', 'tiktok'];
        $staff->save();
        $threeChannels = $this->get(route('dashboard', [
            'hub_id' => $hub->id,
            'date' => '2026-09-28',
        ]));
        $threeChannels->assertOk()
            ->assertSee('Sales channel')
            ->assertDontSee('All assigned channels')
            ->assertSee('value="shopee" selected', false)
            ->assertSee('<h3 class="mb-1">Sales Channels</h3>', false);
        $this->assertSame(['shopee'], $threeChannels->viewData('channelSummaries')->pluck('key')->all());

        $staff->sales_channels = ['shopee', 'lazada', 'tiktok', 'online', 'wholesale'];
        $staff->save();
        $fiveChannels = $this->get(route('dashboard', [
            'hub_id' => $hub->id,
            'date' => '2026-10-01',
        ]));
        $fiveChannels->assertOk()
            ->assertDontSee('Shopee &amp; Lazada Sales')
            ->assertSee('Shopee Payment Overview')
            ->assertDontSee('Lazada Payment Overview')
            ->assertSee('Sales channel')
            ->assertDontSee('All assigned channels')
            ->assertSee('value="shopee" selected', false)
            ->assertSee('<h3 class="mb-1">Sales Channels</h3>', false);
        $this->assertSame(['shopee'], $fiveChannels->viewData('channelSummaries')->pluck('key')->all());
    }

    public function test_sales_channel_filter_is_locked_for_one_channel_and_selectable_for_multiple(): void
    {
        $hub = StoreHub::create([
            'name' => 'Single Channel Dashboard',
            'code' => 'SINGLE-CHANNEL',
            'status' => 'active',
            'is_head_office' => true,
        ]);
        $staff = User::factory()->create([
            'role' => 'sales_marketing_staff',
            'hub_id' => $hub->id,
            'sales_channels' => ['tiktok'],
        ]);

        $singleChannelResponse = $this->actingAs($staff)->get(route('dashboard', ['hub_id' => $hub->id]));
        $singleChannelResponse->assertOk()
            ->assertSee('id="dashboardChannel"', false)
            ->assertSee('value="tiktok" selected', false);
        $this->assertMatchesRegularExpression(
            '/<select name="channel" id="dashboardChannel"[^>]*\sdisabled(?:\s|>)/',
            $singleChannelResponse->getContent()
        );

        $staff->sales_channels = ['tiktok', 'shopee'];
        $staff->save();

        $multipleChannelResponse = $this->get(route('dashboard', ['hub_id' => $hub->id]));
        $multipleChannelResponse->assertOk()
            ->assertSee('onchange="submitDashboardFilters(this.form)"', false);
        $this->assertDoesNotMatchRegularExpression(
            '/<select name="channel" id="dashboardChannel"[^>]*\sdisabled(?:\s|>)/',
            $multipleChannelResponse->getContent()
        );
    }

    public function test_marketing_staff_with_no_assigned_channels_cannot_view_any_channel_data(): void
    {
        $hub = StoreHub::create([
            'name' => 'Unassigned Channel Dashboard',
            'code' => 'NO-CHANNELS',
            'status' => 'active',
            'is_head_office' => true,
        ]);
        $staff = User::factory()->create([
            'role' => 'sales_marketing_staff',
            'hub_id' => $hub->id,
            'sales_channels' => [],
        ]);
        $product = Product::create([
            'store_hub_id' => $hub->id,
            'item_id' => 'NO-CHANNELS-PRODUCT',
            'name' => 'Restricted Channel Product',
            'stock' => 10,
            'status' => 'active',
        ]);
        foreach (['shopee', 'tiktok'] as $channel) {
            $sale = SalesTransaction::create([
                'user_id' => $staff->id,
                'store_hub_id' => $hub->id,
                'customer_name' => 'Restricted Customer',
                'channel_type' => $channel,
                'order_number' => strtoupper($channel).'-RESTRICTED',
                'order_date' => '2026-09-28',
                'grand_total' => 250,
                'status' => 'completed',
            ]);
            $sale->items()->create([
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 250,
                'line_total' => 250,
            ]);
        }

        $response = $this->actingAs($staff)->get(route('dashboard', [
            'hub_id' => $hub->id,
            'date' => '2026-09-28',
        ]));

        $response->assertOk()
            ->assertDontSee('<h3 class="mb-1">Sales Channels</h3>', false)
            ->assertDontSee('Shopee Payment Overview')
            ->assertDontSee('TikTok Settlement Overview');
        $this->assertSame([], $response->viewData('channelSummaries')->all());
        $this->assertSame([], $response->viewData('marketplaceOverviews')->all());
        $this->assertEquals(0, $response->viewData('salesTotals')['Daily']);
        $this->assertSame([], $response->viewData('topProducts')->all());

        $this->get(route('dashboard', [
            'hub_id' => $hub->id,
            'date' => '2026-09-28',
            'channel' => 'shopee',
        ]))->assertForbidden();
    }

    public function test_online_and_walk_in_sales_channels_compare_last_year_with_this_year(): void
    {
        $hub = StoreHub::create([
            'name' => 'Head Office Channel Comparison',
            'code' => 'HO-COMPARE',
            'status' => 'active',
            'is_head_office' => true,
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        foreach ([
            ['online', 'Online', 2025, 100],
            ['online', 'Online', 2026, 250],
            ['walk_in', 'Walk-In', 2025, 150],
            ['walk_in', 'Walk-In', 2026, 350],
        ] as [$channel, $label, $year, $amount]) {
            SalesTransaction::create([
                'user_id' => $admin->id,
                'store_hub_id' => $hub->id,
                'customer_name' => $label.' Customer '.$year,
                'channel_type' => $channel,
                'order_number' => strtoupper($channel).'-'.$year,
                'order_date' => $year.'-09-10',
                'grand_total' => $amount,
                'status' => 'completed',
                'mode_of_payment' => 'GCASH',
            ]);
        }

        foreach ([
            'online' => ['Online', 100.0, 250.0],
            'walk_in' => ['Walk-In', 150.0, 350.0],
        ] as $channel => [$label, $lastYear, $thisYear]) {
            $response = $this->actingAs($admin)->get(route('dashboard', [
                'hub_id' => $hub->id,
                'channel' => $channel,
                'from' => '2026-09-01',
                'to' => '2026-09-30',
            ]));

            $response->assertOk()
                ->assertSee($label)
                ->assertSee('Sales · selected range')
                ->assertSee('Last Yr<br>(2025)', false)
                ->assertSee('This Yr<br>(2026)', false)
                ->assertSee($label.' Sales Overview')
                ->assertSee('Total collected')
                ->assertSee('GCash')
                ->assertDontSee('Branch Transaction Matrix')
                ->assertSee('row g-4 align-items-stretch mb-4', false)
                ->assertSee('col-xl-6', false);
            $this->assertSame($lastYear, $response->viewData('marketplaceSalesComparison')[$channel]['last_year']);
            $this->assertSame($thisYear, $response->viewData('marketplaceSalesComparison')[$channel]['this_year']);
            $this->assertSame($thisYear, $response->viewData('channelPaymentOverview')['total']);
            $this->assertSame(1, $response->viewData('channelPaymentOverview')['counts']['GCash']);
        }
    }

    public function test_non_head_office_dashboard_uses_branch_sales_snapshot_and_payment_cards(): void
    {
        $branch = StoreHub::create(['name' => 'North Branch', 'code' => 'NORTH', 'status' => 'active', 'is_head_office' => false]);
        $otherBranch = StoreHub::create(['name' => 'South Branch', 'code' => 'SOUTH', 'status' => 'active', 'is_head_office' => false]);
        $branchProduct = Product::create(['store_hub_id' => $branch->id, 'item_id' => 'NORTH-LOW', 'name' => 'North Low Stock', 'stock' => 8, 'status' => 'active']);
        ProductStockAllocation::create(['product_id' => $branchProduct->id, 'shopee' => 8]);
        Product::create(['store_hub_id' => $otherBranch->id, 'item_id' => 'SOUTH-LOW', 'name' => 'South Low Stock', 'stock' => 4, 'status' => 'active']);
        $admin = User::factory()->create(['role' => 'admin']);
        SalesTransaction::create([
            'user_id' => $admin->id,
            'store_hub_id' => $branch->id,
            'channel_type' => 'walk_in',
            'customer_name' => 'Branch Customer',
            'contact_number' => '09170000111',
            'order_number' => 'NORTH-001',
            'order_date' => '2026-09-28',
            'grand_total' => 450,
            'status' => 'completed',
            'mode_of_payment' => 'GCASH',
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard', [
            'hub_id' => $branch->id,
            'date' => '2026-09-28',
        ]));

        $response->assertOk()
            ->assertSee('Branch Sales Snapshot')
            ->assertSee('Walk-In sales in selected range')
            ->assertSee('Payment Methods')
            ->assertSee('Live Inventory Alerts')
            ->assertSee('North Low Stock')
            ->assertDontSee('South Low Stock')
            ->assertSee('NORTH BRANCH')
            ->assertSee('GCash')
            ->assertSee('PayMaya')
            ->assertSee('QRPH')
            ->assertSee('Metrobank')
            ->assertDontSee('Dated Check')
            ->assertDontSee('Post-Dated Check')
            ->assertDontSee('Branch Transaction Matrix')
            ->assertDontSee('Sales Channels');
        $this->assertTrue($response->viewData('isBranchDashboard'));
        $this->assertSame(1, $response->viewData('alertCount'));
        $this->assertSame(8, $response->viewData('alerts')->first()->stock);
        $this->assertSame(450.0, $response->viewData('channelSummaries')->first()['total']);
    }

    public function test_non_head_office_dashboard_shows_inventory_alerts_to_branch_staff(): void
    {
        $branch = StoreHub::create(['name' => 'Staff Branch', 'code' => 'STAFF-BR', 'status' => 'active', 'is_head_office' => false]);
        $staff = User::factory()->create(['role' => 'sales_associate', 'hub_id' => $branch->id]);
        Product::create(['store_hub_id' => $branch->id, 'item_id' => 'STAFF-LOW', 'name' => 'Staff Low Stock', 'stock' => 5, 'status' => 'active']);

        $response = $this->actingAs($staff)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Live Inventory Alerts')
            ->assertSee('Staff Low Stock')
            ->assertSee('Critical stock');
        $this->assertSame(1, $response->viewData('alertCount'));
    }

    public function test_live_inventory_alerts_are_paginated_ten_per_page_and_keep_dashboard_filters(): void
    {
        $hub = StoreHub::create(['name' => 'Alerts Hub', 'code' => 'ALERTS-HUB', 'status' => 'active', 'is_head_office' => true]);
        $admin = User::factory()->create(['role' => 'admin']);
        foreach (range(0, 11) as $stock) {
            Product::create([
                'store_hub_id' => $hub->id,
                'item_id' => 'ALERT-'.str_pad((string) $stock, 2, '0', STR_PAD_LEFT),
                'name' => 'Alert Product '.$stock,
                'stock' => $stock,
                'status' => 'active',
            ]);
        }

        $pageOne = $this->actingAs($admin)->get(route('dashboard', [
            'hub_id' => $hub->id,
            'date' => '2026-09-28',
        ]))->assertOk()
            ->assertSee('Showing 1–10 of 12 alerts')
            ->assertSee('alerts_page=2', false)
            ->assertSee('hub_id='.$hub->id, false)
            ->assertSee('date=2026-09-28', false)
            ->assertSee('id="inventoryAlertResults"', false)
            ->assertSee('loadDashboardResults', false)
            ->assertSee('window.addEventListener(\'popstate\'', false);

        $this->assertCount(10, $pageOne->viewData('alerts'));
        $this->assertSame(12, $pageOne->viewData('alertCount'));
        $pageOne->assertSee('Alert Product 0')->assertDontSee('Alert Product 11');

        $pageTwo = $this->get(route('dashboard', [
            'hub_id' => $hub->id,
            'date' => '2026-09-28',
            'alerts_page' => 2,
        ]))->assertOk()
            ->assertSee('Showing 11–12 of 12 alerts')
            ->assertSee('Alert Product 10')
            ->assertSee('Alert Product 11')
            ->assertDontSee('Alert Product 0');

        $this->assertCount(2, $pageTwo->viewData('alerts'));
        $this->assertSame(12, $pageTwo->viewData('alertCount'));
    }

    public function test_dashboard_hides_store_selector_when_only_one_branch_is_accessible(): void
    {
        $branch = StoreHub::create(['name' => 'Only Branch', 'code' => 'ONLY-BRANCH', 'status' => 'active']);
        $staff = User::factory()->create(['role' => 'sales_marketing_staff', 'hub_id' => $branch->id]);

        $response = $this->actingAs($staff)->get(route('dashboard'));

        $response->assertOk()
            ->assertDontSee('id="dashboardHub"', false)
            ->assertSee('ONLY BRANCH')
            ->assertSee('name="hub_id" value="'.$branch->id.'"', false);
    }

    public function test_dashboard_defaults_to_designated_store_and_store_changes_submit_automatically(): void
    {
        $designatedHub = StoreHub::create(['name' => 'Designated Branch', 'code' => 'DESIGNATED', 'status' => 'active']);
        $assignedHub = StoreHub::create(['name' => 'Assigned Branch', 'code' => 'ASSIGNED', 'status' => 'active']);
        $staff = User::factory()->create(['role' => 'sales_associate', 'hub_id' => $designatedHub->id]);
        $staff->assignedStoreHubs()->attach($assignedHub);

        $response = $this->actingAs($staff)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('id="dashboardHub" class="form-select" onchange="submitDashboardFilters(this.form)"', false)
            ->assertSee('<option value="'.$designatedHub->id.'" selected>DESIGNATED BRANCH</option>', false)
            ->assertSee('<input type="date" id="dashboardFrom"', false)
            ->assertSee('<input type="date" id="dashboardTo"', false)
            ->assertSee('<button class="btn btn-primary px-3">', false);
        $this->assertSame($designatedHub->id, $response->viewData('hubId'));
    }

    public function test_dashboard_applies_from_and_to_dates_to_sales_widgets(): void
    {
        $hub = StoreHub::create(['name' => 'Date Range Branch', 'code' => 'DATE-RANGE', 'status' => 'active']);
        $user = User::factory()->create(['role' => 'admin']);
        $product = Product::create(['store_hub_id' => $hub->id, 'item_id' => 'RANGE-1', 'name' => 'Date Range Product', 'stock' => 20, 'status' => 'active']);

        foreach ([
            ['2026-09-07', 50],
            ['2026-09-08', 100],
            ['2026-09-09', 200],
            ['2026-09-10', 400],
        ] as [$date, $amount]) {
            $sale = SalesTransaction::create([
                'user_id' => $user->id,
                'store_hub_id' => $hub->id,
                'customer_name' => 'Range Customer',
                'channel_type' => 'walk_in',
                'order_number' => 'RANGE-'.$date,
                'order_date' => $date,
                'grand_total' => $amount,
                'status' => 'completed',
                'mode_of_payment' => 'GCASH',
            ]);
            $sale->items()->create(['product_id' => $product->id, 'quantity' => 1, 'unit_price' => $amount, 'line_total' => $amount]);
        }

        $response = $this->actingAs($user)->get(route('dashboard', [
            'hub_id' => $hub->id,
            'from' => '2026-09-08',
            'to' => '2026-09-09',
        ]));

        $response->assertOk()->assertSee('Sep 08, 2026 – Sep 09, 2026');
        $this->assertSame('2026-09-08', $response->viewData('fromDate')->toDateString());
        $this->assertSame('2026-09-09', $response->viewData('asOf')->toDateString());
        $this->assertSame(200.0, $response->viewData('salesTotals')['Daily']);
        $this->assertSame(300.0, $response->viewData('salesTotals')['Weekly']);
        $this->assertSame(300.0, $response->viewData('channelSummaries')->first()['total']);
        $this->assertSame(2, $response->viewData('topProducts')->first()['units']);

        $this->get(route('dashboard', [
            'hub_id' => $hub->id,
            'from' => '2026-09-10',
            'to' => '2026-09-09',
        ]))->assertSessionHasErrors('to');
    }

    public function test_slow_moving_items_only_include_in_stock_products_and_paginate_ten_per_page(): void
    {
        $hub = StoreHub::create(['name' => 'Slow Items Branch', 'code' => 'SLOW-ITEMS', 'status' => 'active']);
        $user = User::factory()->create(['role' => 'admin']);
        foreach (range(1, 12) as $number) {
            Product::create([
                'store_hub_id' => $hub->id,
                'item_id' => sprintf('SLOW-%02d', $number),
                'name' => sprintf('Stocked slow item %02d', $number),
                'stock' => 5,
                'status' => 'active',
            ]);
        }
        Product::create([
            'store_hub_id' => $hub->id,
            'item_id' => 'OUT-OF-STOCK',
            'name' => 'Out of stock slow item',
            'stock' => 0,
            'status' => 'active',
        ]);

        $firstPage = $this->actingAs($user)->get(route('dashboard', [
            'hub_id' => $hub->id,
            'from' => '2026-09-01',
            'to' => '2026-09-30',
        ]));

        $firstPage->assertOk()
            ->assertSee('Stocked slow item 01')
            ->assertSee('Stocked slow item 10')
            ->assertSee('slow_page=2', false)
            ->assertSee('id="slowProductsResults"', false)
            ->assertSee('#slowProductsResults nav p { display: none; }', false)
            ->assertSee('event.preventDefault()', false);
        $this->assertSame(10, $firstPage->viewData('slowProducts')->count());
        $this->assertSame(12, $firstPage->viewData('slowProducts')->total());
        $this->assertSame(
            range(1, 10),
            $firstPage->viewData('slowProducts')->getCollection()->map(fn ($product) => (int) substr($product['name'], -2))->all()
        );

        $secondPage = $this->get(route('dashboard', [
            'hub_id' => $hub->id,
            'from' => '2026-09-01',
            'to' => '2026-09-30',
            'slow_page' => 2,
        ]));
        $secondPage->assertOk()
            ->assertSee('Stocked slow item 11')
            ->assertSee('Stocked slow item 12');
        $this->assertSame(2, $secondPage->viewData('slowProducts')->count());
        $this->assertSame(
            [11, 12],
            $secondPage->viewData('slowProducts')->getCollection()->map(fn ($product) => (int) substr($product['name'], -2))->all()
        );
    }

    public function test_dashboard_and_reports_exclude_returned_units_and_show_refund_cost(): void
    {
        $hub = StoreHub::create(['name' => 'Returned Orders Hub', 'code' => 'RET-D', 'status' => 'active', 'is_head_office' => true]);
        $user = User::factory()->create(['role' => 'admin']);
        $product = Product::create(['store_hub_id' => $hub->id, 'item_id' => 'RET-ITEM', 'name' => 'Returned Dashboard Item', 'stock' => 10, 'status' => 'active']);
        $sale = SalesTransaction::create([
            'user_id' => $user->id, 'store_hub_id' => $hub->id, 'customer_name' => 'Return Customer',
            'contact_number' => '09170000003', 'channel_type' => 'shopee', 'order_number' => 'RET-ORDER',
            'order_date' => '2026-09-28', 'date_of_arrangement' => '2026-09-28',
            'grand_total' => 270, 'sub_total' => 270,
            'status' => 'completed', 'mode_of_payment' => 'GCASH',
        ]);
        $item = $sale->items()->create([
            'product_id' => $product->id, 'quantity' => 3, 'returned_quantity' => 1,
            'return_status' => 'received', 'unit_price' => 100, 'discount_percentage' => 10, 'line_total' => 270,
        ]);
        \App\Models\InventoryTransaction::create([
            'type' => 'return', 'store_hub_id' => $hub->id, 'product_id' => $product->id,
            'sales_transaction_id' => $sale->id, 'transaction_item_id' => $item->id, 'channel' => 'shopee',
            'quantity' => 1, 'refund_amount' => 90, 'occurred_on' => '2026-09-28', 'created_by' => $user->id,
        ]);

        $dashboard = $this->actingAs($user)->get(route('dashboard', ['hub_id' => $hub->id, 'date' => '2026-09-28', 'channel' => 'shopee']));
        $dashboard->assertOk()->assertSee('Refund cost');
        $this->assertEquals(180, $dashboard->viewData('salesTotals')['Daily']);
        $this->assertEquals(180, $dashboard->viewData('channelSummaries')->first()['total']);
        $this->assertEquals(90, $dashboard->viewData('channelSummaries')->first()['refund_total']);

        $report = $this->get(route('hub.report', ['hub' => $hub->id, 'channel' => 'shopee']));
        $report->assertOk()
            ->assertSee('Total Refund Cost')
            ->assertSee('New Customers')
            ->assertSee('Save as PNG')
            ->assertSee('180.00');
        $this->assertEquals(200, $report->viewData('metrics')['gross_sales']);
        $this->assertEquals(180, $report->viewData('metrics')['total_sales']);
        $this->assertEquals(90, $report->viewData('metrics')['refund_total']);
        $this->assertSame(['total' => 1, 'new' => 1], $report->viewData('customerMetrics'));
    }

    public function test_dashboard_filters_real_sales_products_and_inventory(): void
    {
        $hub = StoreHub::create(['name' => 'Dashboard Branch', 'code' => 'DB', 'status' => 'active']);
        $other = StoreHub::create(['name' => 'Other Branch', 'code' => 'OTH', 'status' => 'active']);
        $user = User::factory()->create(['role' => 'admin']);
        $product = Product::create(['store_hub_id' => $hub->id, 'item_id' => 'DASH-1', 'name' => 'Real Bestseller', 'stock' => 3, 'status' => 'active']);
        foreach ([['2026-09-08', 100, 'completed', $hub->id], ['2026-09-07', 50, 'completed', $hub->id], ['2026-09-09', 900, 'completed', $hub->id], ['2026-09-08', 800, 'pending', $hub->id], ['2026-09-08', 700, 'completed', $other->id]] as [$date, $total, $status, $store]) {
            $sale = SalesTransaction::create(['user_id' => $user->id, 'store_hub_id' => $store, 'customer_name' => 'Dashboard Customer', 'channel_type' => 'walk_in', 'order_number' => uniqid(), 'order_date' => $date, 'grand_total' => $total, 'status' => $status, 'mode_of_payment' => 'GCASH']);
            $sale->items()->create(['product_id' => $product->id, 'quantity' => 1, 'unit_price' => $total, 'line_total' => $total]);
        }
        $response = $this->actingAs($user)->get(route('dashboard', ['hub_id' => $hub->id, 'date' => '2026-09-08']));
        $response->assertOk()
            ->assertSee('Real Bestseller')
            ->assertSee('top-products-list', false)
            ->assertSee('Critical stock');
        $this->assertEquals(100, $response->viewData('salesTotals')['Daily']);
        $this->assertEquals(150, $response->viewData('salesTotals')['Weekly']);
        $this->assertEquals(150, $response->viewData('matrix')->first()['amounts']['GCash']);
        $this->assertEquals(2, $response->viewData('topProducts')->first()['units']);
        $this->assertEquals(1, $response->viewData('alertCount'));

        $staff = User::factory()->create(['role' => 'sales_associate', 'hub_id' => $hub->id]);
        $this->actingAs($staff)->get(route('dashboard', ['hub_id' => $other->id]))->assertForbidden();
        $this->get(route('dashboard'))->assertOk()->assertViewHas('dashboardHubs', fn ($hubs) => $hubs->modelKeys() === [$hub->id]);
    }

    public function test_admin_all_stores_dashboard_compares_head_office_and_branch_sales(): void
    {
        $headOffice = StoreHub::create([
            'name' => 'Central Head Office',
            'code' => 'CENTRAL-HO',
            'status' => 'active',
            'is_head_office' => true,
        ]);
        $branch = StoreHub::create([
            'name' => 'North Branch Store',
            'code' => 'NORTH-BR',
            'status' => 'active',
            'is_head_office' => false,
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        SalesTransaction::create([
            'user_id' => $admin->id,
            'store_hub_id' => $headOffice->id,
            'channel_type' => 'walk_in',
            'order_number' => 'ALL-STORE-HO-001',
            'customer_name' => 'Head Office Customer',
            'order_date' => '2026-09-28',
            'grand_total' => 300,
            'status' => 'completed',
        ]);
        SalesTransaction::create([
            'user_id' => $admin->id,
            'store_hub_id' => $branch->id,
            'channel_type' => 'walk_in',
            'order_number' => 'ALL-STORE-BR-001',
            'customer_name' => 'Branch Customer',
            'order_date' => '2026-09-29',
            'grand_total' => 175,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard', [
            'from' => '2026-09-01',
            'to' => '2026-09-30',
        ]));

        $response->assertOk()
            ->assertSee('All accessible stores')
            ->assertSee('<h3 class="mb-1">Sales Channels</h3>', false)
            ->assertSee('all-store-channel-section', false)
            ->assertSee('all-store-channel-grid', false)
            ->assertSee('row-cols-lg-3', false)
            ->assertSee('Combined channel performance across all accessible stores')
            ->assertSee('All-Store Performance')
            ->assertSee('Sales by store')
            ->assertSee('Vertical bar chart comparing net sales', false)
            ->assertSee('store-sales-chart-grid', false)
            ->assertSee('CENTRAL HEAD OFFICE')
            ->assertSee('NORTH BRANCH STORE')
            ->assertSee('Head Office')
            ->assertSee('Branch');

        $this->assertTrue($response->viewData('isAllStoresAdminDashboard'));
        $this->assertSame(
            ['CENTRAL HEAD OFFICE', 'NORTH BRANCH STORE'],
            $response->viewData('storeSalesOverview')->pluck('name')->all()
        );
        $this->assertSame([300.0, 175.0], $response->viewData('storeSalesOverview')->pluck('total')->all());
        $this->assertSame(
            ['shopee', 'lazada', 'tiktok', 'online', 'wholesale', 'walk_in'],
            $response->viewData('channelSummaries')->pluck('key')->all()
        );
        $this->assertEquals(475, $response->viewData('salesTotals')['Monthly']);
        $this->assertEquals(475, $response->viewData('channelSummaries')->firstWhere('key', 'walk_in')['total']);
    }

    public function test_admin_head_office_dashboard_uses_a_three_column_sales_channel_layout(): void
    {
        $headOffice = StoreHub::create([
            'name' => 'Selected Head Office',
            'code' => 'SELECTED-HO',
            'status' => 'active',
            'is_head_office' => true,
        ]);
        $branch = StoreHub::create([
            'name' => 'Selected Branch',
            'code' => 'SELECTED-BR',
            'status' => 'active',
            'is_head_office' => false,
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        $headOfficeResponse = $this->actingAs($admin)->get(route('dashboard', ['hub_id' => $headOffice->id]));
        $headOfficeResponse->assertOk()
            ->assertSee('head-office-channel-section', false)
            ->assertSee('head-office-channel-grid', false)
            ->assertSee('row-cols-lg-3', false);

        $branchResponse = $this->get(route('dashboard', ['hub_id' => $branch->id]));
        $branchResponse->assertOk()
            ->assertDontSee('panel head-office-channel-section', false)
            ->assertDontSee('head-office-channel-grid', false);
    }

    public function test_dashboard_hides_shopee_and_lazada_sales_chart_for_all_roles(): void
    {
        $hub = StoreHub::create([
            'name' => 'Admin Marketplace Dashboard',
            'code' => 'ADMIN-MARKETPLACE',
            'status' => 'active',
            'is_head_office' => true,
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        foreach (['shopee', 'lazada'] as $channel) {
            SalesTransaction::create([
                'user_id' => $admin->id,
                'store_hub_id' => $hub->id,
                'channel_type' => $channel,
                'order_number' => strtoupper($channel).'-ADMIN',
                'customer_name' => ucfirst($channel).' Admin Customer',
                'order_date' => '2026-09-28',
                'grand_total' => 100,
                'status' => 'completed',
            ]);
        }

        $response = $this->actingAs($admin)->get(route('dashboard', [
            'hub_id' => $hub->id,
            'from' => '2026-09-01',
            'to' => '2026-09-30',
        ]));

        $response->assertOk()
            ->assertDontSee('Shopee &amp; Lazada Sales')
            ->assertDontSee('role="img" aria-label="Shopee and Lazada sales comparison', false)
            ->assertDontSee('marketplace-dual-chart', false);
    }

    public function test_dashboard_excludes_unpaid_wholesale_and_counts_partial_collection_only(): void
    {
        $hub = StoreHub::create(['name' => 'Wholesale Dashboard', 'code' => 'WDB', 'status' => 'active', 'is_head_office' => true]);
        $user = User::factory()->create(['role' => 'admin']);
        $product = Product::create(['store_hub_id' => $hub->id, 'item_id' => 'WHOLE-1', 'name' => 'Wholesale Product', 'stock' => 10, 'status' => 'active']);

        foreach ([
            ['UNPAID-1', 500, 'unpaid', 0, 'pending'],
            ['PARTIAL-1', 600, 'partial', 150, 'shipped'],
            ['PAID-1', 700, 'paid', 700, 'delivered'],
        ] as [$number, $total, $paymentStatus, $amountPaid, $deliveryStatus]) {
            $sale = SalesTransaction::create([
                'user_id' => $user->id, 'store_hub_id' => $hub->id, 'customer_name' => 'Wholesale Customer',
                'channel_type' => 'wholesale', 'order_number' => $number, 'order_date' => '2026-09-18',
                'grand_total' => $total, 'status' => 'confirmed', 'payment_status' => $paymentStatus,
                'amount_paid' => $amountPaid, 'mode_of_payment' => 'Cash',
                'delivery_status' => $deliveryStatus,
            ]);
            $sale->items()->create(['product_id' => $product->id, 'quantity' => 1, 'unit_price' => $total, 'line_total' => $total]);
        }

        $response = $this->actingAs($user)->get(route('dashboard', ['hub_id' => $hub->id, 'date' => '2026-09-18']));
        $response->assertOk();
        $this->assertEquals(850, $response->viewData('salesTotals')['Daily']);
        $this->assertEquals(850, $response->viewData('matrix')->first()['amounts']['Cash']);
        $wholesale = $response->viewData('channelSummaries')->firstWhere('key', 'wholesale');
        $this->assertSame(2, (int) $wholesale['transactions']);
        $this->assertSame(1, (int) $wholesale['completed_transactions']);
        $this->assertSame(1, (int) $wholesale['partial_transactions']);
        $this->assertEquals(850, $wholesale['total']);
        $this->assertSame(2, $response->viewData('topProducts')->first()['units']);

        $wholesaleStaff = User::factory()->create([
            'role' => 'sales_marketing_staff',
            'hub_id' => $hub->id,
            'sales_channels' => ['wholesale'],
        ]);
        $wholesaleDashboard = $this->actingAs($wholesaleStaff)
            ->get(route('dashboard', ['hub_id' => $hub->id, 'date' => '2026-09-18']))
            ->assertOk()
            ->assertSee('<h3 class="mb-1">Sales Channels</h3>', false)
            ->assertSee('Wholesale Sales Overview')
            ->assertSee('Total collected')
            ->assertSee('850.00')
            ->assertDontSee('Branch Transaction Matrix')
            ->assertDontSee('TikTok Settlement Overview');
    }

    public function test_dashboard_uses_final_tiktok_payouts_and_excludes_fully_returned_or_awaiting_orders(): void
    {
        $hub = StoreHub::create(['name' => 'TikTok Dashboard', 'code' => 'TTD', 'status' => 'active', 'is_head_office' => true]);
        $user = User::factory()->create(['role' => 'admin']);
        $product = Product::create(['store_hub_id' => $hub->id, 'item_id' => 'TT-1', 'name' => 'TikTok Product', 'stock' => 10, 'status' => 'active']);

        $makeSale = function (string $number, ?float $payout, int $quantity, int $returned, ?float $recalculated = null) use ($hub, $user, $product) {
            $sale = SalesTransaction::create([
                'user_id' => $user->id, 'store_hub_id' => $hub->id, 'customer_name' => 'TikTok Customer',
                'channel_type' => 'tiktok', 'order_number' => $number, 'order_date' => '2026-09-28',
                'grand_total' => 999, 'status' => 'confirmed', 'mode_of_payment' => 'GCASH',
                'sales_after_transaction_fee' => $payout,
                'tiktok_recalculated_payout' => $recalculated,
                'tiktok_recalculated_payout_entered' => $recalculated !== null,
            ]);
            $sale->items()->create([
                'product_id' => $product->id, 'quantity' => $quantity, 'returned_quantity' => $returned,
                'unit_price' => 100, 'line_total' => 100 * $quantity,
            ]);

            return $sale;
        };

        $makeSale('TT-NORMAL', 100, 1, 0);
        $makeSale('TT-PARTIAL', 90, 2, 1, 60);
        $makeSale('TT-RETURNED', 80, 1, 1);
        $makeSale('TT-AWAITING', null, 1, 0);

        $response = $this->actingAs($user)->get(route('dashboard', ['hub_id' => $hub->id, 'date' => '2026-09-28']));
        $response->assertOk();

        foreach (['Daily', 'Weekly', 'Monthly', 'Quarterly', 'Yearly'] as $period) {
            $this->assertEquals(160, $response->viewData('salesTotals')[$period]);
        }
        $tiktok = $response->viewData('channelSummaries')->firstWhere('key', 'tiktok');
        $this->assertSame(2, (int) $tiktok['transactions']);
        $this->assertEquals(160, $tiktok['total']);
        $this->assertEquals(0, $response->viewData('matrix')->first()['amounts']['GCash']);
        $this->assertSame(2, $response->viewData('topProducts')->first()['units']);
        $this->assertSame(4, $response->viewData('tiktokOverview')['orders']);
        $this->assertSame(2, $response->viewData('tiktokOverview')['payout_entered']);
        $this->assertSame(1, $response->viewData('tiktokOverview')['recalculated']);
        $this->assertSame(1, $response->viewData('tiktokOverview')['awaiting']);
        $this->assertSame(1, $response->viewData('tiktokOverview')['fully_returned']);
        $this->assertEquals(160, $response->viewData('tiktokOverview')['final_payout']);

        $tiktokStaff = User::factory()->create([
            'role' => 'sales_marketing_staff',
            'hub_id' => $hub->id,
            'sales_channels' => ['tiktok'],
        ]);
        $tiktokDashboard = $this->actingAs($tiktokStaff)
            ->get(route('dashboard', ['hub_id' => $hub->id, 'date' => '2026-09-28']))
            ->assertOk()
            ->assertSee('TikTok Settlement Overview')
            ->assertSee('<h3 class="mb-1">Sales Channels</h3>', false)
            ->assertSee('row g-4 align-items-stretch mb-4', false)
            ->assertSee('col-xl-6', false)
            ->assertDontSee('Head Office channels and store walk-in sales')
            ->assertDontSee('Selected date range · Entered and recalculated TikTok payouts.')
            ->assertDontSee('Sales data is shown for the selected date range.')
            ->assertDontSee('Confirmed sales from')
            ->assertDontSee('Sales by reporting period')
            ->assertDontSee('Preview / Save PNG')
            ->assertDontSee('Branch Transaction Matrix');
        $tiktokDashboard->assertSee('TikTok Product')
            ->assertDontSee('marketplace-dual-chart', false);
        $this->assertSame(1, substr_count($tiktokDashboard->getContent(), 'TikTok Settlement Overview'));
    }
}
