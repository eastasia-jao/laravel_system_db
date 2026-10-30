<?php

namespace Tests\Feature;

use App\Models\EventPullout;
use App\Models\Product;
use App\Models\ProductStockAllocation;
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

    public function test_empty_event_inventory_omits_duplicate_create_button(): void
    {
        [$admin, $hub] = $this->eventFixture();

        $inventoryPage = $this->actingAs($admin)
            ->get(route('event-inventory.index', ['hub_id' => $hub->id]))
            ->assertOk()
            ->assertSee(route('inventory-transactions.sponsor.create', [
                'hub_id' => $hub->id,
                'activity_type' => 'event',
                'return_to' => 'event_inventory',
            ]))
            ->assertSee(route('inventory-transactions.return.create', [
                'hub_id' => $hub->id,
                'channel' => 'event',
                'return_to' => 'event_inventory',
                'return_section' => 'overview',
            ]))
            ->assertSee('No open events found')
            ->assertDontSee('>Create Event Pull-Out</a>', false);
        $inventoryPage->assertSee('event-inventory-actions')
            ->assertSee('event-workspace-tabs')
            ->assertSee('event-overview-filters')
            ->assertDontSee('nav-link flex-fill text-center');

        $this->get(route('inventory-transactions.return.create', [
            'hub_id' => $hub->id,
            'channel' => 'event',
            'return_to' => 'event_inventory',
            'return_section' => 'transactions',
        ]))->assertOk()
            ->assertSee(route('event-inventory.index', [
                'hub_id' => $hub->id,
                'section' => 'transactions',
            ]));

        $this->get(route('inventory-transactions.return.create', ['hub_id' => $hub->id]))
            ->assertOk()
            ->assertSee('href="'.route('inventory-transactions.index', ['hub_id' => $hub->id]).'" class="btn btn-outline-secondary">Cancel</a>', false);
    }

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
            ->assertSee('<title>Event Inventory</title>', false)
            ->assertSee('<h1 class="topbar-title">Event Inventory</h1>', false)
            ->assertSee('Event Inventory')
            ->assertSee('October Art Fair')
            ->assertSee($event->reference)
            ->assertSee('<div class="label">Product Items</div><div class="value">1</div>', false)
            ->assertSee('Pulled Items')
            ->assertSee('Sold Items')
            ->assertSee('Returned Items')
            ->assertSee('Remaining Items')
            ->assertSee('Missing Items')
            ->assertSee('Record Event Sale')
            ->assertSee('event-ledger-items')
            ->assertSee('event-ledger-product-search-input')
            ->assertSee('Choose Good or Damaged, then search by item code, barcode, or description.')
            ->assertSee('list="popupEventProductOptions"', false)
            ->assertSee('Search item code, barcode, or description')
            ->assertSee('01 · Order details')
            ->assertSee('02 · Order items')
            ->assertSee('03 · Payment and totals')
            ->assertSee('04 · Attachments')
            ->assertSee('Order Slip')
            ->assertSee('Up to 5 images, maximum 2 MB each.')
            ->assertSee('Coupon / Voucher')
            ->assertSee('event_promotion_attachment')
            ->assertSee('Proof of Payment')
            ->assertSee('PayMaya')
            ->assertSee('Specify Payment Method')
            ->assertSee('eventSaleImageViewer')
            ->assertSee('id="popupAddress"', false)
            ->assertSee('data-character-count', false)
            ->assertSee('maxlength="150"', false)
            ->assertSee('event-sale-remarks')
            ->assertSee('popup-event-price-display')
            ->assertSee('popupEventPromotionType')
            ->assertSee('popupEventPromotionValueType')
            ->assertSee('popupEventPromotionValue')
            ->assertSee('popupEventPromotionUnavailable')
            ->assertSee('closeEventConfirmModal')
            ->assertSee('Any remaining quantity will be recorded as missing.')
            ->assertSee('Close Event')
            ->assertDontSee('onsubmit="return confirm(', false)
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
            ->assertSee('event-log-products-list')
            ->assertSee('Good: 5')
            ->assertSee('Damaged: 2');
        $this->assertSame(1, substr_count(
            $logResponse->getContent(),
            'data-event-log-product="'.$product->id.'"'
        ));
    }

    public function test_event_pullout_excludes_allocated_good_stock_but_keeps_damaged_stock_available(): void
    {
        [$admin, $hub, $product] = $this->eventFixture();
        $allocation = ProductStockAllocation::create([
            'product_id' => $product->id,
            'online' => 1,
            'wholesale' => 1,
            'shopee' => 1,
            'lazada' => 1,
            'tiktok' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('inventory-transactions.sponsor.create', [
                'hub_id' => $hub->id,
                'activity_type' => 'event',
            ]))
            ->assertOk()
            ->assertViewHas('sponsorProductOptions', function ($options) use ($product): bool {
                $option = $options->firstWhere('id', $product->id);

                return $option !== null
                    && $option['good_stock_available'] === 5
                    && $option['damaged_stock_available'] === 4
                    && str_ends_with($option['label'], '(stock: 5)');
            });

        $this->getJson(route('hub.products.search.ajax', [
            'hubId' => $hub->id,
            'q' => 'EVENT-ART-001',
            'event_pullout' => 1,
        ]))
            ->assertOk()
            ->assertJsonPath('0.good_stock_available', 5)
            ->assertJsonPath('0.damaged_stock_available', 4);

        $this->post(route('inventory-transactions.store'), [
            'type' => 'sponsor_workshop',
            'store_hub_id' => $hub->id,
            'occurred_on' => '2026-10-15',
            'source' => 'October Art Fair',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 6,
                'stock_condition' => 'good',
            ]],
        ])->assertSessionHasErrors('items');

        $this->assertSame(10, (int) $product->fresh()->stock);
        $this->assertDatabaseMissing('event_pullouts', ['store_hub_id' => $hub->id]);

        $this->post(route('inventory-transactions.store'), [
            'type' => 'sponsor_workshop',
            'store_hub_id' => $hub->id,
            'occurred_on' => '2026-10-15',
            'source' => 'October Art Fair',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 5, 'stock_condition' => 'good'],
                ['product_id' => $product->id, 'quantity' => 1, 'stock_condition' => 'damaged'],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(5, (int) $product->fresh()->stock);
        $this->assertSame(3, (int) $product->fresh()->damaged_stock);
        $this->assertSame([
            'online' => 1,
            'wholesale' => 1,
            'shopee' => 1,
            'lazada' => 1,
            'tiktok' => 1,
        ], $allocation->fresh()->only(['online', 'wholesale', 'shopee', 'lazada', 'tiktok']));
        $this->assertDatabaseHas('event_pullout_items', [
            'product_id' => $product->id,
            'stock_condition' => 'good',
            'quantity' => 5,
        ]);
        $this->assertDatabaseHas('event_pullout_items', [
            'product_id' => $product->id,
            'stock_condition' => 'damaged',
            'quantity' => 1,
        ]);
    }

    public function test_event_sale_reduces_only_event_stock_and_return_limit_accounts_for_sold_items(): void
    {
        config(['filesystems.attachment_disks.events' => 'event-attachments']);
        Storage::fake('event-attachments');
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
            'event_promotion_type' => 'voucher',
            'event_promotion_value_type' => 'amount',
            'event_promotion_value' => 25,
            'order_slip_image' => $this->fakePng('order-slip.png'),
            'item_images' => [
                $this->fakePng('event-1.png'),
                $this->fakePng('event-2.png'),
                $this->fakePng('event-3.png'),
            ],
            'event_promotion_attachment' => UploadedFile::fake()->createWithContent(
                'voucher.pdf',
                "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF"
            ),
            'proof_of_payment_image' => $this->fakePng('payment.png'),
            'items' => [[
                'event_pullout_item_id' => $line->id,
                'stock_condition' => 'good',
                'quantity' => 2,
                'unit_price' => 1,
                'discount_percentage' => 0,
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
        $this->assertSame('300.00', $sale->sub_total);
        $this->assertSame('voucher', $sale->event_promotion_type);
        $this->assertSame('amount', $sale->event_promotion_value_type);
        $this->assertSame('25.00', $sale->event_promotion_value);
        $this->assertSame('25.00', $sale->event_promotion_discount_amount);
        $this->assertSame('260.00', $sale->grand_total);
        $this->assertSame('260.00', $sale->amount_paid);
        $this->assertNotEmpty($sale->event_attachments['order_slip']);
        $this->assertCount(3, $sale->event_attachments['item_images']);
        $this->assertNotEmpty($sale->event_attachments['promotion_proof']);
        $this->assertNotEmpty($sale->event_attachments['proof_of_payment']);
        foreach (collect($sale->event_attachments)->flatten() as $attachment) {
            Storage::disk('event-attachments')->assertExists($attachment);
        }
        $this->assertEquals(0.00, $sale->items()->sole()->discount_percentage);
        $this->assertEquals(150.00, $sale->items()->sole()->unit_price);
        $this->assertEquals(300.00, $sale->items()->sole()->line_total);
        $this->assertSame($stockAfterPullout, (int) $product->fresh()->stock);
        $this->assertDatabaseHas('inventory_transactions', [
            'event_pullout_id' => $event->id,
            'event_pullout_item_id' => $line->id,
            'type' => 'sold',
            'channel' => 'event',
            'quantity' => 2,
        ]);

        $inventoryOrderSlipUrl = route('inventory-transactions.sales-order-slip', $sale->id);
        $this->get(route('inventory-transactions.index', [
            'hub_id' => $hub->id,
            'type' => 'sold',
            'month' => '2026-10',
        ]))->assertOk()
            ->assertSee('Order Attachment')
            ->assertSee($inventoryOrderSlipUrl, false)
            ->assertSee('sold-order-image-preview', false)
            ->assertSee('inventory-order-image-viewer', false);
        $this->get($inventoryOrderSlipUrl)->assertOk()->assertHeader('Content-Type', 'image/png');

        $this->get(route('event-inventory.index', [
            'hub_id' => $hub->id,
            'event_id' => $event->id,
            'section' => 'transactions',
        ]))->assertOk()
            ->assertSee('Cooperative Voucher')
            ->assertSee('Coupon / Voucher')
            ->assertSee('Voucher')
            ->assertSee('Deduction: -&#8369;25.00', false)
            ->assertSee('Order Slip Image')
            ->assertSee('Item Images')
            ->assertSee('Coupon / Voucher Attachment')
            ->assertSee('Proof of Payment')
            ->assertSee('event-log-attachment-zoom')
            ->assertSee('eventLogImageViewer')
            ->assertSee('cursor: zoom-in')
            ->assertSee('overflow-y: auto')
            ->assertSee('max-height: min(38vh, 320px)');
        $this->get(route('event-inventory.sales.attachment', [
            'transaction' => $sale->id,
            'type' => 'order-slip',
            'index' => 0,
        ]))->assertOk();
        $promotionAttachmentResponse = $this->get(route('event-inventory.sales.attachment', [
            'transaction' => $sale->id,
            'type' => 'promotion-proof',
            'index' => 0,
        ]));
        $this->assertSame(200, $promotionAttachmentResponse->getStatusCode(), $promotionAttachmentResponse->getContent());
        $promotionAttachmentResponse->assertHeader('Content-Type', 'application/pdf');

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

    public function test_event_sale_applies_a_percentage_coupon_after_existing_discounts(): void
    {
        [$admin, $hub, $product] = $this->eventFixture();
        $event = $this->createEvent($admin, $hub, $product, 3);
        $line = $event->items()->sole();

        $this->actingAs($admin)->post(route('event-inventory.sales.store', $event), [
            'order_date' => '2026-10-15',
            'order_number' => 'EVENT-'.$event->id.'-20261015123000-CD34',
            'mode_of_payment' => 'CASH',
            'additional_discount_percentage' => 10,
            'event_promotion_type' => 'coupon',
            'event_promotion_value_type' => 'percentage',
            'event_promotion_value' => 10,
            'items' => [[
                'event_pullout_item_id' => $line->id,
                'stock_condition' => 'good',
                'quantity' => 1,
                'unit_price' => 150,
                'discount_percentage' => 0,
            ]],
        ])->assertSessionHasNoErrors();

        $sale = SalesTransaction::where('event_pullout_id', $event->id)->sole();
        $this->assertSame('150.00', $sale->sub_total);
        $this->assertSame('coupon', $sale->event_promotion_type);
        $this->assertSame('percentage', $sale->event_promotion_value_type);
        $this->assertSame('10.00', $sale->event_promotion_value);
        $this->assertSame('13.50', $sale->event_promotion_discount_amount);
        $this->assertSame('121.50', $sale->grand_total);
        $this->assertSame('121.50', $sale->amount_paid);
    }

    public function test_event_sale_rejects_coupon_or_voucher_when_a_product_item_has_a_discount(): void
    {
        [$admin, $hub, $product] = $this->eventFixture();
        $event = $this->createEvent($admin, $hub, $product, 3);
        $line = $event->items()->sole();
        $basePayload = [
            'order_date' => '2026-10-15',
            'order_number' => 'EVENT-'.$event->id.'-20261015124500-EF56',
            'mode_of_payment' => 'CASH',
            'items' => [[
                'event_pullout_item_id' => $line->id,
                'stock_condition' => 'good',
                'quantity' => 1,
                'unit_price' => 150,
                'discount_percentage' => 10,
            ]],
        ];

        foreach ([
            ['event_promotion_type' => 'coupon', 'event_promotion_value_type' => 'amount', 'event_promotion_value' => 10],
            ['event_promotion_type' => 'voucher', 'event_promotion_value_type' => 'percentage', 'event_promotion_value' => 10],
        ] as $promotion) {
            $this->actingAs($admin)
                ->post(route('event-inventory.sales.store', $event), array_replace($basePayload, $promotion))
                ->assertSessionHasErrors('event_promotion_type');
        }

        $this->assertDatabaseCount('sales_transactions', 0);
    }

    public function test_event_return_rejects_returning_damaged_pullout_stock_as_good(): void
    {
        [$admin, $hub, $product] = $this->eventFixture();

        $this->actingAs($admin)->post(route('inventory-transactions.store'), [
            'type' => 'sponsor_workshop',
            'store_hub_id' => $hub->id,
            'occurred_on' => '2026-10-15',
            'source' => 'Damaged Stock Event',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 2,
                'pullout_qty' => 2,
                'stock_condition' => 'damaged',
            ]],
        ])->assertSessionHasNoErrors();

        $event = EventPullout::sole();
        $stockAfterPullout = (int) $product->fresh()->stock;
        $damagedStockAfterPullout = (int) $product->fresh()->damaged_stock;
        $returnPayload = [
            'type' => 'return',
            'store_hub_id' => $hub->id,
            'occurred_on' => '2026-10-15',
            'channel' => 'event',
            'event_reference' => $event->reference,
            'items' => [[
                'product_id' => $product->id,
                'good_quantity' => 1,
                'damaged_quantity' => 0,
            ]],
        ];

        $returnFormUrl = route('inventory-transactions.return.create', [
            'hub_id' => $hub->id,
            'channel' => 'event',
            'event_reference' => $event->reference,
        ]);
        $this->from($returnFormUrl)
            ->post(route('inventory-transactions.return.store'), $returnPayload)
            ->assertRedirect($returnFormUrl)
            ->assertSessionHasErrors([
                'items' => 'Good return quantity for Event Artwork exceeds the remaining Good Event stock. Only 0 Good item(s) can be returned.',
            ]);
        $this->get($returnFormUrl)
            ->assertDontSee('<div class="alert alert-danger"', false)
            ->assertSee('window.AppAlert.show(message.text', false)
            ->assertSee('Only 0 Good item(s) can be returned.');
        $this->assertSame($stockAfterPullout, (int) $product->fresh()->stock);
        $this->assertSame($damagedStockAfterPullout, (int) $product->fresh()->damaged_stock);

        $returnPayload['items'][0]['good_quantity'] = 0;
        $returnPayload['items'][0]['damaged_quantity'] = 1;
        $this->post(route('inventory-transactions.return.store'), $returnPayload)
            ->assertSessionHasNoErrors();
        $this->assertSame($stockAfterPullout, (int) $product->fresh()->stock);
        $this->assertSame($damagedStockAfterPullout + 1, (int) $product->fresh()->damaged_stock);
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
        $this->getJson(route('inventory-transactions.return.sales', [
            'hub_id' => $hub->id,
            'channel' => 'event',
            'event_reference' => $event->reference,
        ]))->assertOk()
            ->assertJsonPath('transaction.items.0.remaining_good_quantity', 2)
            ->assertJsonPath('transaction.items.0.remaining_damaged_quantity', 1);
        $this->assertSame($goodStockAfterPullout, (int) $product->fresh()->stock);
        $this->assertSame($damagedStockAfterPullout, (int) $product->fresh()->damaged_stock);

        $this->post(route('inventory-transactions.return.store'), [
            'type' => 'return',
            'store_hub_id' => $hub->id,
            'occurred_on' => '2026-10-15',
            'channel' => 'event',
            'event_reference' => $event->reference,
            'items' => [[
                'product_id' => $product->id,
                'good_quantity' => 0,
                'damaged_quantity' => 1,
            ]],
        ])->assertSessionHasNoErrors();

        $this->get(route('event-inventory.index', ['hub_id' => $hub->id]))
            ->assertOk()
            ->assertSee('Good returned 0', false)
            ->assertSee('Damaged returned 1', false)
            ->assertSee('Good 2', false)
            ->assertSee('Damaged 0', false);
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
            ->assertSee('event-log-products-list')
            ->assertSee('data-bs-backdrop="static" data-bs-keyboard="false"', false)
            ->assertDontSee('class="btn-close"')
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
            ->assertSee('<div class="event-log-hero event-log-sale-hero p-3 mb-3">', false)
            ->assertSee('<div class="event-log-sale-customer-label">Customer</div>', false)
            ->assertSee('Log Customer')
            ->assertSee('Sale Total')
            ->assertSee('Payment')
            ->assertSee('<strong>Log Customer</strong>', false)
            ->assertDontSee('Inventory Effect')
            ->assertSee('event-log-products-list')
            ->assertDontSee('<div class="small text-muted mt-1">Stock sold from Event inventory</div>', false)
            ->assertDontSee('unique product')
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

        $this->post(route('event-inventory.sales.store', $event), array_replace($basePayload, [
            'event_promotion_type' => 'coupon',
            'event_promotion_value_type' => 'percentage',
            'event_promotion_value' => 101,
        ]))->assertSessionHasErrors('event_promotion_value');

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
