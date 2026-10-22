<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($lead) {
            // Delete installments first (query builder, not collection)
            $lead->installments()->delete();

            // Delete deal purchases through deals
            $dealIds = $lead->deals()->pluck('id');
            \App\Models\DealPurchase::whereIn('deal_id', $dealIds)->delete();

            // Delete deals and other related records
            $lead->deals()->delete();
            $lead->salesMeetings()->delete();
            $lead->discussions()->delete();
            $lead->calls()->delete();
            $lead->activities()->delete();
            $lead->files()->delete();
        });
    }

    protected $fillable = [
        'name',
        'email',
        'subject',
        'user_id',
        'pipeline_id',
        'stage_id',
        'sources',
        'products',
        'notes',
        'labels',
        'order',
        'created_by',
        'is_active',
        'date',
        'gender',
        'country',
        'city',
        'costing_type',
        'member_id',
        'campaign_name',
        'cost',
        'phone',
        'telegram',
        'interests',
    ];

    public function labels()
    {
        if ($this->labels) {
            return Label::whereIn('id', explode(',', $this->labels))->get();
        }

        return false;
    }

    public function stage()
    {
        return $this->hasOne('App\Models\LeadStage', 'id', 'stage_id');
    }


    public function files()
    {
        return $this->hasMany('App\Models\LeadFile', 'lead_id', 'id');
    }

    public function pipeline()
    {
        return $this->hasOne('App\Models\Pipeline', 'id', 'pipeline_id');
    }

    public function products()
    {
        if ($this->products) {
            return ProductService::whereIn('id', explode(',', $this->products))->get();
        }

        return collect([]);
    }

    public function sources()
    {
        if ($this->sources) {
            return Source::whereIn('id', explode(',', $this->sources))->get();
        }

        return [];
    }

    public function users()
    {
        return $this->belongsToMany('App\Models\User', 'user_leads', 'lead_id', 'user_id');
    }

    public function assignedUser()
    {
        return $this->belongsTo('App\Models\User', 'user_id');
    }

    public function activities()
    {
        return $this->hasMany('App\Models\LeadActivityLog', 'lead_id', 'id')->orderBy('id', 'desc');
    }

    public function discussions()
    {
        return $this->hasMany('App\Models\LeadDiscussion', 'lead_id', 'id')->orderBy('id', 'desc');
    }

    public function calls()
    {
        return $this->hasMany('App\Models\LeadCall', 'lead_id', 'id');
    }

    public function deals()
    {
        return $this->hasMany('App\Models\Deal', 'lead_id', 'id');
    }

    public function salesMeetings()
    {
        return $this->hasMany('App\Models\SalesMeeting', 'lead_id', 'id');
    }

    public function dealPurchases()
    {
        return $this->hasManyThrough('App\Models\DealPurchase', 'App\Models\Deal', 'lead_id', 'deal_id');
    }

    public function installments()
    {
        // Get all installments through deal purchases
        $dealIds = $this->deals()->pluck('id');
        $dealPurchaseIds = \App\Models\DealPurchase::whereIn('deal_id', $dealIds)->pluck('id');
        return \App\Models\Installment::whereIn('deal_purchase_id', $dealPurchaseIds);
    }

    public function interests()
    {
        if ($this->interests) {
            return Interest::whereIn('id', explode(',', $this->interests))->get();
        }

        return collect([]);
    }

    public function getStageType()
    {
        if (isset($this->installments_count) && $this->installments_count > 0) {
            return 'installments';
        }

        if (isset($this->deal_purchases_count) && $this->deal_purchases_count > 0) {
            return 'deal_purchase';
        }

        if (isset($this->sales_meetings_count) && $this->sales_meetings_count > 0) {
            return 'meeting';
        }

        if (isset($this->deals_count) && $this->deals_count > 0) {
            return 'deal';
        }

        return 'lead';
    }
}
