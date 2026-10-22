<?php

namespace App\Services\CrmReports;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The queries behind CRM Reports > Employee.
 *
 * Two lenses, and every number says which one it uses:
 *
 *  - Cohort: the leads CREATED in the window, and what has happened to them since
 *    (reports 1-4, 7, 8). Rates stay honest because numerator and denominator are
 *    the same set of leads.
 *  - Activity: the events that HAPPENED in the window - calls logged, meetings held
 *    (reports 5, 6).
 *
 * A lead belongs to the employees it is assigned to (user_leads, see ReportContext).
 * Per-employee rows credit a co-assigned lead to each assignee; team totals count
 * every lead once (teamFunnel), so they are not the sum of the rows.
 *
 * An "action" on a lead is a sales meeting or a deal (a purchase or installments
 * always come with a deal). Sales meetings are split by their type: "Call" and
 * "Presentation" (sales_meeting_types, matched by name).
 *
 * Everything is aggregated in SQL: production has ~200k leads, so nothing here may
 * load leads into PHP.
 */
class EmployeeReport
{
    const MEETING_STATUSES = ['scheduled', 'done', 'cancelled', 'no_show'];

    /** Neglect buckets: [key, min days, max days]. */
    const AGE_BUCKETS = [
        ['0_2', 0, 2],
        ['3_7', 3, 7],
        ['8_14', 8, 14],
        ['15_30', 15, 30],
        ['30_plus', 31, null],
    ];

    /** Journey stages, furthest first. */
    const JOURNEY = ['installment', 'purchase', 'deal', 'meeting', 'new'];

    /**
     * A quality evaluation someone actually filled in. Every assignment creates an
     * 'unknown' placeholder row, so the bare row means nothing; it counts once a
     * verdict, an engagement level or a rating has been saved, at updated_at.
     */
    const EVALUATED = "(q.is_potential_lead <> 'unknown' OR NULLIF(q.engagement_level, '') IS NOT NULL OR q.rating IS NOT NULL)";

    /** Who a meeting counts for (needs the `ul` join from meetingsQuery); 0 = nobody. */
    const MEETING_EMPLOYEE = 'COALESCE(m.employee_id, ul.user_id, 0)';

    /**
     * The meeting's date. `datetime` is what the forms and the API write; the old
     * `date` column holds 0000-00-00 on most production rows, so it is only a fallback.
     */
    /**
     * A text column as one fixed collation. Production's tables do not share a
     * collation (columns added at different times got different defaults), and
     * MariaDB refuses a UNION whose text columns differ: "Illegal mix of
     * collations for operation 'UNION'". Every text column in a UNION goes
     * through this.
     */
    private static function text(string $col, string $alias): \Illuminate\Database\Query\Expression
    {
        return DB::raw("CONVERT($col USING utf8mb4) COLLATE utf8mb4_unicode_ci as $alias");
    }

    const MEETING_DATE = "COALESCE(DATE(m.datetime), CASE WHEN m.date > '1000-01-01' THEN m.date END, DATE(m.created_at))";

    public function __construct(private ReportContext $ctx)
    {
    }

    // ---------------------------------------------------------- building blocks
    //
    // Production has ~200k leads and ~85% of them are assigned to nobody and were
    // never touched. Joining every one of them to their activity is what makes a
    // report slow, so no query here does that:
    //
    //  - per-employee figures are read from user_leads (one row per assignment);
    //  - activity figures come from the leads that HAVE activity (leadEvents);
    //  - the unassigned leads are never scanned one by one: their counts are
    //    "all cohort leads minus assigned ones", plus the activity-driven part.

    /** Per-request memo: several parts of one page ask for the same funnel. */
    private array $memo = [];

    private function memo(string $key, callable $fn)
    {
        if (!array_key_exists($key, $this->memo)) {
            $this->memo[$key] = $fn();
        }

        return $this->memo[$key];
    }

    /**
     * Results that several tabs share are kept for 5 minutes, per viewer scope and
     * filters, so moving between tabs does not recompute the whole funnel.
     */
    const CACHE_SECONDS = 300;

    private function cached(string $name, callable $fn)
    {
        $c = $this->ctx;
        $key = 'crm-reports.' . md5(json_encode([
            $name, app()->getLocale(), $c->companyId, $c->employeeIds, $c->from->toDateTimeString(), $c->to->toDateTimeString(),
            $c->prevFrom->toDateTimeString(), $c->hasComparison(), $c->pipelineId, $c->sourceId, $c->campaign, $c->country,
        ]));

        return $this->memo($name, fn () => \Cache::remember($key, self::CACHE_SECONDS, $fn));
    }

    /**
     * Narrow a leads query to the selection and to a window of arrival:
     * false = this period, true = the previous one, 'span' = both together (the
     * period column then tells them apart, see periodSql()).
     */
    private function scopeWindow($q, string $alias, $window, bool $employees = true)
    {
        $c = $this->ctx;
        [$from, $to] = $window === 'span'
            ? [$c->hasComparison() ? $c->prevFrom : $c->from, $c->to]
            : ($window === true ? [$c->prevFrom, $c->prevTo] : [$c->from, $c->to]);
        $c->scopeLeads($q, $alias, $employees);

        return $q->whereBetween("$alias.created_at", [$from, $to]);
    }

    /** 1 for a lead of this period, 0 for one of the previous period. */
    private function periodSql(): string
    {
        return "(l.created_at >= '" . $this->ctx->from->toDateTimeString() . "')";
    }

    /** Per-lead flags over the `ev` columns (NULL when a lead has no activity). */
    const FLAGS = [
        'actioned'    => 'ev.actions > 0',
        'met'         => 'ev.meetings > 0',
        'met_call'    => 'ev.meet_call > 0',
        'met_pres'    => 'ev.meet_pres > 0',
        'dealt'       => 'ev.deals > 0',
        'bought'      => 'ev.purchases > 0',
        'cash'        => 'ev.cash > 0',
        'installment' => 'ev.inst > 0',
        'qualified'   => 'ev.qualified > 0',
    ];

    /** Still worth following up: nothing purchased, and the latest deal is not Won or Lost. */
    const OPEN = '(COALESCE(ev.purchases, 0) = 0 AND COALESCE(ev.closed, 0) = 0)';

    /** Days since the lead's last action, or since it arrived if it never had one. */
    const IDLE = 'DATEDIFF(CURDATE(), COALESCE(ev.last_at, l.created_at))';
    const IDLE_CREATED = 'DATEDIFF(CURDATE(), l.created_at)';
    const IDLE_LAST = 'DATEDIFF(CURDATE(), ev.last_at)';

    /**
     * One row per cohort lead that has any activity, to be joined as `ev`:
     *   actions, last_at - the actions (sales meeting, deal) and when the last one
     *     happened;
     *   meetings, meet_call, meet_pres (meetings of type Call / Presentation),
     *   deals, closed (the latest deal is Won or Lost), purchases, cash, inst
     *   (installment purchases), installments
     *   (installment rows), qualified (evaluated as a potential lead), revenue.
     * Every source is limited to the cohort's leads before grouping.
     */
    private function leadEvents($window): \Illuminate\Database\Query\Builder
    {
        // Limiting every source to the window's leads pays off for short windows only;
        // over a long one it costs more than it saves (MariaDB, 200k leads: 0.3s vs
        // 1.4s for 30 days, 1.4s vs 0.6s for all time). Either way the rows that
        // reach a report are the same: they are joined to the window's leads after.
        $from = $window === true ? $this->ctx->prevFrom : ($window === 'span' && $this->ctx->hasComparison() ? $this->ctx->prevFrom : $this->ctx->from);
        $narrow = $from->diffInDays($this->ctx->to) <= 120;
        $ids = fn () => $this->scopeWindow(DB::table('leads as lx'), 'lx', $window, false)->select('lx.id');
        $limit = fn ($q, $col) => $narrow ? $q->whereIn($col, $ids()) : $q;
        $cols = fn ($lead, $k, $at = 'NULL', $x = 'NULL', $v = 'NULL') => [
            DB::raw("$lead as lead_id"), DB::raw("$at as at"), self::text("'$k'", 'k'), self::text($x, 'x'), DB::raw("$v as v"),
        ];

        $parts = [
            $limit(DB::table('sales_meetings as e')->leftJoin('sales_meeting_types as t', 't.id', '=', 'e.meeting_type_id'), 'e.lead_id')
                ->select($cols('e.lead_id', 'm', 'e.created_at', 'LOWER(TRIM(t.name))')),
            $limit(DB::table('deals as e'), 'e.lead_id')->select($cols('e.lead_id', 'd', 'e.created_at', 'LOWER(TRIM(e.status))', 'e.id')),
            $limit(DB::table('deal_purchases as p')->join('deals as e', 'e.id', '=', 'p.deal_id'), 'e.lead_id')
                ->select($cols('e.lead_id', 'p', 'NULL', 'p.payment_type', 'p.price')),
            $limit(DB::table('installments as i')->join('deal_purchases as p', 'p.id', '=', 'i.deal_purchase_id')
                ->join('deals as e', 'e.id', '=', 'p.deal_id'), 'e.lead_id')->select($cols('e.lead_id', 'i')),
            $limit(DB::table('quality_evaluations as q')->whereRaw(self::EVALUATED), 'q.lead_id')
                ->select($cols('q.lead_id', 'q', 'q.updated_at', 'q.is_potential_lead')),
        ];
        $union = array_shift($parts);
        foreach ($parts as $p) {
            $union->unionAll($p);
        }

        $isAction = "e.k IN ('m', 'd')";

        return DB::query()->fromSub($union, 'e')->groupBy('e.lead_id')->select(
            'e.lead_id',
            DB::raw("SUM($isAction) as actions"),
            DB::raw("MAX(CASE WHEN $isAction THEN e.at END) as last_at"),
            DB::raw("SUM(e.k = 'm') as meetings"),
            DB::raw("SUM(e.k = 'm' AND e.x = 'call') as meet_call"),
            DB::raw("SUM(e.k = 'm' AND e.x = 'presentation') as meet_pres"),
            DB::raw("SUM(e.k = 'd') as deals"),
            // The latest deal (highest id) is Won or Lost: the lead is settled either way.
            DB::raw("COALESCE(SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN e.k = 'd' THEN COALESCE(e.x, '') END ORDER BY e.v DESC SEPARATOR '|'), '|', 1) IN ('won', 'lost'), 0) as closed"),
            DB::raw("SUM(e.k = 'p') as purchases"),
            DB::raw("SUM(e.k = 'p' AND e.x = 'one-time') as cash"),
            DB::raw("SUM(e.k = 'p' AND e.x = 'installment') as inst"),
            DB::raw("SUM(e.k = 'i') as installments"),
            DB::raw("SUM(e.k = 'q' AND e.x = 'yes') as qualified"),
            DB::raw("SUM(CASE WHEN e.k = 'p' THEN e.v END) as revenue")
        );
    }

    /** "user_leads row is the lead's first assignment (within the selection)". */
    private function firstAssignmentSql(): string
    {
        $ids = $this->ctx->employeeIds;
        $in = $ids !== null ? ' AND u2.user_id IN (' . implode(',', array_map('intval', $ids)) . ')' : '';

        return "ul.id = (SELECT MIN(u2.id) FROM user_leads u2 WHERE u2.lead_id = ul.lead_id$in)";
    }

    /**
     * Assigned cohort leads, one row per (lead, assignee): `ul` + `l`, and `ev`
     * when $events. Narrowed to the selected employees' assignments.
     */
    private function assigned($window, bool $events = false): \Illuminate\Database\Query\Builder
    {
        $q = DB::table('user_leads as ul')->join('leads as l', 'l.id', '=', 'ul.lead_id');
        $this->scopeWindow($q, 'l', $window, false);
        if ($this->ctx->employeeIds !== null) {
            $q->whereIn('ul.user_id', $this->ctx->employeeIds);
        }
        if ($events) {
            $q->leftJoinSub($this->leadEvents($window), 'ev', 'ev.lead_id', '=', 'l.id');
        }

        return $q;
    }

    /** Assigned cohort leads, each once (driven from user_leads, never from leads). */
    private function assignedOnce($window, bool $events = false): \Illuminate\Database\Query\Builder
    {
        return $this->assigned($window, $events)->whereRaw($this->firstAssignmentSql());
    }

    /**
     * Every cohort lead in the selection, each once, as `l`. Without an employee
     * filter that includes the unassigned leads - only use it for lead-level
     * aggregates that MySQL can read off the (created_by, created_at) index.
     */
    private function leadsOnce($window): \Illuminate\Database\Query\Builder
    {
        if ($this->ctx->employeeIds !== null) {
            return $this->assignedOnce($window);
        }

        return $this->scopeWindow(DB::table('leads as l'), 'l', $window, false);
    }

    /** Cohort leads in the selection that have activity, each once: `ev` + `l`. */
    private function evented($window): \Illuminate\Database\Query\Builder
    {
        $q = DB::query()->fromSub($this->leadEvents($window), 'ev')->join('leads as l', 'l.id', '=', 'ev.lead_id');

        return $this->scopeWindow($q, 'l', $window);
    }

    /** As evented(), only the leads nobody is assigned to. */
    private function unassignedEvented($window): \Illuminate\Database\Query\Builder
    {
        return $this->evented($window)->whereNotExists(function ($s) {
            $s->select(DB::raw(1))->from('user_leads as una')->whereColumn('una.lead_id', 'l.id');
        });
    }

    /** The unassigned leads only exist as a row when nobody narrowed the employees. */
    private function showsUnassigned(): bool
    {
        return $this->ctx->employeeIds === null;
    }

    /**
     * Lead-level sums over the UNASSIGNED cohort leads, computed as all leads minus
     * the assigned ones so they are never joined one by one. $sums and $dims are SQL
     * over `l` only; $where narrows both sides. Returns [dims key => object].
     */
    private function unassignedLeadSums($window, array $sums, array $dims = [], ?callable $where = null): array
    {
        $run = function ($q) use ($sums, $dims, $where) {
            $select = [];
            foreach ($dims as $alias => $sql) {
                $select[] = DB::raw("$sql as $alias");
                $q->groupBy(DB::raw($sql));
            }
            foreach ($sums as $alias => $sql) {
                $select[] = DB::raw("SUM($sql) as $alias");
            }
            if ($where) {
                $where($q);
            }

            return $q->select($select)->get()
                ->keyBy(fn ($r) => implode('|', array_map(fn ($a) => (string) $r->$a, array_keys($dims))));
        };

        $all = $run($this->scopeWindow(DB::table('leads as l'), 'l', $window, false));
        $assigned = $run($this->assignedOnce($window));

        $out = [];
        foreach ($all as $key => $row) {
            $o = clone $row;
            foreach (array_keys($sums) as $alias) {
                $o->$alias = (float) $row->$alias - (float) ($assigned[$key]->$alias ?? 0);
            }
            $out[$key] = $o;
        }

        return $out;
    }

    /** How many unassigned cohort leads there are. */
    private function unassignedCount(bool $previous): int
    {
        return $this->funnels()[$previous ? 'prev' : 'cur']['unassigned'];
    }

    /** Names for a set of user ids, keyed by id. */
    private function names(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return [];
        }

        return DB::table('users')->whereIn('id', $ids)->pluck('name', 'id')->all();
    }

    private function ownerName(int $id, array $names): string
    {
        return $names[$id] ?? ($id ? __('Deleted user') . ' #' . $id : __('Unassigned'));
    }

    private static function pct($part, $whole): float
    {
        return $whole > 0 ? round($part * 100 / $whole, 1) : 0.0;
    }

    private static function delta($now, $before): ?float
    {
        if ($before === null) {
            return null;
        }
        if ((float) $before == 0.0) {
            return $now > 0 ? null : 0.0;
        }

        return round(($now - $before) * 100 / $before, 1);
    }

    // --------------------------------------------------------- per-employee core

    private function funnelSelects(): array
    {
        $selects = [DB::raw('COUNT(*) as leads')];
        foreach (self::FLAGS as $key => $sql) {
            $selects[] = DB::raw("SUM(CASE WHEN $sql THEN 1 ELSE 0 END) as $key");
        }
        $selects[] = DB::raw('COALESCE(SUM(ev.revenue), 0) as revenue');
        $selects[] = DB::raw('COALESCE(SUM(ev.meetings), 0) as meetings');

        return $selects;
    }

    /**
     * Both funnels - per employee and for the whole team - for this period and the
     * previous one, in one pass per query: the two periods are one span of leads
     * split by periodSql(), so the per-lead activity is built once, not twice.
     */
    private function funnels(): array
    {
        return $this->cached('funnels', function () {
            $period = $this->periodSql();
            $sel = fn (array $cols) => array_merge($cols, [DB::raw("$period as cur")], $this->funnelSelects());
            $out = ['cur' => ['rows' => collect(), 'unassigned' => 0], 'prev' => ['rows' => collect(), 'unassigned' => 0]];
            $slot = fn ($r) => $r->cur ? 'cur' : 'prev';

            foreach ($this->assigned('span', true)->select($sel(['ul.user_id']))->groupBy('ul.user_id', DB::raw($period))->get() as $r) {
                $out[$slot($r)]['rows']->push($r);
            }

            if ($this->showsUnassigned()) {
                $counts = $this->unassignedLeadSums('span', ['n' => '1'], ['cur' => $period]);
                $active = $this->unassignedEvented('span')->select($sel([]))->groupBy(DB::raw($period))->get()->keyBy('cur');
                foreach ([1 => 'cur', 0 => 'prev'] as $flag => $key) {
                    $n = (int) ($counts[(string) $flag]->n ?? 0);
                    $out[$key]['unassigned'] = $n;
                    if ($n > 0) {
                        $u = $active[$flag] ?? (object) [];
                        $u->user_id = 0;
                        $u->leads = $n;
                        $out[$key]['rows']->push($u);
                    }
                }

                // Everyone: count the leads off the index, take the activity from the leads that have some.
                $team = $this->evented('span')->select($sel([]))->groupBy(DB::raw($period))->get()->keyBy('cur');
                $leads = $this->leadsOnce('span')->select(DB::raw("$period as cur"), DB::raw('COUNT(*) as n'))
                    ->groupBy(DB::raw($period))->pluck('n', 'cur');
                foreach ([1 => 'cur', 0 => 'prev'] as $flag => $key) {
                    $t = $team[$flag] ?? (object) [];
                    $t->leads = (int) ($leads[$flag] ?? 0);
                    $out[$key]['team'] = self::funnelRates($t);
                }
            } else {
                $team = $this->assignedOnce('span', true)->select($sel([]))->groupBy(DB::raw($period))->get()->keyBy('cur');
                foreach ([1 => 'cur', 0 => 'prev'] as $flag => $key) {
                    $out[$key]['team'] = self::funnelRates($team[$flag] ?? (object) []);
                }
            }

            foreach ($out as $key => $part) {
                $names = $this->names($part['rows']->pluck('user_id')->all());
                $out[$key]['rows'] = $part['rows']->map(function ($r) use ($names) {
                    $r->user_id = (int) $r->user_id;
                    $r->name = $this->ownerName($r->user_id, $names);
                    unset($r->cur);

                    return self::funnelRates($r);
                })->sortByDesc('leads')->values();
            }

            return $out;
        });
    }

    /**
     * The cohort funnel per employee - the table reports 1, 2, 7 and 8 are cut
     * from - plus an "Unassigned" row (user_id 0) when every employee is shown.
     */
    public function employeeFunnel(bool $previous = false): Collection
    {
        // A copy: callers add columns to the rows.
        return $this->funnels()[$previous ? 'prev' : 'cur']['rows']->map(fn ($r) => clone $r);
    }

    /** The same funnel over the whole selection, each lead counted once. */
    public function teamFunnel(bool $previous = false): object
    {
        return clone $this->funnels()[$previous ? 'prev' : 'cur']['team'];
    }

    private static function funnelRates(object $r): object
    {
        foreach (['leads', 'actioned', 'met', 'met_call', 'met_pres', 'dealt', 'bought', 'cash', 'installment', 'qualified', 'meetings'] as $k) {
            $r->$k = (int) ($r->$k ?? 0);
        }
        $r->revenue = (float) ($r->revenue ?? 0);
        $r->action_rate = self::pct($r->actioned, $r->leads);
        $r->meeting_rate = self::pct($r->met, $r->leads);
        $r->call_rate = self::pct($r->met_call, $r->leads);
        $r->presentation_rate = self::pct($r->met_pres, $r->leads);
        $r->conversion = self::pct($r->dealt, $r->leads);
        $r->purchase_rate = self::pct($r->bought, $r->leads);

        return $r;
    }

    /** Header KPIs with the change against the previous window. */
    public function kpis(): array
    {
        $now = $this->teamFunnel();
        $before = $this->ctx->hasComparison() ? $this->teamFunnel(true) : null;

        $build = function ($key, $label, $icon, $value, $prev, $format = 'int', $suffix = '') {
            return compact('key', 'label', 'icon', 'value', 'format', 'suffix') + ['delta' => self::delta($value, $prev)];
        };

        return [
            $build('leads', __('Leads received'), 'ti-users', $now->leads, $before->leads ?? null),
            $build('meetings', __('Meetings'), 'ti-calendar-event', $now->meetings, $before->meetings ?? null),
            $build('deals', __('Leads converted to deal'), 'ti-briefcase', $now->dealt, $before->dealt ?? null),
            $build('conversion', __('Conversion rate'), 'ti-trending-up', $now->conversion, $before->conversion ?? null, 'pct', '%'),
        ];
    }

    // ------------------------------------------------------------------ report 1

    /** Leads per employee: count, share, change, and the arrival trend. */
    public function leadsPerEmployee(): array
    {
        $now = $this->employeeFunnel();
        $comparing = $this->ctx->hasComparison();
        $before = $comparing ? $this->employeeFunnel(true)->keyBy('user_id') : collect();

        $rows = $now->map(fn ($r) => (object) [
            'user_id' => $r->user_id,
            'name'    => $r->name,
            'leads'   => $r->leads,
            'prev'    => $before[$r->user_id]->leads ?? 0,
        ]);

        // Totals count each lead once: a co-assigned lead is in two rows but once here.
        return [
            'rows'      => $rows,
            'total'     => $this->teamFunnel()->leads,
            'prevTotal' => $comparing ? $this->teamFunnel(true)->leads : null,
            'source'    => $this->cached('sources', fn () => $this->leadsBySource()),
        ];
    }

    /** Day or week buckets depending on window length. */
    private function bucketSql(string $column): array
    {
        $days = $this->ctx->from->diffInDays($this->ctx->to);
        if ($days <= 62) {
            return ["DATE($column)", 'day'];
        }
        if ($days <= 400) {
            return ["DATE_SUB(DATE($column), INTERVAL WEEKDAY($column) DAY)", 'week'];
        }

        return ["DATE_FORMAT($column, '%Y-%m-01')", 'month'];
    }

    /**
     * Count leads per source from per-`sources`-value counts: `sources` is a comma
     * list of ids, so it is grouped as a string in SQL and split here - far cheaper
     * than FIND_IN_SET against every source for every lead. [label => n], top $limit.
     */
    private function countBySource(Collection $groups, int $limit): Collection
    {
        $byId = [];
        $none = 0;
        foreach ($groups as $g) {
            $ids = array_unique(array_filter(array_map('intval', explode(',', (string) $g->label))));
            if (!$ids) {
                $none += (int) $g->n;
            }
            foreach ($ids as $id) {
                $byId[$id] = ($byId[$id] ?? 0) + (int) $g->n;
            }
        }
        $names = $byId ? DB::table('sources')->whereIn('id', array_keys($byId))->pluck('name', 'id')->all() : [];

        $out = [];
        foreach ($byId as $id => $n) {
            if (isset($names[$id])) {
                $out[$names[$id]] = ($out[$names[$id]] ?? 0) + $n;
            } else {
                $none += $n;
            }
        }
        if ($none > 0) {
            $out[__('No source')] = ($out[__('No source')] ?? 0) + $none;
        }
        arsort($out);

        return collect(array_slice($out, 0, $limit, true))->map(fn ($n, $label) => (object) ['label' => $label, 'n' => $n])->values();
    }

    /** Leads per source (a lead can carry several sources). */
    private function leadsBySource(): Collection
    {
        $groups = $this->leadsOnce(false)->select(DB::raw("COALESCE(l.sources, '') as label"), DB::raw('COUNT(*) as n'))
            ->groupBy('l.sources')->get();

        return $this->countBySource($groups, 12)->map(fn ($r) => (object) ['source' => $r->label, 'n' => $r->n]);
    }

    // ------------------------------------------------------------------ report 2

    /** Leads actioned and converted, per employee, plus the overall funnel. */
    public function actionAndConversion(): array
    {
        $rows = $this->employeeFunnel();
        $team = $this->teamFunnel();
        $t = fn ($k) => $team->$k;

        return [
            'rows'   => $rows,
            'funnel' => [
                ['key' => 'leads', 'label' => __('Leads received'), 'n' => $t('leads')],
                ['key' => 'actioned', 'label' => __('Action taken'), 'n' => $t('actioned')],
                ['key' => 'met', 'label' => __('Had a meeting'), 'n' => $t('met')],
                ['key' => 'met_call', 'label' => __('Call meeting'), 'n' => $t('met_call')],
                ['key' => 'met_pres', 'label' => __('Presentation meeting'), 'n' => $t('met_pres')],
                ['key' => 'dealt', 'label' => __('Converted to deal'), 'n' => $t('dealt')],
                ['key' => 'bought', 'label' => __('Purchased'), 'n' => $t('bought')],
            ],
        ];
    }

    // ------------------------------------------------------------------ report 3

    /** Leads per employee per deal status, and per customer-journey stage. */
    public function stages(): array
    {
        return $this->cached('stages', fn () => $this->statusMatrix() + ['journey' => $this->journey()]);
    }

    /**
     * Leads per owner per deal status (deals.status). A lead counts under every
     * status its deals have - one with a Lost deal and a Won deal is in both - so
     * a row's statuses can add up to more than its leads. Leads without a deal
     * are their own column; total = the owner's leads, each once.
     */
    private function statusMatrix(): array
    {
        $status = "COALESCE(NULLIF(TRIM(d.status), ''), '')";
        $count = fn ($q) => $q->join('deals as d', 'd.lead_id', '=', 'l.id')
            ->select(DB::raw("$status as status"), DB::raw('COUNT(DISTINCT l.id) as n'))->groupBy(DB::raw($status));

        $counts = $count($this->assigned(false))->addSelect('ul.user_id')->groupBy('ul.user_id')->get();
        if ($this->showsUnassigned()) {
            $un = $count($this->scopeWindow(DB::table('leads as l'), 'l', false, false))->whereNotExists(function ($s) {
                $s->select(DB::raw(1))->from('user_leads as una')->whereColumn('una.lead_id', 'l.id');
            })->get();
            foreach ($un as $u) {
                $u->user_id = 0;
                $counts->push($u);
            }
        }
        // "close" and "Close" are one status (the column compares case-insensitively),
        // but separate queries may spell it either way: keep the first spelling seen.
        $spelling = [];
        foreach ($counts as $c) {
            $c->status = $spelling[mb_strtolower($c->status)] ??= (string) $c->status;
        }
        $byUser = $counts->groupBy(fn ($c) => (int) $c->user_id);

        // The funnel already has every owner's leads and how many of them have a deal.
        $matrix = [];
        foreach ($this->employeeFunnel() as $r) {
            $matrix[$r->user_id] = [
                'name'     => $r->name,
                'statuses' => ($byUser[$r->user_id] ?? collect())->mapWithKeys(fn ($c) => [$c->status => (int) $c->n])->all(),
                'none'     => $r->leads - $r->dealt,
                'total'    => $r->leads,
            ];
        }

        $statuses = $counts->groupBy('status')->map(fn ($g) => $g->sum('n'))->sortDesc()->keys()->map(fn ($k) => (string) $k)->all();

        return ['statuses' => $statuses, 'matrix' => $matrix];
    }

    /** Each cohort lead counted once per owner, at the furthest point it has reached. */
    private function journey(): array
    {
        $case = "CASE WHEN ev.installments > 0 THEN 'installment' WHEN ev.purchases > 0 THEN 'purchase' WHEN ev.deals > 0 THEN 'deal'"
            . " WHEN ev.meetings > 0 THEN 'meeting' ELSE 'new' END";

        $rows = $this->assigned(false, true)->select('ul.user_id', DB::raw("$case as stage"), DB::raw('COUNT(*) as n'))
            ->groupBy('ul.user_id', DB::raw($case))->get();

        if ($this->showsUnassigned() && ($total = $this->unassignedCount(false)) > 0) {
            $active = $this->unassignedEvented(false)->select(DB::raw("$case as stage"), DB::raw('COUNT(*) as n'))
                ->groupBy(DB::raw($case))->get();
            foreach ($active as $a) {
                $rows->push((object) ['user_id' => 0, 'stage' => $a->stage, 'n' => (int) $a->n]);
            }
            // The rest of the unassigned leads have no activity at all.
            $rows->push((object) ['user_id' => 0, 'stage' => 'new', 'n' => $total - $active->sum('n')]);
        }

        $names = $this->names($rows->pluck('user_id')->all());
        $out = [];
        foreach ($rows as $r) {
            $uid = (int) $r->user_id;
            $out[$uid] ??= ['name' => $this->ownerName($uid, $names), 'stages' => array_fill_keys(self::JOURNEY, 0), 'total' => 0];
            $out[$uid]['stages'][$r->stage] += (int) $r->n;
            $out[$uid]['total'] += (int) $r->n;
        }
        uasort($out, fn ($a, $b) => $b['total'] <=> $a['total']);

        return $out;
    }

    // ------------------------------------------------------------------ report 4

    /**
     * Leads nobody is working: never actioned, or last action older than $staleDays.
     * Leads that already bought, or whose latest deal is Won or Lost, are settled and left out.
     */
    public function neglected(int $staleDays, int $page = 1): array
    {
        $stale = (int) $staleDays;
        $agg = $this->cached("neglected.$stale", fn () => $this->neglectedAggregates($stale));

        $perPage = 25;
        $page = max(1, $page);
        $items = $this->neglectedList($stale, ($page - 1) * $perPage, $perPage);
        $list = new LengthAwarePaginator($items, $agg['total'], $perPage, $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'pageName' => 'page']);

        return [
            'rows'       => $agg['rows'],
            'bySource'   => $agg['bySource'],
            'total'      => $agg['total'],
            'byCampaign' => $agg['byCampaign'],
            'list'       => $list,
            'staleDays'  => $staleDays,
        ];
    }

    /** Everything on the neglect tab except the list itself. */
    private function neglectedAggregates(int $stale): array
    {
        $bucket = fn ($idle, $min, $max) => $max === null ? "$idle >= $min" : "$idle BETWEEN $min AND $max";

        // Assigned leads, per employee.
        $selects = [
            'ul.user_id',
            DB::raw('COUNT(*) as open_leads'),
            DB::raw('SUM(COALESCE(ev.actions, 0) = 0) as never'),
            DB::raw('SUM(ev.actions > 0 AND ' . self::IDLE . " > $stale) as stale"),
            DB::raw('SUM(' . self::IDLE . ') as idle_sum'),
        ];
        foreach (self::AGE_BUCKETS as [$key, $min, $max]) {
            $selects[] = DB::raw('SUM(' . $bucket(self::IDLE, $min, $max) . ") as b_$key");
        }
        $rows = $this->assigned(false, true)->whereRaw(self::OPEN)
            ->select($selects)->groupBy('ul.user_id')->get();

        if ($this->showsUnassigned() && $this->unassignedCount(false) > 0) {
            $rows->push($this->unassignedNeglect($stale, $bucket));
        }

        $names = $this->names($rows->pluck('user_id')->all());
        $rows = $rows->map(function ($r) use ($names) {
            foreach ($r as $k => $v) {
                $r->$k = (int) round((float) $v);
            }
            $r->name = $this->ownerName($r->user_id, $names);
            $r->avg_idle = $r->open_leads > 0 ? round($r->idle_sum / $r->open_leads, 1) : 0;
            $r->neglect_rate = self::pct($r->never + $r->stale, $r->open_leads);

            return $r;
        })->filter(fn ($r) => $r->open_leads > 0)->sortByDesc(fn ($r) => $r->never + $r->stale)->values();

        $dims = $this->neglectBySourceAndCampaign($stale);
        $sum = fn ($key) => $dims->groupBy($key)->map(fn ($g, $label) => (object) ['label' => (string) $label, 'n' => $g->sum('n')])
            ->filter(fn ($r) => $r->n > 0)->values();

        return [
            'rows'       => $rows,
            'total'      => $dims->sum('n'),
            'bySource'   => $this->countBySource($sum('src'), 10),
            'byCampaign' => $sum('camp')
                ->map(fn ($r) => (object) ['label' => $r->label !== '' ? $r->label : __('No campaign'), 'n' => (int) $r->n])
                ->sortByDesc('n')->take(10)->values(),
        ];
    }

    /**
     * The neglect row for the unassigned leads, without scanning them:
     *   never  = unassigned leads - unassigned leads with an action
     *   idle   = days since arrival for the never-actioned ones, since the last
     *            action for the others (both summed, and bucketed).
     */
    private function unassignedNeglect(int $stale, callable $bucket): object
    {
        $leadSums = ['n' => '1', 'idle' => self::IDLE_CREATED];
        foreach (self::AGE_BUCKETS as [$key, $min, $max]) {
            $leadSums["b_$key"] = $bucket(self::IDLE_CREATED, $min, $max);
        }
        $all = $this->unassignedLeadSums(false, $leadSums)[''] ?? (object) array_fill_keys(array_keys($leadSums), 0);

        $open = 'ev.actions > 0 AND ' . self::OPEN;
        $sel = [
            DB::raw('SUM(ev.actions > 0) as acted'),
            DB::raw('SUM(NOT ' . self::OPEN . ') as settled'),
            DB::raw('SUM(CASE WHEN ev.actions > 0 THEN ' . self::IDLE_CREATED . ' ELSE 0 END) as acted_idle_created'),
            DB::raw("SUM(CASE WHEN $open THEN " . self::IDLE_LAST . ' ELSE 0 END) as open_idle'),
            DB::raw("SUM($open AND " . self::IDLE_LAST . " > $stale) as stale"),
        ];
        foreach (self::AGE_BUCKETS as [$key, $min, $max]) {
            $sel[] = DB::raw('SUM(ev.actions > 0 AND ' . $bucket(self::IDLE_CREATED, $min, $max) . ") as acted_b_$key");
            $sel[] = DB::raw("SUM($open AND " . $bucket(self::IDLE_LAST, $min, $max) . ") as open_b_$key");
        }
        $e = $this->unassignedEvented(false)->select($sel)->first();

        $r = (object) [
            'user_id'    => 0,
            'open_leads' => $all->n - (float) $e->settled,
            'never'      => $all->n - (float) $e->acted,
            'stale'      => (float) $e->stale,
            'idle_sum'   => ($all->idle - (float) $e->acted_idle_created) + (float) $e->open_idle,
        ];
        foreach (self::AGE_BUCKETS as [$key]) {
            $r->{"b_$key"} = ($all->{"b_$key"} - (float) $e->{"acted_b_$key"}) + (float) $e->{"open_b_$key"};
        }

        return $r;
    }

    /**
     * Neglected leads (each once) per (sources value, campaign), as
     * (all leads) - (leads with an action) + (stale leads): one pass over the leads
     * index and one over the leads with activity, whatever the window.
     */
    private function neglectBySourceAndCampaign(int $stale): Collection
    {
        $src = "COALESCE(l.sources, '')";
        $camp = "COALESCE(NULLIF(l.campaign_name, ''), '')";
        $key = fn ($r) => $r->src . "" . $r->camp;

        $all = $this->leadsOnce(false)->select(DB::raw("$src as src"), DB::raw("$camp as camp"), DB::raw('COUNT(*) as n'))
            ->groupBy(DB::raw($src), DB::raw($camp))->get();
        $ev = $this->evented(false)->select(DB::raw("$src as src"), DB::raw("$camp as camp"),
            DB::raw('SUM(ev.actions > 0) as acted'),
            DB::raw('SUM(ev.actions > 0 AND ' . self::OPEN . ' AND ' . self::IDLE_LAST . " > $stale) as stale"))
            ->groupBy(DB::raw($src), DB::raw($camp))->get()->keyBy($key);

        return $all->map(function ($r) use ($ev, $key) {
            $e = $ev[$key($r)] ?? null;
            $r->n = (int) $r->n - (int) ($e->acted ?? 0) + (int) ($e->stale ?? 0);

            return $r;
        });
    }

    /**
     * The neglected leads themselves, most idle first. Shared by the page and the
     * export. Built from two short lists - the oldest never-actioned leads (read in
     * arrival order off the index, so MySQL stops early) and the stalest actioned
     * ones - so it never ranks every lead.
     */
    public function neglectedList(int $staleDays, int $offset, int $limit): Collection
    {
        $stale = (int) $staleDays;
        $take = $offset + $limit;
        $cols = fn ($lastAt, $idle) => ['l.id', 'l.name', 'l.phone', 'l.created_at', 'l.campaign_name', 'l.country',
            DB::raw("$lastAt as last_at"), DB::raw("$idle as idle_days")];

        // No action - the same sources as leadEvents' actions.
        $never = $this->leadsOnce(false);
        foreach (['sales_meetings', 'deals'] as $table) {
            $never->whereNotExists(function ($s) use ($table) {
                $s->select(DB::raw(1))->from("$table as x")->whereColumn('x.lead_id', 'l.id');
            });
        }
        $never->select($cols('NULL', self::IDLE_CREATED))->orderBy('l.created_at')->orderBy('l.id')->limit($take);

        $staleQ = $this->evented(false)
            ->whereRaw('ev.actions > 0 AND ' . self::OPEN . ' AND ' . self::IDLE_LAST . " > $stale")
            ->select($cols('ev.last_at', self::IDLE_LAST))->orderBy('ev.last_at')->orderBy('l.id')->limit($take);

        return DB::query()->fromSub($never->unionAll($staleQ), 'x')
            ->select('x.*',
                DB::raw("(SELECT GROUP_CONCAT(DISTINCT d.status ORDER BY d.status SEPARATOR ', ') FROM deals d WHERE d.lead_id = x.id) as deal_status"),
                DB::raw('(SELECT GROUP_CONCAT(DISTINCT ulo.user_id) FROM user_leads ulo WHERE ulo.lead_id = x.id) as owner_ids'))
            ->orderByDesc('x.idle_days')->orderBy('x.id')->offset($offset)->limit($limit)->get()
            ->tap(fn ($rows) => $this->attachOwners($rows, 'owner_ids', 'owner'));
    }

    /**
     * Resolve a user-id column on already-fetched rows into a name column. The
     * column may hold a comma-separated list (a lead with several assignees).
     */
    public function attachOwners(iterable $items, string $idKey, string $nameKey): void
    {
        $items = collect($items);
        $split = fn ($v) => array_filter(array_map('intval', explode(',', (string) $v)));
        $names = $this->names($items->flatMap(fn ($it) => $split($it->$idKey))->all());
        foreach ($items as $it) {
            $list = array_map(fn ($id) => $names[$id] ?? __('Deleted user') . ' #' . $id, $split($it->$idKey));
            $it->$nameKey = $list ? implode(', ', $list) : __('Unassigned');
        }
    }

    // ------------------------------------------------------------------ report 5

    const LOG_KINDS = ['meeting', 'evaluation'];

    /** Meeting types the reports name; anything else is "other". */
    const MEETING_TYPES = ['call', 'presentation'];

    /** A meeting type as shown: Call / Presentation, other types by their own name. */
    public static function meetingTypeLabel(?string $type): string
    {
        return ['call' => __('Call'), 'presentation' => __('Presentation')][$type] ?? ($type !== null && $type !== '' ? ucfirst($type) : __('No type'));
    }

    public static function meetingStatusLabel(?string $status): string
    {
        return [
            'scheduled' => __('Scheduled'), 'done' => __('Done'), 'no_show' => __('No show'), 'cancelled' => __('Cancelled'),
            'past' => __('Past (no status)'), 'upcoming' => __('Upcoming (no status)'),
        ][$status] ?? (string) $status;
    }

    /** "Potential Lead (Moderate)" - the same labels the Quality Evaluations page uses. */
    public static function evaluationLabel(?string $verdict, ?string $engagement): string
    {
        $verdicts = ['yes' => __('Potential Lead'), 'no' => __('Not Potential')];
        $levels = ['highly_engaged' => __('Highly Engaged'), 'moderate' => __('Moderate'),
            'low_engagement' => __('Low Engagement'), 'not_engaged' => __('Not Engaged')];
        $v = $verdicts[$verdict] ?? null;
        $l = $levels[$engagement] ?? null;

        return $v && $l ? "$v ($l)" : (string) ($v ?: $l);
    }

    /**
     * Activity-lens union of sales meetings and quality evaluations, scoped through
     * the lead. Columns: kind, id, lead_id, actor, created_at, title, body, outcome,
     * detail. A meeting is dated by its meeting date, counts for the employee
     * recorded on it (older ones: the lead's first assignee), and carries its type
     * in `outcome` and its status in `detail`. An evaluation carries the
     * potential-lead verdict in `outcome` and the engagement level in `detail`.
     */
    private function communications(?string $type = null): \Illuminate\Database\Query\Builder
    {
        $t = fn ($col, $alias) => self::text($col, $alias);
        $from = $this->ctx->from->toDateString();
        $to = $this->ctx->to->toDateString();

        $meetings = DB::table('sales_meetings as m')->join('leads as l', 'l.id', '=', 'm.lead_id')
            ->leftJoin('sales_meeting_types as t', 't.id', '=', 'm.meeting_type_id')
            ->whereRaw(self::MEETING_DATE . ' BETWEEN ? AND ?', [$from, $to])
            ->select($t("'meeting'", 'kind'), 'm.id', 'm.lead_id',
                DB::raw('COALESCE(m.employee_id, (SELECT MIN(ulm.user_id) FROM user_leads ulm WHERE ulm.lead_id = l.id)) as actor'),
                DB::raw('COALESCE(m.datetime, m.created_at) as created_at'),
                $t('m.title', 'title'), $t('m.notes', 'body'), $t('LOWER(TRIM(t.name))', 'outcome'), $t(self::meetingStatusSql(), 'detail'));
        $evals = DB::table('quality_evaluations as q')->join('leads as l', 'l.id', '=', 'q.lead_id')
            ->whereRaw(self::EVALUATED)
            ->whereBetween('q.updated_at', [$this->ctx->from, $this->ctx->to])
            ->select($t("'evaluation'", 'kind'), 'q.id', 'q.lead_id', 'q.evaluator_id as actor', 'q.updated_at as created_at',
                $t('NULL', 'title'), $t('q.notes', 'body'), $t('q.is_potential_lead', 'outcome'), $t('q.engagement_level', 'detail'));

        $parts = ['meeting' => $meetings, 'evaluation' => $evals];
        if ($type && isset($parts[$type])) {
            $parts = [$type => $parts[$type]];
        }

        $union = null;
        foreach ($parts as $part) {
            // Company + filters through the lead; the window is on the event itself.
            $this->ctx->scopeLeads($part, 'l');
            $union = $union ? $union->unionAll($part) : $part;
        }

        return DB::query()->fromSub($union, 'ev');
    }

    public function communicationLog(?string $type, int $page = 1): array
    {
        $summary = $this->communications($type)
            ->select('actor', 'kind', 'outcome', DB::raw('COUNT(*) as n'))
            ->groupBy('actor', 'kind', 'outcome')->get();

        $names = $this->names($summary->pluck('actor')->all());
        $perEmployee = [];
        foreach ($summary as $s) {
            $uid = (int) $s->actor;
            $perEmployee[$uid] ??= [
                'name' => $this->ownerName($uid, $names),
                'call' => 0, 'presentation' => 0, 'other' => 0, 'evaluation' => 0, 'potential' => 0,
            ];
            if ($s->kind === 'meeting') {
                $perEmployee[$uid][in_array($s->outcome, self::MEETING_TYPES, true) ? $s->outcome : 'other'] += (int) $s->n;
            } else {
                $perEmployee[$uid]['evaluation'] += (int) $s->n;
                if ($s->outcome === 'yes') {
                    $perEmployee[$uid]['potential'] += (int) $s->n;
                }
            }
        }
        $total = fn ($e) => $e['call'] + $e['presentation'] + $e['other'] + $e['evaluation'];
        uasort($perEmployee, fn ($a, $b) => $total($b) <=> $total($a));

        $log = $this->communicationLogQuery($type)->paginate(25, ['*'], 'page', $page);
        $this->prepareLogRows($log->items());

        return ['perEmployee' => $perEmployee, 'log' => $log, 'type' => $type];
    }

    /** The log rows, newest first. Shared by the page and the export. */
    public function communicationLogQuery(?string $type): \Illuminate\Database\Query\Builder
    {
        return $this->communications($type)
            ->leftJoin('leads as ll', 'll.id', '=', 'ev.lead_id')
            ->select('ev.*', 'll.name as lead_name', 'll.phone as lead_phone')
            ->orderByDesc('ev.created_at')->orderByDesc('ev.id');
    }

    public function prepareLogRows(iterable $items): void
    {
        $this->attachOwners($items, 'actor', 'actor_name');
        foreach ($items as $it) {
            $it->body = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $it->body))));
        }
    }

    // ------------------------------------------------------------------ report 6

    /** Meetings that happened (or are booked) in the window, by meeting date. */
    private function meetingsQuery(array $f): \Illuminate\Database\Query\Builder
    {
        $date = self::MEETING_DATE;
        $q = DB::table('sales_meetings as m')->join('leads as l', 'l.id', '=', 'm.lead_id')
            ->leftJoin('sales_meeting_types as t', 't.id', '=', 'm.meeting_type_id')
            ->whereRaw("$date BETWEEN ? AND ?", [$this->ctx->from->toDateString(), $this->ctx->to->toDateString()]);

        // Credit the employee recorded on the meeting. Older meetings, from before
        // that was recorded, go to the lead's assignees - the join only runs for those
        // rows, so a recorded meeting is never repeated per assignee.
        $ids = $this->ctx->employeeIds;
        $q->leftJoin('user_leads as ul', function ($j) use ($ids) {
            $j->on('ul.lead_id', '=', 'm.lead_id')->whereNull('m.employee_id');
            if ($ids !== null) {
                $j->whereIn('ul.user_id', $ids);
            }
        });
        $q->where('l.created_by', $this->ctx->companyId);
        if ($ids !== null) {
            $q->whereRaw(self::MEETING_EMPLOYEE . ' IN (' . implode(',', array_map('intval', $ids)) . ')');
        }
        if ($this->ctx->pipelineId) {
            $q->where('l.pipeline_id', $this->ctx->pipelineId);
        }
        if ($this->ctx->sourceId) {
            $q->whereRaw('FIND_IN_SET(?, l.sources)', [$this->ctx->sourceId]);
        }
        if ($this->ctx->campaign !== null) {
            $q->where('l.campaign_name', $this->ctx->campaign);
        }
        $country = $f['meeting_country'] ?? $this->ctx->country;
        if ($country) {
            $q->where('l.country', $country);
        }
        if (!empty($f['meeting_type'])) {
            $q->where('m.meeting_type_id', (int) $f['meeting_type']);
        }
        if (!empty($f['meeting_status'])) {
            $q->whereRaw(self::meetingStatusSql() . ' = ?', [$f['meeting_status']]);
        }

        return $q;
    }

    /** Recorded status, or past/upcoming from the date for meetings that predate the field. */
    public static function meetingStatusSql(): string
    {
        return "COALESCE(m.status, CASE WHEN " . self::MEETING_DATE . " < CURDATE() THEN 'past' ELSE 'upcoming' END)";
    }

    public function meetings(array $f): array
    {
        $emp = self::MEETING_EMPLOYEE;
        $status = self::meetingStatusSql();

        $perEmployee = $this->meetingsQuery($f)
            ->select(DB::raw("$emp as uid"), DB::raw('COUNT(*) as n'), DB::raw('COUNT(DISTINCT m.lead_id) as leads'),
                DB::raw('SUM(EXISTS(SELECT 1 FROM deals d WHERE d.lead_id = m.lead_id)) as to_deal'),
                DB::raw("SUM($status = 'done') as done"), DB::raw("SUM($status = 'no_show') as no_show"),
                DB::raw("SUM($status = 'cancelled') as cancelled"))
            ->groupBy('uid')->orderByDesc('n')->get();

        $byType = $this->meetingsQuery($f)
            ->select(DB::raw("$emp as uid"), DB::raw("COALESCE(t.name, '') as type"), DB::raw('COUNT(*) as n'))
            ->groupBy('uid', 'type')->get();
        $byStatus = $this->meetingsQuery($f)
            ->select(DB::raw("$status as status"), DB::raw('COUNT(*) as n'))->groupBy('status')->get();
        $byCountry = $this->meetingsQuery($f)
            ->select(DB::raw("COALESCE(NULLIF(l.country, ''), '') as country"), DB::raw('COUNT(*) as n'))
            ->groupBy('country')->orderByDesc('n')->limit(10)->get();

        [$bucket, $unit] = $this->bucketSql(self::MEETING_DATE);
        $trend = $this->meetingsQuery($f)->select(DB::raw("$bucket as b"), DB::raw('COUNT(*) as n'))
            ->groupBy('b')->orderBy('b')->get();

        $names = $this->names($perEmployee->pluck('uid')->merge($byType->pluck('uid'))->all());
        $types = $byType->pluck('type')->unique()->values()->all();
        $typeMatrix = [];
        foreach ($byType as $r) {
            $typeMatrix[(int) $r->uid][$r->type] = (int) $r->n;
        }

        return [
            'perEmployee' => $perEmployee->map(function ($r) use ($names) {
                $r->name = $names[(int) $r->uid] ?? __('Unassigned');
                foreach (['n', 'leads', 'to_deal', 'done', 'no_show', 'cancelled'] as $k) {
                    $r->$k = (int) $r->$k;
                }
                $r->deal_rate = self::pct($r->to_deal, $r->n);

                return $r;
            }),
            'types'      => $types,
            'typeMatrix' => $typeMatrix,
            'byStatus'   => $byStatus,
            'byCountry'  => $byCountry,
            'trend'      => ['unit' => $unit, 'categories' => $trend->pluck('b')->all(), 'data' => $trend->pluck('n')->map(fn ($n) => (int) $n)->all()],
            'total'      => $perEmployee->sum('n'),
        ];
    }

    // --------------------------------------------------------------- reports 7/8

    /** Quick comparison: leaderboards on the headline metrics. */
    public function comparison(): array
    {
        $rows = $this->employeeFunnel()->filter(fn ($r) => $r->user_id > 0)->values();

        // Rates on tiny samples are noise; rank rates only for employees with >= 5 leads.
        $eligible = $rows->filter(fn ($r) => $r->leads >= 5);
        $board = fn ($set, $key) => $set->sortByDesc($key)->take(5)->values();

        return [
            'rows'   => $rows,
            'boards' => [
                ['key' => 'leads', 'label' => __('Most leads'), 'suffix' => '', 'rows' => $board($rows, 'leads')],
                ['key' => 'presentation_rate', 'label' => __('Best presentation rate'), 'suffix' => '%', 'rows' => $board($eligible, 'presentation_rate')],
                ['key' => 'dealt', 'label' => __('Most deals'), 'suffix' => '', 'rows' => $board($rows, 'dealt')],
                ['key' => 'conversion', 'label' => __('Best conversion'), 'suffix' => '%', 'rows' => $board($eligible, 'conversion')],
            ],
            'teamAvg' => [
                'call_rate'         => self::pct($rows->sum('met_call'), $rows->sum('leads')),
                'presentation_rate' => self::pct($rows->sum('met_pres'), $rows->sum('leads')),
                'conversion'   => self::pct($rows->sum('dealt'), $rows->sum('leads')),
                'action_rate'  => self::pct($rows->sum('actioned'), $rows->sum('leads')),
            ],
        ];
    }

    /** The full comparison table, with the previous window for the key columns. */
    public function detailedComparison(): array
    {
        $rows = $this->employeeFunnel();
        $prev = $this->ctx->hasComparison() ? $this->employeeFunnel(true)->keyBy('user_id') : collect();

        $rows = $rows->map(function ($r) use ($prev) {
            $p = $prev[$r->user_id] ?? null;
            $r->prev_leads = $p->leads ?? 0;
            $r->prev_dealt = $p->dealt ?? 0;

            return $r;
        });

        // Each lead once, so the total row is not inflated by co-assigned leads.
        return ['rows' => $rows, 'totals' => $this->teamFunnel()];
    }

    // ------------------------------------------------------------------- extras

    /** When the team works: sales meetings booked and evaluations filled in, by weekday x hour. */
    public function activityHeatmap(): array
    {
        $window = [$this->ctx->from, $this->ctx->to];
        $meetings = DB::table('sales_meetings as x')->whereBetween('x.created_at', $window)->select('x.lead_id', 'x.created_at');
        $evals = DB::table('quality_evaluations as q')->whereRaw(self::EVALUATED)
            ->whereBetween('q.updated_at', $window)->select('q.lead_id', 'q.updated_at as created_at');
        $events = $meetings->unionAll($evals);

        $q = DB::query()->fromSub($events, 'e')->join('leads as l', 'l.id', '=', 'e.lead_id');
        $this->ctx->scopeLeads($q, 'l');

        $rows = $q->select(DB::raw('WEEKDAY(e.created_at) as wd'), DB::raw('HOUR(e.created_at) as hr'), DB::raw('COUNT(*) as n'))
            ->groupBy('wd', 'hr')->get();

        $grid = array_fill(0, 7, array_fill(0, 24, 0));
        foreach ($rows as $r) {
            $grid[(int) $r->wd][(int) $r->hr] = (int) $r->n;
        }

        return $grid;
    }

    /** Paginator helper so views can render links with the current filters. */
    public static function withQuery(LengthAwarePaginator $p, array $query): LengthAwarePaginator
    {
        return $p->appends($query);
    }
}
