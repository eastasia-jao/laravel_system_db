<?php

namespace Tests\Feature;

use App\Models\FullyBookedOrder;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\ProductStockAllocation;
use App\Models\SalesTransaction;
use App\Models\StoreHub;
use App\Models\TransactionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FullyBookedOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_page_offers_fully_booked_and_only_lists_designated_staff(): void
    {
        $hub = StoreHub::create([
            'name' => 'Head Office', 'code' => 'HO', 'status' => 'active', 'is_head_office' => true,
        ]);
        $designated = User::factory()->create([
            'name' => 'Designated Staff',
            'role' => 'sales_marketing_staff',
            'hub_id' => $hub->id,
            'sales_channels' => ['fully_booked'],
        ]);
        $notDesignated = User::factory()->create([
            'name' => 'Other Staff',
            'role' => 'sales_marketing_staff',
            'hub_id' => $hub->id,
            'sales_channels' => ['online'],
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('inventory-transactions.sponsor.create', ['hub_id' => $hub->id]))
            ->assertOk()
            ->assertSee('Event / Fully Booked')
            ->assertSee('<div id="activityHeadingLabel" class="small text-uppercase fw-bold mb-1" style="color:#7c3aed">Event / Fully Booked</div>', false)
            ->assertSee('<h3 id="activityHeadingTitle" class="fw-bold mb-1">Event</h3>', false)
            ->assertSee('value="event"', false)
            ->assertSee('value="fully_booked"', false)
            ->assertDontSee('id="fullyBookedAttachment"', false);

        $this->actingAs($designated)
            ->get(route('inventory-transactions.sponsor.create', ['hub_id' => $hub->id]))
            ->assertOk()
            ->assertSee('<div id="activityHeadingLabel" class="small text-uppercase fw-bold mb-1" style="color:#7c3aed">Event / Fully Booked</div>', false)
            ->assertSee('<h3 id="activityHeadingTitle" class="fw-bold mb-1">Fully Booked</h3>', false)
            ->assertSee('type="hidden" id="activityType" name="activity_type" value="fully_booked"', false)
            ->assertDontSee('<select id="activityType"', false)
            ->assertDontSee('value="event"', false)
            ->assertDontSee('id="sponsorItems"', false)
            ->assertSee('id="fullyBookedAttachment"', false)
            ->assertSee('id="fullyBookedStore"', false)
            ->assertSee('Other')
            ->assertSee('value="Designated Staff — HEAD OFFICE"', false)
            ->assertDontSee('name="sales_staff_id"', false)
            ->assertSee('Generated automatically when submitted')
            ->assertSee('href="'.route('hub.dashboard', $hub->id).'" class="btn btn-outline-secondary">Cancel</a>', false);
        $this->actingAs($admin)->post(route('fully-booked-orders.store'), [
            'sales_staff_id' => $designated->id,
            'attachment' => $this->fakePdf(),
        ])->assertForbidden();

        $this->actingAs($designated)->get(route('hub.dashboard', $hub->id))
            ->assertOk()
            ->assertSee('Fully Booked Order')
            ->assertSeeInOrder([
                '<span class="hub-action-label">Fully Booked Order</span>',
                '<span class="hub-action-label">Fully Booked Returns</span>',
                '<span class="hub-action-label">Rejected Fully Booked</span>',
                '<span class="hub-action-label">Inventory</span>',
            ], false)
            ->assertDontSee('My Fully Booked Orders')
            ->assertDontSee('Fully Booked Orders')
            ->assertDontSee(route('inventory-transactions.fully-booked.index'), false)
            ->assertDontSee('Transaction Logs');
        $this->get(route('inventory-transactions.index'))->assertForbidden();
        $this->get(route('inventory-transactions.fully-booked.index'))->assertForbidden();

        $this->actingAs($notDesignated)
            ->get(route('inventory-transactions.sponsor.create', ['hub_id' => $hub->id]))
            ->assertForbidden();
    }

    public function test_fully_booked_orders_are_accessed_from_transaction_logs_not_sidebar(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('inventory-transactions.index'))
            ->assertOk()
            ->assertDontSee('Review attachments')
            ->assertDontSee('href="'.route('inventory-transactions.fully-booked.index').'" class="card', false);
    }

    public function test_transaction_log_event_fully_booked_shortcut_shows_reviewed_orders_awaiting_pullout(): void
    {
        $hub = StoreHub::create([
            'name' => 'Head Office', 'code' => 'HO', 'status' => 'active', 'is_head_office' => true,
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'sales_marketing_staff', 'hub_id' => $hub->id]);

        foreach ([
            ['number' => 'FB-BADGE-REVIEWED', 'status' => 'reviewed', 'pulled_out_at' => null],
            ['number' => 'FB-BADGE-PENDING', 'status' => 'pending', 'pulled_out_at' => null],
            ['number' => 'FB-BADGE-COMPLETE', 'status' => 'reviewed', 'pulled_out_at' => now()],
        ] as $orderData) {
            FullyBookedOrder::create([
                'order_number' => $orderData['number'],
                'store_hub_id' => $hub->id,
                'sales_staff_id' => $staff->id,
                'submitted_by' => $staff->id,
                'attachment_path' => 'fully-booked-orders/order.pdf',
                'original_filename' => 'order.pdf',
                'mime_type' => 'application/pdf',
                'status' => $orderData['status'],
                'pulled_out_at' => $orderData['pulled_out_at'],
            ]);
        }

        $this->actingAs($admin)
            ->get(route('inventory-transactions.index', ['hub_id' => $hub->id]))
            ->assertOk()
            ->assertSee('1 Fully Booked order awaiting pull-out')
            ->assertViewHas('fullyBookedPulloutCount', 1);
    }

    public function test_sales_staff_fully_booked_order_tracking_is_paginated_six_per_page(): void
    {
        $hub = StoreHub::create([
            'name' => 'Head Office', 'code' => 'HO', 'status' => 'active', 'is_head_office' => true,
        ]);
        $staff = User::factory()->create([
            'role' => 'sales_marketing_staff',
            'hub_id' => $hub->id,
            'sales_channels' => ['fully_booked'],
        ]);

        foreach (range(1, 7) as $number) {
            FullyBookedOrder::create([
                'order_number' => 'FB-PAGE-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
                'store_hub_id' => $hub->id,
                'sales_staff_id' => $staff->id,
                'submitted_by' => $staff->id,
                'attachment_path' => 'fully-booked-orders/order.pdf',
                'original_filename' => 'order.pdf',
                'mime_type' => 'application/pdf',
                'status' => 'pending',
            ]);
        }

        $this->actingAs($staff)
            ->get(route('inventory-transactions.sponsor.create', [
                'hub_id' => $hub->id,
                'activity_type' => 'fully_booked',
            ]))
            ->assertOk()
            ->assertViewHas('fullyBookedOrders', fn ($orders) => $orders->total() === 7 && $orders->perPage() === 6)
            ->assertSee('Showing 1–6 of 7 submission(s)')
            ->assertSee('FB-PAGE-007')
            ->assertSee('FB-PAGE-002')
            ->assertDontSee('FB-PAGE-001')
            ->assertSee('page=2');

        $this->get(route('inventory-transactions.sponsor.create', [
            'hub_id' => $hub->id,
            'activity_type' => 'fully_booked',
            'page' => 2,
        ]))
            ->assertOk()
            ->assertSee('Showing 7–7 of 7 submission(s)')
            ->assertSee('FB-PAGE-001')
            ->assertDontSee('FB-PAGE-002');
    }

    public function test_inventory_staff_selecting_fully_booked_can_preview_and_download_pending_attachments(): void
    {
        Storage::fake('local');
        $hub = StoreHub::create([
            'name' => 'Head Office', 'code' => 'HO', 'status' => 'active', 'is_head_office' => true,
        ]);
        $staff = User::factory()->create([
            'name' => 'Fully Booked Staff',
            'role' => 'sales_marketing_staff',
            'hub_id' => $hub->id,
            'sales_channels' => ['fully_booked'],
        ]);
        $inventoryStaff = User::factory()->create(['role' => 'inventory_staff', 'hub_id' => $hub->id]);
        $path = $this->fakePdf('order-attachment.pdf')->store('fully-booked-orders', 'local');
        $order = FullyBookedOrder::create([
            'order_number' => 'FB-FORM-001',
            'store_hub_id' => $hub->id,
            'sales_staff_id' => $staff->id,
            'submitted_by' => $staff->id,
            'attachment_path' => $path,
            'original_filename' => 'order-attachment.pdf',
            'mime_type' => 'application/pdf',
            'remarks' => 'Please pull these items carefully.',
            'status' => 'pending',
        ]);

        $this->actingAs($inventoryStaff)
            ->get(route('inventory-transactions.sponsor.create', [
                'hub_id' => $hub->id,
                'activity_type' => 'fully_booked',
            ]))
            ->assertOk()
            ->assertSee('<div id="activityHeadingLabel" class="small text-uppercase fw-bold mb-1" style="color:#7c3aed">Event / Fully Booked</div>', false)
            ->assertSee('<h3 id="activityHeadingTitle" class="fw-bold mb-1">Fully Booked</h3>', false)
            ->assertSee('type="hidden" id="activityType" name="activity_type" value="fully_booked"', false)
            ->assertDontSee('<select id="activityType"', false)
            ->assertSee('Type product name, item code, or barcode')
            ->assertSee('role="listbox"', false)
            ->assertDontSee('<datalist id="fullyBookedProducts', false)
            ->assertSee('<div class="alert alert-light border small mb-0 fully-booked-request-remarks">', false)
            ->assertSee('.fully-booked-pullout-rows', false)
            ->assertSee('Orders Awaiting Inventory Pull-Out')
            ->assertSee('FB-FORM-001')
            ->assertSee('Request remarks:')
            ->assertSee('Please pull these items carefully.')
            ->assertSee('Preview Attachment')
            ->assertSee('Download')
            ->assertSee(route('inventory-transactions.fully-booked.attachment', $order), false)
            ->assertSee(route('inventory-transactions.fully-booked.pull-out', $order), false)
            ->assertSee(route('inventory-transactions.index', ['hub_id' => $hub->id]), false)
            ->assertSee('id="fullyBookedPageCancel"', false)
            ->assertSee('Complete Order & Update Stock', false)
            ->assertSee('Export CSV')
            ->assertSee('name="items[0][product_id]"', false)
            ->assertDontSee('id="fullyBookedAttachment"', false);

        $csvScript = file_get_contents(public_path('js/fully-booked-pullout.js'));
        $this->assertIsString($csvScript);
        $this->assertStringContainsString("'Item Id', 'Description', 'Barcode', 'Physical Stock Remaining', 'Actual Pull Out', 'Actual Physical Pullout'", $csvScript);
    }

    public function test_fully_booked_product_search_returns_matching_active_store_products(): void
    {
        $hub = StoreHub::create([
            'name' => 'Head Office', 'code' => 'HO', 'status' => 'active', 'is_head_office' => true,
        ]);
        $staff = User::factory()->create(['role' => 'inventory_staff', 'hub_id' => $hub->id]);
        $matchingProduct = Product::create([
            'store_hub_id' => $hub->id,
            'item_id' => 'CAD-001',
            'name' => 'Cadmium Red',
            'barcode' => '123456789',
            'stock' => 15,
            'status' => 'active',
        ]);
        Product::create([
            'store_hub_id' => $hub->id,
            'item_id' => 'BLUE-001',
            'name' => 'Cobalt Blue',
            'stock' => 7,
            'status' => 'inactive',
        ]);

        $this->actingAs($staff)
            ->getJson(route('hub.products.search.ajax', [
                'hubId' => $hub->id,
                'q' => 'cadmium',
                'active_only' => 1,
                'sort' => 'item_id',
            ]))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $matchingProduct->id)
            ->assertJsonPath('0.name', 'Cadmium Red')
            ->assertJsonPath('0.barcode', '123456789');
    }

    public function test_fully_booked_pull_out_reduces_online_allocation_and_physical_stock(): void
    {
        Storage::fake('local');
        $hub = StoreHub::create([
            'name' => 'Head Office', 'code' => 'HO', 'status' => 'active', 'is_head_office' => true,
        ]);
        $staff = User::factory()->create([
            'role' => 'sales_marketing_staff',
            'hub_id' => $hub->id,
            'sales_channels' => ['fully_booked'],
        ]);
        $inventoryStaff = User::factory()->create(['role' => 'inventory_staff', 'hub_id' => $hub->id]);
        $product = Product::create([
            'store_hub_id' => $hub->id,
            'item_id' => 'FB-ALLOC-1',
            'name' => 'Fully Booked Allocated Product',
            'stock' => 20,
            'status' => 'active',
        ]);
        ProductStockAllocation::create([
            'product_id' => $product->id,
            'online' => 8,
            'wholesale' => 2,
            'shopee' => 1,
            'lazada' => 1,
            'tiktok' => 1,
        ]);
        $order = FullyBookedOrder::create([
            'order_number' => 'FB-ALLOC-001',
            'store_hub_id' => $hub->id,
            'store_name' => 'Head Office',
            'sales_staff_id' => $staff->id,
            'submitted_by' => $staff->id,
            'attachment_path' => 'fully-booked-orders/order.pdf',
            'original_filename' => 'order.pdf',
            'mime_type' => 'application/pdf',
            'status' => 'pending',
        ]);

        $this->actingAs($inventoryStaff)->post(route('inventory-transactions.fully-booked.pull-out', $order), [
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
        ])->assertRedirect();

        $this->assertSame(17, (int) $product->fresh()->stock);
        $this->assertSame(5, (int) $product->stockAllocation()->value('online'));
        $listedProduct = $this->get(route('stock-allocation.index', ['hub_id' => $hub->id]))
            ->assertOk()
            ->viewData('products')
            ->getCollection()
            ->firstWhere('id', $product->id);
        $this->assertSame(5, $listedProduct->allocation_values['online']);
        $this->assertSame(3, $listedProduct->sold_values->get('online'));
    }

    public function test_fully_booked_return_log_displays_its_source_attachment(): void
    {
        Storage::fake('local');
        $hub = StoreHub::create([
            'name' => 'Fully Booked Return Hub',
            'code' => 'FB-RETURN',
            'status' => 'active',
            'is_head_office' => true,
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create([
            'role' => 'sales_marketing_staff',
            'hub_id' => $hub->id,
            'sales_channels' => ['fully_booked'],
        ]);
        $path = $this->fakePdf('fully-booked-return.pdf')->store('fully-booked-orders', 'local');
        $order = FullyBookedOrder::create([
            'order_number' => 'FB-RETURN-001',
            'store_hub_id' => $hub->id,
            'sales_staff_id' => $staff->id,
            'submitted_by' => $staff->id,
            'attachment_path' => $path,
            'original_filename' => 'fully-booked-return.pdf',
            'mime_type' => 'application/pdf',
            'status' => 'reviewed',
        ]);
        $sale = SalesTransaction::create([
            'store_hub_id' => $hub->id,
            'user_id' => $staff->id,
            'channel_type' => 'fully_booked',
            'status' => 'confirmed',
            'order_date' => '2026-09-30',
            'order_number' => $order->order_number,
            'customer_name' => 'Fully Booked Customer',
        ]);
        $product = Product::create([
            'name' => 'Returned Fully Booked Product',
            'item_id' => 'FB-RETURN-ITEM',
            'store_hub_id' => $hub->id,
            'stock' => 2,
            'status' => 'active',
        ]);
        InventoryTransaction::create([
            'reference' => $order->order_number,
            'type' => 'return',
            'store_hub_id' => $hub->id,
            'product_id' => $product->id,
            'channel' => 'fully_booked',
            'quantity' => 1,
            'condition' => 'good',
            'occurred_on' => '2026-10-01',
            'created_by' => $admin->id,
            'sales_transaction_id' => $sale->id,
        ]);

        $this->actingAs($admin)
            ->get(route('inventory-transactions.index', ['hub_id' => $hub->id, 'type' => 'return']))
            ->assertOk()
            ->assertSee('Return order '.$order->order_number)
            ->assertSee('Fully Booked attachment')
            ->assertSee(route('inventory-transactions.fully-booked.attachment', $order), false)
            ->assertSee('fully-booked-return.pdf')
            ->assertSee('class="fully-booked-pdf"', false);
    }

    public function test_fully_booked_returns_are_visible_to_designated_staff_and_return_notification_opens_them(): void
    {
        $hub = StoreHub::create([
            'name' => 'Fully Booked Returns Hub',
            'code' => 'FB-RETURNS',
            'status' => 'active',
            'is_head_office' => true,
        ]);
        $staff = User::factory()->create([
            'role' => 'sales_marketing_staff',
            'hub_id' => $hub->id,
            'sales_channels' => ['fully_booked'],
        ]);
        $inventoryStaff = User::factory()->create(['role' => 'inventory_staff']);
        $otherStaff = User::factory()->create([
            'role' => 'sales_marketing_staff',
            'hub_id' => $hub->id,
            'sales_channels' => ['online'],
        ]);
        $sale = SalesTransaction::create([
            'store_hub_id' => $hub->id,
            'user_id' => $staff->id,
            'channel_type' => 'fully_booked',
            'status' => 'confirmed',
            'order_date' => '2026-10-01',
            'order_number' => 'FB-RETURN-VIEW-001',
            'customer_name' => 'Fully Booked Customer',
        ]);
        $product = Product::create([
            'name' => 'Returned Fully Booked Product',
            'item_id' => 'FB-RETURN-VIEW-ITEM',
            'store_hub_id' => $hub->id,
            'stock' => 4,
            'status' => 'active',
        ]);
        $item = TransactionItem::create([
            'transaction_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 25,
            'discount_percentage' => 0,
            'line_total' => 50,
        ]);
        InventoryTransaction::create([
            'reference' => $sale->order_number,
            'type' => 'return',
            'store_hub_id' => $hub->id,
            'product_id' => $product->id,
            'transaction_item_id' => $item->id,
            'sales_transaction_id' => $sale->id,
            'channel' => 'fully_booked',
            'quantity' => 1,
            'condition' => 'good',
            'occurred_on' => '2026-10-02',
            'notes' => 'Keep packaging intact',
            'created_by' => $inventoryStaff->id,
        ]);

        $returnsUrl = route('hub.fully-booked-returns', ['hub' => $hub->id]);
        $this->actingAs($staff)
            ->get($returnsUrl)
            ->assertOk()
            ->assertSee('Fully Booked Returns')
            ->assertSee('FB-RETURN-VIEW-001')
            ->assertSee('Returned Fully Booked Product')
            ->assertSee('Good: 1')
            ->assertSee('Keep packaging intact')
            ->assertDontSee('>Clear</a>', false)
            ->assertSeeInOrder([
                '<th>Condition summary</th>',
                '<th>Notes</th>',
                '<th class="text-end pe-3">Action</th>',
            ], false)
            ->assertViewHas('transactions', fn ($transactions) => $transactions->total() === 1);

        $this->get(route('hub.dashboard', $hub->id))
            ->assertOk()
            ->assertSee('Fully Booked Returns')
            ->assertSee($returnsUrl, false);
        $this->actingAs($otherStaff)->get($returnsUrl)->assertForbidden();

        $this->actingAs($inventoryStaff)->post(route('inventory-transactions.return.store'), [
            'type' => 'return',
            'store_hub_id' => $hub->id,
            'occurred_on' => '2026-10-03',
            'channel' => 'fully_booked',
            'sales_transaction_id' => $sale->id,
            'reference' => $sale->order_number,
            'items' => [[
                'product_id' => $product->id,
                'transaction_item_id' => $item->id,
                'good_quantity' => 0,
                'damaged_quantity' => 1,
            ]],
        ])->assertRedirect();

        $notification = $staff->notifications()->where('data->event', 'return_recorded')->sole();
        $this->assertSame($returnsUrl, $notification->data['url']);
        $this->assertStringContainsString('Fully Booked Returns', $notification->data['message']);

        $legacyNotification = $staff->notifications()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => \App\Notifications\InventoryWorkflowNotification::class,
            'data' => [
                'event' => 'return_recorded',
                'channel' => 'fully_booked',
                'hub_id' => $hub->id,
                'url' => route('hub.report', ['hub' => $hub->id, 'channel' => 'fully_booked']),
            ],
        ]);
        $this->actingAs($staff)->get(route('notifications.read', $legacyNotification->id))
            ->assertRedirect($returnsUrl);
    }

    public function test_inventory_staff_can_reject_fully_booked_order_with_reason_and_notify_submitter(): void
    {
        Storage::fake('local');
        $hub = StoreHub::create([
            'name' => 'Head Office',
            'code' => 'HO',
            'status' => 'active',
            'is_head_office' => true,
        ]);
        $staff = User::factory()->create([
            'role' => 'sales_marketing_staff',
            'hub_id' => $hub->id,
            'sales_channels' => ['fully_booked'],
        ]);
        $inventoryStaff = User::factory()->create(['role' => 'inventory_staff']);
        $otherStaff = User::factory()->create([
            'role' => 'sales_marketing_staff',
            'hub_id' => $hub->id,
            'sales_channels' => ['fully_booked'],
        ]);
        $path = $this->fakePdf('rejection-order.pdf')->store('fully-booked-orders', 'local');
        $order = FullyBookedOrder::create([
            'order_number' => 'FB-REJECT-001',
            'store_hub_id' => $hub->id,
            'sales_staff_id' => $staff->id,
            'submitted_by' => $staff->id,
            'attachment_path' => $path,
            'original_filename' => 'rejection-order.pdf',
            'mime_type' => 'application/pdf',
            'status' => 'reviewed',
        ]);

        $pullOutPage = $this->actingAs($inventoryStaff)
            ->get(route('inventory-transactions.sponsor.create', [
                'hub_id' => $hub->id,
                'activity_type' => 'fully_booked',
            ]));
        $pullOutPage->assertOk()
            ->assertSee('Reject Order')
            ->assertSee('name="rejection_reason"', false)
            ->assertSee(route('inventory-transactions.fully-booked.reject', $order), false);

        $this->patch(route('inventory-transactions.fully-booked.reject', $order), [])
            ->assertSessionHasErrors('rejection_reason');
        $this->patch(route('inventory-transactions.fully-booked.reject', $order), [
            'rejection_reason' => 'The attachment is missing the requested quantities.',
        ])->assertRedirect(route('inventory-transactions.sponsor.create', [
            'hub_id' => $hub->id,
            'activity_type' => 'fully_booked',
        ]))->assertSessionHas('success');

        $this->assertDatabaseHas('fully_booked_orders', [
            'id' => $order->id,
            'status' => 'rejected',
            'reviewed_by' => $inventoryStaff->id,
            'rejection_reason' => 'The attachment is missing the requested quantities.',
            'pulled_out_at' => null,
        ]);
        $this->get(route('inventory-transactions.sponsor.create', [
            'hub_id' => $hub->id,
            'activity_type' => 'fully_booked',
        ]))->assertOk()->assertViewHas('fullyBookedOrders', fn ($orders) => ! $orders->contains('id', $order->id));
        $this->actingAs($inventoryStaff)
            ->get(route('inventory-transactions.index', [
                'hub_id' => $hub->id,
                'type' => 'fully_booked',
                'status' => 'rejected',
            ]))
            ->assertOk()
            ->assertSee('FB-REJECT-001')
            ->assertSee('REJECTED')
            ->assertSee('The attachment is missing the requested quantities.');

        $rejectedUrl = route('hub.fully-booked-rejected', ['hub' => $hub->id]);
        $notification = $staff->notifications()->where('data->event', 'fully_booked_rejected')->sole();
        $this->assertSame($rejectedUrl, $notification->data['url']);
        $this->assertSame('FB-REJECT-001', $notification->data['reference']);
        $this->assertStringContainsString('The attachment is missing the requested quantities.', $notification->data['message']);

        $this->actingAs($staff)->get($rejectedUrl)
            ->assertOk()
            ->assertSee('FB-REJECT-001')
            ->assertSee('The attachment is missing the requested quantities.')
            ->assertSee(route('inventory-transactions.fully-booked.attachment', $order), false);
        $this->get(route('inventory-transactions.sponsor.create', [
            'hub_id' => $hub->id,
            'activity_type' => 'fully_booked',
        ]))
            ->assertOk()
            ->assertSee('Rejected')
            ->assertSee('Rejected by')
            ->assertSee('The attachment is missing the requested quantities.');
        $this->get(route('hub.dashboard', $hub->id))
            ->assertOk()
            ->assertSeeInOrder([
                '<span class="hub-action-label">Fully Booked Returns</span>',
                '<span class="hub-action-label">Rejected Fully Booked</span>',
                '<span class="hub-action-label">Inventory</span>',
            ], false);
        $this->actingAs($otherStaff)->get($rejectedUrl)
            ->assertOk()
            ->assertDontSee('FB-REJECT-001')
            ->assertSee('You do not have any rejected Fully Booked orders.');
        $this->actingAs($staff)->get(route('notifications.read', $notification->id))
            ->assertRedirect($rejectedUrl);
        $this->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('fa-circle-xmark');
    }

    public function test_other_fully_booked_store_requires_and_saves_a_specified_name(): void
    {
        Storage::fake('local');
        $hub = StoreHub::create([
            'name' => 'Head Office', 'code' => 'HO', 'status' => 'active', 'is_head_office' => true,
        ]);
        $staff = User::factory()->create([
            'role' => 'sales_marketing_staff',
            'hub_id' => $hub->id,
            'sales_channels' => ['fully_booked'],
        ]);

        $this->actingAs($staff)->post(route('fully-booked-orders.store'), [
            'store_selection' => 'other',
            'attachment' => $this->fakePdf(),
        ])->assertSessionHasErrors('other_store_name');

        $this->post(route('fully-booked-orders.store'), [
            'store_selection' => 'other',
            'other_store_name' => 'Downtown Bookshop',
            'attachment' => $this->fakePdf(),
        ])->assertSessionHasNoErrors();

        $this->assertSame('Downtown Bookshop', FullyBookedOrder::sole()->store_name);
    }

    public function test_designated_staff_can_submit_an_attachment_for_private_inventory_review(): void
    {
        Storage::fake('local');
        $hub = StoreHub::create([
            'name' => 'Head Office', 'code' => 'HO', 'status' => 'active', 'is_head_office' => true,
        ]);
        $staff = User::factory()->create([
            'name' => 'Fully Booked Staff',
            'role' => 'sales_marketing_staff',
            'hub_id' => $hub->id,
            'sales_channels' => ['fully_booked'],
        ]);
        $inventoryStaff = User::factory()->create(['role' => 'inventory_staff', 'hub_id' => $hub->id]);
        $admin = User::factory()->create(['role' => 'admin']);
        $product = Product::create([
            'name' => 'Unaffected Product',
            'item_id' => 'FB-1',
            'description' => 'Fully Booked product description',
            'barcode' => '00123456789012345678',
            'store_hub_id' => $hub->id,
            'stock' => 12,
            'status' => 'active',
        ]);

        $otherStaff = User::factory()->create([
            'role' => 'sales_marketing_staff',
            'hub_id' => $hub->id,
            'sales_channels' => ['fully_booked'],
        ]);
        $response = $this->actingAs($staff)->post(route('fully-booked-orders.store'), [
            'sales_staff_id' => $otherStaff->id,
            'store_selection' => (string) $hub->id,
            'attachment' => $this->fakePdf(),
            'remarks' => 'Please prioritize this order.',
        ])->assertSessionHasNoErrors();

        $order = FullyBookedOrder::sole();
        $orderNumber = 'FB-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT);
        $this->assertSame($orderNumber, $order->order_number);
        Storage::disk('local')->assertExists($order->attachment_path);
        $this->assertSame($staff->id, $order->sales_staff_id);
        $this->assertSame($staff->id, $order->submitted_by);
        $this->assertSame('HEAD OFFICE', $order->store_name);
        $this->assertSame('Please prioritize this order.', $order->remarks);
        $this->assertSame('pending', $order->status);
        $this->assertSame(12, $product->fresh()->stock);
        $this->assertSame(1, $inventoryStaff->notifications()->count());
        $this->assertSame(1, $admin->notifications()->count());
        $this->assertSame(
            route('inventory-transactions.sponsor.create', [
                'hub_id' => $hub->id,
                'activity_type' => 'fully_booked',
            ]),
            $inventoryStaff->notifications()->first()->data['url']
        );
        $response->assertRedirect(route('inventory-transactions.sponsor.create', [
            'hub_id' => $hub->id,
            'activity_type' => 'fully_booked',
        ]));
        $response->assertSessionHas('success', 'Fully Booked order '.$orderNumber.' submitted for review.');
        $this->actingAs($staff)->get(route('inventory-transactions.sponsor.create', [
            'hub_id' => $hub->id,
            'activity_type' => 'fully_booked',
        ]))->assertOk()
            ->assertSee('id="fullyBookedRemarks"', false)
            ->assertSee('My Fully Booked Orders')
            ->assertSee('Pending inventory pull-out')
            ->assertSee('Store: HEAD OFFICE')
            ->assertSee('Please prioritize this order.')
            ->assertSee($orderNumber);

        $previewUrl = route('inventory-transactions.fully-booked.attachment', $order);
        $this->actingAs($inventoryStaff)->get(route('inventory-transactions.index', [
            'type' => 'fully_booked',
        ]))->assertOk()
            ->assertViewHas('transactions', fn ($logs) => $logs->total() === 0)
            ->assertDontSee($previewUrl, false)
            ->assertDontSee('Please prioritize this order.')
            ->assertDontSee(route('inventory-transactions.fully-booked.pull-out', $order), false)
            ->assertDontSee('name="items[0][product_id]"', false)
            ->assertDontSee('Complete Order & Update Stock', false);
        $this->get(route('inventory-transactions.index'))
            ->assertOk()
            ->assertViewHas('transactions', fn ($logs) => $logs->total() === 0);
        $this->get(route('inventory-transactions.index', [
            'type' => 'fully_booked',
            'status' => 'reviewed',
        ]))->assertOk()
            ->assertViewHas('transactions', fn ($logs) => $logs->total() === 0);
        $this->get(route('inventory-transactions.fully-booked.index'))
            ->assertRedirect(route('inventory-transactions.index', ['type' => 'fully_booked']));
        $this->get($previewUrl)->assertOk()->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('inventory-transactions.fully-booked.attachment', [
            'fullyBookedOrder' => $order,
            'download' => 1,
        ]))->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="order.pdf"');

        $this->actingAs($inventoryStaff)->post(route('inventory-transactions.fully-booked.pull-out', $order), [
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
        ])->assertRedirect(route('inventory-transactions.sponsor.create', [
            'hub_id' => $hub->id,
            'activity_type' => 'fully_booked',
        ]))->assertSessionHas('success', 'Fully Booked order completed and stock updated.');
        $this->assertSame(9, $product->fresh()->stock);
        $this->assertDatabaseHas('fully_booked_order_items', [
            'fully_booked_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 3,
        ]);
        $this->assertDatabaseHas('inventory_transactions', [
            'reference' => $orderNumber,
            'type' => 'sponsor_workshop',
            'channel' => 'fully_booked',
            'quantity' => 3,
        ]);
        $fullyBookedSale = \App\Models\SalesTransaction::where('order_number', $orderNumber)->sole();
        $this->assertSame('fully_booked', $fullyBookedSale->channel_type);
        $this->assertDatabaseHas('transaction_items', [
            'transaction_id' => $fullyBookedSale->id,
            'product_id' => $product->id,
            'quantity' => 3,
        ]);
        $this->getJson(route('inventory-transactions.return.sales', [
            'hub_id' => $hub->id,
            'channel' => 'fully_booked',
            'date' => $fullyBookedSale->order_date->toDateString(),
        ]))
            ->assertOk()
            ->assertJsonPath('orders.0.id', $fullyBookedSale->id)
            ->assertJsonPath('orders.0.order_number', $orderNumber);
        $this->getJson(route('inventory-transactions.return.sales', [
            'hub_id' => $hub->id,
            'channel' => 'fully_booked',
            'date' => $fullyBookedSale->order_date->toDateString(),
            'transaction_id' => $fullyBookedSale->id,
        ]))
            ->assertOk()
            ->assertJsonPath('transaction.fully_booked_attachment.url', $previewUrl)
            ->assertJsonPath('transaction.fully_booked_attachment.file_name', $order->original_filename)
            ->assertJsonPath('transaction.fully_booked_attachment.mime_type', 'application/pdf');
        $this->get(route('inventory-transactions.index', ['type' => 'fully_booked']))
            ->assertOk()
            ->assertSee('Items Pulled Out')
            ->assertSee('export-fully-booked-details-csv', false)
            ->assertSee('data-barcode="00123456789012345678"', false)
            ->assertSee('data-stock="9"', false)
            ->assertSee('Unaffected Product')
            ->assertSee('Please prioritize this order.')
            ->assertDontSee('PENDING PULL-OUT')
            ->assertDontSee('Pending inventory pull-out');
        $this->assertSame(1, $staff->notifications()->where('data->event', 'fully_booked_completed')->count());
        $completionNotification = $staff->notifications()->where('data->event', 'fully_booked_completed')->first();
        $this->assertSame($orderNumber, $completionNotification->data['reference']);
        $this->assertSame(route('inventory-transactions.sponsor.create', [
            'hub_id' => $hub->id,
            'activity_type' => 'fully_booked',
        ]), $completionNotification->data['url']);
        $this->actingAs($staff)->get(route('inventory-transactions.sponsor.create', [
            'hub_id' => $hub->id,
            'activity_type' => 'fully_booked',
        ]))->assertOk()
            ->assertSee('Stock updated')
            ->assertSee($orderNumber)
            ->assertDontSee('Pending inventory pull-out');
        $this->actingAs($inventoryStaff)
            ->post(route('inventory-transactions.fully-booked.pull-out', $order), [
                'items' => [['product_id' => $product->id, 'quantity' => 3]],
            ])->assertStatus(409);

        $this->actingAs($inventoryStaff)->patch(route('inventory-transactions.fully-booked.review', $order))
            ->assertSessionHas('success', 'Fully Booked attachment marked as reviewed.');
        $this->assertDatabaseHas('fully_booked_orders', [
            'id' => $order->id,
            'status' => 'reviewed',
            'reviewed_by' => $inventoryStaff->id,
        ]);
        $this->assertSame(9, $product->fresh()->stock);
    }

    public function test_fully_booked_upload_rejects_staff_without_channel_designation(): void
    {
        Storage::fake('local');
        $hub = StoreHub::create([
            'name' => 'Head Office', 'code' => 'HO', 'status' => 'active', 'is_head_office' => true,
        ]);
        $authorizedStaff = User::factory()->create([
            'role' => 'sales_marketing_staff',
            'hub_id' => $hub->id,
            'sales_channels' => ['fully_booked'],
        ]);
        $unauthorizedStaff = User::factory()->create([
            'role' => 'sales_marketing_staff',
            'hub_id' => $hub->id,
            'sales_channels' => ['online'],
        ]);

        $this->actingAs($authorizedStaff)->post(route('fully-booked-orders.store'), [
            'sales_staff_id' => $unauthorizedStaff->id,
            'store_selection' => (string) $hub->id,
            'attachment' => $this->fakePdf(),
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('fully_booked_orders', [
            'sales_staff_id' => $authorizedStaff->id,
            'submitted_by' => $authorizedStaff->id,
        ]);

        $this->actingAs($unauthorizedStaff)->post(route('fully-booked-orders.store'), [
            'sales_staff_id' => $authorizedStaff->id,
            'attachment' => $this->fakePdf(),
        ])->assertForbidden();
        $this->assertDatabaseCount('fully_booked_orders', 1);
    }

    public function test_non_inventory_staff_cannot_review_or_preview_another_staff_attachment(): void
    {
        Storage::fake('local');
        $hub = StoreHub::create([
            'name' => 'Head Office', 'code' => 'HO', 'status' => 'active', 'is_head_office' => true,
        ]);
        $staff = User::factory()->create([
            'role' => 'sales_marketing_staff',
            'hub_id' => $hub->id,
            'sales_channels' => ['fully_booked'],
        ]);
        $otherStaff = User::factory()->create([
            'role' => 'sales_marketing_staff',
            'hub_id' => $hub->id,
            'sales_channels' => ['fully_booked'],
        ]);
        $path = $this->fakePdf('private.pdf')->store('fully-booked-orders', 'local');
        $order = FullyBookedOrder::create([
            'order_number' => 'FB-PRIVATE',
            'store_hub_id' => $hub->id,
            'sales_staff_id' => $otherStaff->id,
            'submitted_by' => $otherStaff->id,
            'attachment_path' => $path,
            'original_filename' => 'private.pdf',
            'mime_type' => 'application/pdf',
            'status' => 'pending',
        ]);

        $this->actingAs($staff)->get(route('inventory-transactions.fully-booked.attachment', $order))
            ->assertForbidden();
        $this->patch(route('inventory-transactions.fully-booked.review', $order))
            ->assertForbidden();
        $this->post(route('inventory-transactions.fully-booked.pull-out', $order))
            ->assertForbidden();
        $this->assertSame('pending', $order->fresh()->status);
    }

    private function fakePdf(string $name = 'order.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF"
        );
    }
}
