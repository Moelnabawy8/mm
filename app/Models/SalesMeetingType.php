<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesMeetingType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'created_by',
    ];
}
