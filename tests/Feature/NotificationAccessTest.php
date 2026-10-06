<?php

namespace Tests\Feature;

use App\Models\StoreHub;
use App\Models\User;
use App\Notifications\InventoryWorkflowNotification;
use App\Notifications\SalesWorkflowNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_staff_get_a_friendly_message_for_queue_notifications(): void
    {
        $hub = StoreHub::create(['name' => 'Test', 'code' => 'test', 'status' => 'active']);
        foreach (['sales_associate', 'sales_marketing_staff'] as $role) {
            $user = User::factory()->create(['role' => $role, 'hub_id' => $hub->id]);
            $queue = route('hub.sales.pending', $hub->id);
            $user->notify(new InventoryWorkflowNotification('inventory_verification', 'Your sale was verified.', $hub->id, $queue));
            $notification = $user->notifications()->first();
            $this->actingAs($user)->get(route('notifications.read', $notification->id))
                ->assertRedirect(route('dashboard'))->assertSessionHas('notification_error');
            $this->assertNotNull($notification->fresh()->read_at);
            $this->get(route('dashboard'))->assertOk()->assertSee('Access restricted.')
                ->assertSee('only available to inventory staff and admins');
            $this->get($queue)->assertRedirect(route('dashboard'))->assertSessionHas('notification_error');
        }
    }

    public function test_inventory_staff_and_admin_keep_queue_access(): void
    {
        $hub = StoreHub::create(['name' => 'Test', 'code' => 'test', 'status' => 'active']);
        foreach (['admin', 'inventory_staff'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $queue = route('hub.sales.pending', $hub->id);
            $user->notify(new InventoryWorkflowNotification('inventory_verification', 'Verified.', $hub->id, $queue));
            $this->actingAs($user)->get(route('notifications.read', $user->notifications()->first()->id))
                ->assertRedirect($queue)->assertSessionMissing('notification_error');
            $this->get($queue)->assertOk()->assertSee('Inventory Verification Queue');
        }
    }

    public function test_notification_cannot_be_opened_by_another_user(): void
    {
        $owner = User::factory()->create();
        $owner->notify(new InventoryWorkflowNotification('inventory_verification', 'Verified.'));
        $this->actingAs(User::factory()->create())->get(route('notifications.read', $owner->notifications()->first()->id))->assertNotFound();
        $this->assertNull($owner->notifications()->first()->read_at);
    }

    public function test_tiktok_approval_notification_opens_tiktok_returns_instead_of_sales_report(): void
    {
        $hub = StoreHub::create(['name' => 'Head Office', 'code' => 'HO', 'status' => 'active', 'is_head_office' => true]);
        $staff = User::factory()->create([
            'role' => 'sales_marketing_staff',
            'hub_id' => $hub->id,
            'sales_channels' => ['tiktok'],
        ]);
        $sale = \App\Models\PendingSale::create([
            'store_hub_id' => $hub->id,
            'sales_channel' => 'tiktok',
            'invoice_number' => 'TIKTOK-APPROVED-001',
            'customer_name' => 'TikTok Customer',
            'placed_order_date' => '2026-10-06',
            'items' => [],
            'status' => 'confirmed',
            'submitted_by' => $staff->id,
        ]);

        $staff->notify(new SalesWorkflowNotification('confirmed', $sale));
        $notification = $staff->notifications()->firstOrFail();
        $returnsUrl = route('hub.tiktok-returns', ['hub' => $hub->id]);
        $this->assertSame($returnsUrl, $notification->data['url']);
        $this->assertSame('tiktok', $notification->data['channel']);

        $this->actingAs($staff)
            ->get(route('notifications.read', $notification->id))
            ->assertRedirect($returnsUrl);

        $legacyNotification = $staff->notifications()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => SalesWorkflowNotification::class,
            'data' => [
                'event' => 'confirmed',
                'hub_id' => $hub->id,
                'url' => route('hub.report', ['hub' => $hub->id, 'channel' => 'tiktok']),
            ],
        ]);
        $this->get(route('notifications.read', $legacyNotification->id))
            ->assertRedirect($returnsUrl);
    }

    public function test_mark_all_notifications_as_read_uses_post(): void
    {
        $user = User::factory()->create();
        $user->notify(new InventoryWorkflowNotification('inventory_verification', 'Verified.'));

        $this->actingAs($user)->get(route('notifications.read-all'))->assertMethodNotAllowed();
        $this->assertNull($user->notifications()->first()->read_at);

        $this->post(route('notifications.read-all'))->assertRedirect();
        $this->assertNotNull($user->notifications()->first()->read_at);
    }

    public function test_notification_feed_returns_only_the_signed_in_users_notifications(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $user->notify(new InventoryWorkflowNotification('inventory_verification', 'Your live notification.'));
        $otherUser->notify(new InventoryWorkflowNotification('inventory_verification', 'Another users notification.'));

        $response = $this->actingAs($user)->getJson(route('notifications.feed'));

        $response->assertOk()->assertJsonPath('unread_count', 1);
        $this->assertStringContainsString('Your live notification.', $response->json('html'));
        $this->assertStringNotContainsString('Another users notification.', $response->json('html'));
    }

    public function test_branch_walk_in_replacement_notifications_use_walk_in_label_icon_and_reference(): void
    {
        $hub = StoreHub::create(['name' => 'Branch', 'code' => 'BRANCH', 'status' => 'active', 'is_head_office' => false]);
        $admin = User::factory()->create(['role' => 'admin', 'hub_id' => $hub->id]);
        $admin->notify(new InventoryWorkflowNotification(
            'replacement_request',
            'Angel submitted a replacement request for order PENDING-12. Inventory verification is required.',
            $hub->id,
            null,
            'Wholesale replacement verification needed'
        ));

        $this->actingAs($admin)->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Walk-In replacement verification needed')
            ->assertSee('WALK-IN012')
            ->assertSee('fa-cash-register')
            ->assertDontSee('PENDING-12')
            ->assertDontSee('fa-box-open');
    }

    public function test_inventory_verification_rejection_uses_red_rejection_icon(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $user->notify(new InventoryWorkflowNotification('rejected', 'A sale was rejected during inventory verification.'));

        $this->actingAs($user)->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('fa-circle-xmark');
    }

    public function test_walk_in_replacement_rejection_keeps_red_icon_in_notifications_and_sidebar(): void
    {
        $hub = StoreHub::create(['name' => 'Branch', 'code' => 'BRANCH', 'status' => 'active', 'is_head_office' => false]);
        $user = User::factory()->create(['role' => 'admin', 'hub_id' => $hub->id]);
        $user->notify(new InventoryWorkflowNotification(
            'replacement_rejected',
            'Your Walk-In replacement request was rejected.',
            $hub->id,
            null,
            'Walk-In replacement rejected',
            'walk_in'
        ));

        $this->actingAs($user)->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Walk-In replacement rejected')
            ->assertSee('<i class="fa-solid fa-circle-xmark"></i>', false)
            ->assertDontSee('<i class="fa-solid fa-cash-register"></i>', false);

        $this->get(route('hub.dashboard', $hub->id))
            ->assertOk()
            ->assertSee('Walk-In replacement rejected')
            ->assertSee('<i class="fa-solid fa-circle-xmark small"></i>', false)
            ->assertDontSee('<i class="fa-solid fa-cash-register small"></i>', false);
    }

    public function test_walk_in_inventory_confirmation_uses_green_check_icon_in_notifications_and_sidebar(): void
    {
        $hub = StoreHub::create(['name' => 'Branch', 'code' => 'BRANCH', 'status' => 'active', 'is_head_office' => false]);
        $user = User::factory()->create(['role' => 'admin', 'hub_id' => $hub->id]);
        $user->notify(new InventoryWorkflowNotification(
            'confirmed',
            'A Walk-In sale was approved and stock was deducted.',
            $hub->id,
            null,
            'Sale approved by inventory',
            'walk_in'
        ));

        $this->actingAs($user)->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('<i class="fa-solid fa-clipboard-check"></i>', false)
            ->assertDontSee('<i class="fa-solid fa-cash-register"></i>', false);

        $this->get(route('hub.dashboard', $hub->id))
            ->assertOk()
            ->assertSee('<i class="fa-solid fa-clipboard-check small"></i>', false)
            ->assertDontSee('<i class="fa-solid fa-cash-register small"></i>', false);
    }

    public function test_walk_in_replacement_approval_uses_green_check_icon_in_notifications_and_sidebar(): void
    {
        $hub = StoreHub::create(['name' => 'Branch', 'code' => 'BRANCH', 'status' => 'active', 'is_head_office' => false]);
        $user = User::factory()->create(['role' => 'admin', 'hub_id' => $hub->id]);
        $user->notify(new InventoryWorkflowNotification(
            'replacement_approved',
            'Your Walk-In replacement request was approved.',
            $hub->id,
            null,
            'Walk-In replacement approved',
            'walk_in'
        ));

        $this->actingAs($user)->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('<i class="fa-solid fa-clipboard-check"></i>', false)
            ->assertDontSee('<i class="fa-solid fa-cash-register"></i>', false);

        $this->get(route('hub.dashboard', $hub->id))
            ->assertOk()
            ->assertSee('<i class="fa-solid fa-clipboard-check small"></i>', false)
            ->assertDontSee('<i class="fa-solid fa-cash-register small"></i>', false);
    }
}
