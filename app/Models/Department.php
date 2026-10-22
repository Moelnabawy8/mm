<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable = [
        'name',
        'branch_id',
        'created_by',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'id');
    }

    public function designations()
    {
        return $this->hasMany(Designation::class, 'department_id', 'id');
    }

    public function employees()
    {
        return $this->hasMany(Employee::class, 'department_id', 'id');
    }
}
