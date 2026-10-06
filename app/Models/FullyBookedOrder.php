<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FullyBookedOrder extends Model
{
    protected $fillable = [
        'order_number',
        'store_hub_id',
        'store_name',
        'sales_staff_id',
        'submitted_by',
        'reviewed_by',
        'attachment_path',
        'original_filename',
        'mime_type',
        'remarks',
        'status',
        'reviewed_at',
        'pulled_out_by',
        'pulled_out_at',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime', 'pulled_out_at' => 'datetime'];
    }

    public function storeHub(): BelongsTo
    {
        return $this->belongsTo(StoreHub::class);
    }

    public function salesStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_staff_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function pullOutBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pulled_out_by');
    }

    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FullyBookedOrderItem::class);
    }
}
