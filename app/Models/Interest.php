<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Interest extends Model
{
    protected $fillable = [
        'name',
        'created_by',
    ];

    public function leads()
    {
        return $this->belongsToMany(Lead::class, 'lead_interests');
    }
}
