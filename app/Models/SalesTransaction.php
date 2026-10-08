<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesTransaction extends Model
{
    protected $table = 'sales_transactions';

    protected $guarded = ['id'];

    protected $casts = [
        'quotation_proofs' => 'array',
        'walkin_payment_proofs' => 'array',
        'order_date' => 'date',
        'delivery_date' => 'date',
        'date_of_arrangement' => 'date',
        'check_date' => 'date',
        'shipping_fee_amount' => 'decimal:2',
        'shipping_service_fee' => 'decimal:2',
        'sales_after_transaction_fee' => 'decimal:2',
        'tiktok_recalculated_payout' => 'decimal:2',
        'additional_discount_percentage' => 'decimal:2',
        'withholding_tax' => 'decimal:2',
        'withholding_tax_amount' => 'decimal:2',
        'wholesale_withholding_tax' => 'decimal:2',
        'sub_total' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'refund_shipping_fee' => 'decimal:2',
        'proof_amount' => 'decimal:2',
        'drop_off_date' => 'date',
        'tiktok_recalculated_payout_entered' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(TransactionItem::class, 'transaction_id');
    }

    public function replacements(): HasMany
    {
        return $this->hasMany(ProductReplacement::class, 'transaction_id');
    }

    public function paymentRecords(): HasMany
    {
        return $this->hasMany(SalesPaymentRecord::class, 'sales_transaction_id');
    }

    public function inventoryReturns(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class, 'sales_transaction_id')
            ->where('type', 'return');
    }

    public function completedCustomerRefunds(): float
    {
        return (float) $this->items->where('refund_status', 'completed')->sum('customer_refund_amount');
    }

    public function customerIdentityKey(): ?string
    {
        $contactNumber = preg_replace('/\D+/', '', (string) $this->contact_number);
        if ($contactNumber !== '') {
            if (strlen($contactNumber) > 2 && str_starts_with($contactNumber, '63')) {
                $nationalNumber = substr($contactNumber, 2);
                $contactNumber = str_starts_with($nationalNumber, '0')
                    ? $nationalNumber
                    : '0'.$nationalNumber;
            }

            return 'phone:'.$contactNumber;
        }

        $name = preg_replace('/\s+/u', ' ', mb_strtolower(trim((string) $this->customer_name)));

        return $name === '' ? null : 'name:'.$name;
    }

    public function netOrderTotal(?float $orderAmount = null): float
    {
        $baseAmount = $orderAmount
            ?? (float) ($this->grand_total ?: $this->total_amount ?: $this->items->sum('line_total'));
        $itemReturnDeduction = $this->items->sum(fn ($item) => $item->netReturnDeduction());
        $unlinkedReturnRefund = $this->inventoryReturns->whereNull('transaction_item_id')->sum('refund_amount');
        $returnedAmount = max($itemReturnDeduction, (float) $unlinkedReturnRefund);

        return max(0, $baseAmount - $returnedAmount);
    }

    public function netTikTokPayout(): ?float
    {
        if ($this->sales_after_transaction_fee === null) {
            return null;
        }

        return round((float) $this->sales_after_transaction_fee - ($this->payout_includes_refunds
            ? 0 : (float) $this->refund_shipping_fee + $this->completedCustomerRefunds()), 2);
    }

    public function isTikTokFullyReturned(): bool
    {
        $this->loadMissing([
            'items.inventoryReturns',
            'items.replacements.inventoryReturns',
        ]);

        if ($this->items->isEmpty()) {
            return false;
        }

        return $this->items->every(function ($item) {
            $approvedReplacements = $item->replacements->where('status', 'approved');
            $returnedQuantity = max(
                (int) ($item->returned_quantity ?? 0),
                (int) $item->inventoryReturns->sum('quantity')
            );
            $originalRemaining = max(0,
                (int) $item->quantity
                - $returnedQuantity
                - (int) $approvedReplacements->sum('quantity')
            );
            $replacementRemaining = $approvedReplacements->sum(fn ($replacement) => max(0,
                (int) ($replacement->replacement_quantity ?: $replacement->quantity)
                - (int) $replacement->inventoryReturns->sum('quantity')
            ));

            return $originalRemaining + $replacementRemaining === 0;
        });
    }

    public function effectiveTikTokPayout(): float
    {
        if ($this->isTikTokFullyReturned()) {
            return 0.0;
        }

        return $this->tiktok_recalculated_payout_entered
            && $this->tiktok_recalculated_payout !== null
                ? (float) $this->tiktok_recalculated_payout
                : (float) ($this->netTikTokPayout() ?? 0);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function storeHub(): BelongsTo
    {
        return $this->belongsTo(StoreHub::class, 'store_hub_id');
    }

    public function scopeForDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('order_date', [$startDate, $endDate]);
    }

    public function scopeWholesale($query)
    {
        return $query->where('channel_type', 'wholesale');
    }
}
