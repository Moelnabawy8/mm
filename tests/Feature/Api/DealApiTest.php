<?php

namespace Tests\Feature\Api;

use App\Models\Deal;
use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\Pipeline;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * POST /api/v1/deals had no duplicate protection: between 2026-05-16 and
 * 2026-10-08 a client re-posting the same "<lead name> - Deal" wrote over a
 * hundred rows a minute, 28k rows in all, every one of them with an empty
 * added_by.
 */
class DealApiTest extends TestCase
{
    use DatabaseTransactions;

    protected $company;
    protected $employee;
    protected $pipeline;
    protected $leadStage;
    protected $dealStage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->makeUser('company', 1);
        $this->employee = $this->makeUser('Sales Employee', $this->company->id);

        $this->pipeline = Pipeline::create(['name' => 'Main', 'created_by' => $this->company->id]);
        $this->leadStage = LeadStage::create(['name' => 'New', 'pipeline_id' => $this->pipeline->id, 'created_by' => $this->company->id, 'order' => 0]);
        $this->dealStage = Stage::create(['name' => 'Open', 'pipeline_id' => $this->pipeline->id, 'created_by' => $this->company->id, 'order' => 0]);
    }

    protected function makeUser($type, $createdBy, array $perms = ['create deal', 'edit deal', 'delete deal'])
    {
        static $n = 0;
        $n++;
        $u = new User();
        $u->name = 'api' . $n;
        $u->email = 'api' . $n . '_' . uniqid() . '@test.local';
        $u->password = bcrypt('secret');
        $u->type = $type;
        $u->created_by = $createdBy;
        $u->lang = 'en';
        $u->email_verified_at = now();
        $u->save();

        $role = \Spatie\Permission\Models\Role::firstOrCreate(
            ['name' => 'apiRole' . $n, 'guard_name' => 'web'],
            ['created_by' => $createdBy ?: 1]
        );
        foreach ($perms as $p) {
            if ($perm = \Spatie\Permission\Models\Permission::where('name', $p)->first()) {
                $role->givePermissionTo($perm);
            }
        }
        $u->assignRole($role);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        return $u->fresh();
    }

    protected function makeLead($createdBy = null)
    {
        $lead = new Lead();
        $lead->name = 'ApiLead ' . uniqid();
        $lead->email = 'al' . uniqid() . '@test.local';
        $lead->subject = 'test';
        $lead->user_id = $this->company->id;
        $lead->pipeline_id = $this->pipeline->id;
        $lead->stage_id = $this->leadStage->id;
        $lead->created_by = $createdBy ?? $this->company->id;
        $lead->date = date('Y-m-d');
        $lead->save();

        return $lead->fresh();
    }

    protected function post_deal($lead, $name, $extra = [])
    {
        return $this->postJson('/api/v1/deals', array_merge([
            'name' => $name,
            'lead_id' => $lead->id,
            'stage_id' => $this->dealStage->id,
        ], $extra));
    }

    protected function dealCount($lead, $name)
    {
        return Deal::where('lead_id', $lead->id)->where('name', $name)->count();
    }

    // ---- the duplicate flood -----------------------------------------------------

    public function test_posting_the_same_deal_again_does_not_create_a_second_row()
    {
        Sanctum::actingAs($this->employee, ['*']);
        $lead = $this->makeLead();
        $name = $lead->name . ' - Deal';

        $first = $this->post_deal($lead, $name);
        $first->assertStatus(200);

        for ($i = 0; $i < 5; $i++) {
            $this->post_deal($lead, $name)->assertStatus(200);
        }

        $this->assertSame(1, $this->dealCount($lead, $name), 'the endpoint created duplicates again');
    }

    public function test_a_repeat_returns_the_original_deal_and_says_it_is_a_duplicate()
    {
        Sanctum::actingAs($this->employee, ['*']);
        $lead = $this->makeLead();
        $name = $lead->name . ' - Deal';

        $firstId = $this->post_deal($lead, $name)->json('data.deal.id');
        $repeat = $this->post_deal($lead, $name);

        $repeat->assertStatus(200);
        $this->assertTrue($repeat->json('data.duplicate'));
        $this->assertSame($firstId, $repeat->json('data.deal.id'));
    }

    public function test_a_different_name_on_the_same_lead_is_still_allowed()
    {
        Sanctum::actingAs($this->employee, ['*']);
        $lead = $this->makeLead();

        $this->post_deal($lead, 'First Deal')->assertStatus(200);
        $this->post_deal($lead, 'Second Deal')->assertStatus(200);

        $this->assertSame(2, Deal::where('lead_id', $lead->id)->count());
    }

    public function test_the_same_name_on_a_different_lead_is_still_allowed()
    {
        Sanctum::actingAs($this->employee, ['*']);
        $a = $this->makeLead();
        $b = $this->makeLead();

        $this->post_deal($a, 'Shared Name')->assertStatus(200);
        $this->post_deal($b, 'Shared Name')->assertStatus(200);

        $this->assertSame(1, $this->dealCount($a, 'Shared Name'));
        $this->assertSame(1, $this->dealCount($b, 'Shared Name'));
    }

    // ---- who created the row -----------------------------------------------------

    public function test_the_api_records_which_employee_created_the_deal()
    {
        Sanctum::actingAs($this->employee, ['*']);
        $lead = $this->makeLead();

        $id = $this->post_deal($lead, 'Traceable Deal')->json('data.deal.id');
        $deal = Deal::find($id);

        // the lead page prints this column; every API row used to show "-"
        $this->assertSame($this->employee->id, (int) $deal->added_by);
        $this->assertSame($this->company->id, (int) $deal->created_by);
    }

    // ---- tenant scoping ----------------------------------------------------------

    public function test_a_lead_belonging_to_another_company_is_rejected()
    {
        $other = $this->makeUser('company', 1);
        $foreignLead = $this->makeLead($other->id);

        Sanctum::actingAs($this->employee, ['*']);
        $this->post_deal($foreignLead, 'Cross Tenant Deal')->assertStatus(422);

        $this->assertSame(0, Deal::where('lead_id', $foreignLead->id)->count());
    }

    public function test_an_existing_deal_of_another_company_does_not_block_this_one()
    {
        $other = $this->makeUser('company', 1);
        $lead = $this->makeLead();

        // same lead_id and name, but owned by someone else: the guard is scoped
        // to the tenant, so it must not hand another company's row back
        $foreign = new Deal();
        $foreign->name = 'Scoped Deal';
        $foreign->lead_id = $lead->id;
        $foreign->pipeline_id = $this->pipeline->id;
        $foreign->stage_id = $this->dealStage->id;
        $foreign->created_by = $other->id;
        $foreign->save();

        Sanctum::actingAs($this->employee, ['*']);
        $id = $this->post_deal($lead, 'Scoped Deal')->json('data.deal.id');

        $this->assertNotSame($foreign->id, $id);
        $this->assertSame($this->company->id, (int) Deal::find($id)->created_by);
    }

    // ---- the lock is released ----------------------------------------------------

    public function test_the_named_lock_is_not_left_held_after_the_request()
    {
        Sanctum::actingAs($this->employee, ['*']);
        $lead = $this->makeLead();
        $this->post_deal($lead, 'Lock Deal')->assertStatus(200);

        $lockName = 'deal_create_' . $this->company->id . '_' . $lead->id;
        $used = \DB::selectOne('SELECT IS_USED_LOCK(?) AS holder', [$lockName]);

        $this->assertNull($used->holder, 'the lock was still held after the response');
    }

    // ---- the role finally means something -----------------------------------------

    public function test_an_account_without_the_permission_cannot_create_a_deal()
    {
        $powerless = $this->makeUser('Sales Employee', $this->company->id, []);
        $lead = $this->makeLead();

        Sanctum::actingAs($powerless, ['*']);
        $this->post_deal($lead, 'Forbidden Deal')->assertStatus(403);

        $this->assertSame(0, Deal::where('lead_id', $lead->id)->count());
    }

    public function test_a_refused_create_is_written_to_the_log()
    {
        $file = storage_path('logs/deal-api-' . date('Y-m-d') . '.log');
        if (is_file($file)) {
            unlink($file);
        }

        $powerless = $this->makeUser('Sales Employee', $this->company->id, []);
        $lead = $this->makeLead();

        Sanctum::actingAs($powerless, ['*']);
        $this->post_deal($lead, 'Refused Deal')->assertStatus(403);

        $this->assertStringContainsString('refused', file_get_contents($file));
        $this->assertStringContainsString('user=' . $powerless->id, file_get_contents($file));
        unlink($file);
    }

    public function test_an_account_without_the_permission_cannot_change_a_deal()
    {
        Sanctum::actingAs($this->employee, ['*']);
        $lead = $this->makeLead();
        $id = $this->post_deal($lead, 'Guarded Deal')->json('data.deal.id');

        $powerless = $this->makeUser('Sales Employee', $this->company->id, []);
        Sanctum::actingAs($powerless, ['*']);

        $this->putJson('/api/v1/deals/' . $id, [
            'name' => 'Renamed By Nobody',
            'stage_id' => $this->dealStage->id,
        ])->assertStatus(403);

        $this->assertSame('Guarded Deal', Deal::find($id)->name);
    }

    public function test_an_account_without_the_permission_cannot_delete_a_deal()
    {
        Sanctum::actingAs($this->employee, ['*']);
        $lead = $this->makeLead();
        $id = $this->post_deal($lead, 'Undeletable Deal')->json('data.deal.id');

        $powerless = $this->makeUser('Sales Employee', $this->company->id, []);
        Sanctum::actingAs($powerless, ['*']);

        $this->deleteJson('/api/v1/deals/' . $id)->assertStatus(403);
        $this->assertNotNull(Deal::find($id));
    }

    public function test_reading_deals_is_left_alone()
    {
        $powerless = $this->makeUser('Sales Employee', $this->company->id, []);

        Sanctum::actingAs($powerless, ['*']);
        $this->getJson('/api/v1/deals')->assertStatus(200);
    }

    // ---- naming the client behind the floods --------------------------------------

    protected function dealApiLog()
    {
        $file = storage_path('logs/deal-api-' . date('Y-m-d') . '.log');

        return is_file($file) ? file_get_contents($file) : '';
    }

    protected function forgetDealApiLog()
    {
        $file = storage_path('logs/deal-api-' . date('Y-m-d') . '.log');
        if (is_file($file)) {
            unlink($file);
        }
    }

    public function test_a_created_deal_is_logged_with_its_caller()
    {
        $this->forgetDealApiLog();
        Sanctum::actingAs($this->employee, ['*']);
        $lead = $this->makeLead();

        $id = $this->post_deal($lead, 'Logged Deal')->json('data.deal.id');

        $log = $this->dealApiLog();
        $this->assertStringContainsString('created', $log);
        $this->assertStringContainsString('user=' . $this->employee->id, $log);
        $this->assertStringContainsString('deal=' . $id, $log);
        $this->assertStringContainsString('lead=' . $lead->id, $log);
        $this->forgetDealApiLog();
    }

    public function test_a_blocked_duplicate_is_logged_too()
    {
        $this->forgetDealApiLog();
        Sanctum::actingAs($this->employee, ['*']);
        $lead = $this->makeLead();
        $name = $lead->name . ' - Deal';

        $this->post_deal($lead, $name);
        $this->post_deal($lead, $name);

        $log = $this->dealApiLog();
        $this->assertSame(1, substr_count($log, 'created  '));
        $this->assertSame(1, substr_count($log, 'duplicate'));
        $this->forgetDealApiLog();
    }

    public function test_the_log_records_where_the_request_came_from()
    {
        $this->forgetDealApiLog();
        Sanctum::actingAs($this->employee, ['*']);
        $lead = $this->makeLead();

        $this->withHeaders([
            'User-Agent' => 'SomeAutomation/2.1',
            'X-Forwarded-For' => '203.0.113.9',
        ])->postJson('/api/v1/deals', [
            'name' => 'Traced Deal',
            'lead_id' => $lead->id,
            'stage_id' => $this->dealStage->id,
        ])->assertStatus(200);

        $log = $this->dealApiLog();
        $this->assertStringContainsString('agent=SomeAutomation/2.1', $log);
        $this->assertStringContainsString('fwd=203.0.113.9', $log);
        $this->assertStringContainsString('ip=', $log);
        $this->forgetDealApiLog();
    }

    public function test_one_line_per_attempt_and_nothing_more()
    {
        $this->forgetDealApiLog();
        Sanctum::actingAs($this->employee, ['*']);
        $lead = $this->makeLead();

        for ($i = 0; $i < 4; $i++) {
            $this->post_deal($lead, 'Counted Deal');
        }

        $lines = array_filter(explode("\n", $this->dealApiLog()));
        $this->assertCount(4, $lines);
        $this->forgetDealApiLog();
    }

    public function test_a_rejected_request_writes_no_line()
    {
        $this->forgetDealApiLog();
        $other = $this->makeUser('company', 1);
        $foreignLead = $this->makeLead($other->id);

        Sanctum::actingAs($this->employee, ['*']);
        $this->post_deal($foreignLead, 'Rejected Deal')->assertStatus(422);

        // validation never reaches the write, so there is nothing to record
        $this->assertSame('', $this->dealApiLog());
        $this->forgetDealApiLog();
    }
}