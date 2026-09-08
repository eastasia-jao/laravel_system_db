<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $table = 'departments'; // Forces it to look at the correct table

    protected $fillable = ['name'];
}
