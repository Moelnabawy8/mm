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
 *   php apply_deal_api_permissions_v1.php              dry run, writes nothing
 *   php apply_deal_api_permissions_v1.php --apply      back up, then apply everything
 *   php apply_deal_api_permissions_v1.php --rollback   restore the newest backup
 *
 * Exit codes: 0 fine, 2 an anchor or a preflight check failed (nothing written),
 * 3 the backup could not be taken, 4 a write failed (everything undone),
 * 5 a changed class no longer loads (everything undone).
 */

$BANNER = 'apply_deal_api_permissions v1  (the deals API never checked a role; now it does)';
$edits = array (
  0 => 
  array (
    'path' => 'app/Http/Controllers/Api/DealApiController.php',
    'label' => 'create needs the permission',
    'find' => 'ICAgIHB1YmxpYyBmdW5jdGlvbiBjcmVhdGVEZWFsKFJlcXVlc3QgJHJlcXVlc3QpCiAgICB7CiAgICAgICAgJG93bmVySWQgPSBBdXRoOjp1c2VyKCktPmNyZWF0b3JJZCgpOw==',
    'replace' => 'ICAgIHB1YmxpYyBmdW5jdGlvbiBjcmVhdGVEZWFsKFJlcXVlc3QgJHJlcXVlc3QpCiAgICB7CiAgICAgICAgLy8gVGhlIHdlYiBmb3JtIGhhcyBhbHdheXMgcmVxdWlyZWQgdGhpczsgdGhlIEFQSSBuZXZlciBjaGVja2VkLCBzbyBhCiAgICAgICAgLy8gcm9sZSBtZWFudCBub3RoaW5nIGhlcmUuIFRoYXQgaXMgd2h5IGFuIGF1dG9tYXRpb24gaG9sZGluZyBhbgogICAgICAgIC8vIG9yZGluYXJ5IHVzZXIncyBjcmVkZW50aWFscyBjb3VsZCB3cml0ZSBkZWFscyBhdCB3aWxsLgogICAgICAgIGlmICghQXV0aDo6dXNlcigpLT5jYW4oJ2NyZWF0ZSBkZWFsJykpIHsKICAgICAgICAgICAgLy8gbG9nZ2VkIGxpa2UgdGhlIHJlc3Q6IHR1cm5pbmcgdGhlIHBlcm1pc3Npb24gb2ZmIGlzIGhvdyB0aGUKICAgICAgICAgICAgLy8gYXV0b21hdGlvbiBnZXRzIHN0b3BwZWQsIGFuZCB0aGUgbG9nIGlzIGhvdyB0aGF0IGlzIGNvbmZpcm1lZAogICAgICAgICAgICAkdGhpcy0+bG9nRGVhbEFwaUNhbGxlcigkcmVxdWVzdCwgJ3JlZnVzZWQnLCBudWxsKTsKCiAgICAgICAgICAgIHJldHVybiAkdGhpcy0+ZXJyb3IoJ1Blcm1pc3Npb24gRGVuaWVkLicsIDQwMyk7CiAgICAgICAgfQoKICAgICAgICAkb3duZXJJZCA9IEF1dGg6OnVzZXIoKS0+Y3JlYXRvcklkKCk7',
  ),
  1 => 
  array (
    'path' => 'app/Http/Controllers/Api/DealApiController.php',
    'label' => 'update needs the permission',
    'find' => 'ICAgIHB1YmxpYyBmdW5jdGlvbiB1cGRhdGVEZWFsKFJlcXVlc3QgJHJlcXVlc3QsICRpZCkKICAgIHsKICAgICAgICAkZGVhbCA9IERlYWw6OndoZXJlKCdpZCcsICRpZCktPndoZXJlKCdjcmVhdGVkX2J5JywgQXV0aDo6dXNlcigpLT5jcmVhdG9ySWQoKSktPmZpcnN0KCk7',
    'replace' => 'ICAgIHB1YmxpYyBmdW5jdGlvbiB1cGRhdGVEZWFsKFJlcXVlc3QgJHJlcXVlc3QsICRpZCkKICAgIHsKICAgICAgICBpZiAoIUF1dGg6OnVzZXIoKS0+Y2FuKCdlZGl0IGRlYWwnKSkgewogICAgICAgICAgICByZXR1cm4gJHRoaXMtPmVycm9yKCdQZXJtaXNzaW9uIERlbmllZC4nLCA0MDMpOwogICAgICAgIH0KCiAgICAgICAgJGRlYWwgPSBEZWFsOjp3aGVyZSgnaWQnLCAkaWQpLT53aGVyZSgnY3JlYXRlZF9ieScsIEF1dGg6OnVzZXIoKS0+Y3JlYXRvcklkKCkpLT5maXJzdCgpOw==',
  ),
  2 => 
  array (
    'path' => 'app/Http/Controllers/Api/DealApiController.php',
    'label' => 'delete needs the permission',
    'find' => 'ICAgIHB1YmxpYyBmdW5jdGlvbiBkZWxldGVEZWFsKCRpZCkKICAgIHsKICAgICAgICAkZGVhbCA9IERlYWw6OndoZXJlKCdpZCcsICRpZCktPndoZXJlKCdjcmVhdGVkX2J5JywgQXV0aDo6dXNlcigpLT5jcmVhdG9ySWQoKSktPmZpcnN0KCk7',
    'replace' => 'ICAgIHB1YmxpYyBmdW5jdGlvbiBkZWxldGVEZWFsKCRpZCkKICAgIHsKICAgICAgICBpZiAoIUF1dGg6OnVzZXIoKS0+Y2FuKCdkZWxldGUgZGVhbCcpKSB7CiAgICAgICAgICAgIHJldHVybiAkdGhpcy0+ZXJyb3IoJ1Blcm1pc3Npb24gRGVuaWVkLicsIDQwMyk7CiAgICAgICAgfQoKICAgICAgICAkZGVhbCA9IERlYWw6OndoZXJlKCdpZCcsICRpZCktPndoZXJlKCdjcmVhdGVkX2J5JywgQXV0aDo6dXNlcigpLT5jcmVhdG9ySWQoKSktPmZpcnN0KCk7',
  ),
);
$classes = array (
  0 => 'App\\Http\\Controllers\\Api\\DealApiController',
);
$backupPrefix = 'deal-api-perms';

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
    $tmp = $full . '.tmp-deal-api-perms';

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
echo "Then take 'create deal' off the automation's role and watch for 'refused' in the deal-api log.\n";
echo "To undo: php " . basename(__FILE__) . " --rollback\n";
exit(0);
