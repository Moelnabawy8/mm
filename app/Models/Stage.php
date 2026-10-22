<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Stage extends Model
{
    protected $fillable = [
        'name',
        'pipeline_id',
        'created_by',
        'order'
    ];

    public function deals()
    {
        if (\Auth::user()->type == 'company') {
            // Company users see all deals in this stage
            return Deal::select('deals.*')->where('deals.created_by', '=', \Auth::user()->creatorId())->where('deals.stage_id', '=', $this->id)->orderBy('deals.order')->get();
        } elseif (\Auth::user()->type == 'client') {
            // Client users see only deals where they are the client
            return Deal::select('deals.*')->join('client_deals', 'client_deals.deal_id', '=', 'deals.id')->where('client_deals.client_id', '=', \Auth::user()->id)->where('deals.stage_id', '=', $this->id)->orderBy('deals.order')->get();
        } else {
            // Regular users see only deals assigned to them
            return Deal::select('deals.*')->join('user_deals', 'user_deals.deal_id', '=', 'deals.id')->where('user_deals.user_id', '=', \Auth::user()->id)->where('deals.stage_id', '=', $this->id)->orderBy('deals.order')->get();
        }
    }
}
