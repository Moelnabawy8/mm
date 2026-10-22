<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'data',
        'is_read',
    ];

    public function toHtml()
    {
        $data       = json_decode($this->data);
        $link       = '#';
        $icon       = 'fa fa-bell';
        $icon_color = 'bg-primary';
        $text       = '';

        if (isset($data->updated_by) && !empty($data->updated_by)) {
            $usr = User::find($data->updated_by);
        }

        if (!empty($usr)) {
            // For Deals Notification
            if ($this->type == 'assign_deal') {
                $link       = route('deals.show', [$data->deal_id,]);
                $text       = $usr->name . " " . __('Added you') . " " . __('in deal') . " <b class='font-weight-bold'>" . $data->name . "</b> ";
                $icon       = "fa fa-plus";
                $icon_color = 'bg-primary';
            }

            if ($this->type == 'create_deal_call') {
                $link       = route('deals.show', [$data->deal_id,]);
                $text       = $usr->name . " " . __('Create new Deal Call') . " " . __('in deal') . " <b class='font-weight-bold'>" . $data->name . "</b> ";
                $icon       = "fa fa-phone";
                $icon_color = 'bg-info';
            }

            if ($this->type == 'update_deal_source') {
                $link       = route('deals.show', [$data->deal_id,]);
                $text       = $usr->name . " " . __('Update Sources') . " " . __('in deal') . " <b class='font-weight-bold'>" . $data->name . "</b> ";
                $icon       = "fa fa-file-alt";
                $icon_color = 'bg-warning';
            }

            if ($this->type == 'create_task') {
                $link       = route('deals.show', [$data->deal_id,]);
                $text       = $usr->name . " " . __('Create new Task') . " " . __('in deal') . " <b class='font-weight-bold'>" . $data->name . "</b> ";
                $icon       = "fa fa-tasks";
                $icon_color = 'bg-primary';
            }

            if ($this->type == 'add_product') {
                $link       = route('deals.show', [$data->deal_id,]);
                $text       = $usr->name . " " . __('Add new Products') . " " . __('in deal') . " <b class='font-weight-bold'>" . $data->name . "</b> ";
                $icon       = "fa fa-dolly";
                $icon_color = 'bg-danger';
            }

            if ($this->type == 'add_discussion') {
                $link       = route('deals.show', [$data->deal_id,]);
                $text       = $usr->name . " " . __('Add new Discussion') . " " . __('in deal') . " <b class='font-weight-bold'>" . $data->name . "</b> ";
                $icon       = "fa fa-comments";
                $icon_color = 'bg-info';
            }

            if ($this->type == 'move_deal') {
                $link       = route('deals.show', [$data->deal_id,]);
                $text       = $usr->name . " " . __('Moved the deal') . " <b class='font-weight-bold'>" . $data->name . "</b> " . __('from') . " " . __(ucwords($data->old_status)) . " " . __('to') . " " . __(ucwords($data->new_status));
                $icon       = "fa fa-arrows-alt";
                $icon_color = 'bg-primary';
            }
            // end deals

            // for estimations
            if ($this->type == 'assign_estimation') {
                $link       = route('estimations.show', [$data->estimation_id,]);
                $text       = $usr->name . " " . __('Added you') . " " . __('in estimation') . " <b class='font-weight-bold'>" . $data->estimation_name . "</b> ";
                $icon       = "fa fa-plus";
                $icon_color = 'bg-primary';
            }
            // end estimations

            // For Leads Notification
            if ($this->type == 'assign_lead') {
                $link       = route('leads.show', [$data->lead_id,]);
                $text       = $usr->name . " " . __('Added you') . " " . __('in lead') . " <b class='font-weight-bold'>" . $data->name . "</b> ";
                $icon       = "fa fa-plus";
                $icon_color = 'bg-primary';
            }

            if ($this->type == 'create_lead_call') {
                $link       = route('leads.show', [$data->lead_id,]);
                $text       = $usr->name . " " . __('Create new Lead Call') . " " . __('in lead') . " <b class='font-weight-bold'>" . $data->name . "</b> ";
                $icon       = "fa fa-phone";
                $icon_color = 'bg-info';
            }

            if ($this->type == 'update_lead_source') {
                $link       = route('leads.show', [$data->lead_id,]);
                $text       = $usr->name . " " . __('Update Sources') . " " . __('in lead') . " <b class='font-weight-bold'>" . $data->name . "</b> ";
                $icon       = "fa fa-file-alt";
                $icon_color = 'bg-warning';
            }

            if ($this->type == 'add_lead_product') {
                $link       = route('leads.show', [$data->lead_id,]);
                $text       = $usr->name . " " . __('Add new Products') . " " . __('in lead') . " <b class='font-weight-bold'>" . $data->name . "</b> ";
                $icon       = "fa fa-dolly";
                $icon_color = 'bg-danger';
            }

            if ($this->type == 'add_lead_discussion') {
                $link       = route('leads.show', [$data->lead_id,]);
                $text       = $usr->name . " " . __('Add new Discussion') . " " . __('in lead') . " <b class='font-weight-bold'>" . $data->name . "</b> ";
                $icon       = "fa fa-comments";
                $icon_color = 'bg-info';
            }

            if ($this->type == 'move_lead') {
                $link       = route('leads.show', [$data->lead_id,]);
                $text       = $usr->name . " " . __('Moved the lead') . " <b class='font-weight-bold'>" . $data->name . "</b> " . __('from') . " " . __(ucwords($data->old_status)) . " " . __('to') . " " . __(ucwords($data->new_status));
                $icon       = "fa fa-arrows-alt";
                $icon_color = 'bg-primary';
            }
            // end Leads

            // For Support Requests Notification
            if ($this->type == 'support_request_created') {
                $link       = route('support-requests.show', [$data->support_request_id,]);
                $text       = $usr->name . " " . __('requested your support for') . " <b class='font-weight-bold'>" . $data->lead_name . "</b> ";
                $icon       = "fa fa-hands-helping";
                $icon_color = 'bg-warning';
            }

            if ($this->type == 'support_request_status_updated') {
                $link       = route('support-requests.show', [$data->support_request_id,]);
                $statusText = isset($data->status) ? __(ucfirst(str_replace('_', ' ', $data->status))) : '';
                $text       = $usr->name . " " . __('updated support request status to') . " <b class='font-weight-bold'>" . $statusText . "</b> " . __('for') . " <b>" . $data->lead_name . "</b>";
                $icon       = "fa fa-sync";
                $icon_color = 'bg-info';
            }

            if ($this->type == 'support_request_response_added') {
                $link       = route('support-requests.show', [$data->support_request_id,]);
                $text       = $usr->name . " " . __('added a response to support request for') . " <b class='font-weight-bold'>" . $data->lead_name . "</b>";
                $icon       = "fa fa-comment";
                $icon_color = 'bg-primary';
            }

            if ($this->type == 'support_request_completed') {
                $link       = route('support-requests.show', [$data->support_request_id,]);
                $subscriptionInfo = '';
                if (isset($data->client_subscribed) && $data->client_subscribed) {
                    $amount = isset($data->subscription_amount) ? number_format($data->subscription_amount, 2) : '0.00';
                    $subscriptionInfo = " - " . __('Client subscribed') . " ($" . $amount . ")";
                }
                $text       = $usr->name . " " . __('completed support request for') . " <b class='font-weight-bold'>" . $data->lead_name . "</b>" . $subscriptionInfo;
                $icon       = "fa fa-check-circle";
                $icon_color = 'bg-success';
            }
            // end Support Requests

            $date = $this->created_at->diffForHumans();
            $html = '<a href="' . $link . '" class="list-group-item list-group-item-action">
                                <div class="d-flex align-items-center">
                                    <div>
                                        <span class="avatar ' . $icon_color . ' text-white rounded-circle"><i class="' . $icon . '"></i></span>
                                    </div>
                                    <div class="flex-fill ml-3">
                                        <div class="h6 text-sm mb-0">' . $text . '</div>
                                        <small class="text-muted text-xs">' . $date . '</small>
                                    </div>
                                </div>
                            </a>';
        } else {
            $html = '';
        }

        return $html;
    }
}
