<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreHub extends Model
{
    use HasFactory;

    protected $table = 'store_hubs';

    protected $fillable = ['name', 'code', 'status', 'is_head_office'];

    /**
     * Automatically format the store hub name to UPPERCASE.
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => strtoupper($value),
        );
    }
}
