<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // Optional if you use soft deletes

class Product extends Model
{
    // use SoftDeletes; // Uncomment if applicable

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
        'store_hub_id',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'sales_price' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'shopee_price' => 'decimal:2',
        'lazada_price' => 'decimal:2',
        'tiktok_price' => 'decimal:2',
    ];

    public function storeHub()
    {
        return $this->belongsTo(StoreHub::class, 'store_hub_id');
    }

    public function stockAllocation()
    {
        return $this->hasOne(ProductStockAllocation::class);
    }

    public function inventoryTransactions()
    {
        return $this->hasMany(InventoryTransaction::class);
    }
}
