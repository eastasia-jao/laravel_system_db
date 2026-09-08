<?php

namespace Tests\Feature;

use App\Models\SalesTransaction;
use App\Models\StoreHub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WholesalePaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_submits_partial_payment_status_and_amount(): void
    {
        $hub = StoreHub::create(['name' => 'Wholesale Hub', 'code' => 'WH', 'status' => 'active']);
        $user = User::factory()->create(['role' => 'admin']);
        $sale = SalesTransaction::create([
            'user_id' => $user->id, 'store_hub_id' => $hub->id, 'channel_type' => 'wholesale',
            'customer_name' => 'Customer', 'order_number' => 'PARTIAL-1', 'order_date' => '2026-09-08',
            'grand_total' => 547.20, 'status' => 'confirmed', 'payment_status' => 'unpaid',
            'amount_paid' => 0, 'delivery_status' => 'pending',
        ]);
        $report = route('hub.report', ['hub' => $hub->id, 'channel' => 'wholesale']);
        $html = $this->actingAs($user)->get($report)->assertOk()->getContent();
        $document = new \DOMDocument;
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//form[contains(@class,"status-update-form")]//select[@name="payment_status" and not(@disabled)]')->length);
        $this->from($report)->patch(route('sales.status.update', $sale->id), [
            'payment_status' => 'partial', 'amount_paid' => '47.20', 'delivery_status' => 'pending',
        ])->assertRedirect($report)->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame('partial', $sale->fresh()->payment_status);
        $this->assertSame('47.20', $sale->fresh()->amount_paid);
        $this->get($report)->assertOk()->assertSee('PARTIAL')->assertViewHas('totalSales', 47.20);
    }
}
