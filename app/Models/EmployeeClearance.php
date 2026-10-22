<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeClearance extends Model
{
    protected $fillable = [
        'employee_id',
        'created_by',
        'is_cleared',
        'description',
    ];

    public function employee()
    {
        return $this->hasOne('App\Models\Employee', 'id', 'employee_id');
    }
}
