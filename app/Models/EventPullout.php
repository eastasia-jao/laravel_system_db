<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventPullout extends Model
{
    protected $fillable = [
        'reference', 'store_hub_id', 'source', 'occurred_on', 'notes', 'status',
        'created_by', 'closed_by', 'closed_at',
    ];

    protected $casts = [
        'occurred_on' => 'date',
        'closed_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(EventPulloutItem::class);
    }

    public function storeHub(): BelongsTo
    {
        return $this->belongsTo(StoreHub::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function salesTransactions(): HasMany
    {
        return $this->hasMany(SalesTransaction::class);
    }

    public function inventoryTransactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }
}
