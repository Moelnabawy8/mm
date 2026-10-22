<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadStage extends Model
{
    protected $fillable = [
        'name',
        'pipeline_id',
        'created_by',
        'order',
    ];

    public function lead()
    {
        if (\Auth::user()->type == 'company' || \Auth::user()->type == 'CSO' || \Auth::user()->type == 'Alaa,Saba,aber' || \Auth::user()->type == 'fadi') {
            return Lead::select('leads.*')
                ->where('leads.created_by', '=', \Auth::user()->creatorId())
                ->where('leads.stage_id', '=', $this->id)
                ->orderByRaw('CAST(COALESCE(leads.date, leads.created_at) AS DATETIME) DESC')
                ->get();
        } else {
            return Lead::select('leads.*')
                ->join('user_leads', 'user_leads.lead_id', '=', 'leads.id')
                ->where('user_leads.user_id', '=', \Auth::user()->id)
                ->where('leads.stage_id', '=', $this->id)
                ->orderByRaw('CAST(COALESCE(leads.date, leads.created_at) AS DATETIME) DESC')
                ->get();
        }
    }
}
