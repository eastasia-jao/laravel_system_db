<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductStockAllocation extends Model
{
    protected $fillable = [
        'product_id',
        'online',
        'wholesale',
        'shopee',
        'lazada',
        'tiktok',
    ];

    protected $casts = [
        'online' => 'integer',
        'wholesale' => 'integer',
        'shopee' => 'integer',
        'lazada' => 'integer',
        'tiktok' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
