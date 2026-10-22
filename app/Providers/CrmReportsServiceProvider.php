<?php

namespace App\Providers;

use App\Models\DealPurchase;
use App\Models\Lead;
use App\Models\LeadActivityLog;
use App\Models\SalesMeeting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

/**
 * Records the facts the CRM Reports need, at the model level, so every write path -
 * the web controllers, the mobile API and imports - is covered without touching each
 * controller (several of which differ between this checkout and production).
 *
 *  - sales_meetings.employee_id / deal_purchases.employee_id: the logged-in user who
 *    created the row. created_by on both only holds the company.
 *  - sales_meetings.status: taken from a `meeting_status` form field when one is sent.
 *  - A 'Move' activity log when a lead's stage changes outside the kanban drag (edit
 *    form, API). The kanban already writes its own log just before saving, so a Move
 *    by the same user on the same lead in the last few seconds is not written twice.
 *
 * Each hook checks its column exists, so this is safe to deploy before the migration.
 */
class CrmReportsServiceProvider extends ServiceProvider
{
    const MEETING_STATUSES = ['scheduled', 'done', 'cancelled', 'no_show'];

    public function boot(): void
    {
        SalesMeeting::creating(function (SalesMeeting $m) {
            if (self::has('sales_meetings', 'employee_id') && empty($m->employee_id) && Auth::check()) {
                $m->employee_id = Auth::id();
            }
        });

        SalesMeeting::saving(function (SalesMeeting $m) {
            if (!self::has('sales_meetings', 'status')) {
                return;
            }
            $status = request()->input('meeting_status');
            if (is_string($status) && in_array($status, self::MEETING_STATUSES, true)) {
                $m->status = $status;
            } elseif (request()->has('meeting_status') && ($status === null || $status === '')) {
                $m->status = null;
            }
        });

        DealPurchase::creating(function (DealPurchase $p) {
            if (self::has('deal_purchases', 'employee_id') && empty($p->employee_id) && Auth::check()) {
                $p->employee_id = Auth::id();
            }
        });

        Lead::updated(function (Lead $lead) {
            if (!$lead->wasChanged('stage_id') || !Auth::check()) {
                return;
            }

            $recent = LeadActivityLog::where('lead_id', $lead->id)
                ->where('user_id', Auth::id())
                ->where('log_type', 'Move')
                ->where('created_at', '>=', now()->subSeconds(10))
                ->exists();
            if ($recent) {
                return;
            }

            $old = \App\Models\LeadStage::find($lead->getOriginal('stage_id'));
            $new = \App\Models\LeadStage::find($lead->stage_id);
            LeadActivityLog::create([
                'user_id'  => Auth::id(),
                'lead_id'  => $lead->id,
                'log_type' => 'Move',
                'remark'   => json_encode([
                    'title'      => $lead->name,
                    'old_status' => $old->name ?? '',
                    'new_status' => $new->name ?? '',
                ]),
            ]);
        });
    }

    private static function has(string $table, string $column): bool
    {
        static $cache = [];
        $key = "$table.$column";
        if (!array_key_exists($key, $cache)) {
            try {
                $cache[$key] = Schema::hasColumn($table, $column);
            } catch (\Throwable $e) {
                $cache[$key] = false;
            }
        }

        return $cache[$key];
    }
}
