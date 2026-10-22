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
 *   php apply_leads_filter_restore_fix_v2.php              dry run, writes nothing
 *   php apply_leads_filter_restore_fix_v2.php --apply      back up, then apply everything
 *   php apply_leads_filter_restore_fix_v2.php --rollback   restore the newest backup
 *
 * Exit codes: 0 fine, 2 an anchor or a preflight check failed (nothing written),
 * 3 the backup could not be taken, 4 a write failed (everything undone),
 * 5 a changed class no longer loads (everything undone).
 */

$BANNER = 'apply_leads_filter_restore_fix v2  (restoring a saved filter overwrote it with a blank one)';
$edits = array (
  0 => 
  array (
    'path' => 'resources/views/leads/index.blade.php',
    'label' => 'runLeadsFilter',
    'find' => 'ICAgICAgICAkKGRvY3VtZW50KS5vbignY2xpY2snLCAnI2FwcGx5LWZpbHRlcicsIGZ1bmN0aW9uKCkgewogICAgICAgICAgICAvLyBDb2xsZWN0IGRhdGEgbWFudWFsbHkgdG8gam9pbiBhcnJheXMgd2l0aCBjb21tYXMKICAgICAgICAgICAgdmFyIGRhdGEgPSB7CiAgICAgICAgICAgICAgICBwaXBlbGluZTogJCgnI2ZpbHRlcl9waXBlbGluZScpLnZhbCgpLA==',
    'replace' => 'ICAgICAgICBmdW5jdGlvbiBjb2xsZWN0TGVhZHNGaWx0ZXIoKSB7CiAgICAgICAgICAgIC8vIENvbGxlY3QgZGF0YSBtYW51YWxseSB0byBqb2luIGFycmF5cyB3aXRoIGNvbW1hcwogICAgICAgICAgICByZXR1cm4gewogICAgICAgICAgICAgICAgcGlwZWxpbmU6ICQoJyNmaWx0ZXJfcGlwZWxpbmUnKS52YWwoKSw=',
  ),
  1 => 
  array (
    'path' => 'resources/views/leads/index.blade.php',
    'label' => 'apply-filter handler',
    'find' => 'ICAgICAgICAgICAgICAgIHVuYXNzaWduZWQ6ICQoJyNmaWx0ZXJfdW5hc3NpZ25lZCcpLmlzKCc6Y2hlY2tlZCcpID8gMSA6IDAsCiAgICAgICAgICAgICAgICBub19hY3Rpdml0eTogJCgnI2ZpbHRlcl9ub19hY3Rpdml0eScpLmlzKCc6Y2hlY2tlZCcpID8gMSA6IDAKICAgICAgICAgICAgfTsKCiAgICAgICAgICAgIC8vIFN0b3JlIGZpbHRlciBkYXRhIGdsb2JhbGx5IGZvciBwYWdpbmF0aW9uCiAgICAgICAgICAgIHdpbmRvdy5jdXJyZW50RmlsdGVyRGF0YSA9IGRhdGE7CiAgICAgICAgICAgIGxvY2FsU3RvcmFnZS5zZXRJdGVtKExFQURTX0ZJTFRFUl9LRVksIEpTT04uc3RyaW5naWZ5KGRhdGEpKTsKICAgICAgICAgICAgbGVhZHNGaWx0ZXJCYWRnZShoYXNMZWFkc0FjdGl2ZUZpbHRlcihkYXRhKSk7CgogICAgICAgICAgICAkLmFqYXgoew==',
    'replace' => 'ICAgICAgICAgICAgICAgIHVuYXNzaWduZWQ6ICQoJyNmaWx0ZXJfdW5hc3NpZ25lZCcpLmlzKCc6Y2hlY2tlZCcpID8gMSA6IDAsCiAgICAgICAgICAgICAgICBub19hY3Rpdml0eTogJCgnI2ZpbHRlcl9ub19hY3Rpdml0eScpLmlzKCc6Y2hlY2tlZCcpID8gMSA6IDAKICAgICAgICAgICAgfTsKICAgICAgICB9CgogICAgICAgICQoZG9jdW1lbnQpLm9uKCdjbGljaycsICcjYXBwbHktZmlsdGVyJywgZnVuY3Rpb24oKSB7CiAgICAgICAgICAgIHZhciBkYXRhID0gY29sbGVjdExlYWRzRmlsdGVyKCk7CgogICAgICAgICAgICAvLyBTdG9yZSBmaWx0ZXIgZGF0YSBnbG9iYWxseSBmb3IgcGFnaW5hdGlvbgogICAgICAgICAgICB3aW5kb3cuY3VycmVudEZpbHRlckRhdGEgPSBkYXRhOwogICAgICAgICAgICBsb2NhbFN0b3JhZ2Uuc2V0SXRlbShMRUFEU19GSUxURVJfS0VZLCBKU09OLnN0cmluZ2lmeShkYXRhKSk7CiAgICAgICAgICAgIGxlYWRzRmlsdGVyQmFkZ2UoaGFzTGVhZHNBY3RpdmVGaWx0ZXIoZGF0YSkpOwoKICAgICAgICAgICAgcnVuTGVhZHNGaWx0ZXIoZGF0YSk7CiAgICAgICAgfSk7CgogICAgICAgIC8vIFB1dHMgYSBmaWx0ZXIgb2JqZWN0IG9uIHRoZSBib2FyZC4gUmVzdG9yaW5nIGEgc2F2ZWQgZmlsdGVyIGNhbGxzIHRoaXMKICAgICAgICAvLyB3aXRoIHRoZSBzYXZlZCBvYmplY3QgcmF0aGVyIHRoYW4gY2xpY2tpbmcgI2FwcGx5LWZpbHRlciwgYmVjYXVzZSB0aGF0CiAgICAgICAgLy8gaGFuZGxlciByZWFkcyB0aGUgaW5wdXRzIGJhY2sgLS0gYW5kIGN1c3RvbS5qcyBoYXMgYnkgdGhlbiB3cmFwcGVkIGV2ZXJ5CiAgICAgICAgLy8gLnNlbGVjdDIgaW4gYSBDaG9pY2VzIHdpZGdldCwgc28gdGhvc2UgcmVhZHMgY29tZSBiYWNrIGVtcHR5LiBDbGlja2luZyBpdAogICAgICAgIC8vIG9uIHJlc3RvcmUgdGhlcmVmb3JlIHdyb3RlIGEgYmxhbmsgZmlsdGVyIG92ZXIgdGhlIHNhdmVkIG9uZSBhbmQgYXNrZWQKICAgICAgICAvLyB0aGUgc2VydmVyIGZvciBldmVyeSBsZWFkLCB3aGljaCBpcyBleGFjdGx5IHdoYXQgInRoZSBmaWx0ZXIgaXMgZ29uZSIKICAgICAgICAvLyBsb29rZWQgbGlrZS4KICAgICAgICBmdW5jdGlvbiBydW5MZWFkc0ZpbHRlcihkYXRhKSB7CiAgICAgICAgICAgICQuYWpheCh7',
  ),
  2 => 
  array (
    'path' => 'resources/views/leads/index.blade.php',
    'label' => 'handler tail',
    'find' => 'ICAgICAgICAgICAgfSk7CiAgICAgICAgICAgICQoJyNhZHZhbmNlZEZpbHRlck1vZGFsJykubW9kYWwoJ2hpZGUnKTsKICAgICAgICB9KTsKCiAgICAgICAgJChkb2N1bWVudCkub24oJ2NsaWNrJywgJyNjbGVhci1maWx0ZXInLCBmdW5jdGlvbigpIHs=',
    'replace' => 'ICAgICAgICAgICAgfSk7CiAgICAgICAgICAgICQoJyNhZHZhbmNlZEZpbHRlck1vZGFsJykubW9kYWwoJ2hpZGUnKTsKICAgICAgICB9CgogICAgICAgICQoZG9jdW1lbnQpLm9uKCdjbGljaycsICcjY2xlYXItZmlsdGVyJywgZnVuY3Rpb24oKSB7',
  ),
  3 => 
  array (
    'path' => 'resources/views/leads/index.blade.php',
    'label' => 'restore block',
    'find' => 'ICAgICAgICAgICAgICAgICAgICBpZiAoaGFzTGVhZHNBY3RpdmVGaWx0ZXIoX3NmKSkgewogICAgICAgICAgICAgICAgICAgICAgICBsZWFkc0ZpbHRlckJhZGdlKHRydWUpOwogICAgICAgICAgICAgICAgICAgICAgICBpZiAoX3NmLnBpcGVsaW5lKSB7CiAgICAgICAgICAgICAgICAgICAgICAgICAgICAvLyBObyAudHJpZ2dlcignY2hhbmdlJykgaGVyZTogdGhlIGNoYW5nZSBoYW5kbGVyIGZpcmVzIGl0cwogICAgICAgICAgICAgICAgICAgICAgICAgICAgLy8gb3duIGdldFN0YWdlcygpIHdpdGggbm8gY2FsbGJhY2ssIGFuZCB0aGF0IHNlY29uZCByZXF1ZXN0CiAgICAgICAgICAgICAgICAgICAgICAgICAgICAvLyByYWNlZCB0aGlzIG9uZSBhbmQgZW1wdGllZCB0aGUgbGlzdCBhZ2FpbiBhZnRlciB0aGUgc2F2ZWQKICAgICAgICAgICAgICAgICAgICAgICAgICAgIC8vIHN0YWdlIGhhZCBiZWVuIHB1dCBiYWNrLiBXZSBjYWxsIGdldFN0YWdlcyBvdXJzZWx2ZXMgYmVsb3cuCiAgICAgICAgICAgICAgICAgICAgICAgICAgICAkKCcjZmlsdGVyX3BpcGVsaW5lJykudmFsKF9zZi5waXBlbGluZSk7CiAgICAgICAgICAgICAgICAgICAgICAgICAgICBnZXRTdGFnZXMoX3NmLnBpcGVsaW5lLCBmdW5jdGlvbigpIHsKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICBpZiAoX3NmLnN0YWdlKSAkKCcjZmlsdGVyX3N0YWdlJykudmFsKF9zZi5zdGFnZSkudHJpZ2dlcignY2hhbmdlJyk7CiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgJCgnI2FwcGx5LWZpbHRlcicpLnRyaWdnZXIoJ2NsaWNrJyk7CiAgICAgICAgICAgICAgICAgICAgICAgICAgICB9KTsKICAgICAgICAgICAgICAgICAgICAgICAgfSBlbHNlIHsKICAgICAgICAgICAgICAgICAgICAgICAgICAgICQoJyNhcHBseS1maWx0ZXInKS50cmlnZ2VyKCdjbGljaycpOwogICAgICAgICAgICAgICAgICAgICAgICB9CiAgICAgICAgICAgICAgICAgICAgfQogICAgICAgICAgICAgICAgfSBjYXRjaChlKSB7CiAgICAgICAgICAgICAgICAgICAgbG9jYWxTdG9yYWdlLnJlbW92ZUl0ZW0oTEVBRFNfRklMVEVSX0tFWSk7CiAgICAgICAgICAgICAgICB9',
    'replace' => 'ICAgICAgICAgICAgICAgIH0gY2F0Y2goZSkgewogICAgICAgICAgICAgICAgICAgIC8vIEZpbGxpbmcgdGhlIGJveGVzIGJhY2sgaW4gaXMgYSBjb252ZW5pZW5jZSBhbmQgbXVzdCBuZXZlciBjb3N0CiAgICAgICAgICAgICAgICAgICAgLy8gdGhlIGZpbHRlciBpdHNlbGYuIFRoaXMgdXNlZCB0byBjYWxsIGxvY2FsU3RvcmFnZS5yZW1vdmVJdGVtKCksCiAgICAgICAgICAgICAgICAgICAgLy8gc28gYSBzaW5nbGUgYmFkIGxpbmUgdGhyZXcgdGhlIHdob2xlIHNhdmVkIGZpbHRlciBhd2F5LgogICAgICAgICAgICAgICAgICAgIGNvbnNvbGUubG9nKCdjb3VsZCBub3QgZmlsbCB0aGUgZmlsdGVyIGZvcm06JywgZSk7CiAgICAgICAgICAgICAgICB9CgogICAgICAgICAgICAgICAgaWYgKF9zZiAmJiBoYXNMZWFkc0FjdGl2ZUZpbHRlcihfc2YpKSB7CiAgICAgICAgICAgICAgICAgICAgbGVhZHNGaWx0ZXJCYWRnZSh0cnVlKTsKICAgICAgICAgICAgICAgICAgICB3aW5kb3cuY3VycmVudEZpbHRlckRhdGEgPSBfc2Y7CgogICAgICAgICAgICAgICAgICAgIC8vIFRoZSBzYXZlZCBvYmplY3QgZ29lcyBzdHJhaWdodCB0byB0aGUgc2VydmVyLiBOb3RoaW5nIGhlcmUKICAgICAgICAgICAgICAgICAgICAvLyByZWFkcyB0aGUgaW5wdXRzIGJhY2sgYW5kIG5vdGhpbmcgaGVyZSB3cml0ZXMgdG8gbG9jYWxTdG9yYWdlLAogICAgICAgICAgICAgICAgICAgIC8vIHNvIGEgQ2hvaWNlcyB3aWRnZXQgdGhhdCBoYXMgbm90IGNhdWdodCB1cCBjYW5ub3QgYmxhbmsgdGhlCiAgICAgICAgICAgICAgICAgICAgLy8gZmlsdGVyIG91dC4KICAgICAgICAgICAgICAgICAgICBydW5MZWFkc0ZpbHRlcihfc2YpOwoKICAgICAgICAgICAgICAgICAgICAvLyBSZWZyZXNoaW5nIHRoZSBzdGFnZSBsaXN0IG9ubHkgY2hhbmdlcyB3aGF0IHRoZSBkcm9wZG93biBzaG93cy4KICAgICAgICAgICAgICAgICAgICBpZiAoX3NmLnBpcGVsaW5lKSB7CiAgICAgICAgICAgICAgICAgICAgICAgICQoJyNmaWx0ZXJfcGlwZWxpbmUnKS52YWwoX3NmLnBpcGVsaW5lKTsKICAgICAgICAgICAgICAgICAgICAgICAgZ2V0U3RhZ2VzKF9zZi5waXBlbGluZSwgZnVuY3Rpb24oKSB7CiAgICAgICAgICAgICAgICAgICAgICAgICAgICBpZiAoX3NmLnN0YWdlKSAkKCcjZmlsdGVyX3N0YWdlJykudmFsKF9zZi5zdGFnZSk7CiAgICAgICAgICAgICAgICAgICAgICAgIH0pOwogICAgICAgICAgICAgICAgICAgIH0KICAgICAgICAgICAgICAgIH0=',
  ),
);
$classes = array (
);
$backupPrefix = 'leads-restore-fix';

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
    $tmp = $full . '.tmp-leads-restore-fix';

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
echo "Then filter /leads, press F5, and check the board is still filtered.\n";
echo "To undo: php " . basename(__FILE__) . " --rollback\n";
exit(0);
