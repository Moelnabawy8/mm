<?php
/**
 * fix-myca-hrm.php
 * ------------------------------------------------------------------
 * يشخّص ويفتح موديول الموارد البشرية (HRM) لحساب شركة معيّن.
 *
 * القايمة بتتخفي لسببين مستقلين (resources/views/partials/admin/menu.blade.php:883):
 *   1) الباقة المربوطة بالحساب عندها hrm = 0        <- بتخفي القايمة كلها
 *   2) الدور مش معاه صلاحيات HR                      <- بتخفي عناصر منها
 * السكربت بيفحص الاتنين.
 *
 *   php fix-myca-hrm.php          <- تشخيص فقط
 *   php fix-myca-hrm.php --fix    <- يدي الصلاحيات الناقصة للدور
 *   php fix-myca-hrm.php --fix --plan   <- كمان يفعّل modules الباقة
 * ------------------------------------------------------------------
 */

const TARGET_EMAIL = 'admin@myca.com';

$doFix   = in_array('--fix', $argv, true);
$doPlan  = in_array('--plan', $argv, true);

function line($s = '') { echo $s . PHP_EOL; }
function ok($s)   { line("  [OK]   $s"); }
function bad($s)  { line("  [BAD]  $s"); }
function warn($s) { line("  [WARN] $s"); }
function fix($s)  { line("  [FIX]  $s"); }
function fail($s) { line("  [FAIL] $s"); exit(1); }
function hr()     { line(str_repeat('-', 70)); }

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Plan;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

hr();
line("  فتح موديول HRM لحساب: " . TARGET_EMAIL);
line("  DB: " . DB::connection()->getDatabaseName() . "   |   وضع: " . ($doFix ? "إصلاح" . ($doPlan ? " + باقة" : "") : "قراءة فقط"));
hr();

$user = User::where('email', TARGET_EMAIL)->orderBy('id')->first();
if (!$user) { fail("مفيش user بالإيميل ده."); }

// ---- 1) الباقة والموديولز ----------------------------------------------
line();
line("[1] الباقة والموديولز — ده السبب الأول لاختفاء القايمة");

$plan = Plan::find($user->plan);
if (!$plan) {
    bad("الحساب مربوط بباقة id={$user->plan} وهي مش موجودة! لازم تربطه بباقة صحيحة.");
} else {
    line("         الباقة: #{$plan->id} \"{$plan->name}\"  (duration: {$plan->duration})");
    $modules = ['crm', 'hrm', 'account', 'project', 'pos'];
    foreach ($modules as $m) {
        $on = (int) ($plan->{$m} ?? 0) === 1;
        $mark = $on ? 'ON ' : 'OFF';
        if ($m === 'hrm') {
            $on ? ok("hrm     = $mark   <- القايمة مفتوحة")
               : bad("hrm     = $mark   <- ده اللي بيخفي HRM بالكامل");
        } else {
            line(sprintf("         %-7s = %s", $m, $mark));
        }
    }

    // نطاق التأثير: مين تاني على نفس الباقة؟
    $shared = User::where('type', 'company')->where('plan', $plan->id)->where('id', '!=', $user->id)->count();
    line();
    if ($shared > 0) {
        warn("فيه $shared شركة تانية على نفس الباقة — تعديل الباقة هيأثر عليهم كلهم.");
    } else {
        ok("مفيش شركات تانية على الباقة دي — تعديلها آمن.");
    }
}

// ---- 2) الأدوار والصلاحيات ---------------------------------------------
line();
line("[2] صلاحيات HR على أدوار الحساب");

$roles = $user->roles;
if ($roles->isEmpty()) {
    bad("الحساب مش متعيّن له أي دور خالص!");
} else {
    line("         الأدوار: " . $roles->pluck('name')->implode(', '));
}

// الصلاحيات اللي الـ blade بيفحصها لفتح قايمة HRM
$gateKeys = [
    'manage employee', 'manage set salary', 'manage pay slip', 'manage leave',
    'manage attendance', 'create attendance', 'manage indicator', 'manage appraisal',
    'manage goal tracking', 'manage training', 'manage trainer', 'manage job',
];
// المجموعة الكاملة لـ HR
$hrPrefixes = ['employee','leave','attendance','payslip','pay slip','set salary','department',
    'designation','branch','award','holiday','resignation','promotion','complaint','warning',
    'termination','transfer','trip','announcement','training','appraisal','indicator',
    'goal tracking','company policy','allowance','commission','loan','deduction',
    'saturation deduction','document','leave type','job'];

$allPerms = Permission::pluck('name')->all();
$hrPerms = array_values(array_filter($allPerms, function ($p) use ($hrPrefixes) {
    foreach ($hrPrefixes as $k) { if (str_contains($p, $k)) return true; }
    return false;
}));

$has = [];
foreach ($gateKeys as $k) { $has[$k] = $user->can($k); }
$missingGate = array_keys(array_filter($has, fn($v) => !$v));

line();
line("         صلاحيات فتح القايمة (محتاج واحدة على الأقل):");
foreach ($has as $k => $v) { line(sprintf("           %s %s", $v ? '[x]' : '[ ]', $k)); }

if (count($missingGate) === count($gateKeys)) {
    bad("مفيش ولا صلاحية من دول — القايمة هتفضل مقفولة حتى لو الباقة مظبوطة.");
} elseif ($missingGate) {
    warn(count($missingGate) . " صلاحية ناقصة من مجموعة فتح القايمة.");
} else {
    ok("كل صلاحيات فتح القايمة موجودة.");
}

$missingHr = array_values(array_filter($hrPerms, fn($p) => !$user->can($p)));
line();
line("         إجمالي صلاحيات HR الموجودة في النظام: " . count($hrPerms));
if ($missingHr) {
    bad("ناقص " . count($missingHr) . " صلاحية: " . implode(', ', array_slice($missingHr, 0, 12)) . (count($missingHr) > 12 ? ' …' : ''));
} else {
    ok("الحساب معاه كل صلاحيات HR.");
}

// ---- 3) الإصلاح ---------------------------------------------------------
line();
line("[3] " . ($doFix ? "تطبيق الإصلاح" : "المطلوب"));

$targetRole = $roles->firstWhere('name', 'company') ?? $roles->first();

if (!$missingHr && $plan && (int) $plan->hrm === 1) {
    ok("مفيش حاجة تتعمل — الباقة والصلاحيات مظبوطين.");
    warn("لو القايمة لسه مش ظاهرة: امسح كاش الصلاحيات والـ views (تحت).");
} else {
    // الصلاحيات
    if ($missingHr) {
        if (!$targetRole) {
            bad("مفيش دور أقدر أضيف عليه الصلاحيات.");
        } elseif ($doFix) {
            $targetRole->givePermissionTo($missingHr);
            fix("اتضاف " . count($missingHr) . " صلاحية للدور \"{$targetRole->name}\"");
        } else {
            line("         - إضافة " . count($missingHr) . " صلاحية للدور \"" . ($targetRole->name ?? '?') . "\"");
        }
    }

    // الباقة
    if ($plan && (int) $plan->hrm !== 1) {
        if ($doFix && $doPlan) {
            $plan->hrm = 1;
            $plan->save();
            fix("الباقة #{$plan->id} \"{$plan->name}\" -> hrm = 1");
        } else {
            line("         - تفعيل hrm على الباقة #{$plan->id} \"{$plan->name}\"  (محتاج --plan)");
        }
    }

    if ($doFix) {
        Artisan::call('permission:cache-reset');
        fix("اتمسح كاش الصلاحيات");
        Artisan::call('view:clear');
        fix("اتمسح كاش الـ views");
        line();
        ok("خلص. اعمل logout و login تاني عشان الـ session تتحدّث.");
    } else {
        line();
        warn("ده تشخيص بس. للتطبيق:  php fix-myca-hrm.php --fix --plan");
    }
}

line();
hr();
line("  بعد التطبيق: logout ثم login على " . rtrim(config('app.url'), '/'));
hr();
line();
line("  امسح السكربت بعد الاستخدام:  rm fix-myca-hrm.php");
line();
