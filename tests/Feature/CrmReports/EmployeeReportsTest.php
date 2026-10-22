<?php

namespace Tests\Feature\CrmReports;

use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\Pipeline;
use App\Models\User;
use App\Models\UserLead;
use App\Services\CrmReports\EmployeeReport;
use App\Services\CrmReports\ReportContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * CRM Reports > Employee: access, visibility, and that every number means what the
 * page says it means. Run on its own:
 *     php artisan test tests/Feature/CrmReports/EmployeeReportsTest.php
 */
class EmployeeReportsTest extends TestCase
{
    use DatabaseTransactions;

    protected $company;
    protected $alice;
    protected $bob;
    protected $importer;
    protected $pipeline;
    protected $stage;

    const SECTIONS = ['leads', 'actions', 'stages', 'neglected', 'communications', 'meetings', 'comparison', 'detailed'];

    protected function setUp(): void
    {
        parent::setUp();
        app()['cache']->forget('spatie.permission.cache');

        foreach (['show crm reports', 'view all crm reports'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        $this->company = $this->makeUser('company', ['show crm reports', 'view all crm reports', 'manage lead']);
        $this->company->created_by = 1;
        $this->company->save();
        $this->alice = $this->makeUser('Sales Employee', ['show crm reports', 'manage lead'], $this->company->id, 'Alice');
        $this->bob = $this->makeUser('Sales Employee', ['show crm reports', 'manage lead'], $this->company->id, 'Bobby');
        // Like production's integration account: it creates the leads (leads.user_id)
        // but they are worked by whoever they are assigned to in user_leads.
        $this->importer = $this->makeUser('Alaa,Saba,aber', ['manage lead'], $this->company->id, 'Importer');

        $this->pipeline = Pipeline::create(['name' => 'Main', 'created_by' => $this->company->id]);
        $this->stage = LeadStage::create(['name' => 'New', 'pipeline_id' => $this->pipeline->id, 'created_by' => $this->company->id, 'order' => 0]);
    }

    protected function makeUser($type, array $perms, $createdBy = 0, $name = null)
    {
        static $n = 0;
        $n++;
        $u = new User();
        $u->name = $name ?? ($type . $n);
        $u->email = 'crm' . $n . '_' . uniqid() . '@test.local';
        $u->password = bcrypt('secret');
        $u->type = $type;
        $u->created_by = $createdBy;
        $u->lang = 'en';
        $u->email_verified_at = now();
        $u->save();

        $role = Role::firstOrCreate(['name' => 'crmrole' . $n . uniqid(), 'guard_name' => 'web'], ['created_by' => $createdBy ?: 1]);
        foreach ($perms as $p) {
            $perm = Permission::where('name', $p)->first();
            if ($perm) {
                $role->givePermissionTo($perm);
            }
        }
        $u->assignRole($role);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        return $u->fresh();
    }

    /** A lead assigned to $owner (null = unassigned), created by the importer account. */
    protected function lead(?User $owner, array $attrs = [], $createdAt = null)
    {
        $lead = new Lead();
        $lead->name = $attrs['name'] ?? 'Lead ' . uniqid();
        $lead->email = 'l' . uniqid() . '@test.local';
        $lead->subject = 's';
        $lead->user_id = $this->importer->id;
        $lead->pipeline_id = $this->pipeline->id;
        $lead->stage_id = $this->stage->id;
        $lead->created_by = $attrs['created_by'] ?? $this->company->id;
        $lead->date = date('Y-m-d');
        $lead->cost = 0;
        foreach (['country', 'campaign_name', 'sources', 'phone'] as $f) {
            if (array_key_exists($f, $attrs)) {
                $lead->$f = $attrs[$f];
            }
        }
        $lead->save();
        if ($owner) {
            UserLead::create(['user_id' => $owner->id, 'lead_id' => $lead->id]);
        }
        if ($createdAt) {
            DB::table('leads')->where('id', $lead->id)->update(['created_at' => $createdAt]);
        }

        return $lead->fresh();
    }

    protected function meetingType(string $name): int
    {
        return (int) (DB::table('sales_meeting_types')->where('name', $name)->where('created_by', $this->company->id)->value('id')
            ?? DB::table('sales_meeting_types')->insertGetId(['name' => $name, 'created_by' => $this->company->id, 'created_at' => now(), 'updated_at' => now()]));
    }

    /** An action on the lead: a sales meeting of $type booked by $by at $at. */
    protected function act(Lead $lead, User $by, $at = null, string $type = 'Call')
    {
        $at = $at ?? now();
        DB::table('sales_meetings')->insert([
            'title' => $type . ' with customer', 'lead_id' => $lead->id, 'meeting_type_id' => $this->meetingType($type),
            'date' => $at->toDateString(), 'time' => '10:00', 'datetime' => $at, 'notes' => '<p>ok</p>',
            'created_by' => $this->company->id, 'employee_id' => $by->id, 'created_at' => $at, 'updated_at' => $at,
        ]);
    }

    protected function deal(Lead $lead, User $by, $paymentType = null, $price = 1000)
    {
        $id = DB::table('deals')->insertGetId([
            'name' => 'D', 'lead_id' => $lead->id, 'created_by' => $this->company->id, 'added_by' => $by->id,
            'pipeline_id' => $this->pipeline->id, 'stage_id' => 0, 'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($paymentType) {
            DB::table('deal_purchases')->insert([
                'deal_id' => $id, 'price' => $price, 'currency' => 'EGP', 'payment_method' => 'cash', 'payment_type' => $paymentType,
                'paid_amount' => 0, 'status' => 'pending', 'purchase_date' => date('Y-m-d'), 'created_by' => $this->company->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $id;
    }

    protected function evaluation(Lead $lead, User $by, $verdict = 'unknown', $engagement = null)
    {
        DB::table('quality_evaluations')->insert([
            'lead_id' => $lead->id, 'evaluator_id' => $by->id, 'is_potential_lead' => $verdict,
            'engagement_level' => $engagement, 'notes' => 'eval note', 'created_at' => now()->subHour(), 'updated_at' => now(),
        ]);
    }

    protected function meeting(Lead $lead, ?User $employee, $status = null, $date = null, $datetime = null, string $type = 'Presentation')
    {
        DB::table('sales_meetings')->insert([
            'title' => 'M', 'lead_id' => $lead->id, 'meeting_type_id' => $this->meetingType($type),
            'date' => $date ?? date('Y-m-d'), 'time' => '10:00', 'datetime' => $datetime,
            'created_by' => $this->company->id, 'employee_id' => $employee ? $employee->id : null, 'status' => $status,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function report(User $as, array $query = ['period' => '30d']): EmployeeReport
    {
        $this->actingAs($as);

        return new EmployeeReport(ReportContext::fromRequest(Request::create('/x', 'GET', $query), $as));
    }

    protected function rowFor($rows, User $u)
    {
        return collect($rows)->firstWhere('user_id', $u->id);
    }

    // ---------------------------------------------------------- access

    public function test_guest_is_redirected_to_login()
    {
        $this->get('/crm-reports/employee')->assertRedirect('/login');
    }

    public function test_user_without_permission_is_denied()
    {
        $nobody = $this->makeUser('Employee', ['manage lead'], $this->company->id);
        $this->actingAs($nobody)->get('/crm-reports/employee')->assertRedirect();
        $this->actingAs($nobody)->get('/crm-reports/employee/section/leads')->assertStatus(403);
        $this->actingAs($nobody)->get('/crm-reports/employee/export/leads')->assertRedirect();
    }

    public function test_company_opens_page_and_every_section_and_export()
    {
        $l = $this->lead($this->alice);
        $this->act($l, $this->alice);
        $this->evaluation($l, $this->alice, 'yes', 'moderate');
        $this->meeting($l, $this->alice, 'done');
        $this->deal($l, $this->alice, 'installment');

        $this->actingAs($this->company)->get('/crm-reports/employee')->assertOk()->assertSee('Employee Reports');

        foreach (self::SECTIONS as $s) {
            $r = $this->actingAs($this->company)->getJson('/crm-reports/employee/section/' . $s . '?period=30d');
            $r->assertOk();
            $this->assertStringContainsString('crm-section-head', $r->json('html'), "section $s");

            $x = $this->actingAs($this->company)->get('/crm-reports/employee/export/' . $s . '?period=30d');
            $x->assertOk();
            $this->assertStringContainsString('spreadsheetml', $x->headers->get('content-type'), "export $s");
        }
    }

    public function test_unknown_section_is_404()
    {
        $this->actingAs($this->company)->get('/crm-reports/employee/section/nope')->assertNotFound();
    }

    public function test_data_and_financial_pages_open()
    {
        $this->actingAs($this->company)->get('/crm-reports/data')->assertOk();
        $this->actingAs($this->company)->get('/crm-reports/financial')->assertOk();
    }

    // ---------------------------------------------------------- visibility

    public function test_employee_sees_only_own_numbers()
    {
        $this->lead($this->alice);
        $this->lead($this->bob);
        $this->lead($this->bob);

        $rows = $this->report($this->alice)->employeeFunnel();
        $this->assertCount(1, $rows);
        $this->assertSame($this->alice->id, $rows[0]->user_id);

        $html = $this->actingAs($this->alice)->getJson('/crm-reports/employee/section/leads')->json('html');
        $this->assertStringContainsString('Alice', $html);
        $this->assertStringNotContainsString('Bobby', $html);
    }

    public function test_employee_cannot_widen_scope_with_the_filter()
    {
        $this->lead($this->bob);
        $rows = $this->report($this->alice, ['employees' => [$this->bob->id]])->employeeFunnel();
        $this->assertCount(0, $rows);
    }

    public function test_sales_manager_sees_their_group_only()
    {
        $this->alice->sales_group = 'A';
        $this->alice->save();
        $this->bob->sales_group = 'B';
        $this->bob->save();
        $mgr = $this->makeUser('Sales Manager', ['show crm reports'], $this->company->id);
        $mgr->sales_group = 'A';
        $mgr->save();
        Role::firstOrCreate(['name' => 'Sales Manager', 'guard_name' => 'web'], ['created_by' => 1]);
        $mgr->assignRole('Sales Manager');

        $this->lead($this->alice);
        $this->lead($this->bob);

        $ids = $this->report($mgr->fresh())->employeeFunnel()->pluck('user_id')->all();
        $this->assertSame([$this->alice->id], $ids);
    }

    public function test_other_company_is_never_counted()
    {
        $other = $this->makeUser('company', ['show crm reports'], 0);
        $this->lead($this->alice, ['created_by' => $other->id]);
        $this->lead($this->alice);

        $row = $this->rowFor($this->report($this->company)->employeeFunnel(), $this->alice);
        $this->assertSame(1, $row->leads);
    }

    // ---------------------------------------------------------- definitions

    public function test_action_and_meeting_type_definitions()
    {
        $call = $this->lead($this->alice);
        $this->act($call, $this->alice, null, 'Call');

        $pres = $this->lead($this->alice);
        $this->act($pres, $this->alice, null, 'Presentation');

        $both = $this->lead($this->alice);
        $this->act($both, $this->alice, null, 'Call');
        $this->act($both, $this->alice, null, 'Presentation');

        $noteOnly = $this->lead($this->alice);
        DB::table('lead_discussions')->insert(['lead_id' => $noteOnly->id, 'comment' => 'x', 'created_by' => $this->alice->id, 'created_at' => now(), 'updated_at' => now()]);

        $moved = $this->lead($this->alice);
        DB::table('lead_activity_logs')->insert(['user_id' => $this->alice->id, 'lead_id' => $moved->id, 'log_type' => 'Move', 'remark' => '{}', 'created_at' => now(), 'updated_at' => now()]);

        $evaluated = $this->lead($this->alice);
        $this->evaluation($evaluated, $this->alice, 'yes', 'moderate');

        $dealt = $this->lead($this->alice);
        $this->deal($dealt, $this->alice);

        $row = $this->rowFor($this->report($this->company)->employeeFunnel(), $this->alice);
        $this->assertSame(7, $row->leads);
        $this->assertSame(4, $row->actioned, 'only a sales meeting or a deal is an action');
        $this->assertSame(3, $row->met);
        $this->assertSame(2, $row->met_call);
        $this->assertSame(2, $row->met_pres, 'a lead with both types counts under each');
        $this->assertSame(1, $row->qualified);
        $this->assertSame(1, $row->dealt);
        $this->assertEquals(14.3, $row->conversion);
        $this->assertEquals(28.6, $row->presentation_rate);

        $funnel = collect($this->report($this->company)->actionAndConversion()['funnel'])->pluck('n', 'key');
        $this->assertSame([7, 4, 3, 2, 2, 1, 0], [$funnel['leads'], $funnel['actioned'], $funnel['met'], $funnel['met_call'], $funnel['met_pres'], $funnel['dealt'], $funnel['bought']]);
    }

    public function test_cash_and_installment_customers_and_revenue()
    {
        $a = $this->lead($this->alice);
        $this->deal($a, $this->alice, 'one-time', 1500);
        $b = $this->lead($this->alice);
        $this->deal($b, $this->alice, 'installment', 3000);
        $this->deal($b, $this->alice, 'installment', 1000);   // same customer twice

        $row = $this->rowFor($this->report($this->company)->employeeFunnel(), $this->alice);
        $this->assertSame(1, $row->cash);
        $this->assertSame(1, $row->installment);
        $this->assertSame(2, $row->bought);
        $this->assertEquals(5500, $row->revenue);
    }

    public function test_leads_outside_the_window_are_excluded()
    {
        $this->lead($this->alice, [], now()->subDays(60));
        $this->lead($this->alice);

        $this->assertSame(1, $this->rowFor($this->report($this->company)->employeeFunnel(), $this->alice)->leads);
        $this->assertSame(2, $this->rowFor($this->report($this->company, ['period' => '90d'])->employeeFunnel(), $this->alice)->leads);

        $custom = $this->report($this->company, ['period' => 'custom', 'from' => now()->subDays(61)->toDateString(), 'to' => now()->subDays(59)->toDateString()]);
        $this->assertSame(1, $this->rowFor($custom->employeeFunnel(), $this->alice)->leads);
    }

    public function test_previous_period_counts_the_same_length_window_before()
    {
        $this->lead($this->alice, [], now()->subDays(40));
        $this->lead($this->alice);
        $this->lead($this->alice);

        $r = collect($this->report($this->company)->leadsPerEmployee()['rows'])->firstWhere('user_id', $this->alice->id);
        $this->assertSame(2, $r->leads);
        $this->assertSame(1, $r->prev);
        $d = $this->report($this->company)->leadsPerEmployee();
        $this->assertSame(1, $d['prevTotal']);
        $this->assertSame(3, $this->report($this->company, ['period' => '90d'])->leadsPerEmployee()['total']);
    }

    public function test_neglected_counts_never_and_stale_and_skips_buyers()
    {
        $this->lead($this->alice);                                  // never actioned
        $stale = $this->lead($this->alice, [], now()->subDays(20));
        $this->act($stale, $this->alice, now()->subDays(15));   // stopped 15 days ago
        $fresh = $this->lead($this->alice);
        $this->act($fresh, $this->alice);                       // healthy
        $buyer = $this->lead($this->alice);
        $this->deal($buyer, $this->alice, 'one-time');               // bought: not neglected

        $d = $this->report($this->company)->neglected(7);
        $row = $this->rowFor($d['rows'], $this->alice);
        $this->assertSame(3, $row->open_leads);
        $this->assertSame(1, $row->never);
        $this->assertSame(1, $row->stale);
        $this->assertSame(2, $d['list']->total());
    }

    public function test_meetings_credit_recorded_employee_then_lead_owner()
    {
        $l = $this->lead($this->alice);
        $this->meeting($l, $this->bob, 'done');      // Bob held a meeting on Alice's lead
        $this->meeting($l, null, null);              // older meeting: falls back to the owner

        $d = $this->report($this->company)->meetings([]);
        $by = $d['perEmployee']->keyBy(fn ($r) => (int) $r->uid);
        $this->assertSame(1, $by[$this->bob->id]->n);
        $this->assertSame(1, $by[$this->alice->id]->n);

        $done = $this->report($this->company)->meetings(['meeting_status' => 'done']);
        $this->assertSame(1, $done['total']);
    }

    public function test_activity_log_lists_meetings_and_evaluations_with_customer()
    {
        $l = $this->lead($this->alice, ['name' => 'Customer Zed', 'phone' => '0100']);
        $this->act($l, $this->alice, null, 'Presentation');
        $this->evaluation($l, $this->alice, 'no');

        $d = $this->report($this->company)->communicationLog('meeting');
        $this->assertSame(1, $d['log']->total());
        $item = $d['log']->items()[0];
        $this->assertSame('Customer Zed', $item->lead_name);
        $this->assertSame('ok', $item->body);
        $this->assertSame('presentation', $item->outcome);
        $this->assertSame(1, $d['perEmployee'][$this->alice->id]['presentation']);

        $all = $this->report($this->company)->communicationLog(null);
        $this->assertSame(2, $all['log']->total());
        $this->assertSame(1, $all['perEmployee'][$this->alice->id]['evaluation']);
        $this->assertSame(0, $all['perEmployee'][$this->alice->id]['potential']);
    }

    // ---------------------------------------------------------- ownership (production data shape)

    public function test_owner_is_the_assignment_not_the_creator()
    {
        $this->lead($this->alice);

        $rows = $this->report($this->company)->employeeFunnel();
        $this->assertSame(1, $this->rowFor($rows, $this->alice)->leads);
        $this->assertNull($this->rowFor($rows, $this->importer), 'the account that created the lead is not its owner');

        $mine = $this->report($this->alice)->employeeFunnel();
        $this->assertCount(1, $mine);
        $this->assertSame(1, $mine[0]->leads, 'an employee sees the leads assigned to them');
    }

    public function test_co_assigned_lead_counts_for_each_but_once_in_totals()
    {
        $shared = $this->lead($this->alice);
        UserLead::create(['user_id' => $this->bob->id, 'lead_id' => $shared->id]);
        $this->deal($shared, $this->bob);

        $r = $this->report($this->company);
        $rows = $r->employeeFunnel();
        $this->assertSame(1, $this->rowFor($rows, $this->alice)->dealt);
        $this->assertSame(1, $this->rowFor($rows, $this->bob)->dealt);
        $this->assertSame(1, $r->teamFunnel()->leads);
        $this->assertSame(1, $r->kpis()[0]['value']);
        $this->assertSame(1, $r->leadsPerEmployee()['total']);
        $this->assertSame(1, $r->detailedComparison()['totals']->dealt);

        // Narrowed to Alice, Bob's assignment must not leak in.
        $only = $this->report($this->company, ['period' => '30d', 'employees' => [$this->alice->id]])->employeeFunnel();
        $this->assertSame([$this->alice->id], $only->pluck('user_id')->all());
    }

    public function test_unassigned_leads_are_their_own_row_for_managers_only()
    {
        $this->lead(null);
        $this->lead($this->alice);

        $rows = $this->report($this->company)->employeeFunnel();
        $this->assertSame(1, collect($rows)->firstWhere('user_id', 0)->leads);
        $this->assertSame(2, $this->report($this->company)->teamFunnel()->leads);

        $this->assertSame([$this->alice->id], $this->report($this->alice)->employeeFunnel()->pluck('user_id')->all());
        $this->assertSame(1, $this->report($this->alice)->teamFunnel()->leads);
    }

    public function test_neglected_list_names_every_assignee()
    {
        $l = $this->lead($this->alice);
        UserLead::create(['user_id' => $this->bob->id, 'lead_id' => $l->id]);

        $item = $this->report($this->company)->neglected(7)['list']->items()[0];
        $this->assertStringContainsString('Alice', $item->owner);
        $this->assertStringContainsString('Bobby', $item->owner);
    }

    // ---------------------------------------------------------- quality evaluations

    public function test_evaluation_is_logged_but_is_not_an_action()
    {
        $placeholder = $this->lead($this->alice);
        $this->evaluation($placeholder, $this->alice);                          // auto-created, never filled
        $filled = $this->lead($this->alice);
        $this->evaluation($filled, $this->alice, 'yes', 'moderate');

        $row = $this->rowFor($this->report($this->company)->employeeFunnel(), $this->alice);
        $this->assertSame(0, $row->actioned);
        $this->assertSame(1, $row->qualified);

        $log = $this->report($this->company)->communicationLog('evaluation');
        $this->assertSame(1, $log['log']->total());
        $this->assertSame(1, $log['perEmployee'][$this->alice->id]['evaluation']);
        $this->assertSame(1, $log['perEmployee'][$this->alice->id]['potential']);
        $this->assertSame('Potential Lead (Moderate)', EmployeeReport::evaluationLabel('yes', 'moderate'));

        $this->actingAs($this->company)
            ->getJson(route('crm-reports.employee.section', 'communications') . '?period=30d&kind=evaluation')
            ->assertOk()->assertSee('eval note');
    }

    // ---------------------------------------------------------- meeting dates

    public function test_meeting_date_comes_from_datetime_before_the_old_date_column()
    {
        $l = $this->lead($this->alice);
        // The old `date` column says long ago; `datetime` (what the forms write) says today.
        $this->meeting($l, $this->alice, null, '2020-01-01', now()->format('Y-m-d H:i:s'));

        $this->assertSame(1, $this->report($this->company)->meetings([])['total']);
    }

    public function test_meeting_with_a_zero_date_is_dated_by_datetime()
    {
        $l = $this->lead($this->alice);
        $mode = DB::selectOne('SELECT @@SESSION.sql_mode AS m')->m;
        DB::statement("SET SESSION sql_mode = ''");
        try {
            $this->meeting($l, $this->alice, null, '0000-00-00', now()->addDays(2)->format('Y-m-d H:i:s'));
        } finally {
            DB::statement('SET SESSION sql_mode = ?', [$mode]);
        }

        $d = $this->report($this->company, ['period' => 'custom', 'from' => now()->toDateString(), 'to' => now()->addDays(5)->toDateString()])->meetings([]);
        $this->assertSame(1, $d['total']);
        $this->assertSame('upcoming', $d['byStatus'][0]->status);
    }

    public function test_unassigned_row_matches_a_lead_by_lead_count()
    {
        // Unassigned: never touched (20 days old), stale (meeting 15 days ago), fresh, bought.
        $this->lead(null, [], now()->subDays(20));
        $stale = $this->lead(null, ['campaign_name' => 'C1'], now()->subDays(20));
        $this->act($stale, $this->alice, now()->subDays(15));
        $fresh = $this->lead(null);
        $this->act($fresh, $this->alice);
        $buyer = $this->lead(null);
        $this->deal($buyer, $this->alice, 'installment');
        // And one of Alice's, which must not leak into the unassigned row.
        $this->lead($this->alice, [], now()->subDays(10));

        $r = $this->report($this->company);
        $u = collect($r->employeeFunnel())->firstWhere('user_id', 0);
        $this->assertSame(4, $u->leads);
        $this->assertSame(3, $u->actioned);
        $this->assertSame(2, $u->met_call);
        $this->assertSame(1, $u->installment);

        $n = collect($this->report($this->company)->neglected(7)['rows'])->firstWhere('user_id', 0);
        $this->assertSame(3, $n->open_leads);
        $this->assertSame(1, $n->never);
        $this->assertSame(1, $n->stale);
        // 20 days since arrival (never touched) and 15 since the stale one's last call.
        $this->assertSame(2, $n->b_15_30);
        $this->assertSame(0, $n->b_8_14);
        $this->assertSame(1, $n->b_0_2);
        $this->assertEquals(round((20 + 15 + 0) / 3, 1), $n->avg_idle);

        $d = $this->report($this->company)->neglected(7);
        $this->assertSame(3, $d['total'], 'two unassigned + Alice\'s untouched lead');
        $this->assertSame([20, 15, 10], collect($d['list']->items())->pluck('idle_days')->map(fn ($v) => (int) $v)->all());

        $j = $this->report($this->company)->stages()['journey'][0]['stages'];
        $this->assertSame(1, $j['new']);
        $this->assertSame(2, $j['meeting']);
        $this->assertSame(1, $j['deal'] + $j['purchase'] + $j['installment']);
    }

    protected function dealWithStatus(Lead $lead, string $status)
    {
        DB::table('deals')->where('id', $this->deal($lead, $this->alice))->update(['status' => $status]);
    }

    public function test_deal_status_matrix_counts_a_lead_under_each_of_its_deal_statuses()
    {
        $twoDeals = $this->lead($this->alice);
        $this->dealWithStatus($twoDeals, 'Lost');
        $this->dealWithStatus($twoDeals, 'Won');
        $this->dealWithStatus($this->lead($this->alice), 'close');
        $this->dealWithStatus($this->lead($this->bob), 'Close');
        $this->lead($this->alice);
        $this->lead(null);

        $d = $this->report($this->company)->stages();
        $alice = $d['matrix'][$this->alice->id];
        $this->assertEquals(['Lost' => 1, 'Won' => 1, 'close' => 1], $alice['statuses']);
        $this->assertSame(1, $alice['none']);
        $this->assertSame(3, $alice['total']);
        // One status whatever the spelling.
        $this->assertSame(1, $d['matrix'][$this->bob->id]['statuses']['close']);
        $this->assertSame(1, $d['matrix'][0]['none']);
        $this->assertSame('close', $d['statuses'][0], 'the most used status first');

        $html = $this->actingAs($this->company)->getJson('/crm-reports/employee/section/stages?period=30d')->assertOk()->json('html');
        $this->assertStringContainsString('Deal status per employee', $html);
        $this->assertStringContainsString('Won', $html);
    }

    public function test_leads_whose_latest_deal_is_won_or_lost_are_not_neglected()
    {
        $old = now()->subDays(20);
        foreach ([$this->alice, null] as $owner) {
            $won = $this->lead($owner, [], $old);
            $this->dealWithStatus($won, 'Active');
            $this->dealWithStatus($won, 'won');
            $lost = $this->lead($owner, [], $old);
            $this->dealWithStatus($lost, 'Lost');
            $reopened = $this->lead($owner, [], $old);       // lost, then a new deal is being worked
            $this->dealWithStatus($reopened, 'Lost');
            $this->dealWithStatus($reopened, 'Negotiation');
            $this->lead($owner, [], $old);                   // never actioned
        }
        DB::table('deals')->update(['created_at' => $old]);

        $d = $this->report($this->company)->neglected(7);
        foreach ([$this->alice->id, 0] as $uid) {
            $row = $d['rows']->firstWhere('user_id', $uid);
            $this->assertSame(2, $row->open_leads, "user $uid: the reopened lead and the untouched one");
            $this->assertSame(1, $row->never);
            $this->assertSame(1, $row->stale);
        }
        $this->assertSame(4, $d['total']);
        $this->assertCount(4, $d['list']->items());
        $this->assertEqualsCanonicalizing(['Lost, Negotiation', 'Lost, Negotiation', null, null],
            collect($d['list']->items())->pluck('deal_status')->all());
    }

    public function test_leads_filter_by_deal_status_and_no_deal()
    {
        $won = $this->lead($this->alice);
        $this->dealWithStatus($won, 'Lost');
        $this->dealWithStatus($won, 'Won');
        $lost = $this->lead($this->alice);
        $this->dealWithStatus($lost, 'Lost');
        $none = $this->lead($this->alice);

        $ids = function ($status) {
            $q = Lead::select('leads.*')->where('leads.created_by', $this->company->id);
            \App\Services\LeadScope::applyFilters($q, Request::create('/x', 'GET', ['deal_status' => $status]));

            return $q->pluck('id')->sort()->values()->all();
        };
        $this->assertSame([$won->id], $ids('Won'));
        $this->assertSame([$won->id, $lost->id], $ids('Lost'), 'any deal with that status');
        $this->assertSame([$none->id], $ids(\App\Services\LeadScope::NO_DEAL));
        $this->assertSame(['Lost', 'Won'], \App\Services\LeadScope::dealStatuses($this->company->id));
    }
}

