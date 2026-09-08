<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HubTransaction extends Model
{
    use HasFactory;

    protected $table = 'hub_transactions';

    protected $fillable = [
        'hub_id',
        'order_number',
        'channel_type',
        'customer_name',
        'grand_total',
        'order_date',
    ];

    protected $casts = [
        'order_date' => 'date',
    ];

    public function hub()
    {
        return $this->belongsTo(StoreHub::class, 'hub_id');
    }
}
