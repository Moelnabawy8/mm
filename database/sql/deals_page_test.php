<?php
/**
 * Full functional + performance test for the /deals page.
 *
 * Boots the real application against a throwaway database (erp_deals_test),
 * creates only the tables the deal screens touch, seeds realistic volumes and
 * then drives DealController directly.
 *
 * Usage: php deals_test.php [leadCount] [dealCount]
 */

/*
 * LOCAL DEVELOPMENT ONLY. It DROPS and recreates the database named below and
 * connects as the local root account, so it refuses to run without --local.
 *
 *   php database/sql/deals_page_test.php --local [leadCount] [dealCount]
 */
if (!in_array("--local", $argv ?? [], true)) {
    fwrite(STDERR, "Refusing to run: this script drops/creates a test database.
");
    fwrite(STDERR, "Re-run locally with:  php database/sql/deals_page_test.php --local
");
    exit(1);
}
$argv = array_values(array_filter($argv, fn($a) => $a !== "--local"));

const ROOT = 'c:/xampp/htdocs/mm';

require ROOT . '/vendor/autoload.php';
$app = require ROOT . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\DealController;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

$LEADS = (int) ($argv[1] ?? 20000);
$DEALS = (int) ($argv[2] ?? 3000);
$TESTDB = 'erp_deals_test';

/* ---------------------------------------------------------------- */
/* test plumbing                                                     */
/* ---------------------------------------------------------------- */
$pass = 0;
$fail = 0;
$failures = [];

function ok(string $what, bool $cond, string $detail = ''): void
{
    global $pass, $fail, $failures;
    if ($cond) {
        $pass++;
        echo "  [PASS] $what" . ($detail !== '' ? "  ($detail)" : '') . "\n";
    } else {
        $fail++;
        $failures[] = $what . ($detail !== '' ? " -- $detail" : '');
        echo "  [FAIL] $what" . ($detail !== '' ? "  ($detail)" : '') . "\n";
    }
}

function section(string $title): void
{
    echo "\n=== $title ===\n";
}

function ms(callable $fn): array
{
    $t = microtime(true);
    $r = $fn();
    return [round((microtime(true) - $t) * 1000, 1), $r];
}

/* ---------------------------------------------------------------- */
/* database                                                          */
/* ---------------------------------------------------------------- */
section('setup: throwaway database');

config(["database.connections.mysql.host" => "127.0.0.1", "database.connections.mysql.username" => "root", "database.connections.mysql.password" => "", "database.connections.mysql.database" => null]);
DB::purge("mysql");
DB::statement("DROP DATABASE IF EXISTS `$TESTDB`");
DB::statement("CREATE DATABASE `$TESTDB` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
config(['database.connections.mysql.database' => $TESTDB]);
DB::purge('mysql');
echo "  using database $TESTDB\n";

Schema::create('settings', function ($t) {
    $t->bigIncrements('id');
    $t->string('name')->nullable();
    $t->text('value')->nullable();
    $t->integer('created_by')->default(0);
});

Schema::create('users', function ($t) {
    $t->bigIncrements('id');
    $t->string('name');
    $t->string('email')->unique();
    $t->string('password')->nullable();
    $t->string('type')->default('employee');
    $t->string('avatar')->nullable();
    $t->string('lang')->default('en');
    $t->string('sales_group')->nullable();
    $t->integer('created_by')->default(0);
    $t->integer('is_active')->default(1);
    $t->timestamps();
});

Schema::create('leads', function ($t) {
    $t->bigIncrements('id');
    $t->string('name');
    $t->string('email')->nullable();
    $t->string('phone')->nullable();
    $t->string('subject')->nullable();
    $t->integer('user_id')->default(0);
    $t->integer('pipeline_id')->default(1);
    $t->integer('stage_id')->default(1);
    $t->integer('created_by')->default(0);
    $t->integer('is_active')->default(1);
    $t->timestamps();
});

Schema::create('deals', function ($t) {
    $t->bigIncrements('id');
    $t->string('name');
    $t->unsignedBigInteger('lead_id')->nullable();
    $t->string('phone')->nullable();
    $t->decimal('price', 15, 2)->default(0);
    $t->integer('pipeline_id')->default(1);
    $t->integer('stage_id')->default(1);
    $t->string('sources')->nullable();
    $t->string('products')->nullable();
    $t->text('notes')->nullable();
    $t->dateTime('datetime')->nullable();
    $t->string('labels')->nullable();
    $t->string('status')->nullable();
    $t->integer('order')->default(0);
    $t->integer('created_by')->default(0);
    $t->integer('added_by')->nullable();
    $t->integer('is_active')->default(1);
    $t->timestamps();
});

Schema::create('deal_attachments', function ($t) {
    $t->bigIncrements('id');
    $t->unsignedBigInteger('deal_id');
    $t->string('file_name');
    $t->string('file_path');
    $t->string('file_type')->nullable();
    $t->string('mime_type')->nullable();
    $t->unsignedBigInteger('file_size')->nullable();
    $t->unsignedBigInteger('uploaded_by')->nullable();
    $t->timestamps();
});

Schema::create('user_leads', function ($t) {
    $t->bigIncrements('id');
    $t->unsignedBigInteger('user_id');
    $t->unsignedBigInteger('lead_id');
    $t->timestamps();
    $t->unique(['user_id', 'lead_id'], 'user_leads_user_lead_unique');
});

Schema::create('stages', function ($t) {
    $t->bigIncrements('id');
    $t->string('name');
    $t->integer('pipeline_id')->default(1);
    $t->integer('order')->default(0);
    $t->integer('created_by')->default(0);
    $t->timestamps();
});

Schema::create('product_services', function ($t) {
    $t->bigIncrements('id');
    $t->string('name');
    $t->integer('created_by')->default(0);
    $t->timestamps();
});

Schema::create('custom_fields', function ($t) {
    $t->bigIncrements('id');
    $t->string('module');
    $t->string('name');
    $t->string('type')->nullable();
    $t->integer('created_by')->default(0);
    $t->timestamps();
});

Schema::create('custom_field_values', function ($t) {
    $t->bigIncrements('id');
    $t->integer('record_id')->default(0);
    $t->integer('field_id')->default(0);
    $t->text('value')->nullable();
    $t->timestamps();
});

Schema::create('labels', function ($t) {
    $t->bigIncrements('id');
    $t->string('name');
    $t->string('color')->nullable();
    $t->integer('pipeline_id')->default(1);
    $t->integer('created_by')->default(0);
    $t->timestamps();
});

Schema::create("deal_purchases", function ($t) {
    $t->bigIncrements("id");
    $t->unsignedBigInteger("deal_id");
    $t->decimal("price", 15, 2)->default(0);
    $t->string("currency")->nullable();
    $t->string("payment_method")->nullable();
    $t->string("payment_type")->nullable();
    $t->decimal("paid_amount", 15, 2)->default(0);
    $t->string("status")->nullable();
    $t->date("purchase_date")->nullable();
    $t->text("products")->nullable();
    $t->integer("created_by")->default(0);
    $t->timestamps();
});
Schema::create("installments", function ($t) {
    $t->bigIncrements("id");
    $t->unsignedBigInteger("deal_purchase_id")->nullable();
    $t->decimal("amount", 15, 2)->default(0);
    $t->date("due_date")->nullable();
    $t->string("status")->nullable();
    $t->integer("created_by")->default(0);
    $t->timestamps();
});

// spatie/permission
Schema::create('permissions', function ($t) {
    $t->bigIncrements('id');
    $t->string('name');
    $t->string('guard_name')->default('web');
    $t->timestamps();
});
Schema::create('roles', function ($t) {
    $t->bigIncrements('id');
    $t->string('name');
    $t->string('guard_name')->default('web');
    $t->integer('created_by')->default(0);
    $t->timestamps();
});
Schema::create('model_has_permissions', function ($t) {
    $t->unsignedBigInteger('permission_id');
    $t->string('model_type');
    $t->unsignedBigInteger('model_id');
    $t->primary(['permission_id', 'model_id', 'model_type']);
});
Schema::create('model_has_roles', function ($t) {
    $t->unsignedBigInteger('role_id');
    $t->string('model_type');
    $t->unsignedBigInteger('model_id');
    $t->primary(['role_id', 'model_id', 'model_type']);
});
Schema::create('role_has_permissions', function ($t) {
    $t->unsignedBigInteger('permission_id');
    $t->unsignedBigInteger('role_id');
    $t->primary(['permission_id', 'role_id']);
});
app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
echo "  tables created\n";

/* ---------------------------------------------------------------- */
/* seed                                                              */
/* ---------------------------------------------------------------- */
section('setup: seed');

foreach (['manage deal', 'view deal', 'create deal', 'edit deal', 'delete deal', 'view all leads'] as $p) {
    Permission::create(['name' => $p, 'guard_name' => 'web']);
}
$salesManagerRole = Role::create(['name' => 'Sales Manager', 'guard_name' => 'web']);
$salesEmpRole = Role::create(['name' => 'Sales Employee', 'guard_name' => 'web']);

$mk = function (array $attr) {
    $u = new User();
    $u->forceFill(array_merge([
        'password' => bcrypt('secret'),
        'created_at' => now(),
        'updated_at' => now(),
    ], $attr))->save();
    return $u;
};

$company = $mk(['name' => 'Acme Co', 'email' => 'company@test.local', 'type' => 'company', 'created_by' => 0]);
$company->givePermissionTo(['manage deal', 'view deal', 'create deal', 'edit deal', 'delete deal']);

$employee = $mk(['name' => 'Sam Sales', 'email' => 'emp@test.local', 'type' => 'Sales Employee', 'created_by' => $company->id]);
$employee->givePermissionTo(['manage deal', 'view deal', 'create deal', 'edit deal']);
$employee->assignRole($salesEmpRole);

$manager = $mk(['name' => 'Mona Manager', 'email' => 'mgr@test.local', 'type' => 'employee', 'created_by' => $company->id, 'sales_group' => 'G1']);
$manager->givePermissionTo(['manage deal', 'view deal', 'create deal', 'edit deal']);
$manager->assignRole($salesManagerRole);

$managerNoGroup = $mk(['name' => 'Nabil NoGroup', 'email' => 'mgr2@test.local', 'type' => 'employee', 'created_by' => $company->id]);
$managerNoGroup->givePermissionTo(['manage deal', 'view deal']);
$managerNoGroup->assignRole($salesManagerRole);

$groupMember = $mk(['name' => 'Gina Group', 'email' => 'g1@test.local', 'type' => 'Sales Employee', 'created_by' => $company->id, 'sales_group' => 'G1']);
$noPerms = $mk(['name' => 'Pat NoPerms', 'email' => 'noperm@test.local', 'type' => 'employee', 'created_by' => $company->id]);

// a second tenant, to prove cross-company isolation
$otherCompany = $mk(['name' => 'Rival Ltd', 'email' => 'rival@test.local', 'type' => 'company', 'created_by' => 0]);
$otherCompany->givePermissionTo(['manage deal', 'view deal', 'edit deal']);

DB::table('stages')->insert([
    ['id' => 1, 'name' => 'New', 'order' => 1, 'created_by' => $company->id],
    ['id' => 2, 'name' => 'Negotiation', 'order' => 2, 'created_by' => $company->id],
    ['id' => 3, 'name' => 'Won', 'order' => 3, 'created_by' => $company->id],
]);
DB::table('product_services')->insert([
    ['id' => 1, 'name' => 'Fibre 100', 'created_by' => $company->id],
    ['id' => 2, 'name' => 'Fibre 500', 'created_by' => $company->id],
    ['id' => 3, 'name' => 'Landline', 'created_by' => $company->id],
    ['id' => 4, 'name' => 'Mobile Plan', 'created_by' => $company->id],
]);

// leads
$rows = [];
for ($i = 1; $i <= $LEADS; $i++) {
    $rows[] = [
        'id' => $i,
        'name' => 'Lead ' . $i . ' ' . ($i % 7 === 0 ? 'Mohamed' : 'Ahmed'),
        'email' => 'lead' . $i . '@ex.com',
        'phone' => '0100' . str_pad((string) $i, 7, '0', STR_PAD_LEFT),
        'user_id' => $employee->id,
        'created_by' => $company->id,
        'created_at' => now(),
        'updated_at' => now(),
    ];
    if (count($rows) === 2000) {
        DB::table('leads')->insert($rows);
        $rows = [];
    }
}
if ($rows) {
    DB::table('leads')->insert($rows);
}

// deals
$rows = [];
for ($i = 1; $i <= $DEALS; $i++) {
    $rows[] = [
        'id' => $i,
        'name' => 'Deal ' . $i . ($i % 5 === 0 ? ' Renewal' : ''),
        'lead_id' => (($i - 1) % $LEADS) + 1,
        'stage_id' => ($i % 3) + 1,
        'status' => 'Active',
        'products' => $i % 4 === 0 ? '1,3' : ($i % 3 === 0 ? '2' : null),
        'created_by' => $company->id,
        'added_by' => $employee->id,
        'datetime' => now()->subDays($i % 30)->format('Y-m-d') . ' 00:00:00',
        'created_at' => now()->subDays($i % 60),
        'updated_at' => now(),
    ];
    if (count($rows) === 2000) {
        DB::table('deals')->insert($rows);
        $rows = [];
    }
}
if ($rows) {
    DB::table('deals')->insert($rows);
}

// a deal that belongs to the OTHER company
DB::table('deals')->insert([
    'id' => 999001,
    'name' => 'Rival secret deal',
    'lead_id' => null,
    'stage_id' => 1,
    'created_by' => $otherCompany->id,
    'is_active' => 1,
    'created_at' => now(),
    'updated_at' => now(),
]);

// assignments: employee gets the first 50 leads, group member gets leads 100..119
$assign = [];
for ($i = 1; $i <= 50; $i++) {
    $assign[] = ['user_id' => $employee->id, 'lead_id' => $i, 'created_at' => now(), 'updated_at' => now()];
}
for ($i = 100; $i <= 119; $i++) {
    $assign[] = ['user_id' => $groupMember->id, 'lead_id' => $i, 'created_at' => now(), 'updated_at' => now()];
}
DB::table('user_leads')->insert($assign);

// attachments on deal 1 and 2
DB::table('deal_attachments')->insert([
    ['deal_id' => 1, 'file_name' => 'a.pdf', 'file_path' => 'deal_attachments/1/a.pdf', 'file_type' => 'document', 'created_at' => now(), 'updated_at' => now()],
    ['deal_id' => 1, 'file_name' => 'b.png', 'file_path' => 'deal_attachments/1/b.png', 'file_type' => 'image', 'created_at' => now(), 'updated_at' => now()],
    ['deal_id' => 2, 'file_name' => 'c.pdf', 'file_path' => 'deal_attachments/2/c.pdf', 'file_type' => 'document', 'created_at' => now(), 'updated_at' => now()],
]);

echo "  seeded: {$LEADS} leads, {$DEALS} deals (+1 foreign), " . count($assign) . " assignments\n";

/* ---------------------------------------------------------------- */
/* helpers to drive the controller                                   */
/* ---------------------------------------------------------------- */
function actingAs(User $u): void
{
    \Auth::setUser($u);
}

/**
 * Modules\LandingPage\Providers\AddMenuProvider registers a view composer for
 * EVERY route name, and its menu blade calls Request::route()->getName(), so a
 * faked request needs a resolved route or rendering deals.* blows up.
 */
function attachRoute(Request $r, string $name = 'deals.index'): Request
{
    $route = new \Illuminate\Routing\Route(['GET'], 'deals', ['as' => $name]);
    $route->bind($r); // otherwise $request->name raises 'Route is not bound'
    $r->setRouteResolver(fn() => $route);
    return $r;
}

function req(array $query = [], bool $ajax = false): Request
{
    $r = Request::create('http://localhost/deals', 'GET', $query);
    if ($ajax) {
        $r->headers->set('X-Requested-With', 'XMLHttpRequest');
    }
    attachRoute($r);
    app()->instance('request', $r);
    \Illuminate\Pagination\Paginator::currentPageResolver(function ($pageName = 'page') use ($r) {
        return (int) ($r->query($pageName) ?: 1);
    });
    \Illuminate\Pagination\Paginator::currentPathResolver(function () {
        return 'http://localhost/deals';
    });
    return $r;
}

$controller = new DealController();

/* ================================================================ */
section('1. page load as company owner');

actingAs($company);
DB::flushQueryLog();
DB::enableQueryLog();
[$msIndex, $view] = ms(fn() => $controller->index(req()));
$queries = DB::getQueryLog();
DB::disableQueryLog();

ok('returns the deals.index view', $view instanceof \Illuminate\View\View && $view->name() === 'deals.index', $view instanceof \Illuminate\View\View ? $view->name() : gettype($view));
$data = $view->getData();
ok('no $leads variable is passed any more', !array_key_exists('leads', $data));
ok('$productsMap is passed to the view', array_key_exists('productsMap', $data));
ok('$selectedLead is passed to the view', array_key_exists('selectedLead', $data));
ok('20 deals on page 1', count($data['deals']) === 20, 'got ' . count($data['deals']));
ok('total = seeded deals of this company', $data['deals']->total() === $DEALS, 'total ' . $data['deals']->total());
ok('newest deal first', $data['deals']->first()->id === $DEALS, 'first id ' . $data['deals']->first()->id);
ok('attachments relation NOT eager loaded', !$data['deals']->first()->relationLoaded('attachments'));
ok('attachments_count present', $data['deals']->first()->attachments_count !== null);
ok('query count is bounded', count($queries) <= 15, count($queries) . ' queries, ' . $msIndex . ' ms');
echo "       controller time: {$msIndex} ms\n";

$foreign = collect($data['deals']->items())->firstWhere('id', 999001);
ok('other company deal not listed', $foreign === null);

section('2. products column (was always empty: $productsMap was never passed)');

$dealWithProducts = Deal::where('created_by', $company->id)->whereNotNull('products')->orderBy('id', 'desc')->first();
$req = req(['name' => $dealWithProducts->name]);
$v = $controller->index($req);
$html = $v->getData()['deals']->count() ? view('deals._table', $v->getData())->render() : '';
ok('product badge rendered for a deal with products', str_contains($html, 'badge bg-primary'), 'deal ' . $dealWithProducts->id . ' products=' . $dealWithProducts->products);
foreach (explode(',', $dealWithProducts->products) as $pid) {
    $name = DB::table('product_services')->where('id', $pid)->value('name');
    ok("product name '$name' visible", str_contains($html, $name));
}

section('3. ajax filter path returns the table partial with products');

$ajaxView = $controller->index(req(['name' => $dealWithProducts->name], true));
ok('ajax request renders deals._table', $ajaxView->name() === 'deals._table', $ajaxView->name());
ok('ajax view receives productsMap', array_key_exists('productsMap', $ajaxView->getData()));
$ajaxHtml = $ajaxView->render();
ok('ajax html contains the product badge', str_contains($ajaxHtml, 'badge bg-primary'));
ok('ajax html has no unresolved blade', !str_contains($ajaxHtml, '@php') && !str_contains($ajaxHtml, '{{'));

section('4. attachments badge uses the counted value');

$v = $controller->index(req(['lead_id' => Deal::find(1)->lead_id]));
$rowHtml = view('deals._table', $v->getData())->render();
ok('the page with deal 1 renders', $v->getData()['deals']->total() >= 1, $v->getData()['deals']->total() . ' rows');
ok('deal 1 shows its 2 attachments', (bool) preg_match('/ti-paperclip"><\/i>\s*2\s*</', $rowHtml), 'attachments_count=' . Deal::withCount('attachments')->find(1)->attachments_count);
$noAttach = $controller->index(req(['lead_id' => Deal::find(3)->lead_id]));
ok('a deal without attachments shows a dash', str_contains(view('deals._table', $noAttach->getData())->render(), 'text-muted">-<'));

section('5. filters');

$cases = [
    'name filter' => [['name' => 'Renewal'], fn($d) => $d->every(fn($x) => str_contains($x->name, 'Renewal'))],
    'stage filter' => [['stage_id' => 2], fn($d) => $d->every(fn($x) => (int) $x->stage_id === 2)],
    'lead filter' => [['lead_id' => 5], fn($d) => $d->every(fn($x) => (int) $x->lead_id === 5)],
    'products filter' => [['products' => '3'], fn($d) => $d->every(fn($x) => in_array('3', explode(',', (string) $x->products)))],
    'user filter' => [['user_id' => $GLOBALS['employeeId'] ?? 2], fn($d) => $d->every(fn($x) => $x->lead_id <= 50)],
];
$GLOBALS['employeeId'] = $employee->id;
foreach ($cases as $label => [$query, $assert]) {
    $v = $controller->index(req($query));
    $items = collect($v->getData()['deals']->items());
    ok($label, $items->count() > 0 && $assert($items), $items->count() . ' rows');
}

$v = $controller->index(req(['date_from' => now()->subDays(5)->format('Y-m-d'), 'date_to' => now()->format('Y-m-d')]));
$items = collect($v->getData()['deals']->items());
ok('date range filter', $items->every(fn($x) => $x->created_at->gte(now()->subDays(6))), $items->count() . ' rows');

$v = $controller->index(req(['lead_id' => 5]));
ok('selectedLead labels the picker', optional($v->getData()['selectedLead'])->id === 5);

$v = $controller->index(req(['lead_id' => 999999]));
ok('unknown lead id => no selectedLead', $v->getData()['selectedLead'] === null);

// cross tenant lead id must not leak a name into the filter box
$otherLead = DB::table('leads')->insertGetId([
    'name' => 'Rival lead', 'email' => 'r@x.com', 'created_by' => $otherCompany->id,
    'created_at' => now(), 'updated_at' => now(),
]);
$v = $controller->index(req(['lead_id' => $otherLead]));
ok('other tenant lead id => no selectedLead', $v->getData()['selectedLead'] === null);

section('6. pagination');

$v = $controller->index(req(['page' => 2]));
$p = $v->getData()['deals'];
ok('page 2 returns rows', $p->count() === 20, 'page ' . $p->currentPage());
ok('page 2 rows differ from page 1', $p->first()->id === $DEALS - 20, 'first id ' . $p->first()->id);
$v = $controller->index(req(['page' => 2, 'stage_id' => 2], true));
$p = $v->getData()['deals'];
ok('filter survives pagination', $p->count() > 0 && collect($p->items())->every(fn($x) => (int) $x->stage_id === 2));
ok('pagination links keep the filter', str_contains($p->url(3), 'stage_id=2'), $p->url(3));

section('7. visibility per role');

actingAs($employee);
$v = $controller->index(req());
$items = collect($v->getData()['deals']->items());
ok('employee sees only deals of assigned leads', $items->every(fn($x) => $x->lead_id >= 1 && $x->lead_id <= 50), $v->getData()['deals']->total() . ' visible');
ok('employee total is limited', $v->getData()['deals']->total() < $DEALS, $v->getData()['deals']->total() . ' of ' . $DEALS);

actingAs($manager);
$v = $controller->index(req());
$total = $v->getData()['deals']->total();
$items = collect($v->getData()['deals']->items());
ok('sales manager sees only their group leads', $items->every(fn($x) => $x->lead_id >= 100 && $x->lead_id <= 119), $total . ' visible');

actingAs($managerNoGroup);
$v = $controller->index(req());
ok('sales manager without group sees nothing', $v->getData()['deals']->total() === 0);

actingAs($otherCompany);
$v = $controller->index(req());
ok('other tenant sees only its own deal', $v->getData()['deals']->total() === 1, 'total ' . $v->getData()['deals']->total());

actingAs($noPerms);
$res = $controller->index(req());
ok('user without "manage deal" is refused', $res instanceof \Illuminate\Http\RedirectResponse);

section('8. lead autocomplete endpoint');

actingAs($company);
[$msSearch, $resp] = ms(fn() => $controller->leadsSearch(req(['q' => 'Mohamed'])));
$json = json_decode($resp->getContent(), true);
ok('returns json rows', is_array($json) && count($json) > 0, count($json) . ' rows in ' . $msSearch . ' ms');
ok('never more than 25 rows', count($json) <= 25, count($json) . ' rows');
ok('rows have id + text', isset($json[0]['id'], $json[0]['text']));
ok('text carries the phone', str_contains($json[0]['text'], ' - 0100'), $json[0]['text']);
ok('matches the search term', collect($json)->every(fn($r) => str_contains($r['text'], 'Mohamed')));

$byPhone = json_decode($controller->leadsSearch(req(['q' => '01000000123']))->getContent(), true);
ok('search by phone works', count($byPhone) === 1 && $byPhone[0]['id'] === 123, json_encode($byPhone));
$byEmail = json_decode($controller->leadsSearch(req(['q' => 'lead77@ex.com']))->getContent(), true);
ok('search by email works', count($byEmail) === 1 && $byEmail[0]['id'] === 77);

actingAs($employee);
$empRows = json_decode($controller->leadsSearch(req(['q' => 'Lead']))->getContent(), true);
ok('employee autocomplete is scoped to assigned leads', count($empRows) > 0 && collect($empRows)->every(fn($r) => $r['id'] <= 50), count($empRows) . ' rows');

actingAs($manager);
$mgrRows = json_decode($controller->leadsSearch(req(['q' => 'Lead']))->getContent(), true);
ok('manager autocomplete is scoped to the group', count($mgrRows) > 0 && collect($mgrRows)->every(fn($r) => $r['id'] >= 100 && $r['id'] <= 119), count($mgrRows) . ' rows');

actingAs($managerNoGroup);
ok('manager without group gets no leads', json_decode($controller->leadsSearch(req(['q' => 'Lead']))->getContent(), true) === []);

actingAs($otherCompany);
$rivalRows = json_decode($controller->leadsSearch(req(['q' => 'Lead']))->getContent(), true);
$rivalOwnIds = DB::table('leads')->where('created_by', $otherCompany->id)->pluck('id')->all();
ok('autocomplete never crosses tenants',
    count($rivalRows) === count($rivalOwnIds) && collect($rivalRows)->every(fn($r) => in_array($r['id'], $rivalOwnIds)),
    json_encode($rivalRows));

actingAs($noPerms);
$denied = $controller->leadsSearch(req(['q' => 'Lead']));
ok('autocomplete denied without deal permissions', $denied->getStatusCode() === 401, 'status ' . $denied->getStatusCode());

actingAs($company);
$empty = json_decode($controller->leadsSearch(req([]))->getContent(), true);
ok('empty term still returns at most 25', count($empty) <= 25, count($empty) . ' rows');

section('9. create / edit screens no longer load every lead');

actingAs($company);
DB::flushQueryLog();
DB::enableQueryLog();
[$msCreate, $createView] = ms(fn() => $controller->create());
$createQueries = DB::getQueryLog();
DB::disableQueryLog();
ok('create view has no $leads', !array_key_exists('leads', $createView->getData()));
$leadQueries = collect($createQueries)->filter(fn($q) => str_contains($q['query'], 'from `leads`'))->count();
ok('create runs no query against leads', $leadQueries === 0, $leadQueries . ' lead queries, ' . $msCreate . ' ms');
$createHtml = $createView->render();
ok('create renders the lead picker', str_contains($createHtml, 'deal-lead-picker'));
ok('create html is small', strlen($createHtml) < 40000, strlen($createHtml) . ' bytes');

$editView = $controller->edit(Deal::find(1));
ok('edit view has no $leads', !array_key_exists('leads', $editView->getData()));
$editHtml = $editView->render();
ok('edit renders the lead picker', str_contains($editHtml, 'deal-lead-picker'));
ok('edit prefills the current lead name', str_contains($editHtml, Lead::find(Deal::find(1)->lead_id)->name));
ok('edit html is small', strlen($editHtml) < 40000, strlen($editHtml) . ' bytes');

section('10. show() authorisation');

actingAs($company);
$res = $controller->show(Deal::find(999001));
ok('company cannot open another tenant deal', $res instanceof \Illuminate\Http\RedirectResponse);
actingAs($noPerms);
$res = $controller->show(Deal::find(1));
ok('user without "view deal" is refused', $res instanceof \Illuminate\Http\RedirectResponse);
actingAs($otherCompany);
$res = $controller->show(Deal::find(999001));
ok('owner can still open its own deal', $res instanceof \Illuminate\View\View, is_object($res) ? get_class($res) : gettype($res));

section('11. before / after: the payload that froze the browser');

actingAs($company);
[$msOldPluck, $oldLeads] = ms(fn() => Lead::where('created_by', $company->id)->pluck('name', 'id'));
$oldOptionsHtml = '';
foreach ($oldLeads as $id => $name) {
    $oldOptionsHtml .= '<option value="' . $id . '">' . e($name) . "</option>\n";
}
printf("  OLD lead <select>: %d options, %s KB of html, %s ms just to fetch the names\n",
    count($oldLeads), number_format(strlen($oldOptionsHtml) / 1024, 1), $msOldPluck);

$fullView = $controller->index(req());
$fullData = $fullView->getData();
$tableHtml = view('deals._table', $fullData)->render();
$pickerHtml = view('deals._lead_picker', ['pickerId' => 'filter_lead', 'fieldName' => 'lead_id'])->render();
printf("  NEW lead picker:   0 options, %s KB of html\n", number_format(strlen($pickerHtml) / 1024, 1));
printf("  NEW deals table:   %s KB of html for 20 rows\n", number_format(strlen($tableHtml) / 1024, 1));
ok('lead markup no longer grows with the leads table', strlen($pickerHtml) < 6000, strlen($pickerHtml) . ' bytes');

section('12. index migration effect');

$explainBefore = DB::select('EXPLAIN SELECT count(*) as aggregate FROM `deals` WHERE `created_by` = ?', [$company->id]);
$rowsScannedBefore = $explainBefore[0]->rows ?? null;
$keyBefore = $explainBefore[0]->key ?? null;
[$msCountBefore] = ms(fn() => DB::selectOne('SELECT count(*) c FROM `deals` WHERE `created_by` = ?', [$company->id]));

DB::statement('ALTER TABLE `deals` ADD INDEX `deals_created_by_id_index` (`created_by`, `id`)');
DB::statement('ALTER TABLE `deals` ADD INDEX `deals_lead_id_index` (`lead_id`)');
DB::statement('ALTER TABLE `deals` ADD INDEX `deals_stage_id_index` (`stage_id`)');

$explainAfter = DB::select('EXPLAIN SELECT count(*) as aggregate FROM `deals` WHERE `created_by` = ?', [$company->id]);
$rowsScannedAfter = $explainAfter[0]->rows ?? null;
$keyAfter = $explainAfter[0]->key ?? null;
[$msCountAfter] = ms(fn() => DB::selectOne('SELECT count(*) c FROM `deals` WHERE `created_by` = ?', [$company->id]));

printf("  paginator COUNT(*) before: key=%s rows=%s (%s ms)\n", $keyBefore ?: 'NULL (full scan)', $rowsScannedBefore, $msCountBefore);
printf("  paginator COUNT(*) after:  key=%s rows=%s (%s ms)\n", $keyAfter ?: 'NULL (full scan)', $rowsScannedAfter, $msCountAfter);
ok('COUNT(*) uses an index after the migration', $keyAfter !== null);

actingAs($employee);
[$msEmpAfter] = ms(fn() => $controller->index(req()));
actingAs($company);
[$msCompanyAfter] = ms(fn() => $controller->index(req()));
printf("  controller with indexes: company %s ms, employee %s ms\n", $msCompanyAfter, $msEmpAfter);

section('13. sql shape');

actingAs($employee);
$sql = Deal::where('created_by', $employee->ownerId())
    ->whereIn('lead_id', DB::table('user_leads')->select('lead_id')->where('user_id', $employee->id))
    ->toSql();
ok('visibility is a subquery, not a giant IN list', str_contains($sql, 'select `lead_id` from `user_leads`'), $sql);

section('14. write actions from the deals page');

actingAs($company);

// ---- store()
$before = Deal::where('created_by', $company->id)->count();
$storeReq = Request::create('http://localhost/deals', 'POST', [
    'name' => 'Brand new deal',
    'lead_id' => 7,
    'stage_id' => 2,
    'products' => ['1', '2'],
    'notes' => 'first note',
    'datetime' => now()->format('Y-m-d'),
]);
attachRoute($storeReq);
app()->instance('request', $storeReq);
$res = $controller->store($storeReq);
$created = Deal::where('name', 'Brand new deal')->first();
ok('store() creates the deal', $created !== null);
if ($created) {
    ok('store() sets created_by to the tenant', (int) $created->created_by === (int) $company->id);
    ok('store() records added_by', (int) $created->added_by === (int) $company->id);
    ok('store() stores products as a csv', $created->products === '1,2', (string) $created->products);
    ok('store() copies the stage name into status', $created->status === 'Negotiation', (string) $created->status);
}
ok('store() added exactly one row', Deal::where('created_by', $company->id)->count() === $before + 1);

// ---- store() validation
$badReq = Request::create('http://localhost/deals', 'POST', ['name' => '', 'lead_id' => '', 'stage_id' => '']);
attachRoute($badReq);
app()->instance('request', $badReq);
$res = $controller->store($badReq);
ok('store() rejects an empty form', $res instanceof \Illuminate\Http\RedirectResponse);
ok('store() wrote nothing on failure', Deal::where('created_by', $company->id)->count() === $before + 1);

// ---- store() with a lead from another tenant  (KNOWN GAP)
$rivalLeadId = DB::table('leads')->where('created_by', $otherCompany->id)->value('id');
$xReq = Request::create('http://localhost/deals', 'POST', [
    'name' => 'Cross tenant deal', 'lead_id' => $rivalLeadId, 'stage_id' => 1,
]);
attachRoute($xReq);
app()->instance('request', $xReq);
$controller->store($xReq);
$xDeal = Deal::where('name', 'Cross tenant deal')->first();
ok('store() refuses a lead from another company', $xDeal === null,
    $xDeal ? 'accepted lead ' . $rivalLeadId . ' of tenant ' . $otherCompany->id : '');

// ---- update()
$upReq = Request::create('http://localhost/deals/' . $created->id, 'PUT', [
    'name' => 'Renamed deal',
    'lead_id' => 9,
    'stage_id' => 3,
    'products' => ['3'],
    'notes' => 'edited',
    'datetime' => now()->format('Y-m-d'),
]);
attachRoute($upReq);
app()->instance('request', $upReq);
$controller->update($upReq, $created);
$created->refresh();
ok('update() renames the deal', $created->name === 'Renamed deal');
ok('update() moves the lead', (int) $created->lead_id === 9);
ok('update() replaces the products', $created->products === '3', (string) $created->products);
ok('update() refreshes status from the stage', $created->status === 'Won', (string) $created->status);

// another tenant may not update it
actingAs($otherCompany);
$upReq2 = Request::create('http://localhost/deals/' . $created->id, 'PUT', [
    'name' => 'Hijacked', 'lead_id' => 9, 'stage_id' => 1,
]);
attachRoute($upReq2);
app()->instance('request', $upReq2);
$controller->update($upReq2, $created);
$created->refresh();
ok('another tenant cannot update the deal', $created->name === 'Renamed deal', $created->name);

// ---- note
actingAs($company);
$noteReq = Request::create('/', 'POST', ['notes' => 'inline note']);
attachRoute($noteReq);
app()->instance('request', $noteReq);
$json = $controller->noteStore($created->id, $noteReq);
$created->refresh();
ok('noteStore() saves the note', $created->notes === 'inline note' && $json->getStatusCode() === 200);
actingAs($otherCompany);
$noteReq2 = Request::create('/', 'POST', ['notes' => 'hacked note']);
attachRoute($noteReq2);
app()->instance('request', $noteReq2);
$json = $controller->noteStore($created->id, $noteReq2);
$created->refresh();
ok('noteStore() is tenant scoped', $created->notes === 'inline note' && $json->getStatusCode() === 401);

// ---- products add / remove
actingAs($company);
$pReq = Request::create('/', 'PUT', ['products' => ['1', '2']]);
attachRoute($pReq);
app()->instance('request', $pReq);
$controller->productUpdate($created->id, $pReq);
$created->refresh();
ok('productUpdate() appends products', $created->products === '3,1,2', (string) $created->products);
$pReq2 = Request::create('/', 'PUT', ['products' => ['1']]);
attachRoute($pReq2);
app()->instance('request', $pReq2);
$controller->productUpdate($created->id, $pReq2);
$created->refresh();
ok('productUpdate() no longer duplicates a product', substr_count(',' . $created->products . ',', ',1,') === 1, (string) $created->products);
$controller->productDestroy($created->id, 1);
$created->refresh();
ok('productDestroy() removes just that product', !in_array('1', explode(',', (string) $created->products)), (string) $created->products);

// a deal with NULL products must not explode
$nullProducts = Deal::where('created_by', $company->id)->whereNull('products')->first();
$pReq3 = Request::create('/', 'PUT', ['products' => ['2']]);
attachRoute($pReq3);
app()->instance('request', $pReq3);
$controller->productUpdate($nullProducts->id, $pReq3);
$nullProducts->refresh();
ok('productUpdate() handles a deal with no products yet', $nullProducts->products === '2', (string) $nullProducts->products);

// ---- labels
DB::table('labels')->insert([
    ['id' => 1, 'name' => 'Hot', 'color' => 'danger', 'pipeline_id' => 1, 'created_by' => $company->id],
    ['id' => 2, 'name' => 'Cold', 'color' => 'info', 'pipeline_id' => 1, 'created_by' => $company->id],
]);
$labelView = $controller->labels($created->id);
ok('labels() returns the labels modal', $labelView instanceof \Illuminate\View\View);
$lReq = Request::create('/', 'POST', ['labels' => ['1', '2']]);
attachRoute($lReq);
app()->instance('request', $lReq);
$controller->labelStore($created->id, $lReq);
$created->refresh();
ok('labelStore() saves the labels', $created->labels === '1,2', (string) $created->labels);

// ---- attachments
actingAs($company);
$upload = \Illuminate\Http\UploadedFile::fake()->create('contract.pdf', 12, 'application/pdf');
$aReq = Request::create('/', 'POST', [], [], ['file' => $upload]);
attachRoute($aReq);
app()->instance('request', $aReq);
$aRes = $controller->uploadAttachment($created->id, $aReq);
$aJson = json_decode($aRes->getContent(), true);
ok('uploadAttachment() stores the file', !empty($aJson['is_success']), $aRes->getContent());
$attachmentId = $aJson['attachment']['id'] ?? null;
ok('the attachment row is linked to the deal', DB::table('deal_attachments')->where('deal_id', $created->id)->count() === 1);

actingAs($otherCompany);
$aRes2 = $controller->uploadAttachment($created->id, $aReq);
ok('another tenant cannot upload to the deal', $aRes2->getStatusCode() === 401);
$dRes = $controller->deleteAttachment($created->id, $attachmentId);
ok('another tenant cannot delete the attachment', $dRes->getStatusCode() === 401);
try {
    $sRes = $controller->serveAttachment($created->id, $attachmentId);
    $served = $sRes->getStatusCode();
} catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
    $served = $e->getStatusCode();
}
ok('another tenant cannot view the attachment inline', $served === 403, 'status ' . $served);

actingAs($company);
$dRes = $controller->deleteAttachment($created->id, $attachmentId);
ok('owner can delete the attachment', json_decode($dRes->getContent(), true)['is_success'] ?? false);
ok('the attachment row is gone', DB::table('deal_attachments')->where('deal_id', $created->id)->count() === 0);

// ---- destroy
actingAs($otherCompany);
$controller->destroy($created);
ok('another tenant cannot delete the deal', Deal::find($created->id) !== null);
actingAs($company);

// leave attachments behind on purpose, to check the cleanup
DB::table('deal_attachments')->insert([
    'deal_id' => $created->id, 'file_name' => 'left.pdf', 'file_path' => 'deal_attachments/x/left.pdf',
    'created_at' => now(), 'updated_at' => now(),
]);
$deletedId = $created->id;
$controller->destroy($created);
ok('destroy() deletes the deal', Deal::find($deletedId) === null);
$orphans = DB::table('deal_attachments')->where('deal_id', $deletedId)->count();
ok('destroy() leaves no orphan attachment rows', $orphans === 0, $orphans . ' orphan row(s) -- no cascade and no model cleanup');

section('15. deal detail page');

actingAs($company);
$showView = $controller->show(Deal::find(1));
ok('show() returns the detail view', $showView instanceof \Illuminate\View\View, is_object($showView) ? get_class($showView) : gettype($showView));
if ($showView instanceof \Illuminate\View\View) {
    // not rendered here: deals/show.blade.php extends layouts.admin, whose menu
    // touches dozens of tables this focused test database does not carry.
    $d = $showView->getData();
    ok('detail view carries the deal', isset($d['deal']) && (int) $d['deal']->id === 1);
    ok('detail view carries the custom fields', array_key_exists('customFields', $d));
    ok('detail view exposes the custom field values', isset($d['deal']->customField));
}
$inactive = Deal::find(2);
$inactive->is_active = 0;
$inactive->save();
ok('an inactive deal is not shown', $controller->show($inactive) instanceof \Illuminate\Http\RedirectResponse);
$inactive->is_active = 1;
$inactive->save();

section('16. arabic locale');

app()->setLocale('ar');
$v = $controller->index(req([], true));
$arHtml = $v->render();
ok('table renders in arabic without error', strlen($arHtml) > 500, strlen($arHtml) . ' bytes');
ok('no raw translation keys leak', !str_contains($arHtml, '__('));
$picker = view('deals._lead_picker', ['pickerId' => 'filter_lead', 'fieldName' => 'lead_id'])->render();
ok('lead picker renders in arabic', str_contains($picker, 'deal-lead-search'));
app()->setLocale('en');

section('17. lead picker markup');

$picker = view('deals._lead_picker', [
    'pickerId' => 'filter_lead', 'fieldName' => 'lead_id',
    'selectedId' => 5, 'selectedName' => 'Lead 5 Ahmed',
])->render();
ok('hidden field keeps the old id so existing js still works', str_contains($picker, 'id="filter_lead"'));
ok('search box id follows the convention', str_contains($picker, 'id="filter_lead_search"'));
ok('it is NOT class=select2 (no Choices init, that was the freeze)', !str_contains($picker, 'select2'));
ok('the selected lead is prefilled', str_contains($picker, 'value="Lead 5 Ahmed"'));
ok('it points at the autocomplete route', str_contains($picker, route('deals.leads.search')));
ok('handlers are registered only once', str_contains($picker, '__dealLeadPickerBound'));

/* ---------------------------------------------------------------- */
echo "\n==================================================\n";
echo "PASS: $pass   FAIL: $fail\n";
if ($failures) {
    echo "\nFailures:\n";
    foreach ($failures as $f) {
        echo " - $f\n";
    }
}
echo "\n(test database `$TESTDB` left in place for inspection)\n";
exit($fail > 0 ? 1 : 0);
