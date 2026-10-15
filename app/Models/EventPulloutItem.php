<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventPulloutItem extends Model
{
    protected $fillable = [
        'product_id', 'stock_condition', 'requested_quantity', 'quantity',
    ];

    protected $casts = [
        'requested_quantity' => 'integer',
        'quantity' => 'integer',
    ];

    public function eventPullout(): BelongsTo
    {
        return $this->belongsTo(EventPullout::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
