<?php

namespace Tests\Feature;

use App\Models\StoreHub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreHubConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_hub_delete_action_is_hidden_and_direct_deletion_is_blocked(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $hub = StoreHub::create(['name' => 'Protected Hub', 'code' => 'PROTECTED', 'status' => 'active']);

        $this->actingAs($admin)->get(route('configuration', ['tab' => 'hubs']))
            ->assertOk()
            ->assertSee('aria-label="Deactivate hub"', false)
            ->assertDontSee("openDeleteModal('".route('storehub.destroy', $hub), false);

        $this->delete(route('storehub.destroy', $hub))
            ->assertRedirect('/configuration?tab=hubs')
            ->assertSessionHas('error', 'Store hubs cannot be deleted because they may contain inventory and transaction history. Deactivate the hub instead.');

        $this->assertDatabaseHas('store_hubs', ['id' => $hub->id]);
    }
}
