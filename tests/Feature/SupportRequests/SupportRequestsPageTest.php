<?php

namespace Tests\Feature\SupportRequests;

use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\Pipeline;
use App\Models\SupportRequest;
use App\Models\User;
use App\Models\UserLead;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The index filter and both forms used to render one <option> per lead in the
 * company. Choices.js wraps each one in its own DOM node, which is what locked
 * the browser up on /deals before that page was converted to the same picker.
 */
class SupportRequestsPageTest extends TestCase
{
    use DatabaseTransactions;

    protected $company;
    protected $pipeline;
    protected $leadStage;

    protected function setUp(): void
    {
        parent::setUp();
        app()['cache']->forget('spatie.permission.cache');

        $this->company = $this->makeUser('company', 'srCompany', 1, [
            'manage support request', 'create support request',
            'edit support request', 'delete support request',
        ]);

        $this->pipeline = Pipeline::create(['name' => 'Main', 'created_by' => $this->company->id]);
        $this->leadStage = LeadStage::create([
            'name' => 'New', 'pipeline_id' => $this->pipeline->id,
            'created_by' => $this->company->id, 'order' => 0,
        ]);
    }

    /**
     * @param bool $exactRole the controller checks for literal role names such as
     *                        'Sales Employee', so those cannot carry the counter
     *                        the other users use to stay unique.
     */
    protected function makeUser($type, $roleName, $createdBy, array $perms = [], $exactRole = false)
    {
        static $n = 0;
        $n++;
        $u = new User();
        $u->name = $type . $n;
        $u->email = 'sr' . $n . '_' . uniqid() . '@test.local';
        $u->password = bcrypt('secret');
        $u->type = $type;
        $u->created_by = $createdBy;
        $u->lang = 'en';
        $u->email_verified_at = now();
        $u->save();

        $role = Role::firstOrCreate(
            ['name' => $exactRole ? $roleName : $roleName . $n, 'guard_name' => 'web'],
            ['created_by' => $createdBy ?: 1]
        );
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
        $lead->name = $attrs['name'] ?? 'SRLead ' . uniqid();
        $lead->email = $attrs['email'] ?? ('sr' . uniqid() . '@test.local');
        $lead->phone = $attrs['phone'] ?? null;
        $lead->subject = 'test';
        $lead->user_id = $this->company->id;
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

    // ---- the picker replaced the full list ----------------------------------------

    public function test_the_create_form_does_not_print_an_option_per_lead()
    {
        for ($i = 0; $i < 20; $i++) {
            $this->makeLead(['name' => 'OptionLead' . $i]);
        }

        $r = $this->actingAs($this->company)->get('/support-requests/create');
        $r->assertStatus(200);

        $r->assertDontSee('OptionLead0');
        $r->assertDontSee('OptionLead19');
        $r->assertSee('deal-lead-picker', false);
    }

    public function test_the_create_form_cost_does_not_grow_with_the_leads_table()
    {
        for ($i = 0; $i < 60; $i++) {
            $this->makeLead(['name' => 'CostLead' . $i]);
        }

        \DB::enableQueryLog();
        $this->actingAs($this->company)->get('/support-requests/create')->assertStatus(200);
        $queries = \DB::getQueryLog();
        \DB::disableQueryLog();

        foreach ($queries as $q) {
            $this->assertStringNotContainsString(
                'select * from `leads` where `created_by`',
                str_replace(["\n", '  '], ' ', $q['query']),
                'the form still reads the whole leads table'
            );
        }
    }

    public function test_the_edit_form_uses_the_picker_too()
    {
        $lead = $this->makeLead(['name' => 'EditPickerLead']);
        $other = $this->makeLead(['name' => 'NotInTheFormLead']);
        $request = SupportRequest::create([
            'lead_id' => $lead->id,
            'requester_id' => $this->company->id,
            'supporter_id' => $this->company->id,
            'status' => 'pending',
            'notes' => 'help',
        ]);

        $r = $this->actingAs($this->company)->get('/support-requests/' . $request->id . '/edit');
        $r->assertStatus(200);
        $r->assertSee('deal-lead-picker', false);
        $r->assertDontSee('NotInTheFormLead');
    }

    public function test_the_index_filter_uses_the_picker_and_keeps_its_id()
    {
        $this->makeLead(['name' => 'FilterOptionLead']);

        $r = $this->actingAs($this->company)->get('/support-requests');
        $r->assertStatus(200);
        $r->assertDontSee('FilterOptionLead');
        // the filter javascript reads $('#filter_lead').val()
        $r->assertSee('id="filter_lead"', false);
    }

    // ---- what the form shows back --------------------------------------------------

    public function test_opening_the_form_from_a_lead_shows_that_lead()
    {
        $lead = $this->makeLead(['name' => 'PreselectedLead']);

        $r = $this->actingAs($this->company)->get('/support-requests/create?lead_id=' . $lead->id);
        $r->assertStatus(200);
        $r->assertSee('value="PreselectedLead"', false);
        $r->assertSee('value="' . $lead->id . '"', false);
    }

    public function test_the_edit_form_shows_the_lead_already_attached()
    {
        $lead = $this->makeLead(['name' => 'AttachedLead']);
        $request = SupportRequest::create([
            'lead_id' => $lead->id,
            'requester_id' => $this->company->id,
            'supporter_id' => $this->company->id,
            'status' => 'pending',
            'notes' => 'help',
        ]);

        $r = $this->actingAs($this->company)->get('/support-requests/' . $request->id . '/edit');
        $r->assertSee('value="AttachedLead"', false);
    }

    public function test_a_lead_from_another_company_is_never_shown_as_selected()
    {
        $other = $this->makeUser('company', 'srOther', 1, ['create support request']);
        $foreign = $this->makeLead(['name' => 'FOREIGNSRLEAD', 'created_by' => $other->id]);

        $r = $this->actingAs($this->company)->get('/support-requests/create?lead_id=' . $foreign->id);
        $r->assertStatus(200);
        $r->assertDontSee('FOREIGNSRLEAD');
    }

    // ---- the search endpoint -------------------------------------------------------

    public function test_the_search_finds_a_lead_by_name()
    {
        $this->makeLead(['name' => 'SearchableLead', 'phone' => '96899111111']);

        $r = $this->actingAs($this->company)->get('/support-requests-leads-search?q=Searchable');
        $r->assertStatus(200);
        $r->assertJsonFragment(['name' => 'SearchableLead']);
    }

    public function test_the_search_finds_a_lead_by_phone()
    {
        $this->makeLead(['name' => 'PhoneSearchLead', 'phone' => '96899222222']);

        $r = $this->actingAs($this->company)->get('/support-requests-leads-search?q=99222222');
        $r->assertJsonFragment(['name' => 'PhoneSearchLead']);
    }

    public function test_the_search_sends_name_and_phone_apart()
    {
        $this->makeLead(['name' => 'SplitLead', 'phone' => '96899333333']);

        $r = $this->actingAs($this->company)->get('/support-requests-leads-search?q=SplitLead');
        $row = $r->json()[0];

        $this->assertSame('SplitLead', $row['name']);
        $this->assertSame('96899333333', $row['phone']);
    }

    public function test_the_search_never_returns_more_than_25_rows()
    {
        for ($i = 0; $i < 40; $i++) {
            $this->makeLead(['name' => 'CappedLead' . $i]);
        }

        $r = $this->actingAs($this->company)->get('/support-requests-leads-search?q=CappedLead');
        $this->assertCount(25, $r->json());
    }

    public function test_the_search_is_scoped_to_the_company()
    {
        $other = $this->makeUser('company', 'srOtherSearch', 1, ['create support request']);
        $this->makeLead(['name' => 'FOREIGNSEARCHLEAD', 'created_by' => $other->id]);
        $this->makeLead(['name' => 'MINESEARCHLEAD']);

        $r = $this->actingAs($this->company)->get('/support-requests-leads-search?q=SEARCHLEAD');
        $names = array_column($r->json(), 'name');

        $this->assertContains('MINESEARCHLEAD', $names);
        $this->assertNotContains('FOREIGNSEARCHLEAD', $names);
    }

    public function test_a_sales_employee_only_searches_their_own_leads()
    {
        $sales = $this->makeUser('employee', 'Sales Employee', $this->company->id, [
            'manage support request', 'create support request',
        ], true);

        $mine = $this->makeLead(['name' => 'ASSIGNEDLEAD', 'assign' => [$sales->id]]);
        $this->makeLead(['name' => 'UNASSIGNEDLEAD']);

        $r = $this->actingAs($sales)->get('/support-requests-leads-search?q=LEAD');
        $names = array_column($r->json(), 'name');

        $this->assertContains('ASSIGNEDLEAD', $names);
        $this->assertNotContains('UNASSIGNEDLEAD', $names);
    }

    public function test_the_search_refuses_a_user_with_no_support_permission()
    {
        $nobody = $this->makeUser('employee', 'srNobody', $this->company->id, []);

        $r = $this->actingAs($nobody)->get('/support-requests-leads-search?q=anything');
        $this->assertSame(401, $r->status());
    }

    // ---- the shared partial still serves its first caller --------------------------

    public function test_the_deals_page_still_uses_its_own_search_endpoint()
    {
        $deals = $this->makeUser('company', 'srDeals', 1, [
            'manage deal', 'view deal', 'create deal', 'edit deal', 'view all leads',
        ]);
        Pipeline::create(['name' => 'DealsMain', 'created_by' => $deals->id]);

        $r = $this->actingAs($deals)->get('/deals');
        $r->assertStatus(200);
        $r->assertSee('deals-leads-search', false);
        $r->assertDontSee('support-requests-leads-search', false);
    }
}
