<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoogleMeet extends Model
{
    use HasFactory;

    protected $fillable = [
        'meet_name',
        'created_by',
        'meet_members',
        'start_date',
        'end_date',
        'meet_link',
        'status',
    ];

    protected $casts = [
        'meet_members' => 'array',
    ];
}
