<?php

namespace Tests\Feature\SalesMeetings;

use App\Models\Deal;
use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\Pipeline;
use App\Models\SalesMeeting;
use App\Models\SalesMeetingType;
use App\Models\Stage;
use App\Models\User;
use App\Models\UserLead;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SalesMeetingsPageTest extends TestCase
{
    use DatabaseTransactions;

    protected $company;
    protected $pipeline;
    protected $leadStage;
    protected $dealStage;
    protected $type;

    protected function setUp(): void
    {
        parent::setUp();
        app()['cache']->forget('spatie.permission.cache');

        $this->company = $this->makeUser('company', 'smCompany', 1, [
            'manage lead', 'view lead', 'create lead', 'edit lead', 'delete lead',
        ]);

        $this->pipeline = Pipeline::create(['name' => 'Main', 'created_by' => $this->company->id]);
        $this->leadStage = LeadStage::create(['name' => 'New', 'pipeline_id' => $this->pipeline->id, 'created_by' => $this->company->id, 'order' => 0]);
        $this->dealStage = Stage::create(['name' => 'Open', 'pipeline_id' => $this->pipeline->id, 'created_by' => $this->company->id, 'order' => 0]);
        $this->type = SalesMeetingType::create(['name' => 'Call', 'created_by' => $this->company->id]);
    }

    protected function makeUser($type, $roleName, $createdBy, array $perms = [])
    {
        static $n = 0;
        $n++;
        $u = new User();
        $u->name = $type . $n;
        $u->email = 'sm' . $n . '_' . uniqid() . '@test.local';
        $u->password = bcrypt('secret');
        $u->type = $type;
        $u->created_by = $createdBy;
        $u->lang = 'en';
        $u->email_verified_at = now();
        $u->save();

        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web'], ['created_by' => $createdBy ?: 1]);
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

    protected function makeDeal(Lead $lead, $name = null, $createdBy = null)
    {
        $deal = new Deal();
        $deal->name = $name ?? 'Deal ' . uniqid();
        $deal->lead_id = $lead->id;
        $deal->pipeline_id = $this->pipeline->id;
        $deal->stage_id = $this->dealStage->id;
        $deal->created_by = $createdBy ?? $this->company->id;
        $deal->save();

        return $deal->fresh();
    }

    protected function makeMeeting($attrs = [])
    {
        return SalesMeeting::create(array_merge([
            'title' => 'Meeting ' . uniqid(),
            'meeting_type_id' => $this->type->id,
            'datetime' => '2026-09-20 00:00:00',
            'created_by' => $this->company->id,
        ], $attrs));
    }

    protected function foreignTenant()
    {
        $other = $this->makeUser('company', 'smOther', 1, ['manage lead']);
        $lead = $this->makeLead(['name' => 'FOREIGNLEAD', 'created_by' => $other->id, 'user_id' => $other->id]);
        $meeting = $this->makeMeeting(['title' => 'FOREIGNMEETING', 'lead_id' => $lead->id, 'created_by' => $other->id]);

        return [$other, $lead, $meeting];
    }

    // ---------- A. page load ----------

    public function test_guest_is_redirected()
    {
        $this->get('/sales-meetings')->assertRedirect();
    }

    public function test_company_can_open_the_page()
    {
        $lead = $this->makeLead(['name' => 'VisibleLead']);
        $this->makeMeeting(['title' => 'KickoffMeeting', 'lead_id' => $lead->id]);

        $this->actingAs($this->company)->get('/sales-meetings')
            ->assertStatus(200)->assertSee('KickoffMeeting')->assertSee('VisibleLead');
    }

    public function test_index_does_not_render_every_lead_and_deal_into_dropdowns()
    {
        $lead = $this->makeLead(['name' => 'UNUSEDLEADNAME']);
        $this->makeDeal($lead, 'UNUSEDDEALNAME');

        $this->actingAs($this->company)->get('/sales-meetings')
            ->assertStatus(200)
            ->assertDontSee('UNUSEDLEADNAME')
            ->assertDontSee('UNUSEDDEALNAME');
    }

    public function test_create_form_does_not_render_every_lead()
    {
        $this->makeLead(['name' => 'UNUSEDLEADCREATE']);
        $this->actingAs($this->company)->get('/sales-meetings/create')
            ->assertStatus(200)->assertDontSee('UNUSEDLEADCREATE');
    }

    public function test_edit_form_shows_only_the_selected_lead()
    {
        $this->makeLead(['name' => 'UNUSEDLEADEDIT']);
        $lead = $this->makeLead(['name' => 'PickedLead']);
        $m = $this->makeMeeting(['lead_id' => $lead->id]);

        $this->actingAs($this->company)->get('/sales-meetings/' . $m->id . '/edit')
            ->assertStatus(200)->assertSee('PickedLead')->assertDontSee('UNUSEDLEADEDIT');
    }

    public function test_page_query_count_is_bounded()
    {
        for ($i = 0; $i < 20; $i++) {
            $emp = $this->makeUser('Employee', 'smAssignee' . $i, $this->company->id, []);
            $lead = $this->makeLead(['user_id' => $emp->id]);
            $this->makeMeeting(['lead_id' => $lead->id, 'deal_id' => $this->makeDeal($lead)->id]);
        }

        \DB::enableQueryLog();
        \DB::flushQueryLog();
        $this->actingAs($this->company)->get('/sales-meetings')->assertStatus(200);
        $count = count(\DB::getQueryLog());
        \DB::disableQueryLog();

        fwrite(STDERR, "\n[sales-meetings] queries for 20 rows: " . $count . "\n");
        $this->assertLessThan(45, $count, 'the table runs a query per row');
    }

    public function test_time_is_kept_and_midnight_placeholder_is_not_shown()
    {
        $lead = $this->makeLead();
        $this->makeMeeting(['title' => 'TimedMeeting', 'lead_id' => $lead->id, 'datetime' => '2026-09-20 14:30:00']);
        $this->makeMeeting(['title' => 'DateOnlyMeeting', 'lead_id' => $lead->id, 'datetime' => '2026-09-21 00:00:00']);

        $r = $this->actingAs($this->company)->get('/sales-meetings')->assertStatus(200);
        $r->assertSee('02:30 PM');
        $r->assertDontSee('12:00 AM');
    }

    // ---------- B. filters + search endpoints ----------

    public function test_filter_by_lead()
    {
        $a = $this->makeLead(['name' => 'LeadA']);
        $b = $this->makeLead(['name' => 'LeadB']);
        $this->makeMeeting(['title' => 'MeetA', 'lead_id' => $a->id]);
        $this->makeMeeting(['title' => 'MeetB', 'lead_id' => $b->id]);

        $this->actingAs($this->company)->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get('/sales-meetings?lead_id=' . $a->id)
            ->assertStatus(200)->assertSee('MeetA')->assertDontSee('MeetB');
    }

    public function test_lead_search_is_limited_and_tenant_scoped()
    {
        for ($i = 0; $i < 30; $i++) {
            $this->makeLead(['name' => 'Searchable' . $i]);
        }
        $this->foreignTenant();

        $r = $this->actingAs($this->company)->getJson(route('sales-meetings.leads.search', ['q' => 'Searchable']));
        $r->assertStatus(200);
        $this->assertCount(25, $r->json());

        $r2 = $this->actingAs($this->company)->getJson(route('sales-meetings.leads.search', ['q' => 'FOREIGN']));
        $this->assertCount(0, $r2->json());
    }

    public function test_sales_employee_lead_search_only_returns_assigned_leads()
    {
        $emp = $this->makeUser('Employee', 'Sales Employee', $this->company->id, ['manage lead', 'create lead']);
        $this->makeLead(['name' => 'EmpMineLead', 'assign' => [$emp->id]]);
        $this->makeLead(['name' => 'EmpOtherLead']);

        $r = $this->actingAs($emp)->getJson(route('sales-meetings.leads.search', ['q' => 'Emp']));
        $r->assertStatus(200);
        $this->assertStringContainsString('EmpMineLead', $r->getContent());
        $this->assertStringNotContainsString('EmpOtherLead', $r->getContent());
    }

    public function test_deal_search_is_tenant_scoped()
    {
        $lead = $this->makeLead();
        $this->makeDeal($lead, 'MyDealX');
        [$other, $foreignLead] = $this->foreignTenant();
        $this->makeDeal($foreignLead, 'ForeignDealX', $other->id);

        $r = $this->actingAs($this->company)->getJson(route('sales-meetings.deals.search', ['q' => 'DealX']));
        $r->assertStatus(200);
        $this->assertStringContainsString('MyDealX', $r->getContent());
        $this->assertStringNotContainsString('ForeignDealX', $r->getContent());
    }

    public function test_get_deals_does_not_leak_other_tenants_deals()
    {
        [$other, $foreignLead] = $this->foreignTenant();
        $this->makeDeal($foreignLead, 'LEAKEDDEAL', $other->id);

        $r = $this->actingAs($this->company)->postJson('/sales-meetings/get-deals', ['lead_id' => $foreignLead->id]);
        $this->assertStringNotContainsString('LEAKEDDEAL', $r->getContent());
    }

    // ---------- C. tenant isolation / missing records ----------

    public function test_show_other_tenant_meeting_is_denied()
    {
        [, , $m] = $this->foreignTenant();
        $r = $this->actingAs($this->company)->get('/sales-meetings/' . $m->id);
        $this->assertNotEquals(500, $r->getStatusCode());
        $r->assertDontSee('FOREIGNMEETING');
    }

    public function test_missing_meeting_does_not_crash()
    {
        $this->actingAs($this->company);
        $this->assertNotEquals(500, $this->get('/sales-meetings/99999999')->getStatusCode(), 'show');
        $this->assertNotEquals(500, $this->get('/sales-meetings/99999999/edit')->getStatusCode(), 'edit');
        $this->assertNotEquals(500, $this->put('/sales-meetings/99999999', ['title' => 'x', 'lead_id' => 1, 'meeting_type_id' => 1, 'datetime' => '2026-01-01'])->getStatusCode(), 'update');
        $this->assertNotEquals(500, $this->delete('/sales-meetings/99999999')->getStatusCode(), 'destroy');
        $this->assertNotEquals(500, $this->postJson('/sales-meetings/99999999/note', ['notes' => 'x'])->getStatusCode(), 'note');
        $this->assertNotEquals(500, $this->post('/sales-meetings/99999999/add-product', ['products' => [1]])->getStatusCode(), 'add product');
        $this->assertNotEquals(500, $this->delete('/sales-meetings/99999999/remove-product/1')->getStatusCode(), 'remove product');
    }

    public function test_cannot_edit_update_note_or_delete_other_tenants_meeting()
    {
        [, , $m] = $this->foreignTenant();
        $this->actingAs($this->company);

        $this->get('/sales-meetings/' . $m->id . '/edit')->assertDontSee('FOREIGNMEETING');
        $this->put('/sales-meetings/' . $m->id, ['title' => 'HIJACKED', 'lead_id' => $m->lead_id, 'meeting_type_id' => $this->type->id, 'datetime' => '2026-01-01']);
        $this->postJson('/sales-meetings/' . $m->id . '/note', ['notes' => 'HIJACKED']);
        $this->delete('/sales-meetings/' . $m->id);

        $fresh = SalesMeeting::find($m->id);
        $this->assertNotNull($fresh, 'foreign meeting must survive delete');
        $this->assertEquals('FOREIGNMEETING', $fresh->title);
        $this->assertNotEquals('HIJACKED', $fresh->notes);
    }

    public function test_store_rejects_lead_from_another_tenant()
    {
        [, $foreignLead] = $this->foreignTenant();
        $this->actingAs($this->company)->post('/sales-meetings', [
            'title' => 'AttachedToForeign', 'lead_id' => $foreignLead->id,
            'meeting_type_id' => $this->type->id, 'datetime' => '2026-09-20T10:00',
        ]);
        $this->assertNull(SalesMeeting::where('title', 'AttachedToForeign')->first());
    }

    public function test_store_rejects_deal_that_does_not_belong_to_the_lead()
    {
        $a = $this->makeLead();
        $b = $this->makeLead();
        $dealOfB = $this->makeDeal($b);

        $this->actingAs($this->company)->post('/sales-meetings', [
            'title' => 'MismatchedDeal', 'lead_id' => $a->id, 'deal_id' => $dealOfB->id,
            'meeting_type_id' => $this->type->id, 'datetime' => '2026-09-20T10:00',
        ]);
        $this->assertNull(SalesMeeting::where('title', 'MismatchedDeal')->first());
    }

    public function test_store_keeps_the_time()
    {
        $lead = $this->makeLead();
        $this->actingAs($this->company)->post('/sales-meetings', [
            'title' => 'StoredWithTime', 'lead_id' => $lead->id, 'deal_id' => '',
            'meeting_type_id' => $this->type->id, 'datetime' => '2026-09-20T15:45',
            // exactly what the form submits when the optional fields are left blank
            'duration' => '', 'link' => '',
        ])->assertSessionHas('success');

        $m = SalesMeeting::where('title', 'StoredWithTime')->first();
        $this->assertNotNull($m);
        $this->assertEquals('2026-09-20 15:45', $m->datetime->format('Y-m-d H:i'));
        $this->assertNull($m->deal_id);
    }

    public function test_attachment_download_is_tenant_scoped()
    {
        [, , $foreign] = $this->foreignTenant();
        $foreign->attachment = 'sm_test_foreign.pdf';
        $foreign->save();
        $mine = $this->makeMeeting(['lead_id' => $this->makeLead()->id, 'attachment' => 'sm_test_mine.pdf']);
        \Storage::disk('local')->put('sales_meeting_files/sm_test_foreign.pdf', 'FOREIGNFILE');
        \Storage::disk('local')->put('sales_meeting_files/sm_test_mine.pdf', 'MINEFILE');

        try {
            $this->actingAs($this->company);
            $this->get('/sales-meetings/' . $mine->id . '/attachment')->assertStatus(200);
            $this->assertNotEquals(200, $this->get('/sales-meetings/' . $foreign->id . '/attachment')->getStatusCode());
            $this->assertNotEquals(500, $this->get('/sales-meetings/99999999/attachment')->getStatusCode());
        } finally {
            \Storage::disk('local')->delete(['sales_meeting_files/sm_test_foreign.pdf', 'sales_meeting_files/sm_test_mine.pdf']);
        }
    }

    public function test_show_page_renders_the_product_modal_inside_the_layout()
    {
        $m = $this->makeMeeting(['title' => 'ShowMeeting', 'lead_id' => $this->makeLead()->id]);
        $html = $this->actingAs($this->company)->get('/sales-meetings/' . $m->id)->assertStatus(200)->getContent();

        $this->assertStringContainsString('addProductModal', $html);
        $this->assertLessThan(
            strpos($html, 'addProductModal'),
            stripos($html, '<html'),
            'the Add Product modal was printed before <html>'
        );
    }

    // ---------- D. sales employee visibility ----------

    public function test_sales_employee_only_sees_meetings_of_assigned_leads()
    {
        $emp = $this->makeUser('Employee', 'Sales Employee', $this->company->id, ['manage lead']);
        $mine = $this->makeLead(['assign' => [$emp->id]]);
        $notMine = $this->makeLead();
        $this->makeMeeting(['title' => 'EmpMineMeeting', 'lead_id' => $mine->id]);
        $other = $this->makeMeeting(['title' => 'EmpHiddenMeeting', 'lead_id' => $notMine->id]);

        $this->actingAs($emp)->get('/sales-meetings')
            ->assertStatus(200)->assertSee('EmpMineMeeting')->assertDontSee('EmpHiddenMeeting');

        $this->actingAs($emp)->get('/sales-meetings/' . $other->id)->assertDontSee('EmpHiddenMeeting');
    }

    // ---- Assigned Staff column ---------------------------------------------------

    public function test_assigned_staff_names_the_assigned_user_not_leads_user_id()
    {
        $owner = $this->makeUser('Sales Employee', 'smAssignedOwner', $this->company->id, ['manage lead']);
        // user_id points at the company, the pivot at the sales employee: the column
        // must follow the pivot, exactly like the lead page and the leads list.
        $lead = $this->makeLead(['name' => 'SmPivotLead', 'user_id' => $this->company->id, 'assign' => [$owner->id]]);
        $this->makeMeeting(['title' => 'SmPivotMeeting', 'lead_id' => $lead->id]);

        $r = $this->actingAs($this->company)->get('/sales-meetings');
        $r->assertStatus(200);
        $r->assertSee('SmPivotMeeting');
        $r->assertSee('<span dir="auto">' . $owner->name . '</span>', false);
        $r->assertDontSee('<span dir="auto">' . $this->company->name . '</span>', false);
    }

    public function test_assigned_staff_marks_a_lead_with_nobody_assigned()
    {
        $lead = $this->makeLead(['name' => 'SmNobodyLead']);
        $this->makeMeeting(['title' => 'SmNobodyMeeting', 'lead_id' => $lead->id]);

        $r = $this->actingAs($this->company)->get('/sales-meetings');
        $r->assertStatus(200);
        $r->assertSee('SmNobodyMeeting');
        $r->assertSee('<span class="text-muted">-</span>', false);
    }

    public function test_assigned_staff_lists_every_assigned_user()
    {
        $a = $this->makeUser('Sales Employee', 'smOwnerA', $this->company->id, ['manage lead']);
        $b = $this->makeUser('Sales Employee', 'smOwnerB', $this->company->id, ['manage lead']);
        $lead = $this->makeLead(['name' => 'SmTwoOwnerLead', 'assign' => [$a->id, $b->id]]);
        $this->makeMeeting(['title' => 'SmTwoOwnerMeeting', 'lead_id' => $lead->id]);

        $r = $this->actingAs($this->company)->get('/sales-meetings');
        $r->assertStatus(200);
        $r->assertSee('<span dir="auto">' . $a->name . '</span>', false);
        $r->assertSee('<span dir="auto">' . $b->name . '</span>', false);
    }

    // ---- lead picker search ------------------------------------------------------

    public function test_lead_search_returns_name_and_phone_as_separate_fields()
    {
        $this->makeLead(['name' => 'SmAhmed Zakaria', 'phone' => '201002633348']);

        $r = $this->actingAs($this->company)->get('/sales-meetings-leads-search?q=SmAhmed');
        $r->assertStatus(200);
        $body = $r->json();
        $this->assertNotEmpty($body);
        $this->assertSame('SmAhmed Zakaria', $body[0]['name']);
        $this->assertSame('201002633348', $body[0]['phone']);
    }

    public function test_lead_search_matches_a_phone_number()
    {
        $this->makeLead(['name' => 'SmPhoneLead', 'phone' => '249960694641']);
        $this->makeLead(['name' => 'SmOtherLead', 'phone' => '201111111111']);

        $r = $this->actingAs($this->company)->get('/sales-meetings-leads-search?q=249960');
        $r->assertStatus(200);
        $names = array_column($r->json(), 'name');
        $this->assertContains('SmPhoneLead', $names);
        $this->assertNotContains('SmOtherLead', $names);
    }

    public function test_lead_search_does_not_leak_another_tenant()
    {
        list($other, $foreignLead, $foreignMeeting) = $this->foreignTenant();

        $r = $this->actingAs($this->company)->get('/sales-meetings-leads-search?q=FOREIGNLEAD');
        $r->assertStatus(200);
        $this->assertSame([], $r->json());
    }
}
