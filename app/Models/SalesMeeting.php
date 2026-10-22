<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesMeeting extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'lead_id',
        'deal_id',
        'meeting_type_id',
        'datetime',
        'duration',
        'date',
        'time',
        'link',
        'notes',
        'attachment',
        'created_by',
    ];

    protected $casts = [
        // 'date' truncated every meeting to midnight, so the list always showed 12:00 AM.
        'datetime' => 'datetime',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function deal()
    {
        return $this->belongsTo(Deal::class, 'deal_id');
    }

    public function meetingType()
    {
        return $this->belongsTo(SalesMeetingType::class, 'meeting_type_id');
    }

    public function getMeetingType()
    {
        return $this->hasOne(SalesMeetingType::class, 'id', 'meeting_type_id');
    }

    public function products()
    {
        return $this->belongsToMany(ProductService::class, 'sales_meeting_products', 'sales_meeting_id', 'product_service_id')
            ->withTimestamps();
    }
}
