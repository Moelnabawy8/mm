<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryStructure extends Model
{
    protected $fillable = [
        'name',
        'description',
        'basic_salary',
        'allowances',
        'deductions',
        'status',
        'created_by',
    ];

    protected $casts = [
        'allowances' => 'array',
        'deductions' => 'array',
        'basic_salary' => 'decimal:2',
    ];

    /**
     * Compute total monthly gross (basic + allowances, allowances may be % or fixed)
     */
    public function getTotalGrossAttribute(): float
    {
        $total = (float) $this->basic_salary;
        foreach ((array) $this->allowances as $a) {
            $total += isset($a['is_percentage']) && $a['is_percentage']
                ? ($this->basic_salary * $a['amount'] / 100)
                : (float) $a['amount'];
        }
        return $total;
    }

    /**
     * Compute total deductions
     */
    public function getTotalDeductionsAttribute(): float
    {
        $total = 0.0;
        foreach ((array) $this->deductions as $d) {
            $total += isset($d['is_percentage']) && $d['is_percentage']
                ? ($this->basic_salary * $d['amount'] / 100)
                : (float) $d['amount'];
        }
        return $total;
    }

    public function getNetSalaryAttribute(): float
    {
        return $this->total_gross - $this->total_deductions;
    }
}
