<?php

namespace Tests\Feature;

use App\Models\EventPullout;
use App\Models\Product;
use App\Models\SalesTransaction;
use App\Models\StoreHub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_pullout_creates_a_trackable_ledger_and_deducts_condition_stock_once(): void
    {
        [$admin, $hub, $product] = $this->eventFixture();

        $this->actingAs($admin)->post(route('inventory-transactions.store'), [
            'type' => 'sponsor_workshop',
            'store_hub_id' => $hub->id,
            'occurred_on' => '2026-10-15',
            'source' => 'October Art Fair',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 5, 'pullout_qty' => 6, 'stock_condition' => 'good'],
                ['product_id' => $product->id, 'quantity' => 2, 'pullout_qty' => 2, 'stock_condition' => 'damaged'],
            ],
        ])->assertSessionHasNoErrors();

        $event = EventPullout::with('items')->sole();
        $this->assertSame('October Art Fair', $event->source);
        $this->assertSame('open', $event->status);
        $this->assertSame(7, $event->items->sum('quantity'));
        $this->assertSame(8, $event->items->sum('requested_quantity'));
        $this->assertSame(5, (int) $product->fresh()->stock);
        $this->assertSame(2, (int) $product->fresh()->damaged_stock);

        $this->get(route('event-inventory.index', ['hub_id' => $hub->id]))
            ->assertOk()
            ->assertSee('Event Inventory')
            ->assertSee('October Art Fair')
            ->assertSee($event->reference)
            ->assertSee('Record Event Sale');
    }

    public function test_event_sale_reduces_only_event_stock_and_return_limit_accounts_for_sold_items(): void
    {
        [$admin, $hub, $product] = $this->eventFixture();
        $event = $this->createEvent($admin, $hub, $product, 5);
        $line = $event->items()->sole();
        $stockAfterPullout = (int) $product->fresh()->stock;

        $this->actingAs($admin)->post(route('event-inventory.sales.store', $event), [
            'order_date' => '2026-10-15',
            'customer_name' => 'Event Customer',
            'mode_of_payment' => 'CASH',
            'items' => [[
                'event_pullout_item_id' => $line->id,
                'quantity' => 2,
                'unit_price' => 150,
            ]],
        ])->assertSessionHasNoErrors();

        $sale = SalesTransaction::where('event_pullout_id', $event->id)->sole();
        $this->assertSame('event', $sale->channel_type);
        $this->assertSame(2, $sale->items()->sum('quantity'));
        $this->assertSame($stockAfterPullout, (int) $product->fresh()->stock);
        $this->assertDatabaseHas('inventory_transactions', [
            'event_pullout_id' => $event->id,
            'event_pullout_item_id' => $line->id,
            'type' => 'sold',
            'channel' => 'event',
            'quantity' => 2,
        ]);

        $this->getJson(route('inventory-transactions.return.sales', [
            'hub_id' => $hub->id,
            'channel' => 'event',
            'event_reference' => $event->reference,
        ]))->assertOk()
            ->assertJsonPath('transaction.items.0.quantity', 5)
            ->assertJsonPath('transaction.items.0.sold_quantity', 2)
            ->assertJsonPath('transaction.items.0.returned_quantity', 0);

        $returnPayload = [
            'type' => 'return',
            'store_hub_id' => $hub->id,
            'occurred_on' => '2026-10-15',
            'channel' => 'event',
            'reference' => $event->reference,
            'event_reference' => $event->reference,
            'items' => [[
                'product_id' => $product->id,
                'good_quantity' => 4,
                'damaged_quantity' => 0,
            ]],
        ];
        $this->post(route('inventory-transactions.return.store'), $returnPayload)
            ->assertSessionHasErrors('items');
        $this->assertSame($stockAfterPullout, (int) $product->fresh()->stock);

        $returnPayload['items'][0]['good_quantity'] = 3;
        $this->post(route('inventory-transactions.return.store'), $returnPayload)
            ->assertSessionHasNoErrors();
        $this->assertSame($stockAfterPullout + 3, (int) $product->fresh()->stock);
    }

    public function test_event_closeout_records_remaining_stock_as_missing_and_locks_the_event(): void
    {
        [$admin, $hub, $product] = $this->eventFixture();
        $event = $this->createEvent($admin, $hub, $product, 5);
        $line = $event->items()->sole();

        $this->actingAs($admin)->post(route('event-inventory.sales.store', $event), [
            'order_date' => '2026-10-15',
            'items' => [[
                'event_pullout_item_id' => $line->id,
                'quantity' => 2,
                'unit_price' => 100,
            ]],
        ])->assertSessionHasNoErrors();

        $this->patch(route('event-inventory.close', $event), ['notes' => 'Final event count'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Event closed with 3 missing item(s) recorded.');

        $this->assertSame('closed', $event->fresh()->status);
        $this->assertDatabaseHas('inventory_transactions', [
            'event_pullout_id' => $event->id,
            'type' => 'event_variance',
            'condition' => 'missing',
            'quantity' => 3,
        ]);
        $this->assertSame(5, (int) $product->fresh()->stock);

        $this->post(route('event-inventory.sales.store', $event), [
            'order_date' => '2026-10-15',
            'items' => [[
                'event_pullout_item_id' => $line->id,
                'quantity' => 1,
                'unit_price' => 100,
            ]],
        ])->assertSessionHasErrors('event');
    }

    public function test_event_inventory_routes_are_limited_to_inventory_management_roles(): void
    {
        [, $hub] = $this->eventFixture();
        $salesAssociate = User::factory()->create(['role' => 'sales_associate', 'hub_id' => $hub->id]);

        $this->actingAs($salesAssociate)
            ->get(route('event-inventory.index', ['hub_id' => $hub->id]))
            ->assertForbidden();
    }

    private function eventFixture(): array
    {
        $hub = StoreHub::create([
            'name' => 'Event Head Office',
            'code' => 'EVENT-HO',
            'status' => 'active',
            'is_head_office' => true,
        ]);
        $admin = User::factory()->create(['role' => 'admin', 'hub_id' => $hub->id]);
        $product = Product::create([
            'name' => 'Event Artwork',
            'item_id' => 'EVENT-ART-001',
            'store_hub_id' => $hub->id,
            'stock' => 10,
            'damaged_stock' => 4,
            'sales_price' => 150,
            'status' => 'active',
        ]);

        return [$admin, $hub, $product];
    }

    private function createEvent(User $admin, StoreHub $hub, Product $product, int $quantity): EventPullout
    {
        $this->actingAs($admin)->post(route('inventory-transactions.store'), [
            'type' => 'sponsor_workshop',
            'store_hub_id' => $hub->id,
            'occurred_on' => '2026-10-15',
            'source' => 'October Art Fair',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => $quantity,
                'pullout_qty' => $quantity,
                'stock_condition' => 'good',
            ]],
        ])->assertSessionHasNoErrors();

        return EventPullout::with('items')->sole();
    }
}
