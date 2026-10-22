<?php

namespace Tests\Feature\Leads;

use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\Pipeline;
use App\Models\User;
use App\Models\UserLead;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LeadsPageTest extends TestCase
{
    use DatabaseTransactions;

    protected $company;
    protected $pipeline;
    protected $stageA;
    protected $stageB;

    protected function setUp(): void
    {
        parent::setUp();
        app()['cache']->forget('spatie.permission.cache');

        $this->company = $this->makeUser('company', 'company', 0, [
            'manage lead', 'view all leads', 'view lead', 'create lead', 'edit lead', 'delete lead', 'move lead',
        ]);
        $this->company->created_by = 1;
        $this->company->save();

        $this->pipeline = Pipeline::create(['name' => 'Main', 'created_by' => $this->company->id]);
        $this->stageA = LeadStage::create(['name' => 'New', 'pipeline_id' => $this->pipeline->id, 'created_by' => $this->company->id, 'order' => 0]);
        $this->stageB = LeadStage::create(['name' => 'Won', 'pipeline_id' => $this->pipeline->id, 'created_by' => $this->company->id, 'order' => 1]);
    }

    protected function makeUser($type, $roleName, $createdBy, array $perms = [])
    {
        static $n = 0;
        $n++;
        $u = new User();
        $u->name = $type . $n;
        $u->email = 'u' . $n . '_' . uniqid() . '@test.local';
        $u->password = bcrypt('secret');
        $u->type = $type;
        $u->created_by = $createdBy;
        $u->lang = 'en';
        $u->email_verified_at = now();
        $u->save();

        if ($roleName) {
            $role = Role::firstOrCreate(
                ['name' => $roleName . $n, 'guard_name' => 'web'],
                ['created_by' => $createdBy ?: 1]
            );
            foreach ($perms as $p) {
                $perm = Permission::where('name', $p)->first();
                if ($perm) {
                    $role->givePermissionTo($perm);
                }
            }
            $u->assignRole($role);
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        }
        return $u->fresh();
    }

    protected function makeLead($attrs = [])
    {
        $lead = new Lead();
        $lead->name = $attrs['name'] ?? 'Lead ' . uniqid();
        $lead->email = $attrs['email'] ?? ('l' . uniqid() . '@test.local');
        $lead->phone = $attrs['phone'] ?? null;
        $lead->subject = $attrs['subject'] ?? 'test subject';
        $lead->user_id = $attrs['user_id'] ?? $this->company->id;
        $lead->pipeline_id = $attrs['pipeline_id'] ?? $this->pipeline->id;
        $lead->stage_id = array_key_exists('stage_id', $attrs) ? $attrs['stage_id'] : $this->stageA->id;
        $lead->created_by = $attrs['created_by'] ?? $this->company->id;
        $lead->date = $attrs['date'] ?? date('Y-m-d');
        foreach (['gender', 'country', 'city', 'campaign_name', 'costing_type', 'cost', 'notes', 'sources', 'products', 'labels', 'interests', 'order'] as $f) {
            if (array_key_exists($f, $attrs)) {
                $lead->$f = $attrs[$f];
            }
        }
        $lead->save();
        if (!empty($attrs['assign'])) {
            foreach ((array) $attrs['assign'] as $uid) {
                UserLead::create(['user_id' => $uid, 'lead_id' => $lead->id]);
            }
        }
        return $lead->fresh();
    }

    // ---------- A. access control ----------

    public function test_guest_is_redirected_to_login()
    {
        $this->get('/leads')->assertRedirect('/login');
    }

    public function test_company_owner_can_open_leads_page()
    {
        $this->makeLead(['name' => 'AlphaLead']);
        $r = $this->actingAs($this->company)->get('/leads');
        $r->assertStatus(200);
        $r->assertSee('AlphaLead');
    }

    public function test_company_owner_can_open_list_view()
    {
        $this->makeLead(['name' => 'BetaLead']);
        $r = $this->actingAs($this->company)->get('/leads/list');
        $r->assertStatus(200);
        $r->assertSee('BetaLead');
    }

    public function test_user_without_manage_lead_is_denied()
    {
        $u = $this->makeUser('Employee', 'PlainEmp', $this->company->id, []);
        $r = $this->actingAs($u)->get('/leads');
        $r->assertRedirect();
        $this->assertEquals('Permission Denied.', session('error'));
    }

    public function test_sales_employee_only_sees_own_assigned_leads()
    {
        $emp = $this->makeUser('Employee', 'SalesEmployee', $this->company->id, ['manage lead']);
        $this->makeLead(['name' => 'MineLead', 'assign' => [$emp->id]]);
        $this->makeLead(['name' => 'NotMineLead']);

        $r = $this->actingAs($emp)->get('/leads/list');
        $r->assertStatus(200);
        $r->assertSee('MineLead');
        $r->assertDontSee('NotMineLead');
    }

    public function test_cso_type_without_manage_lead_can_open_kanban_but_not_list()
    {
        $cso = $this->makeUser('CSO', 'CSORole', $this->company->id, []);
        $this->actingAs($cso)->get('/leads')->assertStatus(200);
        $r = $this->actingAs($cso)->get('/leads/list');
        $r->assertRedirect();
        $this->assertEquals('Permission Denied.', session('error'), 'CSO reaches /leads but is denied /leads/list');
    }

    // ---------- B. crash paths ----------

    public function test_leads_page_with_no_pipeline_configured()
    {
        $solo = $this->makeUser('company', 'companyNoPipe', 1, ['manage lead']);
        // must explain itself the way the list view does, not blow up
        $r = $this->actingAs($solo)->get('/leads');
        $this->assertNotEquals(500, $r->getStatusCode());
        $r->assertRedirect();
        $this->assertEquals('Please create a pipeline first.', session('error'));
    }

    public function test_list_view_with_no_pipeline_configured()
    {
        $solo = $this->makeUser('company', 'companyNoPipe2', 1, ['manage lead']);
        $this->actingAs($solo)->get('/leads/list')->assertRedirect();
    }

    public function test_kanban_ajax_with_zero_limit()
    {
        $this->makeLead();
        $r = $this->actingAs($this->company)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get('/leads?limit=0');
        $r->assertStatus(200);
    }

    public function test_kanban_ajax_with_huge_limit()
    {
        $this->makeLead();
        $r = $this->actingAs($this->company)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get('/leads?limit=999999');
        $r->assertStatus(200);
    }

    // ---------- C. tenant isolation ----------

    public function test_show_does_not_leak_other_tenants_lead()
    {
        $otherCompany = $this->makeUser('company', 'companyOther1', 1, ['manage lead']);
        $p2 = Pipeline::create(['name' => 'P2', 'created_by' => $otherCompany->id]);
        $s2 = LeadStage::create(['name' => 'S2', 'pipeline_id' => $p2->id, 'created_by' => $otherCompany->id, 'order' => 0]);
        $foreign = $this->makeLead([
            'name' => 'SECRETFOREIGNLEAD', 'created_by' => $otherCompany->id,
            'pipeline_id' => $p2->id, 'stage_id' => $s2->id,
            'user_id' => $otherCompany->id, 'email' => 'secret@foreign.test',
        ]);

        $emp = $this->makeUser('Employee', 'SalesEmployeeB', $this->company->id, ['manage lead']);
        $r = $this->actingAs($emp)->get('/leads/' . $foreign->id);
        $r->assertDontSee('SECRETFOREIGNLEAD');
    }

    public function test_json_detail_does_not_leak_other_tenants_lead()
    {
        $otherCompany = $this->makeUser('company', 'companyOther2', 1, ['manage lead']);
        $foreign = $this->makeLead([
            'name' => 'SECRETJSONLEAD', 'created_by' => $otherCompany->id,
            'user_id' => $otherCompany->id, 'phone' => '0100000000', 'email' => 'json@foreign.test',
        ]);
        $emp = $this->makeUser('Employee', 'SalesEmployeeC', $this->company->id, ['manage lead']);
        $r = $this->actingAs($emp)->get('/leads/' . $foreign->id . '/json-detail');
        $this->assertStringNotContainsString('SECRETJSONLEAD', $r->getContent());
    }

    public function test_bulk_destroy_cannot_delete_other_tenants_lead()
    {
        $otherCompany = $this->makeUser('company', 'companyOther3', 1, ['manage lead']);
        $foreign = $this->makeLead(['name' => 'ForeignDel', 'created_by' => $otherCompany->id, 'user_id' => $otherCompany->id]);
        $mine = $this->makeLead(['name' => 'MineDel']);

        $admin = $this->makeUser('Employee', 'DelRole', $this->company->id, ['manage lead', 'delete lead']);
        $r = $this->actingAs($admin)->postJson('/leads/bulk-destroy', ['ids' => [$foreign->id, $mine->id]]);
        $r->assertStatus(200);
        $this->assertNotNull(Lead::find($foreign->id), 'foreign lead must survive');
        $this->assertNull(Lead::find($mine->id), 'own lead should be deleted');
    }

    public function test_bulk_reassign_rejects_user_from_another_tenant()
    {
        $otherCompany = $this->makeUser('company', 'companyOther4', 1, []);
        $foreignUser = $this->makeUser('Employee', 'ForeignEmp', $otherCompany->id, []);
        $lead = $this->makeLead(['name' => 'ReassignMe']);

        $admin = $this->makeUser('Employee', 'EditRole', $this->company->id, ['manage lead', 'edit lead']);
        $r = $this->actingAs($admin)->postJson('/leads/bulk-reassign', [
            'lead_ids' => [$lead->id], 'user_id' => $foreignUser->id,
        ]);
        $this->assertNotEquals(500, $r->getStatusCode(), 'must refuse cleanly, not error');
        $r->assertJson(['success' => false]);

        $this->assertNotEquals(
            $foreignUser->id,
            Lead::find($lead->id)->user_id,
            'lead must not be assigned to a user of a different tenant'
        );
    }

    // ---------- D. filters ----------

    public function test_search_by_name_and_phone()
    {
        $this->makeLead(['name' => 'MohamedAli', 'phone' => '01234567890']);
        $this->makeLead(['name' => 'SaraHassan', 'phone' => '01999999999']);

        $r = $this->actingAs($this->company)->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get('/leads?name=Mohamed&phone=Mohamed');
        $r->assertStatus(200)->assertSee('MohamedAli')->assertDontSee('SaraHassan');

        $r2 = $this->actingAs($this->company)->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get('/leads?name=01999&phone=01999');
        $r2->assertStatus(200)->assertSee('SaraHassan')->assertDontSee('MohamedAli');
    }

    public function test_unassigned_filter()
    {
        $emp = $this->makeUser('Employee', 'SalesEmployeeD', $this->company->id, ['manage lead']);
        $this->makeLead(['name' => 'AssignedOne', 'assign' => [$emp->id]]);
        $this->makeLead(['name' => 'UnassignedOne']);

        $r = $this->actingAs($this->company)->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get('/leads?unassigned=1');
        $r->assertStatus(200)->assertSee('UnassignedOne')->assertDontSee('AssignedOne');
    }

    public function test_date_range_filter()
    {
        $this->makeLead(['name' => 'OldLead', 'date' => '2020-01-01']);
        $this->makeLead(['name' => 'NewLead', 'date' => date('Y-m-d')]);

        $r = $this->actingAs($this->company)->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get('/leads?date=2019-12-01 to 2020-02-01');
        $r->assertStatus(200)->assertSee('OldLead')->assertDontSee('NewLead');
    }

    public function test_no_activity_filter_runs()
    {
        $this->makeLead(['name' => 'QuietLead']);
        $r = $this->actingAs($this->company)->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get('/leads?no_activity=1');
        $r->assertStatus(200)->assertSee('QuietLead');
    }

    // ---------- E. export ----------

    public function test_export_requires_permission()
    {
        $u = $this->makeUser('Employee', 'NoPermExport', $this->company->id, []);
        $r = $this->actingAs($u)->get('/leads/export?type=csv');
        $this->assertNotEquals(200, $r->getStatusCode(), 'export must not be open to users without lead permissions');
    }

    public function test_export_honours_the_same_filters_as_the_page()
    {
        $this->makeLead(['name' => 'MaleLead', 'gender' => 'Male']);
        $this->makeLead(['name' => 'FemaleLead', 'gender' => 'Female']);

        $r = $this->actingAs($this->company)->get('/leads/export?type=csv&gender=Male');
        $r->assertStatus(200);
        $response = $r->baseResponse;
        $csv = method_exists($response, 'getFile')
            ? file_get_contents($response->getFile()->getPathname())
            : $r->getContent();
        $this->assertStringContainsString('MaleLead', $csv);
        $this->assertStringNotContainsString('FemaleLead', $csv, 'gender filter must apply to the export too');
    }

    // ---------- F. kanban ordering ----------

    public function test_drag_and_drop_reorders_every_card_in_the_column()
    {
        $l1 = $this->makeLead(['name' => 'Card1', 'stage_id' => $this->stageA->id, 'order' => 0]);
        $l2 = $this->makeLead(['name' => 'Card2', 'stage_id' => $this->stageA->id, 'order' => 1]);
        $l3 = $this->makeLead(['name' => 'Card3', 'stage_id' => $this->stageA->id, 'order' => 2]);

        // the company owner sees every lead, so visibility is not what is under test here
        $mover = $this->company;

        // user drags Card3 to the top: new visual order = [Card3, Card1, Card2]
        $this->actingAs($mover)->postJson('/leads/order', [
            'lead_id' => $l3->id,
            'stage_id' => $this->stageA->id,
            'order' => [$l3->id, $l1->id, $l2->id],
        ])->assertStatus(200);

        $this->assertEquals(0, Lead::find($l3->id)->order, 'dragged card should be first');
        $this->assertEquals(1, Lead::find($l1->id)->order, 'second card should be re-ordered too');
        $this->assertEquals(2, Lead::find($l2->id)->order, 'third card should be re-ordered too');
    }

    public function test_move_lead_across_stages_is_scoped_to_tenant()
    {
        $otherCompany = $this->makeUser('company', 'companyOther5', 1, []);
        $foreign = $this->makeLead(['name' => 'ForeignCard', 'created_by' => $otherCompany->id, 'user_id' => $otherCompany->id]);
        $mover = $this->makeUser('Employee', 'MoveRole2', $this->company->id, ['manage lead', 'move lead']);

        $resp = $this->actingAs($mover)->postJson('/leads/order', [
            'lead_id' => $foreign->id,
            'stage_id' => $this->stageB->id,
            'order' => [$foreign->id],
        ]);
        $resp->assertStatus(403);

        $this->assertNotEquals(
            $this->stageB->id,
            Lead::find($foreign->id)->stage_id,
            'a user must not be able to move a lead belonging to another tenant'
        );
    }

    // ---------- G. create / update ----------

    public function test_duplicate_phone_is_rejected_even_when_formatted_differently()
    {
        $creator = $this->makeUser('Employee', 'CreateRole', $this->company->id, ['manage lead', 'create lead']);
        $this->makeLead(['name' => 'Existing', 'phone' => '01012345678']);

        $this->actingAs($creator)->post('/leads', [
            'name' => 'DuplicatePhone',
            'email' => 'dupphone' . uniqid() . '@test.local',
            'phone' => '010-1234-5678',
            'cost' => 0,
            'pipeline_id' => $this->pipeline->id,
        ]);

        $this->assertNull(
            Lead::where('created_by', $this->company->id)->where('name', 'DuplicatePhone')->first(),
            'the same phone in a different format must not create a second lead'
        );
    }

    public function test_create_lead_with_the_optional_cost_field_left_empty()
    {
        $creator = $this->makeUser('Employee', 'CreateRole2', $this->company->id, ['manage lead', 'create lead']);

        // exactly what the create form submits when Cost is left blank
        $r = $this->actingAs($creator)->post('/leads', [
            'name' => 'EmptyCostLead',
            'email' => 'emptycost' . uniqid() . '@test.local',
            'phone' => '01555555555',
            'cost' => '',
            'pipeline_id' => $this->pipeline->id,
        ]);

        $this->assertNotEquals(500, $r->getStatusCode(), 'submitting the form with an empty Cost must not error');
        $this->assertNotNull(
            Lead::where('created_by', $this->company->id)->where('name', 'EmptyCostLead')->first(),
            'Cost is optional on the form, so the lead must still be created'
        );
    }

    public function test_note_store_on_missing_lead_does_not_crash()
    {
        $u = $this->makeUser('Employee', 'NoteRole', $this->company->id, ['manage lead', 'edit lead']);
        $r = $this->actingAs($u)->postJson('/leads/99999999/note', ['notes' => 'x']);
        $this->assertNotEquals(500, $r->getStatusCode());
    }

    public function test_show_lead_with_no_stages_does_not_crash()
    {
        // a pipeline whose stages were all deleted, with a lead still pointing at it
        $p = Pipeline::create(['name' => 'Empty', 'created_by' => $this->company->id]);
        $lead = $this->makeLead(['name' => 'NoStageLead', 'pipeline_id' => $p->id, 'stage_id' => $this->stageA->id]);
        $r = $this->actingAs($this->company)->get('/leads/' . $lead->id);
        $this->assertNotEquals(500, $r->getStatusCode());
    }

    public function test_privileged_type_without_any_permission_cannot_export_everything()
    {
        $this->makeLead(['name' => 'ExportSecretLead']);
        $cso = $this->makeUser('CSO', 'CSONoPerm', $this->company->id, []);

        $r = $this->actingAs($cso)->get('/leads/export?type=csv');
        $response = $r->baseResponse;
        $csv = method_exists($response, 'getFile')
            ? file_get_contents($response->getFile()->getPathname())
            : $r->getContent();

        $this->assertStringNotContainsString('ExportSecretLead', $csv,
            'a user with no lead permission at all must not be able to export the whole lead database');
    }

    public function test_show_requires_a_lead_permission()
    {
        $lead = $this->makeLead(['name' => 'ShowGatedLead']);
        $u = $this->makeUser('Employee', 'NoPermShow', $this->company->id, []);
        $r = $this->actingAs($u)->get('/leads/' . $lead->id);
        $r->assertDontSee('ShowGatedLead');
    }

    public function test_country_filter_lists_the_create_form_countries_not_stored_junk()
    {
        // a lead whose country column holds a phone number, as seen on production
        $this->makeLead(['name' => 'JunkCountryLead', 'country' => '97477889531']);
        $this->makeLead(['name' => 'RealCountryLead', 'country' => 'Qatar']);

        $r = $this->actingAs($this->company)->get('/leads');
        $r->assertStatus(200);

        // the junk must not be offered as a filter option (it may still appear on the card)
        $r->assertDontSee('<option value="97477889531"', false);
        $r->assertSee('<option value="Qatar"', false);
        // a country nobody has used yet is still offered, because the list is fixed
        $r->assertSee('<option value="Japan"', false);
    }

    public function test_users_filter_lists_sales_people_only()
    {
        $sales = $this->makeUser('Sales Employee', 'SalesRoleForFilter', $this->company->id, ['manage lead']);
        $accountant = $this->makeUser('Accountant', 'AccountantRole', $this->company->id, ['manage lead']);

        $r = $this->actingAs($this->company)->get('/leads');
        $r->assertStatus(200);
        $r->assertSee('<option value="' . $sales->id . '">' . $sales->name, false);
        $r->assertDontSee('<option value="' . $accountant->id . '">' . $accountant->name, false);
    }

    // ---------- H. performance ----------

    public function test_export_does_not_run_a_query_per_row()
    {
        for ($i = 0; $i < 20; $i++) {
            $this->makeLead(['name' => 'PerfLead' . $i]);
        }

        \DB::enableQueryLog();
        \DB::flushQueryLog();
        $this->actingAs($this->company)->get('/leads/export?type=csv');
        $count = count(\DB::getQueryLog());
        \DB::disableQueryLog();

        fwrite(STDERR, "\n[export] queries for 20 leads: " . $count . "\n");
        $this->assertLessThan(40, $count, 'export runs a query per row (N+1); it will time out on a real dataset');
    }

    public function test_leads_page_query_count_is_bounded()
    {
        for ($i = 0; $i < 25; $i++) {
            $this->makeLead(['name' => 'PageLead' . $i]);
        }

        \DB::enableQueryLog();
        \DB::flushQueryLog();
        $this->actingAs($this->company)->get('/leads');
        $count = count(\DB::getQueryLog());
        \DB::disableQueryLog();

        fwrite(STDERR, "\n[page] queries for 25 leads on /leads: " . $count . "\n");
        $this->assertLessThan(60, $count, '/leads runs a query per card');
    }

    public function test_campaign_filter_list_does_not_leak_other_tenants_campaigns()
    {
        $otherCompany = $this->makeUser('company', 'companyOther6', 1, []);
        $this->makeLead(['name' => 'X1', 'created_by' => $otherCompany->id, 'user_id' => $otherCompany->id, 'campaign_name' => 'FOREIGNCAMPAIGN2026']);
        $this->makeLead(['name' => 'Y1', 'campaign_name' => 'MYCAMPAIGN']);

        $r = $this->actingAs($this->company)->get('/leads');
        $r->assertStatus(200);
        $r->assertSee('MYCAMPAIGN');
        $r->assertDontSee('FOREIGNCAMPAIGN2026');
    }

    public function test_list_view_names_the_assigned_user_and_marks_unassigned_leads()
    {
        $owner = $this->makeUser('Sales Employee', 'SalesOwnerForList', $this->company->id, ['manage lead']);
        $this->makeLead(['name' => 'AssignedListLead', 'assign' => [$owner->id]]);
        $this->makeLead(['name' => 'UnassignedListLead']);

        $r = $this->actingAs($this->company)->get('/leads/list');
        $r->assertStatus(200);

        // The name has to be readable in the cell, not hidden in a tooltip the
        // theme never initialises.
        $r->assertSee('<span>' . $owner->name . '</span>', false);
        $r->assertDontSee('data-original-title="' . $owner->name . '"', false);
        $r->assertSee('<span class="text-muted">-</span>', false);
    }

    public function test_list_and_detail_name_the_same_assigned_user()
    {
        $owner = $this->makeUser('Sales Employee', 'SalesOwnerForBoth', $this->company->id, ['manage lead']);
        // user_id deliberately points at someone else: only the user_leads pivot
        // decides who is shown, and both screens must read it the same way.
        $lead = $this->makeLead([
            'name' => 'BothScreensLead',
            'user_id' => $this->company->id,
            'assign' => [$owner->id],
        ]);

        $list = $this->actingAs($this->company)->get('/leads/list');
        $list->assertStatus(200);
        $list->assertSee('<span>' . $owner->name . '</span>', false);

        $show = $this->actingAs($this->company)->get('/leads/' . $lead->id);
        $show->assertStatus(200);
        $show->assertSee($owner->name, false);
        $show->assertDontSee('>' . $this->company->name . '<', false);
    }

    // ---- labels on a card, and the filter that used to vanish with them ----------

    protected function makeLabel($name, $createdBy = null)
    {
        return \App\Models\Label::create([
            'name' => $name,
            'color' => 'primary',
            'pipeline_id' => $this->pipeline->id,
            'created_by' => $createdBy ?? $this->company->id,
        ]);
    }

    public function test_the_last_label_can_be_removed_from_a_lead()
    {
        $label = $this->makeLabel('RemovableLabel');
        $lead = $this->makeLead(['name' => 'LabelLead']);
        $lead->labels = (string) $label->id;
        $lead->save();

        // unticking every box posts no labels[] at all
        $this->actingAs($this->company)->post('/leads/' . $lead->id . '/labels', []);

        $this->assertSame('', (string) $lead->fresh()->labels, 'the last label could not be removed');
    }

    public function test_labels_can_be_set_and_changed()
    {
        $a = $this->makeLabel('LabelA');
        $b = $this->makeLabel('LabelB');
        $lead = $this->makeLead(['name' => 'ChangeLabelLead']);

        $this->actingAs($this->company)->post('/leads/' . $lead->id . '/labels', ['labels' => [$a->id, $b->id]]);
        $this->assertSame($a->id . ',' . $b->id, $lead->fresh()->labels);

        $this->actingAs($this->company)->post('/leads/' . $lead->id . '/labels', ['labels' => [$b->id]]);
        $this->assertSame((string) $b->id, $lead->fresh()->labels);
    }

    public function test_a_label_from_another_tenant_is_rejected()
    {
        $other = $this->makeUser('company', 'LabelOtherCompany', 1, ['edit lead']);
        $foreign = $this->makeLabel('FOREIGNLABEL', $other->id);
        $mine = $this->makeLabel('MineLabel');

        $lead = $this->makeLead(['name' => 'ForeignLabelLead']);
        $lead->labels = (string) $mine->id;
        $lead->save();

        $this->actingAs($this->company)->post('/leads/' . $lead->id . '/labels', ['labels' => [$foreign->id]]);

        $this->assertSame((string) $mine->id, $lead->fresh()->labels, 'another tenant label was attached');
    }

    public function test_the_page_does_not_call_the_missing_select2_plugin()
    {
        $r = $this->actingAs($this->company)->get('/leads');
        $r->assertStatus(200);

        // The theme ships Choices.js; $(...).select2() does not exist and threw,
        // which stopped the saved filter from ever being re-applied after a reload.
        $body = $r->getContent();
        $body = preg_replace('~//[^\n]*~', '', $body);
        $this->assertStringNotContainsString('.select2({', $body);
    }

    public function test_the_filter_offers_deal_status_instead_of_stage()
    {
        $r = $this->actingAs($this->company)->get('/leads');
        $r->assertStatus(200);
        $r->assertSee('<select class="form-control" id="filter_deal_status" name="deal_status">', false);
        $r->assertSee('<option value="none">No deal</option>', false);
        $r->assertDontSee('id="filter_stage"', false);
    }

    public function test_the_filter_restore_always_reaches_the_apply_step()
    {
        $r = $this->actingAs($this->company)->get('/leads');
        $r->assertStatus(200);
        $body = $r->getContent();

        // the callback that re-applies the filter must sit outside the try block
        // that touches the dropdown, and a failed request must call it too
        $this->assertStringContainsString("error: function() {", $body);
        $this->assertSame(2, substr_count($body, "if (typeof callback === 'function') callback();"));
    }

    // ---- restoring a saved filter must not destroy it -----------------------------

    public function test_restoring_a_filter_does_not_read_the_inputs_back()
    {
        $r = $this->actingAs($this->company)->get('/leads');
        $r->assertStatus(200);
        $body = $r->getContent();

        // custom.js wraps every .select2 in a Choices widget on ready, so reading
        // the inputs back during the restore returned blanks. The restore must send
        // the saved object itself.
        // the signature grew a page argument later; what matters is that the
        // saved object itself is what gets applied
        $this->assertStringContainsString('runLeadsFilter(_sf', $body);
        $this->assertStringContainsString('function runLeadsFilter(data', $body);
        $this->assertStringContainsString('function collectLeadsFilter() {', $body);
    }

    public function test_the_restore_never_clicks_apply_filter()
    {
        $r = $this->actingAs($this->company)->get('/leads');
        $body = $r->getContent();

        // clicking it re-collected the (empty) inputs and saved them over the
        // filter we were trying to restore
        $this->assertStringNotContainsString("\$('#apply-filter').trigger('click')", $body);
    }

    public function test_a_failed_restore_keeps_the_saved_filter()
    {
        $r = $this->actingAs($this->company)->get('/leads');
        $body = $r->getContent();

        // the only place allowed to drop the saved filter is the Clear button
        $this->assertSame(1, substr_count($body, 'localStorage.removeItem(LEADS_FILTER_KEY)'));
        $this->assertStringContainsString("console.log('could not fill the filter form:', e);", $body);
    }

    public function test_the_filter_is_applied_only_from_the_saved_object()
    {
        $r = $this->actingAs($this->company)->get('/leads');
        $body = $r->getContent();

        // exactly one writer of the saved filter: the Apply button
        $this->assertSame(1, substr_count($body, 'localStorage.setItem(LEADS_FILTER_KEY'));
    }

    public function test_the_inline_javascript_on_the_leads_page_parses()
    {
        $node = trim((string) @shell_exec('node --version 2>&1'));
        if (strpos($node, 'v') !== 0) {
            $this->markTestSkipped('node is not available');
        }

        $r = $this->actingAs($this->company)->get('/leads');
        $r->assertStatus(200);

        preg_match_all('~<script(?![^>]*\bsrc=)[^>]*>(.*?)</script>~s', $r->getContent(), $m);
        $this->assertNotEmpty($m[1], 'no inline script blocks were found');

        $dir = sys_get_temp_dir() . '/leads-js-check';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $failures = [];
        foreach ($m[1] as $i => $js) {
            if (trim($js) === '') {
                continue;
            }
            $file = $dir . '/block' . $i . '.js';
            file_put_contents($file, $js);
            $out = [];
            $code = 0;
            exec('node --check ' . escapeshellarg($file) . ' 2>&1', $out, $code);
            unlink($file);
            if ($code !== 0) {
                $failures[] = 'block ' . $i . ': ' . implode(' ', $out);
            }
        }

        $this->assertSame([], $failures, "inline script blocks did not parse:\n" . implode("\n", $failures));
    }

    // ---- the page you were on ------------------------------------------------------

    public function test_the_board_remembers_which_page_you_were_on()
    {
        $r = $this->actingAs($this->company)->get('/leads');
        $r->assertStatus(200);
        $body = $r->getContent();

        // the board paginates over AJAX, so the page number never reaches the URL
        $this->assertStringContainsString("var LEADS_PAGE_KEY = 'crm_leads_page';", $body);
        $this->assertStringContainsString('rememberLeadsPage(leadsPageFromUrl(url));', $body);
        $this->assertStringContainsString('runLeadsFilter(_sf, savedLeadsPage());', $body);
    }

    public function test_applying_a_new_filter_goes_back_to_page_one()
    {
        $body = $this->actingAs($this->company)->get('/leads')->getContent();

        $this->assertStringContainsString('rememberLeadsPage(1);', $body);
        // once in the Apply handler, once in Clear
        $this->assertSame(2, substr_count($body, 'rememberLeadsPage(1);'));
    }

    public function test_the_filter_request_carries_the_page_number()
    {
        $body = $this->actingAs($this->company)->get('/leads')->getContent();

        $this->assertStringContainsString('function runLeadsFilter(data, page) {', $body);
        // the append was later folded into leadsUrlWith(); what matters is that
        // the page number is part of the request
        $this->assertStringContainsString("page: page && page > 1 ? page : ''", $body);
    }

    public function test_the_server_still_filters_on_a_later_page()
    {
        for ($i = 0; $i < 30; $i++) {
            $this->makeLead(['name' => 'PagedLead' . $i, 'country' => 'Algeria']);
        }
        $this->makeLead(['name' => 'OtherCountryLead', 'country' => 'Egypt']);

        $r = $this->actingAs($this->company)->get('/leads?page=2&country=Algeria', [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);
        $r->assertStatus(200);
        $r->assertDontSee('OtherCountryLead');
    }

    // ---- rows per page -------------------------------------------------------------

    protected function cardsOnBoard($html)
    {
        return substr_count($html, 'lead-card-item');
    }

    public function test_the_board_honours_the_chosen_page_size()
    {
        for ($i = 0; $i < 30; $i++) {
            $this->makeLead(['name' => 'SizeLead' . $i]);
        }

        $small = $this->actingAs($this->company)->get('/leads?limit=25');
        $small->assertStatus(200);
        $big = $this->actingAs($this->company)->get('/leads?limit=50');
        $big->assertStatus(200);

        $this->assertGreaterThan(
            $this->cardsOnBoard($small->getContent()),
            $this->cardsOnBoard($big->getContent()),
            'the per-page selector still changes nothing on the first render'
        );
    }

    public function test_a_page_size_that_is_not_offered_falls_back_to_25()
    {
        for ($i = 0; $i < 30; $i++) {
            $this->makeLead(['name' => 'BadSizeLead' . $i]);
        }

        $sane = $this->actingAs($this->company)->get('/leads?limit=25');
        $silly = $this->actingAs($this->company)->get('/leads?limit=999999');
        $silly->assertStatus(200);

        $this->assertSame(
            $this->cardsOnBoard($sane->getContent()),
            $this->cardsOnBoard($silly->getContent()),
            'an unoffered page size was accepted'
        );
    }

    public function test_the_ajax_board_honours_the_page_size_too()
    {
        for ($i = 0; $i < 30; $i++) {
            $this->makeLead(['name' => 'AjaxSizeLead' . $i]);
        }

        $headers = ['X-Requested-With' => 'XMLHttpRequest'];
        $small = $this->actingAs($this->company)->get('/leads?limit=25', $headers);
        $big = $this->actingAs($this->company)->get('/leads?limit=50', $headers);

        $this->assertGreaterThan($this->cardsOnBoard($small->getContent()), $this->cardsOnBoard($big->getContent()));
    }

    public function test_every_board_request_carries_the_page_size()
    {
        $body = $this->actingAs($this->company)->get('/leads')->getContent();

        $this->assertStringContainsString('function savedLeadsLimit() {', $body);
        $this->assertStringContainsString('limit: savedLeadsLimit()', $body);
        // once for the filter request, once for each pagination hop
        $this->assertSame(3, substr_count($body, 'savedLeadsLimit()'));
    }

    public function test_the_page_size_is_not_added_twice_to_a_url()
    {
        $body = $this->actingAs($this->company)->get('/leads')->getContent();

        $this->assertStringContainsString("new RegExp('[?&]' + key + '=').test(url)", $body);
    }
}