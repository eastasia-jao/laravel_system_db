<?php

namespace Tests\Feature;

use App\Models\StoreHub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActiveStoreHubSelectorsTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_store_selectors_hide_inactive_hubs_but_configuration_keeps_them_manageable(): void
    {
        $activeHeadOffice = StoreHub::create([
            'name' => 'Active Head Office',
            'code' => 'ACTIVE-HO',
            'status' => 'active',
            'is_head_office' => true,
        ]);
        $activeBranch = StoreHub::create([
            'name' => 'Active Branch',
            'code' => 'ACTIVE-BR',
            'status' => 'active',
            'is_head_office' => false,
        ]);
        $inactiveHeadOffice = StoreHub::create([
            'name' => 'Inactive Head Office',
            'code' => 'INACTIVE-HO',
            'status' => 'inactive',
            'is_head_office' => true,
        ]);
        $inactiveBranch = StoreHub::create([
            'name' => 'Inactive Branch',
            'code' => 'INACTIVE-BR',
            'status' => 'inactive',
            'is_head_office' => false,
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        foreach ([
            route('inventory-transactions.index') => 'transaction log',
            route('staff-logs.index') => 'staff log',
            route('users.index') => 'user assignment',
            route('dashboard') => 'dashboard',
            route('stock-allocation.index', ['hub_id' => $activeHeadOffice->id]) => 'stock allocation',
            route('inventory-transactions.transfer.create', ['hub_id' => $activeHeadOffice->id]) => 'stock transfer form',
            route('inventory-transactions.restock.create', ['hub_id' => $activeHeadOffice->id]) => 'restock form',
            route('inventory-transactions.sponsor.create', ['hub_id' => $activeHeadOffice->id]) => 'event form',
            route('inventory-transactions.return.create', ['hub_id' => $activeHeadOffice->id]) => 'return form',
            route('catalog.index', ['hub_id' => $activeHeadOffice->id]) => 'catalog assignment',
            route('products.index', ['hub_id' => $activeHeadOffice->id]) => 'product list',
        ] as $url => $selector) {
            $response = $this->get($url)->assertOk();
            $this->assertStringContainsString('ACTIVE HEAD OFFICE', $response->getContent(), "{$selector} should list active hubs.");
            $this->assertStringNotContainsString('INACTIVE HEAD OFFICE', $response->getContent(), "{$selector} should hide inactive hubs.");
            $this->assertStringNotContainsString('INACTIVE BRANCH', $response->getContent(), "{$selector} should hide inactive hubs.");
        }

        $this->get(route('hub.dashboard', $activeBranch->id))
            ->assertOk()
            ->assertSee('ACTIVE BRANCH')
            ->assertDontSee('INACTIVE BRANCH');

        $this->get(route('configuration'))
            ->assertOk()
            ->assertSee('INACTIVE HEAD OFFICE')
            ->assertSee('INACTIVE BRANCH');

        $this->assertDatabaseHas('store_hubs', ['id' => $inactiveHeadOffice->id, 'status' => 'inactive']);
        $this->assertDatabaseHas('store_hubs', ['id' => $inactiveBranch->id, 'status' => 'inactive']);
    }
}
