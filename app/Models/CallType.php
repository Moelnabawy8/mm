<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CallType extends Model
{
    protected $fillable = [
        'name',
        'created_by',
    ];
}
