<?php

namespace App\Http\Controllers;

use App\Models\SalesTransaction;
use App\Models\StoreHub;
use App\Models\InventoryTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesReportController extends Controller
{
    public function report(Request $request, int $hub)
    {
        $user = auth()->user();
        if ($user && ! $user->canAccessHub($hub)) {
            abort(403, 'Unauthorized access to this store hub report.');
        }

        $validated = $request->validate([
            'channel' => ['nullable', 'string'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $hub = StoreHub::findOrFail($hub);
        if ($user && ! $hub->is_head_office && ! in_array($user->role, ['admin', 'inventory_staff'], true)
            && (int) $user->store_hub_id !== (int) $hub->id) {
            abort(403, 'Sales staff can only view the report for their designated store branch.');
        }
        $channel = $this->normalizeChannel($validated['channel'] ?? null);
        if (! $hub->is_head_office) {
            $channel = 'walk_in';
        } elseif (in_array($user?->role, ['sales_associate', 'sales_marketing_staff'], true)) {
            $assignedChannels = auth()->user()->sales_channels ?? [];

            if ($channel === 'all') {
                $channel = $this->normalizeChannel($assignedChannels[0] ?? null);
            }

            if ($channel === 'all' || ! auth()->user()->hasSalesChannel($channel)) {
                abort(403, 'You are not assigned to view this sales channel.');
            }
        }
        $dateFrom = $validated['date_from'] ?? null;
        $dateTo = $validated['date_to'] ?? null;

        $query = SalesTransaction::query()
            ->where('store_hub_id', $hub->id)
            ->with('items.product');

        if ($channel !== 'all') {
            $query->where(function ($builder) use ($channel) {
                $builder->whereRaw('LOWER(channel_type) = ?', [$channel])
                    ->orWhereRaw('LOWER(channel_type) = ?', [str_replace('_', '-', $channel)])
                    ->orWhereRaw('LOWER(channel_type) = ?', [str_replace('_', ' ', $channel)]);
            });
        }

        if ($dateFrom) {
            $query->whereDate('order_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('order_date', '<=', $dateTo);
        }

        $allTransactions = (clone $query)->orderBy('order_date')->get();
        $transactions = (clone $query)->orderByDesc('order_date')->paginate(20)->withQueryString();

        $resolveTotal = fn (SalesTransaction $transaction): float => $this->resolveReportedTotal($transaction);
        $grossSales = $allTransactions->sum(fn ($transaction) => $transaction->items->sum(
            fn ($item) => (float) $item->unit_price * (int) $item->quantity
        ));
        $itemNetSales = $allTransactions->sum(fn ($transaction) => $transaction->items->sum('line_total'));
        $totalSales = $allTransactions->sum($resolveTotal);
        $totalTransactions = $allTransactions->count();

        $metrics = [
            'gross_sales' => $grossSales,
            'discounts' => max(0, $grossSales - $itemNetSales),
            'total_sales' => $totalSales,
            'shipping_fees' => $allTransactions->sum('shipping_fee_amount'),
            'shipping_service_fees' => $allTransactions->sum('shipping_service_fee'),
            'refund_shipping_fees' => $allTransactions->sum('refund_shipping_fee'),
            'actual_platform_payout' => $allTransactions->sum('sales_after_transaction_fee'),
            'net_platform_payout' => $allTransactions->sum(fn ($transaction) => $transaction->netTikTokPayout() ?? 0),
            'customer_refund_amount' => $allTransactions->sum(fn ($transaction) => $transaction->completedCustomerRefunds()),
            'pending_payouts' => $allTransactions->whereNull('sales_after_transaction_fee')->count(),
            'proof_amount' => $allTransactions->sum('proof_amount'),
            'difference' => $allTransactions->sum(function ($transaction) use ($resolveTotal) {
                if ($transaction->proof_amount === null) {
                    return 0;
                }

                $saleAmount = (float) ($transaction->sub_total ?: $resolveTotal($transaction));

                return (float) $transaction->proof_amount
                    - $saleAmount
                    - (float) $transaction->shipping_fee_amount;
            }),
        ];

        $paymentBreakdown = $allTransactions
            ->groupBy(fn ($transaction) => strtoupper($transaction->mode_of_payment ?: 'UNSPECIFIED'))
            ->map(fn ($group) => $group->sum($resolveTotal))
            ->sortKeys();

        $salesByChannel = SalesTransaction::where('store_hub_id', $hub->id)
            ->when($dateFrom, fn ($builder) => $builder->whereDate('order_date', '>=', $dateFrom))
            ->when($dateTo, fn ($builder) => $builder->whereDate('order_date', '<=', $dateTo))
            ->with('items')
            ->get()
            ->groupBy(fn ($transaction) => $this->normalizeChannel($transaction->channel_type))
            ->map(function ($group, $channelName) use ($resolveTotal) {
                return (object) [
                    'channel_type' => $channelName,
                    'transaction_count' => $group->count(),
                    'total' => $group->sum($resolveTotal),
                ];
            })
            ->values();

        return view('hubs.report', compact(
            'hub',
            'channel',
            'dateFrom',
            'dateTo',
            'transactions',
            'allTransactions',
            'totalSales',
            'totalTransactions',
            'metrics',
            'paymentBreakdown',
            'salesByChannel',
        ));
    }

    public function updateTikTokFields(Request $request, int $hub, int $transaction)
    {
        $user = auth()->user();
        $storeHub = StoreHub::findOrFail($hub);

        if (! $user->canAccessHub($hub)) {
            abort(403, 'Unauthorized access to this store hub report.');
        }

        if (! $storeHub->is_head_office && ! in_array($user->role, ['admin', 'inventory_staff'], true)
            && (int) $user->store_hub_id !== (int) $storeHub->id) {
            abort(403, 'Sales staff can only update their designated store branch report.');
        }

        if ($user->role !== 'admin'
            && (! in_array($user->role, ['sales_associate', 'sales_marketing_staff'], true)
                || ! $user->hasSalesChannel('tiktok'))) {
            abort(403, 'You are not assigned to manage TikTok sales reports.');
        }

        $validated = $request->validate([
            'refund_shipping_fee' => ['nullable', 'numeric', 'min:0'],
            'sales_after_transaction_fee' => ['nullable', 'numeric'],
            'payout_includes_refunds' => ['required', 'boolean'],
            'drop_off_date' => ['nullable', 'date'],
            'courier' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $salesTransaction = SalesTransaction::whereKey($transaction)
            ->where('store_hub_id', $storeHub->id)
            ->whereRaw('LOWER(channel_type) = ?', ['tiktok'])
            ->firstOrFail();

        $salesTransaction->update([
            'refund_shipping_fee' => $validated['refund_shipping_fee'] ?? 0,
            'sales_after_transaction_fee' => $validated['sales_after_transaction_fee'] ?? null,
            'payout_includes_refunds' => $validated['payout_includes_refunds'],
            'drop_off_date' => $validated['drop_off_date'] ?? null,
            'courier' => $validated['courier'] ?? null,
            'note' => $validated['note'] ?? null,
        ]);

        return back()->with('success', 'TikTok report details updated.');
    }

    public function updateTikTokReturn(Request $request, int $hub, int $transaction, int $item)
    {
        $user = auth()->user();
        $storeHub = StoreHub::findOrFail($hub);

        if (! $user->canAccessHub($hub)) {
            abort(403, 'Unauthorized access to this store hub report.');
        }

        if (! $storeHub->is_head_office && ! in_array($user->role, ['admin', 'inventory_staff'], true)
            && (int) $user->store_hub_id !== (int) $storeHub->id) {
            abort(403, 'Sales staff can only update their designated store branch report.');
        }

        if ($user->role !== 'admin'
            && (! in_array($user->role, ['inventory_staff', 'sales_associate', 'sales_marketing_staff'], true)
                || ($user->role !== 'inventory_staff' && ! $user->hasSalesChannel('tiktok')))) {
            abort(403, 'You are not assigned to manage TikTok returns.');
        }

        $validated = $request->validate([
            'return_status' => ['required', 'in:none,requested,received,rejected,refund_only'],
            'return_condition' => ['nullable', 'required_if:return_status,received', 'in:good,damaged'],
            'refund_status' => ['required', 'in:none,pending,completed,rejected'],
            'returned_quantity' => ['required', 'integer', 'min:0'],
            'customer_refund_amount' => ['nullable', 'numeric', 'min:0'],
            'return_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($validated, $storeHub, $transaction, $item) {
            $salesTransaction = SalesTransaction::whereKey($transaction)
                ->where('store_hub_id', $storeHub->id)
                ->whereRaw('LOWER(channel_type) = ?', ['tiktok'])
                ->firstOrFail();
            $transactionItem = $salesTransaction->items()->whereKey($item)->lockForUpdate()->firstOrFail();

            $quantity = (int) $validated['returned_quantity'];
            $status = $validated['return_status'];
            $condition = $status === 'received' ? $validated['return_condition'] : null;
            $refund = (float) ($validated['customer_refund_amount'] ?? 0);
            $fail = fn ($field, $message) => throw ValidationException::withMessages([$field => $message]);
            if ($quantity > (int) $transactionItem->quantity) {
                $fail('returned_quantity', 'Returned quantity cannot exceed the sold quantity.');
            }
            if (in_array($status, ['requested', 'received']) && $quantity < 1) {
                $fail('returned_quantity', 'Enter the quantity being returned.');
            }
            if (in_array($status, ['none', 'refund_only']) && $quantity !== 0) {
                $fail('returned_quantity', 'Use zero quantity when no item is being returned.');
            }
            if ($refund > (float) $transactionItem->line_total) {
                $fail('customer_refund_amount', 'The item refund cannot exceed its total after discount. Record shipping separately.');
            }
            if (in_array($validated['refund_status'], ['pending', 'completed']) && ($refund <= 0 || in_array($status, ['none', 'rejected']))) {
                $fail('refund_status', 'Select a return or refund-only request and enter a positive refund amount.');
            }
            if (in_array($validated['refund_status'], ['none', 'rejected']) && $refund != 0) {
                $fail('customer_refund_amount', 'Use zero refund amount for no refund or a rejected refund.');
            }
            if ($transactionItem->return_status === 'received' && ($status !== 'received'
                || $quantity !== (int) $transactionItem->returned_quantity || $condition !== $transactionItem->return_condition)) {
                $fail('returned_quantity', 'Received quantity and condition are locked because the inventory movement is already recorded. You can still update the refund.');
            }

            $wasReceived = $transactionItem->return_status === 'received';
            $willBeReceived = $validated['return_status'] === 'received';
            if (! $wasReceived && $willBeReceived && $quantity > 0) {
                $product = $transactionItem->product()->lockForUpdate()->firstOrFail();
                if ($condition === 'good') {
                    $product->increment('stock', $quantity);
                }

                InventoryTransaction::create([
                    'type' => 'return',
                    'reference' => $salesTransaction->order_number,
                    'store_hub_id' => $storeHub->id,
                    'product_id' => $product->id,
                    'channel' => 'tiktok',
                    'source' => 'TikTok customer return',
                    'condition' => $condition,
                    'quantity' => $quantity,
                    'occurred_on' => now()->toDateString(),
                    'notes' => $validated['return_reason'] ?? 'TikTok customer return received.',
                    'created_by' => auth()->id(),
                ]);
            }

            $transactionItem->update([
                'returned_quantity' => $quantity,
                'return_status' => $validated['return_status'],
                'return_condition' => $condition,
                'refund_status' => $validated['refund_status'],
                'customer_refund_amount' => $validated['customer_refund_amount'] ?? 0,
                'returned_at' => $willBeReceived ? ($transactionItem->returned_at ?? now()) : null,
                'return_reason' => $validated['return_reason'] ?? null,
            ]);
        });

        return back()->with('success', 'TikTok return details updated.');
    }

    public function wholesaleReport(int $hub)
    {
        StoreHub::findOrFail($hub);

        return redirect()->route('hub.report', ['hub' => $hub, 'channel' => 'wholesale']);
    }

    private function normalizeChannel(?string $channel): string
    {
        $normalized = strtolower(trim((string) $channel));
        $normalized = str_replace(['-', ' '], '_', $normalized);
        $normalized = str_replace(['_sales', '_orders'], '', $normalized);

        return in_array($normalized, ['shopee', 'lazada', 'tiktok', 'online', 'wholesale', 'walk_in'], true)
            ? $normalized
            : 'all';
    }

    private function resolveTotal(SalesTransaction $transaction): float
    {
        $headerTotal = max((float) $transaction->grand_total, (float) $transaction->total_amount, 0);

        return $headerTotal > 0
            ? $headerTotal
            : (float) $transaction->items->sum('line_total');
    }

    private function resolveReportedTotal(SalesTransaction $transaction): float
    {
        if ($this->normalizeChannel($transaction->channel_type) === 'wholesale') {
            return min(
                $this->resolveTotal($transaction),
                max(0, (float) ($transaction->amount_paid ?? 0))
            );
        }

        return $this->resolveTotal($transaction);
    }
}
