<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffActivityLogItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'staff_activity_log_id',
        'product_id',
        'item_id',
        'product_name',
        'operation',
        'quantity',
        'stock_before',
        'stock_after',
        'details',
    ];

    protected $casts = [
        'details' => 'array',
    ];

    public function activityLog()
    {
        return $this->belongsTo(StaffActivityLog::class, 'staff_activity_log_id');
    }
}
