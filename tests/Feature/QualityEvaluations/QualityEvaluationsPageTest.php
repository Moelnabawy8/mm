<?php

namespace Tests\Feature\QualityEvaluations;

use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\Pipeline;
use App\Models\QualityEvaluation;
use App\Models\User;
use App\Models\UserLead;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class QualityEvaluationsPageTest extends TestCase
{
    use DatabaseTransactions;

    protected $company;
    protected $pipeline;
    protected $leadStage;

    protected function setUp(): void
    {
        parent::setUp();
        app()['cache']->forget('spatie.permission.cache');

        $this->company = $this->makeUser('company', 'qeCompany', 1, [
            'manage quality evaluation', 'create quality evaluation',
            'edit quality evaluation', 'delete quality evaluation',
        ]);

        $this->pipeline = Pipeline::create(['name' => 'Main', 'created_by' => $this->company->id]);
        $this->leadStage = LeadStage::create(['name' => 'New', 'pipeline_id' => $this->pipeline->id, 'created_by' => $this->company->id, 'order' => 0]);
    }

    protected function makeUser($type, $roleName, $createdBy, array $perms = [])
    {
        static $n = 0;
        $n++;
        $u = new User();
        $u->name = $type . $n;
        $u->email = 'qe' . $n . '_' . uniqid() . '@test.local';
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
        $lead->email = $attrs['email'] ?? ('l' . uniqid() . '@test.local');
        $lead->phone = $attrs['phone'] ?? null;
        $lead->subject = 'test';
        $lead->user_id = $attrs['user_id'] ?? $this->company->id;
        $lead->pipeline_id = $this->pipeline->id;
        $lead->stage_id = $this->leadStage->id;
        $lead->created_by = $attrs['created_by'] ?? $this->company->id;
        $lead->date = date('Y-m-d');
        foreach (['country', 'campaign_name', 'city'] as $f) {
            if (array_key_exists($f, $attrs)) {
                $lead->$f = $attrs[$f];
            }
        }
        $lead->save();
        foreach ((array) ($attrs['assign'] ?? []) as $uid) {
            UserLead::create(['user_id' => $uid, 'lead_id' => $lead->id]);
        }

        return $lead->fresh();
    }

    protected function makeEvaluation($lead, $evaluator, $attrs = [])
    {
        return QualityEvaluation::create(array_merge([
            'lead_id'           => $lead->id,
            'evaluator_id'      => $evaluator->id,
            'is_potential_lead' => QualityEvaluation::POTENTIAL_UNKNOWN,
        ], $attrs));
    }

    /** A second company with its own lead, evaluator and evaluation. */
    protected function foreignTenant()
    {
        $other = $this->makeUser('company', 'qeOther', 1, [
            'manage quality evaluation', 'create quality evaluation',
            'edit quality evaluation', 'delete quality evaluation',
        ]);
        $pipeline = Pipeline::create(['name' => 'OtherMain', 'created_by' => $other->id]);
        $stage = LeadStage::create(['name' => 'OtherNew', 'pipeline_id' => $pipeline->id, 'created_by' => $other->id, 'order' => 0]);

        $lead = new Lead();
        $lead->name = 'FOREIGNQELEAD';
        $lead->email = 'f' . uniqid() . '@test.local';
        $lead->subject = 'test';
        $lead->user_id = $other->id;
        $lead->pipeline_id = $pipeline->id;
        $lead->stage_id = $stage->id;
        $lead->created_by = $other->id;
        $lead->date = date('Y-m-d');
        $lead->campaign_name = 'FOREIGNQECAMPAIGN';
        $lead->save();
        UserLead::create(['user_id' => $other->id, 'lead_id' => $lead->id]);

        $evaluation = $this->makeEvaluation($lead, $other);

        return [$other, $lead, $evaluation];
    }

    // ---- the page loads ----------------------------------------------------------

    public function test_guest_is_redirected()
    {
        $this->get('/quality-evaluations')->assertRedirect();
    }

    public function test_company_can_open_the_page()
    {
        $this->makeEvaluation($this->makeLead(['name' => 'OpenPageLead', 'assign' => [$this->company->id]]), $this->company);

        $r = $this->actingAs($this->company)->get('/quality-evaluations');
        $r->assertStatus(200);
        $r->assertSee('OpenPageLead');
    }

    public function test_user_without_the_permission_is_denied()
    {
        $u = $this->makeUser('Employee', 'qePlain', $this->company->id, []);
        $this->actingAs($u)->get('/quality-evaluations')->assertRedirect();
    }

    // ---- the blank Edit page -----------------------------------------------------

    public function test_edit_does_not_render_an_option_per_lead()
    {
        $owner = $this->makeUser('Sales Employee', 'qeEditOwner', $this->company->id, ['manage quality evaluation', 'edit quality evaluation']);
        $target = $this->makeLead(['name' => 'EditTargetLead', 'assign' => [$owner->id]]);
        for ($i = 0; $i < 8; $i++) {
            $this->makeLead(['name' => 'BulkLead' . $i]);
        }
        $evaluation = $this->makeEvaluation($target, $owner);

        $r = $this->actingAs($owner)->get('/quality-evaluations/' . $evaluation->id . '/edit');
        $r->assertStatus(200);
        // the lead it already points at is labelled...
        $r->assertSee('EditTargetLead');
        // ...and no other lead is rendered into the page at all
        $r->assertDontSee('BulkLead0');
        $r->assertDontSee('BulkLead7');
    }

    public function test_create_does_not_render_an_option_per_lead()
    {
        for ($i = 0; $i < 8; $i++) {
            $this->makeLead(['name' => 'CreateBulkLead' . $i]);
        }

        $r = $this->actingAs($this->company)->get('/quality-evaluations/create');
        $r->assertStatus(200);
        $r->assertDontSee('CreateBulkLead0');
        $r->assertDontSee('CreateBulkLead7');
    }

    public function test_edit_labels_the_lead_passed_in_the_query()
    {
        $lead = $this->makeLead(['name' => 'LabelledLead', 'assign' => [$this->company->id]]);
        $evaluation = $this->makeEvaluation($lead, $this->company);

        $r = $this->actingAs($this->company)->get('/quality-evaluations/' . $evaluation->id . '/edit');
        $r->assertStatus(200);
        $r->assertSee('value="LabelledLead"', false);
    }

    public function test_edit_query_count_does_not_grow_with_the_leads_table()
    {
        $evaluation = $this->makeEvaluation($this->makeLead(['name' => 'CountLead', 'assign' => [$this->company->id]]), $this->company);
        for ($i = 0; $i < 25; $i++) {
            $this->makeLead(['name' => 'CountBulk' . $i]);
        }

        \DB::enableQueryLog();
        $r = $this->actingAs($this->company)->get('/quality-evaluations/' . $evaluation->id . '/edit');
        $count = count(\DB::getQueryLog());
        \DB::disableQueryLog();

        $r->assertStatus(200);
        $this->assertLessThan(30, $count, 'edit form ran ' . $count . ' queries');
    }

    // ---- the lead search ---------------------------------------------------------

    public function test_lead_search_is_capped_and_sends_name_and_phone_apart()
    {
        $this->makeLead(['name' => 'SearchAhmed', 'phone' => '201002633348']);
        for ($i = 0; $i < 30; $i++) {
            $this->makeLead(['name' => 'SearchBulk' . $i, 'phone' => '2010000000' . $i]);
        }

        $r = $this->actingAs($this->company)->get('/quality-evaluations-leads-search?q=SearchAhmed');
        $r->assertStatus(200);
        $body = $r->json();
        $this->assertNotEmpty($body);
        $this->assertSame('SearchAhmed', $body[0]['name']);
        $this->assertSame('201002633348', $body[0]['phone']);

        $all = $this->actingAs($this->company)->get('/quality-evaluations-leads-search?q=SearchBulk');
        $this->assertLessThanOrEqual(25, count($all->json()));
    }

    public function test_lead_search_matches_a_phone_number()
    {
        $this->makeLead(['name' => 'PhoneSearchLead', 'phone' => '249960694641']);
        $this->makeLead(['name' => 'OtherPhoneLead', 'phone' => '201111111111']);

        $r = $this->actingAs($this->company)->get('/quality-evaluations-leads-search?q=249960');
        $r->assertStatus(200);
        $names = array_column($r->json(), 'name');
        $this->assertContains('PhoneSearchLead', $names);
        $this->assertNotContains('OtherPhoneLead', $names);
    }

    public function test_lead_search_does_not_leak_another_tenant()
    {
        $this->foreignTenant();

        $r = $this->actingAs($this->company)->get('/quality-evaluations-leads-search?q=FOREIGNQELEAD');
        $r->assertStatus(200);
        $this->assertSame([], $r->json());
    }

    public function test_lead_search_gives_an_ordinary_evaluator_only_their_own_leads()
    {
        $emp = $this->makeUser('Sales Employee', 'qeSearchEmp', $this->company->id, ['manage quality evaluation', 'create quality evaluation']);
        $this->makeLead(['name' => 'EmpOwnLead', 'assign' => [$emp->id]]);
        $this->makeLead(['name' => 'EmpHiddenLead']);

        $r = $this->actingAs($emp)->get('/quality-evaluations-leads-search?q=Emp');
        $r->assertStatus(200);
        $names = array_column($r->json(), 'name');
        $this->assertContains('EmpOwnLead', $names);
        $this->assertNotContains('EmpHiddenLead', $names);
    }

    // ---- cross-tenant -------------------------------------------------------------

    public function test_index_does_not_list_another_tenants_evaluation()
    {
        list($other, $foreignLead, $foreignEvaluation) = $this->foreignTenant();
        $this->makeEvaluation($this->makeLead(['name' => 'MyOwnQELead', 'assign' => [$this->company->id]]), $this->company);

        $r = $this->actingAs($this->company)->get('/quality-evaluations');
        $r->assertStatus(200);
        $r->assertSee('MyOwnQELead');
        $r->assertDontSee('FOREIGNQELEAD');
    }

    public function test_show_does_not_leak_another_tenants_evaluation()
    {
        list($other, $foreignLead, $foreignEvaluation) = $this->foreignTenant();

        $r = $this->actingAs($this->company)->get('/quality-evaluations/' . $foreignEvaluation->id);
        $this->assertNotEquals(200, $r->status(), 'must not read another tenant evaluation');
    }

    public function test_edit_does_not_leak_another_tenants_evaluation()
    {
        list($other, $foreignLead, $foreignEvaluation) = $this->foreignTenant();

        $r = $this->actingAs($this->company)->get('/quality-evaluations/' . $foreignEvaluation->id . '/edit');
        $this->assertNotEquals(200, $r->status(), 'must not open another tenant evaluation');
    }

    public function test_update_cannot_touch_another_tenants_evaluation()
    {
        list($other, $foreignLead, $foreignEvaluation) = $this->foreignTenant();
        $mine = $this->makeLead(['name' => 'MineForUpdate', 'assign' => [$this->company->id]]);

        $this->actingAs($this->company)->put('/quality-evaluations/' . $foreignEvaluation->id, [
            'lead_id'           => $mine->id,
            'engagement_level'  => 'highly_engaged',
            'course_fit_level'  => 'perfect_fit',
            'is_potential_lead' => 'yes',
            'rating'            => 5,
        ]);

        $this->assertSame($foreignLead->id, $foreignEvaluation->fresh()->lead_id, 'another tenant evaluation was modified');
    }

    public function test_destroy_cannot_delete_another_tenants_evaluation()
    {
        list($other, $foreignLead, $foreignEvaluation) = $this->foreignTenant();

        $this->actingAs($this->company)->delete('/quality-evaluations/' . $foreignEvaluation->id);

        $this->assertNotNull($foreignEvaluation->fresh(), 'another tenant evaluation was deleted');
    }

    public function test_store_rejects_a_lead_from_another_tenant()
    {
        list($other, $foreignLead, $foreignEvaluation) = $this->foreignTenant();
        $before = QualityEvaluation::where('lead_id', $foreignLead->id)->count();

        $this->actingAs($this->company)->post('/quality-evaluations', [
            'lead_id'           => $foreignLead->id,
            'engagement_level'  => 'highly_engaged',
            'course_fit_level'  => 'perfect_fit',
            'is_potential_lead' => 'yes',
            'rating'            => 4,
        ]);

        $this->assertSame($before, QualityEvaluation::where('lead_id', $foreignLead->id)->count());
    }

    public function test_campaign_filter_does_not_leak_another_tenants_campaigns()
    {
        $this->foreignTenant();
        $this->makeLead(['name' => 'MyCampaignLead', 'campaign_name' => 'MYQECAMPAIGN', 'assign' => [$this->company->id]]);

        $r = $this->actingAs($this->company)->get('/quality-evaluations');
        $r->assertStatus(200);
        $r->assertSee('MYQECAMPAIGN');
        $r->assertDontSee('FOREIGNQECAMPAIGN');
    }

    public function test_export_does_not_leak_another_tenants_rows()
    {
        list($other, $foreignLead, $foreignEvaluation) = $this->foreignTenant();
        $this->makeEvaluation($this->makeLead(['name' => 'MyExportLead', 'assign' => [$this->company->id]]), $this->company);

        $r = $this->actingAs($this->company)->get('/quality-evaluations-export');
        $r->assertStatus(200);
        $body = $r->streamedContent();
        $this->assertStringContainsString('MyExportLead', $body);
        $this->assertStringNotContainsString('FOREIGNQELEAD', $body);
    }

    // ---- the auto-created placeholder rows ----------------------------------------

    public function test_opening_the_page_creates_one_evaluation_per_assigned_lead()
    {
        $emp = $this->makeUser('Sales Employee', 'qeAutoEmp', $this->company->id, ['manage quality evaluation']);
        $a = $this->makeLead(['name' => 'AutoLeadA', 'assign' => [$emp->id]]);
        $b = $this->makeLead(['name' => 'AutoLeadB', 'assign' => [$emp->id]]);

        $this->actingAs($emp)->get('/quality-evaluations')->assertStatus(200);

        $this->assertSame(1, QualityEvaluation::where('lead_id', $a->id)->where('evaluator_id', $emp->id)->count());
        $this->assertSame(1, QualityEvaluation::where('lead_id', $b->id)->where('evaluator_id', $emp->id)->count());
    }

    public function test_opening_the_page_twice_does_not_duplicate_the_rows()
    {
        $emp = $this->makeUser('Sales Employee', 'qeTwiceEmp', $this->company->id, ['manage quality evaluation']);
        $lead = $this->makeLead(['name' => 'TwiceLead', 'assign' => [$emp->id]]);

        $this->actingAs($emp)->get('/quality-evaluations')->assertStatus(200);
        $this->actingAs($emp)->get('/quality-evaluations')->assertStatus(200);

        $this->assertSame(1, QualityEvaluation::where('lead_id', $lead->id)->where('evaluator_id', $emp->id)->count());
    }

    public function test_the_backfill_is_skipped_while_another_request_holds_the_lock()
    {
        $emp = $this->makeUser('Sales Employee', 'qeLockEmp', $this->company->id, ['manage quality evaluation']);
        $lead = $this->makeLead(['name' => 'LockedLead', 'assign' => [$emp->id]]);

        // A real second connection, so GET_LOCK inside the controller genuinely
        // fails the way it would for a request arriving at the same moment.
        $default = config('database.default');
        config(['database.connections.qe_concurrent' => config('database.connections.' . $default)]);
        $concurrent = \DB::connection('qe_concurrent');

        $lockName = 'qe_backfill_' . $this->company->id;
        $held = $concurrent->selectOne('SELECT GET_LOCK(?, 0) AS got', [$lockName]);
        $this->assertSame(1, (int) $held->got, 'could not take the lock for the test');

        try {
            $this->actingAs($emp)->get('/quality-evaluations')->assertStatus(200);

            // the page still renders, it just leaves the backfill to whoever holds
            // the lock instead of racing them into a duplicate row
            $this->assertSame(
                0,
                QualityEvaluation::where('lead_id', $lead->id)->where('evaluator_id', $emp->id)->count(),
                'the backfill ran while another request held the lock'
            );
        } finally {
            $concurrent->select('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
            $concurrent->disconnect();
        }

        // Lock free again: the next page load does the work.
        $this->actingAs($emp)->get('/quality-evaluations')->assertStatus(200);
        $this->assertSame(1, QualityEvaluation::where('lead_id', $lead->id)->where('evaluator_id', $emp->id)->count());
    }

    public function test_opening_the_page_never_creates_rows_for_another_tenant()
    {
        list($other, $foreignLead, $foreignEvaluation) = $this->foreignTenant();
        $foreignEvaluation->delete();

        $this->actingAs($this->company)->get('/quality-evaluations')->assertStatus(200);

        $this->assertSame(
            0,
            QualityEvaluation::where('lead_id', $foreignLead->id)->count(),
            'the page created an evaluation against another tenant lead'
        );
    }

    public function test_index_query_count_does_not_grow_with_the_number_of_leads()
    {
        $emp = $this->makeUser('Sales Employee', 'qeCountEmp', $this->company->id, ['manage quality evaluation']);
        for ($i = 0; $i < 20; $i++) {
            $this->makeLead(['name' => 'IndexCountLead' . $i, 'assign' => [$emp->id]]);
        }

        \DB::enableQueryLog();
        $r = $this->actingAs($emp)->get('/quality-evaluations');
        $count = count(\DB::getQueryLog());
        \DB::disableQueryLog();

        $r->assertStatus(200);
        // the old code ran a select and an insert per lead
        $this->assertLessThan(45, $count, 'index ran ' . $count . ' queries for 20 leads');
    }

    // ---- an ordinary evaluator stays inside their own rows -------------------------

    public function test_an_employee_cannot_open_a_colleagues_evaluation()
    {
        $a = $this->makeUser('Sales Employee', 'qeEmpA', $this->company->id, ['manage quality evaluation', 'edit quality evaluation']);
        $b = $this->makeUser('Sales Employee', 'qeEmpB', $this->company->id, ['manage quality evaluation', 'edit quality evaluation']);
        $lead = $this->makeLead(['name' => 'ColleagueLead', 'assign' => [$b->id]]);
        $evaluation = $this->makeEvaluation($lead, $b);

        $this->actingAs($a)->get('/quality-evaluations/' . $evaluation->id)->assertRedirect();
        $this->actingAs($a)->get('/quality-evaluations/' . $evaluation->id . '/edit')->assertRedirect();
    }

    // ---- filters -------------------------------------------------------------------

    public function test_country_filter_lists_the_create_form_countries_not_stored_junk()
    {
        $this->makeEvaluation($this->makeLead(['name' => 'JunkCountryQE', 'country' => '97477889531', 'assign' => [$this->company->id]]), $this->company);

        $r = $this->actingAs($this->company)->get('/quality-evaluations');
        $r->assertStatus(200);
        $r->assertDontSee('<option value="97477889531"', false);
        $r->assertSee('<option value="Qatar"', false);
        $r->assertSee('<option value="Japan"', false);
    }

    public function test_users_filter_lists_sales_people_only()
    {
        $sales = $this->makeUser('Sales Employee', 'qeSalesFilter', $this->company->id, ['manage quality evaluation']);
        $accountant = $this->makeUser('Accountant', 'qeAccountantFilter', $this->company->id, ['manage quality evaluation']);

        $r = $this->actingAs($this->company)->get('/quality-evaluations');
        $r->assertStatus(200);
        $r->assertSee('<option value="' . $sales->id . '">' . $sales->name, false);
        $r->assertDontSee('<option value="' . $accountant->id . '">' . $accountant->name, false);
    }

    public function test_filtering_by_potential_narrows_the_list()
    {
        $yes = $this->makeLead(['name' => 'PotentialYesLead', 'assign' => [$this->company->id]]);
        $no = $this->makeLead(['name' => 'PotentialNoLead', 'assign' => [$this->company->id]]);
        $this->makeEvaluation($yes, $this->company, ['is_potential_lead' => 'yes']);
        $this->makeEvaluation($no, $this->company, ['is_potential_lead' => 'no']);

        $r = $this->actingAs($this->company)->get('/quality-evaluations?is_potential_lead=yes');
        $r->assertStatus(200);
        $r->assertSee('PotentialYesLead');
        $r->assertDontSee('PotentialNoLead');
    }

    public function test_search_by_lead_name_and_phone()
    {
        $this->makeEvaluation($this->makeLead(['name' => 'FindMeQE', 'phone' => '201999888777', 'assign' => [$this->company->id]]), $this->company);
        $this->makeEvaluation($this->makeLead(['name' => 'HideMeQE', 'phone' => '201000111222', 'assign' => [$this->company->id]]), $this->company);

        $byName = $this->actingAs($this->company)->get('/quality-evaluations?name=FindMeQE');
        $byName->assertStatus(200)->assertSee('FindMeQE')->assertDontSee('HideMeQE');

        $byPhone = $this->actingAs($this->company)->get('/quality-evaluations?phone=201999888777');
        $byPhone->assertStatus(200)->assertSee('FindMeQE')->assertDontSee('HideMeQE');
    }

    // ---- writing --------------------------------------------------------------------

    public function test_store_creates_an_evaluation()
    {
        $lead = $this->makeLead(['name' => 'StoreQELead', 'assign' => [$this->company->id]]);

        $this->actingAs($this->company)->post('/quality-evaluations', [
            'lead_id'           => $lead->id,
            'engagement_level'  => 'highly_engaged',
            'course_fit_level'  => 'perfect_fit',
            'is_potential_lead' => 'yes',
            'rating'            => 5,
            'notes'             => 'looks good',
        ]);

        $row = QualityEvaluation::where('lead_id', $lead->id)->where('evaluator_id', $this->company->id)->first();
        $this->assertNotNull($row);
        $this->assertSame('yes', $row->is_potential_lead);
        $this->assertSame(5, $row->rating);
    }

    public function test_update_saves_the_changes()
    {
        $lead = $this->makeLead(['name' => 'UpdateQELead', 'assign' => [$this->company->id]]);
        $evaluation = $this->makeEvaluation($lead, $this->company);

        $this->actingAs($this->company)->put('/quality-evaluations/' . $evaluation->id, [
            'lead_id'           => $lead->id,
            'engagement_level'  => 'low_engagement',
            'course_fit_level'  => 'not_a_fit',
            'is_potential_lead' => 'no',
            'rating'            => 2,
            'notes'             => 'changed',
        ]);

        $fresh = $evaluation->fresh();
        $this->assertSame('no', $fresh->is_potential_lead);
        $this->assertSame(2, $fresh->rating);
        $this->assertSame('changed', $fresh->notes);
    }

    public function test_update_rejects_a_rating_outside_one_to_five()
    {
        $lead = $this->makeLead(['name' => 'RatingQELead', 'assign' => [$this->company->id]]);
        $evaluation = $this->makeEvaluation($lead, $this->company, ['rating' => 3]);

        $this->actingAs($this->company)->put('/quality-evaluations/' . $evaluation->id, [
            'lead_id'           => $lead->id,
            'engagement_level'  => 'moderate',
            'course_fit_level'  => 'good_fit',
            'is_potential_lead' => 'unknown',
            'rating'            => 99,
        ]);

        $this->assertSame(3, $evaluation->fresh()->rating);
    }

    public function test_destroy_removes_my_own_evaluation()
    {
        $lead = $this->makeLead(['name' => 'DeleteQELead', 'assign' => [$this->company->id]]);
        $evaluation = $this->makeEvaluation($lead, $this->company);

        $this->actingAs($this->company)->delete('/quality-evaluations/' . $evaluation->id);

        $this->assertNull(QualityEvaluation::find($evaluation->id));
    }

    public function test_a_missing_evaluation_is_a_404_not_a_500()
    {
        $r = $this->actingAs($this->company)->get('/quality-evaluations/99999999');
        $this->assertSame(404, $r->status());
    }

    // ---- reaching the lead from the evaluation ----------------------------------

    public function test_the_card_links_to_whatsapp_with_digits_only()
    {
        $lead = $this->makeLead([
            'name' => 'WhatsAppLead',
            'phone' => '+968 9429-2982',
            'assign' => [$this->company->id],
        ]);
        $this->makeEvaluation($lead, $this->company);

        $r = $this->actingAs($this->company)->get('/quality-evaluations');
        $r->assertStatus(200);

        // wa.me takes bare digits; the spaces, plus and dash must be gone
        $r->assertSee('https://wa.me/96894292982', false);
    }

    public function test_the_card_uses_the_first_of_several_numbers()
    {
        $lead = $this->makeLead([
            'name' => 'MultiPhoneLead',
            'phone' => "96899111111,\n96899222222",
            'assign' => [$this->company->id],
        ]);
        $this->makeEvaluation($lead, $this->company);

        $r = $this->actingAs($this->company)->get('/quality-evaluations');
        $r->assertSee('https://wa.me/96899111111', false);
        $r->assertDontSee('https://wa.me/96899222222', false);
    }

    public function test_a_lead_with_no_phone_gets_no_whatsapp_button()
    {
        $lead = $this->makeLead(['name' => 'NoPhoneLead', 'assign' => [$this->company->id]]);
        $this->makeEvaluation($lead, $this->company);

        $r = $this->actingAs($this->company)->get('/quality-evaluations');
        $r->assertStatus(200);
        $r->assertDontSee('https://wa.me/"', false);
        $r->assertDontSee('https://wa.me/ ', false);
    }

    public function test_the_whatsapp_link_opens_in_a_new_tab_safely()
    {
        $lead = $this->makeLead(['name' => 'RelLead', 'phone' => '96894292982', 'assign' => [$this->company->id]]);
        $this->makeEvaluation($lead, $this->company);

        $r = $this->actingAs($this->company)->get('/quality-evaluations');
        $r->assertSee('target="_blank" rel="noopener"', false);
    }

    public function test_listing_evaluations_does_not_query_per_card()
    {
        for ($i = 0; $i < 5; $i++) {
            $lead = $this->makeLead(['name' => 'PerfLead' . $i, 'phone' => '9689400000' . $i, 'assign' => [$this->company->id]]);
            $this->makeEvaluation($lead, $this->company);
        }

        \DB::enableQueryLog();
        $this->actingAs($this->company)->get('/quality-evaluations')->assertStatus(200);
        $count = count(\DB::getQueryLog());
        \DB::disableQueryLog();

        // the phone comes off the already-loaded lead row, so adding cards must
        // not add queries
        $this->assertLessThan(40, $count, "the page ran $count queries");
    }
}