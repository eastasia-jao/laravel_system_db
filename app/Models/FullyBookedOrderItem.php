<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FullyBookedOrderItem extends Model
{
    protected $fillable = [
        'fully_booked_order_id',
        'product_id',
        'product_name',
        'item_id',
        'quantity',
        'inventory_transaction_id',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(FullyBookedOrder::class, 'fully_booked_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function inventoryTransaction(): BelongsTo
    {
        return $this->belongsTo(InventoryTransaction::class);
    }
}
