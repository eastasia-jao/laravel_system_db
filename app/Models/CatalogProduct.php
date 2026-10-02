<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogProduct extends Model
{
    public const FIELDS = ['item_id', 'name', 'description', 'barcode', 'brand', 'retail_group', 'retail_department', 'unit_type'];

    protected $fillable = self::FIELDS;

    public function branchInventories()
    {
        return $this->hasMany(Product::class);
    }
}
