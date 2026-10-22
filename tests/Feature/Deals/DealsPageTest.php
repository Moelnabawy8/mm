<?php

namespace Tests\Feature\Deals;

use App\Models\Deal;
use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\Pipeline;
use App\Models\Stage;
use App\Models\User;
use App\Models\UserLead;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DealsPageTest extends TestCase
{
    use DatabaseTransactions;

    protected $company;
    protected $pipeline;
    protected $leadStage;
    protected $dealStage;

    protected function setUp(): void
    {
        parent::setUp();
        app()['cache']->forget('spatie.permission.cache');

        $this->company = $this->makeUser('company', 'dealCompany', 1, [
            'manage deal', 'view deal', 'create deal', 'edit deal', 'delete deal', 'view all leads',
        ]);

        $this->pipeline = Pipeline::create(['name' => 'Main', 'created_by' => $this->company->id]);
        $this->leadStage = LeadStage::create(['name' => 'New', 'pipeline_id' => $this->pipeline->id, 'created_by' => $this->company->id, 'order' => 0]);
        $this->dealStage = Stage::create(['name' => 'Open', 'pipeline_id' => $this->pipeline->id, 'created_by' => $this->company->id, 'order' => 0]);
    }

    protected function makeUser($type, $roleName, $createdBy, array $perms = [])
    {
        static $n = 0;
        $n++;
        $u = new User();
        $u->name = $type . $n;
        $u->email = 'dl' . $n . '_' . uniqid() . '@test.local';
        $u->password = bcrypt('secret');
        $u->type = $type;
        $u->created_by = $createdBy;
        $u->lang = 'en';
        $u->email_verified_at = now();
        $u->save();

        $role = Role::firstOrCreate(['name' => $roleName . $n, 'guard_name' => 'web'], ['created_by' => $createdBy ?: 1]);
        foreach ($perms as $p) {
            if ($perm = Permission::where('name', $p)->first()) {
                $role->givePermissionTo($perm);
            }
        }
        $u->assignRole($role);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        return $u->fresh();
    }

    protected function makeLead($attrs = [])
    {
        $lead = new Lead();
        $lead->name = $attrs['name'] ?? 'Lead ' . uniqid();
        $lead->email = 'l' . uniqid() . '@test.local';
        $lead->phone = $attrs['phone'] ?? null;
        $lead->subject = 'test';
        $lead->user_id = $attrs['user_id'] ?? $this->company->id;
        $lead->pipeline_id = $this->pipeline->id;
        $lead->stage_id = $this->leadStage->id;
        $lead->created_by = $attrs['created_by'] ?? $this->company->id;
        $lead->date = date('Y-m-d');
        $lead->save();
        foreach ((array) ($attrs['assign'] ?? []) as $uid) {
            UserLead::create(['user_id' => $uid, 'lead_id' => $lead->id]);
        }

        return $lead->fresh();
    }

    protected function makeDeal($lead = null, $name = null, $createdBy = null)
    {
        $deal = new Deal();
        $deal->name = $name ?? 'Deal ' . uniqid();
        $deal->lead_id = $lead ? $lead->id : null;
        $deal->pipeline_id = $this->pipeline->id;
        $deal->stage_id = $this->dealStage->id;
        $deal->created_by = $createdBy ?? $this->company->id;
        $deal->save();

        return $deal->fresh();
    }

    // ---- Assigned Staff column ---------------------------------------------------

    public function test_assigned_staff_names_the_assigned_user_not_leads_user_id()
    {
        $owner = $this->makeUser('Sales Employee', 'DealSalesOwner', $this->company->id, ['manage deal']);
        // user_id points at the company, the pivot at the sales employee: the column
        // must follow the pivot, exactly like the lead page and the leads list.
        $lead = $this->makeLead(['name' => 'PivotLead', 'user_id' => $this->company->id, 'assign' => [$owner->id]]);
        $this->makeDeal($lead, 'PivotDeal');

        $r = $this->actingAs($this->company)->get('/deals');
        $r->assertStatus(200);
        $r->assertSee('PivotDeal');
        $r->assertSee('<span dir="auto">' . $owner->name . '</span>', false);
        $r->assertDontSee('<span dir="auto">' . $this->company->name . '</span>', false);
    }

    public function test_assigned_staff_marks_a_lead_with_nobody_assigned()
    {
        $lead = $this->makeLead(['name' => 'NobodyLead']);
        $this->makeDeal($lead, 'NobodyDeal');

        $r = $this->actingAs($this->company)->get('/deals');
        $r->assertStatus(200);
        $r->assertSee('NobodyDeal');
        $r->assertSee('<span class="text-muted">-</span>', false);
    }

    public function test_assigned_staff_lists_every_assigned_user()
    {
        $a = $this->makeUser('Sales Employee', 'DealSalesA', $this->company->id, ['manage deal']);
        $b = $this->makeUser('Sales Employee', 'DealSalesB', $this->company->id, ['manage deal']);
        $lead = $this->makeLead(['name' => 'TwoOwnerLead', 'assign' => [$a->id, $b->id]]);
        $this->makeDeal($lead, 'TwoOwnerDeal');

        $r = $this->actingAs($this->company)->get('/deals');
        $r->assertStatus(200);
        $r->assertSee('<span dir="auto">' . $a->name . '</span>', false);
        $r->assertSee('<span dir="auto">' . $b->name . '</span>', false);
    }

    // ---- Employee filter ---------------------------------------------------------

    public function test_employee_filter_lists_sales_people_only()
    {
        $sales = $this->makeUser('Sales Employee', 'DealSalesFilter', $this->company->id, ['manage deal']);
        $accountant = $this->makeUser('Accountant', 'DealAccountantFilter', $this->company->id, ['manage deal']);

        $r = $this->actingAs($this->company)->get('/deals');
        $r->assertStatus(200);
        $r->assertSee('<option value="' . $sales->id . '">' . $sales->name, false);
        $r->assertDontSee('<option value="' . $accountant->id . '">' . $accountant->name, false);
    }

    public function test_employee_filter_does_not_leak_another_tenant()
    {
        $other = $this->makeUser('company', 'DealOtherCompany', 1, ['manage deal']);
        $foreign = $this->makeUser('Sales Employee', 'DealForeignSales', $other->id, ['manage deal']);

        $r = $this->actingAs($this->company)->get('/deals');
        $r->assertStatus(200);
        $r->assertDontSee('<option value="' . $foreign->id . '">' . $foreign->name, false);
    }

    public function test_employee_filter_actually_filters_on_the_assignment()
    {
        $mine = $this->makeUser('Sales Employee', 'DealMineSales', $this->company->id, ['manage deal']);
        $theirs = $this->makeUser('Sales Employee', 'DealTheirsSales', $this->company->id, ['manage deal']);
        $this->makeDeal($this->makeLead(['assign' => [$mine->id]]), 'MINEDEAL');
        $this->makeDeal($this->makeLead(['assign' => [$theirs->id]]), 'THEIRSDEAL');

        $r = $this->actingAs($this->company)->get('/deals?user_id=' . $mine->id);
        $r->assertStatus(200);
        $r->assertSee('MINEDEAL');
        $r->assertDontSee('THEIRSDEAL');
    }

    // ---- Lead picker search ------------------------------------------------------

    public function test_lead_search_returns_name_and_phone_as_separate_fields()
    {
        $this->makeLead(['name' => 'Ahmed Zakaria', 'phone' => '201002633348']);

        $r = $this->actingAs($this->company)->get('/deals-leads-search?q=Ahmed');
        $r->assertStatus(200);
        $body = $r->json();
        $this->assertNotEmpty($body);
        $this->assertSame('Ahmed Zakaria', $body[0]['name']);
        $this->assertSame('201002633348', $body[0]['phone']);
    }

    public function test_lead_search_matches_a_phone_number()
    {
        $this->makeLead(['name' => 'PhoneOnlyLead', 'phone' => '249960694641']);
        $this->makeLead(['name' => 'OtherLead', 'phone' => '201111111111']);

        $r = $this->actingAs($this->company)->get('/deals-leads-search?q=249960');
        $r->assertStatus(200);
        $names = array_column($r->json(), 'name');
        $this->assertContains('PhoneOnlyLead', $names);
        $this->assertNotContains('OtherLead', $names);
    }

    public function test_lead_search_does_not_leak_another_tenant()
    {
        $other = $this->makeUser('company', 'DealSearchOther', 1, ['manage deal']);
        $this->makeLead(['name' => 'FOREIGNSEARCHLEAD', 'created_by' => $other->id, 'user_id' => $other->id]);

        $r = $this->actingAs($this->company)->get('/deals-leads-search?q=FOREIGNSEARCH');
        $r->assertStatus(200);
        $this->assertSame([], $r->json());
    }

    public function test_lead_search_is_capped()
    {
        for ($i = 0; $i < 30; $i++) {
            $this->makeLead(['name' => 'CappedLead' . $i, 'phone' => '2010000000' . $i]);
        }

        $r = $this->actingAs($this->company)->get('/deals-leads-search?q=CappedLead');
        $r->assertStatus(200);
        $this->assertLessThanOrEqual(25, count($r->json()));
    }

    // ---- the page still works ----------------------------------------------------

    public function test_deals_page_does_not_run_a_query_per_row()
    {
        $sales = $this->makeUser('Sales Employee', 'DealQuerySales', $this->company->id, ['manage deal']);
        for ($i = 0; $i < 12; $i++) {
            $this->makeDeal($this->makeLead(['assign' => [$sales->id]]), 'QueryDeal' . $i);
        }

        \DB::enableQueryLog();
        $r = $this->actingAs($this->company)->get('/deals');
        $count = count(\DB::getQueryLog());
        \DB::disableQueryLog();

        $r->assertStatus(200);
        $this->assertLessThan(45, $count, 'deals page ran ' . $count . ' queries for 12 rows');
    }
}
