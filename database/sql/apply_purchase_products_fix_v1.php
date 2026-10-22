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
 *   php apply_purchase_products_fix_v1.php              dry run, writes nothing
 *   php apply_purchase_products_fix_v1.php --apply      back up, then apply everything
 *   php apply_purchase_products_fix_v1.php --rollback   restore the newest backup
 *
 * Exit codes: 0 fine, 2 an anchor or a preflight check failed (nothing written),
 * 3 the backup could not be taken, 4 a write failed (everything undone),
 * 5 a changed class no longer loads (everything undone).
 */

$BANNER = 'apply_purchase_products_fix v1  (Create Deal Purchase: the Products field is initialised on page load)';
$edits = array (
  0 => 
  array (
    'path' => 'resources/views/admin/deal_purchase/create.blade.php',
    'label' => 'products field init',
    'find' => 'ICAgICAgICAkKGRvY3VtZW50KS5vbignY2hhbmdlJywgJyNkZWFsX2lkJywgZnVuY3Rpb24oKSB7CiAgICAgICAgICAgIHZhciBkZWFsX2lkID0gJCh0aGlzKS52YWwoKTsKICAgICAgICAgICAgaWYgKGRlYWxfaWQpIHsKICAgICAgICAgICAgICAgICQuYWpheCh7CiAgICAgICAgICAgICAgICAgICAgdXJsOiAne3sgcm91dGUoJ2RlYWwtcHVyY2hhc2UuZ2V0LXByb2R1Y3RzJywgJycpIH19LycgKyBkZWFsX2lkLAogICAgICAgICAgICAgICAgICAgIHR5cGU6ICdHRVQnLAogICAgICAgICAgICAgICAgICAgIHN1Y2Nlc3M6IGZ1bmN0aW9uKGRhdGEpIHsKICAgICAgICAgICAgICAgICAgICAgICAgdmFyIGVsZW1lbnQgPSBkb2N1bWVudC5nZXRFbGVtZW50QnlJZCgncHJvZHVjdHMnKTsKICAgICAgICAgICAgICAgICAgICAgICAgaWYgKGVsZW1lbnQuY2hvaWNlcykgewogICAgICAgICAgICAgICAgICAgICAgICAgICAgdHJ5IHsKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICBlbGVtZW50LmNob2ljZXMuZGVzdHJveSgpOwogICAgICAgICAgICAgICAgICAgICAgICAgICAgfSBjYXRjaCAoZXJyKSB7CiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgY29uc29sZS5sb2coJ0Nob2ljZXMgZGVzdHJveSBlcnJvciBpZ25vcmVkOicsIGVycik7CiAgICAgICAgICAgICAgICAgICAgICAgICAgICB9CiAgICAgICAgICAgICAgICAgICAgICAgIH0KCiAgICAgICAgICAgICAgICAgICAgICAgIHZhciAkc2VsZWN0ID0gJCgnI3Byb2R1Y3RzJyk7CiAgICAgICAgICAgICAgICAgICAgICAgICRzZWxlY3QuZW1wdHkoKTsKCiAgICAgICAgICAgICAgICAgICAgICAgICQuZWFjaChkYXRhLCBmdW5jdGlvbihrZXksIHZhbHVlKSB7CiAgICAgICAgICAgICAgICAgICAgICAgICAgICB2YXIgbGFiZWwgPSB2YWx1ZS5uYW1lICsgKHZhbHVlLnByaWNlID8gJyAoJyArIHZhbHVlLnByaWNlICsgJyknIDoKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAnJyk7CiAgICAgICAgICAgICAgICAgICAgICAgICAgICAkc2VsZWN0LmFwcGVuZCgnPG9wdGlvbiB2YWx1ZT0iJyArIHZhbHVlLmlkICsgJyI+JyArIGxhYmVsICsKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAnPC9vcHRpb24+Jyk7CiAgICAgICAgICAgICAgICAgICAgICAgIH0pOwoKICAgICAgICAgICAgICAgICAgICAgICAgdmFyIG11bHRpcGxlQ2FuY2VsQnV0dG9uID0gbmV3IENob2ljZXMoIiNwcm9kdWN0cyIsIHsKICAgICAgICAgICAgICAgICAgICAgICAgICAgIHJlbW92ZUl0ZW1CdXR0b246IHRydWUsCiAgICAgICAgICAgICAgICAgICAgICAgIH0pOwogICAgICAgICAgICAgICAgICAgICAgICBlbGVtZW50LmNob2ljZXMgPSBtdWx0aXBsZUNhbmNlbEJ1dHRvbjsKICAgICAgICAgICAgICAgICAgICB9CiAgICAgICAgICAgICAgICB9KTsKICAgICAgICAgICAgfSBlbHNlIHsKICAgICAgICAgICAgICAgIHZhciBlbGVtZW50ID0gZG9jdW1lbnQuZ2V0RWxlbWVudEJ5SWQoJ3Byb2R1Y3RzJyk7CiAgICAgICAgICAgICAgICBpZiAoZWxlbWVudC5jaG9pY2VzKSB7CiAgICAgICAgICAgICAgICAgICAgdHJ5IHsKICAgICAgICAgICAgICAgICAgICAgICAgZWxlbWVudC5jaG9pY2VzLmRlc3Ryb3koKTsKICAgICAgICAgICAgICAgICAgICB9IGNhdGNoIChlcnIpIHsKICAgICAgICAgICAgICAgICAgICAgICAgY29uc29sZS5sb2coJ0Nob2ljZXMgZGVzdHJveSBlcnJvciBpZ25vcmVkOicsIGVycik7CiAgICAgICAgICAgICAgICAgICAgfQogICAgICAgICAgICAgICAgICAgICQoJyNwcm9kdWN0cycpLmVtcHR5KCk7CiAgICAgICAgICAgICAgICAgICAgdmFyIG11bHRpcGxlQ2FuY2VsQnV0dG9uID0gbmV3IENob2ljZXMoIiNwcm9kdWN0cyIsIHsKICAgICAgICAgICAgICAgICAgICAgICAgcmVtb3ZlSXRlbUJ1dHRvbjogdHJ1ZSwKICAgICAgICAgICAgICAgICAgICB9KTsKICAgICAgICAgICAgICAgICAgICBlbGVtZW50LmNob2ljZXMgPSBtdWx0aXBsZUNhbmNlbEJ1dHRvbjsKICAgICAgICAgICAgICAgIH0KICAgICAgICAgICAgfQogICAgICAgIH0pOw==',
    'replace' => 'ICAgICAgICAvLyBTYW1lIGxvYWRlciB0aGUgZWRpdCBzY3JlZW4gdXNlcy4gVGhpcyBwYWdlIG9ubHkgZXZlciByYW4gaXQgZnJvbSB0aGUKICAgICAgICAvLyBEZWFsIGNoYW5nZSBoYW5kbGVyLCBzbyB0aGUgZmllbGQgc3RheWVkIGEgYmFyZSA8c2VsZWN0IG11bHRpcGxlPiB1bnRpbAogICAgICAgIC8vIGEgZGVhbCB3YXMgcGlja2VkIC0tIGFuIGVtcHR5IGJveCB3aXRoIG5vIHBsYWNlaG9sZGVyIC0tIGFuZCBhIGRlYWwgdGhhdAogICAgICAgIC8vIGFycml2ZWQgYWxyZWFkeSBzZWxlY3RlZCAoY29taW5nIGZyb20gYSBkZWFsIG9yIGEgZmFpbGVkIHN1Ym1pdCkgbmV2ZXIKICAgICAgICAvLyBsb2FkZWQgaXRzIHByb2R1Y3RzIGF0IGFsbC4KICAgICAgICBmdW5jdGlvbiBpbml0UHJvZHVjdHNDaG9pY2VzKCkgewogICAgICAgICAgICB2YXIgZWxlbWVudCA9IGRvY3VtZW50LmdldEVsZW1lbnRCeUlkKCdwcm9kdWN0cycpOwogICAgICAgICAgICBpZiAoIWVsZW1lbnQpIHsKICAgICAgICAgICAgICAgIHJldHVybiBudWxsOwogICAgICAgICAgICB9CiAgICAgICAgICAgIGlmIChlbGVtZW50LmNob2ljZXMpIHsKICAgICAgICAgICAgICAgIHRyeSB7CiAgICAgICAgICAgICAgICAgICAgZWxlbWVudC5jaG9pY2VzLmRlc3Ryb3koKTsKICAgICAgICAgICAgICAgIH0gY2F0Y2ggKGVycikgewogICAgICAgICAgICAgICAgICAgIGNvbnNvbGUubG9nKCdDaG9pY2VzIGRlc3Ryb3kgZXJyb3IgaWdub3JlZDonLCBlcnIpOwogICAgICAgICAgICAgICAgfQogICAgICAgICAgICAgICAgZWxlbWVudC5jaG9pY2VzID0gbnVsbDsKICAgICAgICAgICAgfQogICAgICAgICAgICBlbGVtZW50LmNob2ljZXMgPSBuZXcgQ2hvaWNlcygnI3Byb2R1Y3RzJywgewogICAgICAgICAgICAgICAgcmVtb3ZlSXRlbUJ1dHRvbjogdHJ1ZSwKICAgICAgICAgICAgICAgIHBsYWNlaG9sZGVyOiB0cnVlLAogICAgICAgICAgICAgICAgcGxhY2Vob2xkZXJWYWx1ZTogJChlbGVtZW50KS5kYXRhKCdwbGFjZWhvbGRlcicpIHx8ICcnLAogICAgICAgICAgICB9KTsKCiAgICAgICAgICAgIHJldHVybiBlbGVtZW50OwogICAgICAgIH0KCiAgICAgICAgZnVuY3Rpb24gbG9hZFByb2R1Y3RzKGRlYWxfaWQsIHNlbGVjdGVkSWRzKSB7CiAgICAgICAgICAgIHNlbGVjdGVkSWRzID0gc2VsZWN0ZWRJZHMgfHwgW107CgogICAgICAgICAgICBpZiAoIWRlYWxfaWQpIHsKICAgICAgICAgICAgICAgICQoJyNwcm9kdWN0cycpLmVtcHR5KCk7CiAgICAgICAgICAgICAgICBpbml0UHJvZHVjdHNDaG9pY2VzKCk7CiAgICAgICAgICAgICAgICByZXR1cm47CiAgICAgICAgICAgIH0KCiAgICAgICAgICAgICQuYWpheCh7CiAgICAgICAgICAgICAgICB1cmw6ICd7eyByb3V0ZSgnZGVhbC1wdXJjaGFzZS5nZXQtcHJvZHVjdHMnLCAnJykgfX0vJyArIGRlYWxfaWQsCiAgICAgICAgICAgICAgICB0eXBlOiAnR0VUJywKICAgICAgICAgICAgICAgIHN1Y2Nlc3M6IGZ1bmN0aW9uKGRhdGEpIHsKICAgICAgICAgICAgICAgICAgICB2YXIgZWxlbWVudCA9IGRvY3VtZW50LmdldEVsZW1lbnRCeUlkKCdwcm9kdWN0cycpOwogICAgICAgICAgICAgICAgICAgIGlmIChlbGVtZW50ICYmIGVsZW1lbnQuY2hvaWNlcykgewogICAgICAgICAgICAgICAgICAgICAgICB0cnkgewogICAgICAgICAgICAgICAgICAgICAgICAgICAgZWxlbWVudC5jaG9pY2VzLmRlc3Ryb3koKTsKICAgICAgICAgICAgICAgICAgICAgICAgfSBjYXRjaCAoZXJyKSB7CiAgICAgICAgICAgICAgICAgICAgICAgICAgICBjb25zb2xlLmxvZygnQ2hvaWNlcyBkZXN0cm95IGVycm9yIGlnbm9yZWQ6JywgZXJyKTsKICAgICAgICAgICAgICAgICAgICAgICAgfQogICAgICAgICAgICAgICAgICAgICAgICBlbGVtZW50LmNob2ljZXMgPSBudWxsOwogICAgICAgICAgICAgICAgICAgIH0KCiAgICAgICAgICAgICAgICAgICAgdmFyICRzZWxlY3QgPSAkKCcjcHJvZHVjdHMnKTsKICAgICAgICAgICAgICAgICAgICAkc2VsZWN0LmVtcHR5KCk7CgogICAgICAgICAgICAgICAgICAgICQuZWFjaChkYXRhLCBmdW5jdGlvbihrZXksIHZhbHVlKSB7CiAgICAgICAgICAgICAgICAgICAgICAgIHZhciBsYWJlbCA9IHZhbHVlLm5hbWUgKyAodmFsdWUucHJpY2UgPyAnICgnICsgdmFsdWUucHJpY2UgKyAnKScgOiAnJyk7CiAgICAgICAgICAgICAgICAgICAgICAgIC8vIGJ1aWx0IGFzIGEgbm9kZSwgbm90IGEgc3RyaW5nOiBhIHByb2R1Y3QgbmFtZSBob2xkaW5nIGEKICAgICAgICAgICAgICAgICAgICAgICAgLy8gcXVvdGUgb3IgYW4gYW5nbGUgYnJhY2tldCB1c2VkIHRvIGJyZWFrIHRoZSBtYXJrdXAuCiAgICAgICAgICAgICAgICAgICAgICAgICRzZWxlY3QuYXBwZW5kKCQoJzxvcHRpb24+PC9vcHRpb24+JykuYXR0cigndmFsdWUnLCB2YWx1ZS5pZCkudGV4dChsYWJlbCkpOwogICAgICAgICAgICAgICAgICAgIH0pOwoKICAgICAgICAgICAgICAgICAgICBlbGVtZW50ID0gaW5pdFByb2R1Y3RzQ2hvaWNlcygpOwoKICAgICAgICAgICAgICAgICAgICBpZiAoZWxlbWVudCAmJiBlbGVtZW50LmNob2ljZXMgJiYgc2VsZWN0ZWRJZHMubGVuZ3RoID4gMCkgewogICAgICAgICAgICAgICAgICAgICAgICBlbGVtZW50LmNob2ljZXMuc2V0Q2hvaWNlQnlWYWx1ZShzZWxlY3RlZElkcy5tYXAoU3RyaW5nKSk7CiAgICAgICAgICAgICAgICAgICAgfQogICAgICAgICAgICAgICAgfSwKICAgICAgICAgICAgICAgIGVycm9yOiBmdW5jdGlvbigpIHsKICAgICAgICAgICAgICAgICAgICAkKCcjcHJvZHVjdHMnKS5lbXB0eSgpOwogICAgICAgICAgICAgICAgICAgIGluaXRQcm9kdWN0c0Nob2ljZXMoKTsKICAgICAgICAgICAgICAgIH0KICAgICAgICAgICAgfSk7CiAgICAgICAgfQoKICAgICAgICAkKGRvY3VtZW50KS5vbignY2hhbmdlJywgJyNkZWFsX2lkJywgZnVuY3Rpb24oKSB7CiAgICAgICAgICAgIGxvYWRQcm9kdWN0cygkKHRoaXMpLnZhbCgpLCBbXSk7CiAgICAgICAgfSk7CgogICAgICAgICQoZG9jdW1lbnQpLnJlYWR5KGZ1bmN0aW9uKCkgewogICAgICAgICAgICBsb2FkUHJvZHVjdHMoJCgnI2RlYWxfaWQnKS52YWwoKSwgeyEhIGpzb25fZW5jb2RlKG9sZCgncHJvZHVjdHMnLCBbXSkpICEhfSk7CiAgICAgICAgfSk7',
  ),
);
$classes = array (
);
$backupPrefix = 'purchase-products-fix';

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
    $tmp = $full . '.tmp-purchase-products-fix';

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
echo "Then open /deal-purchase/create, pick a deal, and check the Products field.\n";
echo "To undo: php " . basename(__FILE__) . " --rollback\n";
exit(0);
