<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StaffActivityLog;
use App\Models\StoreHub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_logs_exclude_branch_transfers_for_inventory_staff_and_admin(): void
    {
        $inventoryStaff = User::factory()->create(['role' => 'inventory_staff']);

        $this->actingAs($inventoryStaff)
            ->get(route('inventory-transactions.index'))
            ->assertOk()
            ->assertDontSee('<option value="branch_transfer"', false)
            ->assertSee('BRANCH to BRANCH')
            ->assertSee('HO ↔ Branch');

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('inventory-transactions.index'))
            ->assertOk()
            ->assertDontSee('<option value="branch_transfer"', false)
            ->assertSee('BRANCH to BRANCH');
    }

    public function test_product_worksheet_exports_barcode_and_item_id_as_excel_safe_csv_text(): void
    {
        $hub = StoreHub::create(['name' => 'Barcode Branch', 'code' => 'BARCODE', 'status' => 'active']);
        Product::create([
            'name' => 'Barcode Product',
            'item_id' => '0011424',
            'barcode' => '3046450762881',
            'store_hub_id' => $hub->id,
            'stock' => 9,
            'status' => 'active',
        ]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $response = $this->get(route('inventory-transactions.product-worksheet', [
            'hub_id' => $hub->id,
            'type' => 'branch_transfer',
        ]))->assertOk();

        $this->assertStringStartsWith('text/csv', $response->headers->get('Content-Type'));
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $response->streamedContent());
        $rows = array_map('str_getcsv', preg_split('/\r\n|\n|\r/', trim($csv)));
        $this->assertSame('="3046450762881"', $rows[1][1]);
        $this->assertSame('="0011424"', $rows[1][2]);
    }

    public function test_branch_transfer_waits_for_inventory_review_before_moving_stock(): void
    {
        [$source, $target, $product, $receiving] = $this->fixtures();
        $submitter = User::factory()->create(['role' => 'sales_associate', 'hub_id' => $source->id]);
        $receivingAssociate = User::factory()->create(['role' => 'sales_associate', 'hub_id' => $target->id]);
        $additionalReceivingAssociates = collect(range(1, 11))->map(function ($index) use ($source, $target) {
            $associate = User::factory()->create([
                'role' => 'sales_associate',
                'hub_id' => $source->id,
                'name' => "Receiving Associate {$index}",
            ]);
            $associate->assignedStoreHubs()->attach($target->id);

            return $associate;
        });
        $this->actingAs($submitter);
        $reviewer = User::factory()->create(['role' => 'inventory_staff']);
        $form = $this->get(route('inventory-transactions.branch-transfer.create', ['hub_id' => $source->id]))
            ->assertOk()->assertSee('Stock Transfer (BRANCH to BRANCH)')
            ->assertSee('Submit for Approval')
            ->assertSee('Stock changes only after approval.')
            ->assertViewHas('transferSourceHubs', fn ($hubs) => $hubs->modelKeys() === [$source->id])
            ->assertViewHas('allHubs', fn ($hubs) => $hubs->count() === 2 && $hubs->every(fn ($hub) => ! $hub->is_head_office))
            ->assertViewHas('transferReference', fn ($reference) => preg_match('/^TRF-A-\d{8}-\d{6}-[A-F0-9]{6}$/', $reference) === 1)
            ->assertSee('name="reference"', false)
            ->assertSee('readonly', false);
        $reference = $form->viewData('transferReference');
        $this->post(route('inventory-transactions.branch-transfer.store'), array_merge($this->payload($source, $target, $product), ['reference' => $reference]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('hub.dashboard', [
                'id' => $source->id,
                'branch_transfer_submitted' => 1,
            ]));
        $this->get(route('hub.dashboard', [
            'id' => $source->id,
            'branch_transfer_submitted' => 1,
        ]))
            ->assertOk()
            ->assertSee('id="branch-transfer-success-popup"', false)
            ->assertSee('Request submitted')
            ->assertSee('awaiting inventory staff or admin approval');
        $this->assertEquals(10, $product->fresh()->stock);
        $this->assertEquals(2, $receiving->fresh()->stock);
        $transfer = \App\Models\InventoryTransaction::firstOrFail();
        $this->assertSame('pending', $transfer->status);
        $this->assertSame($reference, $transfer->reference);
        $this->assertNotNull($transfer->transfer_batch_id);
        $this->assertSame(1, $reviewer->notifications()->count());
        $this->assertSame(route('product-file-requests.index'), $reviewer->notifications()->first()->data['url']);
        $this->assertStringContainsString($reference, $reviewer->notifications()->first()->data['message']);
        $this->actingAs($reviewer)
            ->get(route('product-file-requests.index'))
            ->assertOk()
            ->assertSee('Stock Transfer Review Requests')
            ->assertSee($reference)
            ->assertSee('Transfer Test Product')
            ->assertSee('Approve and transfer stock');
        $this->get(route('inventory-transactions.branch-transfer.create'))
            ->assertOk()
            ->assertSee('Stock Transfer (BRANCH to BRANCH)');
        $this->get(route('inventory-transactions.index', ['hub_id' => $source->id, 'type' => 'branch_transfer', 'status' => 'pending']))
            ->assertOk()
            ->assertViewHas('transactions', fn ($transactions) => $transactions->total() === 0)
            ->assertDontSee('<option value="branch_transfer"', false);
        $this->post(route('inventory-transactions.branch-transfer.review', ['batch' => $transfer->transfer_batch_id]), ['decision' => 'approved'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertEquals(7, $product->fresh()->stock);
        $this->assertEquals(5, $receiving->fresh()->stock);
        $this->assertSame('approved', $transfer->fresh()->status);
        $this->assertSame($reviewer->id, $transfer->fresh()->reviewed_by);
        $transferLog = StaffActivityLog::where('action_type', 'branch_transfer_sent')->sole();
        $this->assertSame($submitter->id, $transferLog->user_id);
        $this->assertSame($source->id, $transferLog->store_hub_id);
        $this->assertSame($target->name, $transferLog->details['target_hub_name']);
        $expectedReceiverNames = collect([$receivingAssociate->name])->merge($additionalReceivingAssociates->pluck('name'))->sort()->values()->all();
        $this->assertSame($expectedReceiverNames, $transferLog->details['receiver_names']);
        $this->assertSame($reviewer->name, $transferLog->details['approved_by_name']);
        $this->assertDatabaseHas('staff_activity_log_items', [
            'staff_activity_log_id' => $transferLog->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'stock_before' => 10,
            'stock_after' => 7,
            'operation' => 'sent',
        ]);
        $this->actingAs($submitter)
            ->get(route('staff-logs.index'))
            ->assertOk()
            ->assertSee('Branch Transfer Logs')
            ->assertSee($reference)
            ->assertSee('Branch Transfer')
            ->assertSee($source->name)
            ->assertSee($target->name)
            ->assertDontSee('Receiver(s)')
            ->assertDontSee('12 sales associates')
            ->assertDontSee('Receiving Associate 11');
        $this->assertStringContainsString($submitter->name, $this->get(route('staff-logs.index'))->getContent());
        $this->get(route('staff-logs.index'))
            ->assertDontSee('name="action"', false)
            ->assertDontSee('name="hub_id"', false)
            ->assertSee('Sent By');
        $this->get(route('staff-logs.show', $transferLog))
            ->assertOk()
            ->assertSee('Sending Stock Before')
            ->assertSee('Sending Stock After')
            ->assertSee('Transfer Quantity');
        $sourceBranchColleague = User::factory()->create(['role' => 'sales_associate', 'hub_id' => $source->id]);
        $sourceBranchColleague->assignedStoreHubs()->attach($target->id);
        $this->actingAs($sourceBranchColleague)
            ->get(route('staff-logs.index'))
            ->assertOk()
            ->assertSee($reference)
            ->assertSee('name="hub_id"', false)
            ->assertDontSee('name="action"', false)
            ->assertSee($submitter->name)
            ->assertSee('name="user_id"', false);
        $this->actingAs($receivingAssociate)
            ->get(route('staff-logs.index'))
            ->assertOk()
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 0);
        $this->get(route('staff-logs.show', $transferLog))->assertForbidden();
        $this->actingAs($submitter)->get(route('inventory-transactions.index', ['type' => 'branch_transfer']))
            ->assertForbidden();
        $otherBranchAssociate = User::factory()->create(['role' => 'sales_associate', 'hub_id' => $target->id]);
        $this->actingAs($otherBranchAssociate)->get(route('staff-logs.index'))
            ->assertOk()
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 0);
        $this->assertDatabaseHas('inventory_transactions', [
            'type' => 'branch_transfer', 'source_hub_id' => $source->id,
            'target_hub_id' => $target->id, 'product_id' => $product->id, 'quantity' => 3,
        ]);
        $this->assertSame(1, $submitter->notifications()->count());
        $this->assertSame(route('staff-logs.index', ['search' => $reference]), $submitter->notifications()->first()->data['url']);
        $approvalNotification = $submitter->notifications()->first();
        $approvalNotification->forceFill([
            'data' => array_merge($approvalNotification->data, ['url' => route('hub.dashboard', $source->id)]),
        ])->save();
        $this->actingAs($submitter)
            ->get(route('notifications.read', $approvalNotification))
            ->assertRedirect(route('staff-logs.index', ['search' => $reference]));
        $this->assertSame(1, $receivingAssociate->notifications()->count());
        $this->assertStringContainsString($reference, $receivingAssociate->notifications()->first()->data['message']);
        $this->assertSame(route('hub.dashboard', $target->id), $receivingAssociate->notifications()->first()->data['url']);
        $this->actingAs($receivingAssociate)
            ->get(route('notifications.read', $receivingAssociate->notifications()->first()))
            ->assertRedirect(route('hub.dashboard', $target->id));
    }

    public function test_transfer_review_requests_are_paginated_ten_documents_per_page(): void
    {
        [$source, $target, $product] = $this->fixtures();
        $reviewer = User::factory()->create(['role' => 'inventory_staff']);
        foreach (range(1, 11) as $index) {
            \App\Models\InventoryTransaction::create([
                'reference' => sprintf('TRF-PAGE-%02d', $index),
                'transfer_batch_id' => sprintf('batch-page-%02d', $index),
                'type' => 'branch_transfer',
                'store_hub_id' => $source->id,
                'source_hub_id' => $source->id,
                'target_hub_id' => $target->id,
                'product_id' => $product->id,
                'quantity' => 1,
                'occurred_on' => '2026-09-08',
                'created_by' => $reviewer->id,
                'status' => 'pending',
            ]);
        }

        $firstPage = $this->actingAs($reviewer)->get(route('product-file-requests.index'));
        $firstPage->assertOk()
            ->assertSee('TRF-PAGE-11')
            ->assertSee('TRF-PAGE-02')
            ->assertSee('transfer_page=2', false);
        $this->assertSame(10, $firstPage->viewData('transferRequests')->count());
        $this->assertSame(11, $firstPage->viewData('transferRequests')->total());
        $this->assertSame(11, $firstPage->viewData('transferRequestCounts')['pending']);

        $secondPage = $this->get(route('product-file-requests.index', ['transfer_page' => 2]));
        $secondPage->assertOk()
            ->assertSee('TRF-PAGE-01')
            ->assertDontSee('TRF-PAGE-02');
        $this->assertSame(1, $secondPage->viewData('transferRequests')->count());
    }

    public function test_rejected_transfer_does_not_change_stock_and_submitter_cannot_approve_own_request(): void
    {
        [$source, $target, $product, $receiving] = $this->fixtures();
        $submitter = User::factory()->create(['role' => 'sales_associate', 'hub_id' => $source->id]);
        $this->actingAs($submitter);
        $payload = $this->payload($source, $target, $product);
        $this->post(route('inventory-transactions.branch-transfer.store'), $payload)->assertSessionHasNoErrors();
        $transfer = \App\Models\InventoryTransaction::firstOrFail();

        $this->post(route('inventory-transactions.branch-transfer.review', ['batch' => $transfer->transfer_batch_id]), ['decision' => 'approved'])
            ->assertForbidden();
        $staff = User::factory()->create(['role' => 'inventory_staff']);
        $this->actingAs($staff)->post(route('inventory-transactions.branch-transfer.review', ['batch' => $transfer->transfer_batch_id]), ['decision' => 'rejected'])
            ->assertSessionHasErrors('rejection_reason');
        $this->post(route('inventory-transactions.branch-transfer.review', ['batch' => $transfer->transfer_batch_id]), ['decision' => 'rejected', 'rejection_reason' => 'Incorrect quantities'])
            ->assertRedirect();

        $this->assertSame('rejected', $transfer->fresh()->status);
        $this->assertSame('Incorrect quantities', $transfer->fresh()->rejection_reason);
        $rejectedLog = StaffActivityLog::where('action_type', 'branch_transfer_sent')->sole();
        $this->assertSame('rejected', $rejectedLog->details['status']);
        $this->assertSame('Incorrect quantities', $rejectedLog->details['rejection_reason']);
        $this->assertSame(
            "Transfer Document {$transfer->reference} from {$source->name} to {$target->name}.",
            $rejectedLog->description
        );
        $this->assertSame($staff->name, $rejectedLog->details['reviewed_by_name']);
        $this->assertSame('inventory_staff', $rejectedLog->details['reviewed_by_role']);
        $this->actingAs($submitter)
            ->get(route('staff-logs.show', $rejectedLog))
            ->assertOk()
            ->assertSee('Rejection reason:')
            ->assertSee($staff->name)
            ->assertSee('Rejected by '.$staff->name)
            ->assertDontSee('Transfer rejected');
        $this->assertSame(route('staff-logs.index', ['search' => $transfer->reference]), $submitter->notifications()->first()->data['url']);
        $this->assertSame([$submitter->id], $submitter->notifications()->pluck('notifiable_id')->all());
        $this->actingAs($submitter)
            ->get(route('notifications.read', $submitter->notifications()->first()))
            ->assertRedirect(route('staff-logs.index', ['search' => $transfer->reference]));
        $this->get(route('staff-logs.index', ['search' => $transfer->reference]))
            ->assertOk()
            ->assertSee('REJECTED')
            ->assertSee("Transfer Document {$transfer->reference} from {$source->name} to {$target->name}.");
        $this->assertEquals(10, $product->fresh()->stock);
        $this->assertEquals(2, $receiving->fresh()->stock);
    }

    public function test_transfer_approval_rechecks_source_stock(): void
    {
        [$source, $target, $product, $receiving] = $this->fixtures();
        $this->post(route('inventory-transactions.branch-transfer.store'), $this->payload($source, $target, $product))->assertSessionHasNoErrors();
        $transfer = \App\Models\InventoryTransaction::firstOrFail();
        $product->update(['stock' => 1]);

        $this->actingAs(User::factory()->create(['role' => 'inventory_staff']))
            ->post(route('inventory-transactions.branch-transfer.review', ['batch' => $transfer->transfer_batch_id]), ['decision' => 'approved'])
            ->assertSessionHasErrors('decision');

        $this->assertSame('pending', $transfer->fresh()->status);
        $this->assertEquals(1, $product->fresh()->stock);
        $this->assertEquals(2, $receiving->fresh()->stock);
    }

    public function test_branch_transfer_logs_are_paginated_and_keep_search_filters(): void
    {
        [$source] = $this->fixtures();
        $associate = User::factory()->create(['role' => 'sales_associate', 'hub_id' => $source->id]);

        for ($index = 1; $index <= 12; $index++) {
            StaffActivityLog::create([
                'user_id' => $associate->id,
                'store_hub_id' => $source->id,
                'action_type' => 'branch_transfer_sent',
                'description' => "Transfer Document TRF-PAGE-{$index} sent from Branch A.",
                'details' => ['status' => 'approved'],
            ]);
        }

        $this->actingAs($associate)
            ->get(route('staff-logs.index', ['search' => 'TRF-PAGE']))
            ->assertOk()
            ->assertSee('Showing 1 to 10 of 12 entries')
            ->assertSee('search=TRF-PAGE', false)
            ->assertSee('page=2', false)
            ->assertViewHas('logs', fn ($logs) => $logs->count() === 10 && $logs->currentPage() === 1);

        $this->get(route('staff-logs.index', ['search' => 'TRF-PAGE', 'page' => 2]))
            ->assertOk()
            ->assertSee('Showing 11 to 12 of 12 entries')
            ->assertViewHas('logs', fn ($logs) => $logs->count() === 2 && $logs->currentPage() === 2);
    }

    public function test_single_designated_branch_is_locked_for_sales_associate(): void
    {
        [$source, $target] = $this->fixtures();
        $associate = User::factory()->create(['role' => 'sales_associate', 'hub_id' => $source->id]);

        $this->actingAs($associate)
            ->get(route('inventory-transactions.branch-transfer.create'))
            ->assertOk()
            ->assertSee('name="store_hub_id" value="'.$source->id.'"', false)
            ->assertDontSee('<select name="store_hub_id"', false)
            ->assertViewHas('transferSourceHubs', fn ($hubs) => $hubs->modelKeys() === [$source->id]);
    }

    public function test_inventory_staff_can_submit_branch_to_branch_transfers_between_active_branches(): void
    {
        [$source, $target, $product] = $this->fixtures();
        $staff = User::factory()->create(['role' => 'inventory_staff', 'hub_id' => $source->id]);

        $this->actingAs($staff)
            ->get(route('inventory-transactions.branch-transfer.create', ['hub_id' => $source->id]))
            ->assertOk()
            ->assertSee('Stock Transfer (BRANCH to BRANCH)')
            ->assertSee('<select name="store_hub_id" id="sourceHub"', false)
            ->assertViewHas('transferSourceHubs', fn ($hubs) => $hubs->count() === 2)
            ->assertSee('Stock Transfer (Branch to Branch)');

        $this->post(route('inventory-transactions.branch-transfer.store'), $this->payload($source, $target, $product))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('inventory-transactions.index', [
                'hub_id' => $source->id,
                'branch_transfer_submitted' => 1,
            ]));

        $this->assertSame(10, $product->fresh()->stock);
        $this->assertDatabaseHas('inventory_transactions', [
            'type' => 'branch_transfer',
            'source_hub_id' => $source->id,
            'target_hub_id' => $target->id,
            'created_by' => $staff->id,
            'status' => 'pending',
        ]);
    }

    public function test_multiple_designated_branches_allow_source_selection(): void
    {
        [$source, $target] = $this->fixtures();
        $associate = User::factory()->create(['role' => 'sales_associate', 'hub_id' => $source->id]);
        $associate->assignedStoreHubs()->attach($target->id);

        $this->actingAs($associate)
            ->get(route('inventory-transactions.branch-transfer.create'))
            ->assertOk()
            ->assertSee('<select name="store_hub_id" id="sourceHub"', false)
            ->assertSee('BRANCH A')
            ->assertSee('BRANCH B')
            ->assertDontSee('Locked to your designated branch.')
            ->assertViewHas('transferSourceHubs', fn ($hubs) => $hubs->count() === 2);
    }

    public function test_invalid_transfers_leave_stock_unchanged(): void
    {
        [$source, $target, $product, $receiving, $ho] = $this->fixtures();
        foreach ([
            ['target_hub_id' => $source->id],
            ['target_hub_id' => $ho->id],
            ['store_hub_id' => $ho->id],
            ['items' => [['product_id' => $product->id, 'quantity' => 11]]],
            ['items' => [['product_id' => $receiving->id, 'quantity' => 1]]],
        ] as $override) {
            $this->post(route('inventory-transactions.branch-transfer.store'), array_replace($this->payload($source, $target, $product), $override))
                ->assertSessionHasErrors();
            $this->assertEquals(10, $product->fresh()->stock);
            $this->assertEquals(2, $receiving->fresh()->stock);
            $this->assertDatabaseCount('inventory_transactions', 0);
        }
    }

    public function test_ho_transfer_defaults_follow_head_office_mode(): void
    {
        [$source, $target, $product, $receiving, $ho] = $this->fixtures();
        $url = route('inventory-transactions.transfer.create', ['hub_id' => $source->id]);
        $form = $this->get($url)->assertOk()
            ->assertSee('class="transaction-page-heading mb-4"', false)
            ->assertViewHas('hubId', $ho->id)
            ->assertViewHas('transferSourceHubs', fn ($hubs) => $hubs->modelKeys() === [$ho->id])
            ->assertViewHas('allHubs', fn ($hubs) => ! $hubs->contains('id', $ho->id))
            ->assertViewHas('transferReference', fn ($reference) => preg_match('/^TRF-HO-\d{8}-\d{6}-[A-F0-9]{6}$/', $reference) === 1)
            ->assertSee('name="reference"', false)
            ->assertSee('readonly', false);
        $transferReference = $form->viewData('transferReference');

        $ho->update(['is_head_office' => false]);
        $source->update(['is_head_office' => true]);
        $this->get($url)->assertOk()
            ->assertViewHas('hubId', $source->id)
            ->assertViewHas('transferSourceHubs', fn ($hubs) => $hubs->modelKeys() === [$source->id])
            ->assertViewHas('products', fn ($products) => $products->contains('id', $product->id));

        $payload = array_replace($this->payload($source, $target, $product), ['type' => 'stock_transfer']);
        $payload['reference'] = $transferReference;
        $response = $this->post(route('inventory-transactions.store'), $payload)
            ->assertSessionHasNoErrors();
        $savedTransfer = \App\Models\InventoryTransaction::where('type', 'stock_transfer')->firstOrFail();
        $this->assertMatchesRegularExpression('/^TRF-A-\d{8}-\d{6}-[A-F0-9]{6}$/', $savedTransfer->reference);
        $popupUrl = route('inventory-transactions.index', [
            'hub_id' => $source->id,
            'stock_transfer_saved' => 1,
            'reference' => $savedTransfer->reference,
        ]);
        $response->assertRedirect($popupUrl);
        $this->get($popupUrl)
            ->assertOk()
            ->assertSee('id="stock-transfer-success-popup"', false)
            ->assertSee($savedTransfer->reference)
            ->assertSee('.branch-transfer-success-popup', false)
            ->assertSee('Source Hub')
            ->assertSee('Destination Hub')
            ->assertDontSee('<strong>Transfer</strong>', false);
        $this->assertEquals(7, $product->fresh()->stock);
        $this->assertEquals(5, $receiving->fresh()->stock);

        $source->update(['is_head_office' => false]);
        $this->get($url)->assertOk()->assertViewHas('hubId', null)
            ->assertSee('Enable Head Office Mode for an active store first');
        $this->post(route('inventory-transactions.store'), $payload)->assertSessionHasErrors('store_hub_id');
        $this->assertEquals(7, $product->fresh()->stock);
    }

    public function test_stock_transfer_can_move_stock_from_branch_to_head_office(): void
    {
        [$source, , $product, , $ho] = $this->fixtures();
        $headOfficeProduct = Product::create([
            'name' => 'Transfer Test Product',
            'item_id' => 'TRANSFER-1',
            'store_hub_id' => $ho->id,
            'stock' => 4,
            'status' => 'active',
        ]);

        $form = $this->get(route('inventory-transactions.transfer.create', [
            'direction' => 'branch_to_ho',
            'hub_id' => $source->id,
        ]))->assertOk()
            ->assertSee('class="transaction-page-heading mb-4"', false)
            ->assertSee('Transfer Type')
            ->assertSee('Branch to Head Office')
            ->assertSee('Source Branch')
            ->assertSee('Target Head Office')
            ->assertViewHas('hubId', $source->id)
            ->assertViewHas('transferDirection', 'branch_to_ho')
            ->assertViewHas('transferSourceHubs', fn ($hubs) => $hubs->count() === 2 && $hubs->contains('id', $source->id))
            ->assertViewHas('allHubs', fn ($hubs) => $hubs->modelKeys() === [$ho->id])
            ->assertViewHas('products', fn ($products) => $products->contains('id', $product->id));

        $response = $this->post(route('inventory-transactions.store'), [
            'type' => 'stock_transfer',
            'transfer_direction' => 'branch_to_ho',
            'store_hub_id' => $source->id,
            'target_hub_id' => $ho->id,
            'occurred_on' => '2026-09-30',
            'reference' => $form->viewData('transferReference'),
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
        ])->assertSessionHasNoErrors();

        $transfer = \App\Models\InventoryTransaction::where('type', 'stock_transfer')->firstOrFail();
        $this->assertSame($source->id, $transfer->source_hub_id);
        $this->assertSame($ho->id, $transfer->target_hub_id);
        $this->assertSame(7, $product->fresh()->stock);
        $this->assertSame(7, $headOfficeProduct->fresh()->stock);
        $this->get(route('inventory-transactions.product-worksheet', [
            'hub_id' => $source->id,
            'type' => 'stock_transfer',
        ]))->assertOk()
            ->assertHeader(
                'Content-Disposition',
                'attachment; filename=A_Download_StockTrf_B2HO_'.now()->format('Ymd_His').'.csv'
            );

        $logsUrl = route('inventory-transactions.index', [
            'hub_id' => $source->id,
            'stock_transfer_saved' => 1,
            'reference' => $transfer->reference,
        ]);
        $response->assertRedirect($logsUrl);
        $this->get(route('inventory-transactions.index', ['hub_id' => $source->id, 'type' => 'stock_transfer']))
            ->assertOk()
            ->assertSee('Stock Transfer (BRANCH to HO)');
    }

    private function fixtures(): array
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $source = StoreHub::create(['name' => 'Branch A', 'code' => 'A', 'status' => 'active', 'is_head_office' => false]);
        $target = StoreHub::create(['name' => 'Branch B', 'code' => 'B', 'status' => 'active', 'is_head_office' => false]);
        $ho = StoreHub::create(['name' => 'Head Office', 'code' => 'HO', 'status' => 'active', 'is_head_office' => true]);
        $product = Product::create(['name' => 'Transfer Test Product', 'item_id' => 'TRANSFER-1', 'store_hub_id' => $source->id, 'stock' => 10, 'status' => 'active']);
        $receiving = Product::create(['name' => 'Transfer Test Product', 'item_id' => 'TRANSFER-1', 'store_hub_id' => $target->id, 'stock' => 2, 'status' => 'active']);

        return [$source, $target, $product, $receiving, $ho];
    }

    private function payload(StoreHub $source, StoreHub $target, Product $product): array
    {
        return [
            'type' => 'branch_transfer', 'store_hub_id' => $source->id, 'target_hub_id' => $target->id,
            'occurred_on' => '2026-09-08', 'items' => [['product_id' => $product->id, 'quantity' => 3]],
        ];
    }
}
