<?php
/**
 * fix-myca-login.php
 * ------------------------------------------------------------------
 * يشخّص ويصلّح مشكلة "These credentials do not match our records."
 * لحساب الشركة admin@myca.com
 *
 * التشغيل (من جوه مجلد اللارافيل / public_html):
 *   php fix-myca-login.php            <- تشخيص فقط (مش بيغيّر حاجة)
 *   php fix-myca-login.php --fix      <- تشخيص + إصلاح
 * ------------------------------------------------------------------
 */

const TARGET_EMAIL = 'admin@myca.com';
const TARGET_PASSWORD = 'Myca@2026';

$doFix = in_array('--fix', $argv, true);

function line($s = '') { echo $s . PHP_EOL; }
function ok($s)   { line("  [OK]   $s"); }
function bad($s)  { line("  [BAD]  $s"); }
function warn($s) { line("  [WARN] $s"); }
function fix($s)  { line("  [FIX]  $s"); }
function fail($s) { line("  [FAIL] $s"); exit(1); }
function hr()     { line(str_repeat('-', 68)); }

$root = __DIR__;
if (!file_exists($root . '/vendor/autoload.php')) {
    fail("شغّل السكربت من جوه مجلد اللارافيل.");
}
require $root . '/vendor/autoload.php';
$app = require_once $root . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

hr();
line("  تشخيص لوجين: " . TARGET_EMAIL);
line("  DB: " . DB::connection()->getDatabaseName() . "   |   وضع: " . ($doFix ? "إصلاح (--fix)" : "قراءة فقط"));
hr();

// ---- 1) هل فيه أكتر من user بنفس الإيميل؟ -------------------------------
line();
line("[1] البحث عن الحساب");

$users = User::where('email', TARGET_EMAIL)->orderBy('id')->get();
if ($users->isEmpty()) {
    fail("مفيش أي user بالإيميل ده خالص — الحساب اتمسح. شغّل create-myca-company.php تاني.");
}
if ($users->count() > 1) {
    bad("فيه " . $users->count() . " حسابات بنفس الإيميل! ده بيخلي Auth::attempt يجرّب الأول بس.");
    foreach ($users as $u) {
        line("         id={$u->id} type={$u->type} created_by={$u->created_by} created_at={$u->created_at}");
    }
    warn("لازم تمسح المكرر يدويًا — السكربت مش هيمسح حسابات من نفسه.");
} else {
    ok("حساب واحد بس — id={$users->first()->id}");
}

$user = $users->first();

// ---- 2) حالة الحساب -----------------------------------------------------
line();
line("[2] حالة الحساب (id={$user->id})");

$flags = [
    'type'              => ['company', 'لازم يكون company'],
    'is_active'         => [1, '0 = "Your Account is deactive by admin"'],
    'delete_status'     => [1, '0 = "Your Account is deleted by admin"'],
    'is_enable_login'   => [1, '0 = "Your Account is disable from company"'],
];
$needFix = [];
foreach ($flags as $col => [$expected, $note]) {
    if (!Schema::hasColumn('users', $col)) { warn("العمود $col مش موجود في الجدول — تخطّي"); continue; }
    $val = $user->{$col};
    if ((string) $val === (string) $expected) {
        ok(sprintf("%-16s = %-10s", $col, var_export($val, true)));
    } else {
        bad(sprintf("%-16s = %-10s (المتوقع: $expected)  <- $note", $col, var_export($val, true)));
        $needFix[$col] = $expected;
    }
}
line("         email_verified_at = " . var_export($user->email_verified_at, true));
line("         created_by        = " . var_export($user->created_by, true));
line("         plan              = " . var_export($user->plan, true));
line("         plan_expire_date  = " . var_export($user->plan_expire_date, true));

// ---- 3) الـ password hash — ده بيت القصيد -------------------------------
line();
line("[3] فحص الـ password");

$hash = (string) $user->password;
if ($hash === '') {
    bad("عمود password فاضي تمامًا — مستحيل تعمل لوجين.");
    $needFix['password'] = true;
} else {
    $info = password_get_info($hash);
    line("         hash algo   : " . ($info['algoName'] ?: 'unknown') . "   (طول: " . strlen($hash) . ")");
    if (($info['algoName'] ?? '') === 'unknown') {
        bad("الـ hash مش بصيغة bcrypt صحيحة — غالبًا اتكتب plain text أو اتخرب.");
        $needFix['password'] = true;
    } elseif (Hash::check(TARGET_PASSWORD, $hash)) {
        ok("الباسورد '" . TARGET_PASSWORD . "' مطابق للـ hash. الباسورد مش هو المشكلة.");
    } else {
        bad("الباسورد '" . TARGET_PASSWORD . "' *مش* مطابق للـ hash — حد غيّر الباسورد.");
        $needFix['password'] = true;
    }
}

// ---- 4) الـ rate limiter ------------------------------------------------
line();
line("[4] الـ rate limiter (5 محاولات فاشلة = قفل مؤقت)");
$cleared = 0;
foreach (['', request()->ip()] as $ip) {
    $key = strtolower(TARGET_EMAIL) . '|' . $ip;
    if (RateLimiter::attempts($key) > 0) {
        warn("فيه محاولات مسجلة على المفتاح: $key");
        if ($doFix) { RateLimiter::clear($key); $cleared++; }
    }
}
if ($cleared) { fix("اتمسح $cleared مفتاح rate-limit"); }
else { ok("مفيش قفل واضح (ملاحظة: المفتاح مربوط بالـ IP فمش كل الحالات بتظهر من الـ CLI)"); }

// ---- 5) الإصلاح ---------------------------------------------------------
line();
line("[5] " . ($doFix ? "تطبيق الإصلاح" : "الإصلاح المطلوب"));

if (empty($needFix)) {
    ok("مفيش حاجة تتصلّح — الحساب سليم والباسورد مضبوط.");
    line();
    warn("لو لسه بيرفض اللوجين، غالبًا بتكتب الإيميل/الباسورد غلط، أو");
    warn("الكود على السيرفر مختلف عن اللوكال. ابعتلي:");
    warn("  tail -n 60 storage/logs/laravel.log");
} else {
    foreach ($needFix as $col => $val) {
        if ($col === 'password') {
            if ($doFix) { $user->password = Hash::make(TARGET_PASSWORD); fix("اتعمل reset للباسورد -> " . TARGET_PASSWORD); }
            else { line("         - reset الباسورد -> " . TARGET_PASSWORD); }
        } else {
            if ($doFix) { $user->{$col} = $val; fix("$col -> $val"); }
            else { line("         - $col : {$user->{$col}} -> $val"); }
        }
    }
    if (empty($user->email_verified_at)) {
        if ($doFix) { $user->email_verified_at = date('Y-m-d H:i:s'); fix("email_verified_at اتظبط"); }
        else { line("         - email_verified_at -> دلوقتي"); }
    }

    if ($doFix) {
        $user->save();
        line();
        ok("اتحفظ. جرّب اللوجين دلوقتي.");
    } else {
        line();
        warn("ده تشخيص بس. عشان تطبّق: php fix-myca-login.php --fix");
    }
}

line();
hr();
line("  Login : " . rtrim(config('app.url'), '/') . "/login");
line("  Email : " . TARGET_EMAIL);
line("  Pass  : " . TARGET_PASSWORD);
hr();
line();
line("  امسح السكربت بعد الاستخدام:  rm fix-myca-login.php");
line();
