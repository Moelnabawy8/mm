<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One yardstick a department judges its completed work by.
 *
 * Criteria belong to a department, not to the company: Design and Developers do not
 * review a deliverable the same way. See the quality_criteria migration.
 */
class QualityCriterion extends Model
{
    protected $table = 'quality_criteria';

    protected $fillable = [
        'department_id',
        'name',
        'description',
        'weight',
        'max_score',
        'sort_order',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'weight'    => 'integer',
        'max_score' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * The standard set offered to a department that has none yet. Chosen to fit any
     * delivery department; a department is free to edit, reweight or delete them.
     */
    public static function defaults(): array
    {
        return [
            ['name' => 'Meets the requirement', 'weight' => 3, 'description' => 'The output does what the task asked for.'],
            ['name' => 'Quality of the output', 'weight' => 3, 'description' => 'Standard of the work itself.'],
            ['name' => 'Delivered on time',     'weight' => 2, 'description' => 'Finished by the due date.'],
            ['name' => 'Attention to detail',   'weight' => 1, 'description' => 'Finish, accuracy, nothing left half-done.'],
            ['name' => 'Communication',         'weight' => 1, 'description' => 'Kept the task updated and raised blockers early.'],
        ];
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id', 'id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForDepartment($query, $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }
}
