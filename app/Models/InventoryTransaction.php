<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTransaction extends Model
{
    protected $fillable = [
        'reference', 'type', 'store_hub_id', 'product_id', 'source_hub_id',
        'target_hub_id', 'channel', 'source', 'condition', 'quantity',
        'occurred_on', 'notes', 'created_by', 'sales_transaction_id',
        'transaction_item_id', 'product_replacement_id',
        'refund_amount', 'transfer_batch_id', 'status', 'reviewed_by',
        'reviewed_at', 'rejection_reason',
    ];

    protected $casts = [
        'occurred_on' => 'date',
        'quantity' => 'integer',
        'refund_amount' => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function storeHub(): BelongsTo
    {
        return $this->belongsTo(StoreHub::class);
    }

    public function sourceHub(): BelongsTo
    {
        return $this->belongsTo(StoreHub::class, 'source_hub_id');
    }

    public function targetHub(): BelongsTo
    {
        return $this->belongsTo(StoreHub::class, 'target_hub_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function salesTransaction(): BelongsTo
    {
        return $this->belongsTo(SalesTransaction::class, 'sales_transaction_id');
    }

    public function transactionItem(): BelongsTo
    {
        return $this->belongsTo(TransactionItem::class, 'transaction_item_id');
    }

    public function productReplacement(): BelongsTo
    {
        return $this->belongsTo(ProductReplacement::class, 'product_replacement_id');
    }
}
