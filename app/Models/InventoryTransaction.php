<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTransaction extends Model
{
    protected $fillable = [
        'reference', 'type', 'store_hub_id', 'product_id', 'source_hub_id',
        'target_hub_id', 'channel', 'source', 'condition', 'quantity',
        'occurred_on', 'notes', 'created_by',
    ];

    protected $casts = ['occurred_on' => 'date', 'quantity' => 'integer'];

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
}
