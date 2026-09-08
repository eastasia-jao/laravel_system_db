<?php

namespace Tests\Feature;

use App\Models\StoreHub;
use App\Models\User;
use App\Notifications\InventoryWorkflowNotification;
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
}
