<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NationalPulloutItem extends Model
{
    protected $fillable = [
        'national_product_id', 'item_id', 'product_name', 'quantity', 'unit_type',
        'purpose', 'physical_stock', 'actual_pullout',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'physical_stock' => 'integer',
        'actual_pullout' => 'integer',
    ];

    public function pullout(): BelongsTo
    {
        return $this->belongsTo(NationalPullout::class, 'national_pullout_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(NationalProduct::class, 'national_product_id');
    }

    public function inventoryReturns(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class)->where('type', 'return');
    }
}
