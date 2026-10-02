<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CHANNELS = ['online', 'wholesale', 'shopee', 'lazada', 'tiktok'];

    public function up(): void
    {
        $this->convert(false);
    }

    public function down(): void
    {
        $this->convert(true);
    }

    private function convert(bool $restoreStartingAllocations): void
    {
        DB::transaction(function () use ($restoreStartingAllocations) {
            $consumed = [];
            $sales = DB::table('transaction_items')
                ->join('sales_transactions', 'sales_transactions.id', '=', 'transaction_items.transaction_id')
                ->whereIn('sales_transactions.status', ['confirmed', 'completed'])
                ->select('transaction_items.product_id', 'sales_transactions.channel_type')
                ->selectRaw('SUM(transaction_items.quantity) AS quantity')
                ->groupBy('transaction_items.product_id', 'sales_transactions.channel_type')
                ->get();
            foreach ($sales as $sale) {
                $channel = $this->normalizeChannel($sale->channel_type);
                if (in_array($channel, self::CHANNELS, true)) {
                    $consumed[$sale->product_id][$channel] = ($consumed[$sale->product_id][$channel] ?? 0) + (int) $sale->quantity;
                }
            }

            $movements = DB::table('inventory_transactions')
                ->whereIn('type', ['return', 'replacement_return', 'replacement_out'])
                ->whereNotNull('channel')
                ->select('product_id', 'channel', 'type')
                ->selectRaw('SUM(quantity) AS quantity')
                ->groupBy('product_id', 'channel', 'type')
                ->get();
            foreach ($movements as $movement) {
                $channel = $this->normalizeChannel($movement->channel);
                if (! in_array($channel, self::CHANNELS, true)) {
                    continue;
                }
                $direction = $movement->type === 'replacement_out' ? 1 : -1;
                $consumed[$movement->product_id][$channel] = ($consumed[$movement->product_id][$channel] ?? 0)
                    + ($direction * (int) $movement->quantity);
            }

            DB::table('product_stock_allocations')->orderBy('id')->each(function ($allocation) use ($consumed, $restoreStartingAllocations) {
                $updates = [];
                foreach (self::CHANNELS as $channel) {
                    $used = max(0, (int) ($consumed[$allocation->product_id][$channel] ?? 0));
                    $updates[$channel] = $restoreStartingAllocations
                        ? (int) $allocation->{$channel} + $used
                        : max(0, (int) $allocation->{$channel} - $used);
                }
                $updates['updated_at'] = now();
                DB::table('product_stock_allocations')->where('id', $allocation->id)->update($updates);
            });
        });
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
