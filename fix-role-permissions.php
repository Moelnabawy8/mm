<?php
/**
 * fix-role-permissions.php
 * ------------------------------------------------------------------
 * يفتح صلاحيات HRM (خصوصًا edit) لمستخدم زي حساب الـ HR.
 *
 * بيصلّح طبقتين، والاتنين لازمين:
 *   1) دور المستخدم نفسه  -> عشان يقدر يعمل edit فعلاً في الصفحات
 *   2) دور الشركة الأم    -> عشان الصلاحيات دي تظهر أصلاً في مودال
 *      /roles؛ الشركة مش بتقدر تعرض صلاحية هي نفسها مش ماسكاها
 *      (RoleController::edit سطر ~110)
 *
 * مش بيعمل صلاحيات جديدة — بيوصّل الموجود بس. الصلاحيات الجديدة
 * مكانها migration.
 *
 *   php fix-role-permissions.php hr@my-communication.net
 *   php fix-role-permissions.php hr@my-communication.net --fix
 * ------------------------------------------------------------------
 */

$args  = array_slice($argv, 1);
$doFix = in_array('--fix', $args, true);
$email = null;
foreach ($args as $a) { if ($a !== '--fix') { $email = $a; } }

function line($s = '') { echo $s . PHP_EOL; }
function ok($s)   { line("  [OK]   $s"); }
function bad($s)  { line("  [BAD]  $s"); }
function warn($s) { line("  [WARN] $s"); }
function fix($s)  { line("  [FIX]  $s"); }
function fail($s) { line("  [FAIL] $s"); exit(1); }
function hr()     { line(str_repeat('-', 72)); }

if (!$email) { fail("استخدام: php fix-role-permissions.php <email> [--fix]"); }

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

hr();
line("  فتح صلاحيات HRM لـ: $email");
line("  DB: " . DB::connection()->getDatabaseName() . "   |   وضع: " . ($doFix ? "إصلاح" : "قراءة فقط"));
hr();

$user = User::where('email', $email)->orderBy('id')->first();
if (!$user) { fail("مفيش user بالإيميل ده."); }

line();
line("[1] الحساب");
line("         id={$user->id}   type={$user->type}   created_by={$user->created_by}");
$userRoles = $user->roles;
if ($userRoles->isEmpty()) { bad("الحساب مش متعيّن له أي دور — لازم تديله دور الأول من /users."); }
else { ok("أدواره: " . $userRoles->pluck('name')->implode(', ')); }

$company = User::find($user->creatorId());
if ($company && $company->id !== $user->id) {
    line("         الشركة الأم: #{$company->id} ({$company->email})  أدوارها: " . $company->roles->pluck('name')->implode(', '));
}

// نفس قايمة الموديولز اللي في resources/views/role/edit.blade.php
$modules = ['hrm dashboard','employee','employee profile','department','designation','branch',
 'document type','document','payslip type','allowance','commission','allowance option',
 'loan option','deduction option','loan','saturation deduction','other payment','overtime',
 'set salary','pay slip','company policy','appraisal','goal tracking','goal type','indicator',
 'event','meeting','training','trainer','training type','award','award type','resignation',
 'travel','promotion','complaint','warning','termination','termination type','job application',
 'job application note','job onBoard','job category','job','job stage','custom question',
 'interview schedule','career','estimation','holiday','transfer','announcement','leave',
 'leave type','attendance'];
$verbs = ['view','add','move','manage','create','edit','delete','show'];

$existing = [];
foreach ($modules as $m) {
    foreach ($verbs as $v) {
        if (Permission::where('name', "$v $m")->exists()) { $existing[] = "$v $m"; }
    }
}
line();
line("         إجمالي صلاحيات HRM الموجودة في الداتابيز: " . count($existing));

// الأدوار اللي هتتصلح: أدوار المستخدم + أدوار الشركة الأم
$targets = [];
foreach ($userRoles as $r) { $targets[$r->id] = ['role' => $r, 'why' => 'دور المستخدم — عشان يقدر يعمل edit فعليًا']; }
if ($company && $company->id !== $user->id) {
    foreach ($company->roles as $r) {
        if (!isset($targets[$r->id])) { $targets[$r->id] = ['role' => $r, 'why' => 'دور الشركة — عشان الصلاحية تظهر في مودال /roles']; }
    }
}
if (!$targets) { fail("مفيش أدوار أقدر أشتغل عليها."); }

line();
line("[2] الفحص لكل دور");
$plan = [];
foreach ($targets as $t) {
    $role = $t['role'];
    $held = $role->permissions->pluck('name')->all();
    $miss = array_values(array_diff($existing, $held));
    line();
    line("         الدور: \"{$role->name}\"  ({$t['why']})");
    line("         ماسك " . (count($existing) - count($miss)) . " من " . count($existing));
    if (!$miss) { ok("مفيش ناقص."); continue; }
    bad("ناقص " . count($miss) . " صلاحية:");
    $byModule = [];
    foreach ($miss as $p) {
        foreach ($modules as $m) {
            if (str_ends_with($p, ' ' . $m)) { $byModule[$m][] = explode(' ', $p)[0]; break; }
        }
    }
    foreach ($byModule as $m => $vs) { line(sprintf("           %-24s %s", $m, implode(', ', $vs))); }
    $plan[] = [$role, $miss];
}

line();
line("[3] " . ($doFix ? "التطبيق" : "المطلوب"));
if (!$plan) {
    ok("مفيش حاجة تتعمل — كل الأدوار كاملة.");
    warn("لو الـ edit لسه مش ظاهر، يبقى الصفحة بتتحكم بالـ type مش بالصلاحية — قوللي وأفحصها.");
} elseif ($doFix) {
    foreach ($plan as [$role, $miss]) {
        $role->givePermissionTo($miss);
        fix("\"{$role->name}\" +" . count($miss) . " صلاحية");
    }
    Artisan::call('permission:cache-reset');
    fix("اتمسح كاش الصلاحيات");
    line();
    ok("خلص. اعمل logout و login بحساب الـ HR.");
} else {
    foreach ($plan as [$role, $miss]) { line("         - \"{$role->name}\" +" . count($miss) . " صلاحية"); }
    line();
    warn("للتطبيق:  php fix-role-permissions.php $email --fix");
}

line();
hr();
line("  بيوصّل صلاحيات موجودة بس — مش بيعمل صلاحيات جديدة (دي مكانها migration).");
hr();
line();
