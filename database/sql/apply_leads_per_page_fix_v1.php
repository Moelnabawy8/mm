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
 *   php apply_leads_per_page_fix_v1.php              dry run, writes nothing
 *   php apply_leads_per_page_fix_v1.php --apply      back up, then apply everything
 *   php apply_leads_per_page_fix_v1.php --rollback   restore the newest backup
 *
 * Exit codes: 0 fine, 2 an anchor or a preflight check failed (nothing written),
 * 3 the backup could not be taken, 4 a write failed (everything undone),
 * 5 a changed class no longer loads (everything undone).
 */

$BANNER = 'apply_leads_per_page_fix v1  (the rows-per-page selector on /leads changed nothing)';
$edits = array (
  0 => 
  array (
    'path' => 'app/Http/Controllers/LeadController.php',
    'label' => 'board page size',
    'find' => 'ICAgICAgICAgICAgTGVhZFNjb3BlOjphcHBseVZpc2liaWxpdHkoJHF1ZXJ5LCAkdXNyKTsKICAgICAgICAgICAgJGxlYWRzID0gJHF1ZXJ5LT53aXRoKFsndXNlcnMnLCAnc3RhZ2UnXSkKICAgICAgICAgICAgICAgIC0+d2l0aENvdW50KFsnZGVhbHMnLCAnc2FsZXNNZWV0aW5ncycsICdkZWFsUHVyY2hhc2VzJ10pCiAgICAgICAgICAgICAgICAtPm9yZGVyQnlSYXcoJ0NBU1QoQ09BTEVTQ0UobGVhZHMuZGF0ZSwgbGVhZHMuY3JlYXRlZF9hdCkgQVMgREFURVRJTUUpIERFU0MnKQogICAgICAgICAgICAgICAgLT5wYWdpbmF0ZSgyNSk7',
    'replace' => 'ICAgICAgICAgICAgTGVhZFNjb3BlOjphcHBseVZpc2liaWxpdHkoJHF1ZXJ5LCAkdXNyKTsKICAgICAgICAgICAgLy8gV2FzIGEgaGFyZC1jb2RlZCAyNSwgc28gdGhlIHBlci1wYWdlIHNlbGVjdG9yIGNoYW5nZWQgbm90aGluZyBvbiB0aGUKICAgICAgICAgICAgLy8gZmlyc3QgcmVuZGVyLiBTYW1lIHJ1bGUgYXMgdGhlIEFKQVggYnJhbmNoIGFib3ZlIGFuZCBhcyAvbGVhZHMvbGlzdDoKICAgICAgICAgICAgLy8gYW55dGhpbmcgdGhhdCBpcyBub3Qgb25lIG9mIHRoZSBvZmZlcmVkIHNpemVzIGZhbGxzIGJhY2sgdG8gMjUuCiAgICAgICAgICAgICRsaW1pdCA9IChpbnQpICRyZXF1ZXN0LT5nZXQoJ2xpbWl0JywgMjUpOwogICAgICAgICAgICBpZiAoIWluX2FycmF5KCRsaW1pdCwgc2VsZjo6UEFHRV9TSVpFUykpIHsKICAgICAgICAgICAgICAgICRsaW1pdCA9IDI1OwogICAgICAgICAgICB9CgogICAgICAgICAgICAkbGVhZHMgPSAkcXVlcnktPndpdGgoWyd1c2VycycsICdzdGFnZSddKQogICAgICAgICAgICAgICAgLT53aXRoQ291bnQoWydkZWFscycsICdzYWxlc01lZXRpbmdzJywgJ2RlYWxQdXJjaGFzZXMnXSkKICAgICAgICAgICAgICAgIC0+b3JkZXJCeVJhdygnQ0FTVChDT0FMRVNDRShsZWFkcy5kYXRlLCBsZWFkcy5jcmVhdGVkX2F0KSBBUyBEQVRFVElNRSkgREVTQycpCiAgICAgICAgICAgICAgICAtPnBhZ2luYXRlKCRsaW1pdCk7',
  ),
  1 => 
  array (
    'path' => 'resources/views/leads/index.blade.php',
    'label' => 'limit helpers',
    'find' => 'ICAgICAgICBmdW5jdGlvbiBzYXZlZExlYWRzUGFnZSgpIHsKICAgICAgICAgICAgdHJ5IHsKICAgICAgICAgICAgICAgIHJldHVybiBwYXJzZUludChsb2NhbFN0b3JhZ2UuZ2V0SXRlbShMRUFEU19QQUdFX0tFWSkgfHwgJzEnLCAxMCkgfHwgMTsKICAgICAgICAgICAgfSBjYXRjaCAoZSkgewogICAgICAgICAgICAgICAgcmV0dXJuIDE7CiAgICAgICAgICAgIH0KICAgICAgICB9',
    'replace' => 'ICAgICAgICBmdW5jdGlvbiBzYXZlZExlYWRzUGFnZSgpIHsKICAgICAgICAgICAgdHJ5IHsKICAgICAgICAgICAgICAgIHJldHVybiBwYXJzZUludChsb2NhbFN0b3JhZ2UuZ2V0SXRlbShMRUFEU19QQUdFX0tFWSkgfHwgJzEnLCAxMCkgfHwgMTsKICAgICAgICAgICAgfSBjYXRjaCAoZSkgewogICAgICAgICAgICAgICAgcmV0dXJuIDE7CiAgICAgICAgICAgIH0KICAgICAgICB9CgogICAgICAgIC8vIFRoZSBwZXItcGFnZSBzZWxlY3RvciByZWxvYWRzIHRoZSBib2FyZCB3aXRoID9saW1pdD1OLCBidXQgZXZlcnkgbGF0ZXIKICAgICAgICAvLyByZXF1ZXN0IHdlbnQgb3V0IHdpdGhvdXQgaXQsIHNvIHRoZSBzZXJ2ZXIgZmVsbCBiYWNrIHRvIDI1IGFuZCB0aGUKICAgICAgICAvLyBjaG9pY2UgbG9va2VkIGlnbm9yZWQuCiAgICAgICAgZnVuY3Rpb24gc2F2ZWRMZWFkc0xpbWl0KCkgewogICAgICAgICAgICB0cnkgewogICAgICAgICAgICAgICAgdmFyIGVsID0gZG9jdW1lbnQuZ2V0RWxlbWVudEJ5SWQoJ3Blci1wYWdlLXNlbGVjdCcpOwogICAgICAgICAgICAgICAgdmFyIHZhbHVlID0gcGFyc2VJbnQoKGVsICYmIGVsLnZhbHVlKSB8fCBsb2NhbFN0b3JhZ2UuZ2V0SXRlbSgnbGVhZHNfcGVyX3BhZ2UnKSB8fCAnMjUnLCAxMCk7CiAgICAgICAgICAgICAgICByZXR1cm4gdmFsdWUgPiAwID8gdmFsdWUgOiAyNTsKICAgICAgICAgICAgfSBjYXRjaCAoZSkgewogICAgICAgICAgICAgICAgcmV0dXJuIDI1OwogICAgICAgICAgICB9CiAgICAgICAgfQoKICAgICAgICBmdW5jdGlvbiBsZWFkc1VybFdpdGgodXJsLCBwYXJhbXMpIHsKICAgICAgICAgICAgT2JqZWN0LmtleXMocGFyYW1zKS5mb3JFYWNoKGZ1bmN0aW9uKGtleSkgewogICAgICAgICAgICAgICAgdmFyIHZhbHVlID0gcGFyYW1zW2tleV07CiAgICAgICAgICAgICAgICBpZiAoIXZhbHVlIHx8IG5ldyBSZWdFeHAoJ1s/Jl0nICsga2V5ICsgJz0nKS50ZXN0KHVybCkpIHsKICAgICAgICAgICAgICAgICAgICByZXR1cm47CiAgICAgICAgICAgICAgICB9CiAgICAgICAgICAgICAgICB1cmwgKz0gKHVybC5pbmRleE9mKCc/JykgPT09IC0xID8gJz8nIDogJyYnKSArIGtleSArICc9JyArIGVuY29kZVVSSUNvbXBvbmVudCh2YWx1ZSk7CiAgICAgICAgICAgIH0pOwoKICAgICAgICAgICAgcmV0dXJuIHVybDsKICAgICAgICB9',
  ),
  2 => 
  array (
    'path' => 'resources/views/leads/index.blade.php',
    'label' => 'filter carries the limit',
    'find' => 'ICAgICAgICBmdW5jdGlvbiBydW5MZWFkc0ZpbHRlcihkYXRhLCBwYWdlKSB7CiAgICAgICAgICAgIHZhciB1cmwgPSAne3sgcm91dGUoJ2xlYWRzLmluZGV4JykgfX0nOwogICAgICAgICAgICBpZiAocGFnZSAmJiBwYWdlID4gMSkgewogICAgICAgICAgICAgICAgdXJsICs9ICh1cmwuaW5kZXhPZignPycpID09PSAtMSA/ICc/JyA6ICcmJykgKyAncGFnZT0nICsgcGFnZTsKICAgICAgICAgICAgfQ==',
    'replace' => 'ICAgICAgICBmdW5jdGlvbiBydW5MZWFkc0ZpbHRlcihkYXRhLCBwYWdlKSB7CiAgICAgICAgICAgIHZhciB1cmwgPSBsZWFkc1VybFdpdGgoJ3t7IHJvdXRlKCdsZWFkcy5pbmRleCcpIH19JywgewogICAgICAgICAgICAgICAgcGFnZTogcGFnZSAmJiBwYWdlID4gMSA/IHBhZ2UgOiAnJywKICAgICAgICAgICAgICAgIGxpbWl0OiBzYXZlZExlYWRzTGltaXQoKQogICAgICAgICAgICB9KTs=',
  ),
  3 => 
  array (
    'path' => 'resources/views/leads/index.blade.php',
    'label' => 'pagination carries the limit',
    'find' => 'ICAgICAgICAgICAgICAgICAgICByZW1lbWJlckxlYWRzUGFnZShsZWFkc1BhZ2VGcm9tVXJsKHVybCkpOwogICAgICAgICAgICAgICAgICAgICQuYWpheCh7CiAgICAgICAgICAgICAgICAgICAgICAgIHVybDogdXJsLAogICAgICAgICAgICAgICAgICAgICAgICB0eXBlOiAnR0VUJywKICAgICAgICAgICAgICAgICAgICAgICAgZGF0YTogd2luZG93LmN1cnJlbnRGaWx0ZXJEYXRhLA==',
    'replace' => 'ICAgICAgICAgICAgICAgICAgICByZW1lbWJlckxlYWRzUGFnZShsZWFkc1BhZ2VGcm9tVXJsKHVybCkpOwogICAgICAgICAgICAgICAgICAgICQuYWpheCh7CiAgICAgICAgICAgICAgICAgICAgICAgIC8vIHRoZSBwYWdpbmF0b3IgYnVpbGRzIGl0cyBsaW5rcyB3aXRoIHBhZ2U9IGFsb25lLCBzbyB0aGUKICAgICAgICAgICAgICAgICAgICAgICAgLy8gcGFnZSBzaXplIGhhcyB0byBiZSBwdXQgYmFjayBvbiBldmVyeSBob3AKICAgICAgICAgICAgICAgICAgICAgICAgdXJsOiBsZWFkc1VybFdpdGgodXJsLCB7IGxpbWl0OiBzYXZlZExlYWRzTGltaXQoKSB9KSwKICAgICAgICAgICAgICAgICAgICAgICAgdHlwZTogJ0dFVCcsCiAgICAgICAgICAgICAgICAgICAgICAgIGRhdGE6IHdpbmRvdy5jdXJyZW50RmlsdGVyRGF0YSw=',
  ),
);
$classes = array (
  0 => 'App\\Http\\Controllers\\LeadController',
);
$backupPrefix = 'leads-per-page-fix';

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
    $tmp = $full . '.tmp-leads-per-page';

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
echo "Then set /leads to 50 per page and count the cards, filter, and page through.\n";
echo "To undo: php " . basename(__FILE__) . " --rollback\n";
exit(0);
