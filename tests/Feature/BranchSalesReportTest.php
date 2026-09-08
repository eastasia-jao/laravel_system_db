<?php

namespace Tests\Feature;

use App\Models\{StoreHub, User, SalesTransaction};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchSalesReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_report_only_shows_walk_in_orders_with_view_buttons(): void
    {
        $hub = StoreHub::create(['name' => 'Branch', 'code' => 'branch', 'status' => 'active', 'is_head_office' => false]);
        $user = User::factory()->create(['role' => 'admin']);
        foreach (['walk_in', 'tiktok'] as $channel) {
            SalesTransaction::create(['store_hub_id' => $hub->id, 'channel_type' => $channel, 'order_date' => '2026-09-08', 'order_number' => $channel.'-order', 'customer_name' => $channel.' customer']);
        }
        foreach (['all', 'tiktok', 'walk_in'] as $channel) {
            $response = $this->actingAs($user)->get(route('hub.report', ['hub' => $hub->id, 'channel' => $channel]));
            $response->assertOk()->assertSee('Walk-In Sales Report')->assertSee('View order')
                ->assertSee('walk_in customer')->assertDontSee('tiktok customer');
            $this->assertSame('walk_in', $response->viewData('channel'));
            $this->assertSame(1, $response->viewData('totalTransactions'));
            $document = new \DOMDocument;
            @$document->loadHTML($response->getContent());
            $xpath = new \DOMXPath($document);
            $this->assertSame(0, $xpath->query('//a[contains(@href,"channel=tiktok") or contains(@href,"channel=all") or contains(@href,"channel=wholesale")]')->length);
            $this->assertSame(1, $xpath->query('//button[@data-bs-toggle="modal" and starts-with(@data-bs-target,"#walk-in-order-")]')->length);
        }
    }
}
