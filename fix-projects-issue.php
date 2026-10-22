<?php
/**
 * fix-projects-issue.php
 * ------------------------------------------------------------------
 * يشخّص ويصلح مشكلة الشاشة السودة في صفحة المشاريع:
 *   "Uncaught TypeError: $(...).tagsinput is not a function"
 * بالإضافة لخطأ الـ favorites (Attempt to read property "id" on null).
 *
 * كيفية التشغيل على السيرفر (من جوه مجلد اللارافيل / public_html):
 *   php fix-projects-issue.php
 *
 * السكريبت:
 *   1) يشخّص الوضع الحالي ويطبع تقرير.
 *   2) ياخد نسخة احتياطية من كل ملف قبل ما يعدّله (.bak_TIMESTAMP).
 *   3) يطبّق الإصلاحات (idempotent — آمن لو اتشغّل أكتر من مرة).
 *   4) يمسح الـ compiled views عشان تعديلات الـ blade تشتغل.
 * ------------------------------------------------------------------
 */

// ---- إعدادات ومساعدات ----------------------------------------------------

$root = getcwd();
$stamp = date('Ymd_His');

function line($s = '') { echo $s . PHP_EOL; }
function ok($s)    { line("  [OK]   $s"); }
function warn($s)  { line("  [WARN] $s"); }
function fix($s)   { line("  [FIX]  $s"); }
function skip($s)  { line("  [SKIP] $s"); }

function backup($file, $stamp) {
    $bak = $file . '.bak_' . $stamp;
    if (@copy($file, $bak)) {
        line("         backup -> $bak");
        return true;
    }
    warn("failed to create backup for $file");
    return false;
}

/** يقرأ آخر $bytes بايت من ملف (tail) من غير ما يحمّل الملف كله. */
function tail_text($file, $bytes) {
    $size = filesize($file);
    $fh = fopen($file, 'rb');
    if ($size > $bytes) { fseek($fh, -$bytes, SEEK_END); }
    $data = fread($fh, max($bytes, 1));
    fclose($fh);
    return $data;
}

/**
 * يدوّر على ملفات PHP error_log في الأماكن الشائعة (cPanel/LiteSpeed)
 * ويطبع آخر السطور — عشان أخطاء الـ fatal/timeout اللي مش بتظهر في laravel.log.
 */
function show_php_error_log($root) {
    $candidates = [];
    foreach ([$root . '/error_log', $root . '/public/error_log'] as $p) {
        if (is_file($p)) { $candidates[] = $p; }
    }
    // ~/logs/* (لوجات الـ cPanel/LiteSpeed)
    $homeLogs = dirname($root) . '/logs';
    if (is_dir($homeLogs)) {
        foreach (glob($homeLogs . '/*') as $g) { if (is_file($g)) { $candidates[] = $g; } }
    }
    if (!$candidates) {
        warn('no PHP error_log found in the usual places (public_html/error_log, ~/logs/).');
        warn('جرّب من cPanel: Metrics > Errors — أو دوّر على ملف اسمه error_log جوّه public_html.');
        return;
    }
    usort($candidates, function ($a, $b) { return filemtime($b) <=> filemtime($a); });
    $pl = $candidates[0];
    line('  newest PHP log: ' . $pl . '  (modified: ' . date('Y-m-d H:i:s', filemtime($pl)) . ')');
    $tail = tail_text($pl, 60 * 1024);
    $lines = array_slice(explode("\n", $tail), -25);
    line('  ------------------------------------------------------------');
    foreach ($lines as $l) { line('  | ' . $l); }
    line('  ------------------------------------------------------------');
}

/** يختبر فتح اتصال TCP بمهلة قصيرة ويقيس الوقت — عشان نعرف مين اللي بيعلّق. */
function probe_tcp($label, $host, $port, $timeout = 5) {
    $t0 = microtime(true);
    $errno = 0; $errstr = '';
    $fp = @fsockopen($host, (int)$port, $errno, $errstr, $timeout);
    $ms = round((microtime(true) - $t0) * 1000);
    if ($fp) {
        fclose($fp);
        ok(sprintf('%s -> CONNECTED in %d ms', $label, $ms));
        return true;
    }
    warn(sprintf('%s -> FAILED after %d ms (%s) %s', $label, $ms, $errno, $errstr ?: 'timeout/blocked'));
    if ($ms >= ($timeout * 1000) - 200) {
        line('         ^^ ده تعليق (timeout) — غالبًا الهوست بيحجب الاتصال الصادر للبورت ده. ده مصدر الـ 32 ثانية.');
    }
    return false;
}

/** يختبر URL (webhook/slack) بـ cURL بمهلة قصيرة ويقيس الوقت. */
function probe_url($label, $url, $timeout = 5) {
    $t0 = microtime(true);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout + 3);
    curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $ms = round((microtime(true) - $t0) * 1000);
    if ($err) {
        warn(sprintf('%s -> FAILED after %d ms (%s)', $label, $ms, $err));
        if (stripos($err, 'timed out') !== false) {
            line('         ^^ ده تعليق (timeout) — ده مصدر بطء الطلب.');
        }
        return false;
    }
    ok(sprintf('%s -> HTTP %d in %d ms', $label, $code, $ms));
    return true;
}

/**
 * وضع probe: يبوّت لارافيل ويختبر اتصال كل قنوات الإشعارات المفعّلة
 * (SMTP / Slack / Telegram / Webhooks) — عشان نحدد مين اللي بيعلّق الطلب.
 */
function run_probe($root) {
    line('== Probe: notification channels connectivity ==');
    try {
        require $root . '/vendor/autoload.php';
        $app = require $root . '/bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        ok('Laravel booted.');
    } catch (\Throwable $e) {
        warn('failed to boot Laravel: ' . $e->getMessage());
        return;
    }

    // 1) إعدادات الميل من .env/config (دي اللي Mail::send بيستخدمها فعليًا)
    line('');
    line('-- .env / config mail --');
    $mailer  = config('mail.default');
    $envHost = config('mail.mailers.smtp.host');
    $envPort = config('mail.mailers.smtp.port');
    $timeoutCfg = config('mail.mailers.smtp.timeout');
    line("  mailer=$mailer  host=$envHost  port=$envPort  timeout=" . var_export($timeoutCfg, true));
    if ($mailer === 'smtp' && !empty($envHost)) {
        probe_tcp("SMTP(.env) $envHost:$envPort", $envHost, $envPort);
    }

    // 2) إعدادات الإشعارات من جدول settings (لكل شركة)
    line('');
    line('-- settings table (per company / created_by) --');
    try {
        $names = ['new_project', 'project_notification', 'telegram_project_notification',
                  'mail_driver', 'mail_host', 'mail_port', 'slack_webhook', 'telegram_accestoken'];
        $rows = \Illuminate\Support\Facades\DB::table('settings')->whereIn('name', $names)->get();
        $by = [];
        foreach ($rows as $r) { $by[$r->created_by][$r->name] = $r->value; }
        if (!$by) { line('  (no relevant settings rows)'); }
        foreach ($by as $cid => $s) {
            $flags = [];
            foreach (['new_project' => 'email', 'project_notification' => 'slack', 'telegram_project_notification' => 'telegram'] as $k => $lbl) {
                if (!empty($s[$k]) && $s[$k] == 1) { $flags[] = $lbl; }
            }
            line("  company created_by=$cid : enabled on create = [" . implode(', ', $flags ?: ['none']) . ']');
            if (in_array('email', $flags) && !empty($s['mail_host'])) {
                probe_tcp("    SMTP(db) {$s['mail_host']}:" . (!empty($s['mail_port']) ? $s['mail_port'] : 587), $s['mail_host'], !empty($s['mail_port']) ? $s['mail_port'] : 587);
            }
            if (in_array('slack', $flags) && !empty($s['slack_webhook'])) {
                probe_url('    Slack webhook', $s['slack_webhook']);
            }
            if (in_array('telegram', $flags) && !empty($s['telegram_accestoken'])) {
                probe_tcp('    Telegram api.telegram.org:443', 'api.telegram.org', 443);
            }
        }
    } catch (\Throwable $e) {
        warn('settings query failed: ' . $e->getMessage());
    }

    // 3) جدول webhook_settings
    line('');
    line('-- webhook_settings table --');
    try {
        $hooks = \Illuminate\Support\Facades\DB::table('webhook_settings')->get();
        if (count($hooks) === 0) { line('  (empty — no custom webhooks)'); }
        foreach ($hooks as $h) {
            probe_url('  Webhook [' . $h->module . '] created_by=' . $h->created_by, $h->url);
        }
    } catch (\Throwable $e) {
        warn('webhook_settings query failed: ' . $e->getMessage());
    }

    line('');
    line('التفسير: أي سطر FAILED بوقت قريب من 5000ms = القناة دي بتعلّق الطلب.');
    line('الحل السريع: عطّل الإشعار ده من إعدادات الشركة، أو صلّح الهوست/الـ URL بتاعه.');
}

/**
 * يلاقي أحدث ملف log في storage/logs (بيدعم daily: laravel-YYYY-MM-DD.log)
 * ويطبع آخر بلوك فيه ".ERROR:" — عشان نشوف الخطأ الحقيقي بتاع آخر محاولة.
 */
function show_latest_error($root) {
    $dir = $root . '/storage/logs';
    if (!is_dir($dir)) { warn("logs dir not found: $dir"); return; }
    $files = glob($dir . '/*.log');
    if (!$files) { warn("no .log files in $dir"); return; }
    // رتّب حسب أحدث تعديل (الأحدث الأول)
    usort($files, function ($a, $b) { return filemtime($b) <=> filemtime($a); });
    $log = $files[0];
    line('  newest log file: ' . basename($log) . '  (modified: ' . date('Y-m-d H:i:s', filemtime($log)) . ')');

    $maxRead = 400 * 1024; // 400 KB
    $size = filesize($log);
    $fh = fopen($log, 'rb');
    if ($size > $maxRead) { fseek($fh, -$maxRead, SEEK_END); }
    $tail = fread($fh, max($maxRead, 1));
    fclose($fh);

    $lines = explode("\n", $tail);
    $idx = -1;
    for ($i = count($lines) - 1; $i >= 0; $i--) {
        if (strpos($lines[$i], '.ERROR:') !== false) { $idx = $i; break; }
    }
    if ($idx === -1) {
        ok('no .ERROR entries in ' . basename($log) . ' 🎉');
        return;
    }
    line('  آخر خطأ مسجّل (أول 40 سطر من البلوك):');
    line('  ------------------------------------------------------------');
    $printed = 0;
    for ($i = $idx; $i < count($lines) && $printed < 40; $i++, $printed++) {
        line('  | ' . $lines[$i]);
    }
    line('  ------------------------------------------------------------');
}

// تأكد إننا في جذر مشروع لارافيل
if (!file_exists($root . '/artisan')) {
    line('ERROR: ملف artisan مش موجود في المجلد الحالي.');
    line('       افتح مجلد اللارافيل (public_html) الأول وبعدين شغّل السكريبت:');
    line('       cd /home/erp.my-communication.uk/public_html && php fix-projects-issue.php');
    exit(1);
}

// وضع سريع: "php fix-projects-issue.php log" يعرض آخر خطأ من أحدث ملف log وبس (من غير أي تعديل).
// شغّله فورًا بعد ما تجرّب إنشاء مشروع عشان يجيبلك سبب الخطأ الحقيقي.
$mode = isset($argv[1]) ? strtolower($argv[1]) : '';
if ($mode === 'log') {
    line('== Latest error (newest log file in storage/logs) ==');
    show_latest_error($root);
    line('');
    line('== PHP error_log (fatals / timeouts) ==');
    show_php_error_log($root);
    exit(0);
}

// وضع probe: "php fix-projects-issue.php probe" — يختبر اتصال قنوات الإشعارات
// ويقيس وقت كل واحدة، عشان نحدد بالظبط مين اللي بيعلّق الطلب 32 ثانية.
if ($mode === 'probe') {
    run_probe($root);
    exit(0);
}

// وضع trace: "php fix-projects-issue.php trace" — يعرض تتبّع خطوات store() الزمني
// (بيتسجّل تلقائيًا في storage/logs/store-trace.log مع كل محاولة إنشاء مشروع).
if ($mode === 'trace') {
    line('== store() timing trace (storage/logs/store-trace.log) ==');
    $tf = $root . '/storage/logs/store-trace.log';
    if (!is_file($tf)) {
        warn('no trace file yet.');
        line('  جرّب تعمل مشروع الأول (سيبه لحد ما يخلّص أو يطلع الخطأ)، وبعدين شغّل الأمر ده تاني.');
        exit(0);
    }
    $lines = array_slice(explode("\n", tail_text($tf, 20 * 1024)), -40);
    foreach ($lines as $l) { if (trim($l) !== '') line('  | ' . $l); }
    line('');
    line('التفسير:');
    line('  - "+Nms" = الوقت من بداية الطلب. أول سطر (begin) بيقيس وقت الـ middleware + رفع الملف.');
    line('  - قفزة كبيرة في +ms بين سطرين متتاليين = الخطوة اللي بينهم هي اللي بتعلّق.');
    line('  - لو آخر سطر لمحاولة معيّنة مش "returning redirect" يبقى الخطوة اللي بعده علّقت لحد ما السيرفر قتل الطلب.');
    exit(0);
}

line('==================================================================');
line(' Fix projects page / tagsinput crash');
line(' Root: ' . $root);
line('==================================================================');

// مسارات الملفات
$footer     = $root . '/resources/views/partials/admin/footer.blade.php';
$custom     = $root . '/public/js/custom.js';
$favorite   = $root . '/resources/views/vendor/Chatify/layouts/favorite.blade.php';
$pluginJs   = $root . '/public/js/bootstrap-tagsinput.min.js';
$pluginCss  = $root . '/public/css/bootstrap-tagsinput.css';

$changed = 0;

// ---- 1) تشخيص ------------------------------------------------------------
line('');
line('== 1) Diagnose ==');
$hasPluginJs  = file_exists($pluginJs);
$hasPluginCss = file_exists($pluginCss);
$hasPluginJs  ? ok("plugin JS present:  public/js/bootstrap-tagsinput.min.js")
              : warn("plugin JS MISSING: public/js/bootstrap-tagsinput.min.js");
$hasPluginCss ? ok("plugin CSS present: public/css/bootstrap-tagsinput.css")
              : warn("plugin CSS MISSING: public/css/bootstrap-tagsinput.css");

// ---- 2) إصلاح الحارس في custom.js ---------------------------------------
line('');
line('== 2) Guard custom.js commonLoader() ==');
if (!file_exists($custom)) {
    warn("custom.js not found: $custom");
} else {
    $js = file_get_contents($custom);
    if (strpos($js, 'typeof $.fn.tagsinput') !== false) {
        skip('custom.js already guarded');
    } else {
        $target  = "if (\$('[data-toggle=\"tags\"]').length > 0) {";
        $replace = "if (\$('[data-toggle=\"tags\"]').length > 0 && typeof \$.fn.tagsinput === 'function') {";
        if (strpos($js, $target) !== false) {
            backup($custom, $stamp);
            $js = str_replace($target, $replace, $js);
            file_put_contents($custom, $js);
            fix('added plugin guard in custom.js');
            $changed++;
        } else {
            warn('could not find the .tagsinput() call pattern in custom.js — check manually around commonLoader()');
        }
    }
}

// ---- 3) تحميل الـ plugin عامًّا في الـ footer ----------------------------
line('');
line('== 3) Load tagsinput plugin globally (footer) ==');
if (!file_exists($footer)) {
    warn("footer not found: $footer");
} else {
    $f = file_get_contents($footer);
    if (strpos($f, 'bootstrap-tagsinput.min.js') !== false) {
        skip('footer already loads the plugin');
    } elseif (!$hasPluginJs) {
        warn('skipping footer include because the plugin JS file is missing.');
        warn('ارفع الملف public/js/bootstrap-tagsinput.min.js الأول، وبعدين شغّل السكريبت تاني.');
    } else {
        // نحقن التحميل قبل سطر custom.js مباشرة
        $anchor = "<script src=\"{{ asset('js/custom.js') }}\"></script>";
        $inject = "{{-- Bootstrap TagsInput: loaded globally so commonLoader()'s .tagsinput() never fails --}}\n"
                . "<link rel=\"stylesheet\" href=\"{{ asset('css/bootstrap-tagsinput.css') }}\">\n"
                . "<script src=\"{{ asset('js/bootstrap-tagsinput.min.js') }}\"></script>\n";
        if (strpos($f, $anchor) !== false) {
            backup($footer, $stamp);
            $f = str_replace($anchor, $inject . $anchor, $f);
            file_put_contents($footer, $f);
            fix('injected tagsinput plugin load before custom.js');
            $changed++;
        } else {
            warn("could not find custom.js <script> anchor in footer — add these lines manually before custom.js:");
            line("         <link rel=\"stylesheet\" href=\"{{ asset('css/bootstrap-tagsinput.css') }}\">");
            line("         <script src=\"{{ asset('js/bootstrap-tagsinput.min.js') }}\"></script>");
        }
    }
}

// ---- 4) حماية favorite.blade.php ضد المستخدم المحذوف --------------------
line('');
line('== 4) Guard Chatify favorite.blade.php against null user ==');
if (!file_exists($favorite)) {
    warn("favorite.blade.php not found: $favorite");
} else {
    $fav = file_get_contents($favorite);
    if (strpos($fav, '@if(!empty($user))') !== false || strpos($fav, '@if (!empty($user))') !== false) {
        skip('favorite.blade.php already guarded');
    } else {
        backup($favorite, $stamp);
        $wrapped = "@if(!empty(\$user))\n" . rtrim($fav) . "\n@endif\n";
        file_put_contents($favorite, $wrapped);
        fix('wrapped favorite.blade.php in @if(!empty($user))');
        $changed++;
    }
}

// ---- 5) فحص إصلاح Project.store() (خطأ الـ 500) --------------------------
line('');
line('== 5) Check ProjectController::store() (the 500 fix) ==');
$controller = $root . '/app/Http/Controllers/ProjectController.php';
if (!file_exists($controller)) {
    warn("ProjectController.php not found: $controller");
} else {
    $c = file_get_contents($controller);
    if (strpos($c, "route('projects.index')->with('success', \$successMsg)") !== false) {
        ok('ProjectController::store() already has the safe fallback (returns a valid response).');
    } else {
        warn('ProjectController::store() may still return null on some paths -> causes the 500.');
        warn('ارفع النسخة المصلّحة من الملف ده من جهازك المحلي:');
        line('         app/Http/Controllers/ProjectController.php');
        line('         (الإصلاح بيضمن إن الدالة ترجع redirect صح دايمًا بدل null)');
    }
}

// ---- 5b) فحص إصلاحات timeout الإشعارات (خطأ الـ 32 ثانية / الـ 500) ------
line('');
line('== 5b) Check notification timeout fixes (the 32s hang / 500) ==');
$checks = [
    ['config/mail.php',                        "MAIL_TIMEOUT",                 'SMTP connection timeout'],
    ['app/Models/Utility.php',                 "CURLOPT_CONNECTTIMEOUT",       'cURL timeouts (webhook/slack)'],
    ['app/Http/Controllers/ProjectController.php', "created but notifications failed", 'store() notification try/catch'],
];
$missingNotif = false;
foreach ($checks as $chk) {
    list($rel, $needle, $label) = $chk;
    $path = $root . '/' . $rel;
    if (!file_exists($path)) { warn("$rel not found"); $missingNotif = true; continue; }
    if (strpos(file_get_contents($path), $needle) !== false) {
        ok("$label present ($rel)");
    } else {
        warn("$label MISSING -> upload the fixed $rel");
        $missingNotif = true;
    }
}
if ($missingNotif) {
    warn('ارفع الملفات الناقصة من جهازك المحلي، وامسح OPcache بعدها (ملفات PHP بتتخزّن في الذاكرة).');
}

// ---- 6) مسح الـ compiled views + الـ bootstrap cache --------------------
line('');
line('== 6) Clear compiled views + bootstrap cache ==');
$viewCache = $root . '/storage/framework/views';
$cleared = 0;
if (is_dir($viewCache)) {
    foreach (glob($viewCache . '/*.php') as $file) {
        if (@unlink($file)) { $cleared++; }
    }
    ok("cleared $cleared compiled view file(s)");
} else {
    warn("view cache dir not found: $viewCache");
}
// امسح كاش الـ config/routes (مهم عشان تعديل config/mail.php يشتغل)
$bootCleared = 0;
foreach (['config.php', 'routes-v7.php', 'routes.php', 'services.php', 'packages.php'] as $bc) {
    $p = $root . '/bootstrap/cache/' . $bc;
    if (is_file($p) && @unlink($p)) { $bootCleared++; line("         removed bootstrap/cache/$bc"); }
}
ok("cleared $bootCleared bootstrap cache file(s)" . ($bootCleared === 0 ? ' (none cached)' : ''));

// ---- 7) عرض آخر خطأ من أحدث ملف log -------------------------------------
line('');
line('== 7) Latest error (newest log file in storage/logs) ==');
show_latest_error($root);

// ---- 8) عرض PHP error log (fatals/timeouts مش بتظهر في laravel.log) ------
line('');
line('== 8) PHP error_log (fatals / timeouts) ==');
show_php_error_log($root);

// ---- الخلاصة -------------------------------------------------------------
line('');
line('==================================================================');
line(" Done. Files changed: $changed");
line('==================================================================');
line('');
line('خطوات أخيرة موصى بيها (شغّلها لو عندك صلاحية artisan):');
line('   php artisan view:clear');
line('   php artisan route:clear');
line('   php artisan config:clear');
line('   php artisan cache:clear');
line('');
line('بعدها افتح صفحة المشاريع تاني (Ctrl+Shift+R عشان يمسح كاش المتصفح).');
line('لو ظهر خطأ 500 لسه عند إنشاء مشروع، ابعتلي آخر بلوك من storage/logs/laravel.log');
