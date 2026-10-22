<?php
/**
 * create-myca-company.php
 * ------------------------------------------------------------------
 * ينشئ شركة (company) جديدة باسم "MYCA International" مع كل الداتا
 * الافتراضية بالظبط زي ما بيعمل UserController::store() لما يكون
 * المستخدم super admin.
 *
 * كيفية التشغيل على السيرفر (من جوه مجلد اللارافيل / public_html):
 *   php create-myca-company.php
 *
 * السكريبت idempotent — لو الشركة موجودة بنفس الإيميل بيقف ومش بيكرر.
 * ------------------------------------------------------------------
 */

// ==== الإعدادات ====
const COMPANY_NAME = 'MYCA International';
const COMPANY_EMAIL = 'admin@myca.com';
const COMPANY_PASSWORD = 'Myca@2026';

// ---- helpers -------------------------------------------------------------
function line($s = '') { echo $s . PHP_EOL; }
function ok($s)    { line("  [OK]   $s"); }
function warn($s)  { line("  [WARN] $s"); }
function step($s)  { line("  [..]   $s"); }
function fail($s)  { line("  [FAIL] $s"); exit(1); }
function hr()      { line(str_repeat('-', 66)); }

// ---- bootstrap Laravel ---------------------------------------------------
$root = __DIR__;
if (!file_exists($root . '/vendor/autoload.php') || !file_exists($root . '/bootstrap/app.php')) {
    fail("مش لاقي vendor/autoload.php أو bootstrap/app.php — شغّل السكربت من جوه مجلد اللارافيل.");
}

require $root . '/vendor/autoload.php';
$app = require_once $root . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Plan;
use App\Models\Utility;
use App\Models\GenerateOfferLetter;
use App\Models\ExperienceCertificate;
use App\Models\JoiningLetter;
use App\Models\NOC;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

hr();
line("  إنشاء شركة: " . COMPANY_NAME);
line("  DB: " . DB::connection()->getDatabaseName());
hr();

// ---- 1) فحوصات قبل الإنشاء ----------------------------------------------
line();
line("[1] فحوصات مبدئية");

if (User::where('email', COMPANY_EMAIL)->exists()) {
    $existing = User::where('email', COMPANY_EMAIL)->first();
    warn("فيه مستخدم موجود بالفعل بالإيميل ده (id={$existing->id}, type={$existing->type}).");
    warn("مش هعمل حاجة. غيّر COMPANY_EMAIL فوق لو عايز تعمل شركة تانية.");
    exit(0);
}
ok("الإيميل " . COMPANY_EMAIL . " متاح");

$superAdmin = User::where('type', 'super admin')->orderBy('id')->first();
if (!$superAdmin) {
    fail("مفيش super admin في جدول users — مش هقدر أحدد created_by.");
}
ok("Super admin: #{$superAdmin->id} ({$superAdmin->email})");

$plan = Plan::orderBy('id')->first();
if (!$plan) {
    fail("مفيش أي Plan في جدول plans — UserController::store() بيستخدم Plan::first()->id، لازم تعمل باقة الأول.");
}
ok("Plan افتراضي: #{$plan->id} ({$plan->name})");

$companyRole = Role::where('name', 'company')->first();
if (!$companyRole) {
    fail("مفيش role اسمه 'company' في جدول roles.");
}
ok("Role: 'company' (#{$companyRole->id})");

// ---- 2) إنشاء المستخدم ---------------------------------------------------
line();
line("[2] إنشاء الـ company user");

$creatorId = $superAdmin->creatorId();

$default_language = DB::table('settings')->select('value')
    ->where('name', 'default_language')
    ->where('created_by', $creatorId)
    ->first();

$settings = Utility::settings();

do {
    $code = rand(100000, 999999);
} while (User::where('referral_code', $code)->exists());

DB::beginTransaction();
try {
    $user = new User();
    $user->name             = COMPANY_NAME;
    $user->email            = COMPANY_EMAIL;
    $user->password         = \Hash::make(COMPANY_PASSWORD);
    $user->type             = 'company';
    $user->default_pipeline = 1;
    $user->lang             = !empty($default_language) ? $default_language->value : 'en';
    $user->referral_code    = $code;
    $user->created_by       = $creatorId;
    $user->plan             = $plan->id;
    $user->is_enable_login  = 1;   // اللوجين مفعّل
    $user->email_verified_at = (isset($settings['email_verification']) && $settings['email_verification'] == 'on')
        ? null
        : date('Y-m-d H:i:s');

    $user->save();
    ok("اتعمل user #{$user->id} — type=company, plan={$plan->id}, referral_code={$code}");

    $user->assignRole($companyRole);
    ok("اتربط بـ role 'company'");

    DB::commit();
} catch (\Throwable $e) {
    DB::rollBack();
    fail("فشل إنشاء المستخدم: " . $e->getMessage());
}

// ---- 3) الداتا الافتراضية ------------------------------------------------
line();
line("[3] توليد الداتا الافتراضية للشركة");

$tasks = [
    'Email templates'        => fn() => $user->userDefaultDataRegister($user->id),
    'Warehouse'              => fn() => $user->userWarehouseRegister($user->id),
    'Bank account (cash)'    => fn() => $user->userDefaultBankAccount($user->id),
    'Chart of account types' => fn() => Utility::chartOfAccountTypeData($user->id),
    'Chart of accounts'      => fn() => Utility::chartOfAccountData1($user->id),
    'Pipeline + lead/deal stages' => fn() => Utility::pipeline_lead_deal_Stage($user->id),
    'Project task stages'    => fn() => Utility::project_task_stages($user->id),
    'Labels'                 => fn() => Utility::labels($user->id),
    'Sources'                => fn() => Utility::sources($user->id),
    'Job stages'             => fn() => Utility::jobStage($user->id),
    'Offer letter template'  => fn() => GenerateOfferLetter::defaultOfferLetterRegister($user->id),
    'Experience certificate' => fn() => ExperienceCertificate::defaultExpCertificatRegister($user->id),
    'Joining letter'         => fn() => JoiningLetter::defaultJoiningLetterRegister($user->id),
    'NOC certificate'        => fn() => NOC::defaultNocCertificateRegister($user->id),
];

$failed = [];
foreach ($tasks as $label => $fn) {
    try {
        $fn();
        ok($label);
    } catch (\Throwable $e) {
        warn("$label -> " . $e->getMessage());
        $failed[] = $label;
    }
}

// ---- 4) الخلاصة ----------------------------------------------------------
line();
hr();
line("  تم إنشاء الشركة");
hr();
line("  Name     : " . COMPANY_NAME);
line("  Email    : " . COMPANY_EMAIL);
line("  Password : " . COMPANY_PASSWORD);
line("  User ID  : " . $user->id);
line("  Plan     : #{$plan->id} ({$plan->name})");
line("  Login    : " . rtrim(config('app.url'), '/') . "/login");
hr();

if ($failed) {
    line();
    warn("الخطوات دي فشلت — راجعها من لوحة الشركة يدويًا:");
    foreach ($failed as $f) { line("         - $f"); }
    line();
    warn("المستخدم اتعمل بنجاح رغم كده وتقدر تدخل بيه.");
}

line();
line("  امسح السكربت من السيرفر بعد التشغيل (فيه الباسورد جوّاه):");
line("    rm create-myca-company.php");
line();
