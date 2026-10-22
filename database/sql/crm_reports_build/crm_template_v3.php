<?php
/**
 * apply_crm_reports v3 - CRM Reports follow-up on top of v1 (and of the first v2, if that was applied).
 *
 *   php database/sql/apply_crm_reports_v3.php             check only, writes nothing
 *   php database/sql/apply_crm_reports_v3.php --apply     back up, write, verify the app boots
 *   php database/sql/apply_crm_reports_v3.php --rollback  restore everything from the newest v3 backup
 *
 * - Replaces the CRM Reports files that changed since v1, and deletes v1's
 *   call-logging files - only when a file is byte-identical to a version we
 *   shipped, so nothing someone changed on the server is ever overwritten.
 * - Takes v1's two call-logging edits back out of routes/web.php and
 *   resources/views/leads/show.blade.php (only v1's exact text is touched).
 * - Rewords one translation line in ar.json and appends the new ones (as text,
 *   the file is never decoded and re-encoded).
 *
 * Exit codes: 0 fine, 2 blocked (nothing written), 3 backup failed (nothing
 * written), 4 a write failed (all undone), 5 the app no longer boots (all undone).
 */
$root = dirname(__DIR__, 2);
$replace = __REPLACE__;
$delete = __DELETE__;
$edits = __EDITS__;
$lang = __LANG__;

$mode = in_array('--rollback', $argv, true) ? 'rollback' : (in_array('--apply', $argv, true) ? 'apply' : 'check');
$lf = fn ($s) => str_replace("\r\n", "\n", $s);
echo "apply_crm_reports v3  (CRM Reports: meetings-based actions, Activity log, call logging removed)\n";
echo "Laravel root: $root\nMode:         $mode\n\n";

if ($mode === 'rollback') {
    $dirs = glob($root . '/storage/backups/crm-reports-v3-*', GLOB_ONLYDIR) ?: [];
    rsort($dirs);
    if (!$dirs) {
        fwrite(STDERR, "No v3 backup found under storage/backups/crm-reports-v3-*.\n");
        exit(1);
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dirs[0], FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($dirs[0]) + 1));
        @mkdir(dirname($root . '/' . $rel), 0775, true);
        copy($file->getPathname(), $root . '/' . $rel);
        echo "[RESTORED] $rel\n";
    }
    echo "\nFrom: {$dirs[0]}\nNext: php artisan view:clear && php artisan optimize:clear\n";
    exit(0);
}

$blocked = 0;
$plan = [];     // rel => new content
$remove = [];   // rel to delete

foreach ($replace as $rel => $f) {
    $path = "$root/$rel";
    $have = is_file($path) ? hash_file('sha256', $path) : null;
    if ($have === $f['newHash']) {
        echo "[SKIP]    $rel -- already applied\n";
    } elseif (in_array($have, $f['expected'], true)) {
        echo "[READY]   $rel\n";
        $plan[$rel] = base64_decode($f['new']);
    } else {
        echo "[BLOCKED] $rel -- not a version we shipped (" . ($have ? 'sha256 ' . substr($have, 0, 16) : 'missing') . ")\n";
        $blocked++;
    }
}
foreach ($delete as $rel => $f) {
    $path = "$root/$rel";
    if (!is_file($path)) {
        echo "[SKIP]    $rel -- already deleted\n";
    } elseif (in_array(hash_file('sha256', $path), $f['expected'], true)) {
        echo "[READY]   $rel -- delete\n";
        $remove[] = $rel;
    } else {
        echo "[BLOCKED] $rel -- changed on the server, not deleting it\n";
        $blocked++;
    }
}
foreach ($edits as $e) {
    $path = "$root/{$e['path']}";
    $raw = (string) @file_get_contents($path);
    $src = $plan[$e['path']] ?? $lf($raw);
    $find = base64_decode($e['find']);
    $n = substr_count($src, $find);
    if ($n === 1) {
        echo "[READY]   {$e['path']} -- {$e['label']}\n";
        $plan[$e['path']] = str_replace($find, base64_decode($e['replace']), $src);
    } elseif ($n === 0) {
        echo "[SKIP]    {$e['path']} -- {$e['label']}: already done\n";
    } else {
        echo "[BLOCKED] {$e['path']} -- {$e['label']}: the text is there $n times\n";
        $blocked++;
    }
}

$langRel = 'resources/lang/ar.json';
$src = $lf((string) file_get_contents("$root/$langRel"));
$langChanges = 0;
if (strpos($src, $lang['swapReplace']) === false) {
    if (substr_count($src, $lang['swapFind']) === 1) {
        $src = str_replace($lang['swapFind'], $lang['swapReplace'], $src);
        $langChanges++;
    } else {
        echo "[BLOCKED] $langRel -- the v1 line to reword is not there exactly once\n";
        $blocked++;
    }
}
$lines = [];
foreach ($lang['append'] as $k => $v) {
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
}
if ($langChanges || $lines) {
    if (json_decode($src, true) === null) {
        echo "[BLOCKED] $langRel -- the result would not be valid JSON\n";
        $blocked++;
    } else {
        echo "[READY]   $langRel -- " . ($langChanges ? '1 line reworded, ' : '') . count($lines) . " translation(s) to append\n";
        $plan[$langRel] = $src;
    }
} else {
    echo "[SKIP]    $langRel -- already applied\n";
}

echo "\n" . str_repeat('-', 50) . "\nchanged: " . count($plan) . "   deleted: " . count($remove) . "   blocked: $blocked\n";
if ($blocked) {
    echo "\nBlocked: nothing was written. Send this output.\n";
    exit(2);
}
if (!$plan && !$remove) {
    echo "\nEverything is already in place.\n";
    exit(0);
}
if ($mode === 'check') {
    echo "\nCheck only. Re-run with --apply to write.\n";
    exit(0);
}

// ---- backup: every file we change or delete, so --rollback can put it back
$backup = $root . '/storage/backups/crm-reports-v3-' . date('Ymd-His');
foreach (array_merge(array_keys($plan), $remove) as $rel) {
    if (!is_file("$root/$rel")) {
        continue;
    }
    @mkdir(dirname("$backup/$rel"), 0775, true);
    if (!copy("$root/$rel", "$backup/$rel") || hash_file('sha256', "$root/$rel") !== hash_file('sha256', "$backup/$rel")) {
        fwrite(STDERR, "Could not back up $rel - nothing written.\n");
        exit(3);
    }
}
echo "\nBackup: $backup\n";

$undo = function () use ($backup, $root, $plan, $remove) {
    foreach (array_merge(array_keys($plan), $remove) as $rel) {
        if (is_file("$backup/$rel")) {
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
foreach ($remove as $rel) {
    if (!@unlink("$root/$rel")) {
        fwrite(STDERR, "Could not delete $rel - restoring everything.\n");
        $undo();
        exit(4);
    }
    echo "[ DEL ]   $rel\n";
}

// ---- the app must still boot, know the report routes (and not the call routes),
//      and every changed Blade view must compile to valid PHP
@unlink("$root/bootstrap/cache/routes-v7.php");
$views = array_values(array_filter(array_keys($plan), fn ($r) => substr($r, -10) === '.blade.php'));
$code = "require " . var_export("$root/vendor/autoload.php", true) . ";\n"
    . "if (!class_exists('App\\\\Services\\\\CrmReports\\\\EmployeeReport') || !class_exists('App\\\\Http\\\\Controllers\\\\CrmReportController')) { fwrite(STDERR, 'report classes do not load'); exit(1); }\n"
    . "\$app = require " . var_export("$root/bootstrap/app.php", true) . ";\n"
    . "\$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();\n"
    . "if (!\\Illuminate\\Support\\Facades\\Route::has('crm-reports.employee')) { fwrite(STDERR, 'route missing: crm-reports.employee'); exit(1); }\n"
    . "if (\\Illuminate\\Support\\Facades\\Route::has('leads.calls.store')) { fwrite(STDERR, 'call routes still registered'); exit(1); }\n"
    . "foreach (" . var_export($views, true) . " as \$v) {\n"
    . "    try { token_get_all(app('blade.compiler')->compileString(file_get_contents(" . var_export("$root/", true) . " . \$v)), TOKEN_PARSE); }\n"
    . "    catch (\\ParseError \$e) { fwrite(STDERR, \"view \$v does not compile: \" . \$e->getMessage()); exit(1); }\n"
    . "}\n"
    . "exit(0);\n";
$probe = "$root/storage/crm-reports-v3-probe.php";
file_put_contents($probe, "<?php\n" . $code);
$out = [];
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($probe) . ' 2>&1', $out, $rc);
@unlink($probe);
if ($rc !== 0) {
    fwrite(STDERR, "The app does not work after the change - restoring everything.\n" . implode("\n", array_slice($out, 0, 15)) . "\n");
    $undo();
    exit(5);
}
echo "[ OK ]    the app boots, the report routes are there, the call routes are gone, the views compile\n";
echo "\nDone. Next: php artisan view:clear && php artisan optimize:clear\n";
echo "Undo:  php database/sql/apply_crm_reports_v3.php --rollback\n";
