<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model; // This is the correct base class

class Group extends Model // This MUST be "extends Model"
{
    // This allows mass assignment
    protected $fillable = ['name'];
}
