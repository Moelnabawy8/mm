<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A named reviewer for one department. Not a role — see the migration for why.
 */
class QualityDepartmentReviewer extends Model
{
    protected $table = 'quality_department_reviewers';

    protected $fillable = [
        'department_id',
        'user_id',
        'created_by',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id', 'id');
    }
}
