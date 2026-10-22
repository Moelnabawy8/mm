<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DealPurchase extends Model
{
    use HasFactory;

    public static function boot()
    {
        parent::boot();

        static::deleting(function ($dealPurchase) {
            $dealPurchase->installments->each->delete();
        });
    }

    protected $fillable = [
        'deal_id',
        'price',
        'currency',
        'payment_method',
        'payment_type',
        'paid_amount',
        'status',
        'purchase_date',
        'created_by',
        'products',
    ];

    protected $casts = [
        'products' => 'array',
    ];

    public function deal()
    {
        return $this->belongsTo(Deal::class, 'deal_id', 'id');
    }

    // Helper to get products from the associated deal
    public function getDealProductsAttribute()
    {
        return $this->deal ? $this->deal->products() : [];
    }

    public function installments()
    {
        return $this->hasMany(Installment::class, 'deal_purchase_id', 'id');
    }

    public function attachments()
    {
        return $this->hasMany(DealPurchaseAttachment::class);
    }

    public function paymentMethodDetails()
    {
        return $this->belongsTo(SalesMeetingPaymentMethod::class, 'payment_method', 'id');
    }
}
