<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Installment extends Model
{
    use HasFactory;

    protected $fillable = [
        'deal_purchase_id',
        'amount',
        'date',
        'description',
        'status',
        'is_hopeless',
        'exemption',
        'created_by',
    ];

    protected $appends = ['effective_amount'];

    protected $casts = [
        'is_hopeless' => 'boolean',
        'exemption'   => 'decimal:2',
        'amount'      => 'decimal:2',
    ];

    public function dealPurchase()
    {
        return $this->belongsTo(DealPurchase::class, 'deal_purchase_id', 'id');
    }

    /**
     * Get the effective amount to be paid (amount - exemption)
     */
    public function getEffectiveAmountAttribute()
    {
        return $this->amount - $this->exemption;
    }
}
