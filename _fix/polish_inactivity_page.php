<?php
/**
 * polish_inactivity_page — the two loose ends on the Inactive Accounts Report.
 *
 * 1. The page was built straight onto the server in English and never translated,
 *    so 31 of its 43 strings render as English sentences for Arabic users. (Unrelated
 *    to the 174 WhatsApp keys a deploy destroyed on 2026-09-12 — these were simply
 *    never written.)
 *
 * 2. The menu item tests request()->is('report-inactivity') while the route is
 *    'report/inactivity' — a dash where the URI has a slash — so the link never
 *    highlights as the active page.
 *
 * Translations are appended textually before the closing brace, never via
 * json_decode: ar.json carries duplicate keys and a decode/encode round trip
 * collapses them. Result is validated as JSON before anything is written.
 *
 *   php _fix/polish_inactivity_page.php --dry-run
 *   php _fix/polish_inactivity_page.php
 */

$dry  = in_array('--dry-run', $argv, true);
$root = __DIR__ . '/..';

/* ------------------------------------------------------------------ 1. Arabic */

$translations = [
    'Inactive Accounts Report'   => 'تقرير الحسابات غير النشطة',
    'Report-only mode'           => 'وضع التقرير فقط',
    'Enforcement is ON'          => 'الحظر التلقائي مُفعَّل',
    'No account is blocked. Review the list below, then turn enforcement on when you are ready.'
                                 => 'لا يوجد حساب محظور. راجع القائمة أدناه، ثم فعّل الحظر التلقائي عندما تكون جاهزاً.',
    'Accounts that pass the threshold are blocked automatically every Saturday.'
                                 => 'يتم حظر الحسابات التي تتجاوز الحد تلقائياً كل يوم سبت.',

    'Generate the report now and email it' => 'إنشاء التقرير الآن وإرساله بالبريد',
    'Run now'                    => 'تشغيل الآن',

    'Rules'                      => 'القواعد',
    'Idle days before blocking'  => 'أيام الخمول قبل الحظر',
    'Grace period for new accounts (days)' => 'فترة السماح للحسابات الجديدة (بالأيام)',
    'Report recipients (comma separated)'  => 'مستلمو التقرير (مفصولة بفاصلة)',
    'Exempt roles (comma separated)'       => 'الأدوار المستثناة (مفصولة بفاصلة)',
    'Block inactive accounts automatically' => 'حظر الحسابات غير النشطة تلقائياً',

    // Sentence split around a live count: "… affects" [N] "accounts."
    'Leave this off until you have reviewed the list — it currently affects'
                                 => 'اتركه مغلقاً حتى تراجع القائمة — فهو يؤثر حالياً على',
    'accounts.'                  => 'حساب.',

    'Threshold'                  => 'الحد',
    'Grace'                      => 'السماح',
    'Last run'                   => 'آخر تشغيل',

    'Approaching'                => 'يقترب',
    'Blocked'                    => 'محظور',
    'Exempt'                     => 'مستثنى',

    'Blocked for inactivity'     => 'محظور لعدم النشاط',
    'Inactive — met the threshold' => 'غير نشط — بلغ الحد',
    'Approaching the threshold'  => 'يقترب من الحد',

    'Last app use'               => 'آخر استخدام للتطبيق',
    'Idle days'                  => 'أيام الخمول',
    'Never'                      => 'مطلقاً',
    'Restore login'              => 'استعادة الدخول',
    'Unpin'                      => 'إلغاء الاستثناء',

    'Recent activity'            => 'النشاط الأخير',
    'When'                       => 'التوقيت',
];

/** Key of a `  "key": "value",` line, honouring escaped quotes. See [[lang-json-duplicate-keys]]. */
function entryKey(string $line): ?string
{
    $s = ltrim($line);

    if ($s === '' || $s[0] !== '"') {
        return null;
    }

    $key = '';
    $len = strlen($s);

    for ($i = 1; $i < $len; $i++) {
        $c = $s[$i];

        if ($c === '\\') {
            if ($i + 1 >= $len) {
                return null;
            }
            $key .= $c . $s[$i + 1];
            $i++;
            continue;
        }

        if ($c === '"') {
            $rest = ltrim(substr($s, $i + 1));

            if ($rest === '' || $rest[0] !== ':') {
                return null;
            }

            // Return the DECODED key. entryKey used to hand back the raw source form
            // with its backslashes intact, which never compares equal to the plain PHP
            // array key it is checked against — so a key containing a quote was judged
            // missing and re-appended on every run.
            $decoded = json_decode('"' . $key . '"');

            return is_string($decoded) ? $decoded : $key;
        }

        $key .= $c;
    }

    return null;
}

function patchArabic(string $path, array $translations, bool $dry): void
{
    $raw   = file_get_contents($path);
    $eol   = strpos($raw, "\r\n") !== false ? "\r\n" : "\n";
    $lines = explode("\n", str_replace("\r\n", "\n", $raw));

    $have = [];
    foreach ($lines as $l) {
        if (($k = entryKey($l)) !== null) {
            $have[$k] = true;
        }
    }

    if (count($have) < 1000) {
        fwrite(STDERR, "  only " . count($have) . " keys parsed from ar.json — refusing to touch it.\n");
        exit(1);
    }

    $add = array_diff_key($translations, $have);

    echo "  ar.json: " . count($have) . " keys present, " . count($add) . " to add";
    echo count($add) === count($translations) ? "\n" : " (" . (count($translations) - count($add)) . " already there)\n";

    if (!$add) {
        return;
    }

    $close = null;
    for ($i = count($lines) - 1; $i >= 0; $i--) {
        if (trim($lines[$i]) === '}') {
            $close = $i;
            break;
        }
    }

    if ($close === null) {
        fwrite(STDERR, "  no closing brace in ar.json — refusing.\n");
        exit(1);
    }

    $last = null;
    for ($i = $close - 1; $i >= 0; $i--) {
        if (trim($lines[$i]) !== '') {
            $last = $i;
            break;
        }
    }

    if ($last === null || entryKey($lines[$last]) === null) {
        fwrite(STDERR, "  the line before the closing brace is not an entry — refusing.\n");
        exit(1);
    }

    if (substr(rtrim($lines[$last]), -1) !== ',') {
        $lines[$last] = rtrim($lines[$last]) . ',';
    }

    $block   = [''];
    $block[] = '    "_inactivity_page_ar_2026_09_12": "Arabic for the Inactive Accounts Report, which was built on the server in English only",';

    $i = 0;
    $n = count($add);
    foreach ($add as $k => $v) {
        $enc = fn($s) => substr(json_encode($s, JSON_UNESCAPED_UNICODE), 0);
        $block[] = '    ' . $enc($k) . ': ' . $enc($v) . (++$i === $n ? '' : ',');
    }

    array_splice($lines, $close, 0, $block);
    $out = implode($eol, $lines);

    if (json_decode(str_replace("\r\n", "\n", $out)) === null) {
        fwrite(STDERR, "  RESULT IS NOT VALID JSON (" . json_last_error_msg() . ") — nothing written.\n");
        exit(1);
    }

    echo "  ar.json: result parses as valid JSON\n";

    if ($dry) {
        return;
    }

    copy($path, $path . '.bak-' . date('Ymd-His'));
    file_put_contents($path, $out);
    clearstatcache();
    echo "  ar.json: written (" . filesize($path) . " bytes)\n";
}

/* --------------------------------------------------------- 2. menu highlight */

function patchMenu(string $path, bool $dry): void
{
    $raw = file_get_contents($path);
    $eol = strpos($raw, "\r\n") !== false ? "\r\n" : "\n";
    $src = str_replace("\r\n", "\n", $raw);

    $from = "request()->is('report-inactivity')";
    $to   = "request()->is('report/inactivity')";

    if (strpos($src, $to) !== false && strpos($src, $from) === false) {
        echo "  menu:    already correct\n";

        return;
    }

    $hits = substr_count($src, $from);
    if ($hits !== 1) {
        fwrite(STDERR, "  menu:    anchor found {$hits}x (need exactly 1) — refusing.\n");
        exit(1);
    }

    $src = str_replace($from, $to, $src);
    echo "  menu:    report-inactivity -> report/inactivity\n";

    if ($dry) {
        return;
    }

    copy($path, $path . '.bak-' . date('Ymd-His'));
    file_put_contents($path, str_replace("\n", $eol, $src));
    echo "  menu:    written\n";
}

/* -------------------------------------------------------------------- run it */

$ar   = $root . '/resources/lang/ar.json';
$menu = $root . '/resources/views/partials/admin/menu.blade.php';

foreach ([$ar, $menu] as $f) {
    if (!is_file($f)) {
        fwrite(STDERR, "missing: {$f}\n");
        exit(1);
    }
}

patchArabic($ar, $translations, $dry);
patchMenu($menu, $dry);

echo $dry
    ? "\ndry run — nothing written.\n"
    : "\nNow run:  php artisan optimize:clear\n";
