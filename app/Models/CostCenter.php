<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CostCenter extends Model
{
    protected $fillable = [
        'name',
        'code',
        'description',
        'status',
        'created_by',
    ];
}
