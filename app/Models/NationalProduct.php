<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NationalProduct extends Model
{
    protected $fillable = [
        'item_id',
        'name',
        'description',
        'barcode',
        'brand',
        'retail_group',
        'retail_department',
        'unit_type',
        'cost_price',
        'sales_price',
        'wholesale_price',
        'shopee_price',
        'lazada_price',
        'tiktok_price',
        'stock',
        'status',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'sales_price' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'shopee_price' => 'decimal:2',
        'lazada_price' => 'decimal:2',
        'tiktok_price' => 'decimal:2',
        'stock' => 'integer',
    ];
}
