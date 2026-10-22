<?php
/**
 * Anchored in-place patch.
 *
 * Unlike the leads deploy scripts this one does NOT ship whole files: the deals
 * files on the server are ahead of the local checkout, so replacing them would
 * throw away whatever is only on the server. Each change is an exact find ->
 * replace pair instead, and the script refuses to touch anything unless every
 * anchor is found exactly once.
 *
 *   php apply_deal_api_duplicate_fix_v1.php              dry run, writes nothing
 *   php apply_deal_api_duplicate_fix_v1.php --apply      back up, then apply everything
 *   php apply_deal_api_duplicate_fix_v1.php --rollback   restore the newest backup
 *
 * Exit codes: 0 fine, 2 an anchor or a preflight check failed (nothing written),
 * 3 the backup could not be taken, 4 a write failed (everything undone),
 * 5 a changed class no longer loads (everything undone).
 */

$BANNER = 'apply_deal_api_duplicate_fix v1  (POST /api/v1/deals created a new row for every repeated request)';
$edits = array (
  0 => 
  array (
    'path' => 'app/Http/Controllers/Api/DealApiController.php',
    'label' => 'imports',
    'find' => 'dXNlIElsbHVtaW5hdGVcU3VwcG9ydFxGYWNhZGVzXFZhbGlkYXRvcjsKdXNlIElsbHVtaW5hdGVcU3VwcG9ydFxGYWNhZGVzXFN0b3JhZ2U7',
    'replace' => 'dXNlIElsbHVtaW5hdGVcU3VwcG9ydFxGYWNhZGVzXFZhbGlkYXRvcjsKdXNlIElsbHVtaW5hdGVcU3VwcG9ydFxGYWNhZGVzXFN0b3JhZ2U7CnVzZSBJbGx1bWluYXRlXFN1cHBvcnRcRmFjYWRlc1xEQjsKdXNlIElsbHVtaW5hdGVcVmFsaWRhdGlvblxSdWxlOw==',
  ),
  1 => 
  array (
    'path' => 'app/Http/Controllers/Api/DealApiController.php',
    'label' => 'createDeal guard',
    'find' => 'ICAgIHB1YmxpYyBmdW5jdGlvbiBjcmVhdGVEZWFsKFJlcXVlc3QgJHJlcXVlc3QpCiAgICB7CiAgICAgICAgJHZhbGlkYXRvciA9IFZhbGlkYXRvcjo6bWFrZSgKICAgICAgICAgICAgJHJlcXVlc3QtPmFsbCgpLAogICAgICAgICAgICBbCiAgICAgICAgICAgICAgICAnbmFtZScgPT4gJ3JlcXVpcmVkJywKICAgICAgICAgICAgICAgICdsZWFkX2lkJyA9PiAncmVxdWlyZWR8ZXhpc3RzOmxlYWRzLGlkJywKICAgICAgICAgICAgICAgICdzdGFnZV9pZCcgPT4gJ3JlcXVpcmVkfGV4aXN0czpzdGFnZXMsaWQnLAogICAgICAgICAgICBdCiAgICAgICAgKTsKCiAgICAgICAgaWYgKCR2YWxpZGF0b3ItPmZhaWxzKCkpIHsKICAgICAgICAgICAgcmV0dXJuICR0aGlzLT5lcnJvcigkdmFsaWRhdG9yLT5lcnJvcnMoKS0+Zmlyc3QoKSwgNDIyKTsKICAgICAgICB9CgogICAgICAgICRsZWFkID0gTGVhZDo6ZmluZCgkcmVxdWVzdC0+bGVhZF9pZCk7CiAgICAgICAgJHN0YWdlID0gU3RhZ2U6OmZpbmQoJHJlcXVlc3QtPnN0YWdlX2lkKTsKCiAgICAgICAgJGRlYWwgPSBuZXcgRGVhbCgpOwogICAgICAgICRkZWFsLT5uYW1lID0gJHJlcXVlc3QtPm5hbWU7CiAgICAgICAgJGRlYWwtPmxlYWRfaWQgPSAkcmVxdWVzdC0+bGVhZF9pZDsKICAgICAgICBpZiAoJHJlcXVlc3QtPnByb2R1Y3RzKSB7CiAgICAgICAgICAgICRkZWFsLT5wcm9kdWN0cyA9IGltcGxvZGUoJywnLCBhcnJheV9maWx0ZXIoJHJlcXVlc3QtPnByb2R1Y3RzKSk7CiAgICAgICAgfQogICAgICAgICRkZWFsLT5jcmVhdGVkX2J5ID0gQXV0aDo6dXNlcigpLT5jcmVhdG9ySWQoKTsKICAgICAgICAkZGVhbC0+c3RhZ2VfaWQgPSAkcmVxdWVzdC0+c3RhZ2VfaWQ7CiAgICAgICAgJGRlYWwtPnN0YXR1cyA9ICRzdGFnZSA/ICRzdGFnZS0+bmFtZSA6IG51bGw7CiAgICAgICAgJGRlYWwtPnBpcGVsaW5lX2lkID0gJGxlYWQtPnBpcGVsaW5lX2lkID8/IDA7CiAgICAgICAgJGRlYWwtPm5vdGVzID0gJHJlcXVlc3QtPm5vdGVzOwogICAgICAgICRkZWFsLT5kYXRldGltZSA9ICRyZXF1ZXN0LT5kYXRldGltZTsKICAgICAgICAkZGVhbC0+c2F2ZSgpOwoKICAgICAgICBpZiAoJHJlcXVlc3QtPmN1c3RvbUZpZWxkKSB7CiAgICAgICAgICAgIEN1c3RvbUZpZWxkOjpzYXZlRGF0YSgkZGVhbCwgJHJlcXVlc3QtPmN1c3RvbUZpZWxkKTsKICAgICAgICB9CgogICAgICAgIHJldHVybiAkdGhpcy0+c3VjY2VzcyhbJ2RlYWwnID0+ICRkZWFsXSwgJ0RlYWwgY3JlYXRlZCBzdWNjZXNzZnVsbHkuJyk7CiAgICB9',
    'replace' => 'ICAgIHB1YmxpYyBmdW5jdGlvbiBjcmVhdGVEZWFsKFJlcXVlc3QgJHJlcXVlc3QpCiAgICB7CiAgICAgICAgJG93bmVySWQgPSBBdXRoOjp1c2VyKCktPmNyZWF0b3JJZCgpOwoKICAgICAgICAkdmFsaWRhdG9yID0gVmFsaWRhdG9yOjptYWtlKAogICAgICAgICAgICAkcmVxdWVzdC0+YWxsKCksCiAgICAgICAgICAgIFsKICAgICAgICAgICAgICAgICduYW1lJyA9PiAncmVxdWlyZWQnLAogICAgICAgICAgICAgICAgLy8gJ2V4aXN0czpsZWFkcyxpZCcgb24gaXRzIG93biBsZXQgYSBkZWFsIGJlIGh1bmcgb24gYW5vdGhlcgogICAgICAgICAgICAgICAgLy8gY29tcGFueSdzIGxlYWQuIFRoZSB3ZWIgZm9ybSB3YXMgc2NvcGVkIG1vbnRocyBhZ287IHRoaXMgd2FzCiAgICAgICAgICAgICAgICAvLyBub3QuCiAgICAgICAgICAgICAgICAnbGVhZF9pZCcgPT4gWwogICAgICAgICAgICAgICAgICAgICdyZXF1aXJlZCcsCiAgICAgICAgICAgICAgICAgICAgUnVsZTo6ZXhpc3RzKCdsZWFkcycsICdpZCcpLT53aGVyZSgnY3JlYXRlZF9ieScsICRvd25lcklkKSwKICAgICAgICAgICAgICAgIF0sCiAgICAgICAgICAgICAgICAnc3RhZ2VfaWQnID0+ICdyZXF1aXJlZHxleGlzdHM6c3RhZ2VzLGlkJywKICAgICAgICAgICAgXQogICAgICAgICk7CgogICAgICAgIGlmICgkdmFsaWRhdG9yLT5mYWlscygpKSB7CiAgICAgICAgICAgIHJldHVybiAkdGhpcy0+ZXJyb3IoJHZhbGlkYXRvci0+ZXJyb3JzKCktPmZpcnN0KCksIDQyMik7CiAgICAgICAgfQoKICAgICAgICAvLyBCZXR3ZWVuIDIwMjYtMDUtMTYgYW5kIDIwMjYtMTAtMDggdGhpcyBlbmRwb2ludCB3cm90ZSBvdmVyIGEgaHVuZHJlZAogICAgICAgIC8vIGRlYWxzIGEgbWludXRlOiBzb21ldGhpbmcga2VwdCByZS1wb3N0aW5nIHRoZSBzYW1lICI8bGVhZD4gLSBEZWFsIgogICAgICAgIC8vIGFuZCBub3RoaW5nIGhlcmUgZXZlciBzYWlkIG5vLiBUd28gaWRlbnRpY2FsIGRlYWxzIG9uIG9uZSBsZWFkIGNhcnJ5CiAgICAgICAgLy8gbm8gaW5mb3JtYXRpb24sIHNvIHRoZSBmaXJzdCBvbmUgd2lucyBhbmQgZXZlcnkgcmVwZWF0IGdldHMgaXQgYmFjawogICAgICAgIC8vIGluc3RlYWQgb2YgYSBuZXcgcm93LiBUaGUgbmFtZWQgbG9jayBjbG9zZXMgdGhlIGdhcCBiZXR3ZWVuIHRoZQogICAgICAgIC8vIGxvb2t1cCBhbmQgdGhlIGluc2VydCB3aGVuIHR3byByZXF1ZXN0cyBhcnJpdmUgdG9nZXRoZXIuCiAgICAgICAgJGxvY2tOYW1lID0gJ2RlYWxfY3JlYXRlXycgLiAoaW50KSAkb3duZXJJZCAuICdfJyAuIChpbnQpICRyZXF1ZXN0LT5sZWFkX2lkOwogICAgICAgICRsb2NrID0gREI6OnNlbGVjdE9uZSgnU0VMRUNUIEdFVF9MT0NLKD8sIDUpIEFTIGdvdCcsIFskbG9ja05hbWVdKTsKICAgICAgICAkbG9ja2VkID0gJGxvY2sgJiYgKGludCkgJGxvY2stPmdvdCA9PT0gMTsKCiAgICAgICAgdHJ5IHsKICAgICAgICAgICAgJGV4aXN0aW5nID0gRGVhbDo6d2hlcmUoJ2NyZWF0ZWRfYnknLCAkb3duZXJJZCkKICAgICAgICAgICAgICAgIC0+d2hlcmUoJ2xlYWRfaWQnLCAkcmVxdWVzdC0+bGVhZF9pZCkKICAgICAgICAgICAgICAgIC0+d2hlcmUoJ25hbWUnLCAkcmVxdWVzdC0+bmFtZSkKICAgICAgICAgICAgICAgIC0+Zmlyc3QoKTsKCiAgICAgICAgICAgIGlmICgkZXhpc3RpbmcpIHsKICAgICAgICAgICAgICAgIHJldHVybiAkdGhpcy0+c3VjY2VzcygKICAgICAgICAgICAgICAgICAgICBbJ2RlYWwnID0+ICRleGlzdGluZywgJ2R1cGxpY2F0ZScgPT4gdHJ1ZV0sCiAgICAgICAgICAgICAgICAgICAgJ0EgZGVhbCB3aXRoIHRoaXMgbmFtZSBhbHJlYWR5IGV4aXN0cyBvbiB0aGlzIGxlYWQuJwogICAgICAgICAgICAgICAgKTsKICAgICAgICAgICAgfQoKICAgICAgICAgICAgJGxlYWQgPSBMZWFkOjpmaW5kKCRyZXF1ZXN0LT5sZWFkX2lkKTsKICAgICAgICAgICAgJHN0YWdlID0gU3RhZ2U6OmZpbmQoJHJlcXVlc3QtPnN0YWdlX2lkKTsKCiAgICAgICAgICAgICRkZWFsID0gbmV3IERlYWwoKTsKICAgICAgICAgICAgJGRlYWwtPm5hbWUgPSAkcmVxdWVzdC0+bmFtZTsKICAgICAgICAgICAgJGRlYWwtPmxlYWRfaWQgPSAkcmVxdWVzdC0+bGVhZF9pZDsKICAgICAgICAgICAgaWYgKCRyZXF1ZXN0LT5wcm9kdWN0cykgewogICAgICAgICAgICAgICAgJGRlYWwtPnByb2R1Y3RzID0gaW1wbG9kZSgnLCcsIGFycmF5X2ZpbHRlcigkcmVxdWVzdC0+cHJvZHVjdHMpKTsKICAgICAgICAgICAgfQogICAgICAgICAgICAkZGVhbC0+Y3JlYXRlZF9ieSA9ICRvd25lcklkOwogICAgICAgICAgICAvLyBOb3RoaW5nIGluIGFwcC9IdHRwL0NvbnRyb2xsZXJzL0FwaSBldmVyIHNldCB0aGlzLCB3aGljaCBpcyB3aHkKICAgICAgICAgICAgLy8gZXZlcnkgQVBJIHJvdyBzaG93cyAiLSIgdW5kZXIgQ3JlYXRlZCBCeSBvbiB0aGUgbGVhZCBwYWdlIGFuZAogICAgICAgICAgICAvLyB3aHkgdGhlIHNvdXJjZSBvZiBhIHJvdyBjb3VsZCBub3QgYmUgdG9sZCBhcGFydCBmcm9tIHRoZSB3ZWIuCiAgICAgICAgICAgICRkZWFsLT5hZGRlZF9ieSA9IEF1dGg6OmlkKCk7CiAgICAgICAgICAgICRkZWFsLT5zdGFnZV9pZCA9ICRyZXF1ZXN0LT5zdGFnZV9pZDsKICAgICAgICAgICAgJGRlYWwtPnN0YXR1cyA9ICRzdGFnZSA/ICRzdGFnZS0+bmFtZSA6IG51bGw7CiAgICAgICAgICAgICRkZWFsLT5waXBlbGluZV9pZCA9ICRsZWFkLT5waXBlbGluZV9pZCA/PyAwOwogICAgICAgICAgICAkZGVhbC0+bm90ZXMgPSAkcmVxdWVzdC0+bm90ZXM7CiAgICAgICAgICAgICRkZWFsLT5kYXRldGltZSA9ICRyZXF1ZXN0LT5kYXRldGltZTsKICAgICAgICAgICAgJGRlYWwtPnNhdmUoKTsKICAgICAgICB9IGZpbmFsbHkgewogICAgICAgICAgICBpZiAoJGxvY2tlZCkgewogICAgICAgICAgICAgICAgREI6OnNlbGVjdCgnU0VMRUNUIFJFTEVBU0VfTE9DSyg/KSBBUyByZWxlYXNlZCcsIFskbG9ja05hbWVdKTsKICAgICAgICAgICAgfQogICAgICAgIH0KCiAgICAgICAgaWYgKCRyZXF1ZXN0LT5jdXN0b21GaWVsZCkgewogICAgICAgICAgICBDdXN0b21GaWVsZDo6c2F2ZURhdGEoJGRlYWwsICRyZXF1ZXN0LT5jdXN0b21GaWVsZCk7CiAgICAgICAgfQoKICAgICAgICByZXR1cm4gJHRoaXMtPnN1Y2Nlc3MoWydkZWFsJyA9PiAkZGVhbF0sICdEZWFsIGNyZWF0ZWQgc3VjY2Vzc2Z1bGx5LicpOwogICAgfQ==',
  ),
);
$classes = array (
  0 => 'App\\Http\\Controllers\\Api\\DealApiController',
);
$backupPrefix = 'deal-api-fix';

echo $BANNER . "\n\n";

// ---- locate the Laravel root -----------------------------------------------------

$root = __DIR__;
while (!file_exists($root . '/artisan') && dirname($root) !== $root) {
    $root = dirname($root);
}
if (!file_exists($root . '/artisan')) {
    fwrite(STDERR, "Could not locate the Laravel root (no artisan found).\n");
    exit(2);
}
$root = rtrim(str_replace('\\', '/', $root), '/');

$argvAll = $argv;
$apply = in_array('--apply', $argvAll, true);
$rollback = in_array('--rollback', $argvAll, true);
$simulateFail = null;
foreach ($argvAll as $a) {
    if (strpos($a, '--simulate-write-failure=') === 0) {
        $simulateFail = substr($a, strlen('--simulate-write-failure='));
    }
}

echo "Laravel root: $root\n";
echo 'Running as:   ' . (function_exists('posix_getpwuid') && function_exists('posix_geteuid')
    ? (posix_getpwuid(posix_geteuid())['name'] ?? 'unknown')
    : (getenv('USERNAME') ?: 'unknown')) . "\n";
echo 'Mode:         ' . ($rollback ? 'rollback' : ($apply ? 'apply' : 'check')) . "\n\n";

function lf($s)
{
    return str_replace("\r\n", "\n", $s);
}

function h16($s)
{
    return substr(hash('sha256', lf($s)), 0, 16);
}

/**
 * The nearest ancestor that exists, so we can report a useful problem for a path
 * whose parent directories are missing.
 */
function nearestExisting($path)
{
    $p = $path;
    while ($p && !file_exists($p) && dirname($p) !== $p) {
        $p = dirname($p);
    }
    return $p;
}

function writeProblem($root, $rel)
{
    $full = $root . '/' . $rel;
    if (!is_file($full)) {
        $near = nearestExisting($full);
        if ($near !== $full && is_file($near)) {
            return 'is not a directory: ' . str_replace($root . '/', '', $near);
        }
        return 'file not found';
    }
    if (!is_readable($full)) {
        return 'not readable';
    }
    if (!is_writable($full)) {
        return 'not writable by this user';
    }
    if (!is_writable(dirname($full))) {
        return 'the directory ' . str_replace($root . '/', '', dirname($full)) . ' is not writable by this user';
    }
    return null;
}

// ---- rollback ---------------------------------------------------------------------

function backupDirs($root, $prefix)
{
    $d = glob($root . '/storage/backups/' . $prefix . '-*', GLOB_ONLYDIR) ?: [];
    sort($d);
    return $d;
}

if ($rollback) {
    $dirs = backupDirs($root, $backupPrefix);
    if (!$dirs) {
        fwrite(STDERR, "No backup to roll back to under storage/backups/$backupPrefix-*.\n");
        exit(2);
    }
    $dir = end($dirs);
    echo "Restoring from $dir\n\n";

    $manifest = @file($dir . '/MANIFEST.txt', FILE_IGNORE_NEW_LINES) ?: [];
    $applied = [];
    foreach ($manifest as $line) {
        $p = preg_split('/\s+/', trim($line));
        if (count($p) >= 3 && $p[0] === 'FILE') {
            // FILE <rel> <hash-before> <hash-after>
            $applied[$p[1]] = ['before' => $p[2], 'after' => isset($p[3]) ? $p[3] : null];
        }
    }

    $kept = 0;
    $restored = 0;
    foreach ($applied as $rel => $h) {
        $full = $root . '/' . $rel;
        $saved = $dir . '/' . $rel;
        if (!is_file($saved)) {
            echo "[SKIP] $rel -- not in the backup\n";
            continue;
        }
        if (is_file($full) && $h['after'] && h16(file_get_contents($full)) !== $h['after']) {
            echo "[KEEP] $rel -- changed since the patch was applied, leaving it alone\n";
            $kept++;
            continue;
        }
        if (@file_put_contents($full, file_get_contents($saved)) === false) {
            fwrite(STDERR, "[FAIL] $rel -- could not restore\n");
            $kept++;
            continue;
        }
        echo "[ OK ] $rel restored\n";
        $restored++;
    }

    echo "\nRestored $restored file(s)" . ($kept ? ", kept $kept" : '') . ".\n";
    echo "Next: php artisan view:clear && php artisan optimize:clear\n";
    exit($kept ? 2 : 0);
}

// ---- plan --------------------------------------------------------------------------

$byPath = [];
foreach ($edits as $e) {
    $byPath[$e['path']][] = $e;
}

$blocked = 0;
$plan = [];      // rel => new content
$before = [];    // rel => original content
$noop = 0;

foreach ($byPath as $rel => $list) {
    $problem = writeProblem($root, $rel);
    if ($problem !== null) {
        echo "[FAIL] $rel -- $problem\n";
        $blocked++;
        continue;
    }

    $raw = file_get_contents($root . '/' . $rel);
    $crlf = strpos($raw, "\r\n") !== false;
    $content = lf($raw);
    $before[$rel] = $raw;

    $changed = 0;
    $already = 0;
    $bad = false;

    foreach ($list as $e) {
        $find = lf(base64_decode($e['find']));
        $repl = lf(base64_decode($e['replace']));
        $nFind = substr_count($content, $find);
        $nRepl = substr_count($content, $repl);

        // A pure insertion keeps the anchor inside its own replacement, so the
        // anchor still matches after the change. For those the replacement being
        // present is what says "already applied"; otherwise the anchor is.
        if (strpos($repl, $find) !== false) {
            if ($nRepl >= 1) {
                $already++;
                continue;
            }
            if ($nFind === 1) {
                $content = str_replace($find, $repl, $content);
                $changed++;
                continue;
            }
            echo '[FAIL] ' . $rel . ' -- anchor "' . $e['label'] . '" found ' . $nFind . ' times, expected exactly 1' . "\n";
            $bad = true;
            continue;
        }

        if ($nFind === 1) {
            $content = str_replace($find, $repl, $content);
            $changed++;
            continue;
        }
        if ($nFind === 0 && $nRepl >= 1) {
            $already++;
            continue;
        }
        echo '[FAIL] ' . $rel . ' -- anchor "' . $e['label'] . '" found ' . $nFind . ' times, expected exactly 1'
            . ($nRepl ? ' (and the replacement is not there either)' : '') . "\n";
        $bad = true;
    }

    if ($bad) {
        $blocked++;
        continue;
    }

    if ($changed === 0) {
        echo "[SKIP] $rel -- already applied ($already change(s))\n";
        $noop++;
        continue;
    }

    if ($crlf) {
        echo "       note: $rel had CRLF line endings; it will be written as LF\n";
    }

    echo "[READY] $rel -- $changed change(s)" . ($already ? ", $already already applied" : '') . "\n";
    $plan[$rel] = $content;
}

echo "\n--------------------------------------------------\n";
echo 'ready: ' . count($plan) . '   blocked: ' . $blocked . "\n";

if ($blocked) {
    echo "\nNOTHING WAS WRITTEN. An anchor did not match, which means the server copy\n";
    echo "of that file is not what this patch was built against. Send the file back\n";
    echo "so the change can be re-made against it.\n";
    exit(2);
}

if (!$plan) {
    echo "\nEverything is already applied.\n";
    exit(0);
}

if (!$apply) {
    echo "\nDry run only. Re-run with --apply to write these files.\n";
    exit(0);
}

// ---- backup --------------------------------------------------------------------------

$stamp = date('Ymd-His');
$dir = $root . '/storage/backups/' . $backupPrefix . '-' . $stamp;

$backupsRoot = $root . '/storage/backups';
$near = nearestExisting($backupsRoot);
if (is_file($near)) {
    fwrite(STDERR, "The backup location $backupsRoot is blocked by a file at $near.\n");
    exit(3);
}
if (!is_dir($backupsRoot) && !@mkdir($backupsRoot, 0775, true)) {
    fwrite(STDERR, "Could not create the backup location $backupsRoot.\n");
    exit(3);
}
if (!is_writable($backupsRoot)) {
    fwrite(STDERR, "The backup location $backupsRoot is not writable by this user.\n");
    exit(3);
}
if (!@mkdir($dir, 0775, true)) {
    fwrite(STDERR, "Could not create $dir.\n");
    exit(3);
}

echo "Backing up to $dir\n";
$manifestLines = [];
foreach ($plan as $rel => $new) {
    $target = $dir . '/' . $rel;
    if (!is_dir(dirname($target)) && !@mkdir(dirname($target), 0775, true)) {
        fwrite(STDERR, "Could not create " . dirname($target) . ".\n");
        exit(3);
    }
    if (@file_put_contents($target, $before[$rel]) === false) {
        fwrite(STDERR, "Could not write the backup copy of $rel.\n");
        exit(3);
    }
    if (h16(file_get_contents($target)) !== h16($before[$rel])) {
        fwrite(STDERR, "The backup copy of $rel does not match the original.\n");
        exit(3);
    }
    echo "       saved $rel\n";
    $manifestLines[] = 'FILE ' . $rel . ' ' . h16($before[$rel]) . ' ' . h16($new);
}
file_put_contents($dir . '/MANIFEST.txt', implode("\n", $manifestLines) . "\n");
file_put_contents($dir . '/.htaccess', "Require all denied\n");
echo "       backup verified\n\n";

// ---- write -----------------------------------------------------------------------------

$written = [];

function undo($root, $before, $written)
{
    foreach ($written as $rel) {
        @file_put_contents($root . '/' . $rel, $before[$rel]);
    }
}

foreach ($plan as $rel => $new) {
    $full = $root . '/' . $rel;
    $tmp = $full . '.tmp-deal-api-fix';

    if ($simulateFail === $rel) {
        fwrite(STDERR, "[FAIL] $rel -- simulated write failure\n");
        undo($root, $before, $written);
        echo "\nEverything written so far was put back. The site is back exactly as it was.\n";
        exit(4);
    }

    if (@file_put_contents($tmp, $new) === false || !is_file($tmp) || h16(file_get_contents($tmp)) !== h16($new)) {
        @unlink($tmp);
        fwrite(STDERR, "[FAIL] $rel -- could not write\n");
        undo($root, $before, $written);
        echo "\nEverything written so far was put back. The site is back exactly as it was.\n";
        exit(4);
    }
    if (!@rename($tmp, $full)) {
        @unlink($tmp);
        fwrite(STDERR, "[FAIL] $rel -- could not replace the file\n");
        undo($root, $before, $written);
        echo "\nEverything written so far was put back. The site is back exactly as it was.\n";
        exit(4);
    }
    if (h16(file_get_contents($full)) !== h16($new)) {
        fwrite(STDERR, "[FAIL] $rel -- the file on disk does not match what was written\n");
        undo($root, $before, $written);
        echo "\nEverything written so far was put back. The site is back exactly as it was.\n";
        exit(4);
    }

    echo "[ OK ] $rel\n";
    $written[] = $rel;
}

// ---- the changed classes must still load ------------------------------------------------

if ($classes) {
    $code = "require " . var_export($root . '/vendor/autoload.php', true) . ";\n";
    foreach ($classes as $c) {
        $code .= "if (!class_exists(" . var_export($c, true) . ")) { fwrite(STDERR, 'cannot load " . $c . "'); exit(1); }\n";
    }
    $code .= "exit(0);\n";
    $probe = $root . '/storage/deals-fix-probe.php';
    file_put_contents($probe, "<?php\n" . $code);
    $out = [];
    $rc = 0;
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($probe) . ' 2>&1', $out, $rc);
    @unlink($probe);

    if ($rc !== 0) {
        fwrite(STDERR, "\n[FAIL] " . implode("\n", $out) . "\n");
        undo($root, $before, $written);
        echo "\nEverything was put back. The site is back exactly as it was.\n";
        echo "If this keeps happening, run: composer dump-autoload\n";
        exit(5);
    }
    echo "\n[ OK ] every changed class loads through the autoloader\n";
}

echo "\nApplied " . count($written) . " file(s).\n";
echo "Next:\n";
echo "  php artisan view:clear\n";
echo "  php artisan optimize:clear\n";
echo "Then watch: SELECT COUNT(*) FROM deals WHERE created_at >= NOW() - INTERVAL 10 MINUTE;\n";
echo "To undo: php " . basename(__FILE__) . " --rollback\n";
exit(0);
