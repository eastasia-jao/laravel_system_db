<?php

namespace Tests\Feature;

use App\Models\EventPullout;
use App\Models\Product;
use App\Models\SalesTransaction;
use App\Models\StoreHub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

        $inventoryResponse = $this->get(route('event-inventory.index', ['hub_id' => $hub->id]))
            ->assertOk()
            ->assertSee('Event Inventory')
            ->assertSee('October Art Fair')
            ->assertSee($event->reference)
            ->assertSee('Record Event Sale')
            ->assertSee('Choose Good or Damaged for each row')
            ->assertSee('Search product name or Item ID')
            ->assertSee('Order Slip Image')
            ->assertSee('Upload up to 5 images; maximum 2 MB each.')
            ->assertSee('Proof of Payment')
            ->assertSee('PayMaya')
            ->assertSee('Specify Payment Method')
            ->assertSee('eventSaleImageViewer')
            ->assertSee('maxlength="150"', false)
            ->assertSee('event-sale-remarks')
            ->assertSee('popup-event-price-display')
            ->assertDontSee('Bank Transfer')
            ->assertDontSee('Credit/Debit Card')
            ->assertDontSee('Payment Status')
            ->assertDontSee('Amount Paid')
            ->assertDontSee('General Transaction Logs');
        $this->assertMatchesRegularExpression(
            '/EVENT-'.$event->id.'-\d{14}-[A-Z0-9]{4}/',
            $inventoryResponse->getContent()
        );

        $this->get(route('event-inventory.sales.create', $event))
            ->assertRedirect(route('event-inventory.index', [
                'hub_id' => $hub->id,
                'sale_event_id' => $event->id,
            ]));

        $logResponse = $this->get(route('event-inventory.index', [
            'hub_id' => $hub->id,
            'section' => 'transactions',
            'event_id' => $event->id,
        ]))->assertOk()
            ->assertSee('Event Transaction Logs')
            ->assertSee('View details')
            ->assertSee('Same products are consolidated')
            ->assertSee('Good: 5')
            ->assertSee('Damaged: 2');
        $this->assertSame(1, substr_count(
            $logResponse->getContent(),
            'data-event-log-product="'.$product->id.'"'
        ));
    }

    public function test_event_sale_reduces_only_event_stock_and_return_limit_accounts_for_sold_items(): void
    {
        Storage::fake('public');
        [$admin, $hub, $product] = $this->eventFixture();
        $event = $this->createEvent($admin, $hub, $product, 5);
        $line = $event->items()->sole();
        $stockAfterPullout = (int) $product->fresh()->stock;
        $orderNumber = 'EVENT-'.$event->id.'-20261015120000-AB12';

        $this->actingAs($admin)->post(route('event-inventory.sales.store', $event), [
            'order_date' => '2026-10-15',
            'order_number' => $orderNumber,
            'customer_name' => 'event customer',
            'contact_number' => '09171234567',
            'address' => 'Event Hall, Manila',
            'mode_of_payment' => 'OTHERS',
            'custom_mop' => 'Cooperative Voucher',
            'payment_status' => 'partial',
            'amount_paid' => 1,
            'additional_discount_percentage' => 5,
            'order_slip_image' => $this->fakePng('order-slip.png'),
            'item_images' => [
                $this->fakePng('event-1.png'),
                $this->fakePng('event-2.png'),
                $this->fakePng('event-3.png'),
            ],
            'proof_of_payment_image' => $this->fakePng('payment.png'),
            'items' => [[
                'event_pullout_item_id' => $line->id,
                'stock_condition' => 'good',
                'quantity' => 2,
                'unit_price' => 1,
                'discount_percentage' => 10,
            ]],
        ])->assertSessionHasNoErrors();

        $sale = SalesTransaction::where('event_pullout_id', $event->id)->sole();
        $this->assertSame('event', $sale->channel_type);
        $this->assertSame($orderNumber, $sale->order_number);
        $this->assertSame(2, $sale->items()->sum('quantity'));
        $this->assertSame('Event Customer', $sale->customer_name);
        $this->assertSame('+639171234567', $sale->contact_number);
        $this->assertSame('OTHERS', $sale->mode_of_payment);
        $this->assertSame('Cooperative Voucher', $sale->custom_mop);
        $this->assertSame('paid', $sale->payment_status);
        $this->assertSame('270.00', $sale->sub_total);
        $this->assertSame('256.50', $sale->grand_total);
        $this->assertSame('256.50', $sale->amount_paid);
        $this->assertNotEmpty($sale->event_attachments['order_slip']);
        $this->assertCount(3, $sale->event_attachments['item_images']);
        $this->assertNotEmpty($sale->event_attachments['proof_of_payment']);
        foreach (collect($sale->event_attachments)->flatten() as $attachment) {
            Storage::disk('public')->assertExists($attachment);
        }
        $this->assertEquals(10.00, $sale->items()->sole()->discount_percentage);
        $this->assertEquals(150.00, $sale->items()->sole()->unit_price);
        $this->assertEquals(270.00, $sale->items()->sole()->line_total);
        $this->assertSame($stockAfterPullout, (int) $product->fresh()->stock);
        $this->assertDatabaseHas('inventory_transactions', [
            'event_pullout_id' => $event->id,
            'event_pullout_item_id' => $line->id,
            'type' => 'sold',
            'channel' => 'event',
            'quantity' => 2,
        ]);

        $this->get(route('event-inventory.index', [
            'hub_id' => $hub->id,
            'event_id' => $event->id,
            'section' => 'transactions',
        ]))->assertOk()
            ->assertSee('Cooperative Voucher')
            ->assertSee('Order Slip Image')
            ->assertSee('Item Images')
            ->assertSee('Proof of Payment');

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
            'order_number' => 'EVENT-'.$event->id.'-20261015130000-CD34',
            'mode_of_payment' => 'CASH',
            'items' => [[
                'event_pullout_item_id' => $line->id,
                'stock_condition' => 'good',
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
            'order_number' => 'EVENT-'.$event->id.'-20261015140000-EF56',
            'mode_of_payment' => 'CASH',
            'items' => [[
                'event_pullout_item_id' => $line->id,
                'stock_condition' => 'good',
                'quantity' => 1,
                'unit_price' => 100,
            ]],
        ])->assertSessionHasErrors('event');
    }

    public function test_one_event_sale_can_include_good_and_damaged_items(): void
    {
        [$admin, $hub, $product] = $this->eventFixture();

        $this->actingAs($admin)->post(route('inventory-transactions.store'), [
            'type' => 'sponsor_workshop',
            'store_hub_id' => $hub->id,
            'occurred_on' => '2026-10-15',
            'source' => 'Mixed Condition Event',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3, 'pullout_qty' => 3, 'stock_condition' => 'good'],
                ['product_id' => $product->id, 'quantity' => 2, 'pullout_qty' => 2, 'stock_condition' => 'damaged'],
            ],
        ])->assertSessionHasNoErrors();

        $event = EventPullout::with('items')->sole();
        $goodLine = $event->items->firstWhere('stock_condition', 'good');
        $damagedLine = $event->items->firstWhere('stock_condition', 'damaged');
        $goodStockAfterPullout = (int) $product->fresh()->stock;
        $damagedStockAfterPullout = (int) $product->fresh()->damaged_stock;

        $this->post(route('event-inventory.sales.store', $event), [
            'order_date' => '2026-10-15',
            'order_number' => 'EVENT-'.$event->id.'-20261015143000-MX12',
            'mode_of_payment' => 'CASH',
            'items' => [
                [
                    'event_pullout_item_id' => $goodLine->id,
                    'stock_condition' => 'good',
                    'quantity' => 1,
                    'unit_price' => 150,
                ],
                [
                    'event_pullout_item_id' => $damagedLine->id,
                    'stock_condition' => 'damaged',
                    'quantity' => 1,
                    'unit_price' => 100,
                ],
            ],
        ])->assertSessionHasNoErrors();

        $sale = SalesTransaction::where('event_pullout_id', $event->id)->sole();
        $this->assertSame(1, $sale->items()->where('stock_condition', 'good')->sum('quantity'));
        $this->assertSame(1, $sale->items()->where('stock_condition', 'damaged')->sum('quantity'));
        $this->assertDatabaseHas('inventory_transactions', [
            'sales_transaction_id' => $sale->id,
            'condition' => 'good',
            'quantity' => 1,
        ]);
        $this->assertDatabaseHas('inventory_transactions', [
            'sales_transaction_id' => $sale->id,
            'condition' => 'damaged',
            'quantity' => 1,
        ]);
        $this->assertSame($goodStockAfterPullout, (int) $product->fresh()->stock);
        $this->assertSame($damagedStockAfterPullout, (int) $product->fresh()->damaged_stock);
    }

    public function test_event_inventory_contains_a_filterable_transaction_log(): void
    {
        [$admin, $hub, $product] = $this->eventFixture();
        $event = $this->createEvent($admin, $hub, $product, 5);
        $line = $event->items()->sole();

        $this->actingAs($admin)->post(route('event-inventory.sales.store', $event), [
            'order_date' => '2026-10-15',
            'order_number' => 'EVENT-'.$event->id.'-20261015150000-GH78',
            'customer_name' => 'Log Customer',
            'mode_of_payment' => 'CASH',
            'items' => [[
                'event_pullout_item_id' => $line->id,
                'stock_condition' => 'good',
                'quantity' => 2,
                'unit_price' => 150,
            ]],
        ])->assertSessionHasNoErrors();

        $sale = SalesTransaction::where('event_pullout_id', $event->id)->sole();
        $this->get(route('event-inventory.transactions', [
            'hub_id' => $hub->id,
            'event_id' => $event->id,
        ]))->assertRedirect(route('event-inventory.index', [
            'hub_id' => $hub->id,
            'event_id' => $event->id,
            'section' => 'transactions',
        ]));

        $this->get(route('event-inventory.index', [
            'hub_id' => $hub->id,
            'event_id' => $event->id,
            'section' => 'transactions',
        ]))->assertOk()
            ->assertSee('Event Transaction Logs')
            ->assertSee('View details')
            ->assertSee('Stock entered Event inventory')
            ->assertSee('Stock sold from Event inventory')
            ->assertSee($sale->order_number)
            ->assertSee('Log Customer');

        $this->get(route('event-inventory.index', [
            'hub_id' => $hub->id,
            'event_id' => $event->id,
            'activity' => 'sale',
            'section' => 'transactions',
        ]))->assertOk()
            ->assertSee('Stock sold from Event inventory')
            ->assertDontSee('Stock entered Event inventory');
    }

    public function test_event_sale_rejects_more_than_five_item_images_and_a_mismatched_stock_condition(): void
    {
        Storage::fake('public');
        [$admin, $hub, $product] = $this->eventFixture();
        $event = $this->createEvent($admin, $hub, $product, 5);
        $line = $event->items()->sole();
        $basePayload = [
            'order_date' => '2026-10-15',
            'order_number' => 'EVENT-'.$event->id.'-20261015160000-IJ90',
            'mode_of_payment' => 'CASH',
            'items' => [[
                'event_pullout_item_id' => $line->id,
                'stock_condition' => 'good',
                'quantity' => 1,
                'unit_price' => 150,
            ]],
        ];

        $this->actingAs($admin)->post(route('event-inventory.sales.store', $event), $basePayload + [
            'item_images' => [
                $this->fakePng('one.png'),
                $this->fakePng('two.png'),
                $this->fakePng('three.png'),
                $this->fakePng('four.png'),
                $this->fakePng('five.png'),
                $this->fakePng('six.png'),
            ],
        ])->assertSessionHasErrors('item_images');

        $conditionMismatchPayload = $basePayload;
        $conditionMismatchPayload['items'][0]['stock_condition'] = 'damaged';
        $this->post(route('event-inventory.sales.store', $event), $conditionMismatchPayload)
            ->assertSessionHasErrors('items');

        $this->post(route('event-inventory.sales.store', $event), array_replace($basePayload, [
            'contact_number' => '12345',
        ]))->assertSessionHasErrors('contact_number');

        $this->post(route('event-inventory.sales.store', $event), array_replace($basePayload, [
            'mode_of_payment' => 'OTHERS',
        ]))->assertSessionHasErrors('custom_mop');

        $this->post(route('event-inventory.sales.store', $event), array_replace($basePayload, [
            'note' => str_repeat('R', 151),
        ]))->assertSessionHasErrors('note');

        $this->assertDatabaseCount('sales_transactions', 0);
    }

    public function test_event_inventory_routes_are_limited_to_inventory_management_roles(): void
    {
        [$admin, $hub, $product] = $this->eventFixture();
        $event = $this->createEvent($admin, $hub, $product, 2);
        $salesAssociate = User::factory()->create(['role' => 'sales_associate', 'hub_id' => $hub->id]);

        $this->actingAs($salesAssociate)
            ->get(route('event-inventory.index', ['hub_id' => $hub->id]))
            ->assertForbidden();

        $this->get(route('event-inventory.transactions', ['hub_id' => $hub->id]))
            ->assertForbidden();
        $this->get(route('event-inventory.sales.create', $event))
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

    private function fakePng(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Zhf8AAAAASUVORK5CYII=')
        );
    }
}
