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
        'order_date' => 'date',
        'delivery_date' => 'date',
        'date_of_arrangement' => 'date',
        'check_date' => 'date',
        'shipping_fee_amount' => 'decimal:2',
        'shipping_service_fee' => 'decimal:2',
        'sales_after_transaction_fee' => 'decimal:2',
        'additional_discount_percentage' => 'decimal:2',
        'withholding_tax' => 'decimal:2',
        'withholding_tax_amount' => 'decimal:2',
        'sub_total' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'refund_shipping_fee' => 'decimal:2',
        'proof_amount' => 'decimal:2',
        'drop_off_date' => 'date',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(TransactionItem::class, 'transaction_id');
    }

    public function completedCustomerRefunds(): float
    {
        return (float) $this->items->where('refund_status', 'completed')->sum('customer_refund_amount');
    }

    public function netTikTokPayout(): ?float
    {
        if ($this->sales_after_transaction_fee === null) {
            return null;
        }

        return round((float) $this->sales_after_transaction_fee - ($this->payout_includes_refunds
            ? 0 : (float) $this->refund_shipping_fee + $this->completedCustomerRefunds()), 2);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
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
