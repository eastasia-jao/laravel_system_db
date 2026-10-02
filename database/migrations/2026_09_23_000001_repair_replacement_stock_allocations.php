<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->adjustAllocations(false);
    }

    public function down(): void
    {
        $this->adjustAllocations(true);
    }

    private function adjustAllocations(bool $restoreOldBehavior): void
    {
        DB::transaction(function () use ($restoreOldBehavior) {
            DB::table('product_replacements')
                ->join('sales_transactions', 'sales_transactions.id', '=', 'product_replacements.transaction_id')
                ->where('product_replacements.status', 'approved')
                ->select([
                    'product_replacements.original_product_id',
                    'product_replacements.replacement_product_id',
                    'product_replacements.quantity',
                    'product_replacements.replacement_quantity',
                    'sales_transactions.channel_type',
                ])
                ->orderBy('product_replacements.id')
                ->each(function ($replacement) use ($restoreOldBehavior) {
                    $channel = $this->normalizeChannel($replacement->channel_type);
                    if (! in_array($channel, ['online', 'tiktok', 'wholesale'], true)) {
                        return;
                    }

                    $originalChange = (int) $replacement->quantity;
                    $replacementChange = (int) ($replacement->replacement_quantity ?: $replacement->quantity);
                    $this->changeAllocation(
                        (int) $replacement->original_product_id,
                        $channel,
                        $restoreOldBehavior ? $originalChange : -$originalChange
                    );
                    $this->changeAllocation(
                        (int) $replacement->replacement_product_id,
                        $channel,
                        $restoreOldBehavior ? -$replacementChange : $replacementChange
                    );
                });
        });
    }

    private function changeAllocation(int $productId, string $channel, int $change): void
    {
        $allocation = DB::table('product_stock_allocations')->where('product_id', $productId)->lockForUpdate()->first();
        if (! $allocation) {
            return;
        }

        DB::table('product_stock_allocations')->where('product_id', $productId)->update([
            $channel => max(0, (int) $allocation->{$channel} + $change),
            'updated_at' => now(),
        ]);
    }

    private function normalizeChannel(?string $channel): string
    {
        $normalized = str_replace(['-', ' '], '_', strtolower(trim((string) $channel)));

        return match ($normalized) {
            'online_order', 'online_sales', 'event', 'fully_booked' => 'online',
            default => $normalized,
        };
    }
};
