<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Legacy walk-in sales reduced physical stock without checking whether
        // all physical units had already been reserved for allocated channels.
        DB::table('product_stock_allocations')
            ->join('products', 'products.id', '=', 'product_stock_allocations.product_id')
            ->select('product_stock_allocations.*', 'products.stock')
            ->orderBy('product_stock_allocations.id')
            ->each(function ($allocation) {
                $channels = ['online', 'wholesale', 'shopee', 'lazada', 'tiktok'];
                $excess = max(0, collect($channels)->sum(fn ($channel) => (int) $allocation->{$channel}) - (int) $allocation->stock);
                if ($excess === 0) {
                    return;
                }

                $updates = [];
                foreach ($channels as $channel) {
                    $deduction = min($excess, (int) $allocation->{$channel});
                    $updates[$channel] = (int) $allocation->{$channel} - $deduction;
                    $excess -= $deduction;
                    if ($excess === 0) {
                        break;
                    }
                }
                DB::table('product_stock_allocations')->where('id', $allocation->id)->update($updates);
            });
    }

    public function down(): void
    {
        // The repaired values represent valid live balances and are not inflated again.
    }
};
