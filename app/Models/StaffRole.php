<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffRole extends Model
{
    public const BUILT_IN_ROLES = [
        'admin' => 'Admin',
        'inventory_staff' => 'Inventory Staff',
        'sales_associate' => 'Sales Associate',
        'sales_marketing_staff' => 'Sales/Marketing Staff',
    ];

    protected $fillable = ['name', 'slug', 'is_system'];

    protected $casts = [
        'is_system' => 'boolean',
    ];
}
