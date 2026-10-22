<?php
/**
 * diagnose-pr-menu.php
 * ------------------------------------------------------------------
 * تشخيص سبب عدم ظهور "Public Relations" في الـ sidebar، مع إصلاح
 * اختياري وآمن (idempotent) للجزء اللي ممكن يتصلح تلقائيًا.
 *
 * تشغيل التشخيص فقط (للقراءة فقط - مفيش أي تعديل):
 *   php diagnose-pr-menu.php
 *
 * تشغيل مع الإصلاح التلقائي:
 *   php diagnose-pr-menu.php --fix
 *
 * الإصلاح التلقائي بيعمل بس اتنين حاجة آمنة تمامًا:
 *   1) لو modules_statuses.json ناقصه PublicRelations => true بيضيفها.
 *   2) لو bootstrap/cache/modules.php قديم (cache قبل الموديول) بيمسحه.
 *   3) لو مفيش أي Role عنده صلاحية "manage pr entity"، بياخد كل Role
 *      عنده "manage deal" حاليًا ويدّيله صلاحيات الـ PR الخمسة كمان
 *      (تقدر تلغيها بعدين من شاشة Roles & Permissions عادي).
 * ------------------------------------------------------------------
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\Plan;
use App\Models\User;

$fix = in_array('--fix', $argv, true);

function line($s = '')
{
    echo $s . PHP_EOL;
}
function ok($s)
{
    line("  [OK]   $s");
}
function warn($s)
{
    line("  [WARN] $s");
}
function fixed($s)
{
    line("  [FIX]  $s");
}
function info($s)
{
    line("  [INFO] $s");
}

line('=== PR Module Diagnostic ===');
line($fix ? '(fix mode enabled)' : '(read-only — pass --fix to apply the safe automatic fixes)');
line();

// 1) modules_statuses.json ------------------------------------------------
line('1) Module status (modules_statuses.json)');
$statusFile = __DIR__ . '/modules_statuses.json';
if (!is_file($statusFile)) {
    warn('modules_statuses.json not found at repo root!');
} else {
    $statuses = json_decode(file_get_contents($statusFile), true) ?? [];
    if (!empty($statuses['PublicRelations'])) {
        ok('PublicRelations is enabled');
    } else {
        warn('PublicRelations is missing or false — this alone would explain everything below.');
        if ($fix) {
            $statuses['PublicRelations'] = true;
            file_put_contents($statusFile, json_encode($statuses, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            fixed('set "PublicRelations": true in modules_statuses.json');
        }
    }
}
line();

// 2) stale nwidart provider cache -----------------------------------------
line('2) Cached module providers (bootstrap/cache/modules.php)');
$cacheFile = __DIR__ . '/bootstrap/cache/modules.php';
if (is_file($cacheFile)) {
    $cached = include $cacheFile;
    $hasProvider = collect($cached['providers'] ?? [])->contains(fn ($p) => str_contains($p, 'PublicRelations'));
    if ($hasProvider) {
        ok('PublicRelations providers are present in the cached manifest');
    } else {
        warn('bootstrap/cache/modules.php exists but does NOT list PublicRelations — stale cache.');
        if ($fix) {
            @unlink($cacheFile);
            fixed('deleted stale bootstrap/cache/modules.php (Laravel regenerates it on the next request)');
        }
    }
} else {
    ok('no cache file present — will be generated fresh on next request');
}
line();

// 3) DB tables -------------------------------------------------------------
line('3) Database tables');
foreach (['pr_entities', 'pr_fields', 'pr_field_values', 'pr_interaction_logs', 'pr_milestones', 'pr_attachments'] as $table) {
    Schema::hasTable($table) ? ok("table `$table` exists") : warn("table `$table` MISSING — migrations did not run correctly");
}
line();

// 4) Routes registered -------------------------------------------------------
line('4) Web routes registered');
foreach (['pr.index', 'pr.show', 'pr.field.index', 'pr.milestone.store'] as $routeName) {
    Route::has($routeName)
        ? ok("route `$routeName` is registered")
        : warn("route `$routeName` NOT registered — the module's service providers are not being loaded (see section 1 & 2 above)");
}
line();

// 5) Permissions exist in DB -------------------------------------------------
line('5) Permissions');
$permNames = ['manage pr entity', 'create pr entity', 'edit pr entity', 'delete pr entity', 'manage pr custom field'];
$permissions = Permission::whereIn('name', $permNames)->get()->keyBy('name');
foreach ($permNames as $name) {
    $permissions->has($name)
        ? ok("permission `$name` exists in DB")
        : warn("permission `$name` MISSING — re-run: php artisan module:seed PublicRelations");
}
line();

// 6) Which roles actually have "manage pr entity"? (this gates the sidebar link)
line('6) Role assignment — this is what actually controls sidebar visibility');
$rolesWithPr = $permissions->has('manage pr entity')
    ? Role::whereHas('permissions', fn ($q) => $q->where('name', 'manage pr entity'))->get()
    : collect();
$rolesWithDeal = Role::whereHas('permissions', fn ($q) => $q->where('name', 'manage deal'))->get();

if ($rolesWithPr->isEmpty()) {
    warn('NO role currently has "manage pr entity" — this is almost certainly why nobody sees the sidebar link.');
    info('Roles that already manage Deals (closest candidates to also get PR access): '
        . ($rolesWithDeal->isEmpty() ? '(none found)' : $rolesWithDeal->pluck('name')->implode(', ')));

    if ($fix && $rolesWithDeal->isNotEmpty()) {
        foreach ($rolesWithDeal as $role) {
            foreach ($permNames as $name) {
                if ($permissions->has($name) && !$role->hasPermissionTo($name)) {
                    $role->givePermissionTo($name);
                }
            }
        }
        fixed('attached the 5 PR permissions to role(s): ' . $rolesWithDeal->pluck('name')->implode(', '));
    } elseif (!$fix) {
        info('Re-run with --fix to attach these permissions automatically to the same roles that manage Deals — or do it manually via the Roles & Permissions screen.');
    }
} else {
    ok('Roles with "manage pr entity": ' . $rolesWithPr->pluck('name')->implode(', '));
}
line();

// 7) CRM plan flag — gates the whole "CRM System" section, not just PR ------
line('7) CRM plan flag (gates the entire "CRM System" sidebar section)');
try {
    $companies = User::where('type', 'company')->get(['id', 'name', 'plan']);
    if ($companies->isEmpty()) {
        info('no users with type=company found');
    }
    foreach ($companies as $company) {
        $plan = Plan::getPlan($company->plan);
        $crmOn = !empty($plan) && (int) $plan->crm === 1;
        $crmOn
            ? ok("company #{$company->id} ({$company->name}): crm plan flag = ON")
            : warn("company #{$company->id} ({$company->name}): crm plan flag = OFF — the whole CRM section (Leads/Deals/PR) is hidden for this company regardless of permissions");
    }
} catch (\Throwable $e) {
    info('could not check plan flags automatically: ' . $e->getMessage());
}
line();

line('=== Done ===');
if (!$fix) {
    line('Nothing was changed. Re-run with --fix to apply the safe automatic fixes reported above.');
}
