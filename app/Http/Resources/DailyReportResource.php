<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DailyReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'report_date' => $this->report_date ? $this->report_date->toDateString() : null,
            'status' => $this->status,
            'metrics' => [
                'data_received' => $this->data_received,
                'sales_count' => $this->sales_count,
                'monthly_target_total' => (float) $this->monthly_target_total,
                'weekly_target_total' => (float) $this->weekly_target_total,
                'collections_actual' => (float) $this->collections_actual,
                'closed_deals' => $this->closed_deals,
                'calls_count' => $this->calls_count,
                'presentations_count' => $this->presentations_count,
            ],
            'logic_fields' => [
                'top_clients' => $this->top_clients,
                'ai_usage_logs' => $this->ai_usage_logs,
                'future_ideas' => $this->future_ideas,
                'sod_data' => $this->sod_data,
                'eod_data' => $this->eod_data,
            ],
            'audit_trail' => [
                'sod_submitted_at' => $this->sod_submitted_at ? $this->sod_submitted_at->toDateTimeString() : null,
                'eod_submitted_at' => $this->eod_submitted_at ? $this->eod_submitted_at->toDateTimeString() : null,
                'created_at' => $this->created_at ? $this->created_at->toDateTimeString() : null,
                'updated_at' => $this->updated_at ? $this->updated_at->toDateTimeString() : null,
            ],
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name ?? '',
                    'email' => $this->user->email ?? '',
                ];
            }),
            'cumulative_achievements' => $this->when(isset($this->cumulative_achievements), $this->cumulative_achievements)
        ];
    }
}
