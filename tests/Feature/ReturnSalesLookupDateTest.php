<?php

namespace Tests\Feature;

use App\Models\SalesTransaction;
use App\Models\StoreHub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReturnSalesLookupDateTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_lookup_is_limited_to_the_selected_return_date(): void
    {
        $hub = StoreHub::create(['name' => 'Returns', 'code' => 'RETURNS', 'status' => 'active']);
        $admin = User::factory()->create(['role' => 'admin']);

        SalesTransaction::create([
            'store_hub_id' => $hub->id,
            'channel_type' => 'walk_in',
            'customer_name' => 'Customer on selected date',
            'order_number' => 'WALK-IN001',
            'order_date' => '2026-09-28',
            'status' => 'completed',
        ]);
        SalesTransaction::create([
            'store_hub_id' => $hub->id,
            'channel_type' => 'walk_in',
            'customer_name' => 'Customer on another date',
            'order_number' => 'WALK-IN002',
            'order_date' => '2026-09-29',
            'status' => 'completed',
        ]);

        $this->actingAs($admin)
            ->getJson(route('inventory-transactions.return.sales', [
                'hub_id' => $hub->id,
                'channel' => 'walk_in',
                'date' => '2026-09-28',
            ]))
            ->assertOk()
            ->assertJsonPath('customers', ['Customer on selected date']);
    }
}
