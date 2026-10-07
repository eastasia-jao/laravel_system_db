<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransactionItem extends Model
{
    protected $table = 'transaction_items';

    protected $guarded = ['id'];

    protected $casts = [
        'customer_refund_amount' => 'decimal:2',
        'returned_at' => 'datetime',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(SalesTransaction::class, 'transaction_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function replacements(): HasMany
    {
        return $this->hasMany(ProductReplacement::class, 'transaction_item_id');
    }

    public function inventoryReturns(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class, 'transaction_item_id')
            ->where('type', 'return')
            ->whereNull('product_replacement_id');
    }

    public function getRemainingReplaceableQuantityAttribute(): int
    {
        return max(0, (int) $this->quantity - (int) $this->replacements
            ->whereIn('status', ['pending', 'approved'])
            ->sum('quantity'));
    }

    public function getRemainingReturnedReplaceableQuantityAttribute(): int
    {
        $alreadyReplaced = $this->relationLoaded('replacements')
            ? (int) $this->replacements->whereIn('status', ['pending', 'approved'])->sum('quantity')
            : (int) $this->replacements()->whereIn('status', ['pending', 'approved'])->sum('quantity');

        return max(0, $this->returnedQuantity() - $alreadyReplaced);
    }

    public function returnedQuantity(): int
    {
        $recordedQuantity = $this->return_status === 'received'
            ? max(0, (int) ($this->returned_quantity ?? 0))
            : 0;

        return min(
            max(0, (int) $this->quantity),
            max(
                $recordedQuantity,
                (int) $this->inventoryReturns->sum('quantity')
            )
        );
    }

    public function returnedGrossAmount(): float
    {
        return round($this->returnedQuantity() * (float) $this->unit_price, 2);
    }

    public function returnedNetAmount(): float
    {
        $quantity = (int) $this->quantity;
        if ($quantity < 1) {
            return 0.0;
        }

        return round((float) $this->line_total * ($this->returnedQuantity() / $quantity), 2);
    }

    public function refundCostAmount(): float
    {
        $recordedRefund = $this->inventoryReturns->sum('refund_amount');
        $completedRefund = $this->refund_status === 'completed'
            ? (float) ($this->customer_refund_amount ?? 0)
            : 0.0;

        return max((float) $recordedRefund, $completedRefund);
    }

    public function netReturnDeduction(): float
    {
        return max($this->returnedNetAmount(), $this->refundCostAmount());
    }
}
