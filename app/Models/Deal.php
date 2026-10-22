<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Deal extends Model
{
    public static function boot()
    {
        parent::boot();

        static::deleting(function ($deal) {
            if ($deal->dealPurchase) {
                $deal->dealPurchase->delete();
            }

            // deal_attachments has no cascade on every environment, so the rows
            // (and the uploaded files) were left behind on every deal deletion.
            foreach ($deal->attachments as $attachment) {
                if ($attachment->file_path && \Storage::disk('public')->exists($attachment->file_path)) {
                    \Storage::disk('public')->delete($attachment->file_path);
                }
                $attachment->delete();
            }
        });
    }

    protected $fillable = [
        'name',
        'lead_id',
        'products',
        'labels',
        'created_by',
        'added_by',
        'notes',
        'datetime',
        'status',
        'is_active',
        'pipeline_id',
        'stage_id',
    ];

    protected $casts = [
        'datetime' => 'date',
    ];

    public function stage()
    {
        return $this->belongsTo('App\Models\Stage', 'stage_id', 'id');
    }

    public function pipeline()
    {
        return $this->belongsTo('App\Models\Pipeline', 'pipeline_id', 'id');
    }

    public static $statues = [
        'Active' => 'Active',
        'Won' => 'Won',
        'Loss' => 'Loss',
    ];

    public function lead()
    {
        return $this->belongsTo('App\Models\Lead', 'lead_id', 'id');
    }

    public function labels()
    {
        if ($this->labels) {
            return Label::whereIn('id', explode(',', $this->labels))->get();
        }

        return false;
    }

    public function products()
    {
        if ($this->products) {
            return ProductService::whereIn('id', explode(',', $this->products))->get();
        }

        return [];
    }

    public function dealPurchase()
    {
        return $this->hasOne(DealPurchase::class, 'deal_id', 'id');
    }

    public function attachments()
    {
        return $this->hasMany(DealAttachment::class);
    }

    public function createdByUser()
    {
        // added_by = the actual employee who clicked "Create Deal"
        // Falls back to created_by (company owner) if added_by is not set
        return $this->belongsTo('App\Models\User', 'added_by', 'id');
    }

    public function discussions()
    {
        return $this->hasMany(DealDiscussion::class, 'deal_id', 'id')->orderBy('id', 'desc');
    }

    public function calls()
    {
        return $this->hasMany(DealCall::class, 'deal_id', 'id');
    }
}
