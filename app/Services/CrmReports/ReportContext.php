<?php

namespace App\Services\CrmReports;

use App\Models\User;
use App\Services\LeadScope;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Everything a CRM report query needs to know about who is asking and what they
 * filtered on: the company, which employees the viewer may see, the date window,
 * and the lead filters.
 *
 * Visibility follows the leads page exactly (LeadScope): company / CSO / the other
 * privileged types and holders of `view all leads` or `view all crm reports` see
 * every employee; a Sales Manager sees their sales group; everyone else sees only
 * themselves.
 *
 * A lead belongs to the employees it is assigned to in user_leads - the same rule
 * the leads page uses. leads.user_id is NOT the owner: it is whoever created or
 * imported the lead (in production one integration account holds ~90% of them).
 * A lead can be assigned to more than one employee and then counts for each.
 */
class ReportContext
{
    const PRESETS = ['today', '7d', '30d', '90d', 'mtd', 'last_month', 'qtd', 'ytd', 'all', 'custom'];

    public User $user;
    public int $companyId;

    /** Employees the viewer may see; null means the whole company. */
    public ?array $visibleIds;

    /** Employees the report is about: visibleIds narrowed by the employee filter. */
    public ?array $employeeIds;

    public string $preset;
    public Carbon $from;
    public Carbon $to;
    public Carbon $prevFrom;
    public Carbon $prevTo;

    public ?int $pipelineId;
    public ?int $sourceId;
    public ?string $campaign;
    public ?string $country;

    public static function fromRequest(Request $request, User $user): self
    {
        $ctx = new self();
        $ctx->user = $user;
        $ctx->companyId = (int) $user->creatorId();
        $ctx->visibleIds = self::visibleEmployeeIds($user);

        $selected = array_values(array_filter(array_map('intval', (array) $request->input('employees', []))));
        if ($selected) {
            $ctx->employeeIds = $ctx->visibleIds === null
                ? $selected
                : array_values(array_intersect($selected, $ctx->visibleIds));
            if (!$ctx->employeeIds) {
                $ctx->employeeIds = [0];
            }
        } else {
            $ctx->employeeIds = $ctx->visibleIds;
        }

        $ctx->resolveDates($request);

        $ctx->pipelineId = $request->filled('pipeline') ? (int) $request->input('pipeline') : null;
        $ctx->sourceId = $request->filled('source') ? (int) $request->input('source') : null;
        $ctx->campaign = $request->filled('campaign') ? (string) $request->input('campaign') : null;
        $ctx->country = $request->filled('country') ? (string) $request->input('country') : null;

        return $ctx;
    }

    /**
     * Null when the viewer sees the whole company, otherwise the user ids they
     * may report on. Never an empty array: a Sales Manager with no group sees [0].
     */
    public static function visibleEmployeeIds(User $user): ?array
    {
        if ($user->type === 'company'
            || in_array($user->type, LeadScope::PRIVILEGED_TYPES, true)
            || $user->can('view all crm reports')
            || $user->can('view all leads')) {
            return null;
        }

        if ($user->hasRole('Sales Manager') || $user->hasRole('sales manager')) {
            if (!$user->sales_group) {
                return [(int) $user->id];
            }

            $ids = User::where('created_by', $user->creatorId())
                ->where('sales_group', $user->sales_group)
                ->pluck('id')->map(fn ($id) => (int) $id)->all();
            $ids[] = (int) $user->id;

            return array_values(array_unique($ids));
        }

        return [(int) $user->id];
    }

    public function seesEveryone(): bool
    {
        return $this->visibleIds === null;
    }

    /** True when the viewer can only ever see themselves. */
    public function isSelfOnly(): bool
    {
        return $this->visibleIds !== null && count($this->visibleIds) === 1
            && (int) $this->visibleIds[0] === (int) $this->user->id;
    }

    private function resolveDates(Request $request): void
    {
        $preset = (string) $request->input('period', '30d');
        if (!in_array($preset, self::PRESETS, true)) {
            $preset = '30d';
        }

        $today = Carbon::today();
        switch ($preset) {
            case 'today':      [$from, $to] = [$today->copy(), $today->copy()]; break;
            case '7d':         [$from, $to] = [$today->copy()->subDays(6), $today->copy()]; break;
            case '90d':        [$from, $to] = [$today->copy()->subDays(89), $today->copy()]; break;
            case 'mtd':        [$from, $to] = [$today->copy()->startOfMonth(), $today->copy()]; break;
            case 'last_month': [$from, $to] = [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()]; break;
            case 'qtd':        [$from, $to] = [$today->copy()->firstOfQuarter(), $today->copy()]; break;
            case 'ytd':        [$from, $to] = [$today->copy()->startOfYear(), $today->copy()]; break;
            case 'all':        [$from, $to] = [Carbon::create(2000, 1, 1), $today->copy()]; break;
            case 'custom':
                $from = $this->parseDate($request->input('from')) ?? $today->copy()->subDays(29);
                $to = $this->parseDate($request->input('to')) ?? $today->copy();
                if ($from->gt($to)) {
                    [$from, $to] = [$to, $from];
                }
                break;
            default:           [$from, $to] = [$today->copy()->subDays(29), $today->copy()];
        }

        $this->preset = $preset;
        $this->from = $from->startOfDay();
        $this->to = $to->endOfDay();

        // The previous window of the same length, ending the day before.
        $days = $this->from->diffInDays($this->to->copy()->startOfDay()) + 1;
        $this->prevTo = $this->from->copy()->subDay()->endOfDay();
        $this->prevFrom = $this->from->copy()->subDays($days)->startOfDay();
    }

    private function parseDate($value): ?Carbon
    {
        if (!$value || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value)) {
            return null;
        }
        try {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function hasComparison(): bool
    {
        return $this->preset !== 'all';
    }

    /**
     * Restrict a query on `leads` (aliased $alias) to this company, the viewer's
     * employees and the lead filters. The date window is NOT applied here - each
     * report decides which date it filters on. Pass $employees = false when the
     * query already narrows the employees itself (through user_leads).
     *
     * @param \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $q
     */
    public function scopeLeads($q, string $alias = 'l', bool $employees = true)
    {
        $q->where("$alias.created_by", $this->companyId);

        if ($employees && $this->employeeIds !== null) {
            $ids = $this->employeeIds;
            $q->whereExists(function ($s) use ($alias, $ids) {
                $s->select(DB::raw(1))->from('user_leads as ulf')
                    ->whereColumn('ulf.lead_id', "$alias.id")
                    ->whereIn('ulf.user_id', $ids);
            });
        }
        if ($this->pipelineId) {
            $q->where("$alias.pipeline_id", $this->pipelineId);
        }
        if ($this->sourceId) {
            $q->whereRaw("FIND_IN_SET(?, $alias.sources)", [$this->sourceId]);
        }
        if ($this->campaign !== null) {
            $q->where("$alias.campaign_name", $this->campaign);
        }
        if ($this->country !== null) {
            $q->where("$alias.country", $this->country);
        }

        return $q;
    }

    /** scopeLeads plus "created inside the window". */
    public function scopeLeadCohort($q, string $alias = 'l', bool $previous = false, bool $employees = true)
    {
        $this->scopeLeads($q, $alias, $employees);

        return $q->whereBetween("$alias.created_at", $previous
            ? [$this->prevFrom, $this->prevTo]
            : [$this->from, $this->to]);
    }

    /** The filters as query-string parameters, for links and exports. */
    public function query(): array
    {
        return array_filter([
            'period'    => $this->preset,
            'from'      => $this->preset === 'custom' ? $this->from->toDateString() : null,
            'to'        => $this->preset === 'custom' ? $this->to->toDateString() : null,
            'employees' => request()->input('employees'),
            'pipeline'  => $this->pipelineId,
            'source'    => $this->sourceId,
            'campaign'  => $this->campaign,
            'country'   => $this->country,
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);
    }
}
