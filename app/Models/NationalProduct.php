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
        'unit_type',
        'stock',
        'damaged_stock',
        'status',
    ];

    protected $casts = [
        'stock' => 'integer',
        'damaged_stock' => 'integer',
    ];
}
