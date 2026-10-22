<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceEmployee extends Model
{
    protected $fillable = [
        'employee_id',
        'date',
        'status',
        'clock_in',
        'clock_out',
        'late',
        'early_leaving',
        'overtime',
        'total_rest',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'date' => 'date:Y-m-d',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = ['employee_name', 'department_name'];

    public function employees()
    {
        return $this->hasOne('App\Models\Employee', 'user_id', 'employee_id');
    }

    public function employee()
    {
        return $this->hasOne('App\Models\Employee', 'id', 'employee_id');
    }

    /**
     * Get the employee name attribute.
     */
    public function getEmployeeNameAttribute()
    {
        return $this->employee ? $this->employee->name : null;
    }

    /**
     * Get the department name attribute.
     */
    public function getDepartmentNameAttribute()
    {
        if ($this->employee && $this->employee->department) {
            return $this->employee->department->name;
        }
        return null;
    }
}
