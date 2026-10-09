<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NationalPullout extends Model
{
    protected $fillable = ['po_number', 'occurred_on', 'remarks', 'store_hub_id', 'created_by'];

    protected $casts = ['occurred_on' => 'date'];

    public function items(): HasMany
    {
        return $this->hasMany(NationalPulloutItem::class);
    }

    public function storeHub(): BelongsTo
    {
        return $this->belongsTo(StoreHub::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
