<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductReplacement extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'quantity' => 'integer',
        'replacement_quantity' => 'integer',
        'original_unit_price' => 'decimal:2',
        'replacement_unit_price' => 'decimal:2',
        'replacement_discount_percentage' => 'decimal:2',
        'price_adjustment' => 'decimal:2',
        'exchange_credit' => 'decimal:2',
        'uses_exchange_credit' => 'boolean',
        'exchange_total' => 'decimal:2',
        'additional_payment_due' => 'decimal:2',
        'replacement_shipping_fee_amount' => 'decimal:2',
        'exchange_payment_amount' => 'decimal:2',
        'exchange_payment_proofs' => 'array',
        'exchange_check_date' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(SalesTransaction::class, 'transaction_id');
    }

    public function transactionItem(): BelongsTo
    {
        return $this->belongsTo(TransactionItem::class, 'transaction_item_id');
    }

    public function originalProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'original_product_id');
    }

    public function replacementProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'replacement_product_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function inventoryReturns(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class, 'product_replacement_id')
            ->where('type', 'return');
    }
}
