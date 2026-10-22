<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QualityEvaluation extends Model
{
    use HasFactory;

    const POTENTIAL_YES     = 'yes';
    const POTENTIAL_NO      = 'no';
    const POTENTIAL_UNKNOWN = 'unknown';

    protected $fillable = [
        'lead_id',
        'evaluator_id',
        'university_major',
        'university_major_other',
        'age',
        'career_stage',
        'engagement_level',
        'interest_indicators',
        'course_fit_level',
        'is_potential_lead',
        'rejection_reasons',
        'city',
        'rating',
        'notes',
    ];

    protected $casts = [
        'interest_indicators' => 'array',
        'rejection_reasons'   => 'array',
        'rating'              => 'integer',
    ];

    protected $attributes = [
        'is_potential_lead' => self::POTENTIAL_UNKNOWN,
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id', 'id');
    }

    public function evaluator()
    {
        return $this->belongsTo(User::class, 'evaluator_id', 'id');
    }

    public function getEngagementBadgeAttribute()
    {
        $badges = [
            'highly_engaged' => 'success',
            'moderate'       => 'info',
            'Moderate'       => 'info',
            'low_engagement' => 'warning',
            'not_engaged'    => 'danger',
        ];

        return $badges[$this->engagement_level] ?? 'secondary';
    }

    public function getCourseFitBadgeAttribute()
    {
        $badges = [
            'perfect_fit' => 'success',
            'good_fit'    => 'primary',
            'partial_fit' => 'warning',
            'not_a_fit'   => 'danger',
        ];

        return $badges[$this->course_fit_level] ?? 'secondary';
    }

    public function getPotentialBadgeAttribute()
    {
        $badges = [
            self::POTENTIAL_YES     => 'success',
            self::POTENTIAL_NO      => 'danger',
            self::POTENTIAL_UNKNOWN => 'secondary',
        ];

        return $badges[$this->is_potential_lead] ?? 'secondary';
    }

    public function getPotentialLabelAttribute()
    {
        $labels = [
            self::POTENTIAL_YES     => 'Yes',
            self::POTENTIAL_NO      => 'No',
            self::POTENTIAL_UNKNOWN => 'Unknown',
        ];

        return $labels[$this->is_potential_lead] ?? 'Unknown';
    }

    public function scopeByEvaluator($query, $evaluatorId)
    {
        return $query->where('evaluator_id', $evaluatorId);
    }

    public function scopePotential($query)
    {
        return $query->where('is_potential_lead', self::POTENTIAL_YES);
    }

    public function scopeNotPotential($query)
    {
        return $query->where('is_potential_lead', self::POTENTIAL_NO);
    }

    public function scopeUnknownPotential($query)
    {
        return $query->where('is_potential_lead', self::POTENTIAL_UNKNOWN);
    }

    public function scopeByRating($query, $rating)
    {
        return $query->where('rating', $rating);
    }
}
