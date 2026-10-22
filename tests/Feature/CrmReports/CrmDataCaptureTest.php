<?php

namespace Tests\Feature\CrmReports;

use App\Models\DealPurchase;
use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\Pipeline;
use App\Models\SalesMeeting;
use App\Models\User;
use App\Models\UserLead;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The data the CRM Reports depend on actually gets recorded: the employee on
 * meetings and purchases, meeting status, and stage moves.
 *     php artisan test tests/Feature/CrmReports/CrmDataCaptureTest.php
 */
class CrmDataCaptureTest extends TestCase
{
    use DatabaseTransactions;

    protected $company;
    protected $alice;
    protected $bob;
    protected $pipeline;
    protected $stageA;
    protected $stageB;

    protected function setUp(): void
    {
        parent::setUp();
        app()['cache']->forget('spatie.permission.cache');

        $this->company = $this->makeUser('company', ['manage lead', 'view all leads', 'delete lead', 'edit lead', 'move lead']);
        $this->company->created_by = 1;
        $this->company->save();
        $this->alice = $this->makeUser('Sales Employee', ['manage lead'], $this->company->id);
        $this->bob = $this->makeUser('Sales Employee', ['manage lead'], $this->company->id);

        $this->pipeline = Pipeline::create(['name' => 'Main', 'created_by' => $this->company->id]);
        $this->stageA = LeadStage::create(['name' => 'New', 'pipeline_id' => $this->pipeline->id, 'created_by' => $this->company->id, 'order' => 0]);
        $this->stageB = LeadStage::create(['name' => 'Hot', 'pipeline_id' => $this->pipeline->id, 'created_by' => $this->company->id, 'order' => 1]);
    }

    protected function makeUser($type, array $perms, $createdBy = 0)
    {
        static $n = 0;
        $n++;
        $u = new User();
        $u->name = $type . $n;
        $u->email = 'cap' . $n . '_' . uniqid() . '@test.local';
        $u->password = bcrypt('secret');
        $u->type = $type;
        $u->created_by = $createdBy;
        $u->lang = 'en';
        $u->email_verified_at = now();
        $u->save();
        $role = Role::firstOrCreate(['name' => 'caprole' . $n . uniqid(), 'guard_name' => 'web'], ['created_by' => $createdBy ?: 1]);
        foreach ($perms as $p) {
            if ($perm = Permission::where('name', $p)->first()) {
                $role->givePermissionTo($perm);
            }
        }
        $u->assignRole($role);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        return $u->fresh();
    }

    protected function lead(User $owner, $companyId = null)
    {
        $lead = new Lead();
        $lead->name = 'Lead ' . uniqid();
        $lead->email = 'l' . uniqid() . '@test.local';
        $lead->subject = 's';
        $lead->user_id = $owner->id;
        $lead->pipeline_id = $this->pipeline->id;
        $lead->stage_id = $this->stageA->id;
        $lead->created_by = $companyId ?? $this->company->id;
        $lead->date = date('Y-m-d');
        $lead->cost = 0;
        $lead->save();
        UserLead::create(['user_id' => $owner->id, 'lead_id' => $lead->id]);

        return $lead->fresh();
    }

    // ------------------------------------------------------------ call logging (removed)

    public function test_call_logging_is_gone_and_the_lead_page_still_opens()
    {
        $lead = $this->lead($this->alice);
        $this->actingAs($this->alice)->post("/leads/{$lead->id}/calls", ['outcome' => 'answered'])->assertNotFound();
        $this->actingAs($this->alice)->get("/leads/{$lead->id}")->assertOk()->assertDontSee('Log a call');
    }

    // ------------------------------------------------------------ hooks

    public function test_meeting_records_the_employee_and_status()
    {
        $lead = $this->lead($this->alice);
        $this->actingAs($this->alice);
        request()->merge(['meeting_status' => 'done']);

        $m = SalesMeeting::create(['title' => 'Demo', 'lead_id' => $lead->id, 'date' => date('Y-m-d'), 'created_by' => $this->company->id]);
        $m->refresh();
        $this->assertSame($this->alice->id, (int) $m->employee_id);
        $this->assertSame('done', $m->status);

        request()->merge(['meeting_status' => 'nonsense']);
        $m->title = 'Demo 2';
        $m->save();
        $this->assertSame('done', $m->fresh()->status, 'an unknown status is ignored');
    }

    public function test_purchase_records_the_employee()
    {
        $lead = $this->lead($this->alice);
        $dealId = DB::table('deals')->insertGetId(['name' => 'D', 'lead_id' => $lead->id, 'created_by' => $this->company->id,
            'pipeline_id' => $this->pipeline->id, 'stage_id' => 0, 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($this->bob);

        $p = DealPurchase::create(['deal_id' => $dealId, 'price' => 100, 'currency' => 'EGP', 'payment_method' => 'cash',
            'payment_type' => 'one-time', 'paid_amount' => 100, 'status' => 'complete', 'purchase_date' => date('Y-m-d'),
            'created_by' => $this->company->id]);

        $this->assertSame($this->bob->id, (int) $p->fresh()->employee_id);
    }

    public function test_stage_change_outside_kanban_is_logged_once()
    {
        $lead = $this->lead($this->alice);
        $this->actingAs($this->alice);

        $lead->stage_id = $this->stageB->id;
        $lead->save();
        $lead->name = 'renamed';
        $lead->save();   // no stage change: no log

        $this->assertSame(1, DB::table('lead_activity_logs')->where('lead_id', $lead->id)->where('log_type', 'Move')->count());
    }

    public function test_kanban_move_is_not_logged_twice()
    {
        $lead = $this->lead($this->alice);
        $this->actingAs($this->company)->postJson('/leads/order', [
            'lead_id' => $lead->id, 'stage_id' => $this->stageB->id, 'order' => [$lead->id],
        ])->assertOk();

        $this->assertSame($this->stageB->id, (int) $lead->fresh()->stage_id);
        $this->assertSame(1, DB::table('lead_activity_logs')->where('lead_id', $lead->id)->where('log_type', 'Move')->count());
    }
}
