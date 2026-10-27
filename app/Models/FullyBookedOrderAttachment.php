<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FullyBookedOrderAttachment extends Model
{
    protected $fillable = [
        'fully_booked_order_id',
        'path',
        'original_filename',
        'mime_type',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(FullyBookedOrder::class, 'fully_booked_order_id');
    }
}
