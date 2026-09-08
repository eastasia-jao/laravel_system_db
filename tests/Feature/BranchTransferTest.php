<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StoreHub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_transfer_moves_stock_and_can_be_filtered_in_logs(): void
    {
        [$source, $target, $product, $receiving] = $this->fixtures();
        $this->get(route('inventory-transactions.branch-transfer.create', ['hub_id' => $source->id]))
            ->assertOk()->assertSee('Stock Transfer (BRANCH to BRANCH)')
            ->assertViewHas('transferSourceHubs', fn ($hubs) => $hubs->count() === 2 && $hubs->every(fn ($hub) => ! $hub->is_head_office))
            ->assertViewHas('allHubs', fn ($hubs) => $hubs->count() === 2 && $hubs->every(fn ($hub) => ! $hub->is_head_office));
        $this->post(route('inventory-transactions.store'), $this->payload($source, $target, $product))
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertEquals(7, $product->fresh()->stock);
        $this->assertEquals(5, $receiving->fresh()->stock);
        $this->assertDatabaseHas('inventory_transactions', [
            'type' => 'branch_transfer', 'source_hub_id' => $source->id,
            'target_hub_id' => $target->id, 'product_id' => $product->id, 'quantity' => 3,
        ]);
        $this->get(route('inventory-transactions.index', ['type' => 'branch_transfer']))
            ->assertOk()->assertSee('Stock Transfer (BRANCH to BRANCH)')->assertSee('Transfer Test Product');
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
            $this->post(route('inventory-transactions.store'), array_replace($this->payload($source, $target, $product), $override))
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
        $this->get($url)->assertOk()
            ->assertViewHas('hubId', $ho->id)
            ->assertViewHas('transferSourceHubs', fn ($hubs) => $hubs->modelKeys() === [$ho->id])
            ->assertViewHas('allHubs', fn ($hubs) => ! $hubs->contains('id', $ho->id));

        $ho->update(['is_head_office' => false]);
        $source->update(['is_head_office' => true]);
        $this->get($url)->assertOk()
            ->assertViewHas('hubId', $source->id)
            ->assertViewHas('transferSourceHubs', fn ($hubs) => $hubs->modelKeys() === [$source->id])
            ->assertViewHas('products', fn ($products) => $products->contains('id', $product->id));

        $payload = array_replace($this->payload($source, $target, $product), ['type' => 'stock_transfer']);
        $this->post(route('inventory-transactions.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertEquals(7, $product->fresh()->stock);
        $this->assertEquals(5, $receiving->fresh()->stock);

        $source->update(['is_head_office' => false]);
        $this->get($url)->assertOk()->assertViewHas('hubId', null)
            ->assertSee('Enable Head Office Mode for an active store first');
        $this->post(route('inventory-transactions.store'), $payload)->assertSessionHasErrors('store_hub_id');
        $this->assertEquals(7, $product->fresh()->stock);
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
