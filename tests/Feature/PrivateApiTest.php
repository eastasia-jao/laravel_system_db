<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductFileRequest;
use App\Models\StoreHub;
use App\Models\User;
use App\Notifications\InventoryWorkflowNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivateApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_api_requires_login(): void
    {
        $this->getJson(route('api.me'))->assertUnauthorized();
    }

    public function test_me_endpoint_returns_user_permissions_and_accessible_hubs(): void
    {
        $hub = StoreHub::create(['name' => 'Branch', 'code' => 'BR', 'status' => 'active']);
        $user = User::factory()->create(['role' => 'sales_associate', 'hub_id' => $hub->id]);

        $this->actingAs($user)
            ->getJson(route('api.me'))
            ->assertOk()
            ->assertJsonPath('user.role', 'sales_associate')
            ->assertJsonPath('permissions.view_products', true)
            ->assertJsonPath('permissions.manage_inventory', false)
            ->assertJsonPath('hubs.0.id', $hub->id);
    }

    public function test_products_api_sorts_item_ids_before_paginating(): void
    {
        $hub = StoreHub::create(['name' => 'Head Office', 'code' => 'HO', 'is_head_office' => true, 'status' => 'active']);
        $admin = User::factory()->create(['role' => 'admin']);

        foreach (['3', '4', '5', '6', '7', '8', '9', '10', '11', '12', '1', '2'] as $itemId) {
            Product::create([
                'store_hub_id' => $hub->id,
                'item_id' => $itemId,
                'name' => "Product {$itemId}",
                'stock' => 10,
            ]);
        }

        $response = $this->actingAs($admin)->getJson(route('api.products.index', [
            'hub_id' => $hub->id,
            'per_page' => 10,
        ]));

        $response->assertOk()->assertJsonPath('meta.total', 12);
        $this->assertSame(
            ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10'],
            collect($response->json('data'))->pluck('item_id')->all()
        );
    }

    public function test_product_search_api_is_scoped_to_the_users_allowed_hubs(): void
    {
        $assigned = StoreHub::create(['name' => 'Assigned', 'code' => 'A', 'status' => 'active']);
        $private = StoreHub::create(['name' => 'Private', 'code' => 'P', 'status' => 'active']);
        $user = User::factory()->create(['role' => 'sales_associate', 'hub_id' => $assigned->id]);
        Product::create(['store_hub_id' => $assigned->id, 'item_id' => 'AC-001', 'name' => 'Allowed Brush', 'stock' => 5]);
        Product::create(['store_hub_id' => $private->id, 'item_id' => 'AC-999', 'name' => 'Private Brush', 'stock' => 5]);

        $this->actingAs($user)
            ->getJson(route('api.products.search', ['hub_id' => $assigned->id, 'q' => 'Brush']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.item_id', 'AC-001');

        $this->getJson(route('api.products.search', ['hub_id' => $private->id, 'q' => 'Brush']))
            ->assertForbidden();
    }

    public function test_notifications_api_returns_and_marks_only_the_signed_in_users_notifications(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $user->notify(new InventoryWorkflowNotification('inventory_verification', 'Your private API notification.'));
        $otherUser->notify(new InventoryWorkflowNotification('inventory_verification', 'Hidden notification.'));

        $notification = $user->notifications()->firstOrFail();

        $response = $this->actingAs($user)->getJson(route('api.notifications.index'));
        $response->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonCount(1, 'data');
        $this->assertStringContainsString('Your private API notification.', $response->json('data.0.message'));

        $this->postJson(route('api.notifications.read', $otherUser->notifications()->firstOrFail()->id))
            ->assertNotFound();

        $this->postJson(route('api.notifications.read', $notification->id))
            ->assertOk()
            ->assertJsonPath('unread_count', 0);
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_import_status_api_is_private_to_inventory_roles(): void
    {
        $hub = StoreHub::create(['name' => 'Branch', 'code' => 'BR', 'status' => 'active']);
        $record = ProductFileRequest::create([
            'submitted_by' => User::factory()->create(['role' => 'inventory_staff'])->id,
            'store_hub_id' => $hub->id,
            'type' => 'import',
            'status' => 'pending',
            'processing_status' => 'completed',
            'file_name' => 'products.csv',
            'total_rows' => 5,
            'created_count' => 4,
            'updated_count' => 1,
            'skipped_count' => 0,
        ]);

        $this->actingAs(User::factory()->create(['role' => 'sales_associate', 'hub_id' => $hub->id]))
            ->getJson(route('api.hubs.products.import-status', $hub->id))
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'inventory_staff']))
            ->getJson(route('api.hubs.products.import-status', $hub->id))
            ->assertOk()
            ->assertJsonPath('import.id', $record->id)
            ->assertJsonPath('import.total_rows', 5)
            ->assertJsonPath('import.created_count', 4);
    }
}
