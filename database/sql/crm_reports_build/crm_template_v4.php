<?php
/**
 * apply_crm_reports v4 - on top of v3.
 *
 *   php database/sql/apply_crm_reports_v4.php             check only, writes nothing
 *   php database/sql/apply_crm_reports_v4.php --apply     back up, write, verify the app boots
 *   php database/sql/apply_crm_reports_v4.php --rollback  undo everything from the newest v4 backup
 *
 * - CRM Reports: Actions tab without first response / speed to lead; Lead stages tab
 *   by deal status; neglected leads leave out the ones whose latest deal is Won or Lost.
 *   These files are replaced only when byte-identical to what v3 wrote.
 * - Leads page: the Stage filter becomes a Deal status filter (anchored edits to
 *   LeadScope.php, LeadController.php and leads/index.blade.php).
 * - WhatsApp links open the app straight away on phones (one new partial, included
 *   from partials/admin/footer.blade.php).
 * - Appends the new translations to ar.json (as text, never decoded and re-encoded).
 *
 * Exit codes: 0 fine, 2 blocked (nothing written), 3 backup failed (nothing
 * written), 4 a write failed (all undone), 5 the app no longer boots (all undone).
 */
$root = dirname(__DIR__, 2);
$replace = __REPLACE__;
$create = __CREATE__;
$edits = __EDITS__;
$append = __APPEND__;

$mode = in_array('--rollback', $argv, true) ? 'rollback' : (in_array('--apply', $argv, true) ? 'apply' : 'check');
$lf = fn ($s) => str_replace("\r\n", "\n", $s);
$createdList = '__created.txt';
echo "apply_crm_reports v4  (deal status instead of stage, settled leads not neglected, WhatsApp links)\n";
echo "Laravel root: $root\nMode:         $mode\n\n";

if ($mode === 'rollback') {
    $dirs = glob($root . '/storage/backups/crm-reports-v4-*', GLOB_ONLYDIR) ?: [];
    rsort($dirs);
    if (!$dirs) {
        fwrite(STDERR, "No v4 backup found under storage/backups/crm-reports-v4-*.\n");
        exit(1);
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dirs[0], FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($dirs[0]) + 1));
        if ($rel === $createdList) {
            continue;
        }
        @mkdir(dirname($root . '/' . $rel), 0775, true);
        copy($file->getPathname(), $root . '/' . $rel);
        echo "[RESTORED] $rel\n";
    }
    foreach (array_filter(explode("\n", (string) @file_get_contents($dirs[0] . '/' . $createdList))) as $rel) {
        if (@unlink($root . '/' . $rel)) {
            echo "[REMOVED]  $rel\n";
        }
    }
    echo "\nFrom: {$dirs[0]}\nNext: php artisan view:clear && php artisan optimize:clear\n";
    exit(0);
}

$blocked = 0;
$plan = [];     // rel => new content
$new = [];      // rel of files this run creates

foreach ($replace as $rel => $f) {
    $path = "$root/$rel";
    $have = is_file($path) ? hash_file('sha256', $path) : null;
    if ($have === $f['newHash']) {
        echo "[SKIP]    $rel -- already applied\n";
    } elseif (in_array($have, $f['expected'], true)) {
        echo "[READY]   $rel\n";
        $plan[$rel] = base64_decode($f['new']);
    } else {
        echo "[BLOCKED] $rel -- not the version v3 wrote (" . ($have ? 'sha256 ' . substr($have, 0, 16) : 'missing') . ")\n";
        $blocked++;
    }
}
foreach ($create as $rel => $f) {
    $path = "$root/$rel";
    if (!is_file($path)) {
        echo "[READY]   $rel -- new file\n";
        $plan[$rel] = base64_decode($f['new']);
        $new[] = $rel;
    } elseif (hash_file('sha256', $path) === $f['newHash']) {
        echo "[SKIP]    $rel -- already there\n";
    } else {
        echo "[BLOCKED] $rel -- a different file with this name is already there\n";
        $blocked++;
    }
}
foreach ($edits as $e) {
    $path = "$root/{$e['path']}";
    if (!is_file($path)) {
        echo "[BLOCKED] {$e['path']} -- missing\n";
        $blocked++;
        continue;
    }
    $src = $plan[$e['path']] ?? $lf((string) file_get_contents($path));
    $find = base64_decode($e['find']);
    $repl = base64_decode($e['replace']);
    $n = $e['regex'] ? preg_match_all($find, $src) : substr_count($src, $find);
    if (strpos($src, $e['done']) !== false) {
        echo "[SKIP]    {$e['path']} -- {$e['label']}: already done\n";
    } elseif ($n === $e['times']) {
        echo "[READY]   {$e['path']} -- {$e['label']}\n";
        $plan[$e['path']] = $e['regex'] ? preg_replace($find, str_replace(['\\', '$'], ['\\\\', '\\$'], $repl), $src) : str_replace($find, $repl, $src);
    } elseif ($n === 0 && $e['optional']) {
        echo "[SKIP]    {$e['path']} -- {$e['label']}: not on this server, nothing to change\n";
    } else {
        echo "[BLOCKED] {$e['path']} -- {$e['label']}: the text is there $n time(s), expected {$e['times']}\n";
        $blocked++;
    }
}

$langRel = 'resources/lang/ar.json';
$src = $lf((string) file_get_contents("$root/$langRel"));
$lines = [];
foreach ($append as $k => $v) {
    $key = '    ' . json_encode($k, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ':';
    if (strpos($src, "\n" . $key) === false) {
        $lines[] = $key . ' ' . json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
if ($lines) {
    $end = strrpos($src, '}');
    $body = rtrim(substr($src, 0, $end));
    if (substr($body, -1) !== ',' && substr($body, -1) !== '{') {
        $body .= ',';
    }
    $src = $body . "\n" . implode(",\n", $lines) . "\n}\n";
    if (json_decode($src, true) === null) {
        echo "[BLOCKED] $langRel -- the result would not be valid JSON\n";
        $blocked++;
    } else {
        echo "[READY]   $langRel -- " . count($lines) . " translation(s) to append\n";
        $plan[$langRel] = $src;
    }
} else {
    echo "[SKIP]    $langRel -- already applied\n";
}

echo "\n" . str_repeat('-', 50) . "\nchanged: " . (count($plan) - count($new)) . "   new: " . count($new) . "   blocked: $blocked\n";
if ($blocked) {
    echo "\nBlocked: nothing was written. Send this output.\n";
    exit(2);
}
if (!$plan) {
    echo "\nEverything is already in place.\n";
    exit(0);
}
if ($mode === 'check') {
    echo "\nCheck only. Re-run with --apply to write.\n";
    exit(0);
}

// ---- backup: every file we change, plus the list of files we create, so --rollback can undo both
$backup = $root . '/storage/backups/crm-reports-v4-' . date('Ymd-His');
@mkdir($backup, 0775, true);
foreach (array_keys($plan) as $rel) {
    if (in_array($rel, $new, true)) {
        continue;
    }
    @mkdir(dirname("$backup/$rel"), 0775, true);
    if (!copy("$root/$rel", "$backup/$rel") || hash_file('sha256', "$root/$rel") !== hash_file('sha256', "$backup/$rel")) {
        fwrite(STDERR, "Could not back up $rel - nothing written.\n");
        exit(3);
    }
}
if (file_put_contents("$backup/$createdList", implode("\n", $new) . "\n") === false) {
    fwrite(STDERR, "Could not write the backup's file list - nothing written.\n");
    exit(3);
}
echo "\nBackup: $backup\n";

$undo = function () use ($backup, $root, $plan, $new) {
    foreach (array_keys($plan) as $rel) {
        if (in_array($rel, $new, true)) {
            @unlink("$root/$rel");
        } elseif (is_file("$backup/$rel")) {
            copy("$backup/$rel", "$root/$rel");
        }
    }
};

foreach ($plan as $rel => $content) {
    if (file_put_contents("$root/$rel", $content) !== strlen($content)) {
        fwrite(STDERR, "Write failed on $rel - restoring everything.\n");
        $undo();
        exit(4);
    }
    echo "[ OK ]    $rel\n";
}

// ---- the app must still boot and know its routes, and every changed Blade view must compile to valid PHP
@unlink("$root/bootstrap/cache/routes-v7.php");
$views = array_values(array_filter(array_keys($plan), fn ($r) => substr($r, -10) === '.blade.php'));
$code = "require " . var_export("$root/vendor/autoload.php", true) . ";\n"
    . "foreach (['App\\\\Services\\\\CrmReports\\\\EmployeeReport', 'App\\\\Http\\\\Controllers\\\\CrmReportController', 'App\\\\Services\\\\LeadScope', 'App\\\\Http\\\\Controllers\\\\LeadController'] as \$c) {\n"
    . "    if (!class_exists(\$c)) { fwrite(STDERR, \"class does not load: \$c\"); exit(1); }\n"
    . "}\n"
    . "if (!defined('App\\\\Services\\\\LeadScope::NO_DEAL')) { fwrite(STDERR, 'LeadScope was not patched'); exit(1); }\n"
    . "\$app = require " . var_export("$root/bootstrap/app.php", true) . ";\n"
    . "\$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();\n"
    . "foreach (['crm-reports.employee', 'leads.index'] as \$r) {\n"
    . "    if (!\\Illuminate\\Support\\Facades\\Route::has(\$r)) { fwrite(STDERR, \"route missing: \$r\"); exit(1); }\n"
    . "}\n"
    . "foreach (" . var_export($views, true) . " as \$v) {\n"
    . "    try { token_get_all(app('blade.compiler')->compileString(file_get_contents(" . var_export("$root/", true) . " . \$v)), TOKEN_PARSE); }\n"
    . "    catch (\\ParseError \$e) { fwrite(STDERR, \"view \$v does not compile: \" . \$e->getMessage()); exit(1); }\n"
    . "}\n"
    . "exit(0);\n";
$probe = "$root/storage/crm-reports-v4-probe.php";
file_put_contents($probe, "<?php\n" . $code);
$out = [];
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($probe) . ' 2>&1', $out, $rc);
@unlink($probe);
if ($rc !== 0) {
    fwrite(STDERR, "The app does not work after the change - restoring everything.\n" . implode("\n", array_slice($out, 0, 15)) . "\n");
    $undo();
    exit(5);
}
echo "[ OK ]    the app boots, the routes are there, the views compile\n";
echo "\nDone. Next: php artisan view:clear && php artisan optimize:clear && php artisan cache:clear\n";
echo "Undo:  php database/sql/apply_crm_reports_v4.php --rollback\n";
