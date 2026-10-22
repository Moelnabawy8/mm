<?php

namespace Tests\Feature\DealPurchases;

use App\Models\Deal;
use App\Models\DealPurchase;
use App\Models\DealPurchaseAttachment;
use App\Models\Installment;
use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\Pipeline;
use App\Models\SalesMeetingPaymentMethod;
use App\Models\Stage;
use App\Models\User;
use App\Models\UserLead;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DealPurchasePageTest extends TestCase
{
    use DatabaseTransactions;

    protected $company;
    protected $pipeline;
    protected $leadStage;
    protected $dealStage;
    protected $method;

    protected function setUp(): void
    {
        parent::setUp();
        app()['cache']->forget('spatie.permission.cache');

        $this->company = $this->makeUser('company', 'dpCompany', 1, [
            'manage deal', 'view deal', 'create deal', 'edit deal', 'delete deal',
        ]);

        $this->pipeline = Pipeline::create(['name' => 'Main', 'created_by' => $this->company->id]);
        $this->leadStage = LeadStage::create(['name' => 'New', 'pipeline_id' => $this->pipeline->id, 'created_by' => $this->company->id, 'order' => 0]);
        $this->dealStage = Stage::create(['name' => 'Open', 'pipeline_id' => $this->pipeline->id, 'created_by' => $this->company->id, 'order' => 0]);
        $this->method = SalesMeetingPaymentMethod::create(['name' => 'Cash', 'created_by' => $this->company->id]);
    }

    protected function makeUser($type, $roleName, $createdBy, array $perms = [])
    {
        static $n = 0;
        $n++;
        $u = new User();
        $u->name = $type . $n;
        $u->email = 'dp' . $n . '_' . uniqid() . '@test.local';
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

    protected function makePurchase($attrs = [])
    {
        $deal = $attrs['deal'] ?? $this->makeDeal($this->makeLead());
        $p = new DealPurchase();
        $p->deal_id = $deal->id;
        $p->price = $attrs['price'] ?? 1000;
        $p->currency = $attrs['currency'] ?? 'USD';
        $p->payment_method = $attrs['payment_method'] ?? (string) $this->method->id;
        $p->payment_type = $attrs['payment_type'] ?? 'one-time';
        $p->paid_amount = $attrs['paid_amount'] ?? 1000;
        $p->status = $attrs['status'] ?? 'complete';
        $p->purchase_date = $attrs['purchase_date'] ?? date('Y-m-d');
        $p->created_by = $attrs['created_by'] ?? $this->company->id;
        $p->save();

        return $p->fresh();
    }

    /** Another tenant with its own lead/deal/purchase */
    protected function foreignTenant()
    {
        $other = $this->makeUser('company', 'dpOther', 1, ['manage deal', 'view deal', 'create deal', 'edit deal', 'delete deal']);
        $lead = $this->makeLead(['name' => 'FOREIGNLEAD', 'created_by' => $other->id, 'user_id' => $other->id]);
        $deal = $this->makeDeal($lead, 'FOREIGNDEAL', $other->id);
        $purchase = $this->makePurchase(['deal' => $deal, 'created_by' => $other->id]);

        return [$other, $lead, $deal, $purchase];
    }

    // ---------------------------------------------------------------- index

    public function test_index_loads_for_company()
    {
        $this->makePurchase();
        $r = $this->actingAs($this->company)->get('/deal-purchase');
        $r->assertStatus(200);
        $r->assertSee('Manage Deal Purchase', false);
    }

    public function test_index_denied_without_manage_deal_permission()
    {
        $u = $this->makeUser('employee', 'dpNoPerm', $this->company->id, []);
        $r = $this->actingAs($u)->get('/deal-purchase');
        $r->assertStatus(302);
    }

    public function test_index_totals_cover_all_filtered_rows_not_just_page()
    {
        $this->makePurchase(['price' => 100, 'paid_amount' => 40]);
        $this->makePurchase(['price' => 300, 'paid_amount' => 60]);

        $r = $this->actingAs($this->company)->get('/deal-purchase');
        $r->assertStatus(200);
        $r->assertSee('400.00', false);   // total price
        $r->assertSee('100.00', false);   // total paid
        $r->assertSee('300.00', false);   // remaining
    }

    public function test_index_counts_paid_installments_like_the_detail_page()
    {
        // 1000 total, 400 upfront, one 300 installment already paid, one 300 unpaid.
        // Detail page reports 700 paid / 300 remaining; the list must agree.
        $p = $this->makePurchase(['payment_type' => 'installment', 'price' => 1000, 'paid_amount' => 400]);
        Installment::create([
            'deal_purchase_id' => $p->id, 'amount' => 300, 'date' => '2026-10-01',
            'status' => 'paid', 'created_by' => $this->company->id,
        ]);
        Installment::create([
            'deal_purchase_id' => $p->id, 'amount' => 300, 'date' => '2026-11-01',
            'status' => 'unpaid', 'created_by' => $this->company->id,
        ]);

        $r = $this->actingAs($this->company)->get('/deal-purchase');
        $r->assertStatus(200);
        $r->assertSee('700.00', false);
        $r->assertSee('300.00', false);
        $r->assertDontSee('600.00', false); // the old price - paid_amount answer
    }

    public function test_index_warns_when_totals_mix_currencies()
    {
        $this->makePurchase(['currency' => 'USD', 'price' => 100, 'paid_amount' => 100]);
        $this->makePurchase(['currency' => 'EGP', 'price' => 100, 'paid_amount' => 100]);

        $r = $this->actingAs($this->company)->get('/deal-purchase');
        $r->assertStatus(200);
        $r->assertSee('mixed-currency-warning', false);
    }

    public function test_index_status_filter()
    {
        $this->makePurchase(['status' => 'pending', 'deal' => $this->makeDeal($this->makeLead(['name' => 'PENDINGLEAD']))]);
        $this->makePurchase(['status' => 'complete', 'deal' => $this->makeDeal($this->makeLead(['name' => 'COMPLETELEAD']))]);

        $r = $this->actingAs($this->company)->get('/deal-purchase?status=pending');
        $r->assertStatus(200);
        $r->assertSee('PENDINGLEAD', false);
        $r->assertDontSee('COMPLETELEAD', false);
    }

    public function test_index_price_and_date_filters()
    {
        $this->makePurchase(['price' => 50, 'deal' => $this->makeDeal($this->makeLead(['name' => 'CHEAPLEAD'])), 'purchase_date' => '2026-01-01']);
        $this->makePurchase(['price' => 5000, 'deal' => $this->makeDeal($this->makeLead(['name' => 'RICHLEAD'])), 'purchase_date' => '2026-08-01']);

        $r = $this->actingAs($this->company)->get('/deal-purchase?min_price=1000');
        $r->assertStatus(200);
        $r->assertSee('RICHLEAD', false);
        $r->assertDontSee('CHEAPLEAD', false);

        $r = $this->actingAs($this->company)->get('/deal-purchase?date_from=2026-07-01&date_to=2026-09-01');
        $r->assertStatus(200);
        $r->assertSee('RICHLEAD', false);
        $r->assertDontSee('CHEAPLEAD', false);
    }

    public function test_index_ajax_returns_table_partial()
    {
        $this->makePurchase();
        $r = $this->actingAs($this->company)
            ->get('/deal-purchase', ['X-Requested-With' => 'XMLHttpRequest']);
        $r->assertStatus(200);
        $r->assertSee('filter-metadata', false);
        $r->assertDontSee('advancedFilterModal', false);
    }

    public function test_index_does_not_leak_other_tenant_rows()
    {
        [$other, $lead, $deal, $purchase] = $this->foreignTenant();
        $this->makePurchase(['deal' => $this->makeDeal($this->makeLead(['name' => 'MYLEAD']))]);

        $r = $this->actingAs($this->company)->get('/deal-purchase');
        $r->assertStatus(200);
        $r->assertSee('MYLEAD', false);
        $r->assertDontSee('FOREIGNDEAL', false);
    }

    public function test_index_survives_purchase_whose_deal_was_removed()
    {
        $purchase = $this->makePurchase();
        // orphan the purchase (deal row gone, FK cascade disabled by raw delete on child first)
        \DB::table('deals')->where('id', $purchase->deal_id)->delete();

        $r = $this->actingAs($this->company)->get('/deal-purchase');
        $r->assertStatus(200);
    }

    public function test_index_sales_employee_only_sees_assigned_leads()
    {
        $emp = $this->makeUser('employee', 'Sales Employee', $this->company->id, ['manage deal', 'view deal']);
        $mine = $this->makeLead(['name' => 'ASSIGNEDLEAD', 'assign' => [$emp->id]]);
        $theirs = $this->makeLead(['name' => 'OTHERSLEAD']);
        $this->makePurchase(['deal' => $this->makeDeal($mine)]);
        $this->makePurchase(['deal' => $this->makeDeal($theirs)]);

        $r = $this->actingAs($emp)->get('/deal-purchase');
        $r->assertStatus(200);
        $r->assertSee('ASSIGNEDLEAD', false);
        $r->assertDontSee('OTHERSLEAD', false);
    }

    // --------------------------------------------------------------- create

    public function test_create_page_loads()
    {
        $this->makeDeal($this->makeLead());
        $r = $this->actingAs($this->company)->get('/deal-purchase/create');
        $r->assertStatus(200);
    }

    public function test_create_page_denied_without_permission()
    {
        $u = $this->makeUser('employee', 'dpNoCreate', $this->company->id, ['manage deal']);
        $r = $this->actingAs($u)->get('/deal-purchase/create');
        $r->assertStatus(302);
    }

    public function test_create_page_only_offers_own_tenant_deals()
    {
        [$other, $lead, $deal, $purchase] = $this->foreignTenant();
        $freeForeign = $this->makeDeal($lead, 'FOREIGNFREEDEAL', $other->id);
        $this->makeDeal($this->makeLead(), 'MYFREEDEAL');

        $r = $this->actingAs($this->company)->get('/deal-purchase/create');
        $r->assertStatus(200);
        $r->assertSee('MYFREEDEAL', false);
        $r->assertDontSee('FOREIGNFREEDEAL', false);
    }

    // -------------------------------------------------- deal picker (perf fix)

    public function test_index_filter_uses_searched_picker_not_a_full_deal_list()
    {
        for ($i = 0; $i < 5; $i++) {
            $this->makeDeal($this->makeLead(), 'BULKDEAL' . $i);
        }
        $this->makePurchase();

        $r = $this->actingAs($this->company)->get('/deal-purchase');
        $r->assertStatus(200);
        $r->assertSee('deal-purchase-deals-search', false);
        // No <select class="select2"> holding every deal of the tenant.
        $r->assertDontSee('BULKDEAL3', false);
    }

    public function test_create_and_edit_use_searched_picker()
    {
        $this->makeDeal($this->makeLead(), 'PICKERDEAL');
        $create = $this->actingAs($this->company)->get('/deal-purchase/create');
        $create->assertStatus(200);
        $create->assertSee('deal-purchase-deals-search', false);

        $p = $this->makePurchase();
        $edit = $this->actingAs($this->company)->get('/deal-purchase/' . $p->id . '/edit');
        $edit->assertStatus(200);
        $edit->assertSee('deal-purchase-deals-search', false);
        // The current deal must stay selected in the hidden input.
        $edit->assertSee('value="' . $p->deal_id . '"', false);
    }

    public function test_deals_search_is_scoped_and_limited()
    {
        [$other, $lead, $deal, $purchase] = $this->foreignTenant();
        $this->makeDeal($lead, 'ZZFOREIGNSEARCH', $other->id);
        $this->makeDeal($this->makeLead(), 'ZZMINESEARCH');

        $r = $this->actingAs($this->company)->get('/deal-purchase-deals-search?q=ZZ');
        $r->assertStatus(200);
        $names = collect($r->json())->pluck('text');
        $this->assertTrue($names->contains('ZZMINESEARCH'));
        $this->assertFalse($names->contains('ZZFOREIGNSEARCH'));
        $this->assertLessThanOrEqual(25, $names->count());
    }

    public function test_deals_search_free_mode_hides_deals_that_already_have_a_purchase()
    {
        $taken = $this->makeDeal($this->makeLead(), 'QQTAKENDEAL');
        $this->makePurchase(['deal' => $taken]);
        $this->makeDeal($this->makeLead(), 'QQFREEDEAL');

        $r = $this->actingAs($this->company)->get('/deal-purchase-deals-search?q=QQ&mode=free');
        $names = collect($r->json())->pluck('text');
        $this->assertTrue($names->contains('QQFREEDEAL'));
        $this->assertFalse($names->contains('QQTAKENDEAL'));

        // ...but the deal being edited stays selectable.
        $r2 = $this->actingAs($this->company)->get('/deal-purchase-deals-search?q=QQ&mode=free&except=' . $taken->id);
        $this->assertTrue(collect($r2->json())->pluck('text')->contains('QQTAKENDEAL'));
    }

    // ---------------------------------------------------------------- store

    public function test_store_one_time_forces_paid_amount_to_price()
    {
        $deal = $this->makeDeal($this->makeLead());
        $r = $this->actingAs($this->company)->post('/deal-purchase', [
            'deal_id' => $deal->id,
            'price' => 1500,
            'currency' => 'USD',
            'payment_method' => (string) $this->method->id,
            'payment_type' => 'one-time',
            'paid_amount' => 10,
            'purchase_date' => '2026-09-01',
            'status' => 'complete',
        ]);
        $r->assertRedirect(route('deal-purchase.index'));

        $p = DealPurchase::where('deal_id', $deal->id)->first();
        $this->assertNotNull($p);
        $this->assertEquals(1500, (float) $p->paid_amount);
    }

    public function test_store_with_installments()
    {
        $deal = $this->makeDeal($this->makeLead());
        $data = json_encode([
            ['amount' => 300, 'date' => '2026-10-01', 'description' => 'i1', 'status' => 'unpaid'],
            ['amount' => 300, 'date' => '2026-11-01', 'description' => 'i2', 'status' => 'unpaid'],
        ]);

        $r = $this->actingAs($this->company)->post('/deal-purchase', [
            'deal_id' => $deal->id,
            'price' => 1000,
            'currency' => 'USD',
            'payment_method' => (string) $this->method->id,
            'payment_type' => 'installment',
            'paid_amount' => 400,
            'purchase_date' => '2026-09-01',
            'status' => 'pending',
            'installments_data' => $data,
        ]);
        $r->assertRedirect(route('deal-purchase.index'));

        $p = DealPurchase::where('deal_id', $deal->id)->first();
        $this->assertEquals(400, (float) $p->paid_amount);
        $this->assertCount(2, $p->installments);
    }

    public function test_store_clamps_paid_amount_when_it_overshoots_price()
    {
        $deal = $this->makeDeal($this->makeLead());
        $data = json_encode([['amount' => 900, 'date' => '2026-10-01']]);

        $this->actingAs($this->company)->post('/deal-purchase', [
            'deal_id' => $deal->id,
            'price' => 1000,
            'currency' => 'USD',
            'payment_method' => (string) $this->method->id,
            'payment_type' => 'installment',
            'paid_amount' => 800,
            'purchase_date' => '2026-09-01',
            'status' => 'pending',
            'installments_data' => $data,
        ]);

        $p = DealPurchase::where('deal_id', $deal->id)->first();
        $this->assertEquals(100, (float) $p->paid_amount);
    }

    public function test_store_rejects_duplicate_deal()
    {
        $purchase = $this->makePurchase();
        $r = $this->actingAs($this->company)->post('/deal-purchase', [
            'deal_id' => $purchase->deal_id,
            'price' => 100,
            'currency' => 'USD',
            'payment_method' => (string) $this->method->id,
            'payment_type' => 'one-time',
            'paid_amount' => 100,
            'purchase_date' => '2026-09-01',
            'status' => 'complete',
        ]);
        $r->assertSessionHas('error');
        $this->assertEquals(1, DealPurchase::where('deal_id', $purchase->deal_id)->count());
    }

    public function test_store_rejects_negative_price()
    {
        $deal = $this->makeDeal($this->makeLead());
        $r = $this->actingAs($this->company)->post('/deal-purchase', [
            'deal_id' => $deal->id,
            'price' => -500,
            'currency' => 'USD',
            'payment_method' => (string) $this->method->id,
            'payment_type' => 'one-time',
            'paid_amount' => -500,
            'purchase_date' => '2026-09-01',
            'status' => 'complete',
        ]);
        $r->assertSessionHas('error');
        $this->assertNull(DealPurchase::where('deal_id', $deal->id)->first());
    }

    public function test_store_rejects_paid_amount_greater_than_price_for_installment()
    {
        $deal = $this->makeDeal($this->makeLead());
        $this->actingAs($this->company)->post('/deal-purchase', [
            'deal_id' => $deal->id,
            'price' => 100,
            'currency' => 'USD',
            'payment_method' => (string) $this->method->id,
            'payment_type' => 'installment',
            'paid_amount' => 5000,
            'purchase_date' => '2026-09-01',
            'status' => 'pending',
        ]);

        $p = DealPurchase::where('deal_id', $deal->id)->first();
        $this->assertTrue($p === null || (float) $p->paid_amount <= 100, 'paid_amount must never exceed price');
    }

    public function test_store_rejects_foreign_tenant_deal()
    {
        [$other, $lead, $deal, $purchase] = $this->foreignTenant();
        $freeForeign = $this->makeDeal($lead, 'FOREIGNFREEDEAL2', $other->id);

        $this->actingAs($this->company)->post('/deal-purchase', [
            'deal_id' => $freeForeign->id,
            'price' => 100,
            'currency' => 'USD',
            'payment_method' => (string) $this->method->id,
            'payment_type' => 'one-time',
            'paid_amount' => 100,
            'purchase_date' => '2026-09-01',
            'status' => 'complete',
        ]);

        $this->assertNull(
            DealPurchase::where('deal_id', $freeForeign->id)->first(),
            'must not attach a purchase to another tenant deal'
        );
    }

    public function test_store_rejects_invalid_date()
    {
        $deal = $this->makeDeal($this->makeLead());
        $r = $this->actingAs($this->company)->post('/deal-purchase', [
            'deal_id' => $deal->id,
            'price' => 100,
            'currency' => 'USD',
            'payment_method' => (string) $this->method->id,
            'payment_type' => 'one-time',
            'paid_amount' => 100,
            'purchase_date' => 'not-a-date',
            'status' => 'complete',
        ]);
        $r->assertSessionHas('error');
    }

    // ----------------------------------------------------------------- show

    public function test_show_loads()
    {
        $p = $this->makePurchase();
        $r = $this->actingAs($this->company)->get('/deal-purchase/' . $p->id);
        $r->assertStatus(200);
    }

    public function test_show_blocks_other_tenant()
    {
        [$other, $lead, $deal, $purchase] = $this->foreignTenant();
        $r = $this->actingAs($this->company)->get('/deal-purchase/' . $purchase->id);
        $this->assertNotEquals(200, $r->status(), 'must not expose another tenant purchase');
    }

    public function test_show_missing_id_is_404_not_500()
    {
        $r = $this->actingAs($this->company)->get('/deal-purchase/99999999');
        $this->assertEquals(404, $r->status());
    }

    // ----------------------------------------------------------------- edit

    public function test_edit_loads()
    {
        $p = $this->makePurchase();
        $r = $this->actingAs($this->company)->get('/deal-purchase/' . $p->id . '/edit');
        $r->assertStatus(200);
    }

    public function test_edit_missing_id_is_404_not_500()
    {
        $r = $this->actingAs($this->company)->get('/deal-purchase/99999999/edit');
        $this->assertEquals(404, $r->status());
    }

    public function test_edit_blocks_other_tenant()
    {
        [$other, $lead, $deal, $purchase] = $this->foreignTenant();
        $r = $this->actingAs($this->company)->get('/deal-purchase/' . $purchase->id . '/edit');
        $this->assertNotEquals(200, $r->status(), 'must not expose another tenant purchase form');
    }

    // --------------------------------------------------------------- update

    public function test_update_changes_fields()
    {
        $p = $this->makePurchase(['price' => 100, 'paid_amount' => 100]);
        $r = $this->actingAs($this->company)->put('/deal-purchase/' . $p->id, [
            'deal_id' => $p->deal_id,
            'price' => 900,
            'currency' => 'EGP',
            'payment_method' => (string) $this->method->id,
            'payment_type' => 'one-time',
            'paid_amount' => 900,
            'purchase_date' => '2026-09-05',
            'status' => 'pending',
        ]);
        $r->assertRedirect(route('deal-purchase.index'));

        $p->refresh();
        $this->assertEquals(900, (float) $p->price);
        $this->assertEquals('EGP', $p->currency);
        $this->assertEquals('pending', $p->status);
    }

    public function test_update_to_one_time_drops_installments()
    {
        $p = $this->makePurchase(['payment_type' => 'installment', 'price' => 1000, 'paid_amount' => 0]);
        Installment::create([
            'deal_purchase_id' => $p->id, 'amount' => 500, 'date' => '2026-10-01',
            'status' => 'unpaid', 'created_by' => $this->company->id,
        ]);

        $this->actingAs($this->company)->put('/deal-purchase/' . $p->id, [
            'deal_id' => $p->deal_id,
            'price' => 1000,
            'currency' => 'USD',
            'payment_method' => (string) $this->method->id,
            'payment_type' => 'one-time',
            'paid_amount' => 1000,
            'purchase_date' => '2026-09-05',
            'status' => 'complete',
        ]);

        $this->assertEquals(0, Installment::where('deal_purchase_id', $p->id)->count());
    }

    public function test_update_syncs_installments()
    {
        $p = $this->makePurchase(['payment_type' => 'installment', 'price' => 1000, 'paid_amount' => 0]);
        $keep = Installment::create([
            'deal_purchase_id' => $p->id, 'amount' => 200, 'date' => '2026-10-01',
            'status' => 'unpaid', 'created_by' => $this->company->id,
        ]);
        $drop = Installment::create([
            'deal_purchase_id' => $p->id, 'amount' => 300, 'date' => '2026-11-01',
            'status' => 'unpaid', 'created_by' => $this->company->id,
        ]);

        $this->actingAs($this->company)->put('/deal-purchase/' . $p->id, [
            'deal_id' => $p->deal_id,
            'price' => 1000,
            'currency' => 'USD',
            'payment_method' => (string) $this->method->id,
            'payment_type' => 'installment',
            'paid_amount' => 0,
            'purchase_date' => '2026-09-05',
            'status' => 'pending',
            'installments_data' => json_encode([
                ['id' => $keep->id, 'amount' => 250, 'date' => '2026-10-15', 'status' => 'paid'],
                ['amount' => 400, 'date' => '2026-12-01', 'status' => 'unpaid'],
            ]),
        ]);

        $ids = Installment::where('deal_purchase_id', $p->id)->pluck('amount')->map(fn($a) => (float) $a)->sort()->values()->all();
        $this->assertEquals([250.0, 400.0], $ids);
        $this->assertNull(Installment::find($drop->id));
    }

    public function test_update_cannot_steal_another_tenant_installment()
    {
        [$other, $lead, $deal, $foreignPurchase] = $this->foreignTenant();
        $foreignInst = Installment::create([
            'deal_purchase_id' => $foreignPurchase->id, 'amount' => 999, 'date' => '2026-10-01',
            'status' => 'unpaid', 'created_by' => $other->id,
        ]);

        $p = $this->makePurchase(['payment_type' => 'installment', 'price' => 1000, 'paid_amount' => 0]);
        $this->actingAs($this->company)->put('/deal-purchase/' . $p->id, [
            'deal_id' => $p->deal_id,
            'price' => 1000,
            'currency' => 'USD',
            'payment_method' => (string) $this->method->id,
            'payment_type' => 'installment',
            'paid_amount' => 0,
            'purchase_date' => '2026-09-05',
            'status' => 'pending',
            'installments_data' => json_encode([
                ['id' => $foreignInst->id, 'amount' => 1, 'date' => '2026-10-01', 'status' => 'paid'],
            ]),
        ]);

        $foreignInst->refresh();
        $this->assertEquals(999, (float) $foreignInst->amount, 'foreign installment must be untouched');
    }

    public function test_update_missing_id_is_404_not_500()
    {
        $r = $this->actingAs($this->company)->put('/deal-purchase/99999999', [
            'deal_id' => $this->makeDeal($this->makeLead())->id,
            'price' => 100,
            'currency' => 'USD',
            'payment_method' => (string) $this->method->id,
            'payment_type' => 'one-time',
            'paid_amount' => 100,
            'purchase_date' => '2026-09-05',
            'status' => 'complete',
        ]);
        $this->assertEquals(404, $r->status());
    }

    public function test_update_blocks_other_tenant()
    {
        [$other, $lead, $deal, $purchase] = $this->foreignTenant();
        $this->actingAs($this->company)->put('/deal-purchase/' . $purchase->id, [
            'deal_id' => $purchase->deal_id,
            'price' => 1,
            'currency' => 'USD',
            'payment_method' => (string) $this->method->id,
            'payment_type' => 'one-time',
            'paid_amount' => 1,
            'purchase_date' => '2026-09-05',
            'status' => 'complete',
        ]);

        $purchase->refresh();
        $this->assertNotEquals(1, (float) $purchase->price, 'must not update another tenant purchase');
    }

    // -------------------------------------------------------------- destroy

    public function test_destroy_removes_purchase_and_installments()
    {
        $p = $this->makePurchase(['payment_type' => 'installment']);
        $inst = Installment::create([
            'deal_purchase_id' => $p->id, 'amount' => 100, 'date' => '2026-10-01',
            'status' => 'unpaid', 'created_by' => $this->company->id,
        ]);

        $r = $this->actingAs($this->company)->delete('/deal-purchase/' . $p->id);
        $r->assertRedirect(route('deal-purchase.index'));
        $this->assertNull(DealPurchase::find($p->id));
        $this->assertNull(Installment::find($inst->id));
    }

    public function test_destroy_missing_id_is_404_not_500()
    {
        $r = $this->actingAs($this->company)->delete('/deal-purchase/99999999');
        $this->assertEquals(404, $r->status());
    }

    public function test_destroy_blocks_other_tenant()
    {
        [$other, $lead, $deal, $purchase] = $this->foreignTenant();
        $this->actingAs($this->company)->delete('/deal-purchase/' . $purchase->id);
        $this->assertNotNull(DealPurchase::find($purchase->id), 'must not delete another tenant purchase');
    }

    // ----------------------------------------------------------- attachments

    public function test_attachment_upload_download_and_delete()
    {
        $p = $this->makePurchase();

        $r = $this->actingAs($this->company)->post('/deal-purchase/' . $p->id . '/attachments', [
            'file' => UploadedFile::fake()->create('invoice.pdf', 12, 'application/pdf'),
        ]);
        $r->assertStatus(200);
        $r->assertJson(['is_success' => true]);

        $att = DealPurchaseAttachment::where('deal_purchase_id', $p->id)->first();
        $this->assertNotNull($att);

        $d = $this->actingAs($this->company)->get('/deal-purchase/' . $p->id . '/attachments/' . $att->id . '/download');
        $d->assertStatus(200);

        $del = $this->actingAs($this->company)->delete('/deal-purchase/' . $p->id . '/attachments/' . $att->id);
        $del->assertStatus(200);
        $this->assertNull(DealPurchaseAttachment::find($att->id));
    }

    public function test_attachment_rejects_executable()
    {
        $p = $this->makePurchase();
        $r = $this->actingAs($this->company)->post('/deal-purchase/' . $p->id . '/attachments', [
            'file' => UploadedFile::fake()->create('evil.php', 4, 'application/x-php'),
        ], ['Accept' => 'application/json']);
        $this->assertEquals(422, $r->status());
    }

    public function test_attachment_download_blocks_other_tenant()
    {
        [$other, $lead, $deal, $purchase] = $this->foreignTenant();

        // The file must really exist on disk, otherwise the "file not found"
        // branch hides a missing authorisation check.
        $relative = 'deal_purchase_attachments/' . $purchase->id . '/secret.pdf';
        $absolute = storage_path('app/public/' . $relative);
        @mkdir(dirname($absolute), 0777, true);
        file_put_contents($absolute, '%PDF-1.4 secret');

        $att = DealPurchaseAttachment::create([
            'deal_purchase_id' => $purchase->id,
            'file_name' => 'secret.pdf',
            'file_path' => $relative,
            'file_type' => 'document',
            'mime_type' => 'application/pdf',
            'file_size' => 15,
            'uploaded_by' => $other->id,
        ]);

        try {
            $serve = $this->actingAs($this->company)->get('/deal-purchase/' . $purchase->id . '/attachments/' . $att->id . '/serve');
            $this->assertNotEquals(200, $serve->status(), 'must not serve another tenant attachment');

            $download = $this->actingAs($this->company)->get('/deal-purchase/' . $purchase->id . '/attachments/' . $att->id . '/download');
            $this->assertNotEquals(200, $download->status(), 'must not download another tenant attachment');
        } finally {
            @unlink($absolute);
        }
    }

    public function test_attachment_id_from_another_purchase_is_rejected()
    {
        $a = $this->makePurchase();
        $b = $this->makePurchase();
        $att = DealPurchaseAttachment::create([
            'deal_purchase_id' => $b->id,
            'file_name' => 'x.pdf',
            'file_path' => 'deal_purchase_attachments/' . $b->id . '/x.pdf',
            'file_type' => 'document',
            'mime_type' => 'application/pdf',
            'file_size' => 10,
            'uploaded_by' => $this->company->id,
        ]);

        $r = $this->actingAs($this->company)->get('/deal-purchase/' . $a->id . '/attachments/' . $att->id . '/download');
        $this->assertEquals(404, $r->status());
    }

    // ------------------------------------------------------------ getProducts

    public function test_get_products_returns_json()
    {
        $deal = $this->makeDeal($this->makeLead());
        $r = $this->actingAs($this->company)->get('/deal-purchase/get-products/' . $deal->id);
        $r->assertStatus(200);
        $r->assertHeader('content-type', 'application/json');
    }

    public function test_get_products_blocks_other_tenant_deal()
    {
        [$other, $lead, $deal, $purchase] = $this->foreignTenant();
        $r = $this->actingAs($this->company)->get('/deal-purchase/get-products/' . $deal->id);
        $this->assertNotEquals(200, $r->status(), 'must not read another tenant deal products');
    }

    // ---- Assigned Staff column ---------------------------------------------------

    public function test_assigned_staff_names_the_assigned_user_not_leads_user_id()
    {
        $owner = $this->makeUser('Sales Employee', 'dpAssignedOwner', $this->company->id, ['manage deal']);
        // user_id points at the company, the pivot at the sales employee: the column
        // must follow the pivot, exactly like the lead page and the leads list.
        $lead = $this->makeLead(['name' => 'DpPivotLead', 'user_id' => $this->company->id, 'assign' => [$owner->id]]);
        $deal = $this->makeDeal($lead, 'DpPivotDeal');
        $this->makePurchase(['deal' => $deal]);

        $r = $this->actingAs($this->company)->get('/deal-purchase');
        $r->assertStatus(200);
        $r->assertSee('DpPivotDeal');
        $r->assertSee('<span dir="auto">' . $owner->name . '</span>', false);
        $r->assertDontSee('<span dir="auto">' . $this->company->name . '</span>', false);
    }

    public function test_assigned_staff_marks_a_lead_with_nobody_assigned()
    {
        $deal = $this->makeDeal($this->makeLead(['name' => 'DpNobodyLead']), 'DpNobodyDeal');
        $this->makePurchase(['deal' => $deal]);

        $r = $this->actingAs($this->company)->get('/deal-purchase');
        $r->assertStatus(200);
        $r->assertSee('DpNobodyDeal');
        $r->assertSee('<span class="text-muted">-</span>', false);
    }

    public function test_assigned_staff_lists_every_assigned_user()
    {
        $a = $this->makeUser('Sales Employee', 'dpOwnerA', $this->company->id, ['manage deal']);
        $b = $this->makeUser('Sales Employee', 'dpOwnerB', $this->company->id, ['manage deal']);
        $deal = $this->makeDeal($this->makeLead(['name' => 'DpTwoOwnerLead', 'assign' => [$a->id, $b->id]]), 'DpTwoOwnerDeal');
        $this->makePurchase(['deal' => $deal]);

        $r = $this->actingAs($this->company)->get('/deal-purchase');
        $r->assertStatus(200);
        $r->assertSee('<span dir="auto">' . $a->name . '</span>', false);
        $r->assertSee('<span dir="auto">' . $b->name . '</span>', false);
    }

    // ---- Employee filter ---------------------------------------------------------

    public function test_employee_filter_lists_sales_people_only()
    {
        $sales = $this->makeUser('Sales Employee', 'dpSalesFilter', $this->company->id, ['manage deal']);
        $accountant = $this->makeUser('Accountant', 'dpAccountantFilter', $this->company->id, ['manage deal']);

        $r = $this->actingAs($this->company)->get('/deal-purchase');
        $r->assertStatus(200);
        $r->assertSee('<option value="' . $sales->id . '"', false);
        $r->assertDontSee('<option value="' . $accountant->id . '"', false);
    }

    public function test_employee_filter_does_not_leak_another_tenant()
    {
        $other = $this->makeUser('company', 'dpFilterOther', 1, ['manage deal']);
        $foreign = $this->makeUser('Sales Employee', 'dpForeignSales', $other->id, ['manage deal']);

        $r = $this->actingAs($this->company)->get('/deal-purchase');
        $r->assertStatus(200);
        $r->assertDontSee('<option value="' . $foreign->id . '"', false);
    }

    public function test_employee_filter_filters_on_the_assignment()
    {
        $mine = $this->makeUser('Sales Employee', 'dpMineSales', $this->company->id, ['manage deal']);
        $theirs = $this->makeUser('Sales Employee', 'dpTheirsSales', $this->company->id, ['manage deal']);
        $this->makePurchase(['deal' => $this->makeDeal($this->makeLead(['assign' => [$mine->id]]), 'DPMINEDEAL')]);
        $this->makePurchase(['deal' => $this->makeDeal($this->makeLead(['assign' => [$theirs->id]]), 'DPTHEIRSDEAL')]);

        $r = $this->actingAs($this->company)->get('/deal-purchase?employee_id=' . $mine->id);
        $r->assertStatus(200);
        $r->assertSee('DPMINEDEAL');
        $r->assertDontSee('DPTHEIRSDEAL');
    }

    // ---- Products field on the create form ---------------------------------------

    protected function makeProduct($name, $price = 100, $createdBy = null)
    {
        $p = new \App\Models\ProductService();
        $p->name = $name;
        $p->sku = 'SKU' . uniqid();
        $p->sale_price = $price;
        $p->purchase_price = $price;
        $p->quantity = 1;
        $p->type = 'product';
        $p->created_by = $createdBy ?? $this->company->id;
        $p->save();

        return $p->fresh();
    }

    public function test_create_form_initialises_the_products_field_on_load()
    {
        $r = $this->actingAs($this->company)->get('/deal-purchase/create');
        $r->assertStatus(200);
        // Without this the field stays a bare <select multiple>: an empty box with
        // no placeholder, and a preselected deal never loads its products.
        $r->assertSee("loadProducts($('#deal_id').val()", false);
    }

    public function test_create_form_loads_the_products_of_a_preselected_deal()
    {
        $a = $this->makeProduct('CreateFormProductA', 250);
        $b = $this->makeProduct('CreateFormProductB', 75);
        $lead = $this->makeLead(['name' => 'ProductsLead']);
        $deal = $this->makeDeal($lead, 'ProductsDeal');
        $deal->products = $a->id . ',' . $b->id;
        $deal->save();

        $page = $this->actingAs($this->company)->get('/deal-purchase/create?deal_id=' . $deal->id);
        $page->assertStatus(200);
        $page->assertSee('ProductsDeal');

        // the field is filled from this endpoint
        $r = $this->actingAs($this->company)->get('/deal-purchase/get-products/' . $deal->id);
        $r->assertStatus(200);
        $body = $r->json();
        $this->assertCount(2, $body);
        $names = array_column($body, 'name');
        $this->assertContains('CreateFormProductA', $names);
        $this->assertContains('CreateFormProductB', $names);
        $this->assertSame('250.00', (string) $body[0]['price']);
    }

    public function test_get_products_is_empty_for_a_deal_with_no_products()
    {
        $deal = $this->makeDeal($this->makeLead(['name' => 'NoProductsLead']), 'NoProductsDeal');

        $r = $this->actingAs($this->company)->get('/deal-purchase/get-products/' . $deal->id);
        $r->assertStatus(200);
        $this->assertSame([], $r->json());
    }

    public function test_create_form_keeps_the_picked_products_after_a_failed_submit()
    {
        $a = $this->makeProduct('OldProductA', 10);
        $deal = $this->makeDeal($this->makeLead(['name' => 'OldLead']), 'OldDeal');
        $deal->products = (string) $a->id;
        $deal->save();

        $r = $this->actingAs($this->company)
            ->withSession(['_old_input' => ['deal_id' => $deal->id, 'products' => [(string) $a->id]]])
            ->get('/deal-purchase/create');

        $r->assertStatus(200);
        $r->assertSee('loadProducts($(\'#deal_id\').val(), ["' . $a->id . '"]);', false);
    }

    // ------------------------------------------------- the picker and the lead

    public function test_the_picker_only_offers_deals_of_the_lead_it_was_opened_from()
    {
        $lead = $this->makeLead(['name' => 'TwoDealLead']);
        $this->makeDeal($lead, 'LeadDealOne');
        $this->makeDeal($lead, 'LeadDealTwo');

        $elsewhere = $this->makeLead(['name' => 'SomebodyElse']);
        $this->makeDeal($elsewhere, 'SomebodyElseDeal');

        $r = $this->actingAs($this->company)
            ->get('/deal-purchase-deals-search?mode=free&lead_id=' . $lead->id);
        $r->assertStatus(200);

        $names = array_column($r->json(), 'text');
        sort($names);

        $this->assertSame(['LeadDealOne', 'LeadDealTwo'], $names);
    }

    public function test_without_a_lead_the_picker_still_offers_everything_free()
    {
        $a = $this->makeLead(['name' => 'LeadA']);
        $b = $this->makeLead(['name' => 'LeadB']);
        $this->makeDeal($a, 'FreeDealA');
        $this->makeDeal($b, 'FreeDealB');

        $r = $this->actingAs($this->company)->get('/deal-purchase-deals-search?mode=free');
        $names = array_column($r->json(), 'text');

        $this->assertContains('FreeDealA', $names);
        $this->assertContains('FreeDealB', $names);
    }

    public function test_a_lead_filter_cannot_reach_another_tenant()
    {
        list($other, $foreignLead, $foreignDeal) = $this->foreignTenant();

        $r = $this->actingAs($this->company)
            ->get('/deal-purchase-deals-search?mode=free&lead_id=' . $foreignLead->id);
        $r->assertStatus(200);

        $this->assertSame([], $r->json());
    }

    public function test_a_deal_that_already_has_a_purchase_stays_out_even_with_a_lead()
    {
        $lead = $this->makeLead(['name' => 'MixedLead']);
        $free = $this->makeDeal($lead, 'StillFreeDeal');
        $taken = $this->makeDeal($lead, 'AlreadyBoughtDeal');
        $this->makePurchase(['deal' => $taken]);

        $r = $this->actingAs($this->company)
            ->get('/deal-purchase-deals-search?mode=free&lead_id=' . $lead->id);
        $names = array_column($r->json(), 'text');

        $this->assertSame(['StillFreeDeal'], $names);
    }

    public function test_past_the_dropdown_limit_the_picker_comes_back_and_keeps_the_lead()
    {
        $lead = $this->makeLead(['name' => 'ManyDealsLead']);
        $limit = \App\Http\Controllers\DealPurchaseController::DROPDOWN_LIMIT;
        for ($i = 0; $i <= $limit; $i++) {
            $this->makeDeal($lead, 'BulkDeal' . $i);
        }

        $r = $this->actingAs($this->company)->get('/deal-purchase/create?lead_id=' . $lead->id);
        $r->assertStatus(200);

        // too many to list, so the searched picker takes over -- and it still has
        // to be told which lead, or it would offer the whole company's deals
        $r->assertSee('sm-picker', false);
        $r->assertDontSee('<select class="form-control" id="deal_id" name="deal_id" required>', false);
        $r->assertSee('lead_id=' . $lead->id, false);
    }

    public function test_a_lead_with_one_free_deal_comes_in_already_chosen()
    {
        $lead = $this->makeLead(['name' => 'SingleDealLead']);
        $deal = $this->makeDeal($lead, 'TheOnlyDeal');
        // a second lead with its own deal must not disturb the count
        $this->makeDeal($this->makeLead(['name' => 'NoiseLead']), 'NoiseDeal');

        $r = $this->actingAs($this->company)->get('/deal-purchase/create?lead_id=' . $lead->id);
        $r->assertStatus(200);
        $r->assertSee('value="' . $deal->id . '"', false);
        $r->assertSee('TheOnlyDeal', false);
    }

    public function test_a_lead_with_two_free_deals_chooses_nothing()
    {
        $lead = $this->makeLead(['name' => 'AmbiguousLead']);
        $this->makeDeal($lead, 'AmbiguousDealOne');
        $this->makeDeal($lead, 'AmbiguousDealTwo');

        $r = $this->actingAs($this->company)->get('/deal-purchase/create?lead_id=' . $lead->id);
        $r->assertStatus(200);

        // both are listed on purpose; neither may be picked for the user
        $r->assertSee('AmbiguousDealOne', false);
        $r->assertSee('AmbiguousDealTwo', false);
        $r->assertSee('Select Deal', false);
        $this->assertStringNotContainsString('selected', $this->dealSelectMarkup($r->getContent()));
    }

    /** The &lt;select id="deal_id"&gt; element on its own. */
    protected function dealSelectMarkup($html)
    {
        $start = strpos($html, '<select class="form-control" id="deal_id"');
        if ($start === false) {
            return '';
        }
        $end = strpos($html, '</select>', $start);

        return substr($html, $start, $end - $start);
    }

    public function test_the_deal_field_is_a_dropdown_when_opened_from_a_lead()
    {
        $lead = $this->makeLead(['name' => 'DropdownLead']);
        $one = $this->makeDeal($lead, 'DropdownDealOne');
        $two = $this->makeDeal($lead, 'DropdownDealTwo');

        $r = $this->actingAs($this->company)->get('/deal-purchase/create?lead_id=' . $lead->id);
        $r->assertStatus(200);

        $r->assertSee('<select class="form-control" id="deal_id" name="deal_id" required>', false);
        $r->assertSee('value="' . $one->id . '"', false);
        $r->assertSee('value="' . $two->id . '"', false);
        $r->assertSee('DropdownDealOne', false);
        $r->assertSee('DropdownDealTwo', false);
    }

    public function test_the_dropdown_offers_no_other_leads_deals()
    {
        $lead = $this->makeLead(['name' => 'OnlyMineLead']);
        $this->makeDeal($lead, 'MineDropdownDeal');
        $this->makeDeal($this->makeLead(['name' => 'TheirLead']), 'TheirDropdownDeal');

        $r = $this->actingAs($this->company)->get('/deal-purchase/create?lead_id=' . $lead->id);
        $r->assertSee('MineDropdownDeal', false);
        $r->assertDontSee('TheirDropdownDeal', false);
    }

    public function test_one_deal_needs_no_placeholder_row()
    {
        $lead = $this->makeLead(['name' => 'LoneDropdownLead']);
        $deal = $this->makeDeal($lead, 'LoneDropdownDeal');

        $r = $this->actingAs($this->company)->get('/deal-purchase/create?lead_id=' . $lead->id);
        $r->assertSee('<select class="form-control" id="deal_id" name="deal_id" required>', false);
        $r->assertDontSee('Select Deal', false);
        $r->assertSee('selected', false);
    }

    public function test_two_deals_start_with_nothing_chosen()
    {
        $lead = $this->makeLead(['name' => 'PickOneLead']);
        $this->makeDeal($lead, 'PickDealOne');
        $this->makeDeal($lead, 'PickDealTwo');

        $r = $this->actingAs($this->company)->get('/deal-purchase/create?lead_id=' . $lead->id);
        $r->assertSee('Select Deal', false);
    }

    public function test_the_dropdown_is_not_a_choices_widget()
    {
        $lead = $this->makeLead(['name' => 'PlainSelectLead']);
        $this->makeDeal($lead, 'PlainSelectDeal');

        $r = $this->actingAs($this->company)->get('/deal-purchase/create?lead_id=' . $lead->id);

        // custom.js wraps every .select2 in a Choices widget, and the products
        // handler below reads this element directly
        $r->assertDontSee('class="form-control select2" id="deal_id"', false);
    }

    public function test_a_deal_with_a_purchase_is_not_in_the_dropdown()
    {
        $lead = $this->makeLead(['name' => 'HalfTakenLead']);
        $free = $this->makeDeal($lead, 'FreeDropdownDeal');
        $taken = $this->makeDeal($lead, 'TakenDropdownDeal');
        $this->makePurchase(['deal' => $taken]);

        $r = $this->actingAs($this->company)->get('/deal-purchase/create?lead_id=' . $lead->id);
        $r->assertSee('FreeDropdownDeal', false);
        $r->assertDontSee('TakenDropdownDeal', false);
    }

    public function test_without_a_lead_the_field_stays_a_search_box()
    {
        $this->makeDeal($this->makeLead(['name' => 'NoLeadParamLead']), 'SearchedDeal');

        $r = $this->actingAs($this->company)->get('/deal-purchase/create');
        $r->assertStatus(200);
        $r->assertDontSee('<select class="form-control" id="deal_id" name="deal_id" required>', false);
        $r->assertSee('sm-picker', false);
    }

    public function test_opening_the_form_without_a_lead_chooses_nothing()
    {
        $lead = $this->makeLead(['name' => 'LooseLead']);
        $this->makeDeal($lead, 'LooseDealOne');
        $this->makeDeal($this->makeLead(), 'LooseDealTwo');

        $r = $this->actingAs($this->company)->get('/deal-purchase/create');
        $r->assertStatus(200);
        $r->assertDontSee('LooseDealOne', false);
    }
}
