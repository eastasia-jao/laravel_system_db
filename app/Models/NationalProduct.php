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
        'status',
    ];

    protected $casts = [
        'stock' => 'integer',
    ];
}
