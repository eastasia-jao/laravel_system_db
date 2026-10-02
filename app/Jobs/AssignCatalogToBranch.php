<?php

namespace App\Jobs;

use App\Models\CatalogProduct;
use App\Models\StoreHub;
use App\Models\User;
use App\Notifications\InventoryWorkflowNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class AssignCatalogToBranch implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public int $hubId, public int $userId, public int $sourceHubId)
    {
        $this->onConnection(config('inventory.queue_connection', 'inventory'));
        $this->onQueue('product-files');
    }

    public function handle(): void
    {
        $user = User::findOrFail($this->userId);
        abort_unless($user->can('manage-inventory'), 403);
        $hub = StoreHub::where('status', 'active')
            ->where('is_head_office', false)
            ->findOrFail($this->hubId);
        DB::transaction(function () use ($user, $hub) {
            StoreHub::whereKey($hub->id)->lockForUpdate()->firstOrFail();
            $added = 0;
            $log = \App\Models\StaffActivityLog::create([
                'user_id' => $user->id, 'store_hub_id' => $hub->id,
                'action_type' => 'catalog_assignment', 'description' => 'Assigned entire shared catalog to branch.',
            ]);
            CatalogProduct::chunkById(100, function ($catalog) use (&$added, $log) {
                $now = now();
                $existing = DB::table('products')->where('store_hub_id', $this->hubId)->whereIn('catalog_product_id', $catalog->pluck('id'))->pluck('catalog_product_id')->all();
                $rows = $catalog->reject(fn ($item) => in_array($item->id, $existing))->map(fn ($item) => [
                    'catalog_product_id' => $item->id, 'store_hub_id' => $this->hubId,
                    'stock' => 0, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
                ])->all();
                if ($rows) {
                    DB::table('products')->insert($rows);
                    $added += count($rows);
                }
                $products = DB::table('products')->where('store_hub_id', $this->hubId)->whereIn('catalog_product_id', $catalog->pluck('id'))->get()->keyBy('catalog_product_id');
                $log->items()->insert($catalog->map(fn ($item) => [
                    'staff_activity_log_id' => $log->id, 'product_id' => $products[$item->id]->id,
                    'item_id' => $item->item_id, 'product_name' => $item->name,
                    'operation' => in_array($item->id, $existing) ? 'skipped' : 'created',
                    'quantity' => 0, 'stock_before' => $products[$item->id]->stock, 'stock_after' => $products[$item->id]->stock,
                ])->all());
            });
            $log->update(['description' => "$added shared catalog products added to branch; existing products preserved.", 'details' => ['created_count' => $added, 'scope' => 'entire_catalog']]);
            $user->notify(new InventoryWorkflowNotification('catalog_assignment', "$added catalog products added to {$hub->name} with zero stock. Existing items were preserved.", $hub->id, route('products.index', ['hub_id' => $hub->id])));
        });
    }

    public function failed(?\Throwable $exception): void
    {
        User::find($this->userId)?->notify(new InventoryWorkflowNotification('catalog_assignment', 'Catalog assignment failed. No branch products were added. Please try again or contact the administrator.', $this->hubId, route('catalog.index', ['hub_id' => $this->sourceHubId])));
    }
}
