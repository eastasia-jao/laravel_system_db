<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'store_hub_id',
        'action_type',
        'description',
        'details',
        'ip_address',
    ];

    protected $casts = [
        'details' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function storeHub()
    {
        return $this->belongsTo(StoreHub::class, 'store_hub_id');
    }

    public function items()
    {
        return $this->hasMany(StaffActivityLogItem::class);
    }
}
