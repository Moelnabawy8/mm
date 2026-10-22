<?php
/**
 * audit-missing-permissions.php
 * ------------------------------------------------------------------
 * بيقارن كل صلاحية الكود بينادي عليها (can / @can / Gate::check)
 * بجدول `permissions` في الداتابيز، ويطلع اللي **مش موجود كسجل**.
 *
 * ليه ده مهم:
 *   لو الصلاحية مش موجودة كسجل، فـ:
 *     - مستحيل أي دور يمسكها
 *     - مش بتظهر كـ checkbox في مودال /roles
 *     - و can('...') بترجع false دايمًا -> الزرار مختفي / Permission denied
 *   وده بيبان كأن "الصلاحيات كلها متدية بس الزرار مش ظاهر".
 *
 * قراءة فقط — مش بيكتب أي حاجة.
 *   php audit-missing-permissions.php
 * ------------------------------------------------------------------
 */

function line($s = '') { echo $s . PHP_EOL; }
function ok($s)   { line("  [OK]   $s"); }
function bad($s)  { line("  [BAD]  $s"); }
function warn($s) { line("  [WARN] $s"); }
function hr()     { line(str_repeat('-', 72)); }

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;

hr();
line("  فحص الصلاحيات الناقصة من جدول permissions");
line("  DB: " . DB::connection()->getDatabaseName());
hr();

// ---- 1) اجمع كل الصلاحيات اللي الكود بينادي عليها ----------------------
$roots = ['app', 'resources/views'];
$found = [];
$patterns = [
    '/\bcan\(\s*[\'"]([a-z][a-z0-9 &\-]{2,60})[\'"]/i',
    '/@can\(\s*[\'"]([a-z][a-z0-9 &\-]{2,60})[\'"]/i',
    '/Gate::check\(\s*[\'"]([a-z][a-z0-9 &\-]{2,60})[\'"]/i',
];

$scan = function ($dir) use (&$scan, $patterns, &$found) {
    foreach (@scandir($dir) ?: [] as $e) {
        if ($e === '.' || $e === '..') { continue; }
        $p = "$dir/$e";
        if (is_dir($p)) { $scan($p); continue; }
        if (!str_ends_with($p, '.php')) { continue; }
        $src = @file_get_contents($p);
        if ($src === false) { continue; }
        foreach ($patterns as $re) {
            if (preg_match_all($re, $src, $m)) {
                foreach ($m[1] as $name) {
                    $name = trim($name);
                    // استبعاد نتايج مش صلاحيات (لازم تبدأ بفعل معروف)
                    if (preg_match('/^(manage|create|edit|delete|view|show|add|move|import|export|duplicate|reply|send|download|approve|assign)\b/i', $name)) {
                        $found[$name][$p] = true;
                    }
                }
            }
        }
    }
};
foreach ($roots as $r) { if (is_dir(__DIR__ . "/$r")) { $scan(__DIR__ . "/$r"); } }

ksort($found);
line();
line("[1] صلاحيات الكود بينادي عليها: " . count($found));

// ---- 2) قارن بالداتابيز ------------------------------------------------
$inDb = Permission::pluck('name')->flip();
$missing = [];
foreach ($found as $name => $files) {
    if (!isset($inDb[$name])) { $missing[$name] = array_keys($files); }
}

line("         موجودة في جدول permissions : " . (count($found) - count($missing)));
line("         إجمالي سجلات الجدول         : " . count($inDb));

line();
line("[2] النتيجة");
if (!$missing) {
    ok("مفيش صلاحية ناقصة — كل اللي الكود بيطلبه موجود كسجل.");
    warn("يبقى سبب اختفاء الزرار حاجة تانية (gate على الـ type، أو الباقة).");
} else {
    bad("فيه " . count($missing) . " صلاحية الكود بيطلبها ومش موجودة في الداتابيز:");
    line();
    foreach ($missing as $name => $files) {
        line(sprintf("    %-42s", $name));
        foreach (array_slice($files, 0, 2) as $f) {
            line("        ← " . str_replace(__DIR__ . '/', '', $f));
        }
    }
    line();
    warn("دي السبب: can('...') بترجع false دايمًا لأي دور، فالزرار مش بيظهر");
    warn("ومش بتظهر كـ checkbox في مودال /roles.");
    line();
    warn("متضفهمش بإيدك — ابعتلي القايمة دي وهعملك migration يضيفهم");
    warn("ويربطهم بالأدوار الصح، عشان ما يضيعوش مع أول deploy.");
}

line();
hr();
line("  قراءة فقط — مكتبش أي حاجة.  امسحه بعد الاستخدام: rm audit-missing-permissions.php");
hr();
line();
