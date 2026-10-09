<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
