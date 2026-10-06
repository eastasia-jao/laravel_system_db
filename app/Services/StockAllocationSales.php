<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StockAllocationSales
{
    public function soldByProduct(iterable $productIds, ?string $soldFrom = null, ?string $soldTo = null): Collection
    {
        $productIds = collect($productIds)->values();
        if ($productIds->isEmpty()) {
            return collect();
        }

        $sales = DB::table('transaction_items')
            ->join('sales_transactions', 'sales_transactions.id', '=', 'transaction_items.transaction_id')
            ->whereIn('transaction_items.product_id', $productIds)
            ->when($soldFrom && $soldTo, fn ($query) => $query
                ->whereDate('sales_transactions.order_date', '>=', $soldFrom)
                ->whereDate('sales_transactions.order_date', '<=', $soldTo))
            ->where(function ($query) {
                $query->whereNull('sales_transactions.status')
                    ->orWhereNotIn('sales_transactions.status', ['cancelled', 'rejected']);
            })
            ->select('transaction_items.product_id', 'sales_transactions.channel_type', DB::raw('SUM(transaction_items.quantity) as quantity'))
            ->groupBy('transaction_items.product_id', 'sales_transactions.channel_type')
            ->get()
            ->groupBy('product_id')
            ->map(function ($rows) {
                return $rows->reduce(function ($totals, $row) {
                    $channel = $this->normalizeChannel($row->channel_type);
                    $totals[$channel] = ($totals[$channel] ?? 0) + (int) $row->quantity;

                    return $totals;
                }, collect());
            });

        $movements = DB::table('inventory_transactions')
            ->whereIn('product_id', $productIds)
            ->when($soldFrom && $soldTo, fn ($query) => $query
                ->whereDate('occurred_on', '>=', $soldFrom)
                ->whereDate('occurred_on', '<=', $soldTo))
            ->whereIn('type', ['return', 'replacement_return', 'replacement_out'])
            ->whereNotNull('channel')
            ->select('product_id', 'channel', 'type')
            ->selectRaw('SUM(quantity) AS quantity')
            ->groupBy('product_id', 'channel', 'type')
            ->get();

        foreach ($movements as $movement) {
            $productTotals = $sales->get($movement->product_id, collect());
            $channel = $this->normalizeChannel($movement->channel);
            $quantity = (int) $movement->quantity;
            $current = (int) $productTotals->get($channel, 0);

            $productTotals[$channel] = $movement->type === 'replacement_out'
                ? $current + $quantity
                : max(0, $current - $quantity);
            $sales->put($movement->product_id, $productTotals);
        }

        return $sales;
    }

    private function normalizeChannel(?string $channel): string
    {
        $normalized = str_replace(['-', ' '], '_', strtolower(trim((string) $channel)));

        return match ($normalized) {
            'online_order', 'online_sales', 'event', 'fully_booked' => 'online',
            'walk_in', 'walkin' => 'walk_in',
            'wholesale', 'shopee', 'lazada', 'tiktok' => $normalized,
            default => 'online',
        };
    }
}
