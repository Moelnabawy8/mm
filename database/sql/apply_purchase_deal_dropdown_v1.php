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
 *   php apply_purchase_deal_dropdown_v1.php              dry run, writes nothing
 *   php apply_purchase_deal_dropdown_v1.php --apply      back up, then apply everything
 *   php apply_purchase_deal_dropdown_v1.php --rollback   restore the newest backup
 *
 * Exit codes: 0 fine, 2 an anchor or a preflight check failed (nothing written),
 * 3 the backup could not be taken, 4 a write failed (everything undone),
 * 5 a changed class no longer loads (everything undone).
 */

$BANNER = 'apply_purchase_deal_dropdown v1  (the Deal field is a dropdown when the form is opened from a lead)';
$edits = array (
  0 => 
  array (
    'path' => 'app/Http/Controllers/DealPurchaseController.php',
    'label' => 'dropdown limit',
    'find' => 'Y2xhc3MgRGVhbFB1cmNoYXNlQ29udHJvbGxlciBleHRlbmRzIENvbnRyb2xsZXIKew==',
    'replace' => 'Y2xhc3MgRGVhbFB1cmNoYXNlQ29udHJvbGxlciBleHRlbmRzIENvbnRyb2xsZXIKewogICAgLyoqCiAgICAgKiBBYm92ZSB0aGlzIG1hbnkgY2FuZGlkYXRlcyB0aGUgRGVhbCBmaWVsZCBnb2VzIGJhY2sgdG8gdGhlIHNlYXJjaGVkCiAgICAgKiBwaWNrZXIuIEEgbGVhZCBub3JtYWxseSBoYXMgb25lIG9yIHR3byBkZWFsczsgdGhpcyBpcyBhIGd1YXJkIGFnYWluc3QgdGhlCiAgICAgKiBwYXRob2xvZ2ljYWwgY2FzZSwgbm90IGFuIGV4cGVjdGF0aW9uLgogICAgICovCiAgICBjb25zdCBEUk9QRE9XTl9MSU1JVCA9IDUwOwo=',
  ),
  1 => 
  array (
    'path' => 'app/Http/Controllers/DealPurchaseController.php',
    'label' => 'load the lead deals',
    'find' => 'ICAgICAgICAgICAgJGRlYWxzUXVlcnkgPSAkdGhpcy0+c2VsZWN0YWJsZURlYWxzUXVlcnkoJGxlYWRJZCk7CiAgICAgICAgICAgICRkZWFsc0NvdW50ID0gJGRlYWxzUXVlcnktPmNvdW50KCk7CgogICAgICAgICAgICAvLyBQcmUtc2VsZWN0IHdoZW4gdGhlcmUgaXMgZXhhY3RseSBvbmUgY2FuZGlkYXRlICh0aGUgbGVhZF9pZCBmbG93KS4KICAgICAgICAgICAgJHNlbGVjdGVkRGVhbCA9ICRkZWFsc0NvdW50ID09PSAxID8gJGRlYWxzUXVlcnktPmZpcnN0KCkgOiBudWxsOw==',
    'replace' => 'ICAgICAgICAgICAgJGRlYWxzUXVlcnkgPSAkdGhpcy0+c2VsZWN0YWJsZURlYWxzUXVlcnkoJGxlYWRJZCk7CiAgICAgICAgICAgICRkZWFsc0NvdW50ID0gJGRlYWxzUXVlcnktPmNvdW50KCk7CgogICAgICAgICAgICAvLyBPcGVuZWQgZnJvbSBhIGxlYWQgdGhlIGNhbmRpZGF0ZXMgYXJlIGZldywgc28gdGhlIGZvcm0gb2ZmZXJzIGEKICAgICAgICAgICAgLy8gcGxhaW4gZHJvcGRvd24uIE51bGwgbWVhbnMgIm5vIGxlYWQsIG9yIHRvbyBtYW55IiBhbmQgdGhlIHZpZXcKICAgICAgICAgICAgLy8gZmFsbHMgYmFjayB0byB0aGUgc2VhcmNoZWQgcGlja2VyLgogICAgICAgICAgICAvLwogICAgICAgICAgICAvLyBGZXRjaGVkIGJlZm9yZSAkc2VsZWN0ZWREZWFsIG9uIHB1cnBvc2U6IGZpcnN0KCkgcHV0cyBhIGxpbWl0IG9uCiAgICAgICAgICAgIC8vIHRoZSBidWlsZGVyLCBhbmQgYSBnZXQoKSBhZnRlciBpdCB3b3VsZCBjb21lIGJhY2sgc2hvcnQuCiAgICAgICAgICAgICRsZWFkRGVhbHMgPSAkbGVhZElkICYmICRkZWFsc0NvdW50ID4gMCAmJiAkZGVhbHNDb3VudCA8PSBzZWxmOjpEUk9QRE9XTl9MSU1JVAogICAgICAgICAgICAgICAgPyAkZGVhbHNRdWVyeS0+b3JkZXJCeSgnbmFtZScpLT5nZXQoWydpZCcsICduYW1lJ10pCiAgICAgICAgICAgICAgICA6IG51bGw7CgogICAgICAgICAgICAvLyBQcmUtc2VsZWN0IHdoZW4gdGhlcmUgaXMgZXhhY3RseSBvbmUgY2FuZGlkYXRlICh0aGUgbGVhZF9pZCBmbG93KS4KICAgICAgICAgICAgJHNlbGVjdGVkRGVhbCA9ICRkZWFsc0NvdW50ID09PSAxCiAgICAgICAgICAgICAgICA/ICgkbGVhZERlYWxzID8gJGxlYWREZWFscy0+Zmlyc3QoKSA6ICRkZWFsc1F1ZXJ5LT5maXJzdCgpKQogICAgICAgICAgICAgICAgOiBudWxsOw==',
  ),
  2 => 
  array (
    'path' => 'app/Http/Controllers/DealPurchaseController.php',
    'label' => 'pass them to the view',
    'find' => 'ICAgICAgICAgICAgcmV0dXJuIHZpZXcoJ2FkbWluLmRlYWxfcHVyY2hhc2UuY3JlYXRlJywgY29tcGFjdCgnZGVhbHNDb3VudCcsICdzZWxlY3RlZERlYWwnLCAncGF5bWVudE1ldGhvZHMnLCAnY3VycmVuY2llcycsICdsZWFkSWQnKSk7',
    'replace' => 'ICAgICAgICAgICAgcmV0dXJuIHZpZXcoJ2FkbWluLmRlYWxfcHVyY2hhc2UuY3JlYXRlJywgY29tcGFjdCgnZGVhbHNDb3VudCcsICdzZWxlY3RlZERlYWwnLCAnbGVhZERlYWxzJywgJ3BheW1lbnRNZXRob2RzJywgJ2N1cnJlbmNpZXMnLCAnbGVhZElkJykpOw==',
  ),
  3 => 
  array (
    'path' => 'resources/views/admin/deal_purchase/create.blade.php',
    'label' => 'deal dropdown',
    'find' => 'ICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICB7eyBGb3JtOjpsYWJlbCgnZGVhbF9pZF9zZWFyY2gnLCBfXygnRGVhbCcpLCBbJ2NsYXNzJyA9PiAnZm9ybS1sYWJlbCddKSB9fTx4LXJlcXVpcmVkPjwveC1yZXF1aXJlZD4KICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICBAaW5jbHVkZSgnc2FsZXNfbWVldGluZ3MuX3NlYXJjaF9waWNrZXInLCBbCiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICdwaWNrZXJJZCcgPT4gJ2RlYWxfaWQnLAogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAnZmllbGROYW1lJyA9PiAnZGVhbF9pZCcsCiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICd1cmwnID0+IHJvdXRlKAogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgJ2RlYWwtcHVyY2hhc2UuZGVhbHMuc2VhcmNoJywKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIGFycmF5X2ZpbHRlcihbJ21vZGUnID0+ICdmcmVlJywgJ2xlYWRfaWQnID0+ICRsZWFkSWRdKSwKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgKSwKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgJ3NlbGVjdGVkSWQnID0+IG9sZCgnZGVhbF9pZCcsICRzZWxlY3RlZERlYWwtPmlkID8/IG51bGwpLAogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAnc2VsZWN0ZWROYW1lJyA9PiAkc2VsZWN0ZWREZWFsLT5uYW1lID8/ICcnLAogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAncGxhY2Vob2xkZXInID0+IF9fKCdTZWFyY2ggRGVhbCcpLAogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAncmVxdWlyZWQnID0+IHRydWUsCiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgXSk=',
    'replace' => 'ICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICBAaWYgKCFpc19udWxsKCRsZWFkRGVhbHMpKQogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICB7ey0tIE9wZW5lZCBmcm9tIGEgbGVhZDogdGhlIGNhbmRpZGF0ZXMgYXJlIGZldyBhbmQgYWxyZWFkeQogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIGtub3duLCBzbyB0aGV5IGFyZSBzaW1wbHkgbGlzdGVkLiBEZWxpYmVyYXRlbHkgbm90CiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgLnNlbGVjdDIgLS0gY3VzdG9tLmpzIHdvdWxkIHdyYXAgaXQgaW4gYSBDaG9pY2VzIHdpZGdldAogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIGFuZCB0aGUgY2hhbmdlIGhhbmRsZXIgYmVsb3cgcmVhZHMgdGhpcyBlbGVtZW50LiAtLX19CiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIHt7IEZvcm06OmxhYmVsKCdkZWFsX2lkJywgX18oJ0RlYWwnKSwgWydjbGFzcycgPT4gJ2Zvcm0tbGFiZWwnXSkgfX08eC1yZXF1aXJlZD48L3gtcmVxdWlyZWQ+CiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIDxzZWxlY3QgY2xhc3M9ImZvcm0tY29udHJvbCIgaWQ9ImRlYWxfaWQiIG5hbWU9ImRlYWxfaWQiIHJlcXVpcmVkPgogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgQGlmICgkbGVhZERlYWxzLT5jb3VudCgpID4gMSkKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICA8b3B0aW9uIHZhbHVlPSIiPnt7IF9fKCdTZWxlY3QgRGVhbCcpIH19PC9vcHRpb24+CiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICBAZW5kaWYKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIEBmb3JlYWNoICgkbGVhZERlYWxzIGFzICRsZWFkRGVhbCkKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICA8b3B0aW9uIHZhbHVlPSJ7eyAkbGVhZERlYWwtPmlkIH19IgogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICBAc2VsZWN0ZWQoKHN0cmluZykgb2xkKCdkZWFsX2lkJywgJHNlbGVjdGVkRGVhbC0+aWQgPz8gJycpID09PSAoc3RyaW5nKSAkbGVhZERlYWwtPmlkKT4KICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAge3sgJGxlYWREZWFsLT5uYW1lIH19CiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgPC9vcHRpb24+CiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICBAZW5kZm9yZWFjaAogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICA8L3NlbGVjdD4KICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICBAZWxzZQogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICB7eyBGb3JtOjpsYWJlbCgnZGVhbF9pZF9zZWFyY2gnLCBfXygnRGVhbCcpLCBbJ2NsYXNzJyA9PiAnZm9ybS1sYWJlbCddKSB9fTx4LXJlcXVpcmVkPjwveC1yZXF1aXJlZD4KICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgQGluY2x1ZGUoJ3NhbGVzX21lZXRpbmdzLl9zZWFyY2hfcGlja2VyJywgWwogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgJ3BpY2tlcklkJyA9PiAnZGVhbF9pZCcsCiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAnZmllbGROYW1lJyA9PiAnZGVhbF9pZCcsCiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAndXJsJyA9PiByb3V0ZSgKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAnZGVhbC1wdXJjaGFzZS5kZWFscy5zZWFyY2gnLAogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIGFycmF5X2ZpbHRlcihbJ21vZGUnID0+ICdmcmVlJywgJ2xlYWRfaWQnID0+ICRsZWFkSWRdKSwKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICksCiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAnc2VsZWN0ZWRJZCcgPT4gb2xkKCdkZWFsX2lkJywgJHNlbGVjdGVkRGVhbC0+aWQgPz8gbnVsbCksCiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAnc2VsZWN0ZWROYW1lJyA9PiAkc2VsZWN0ZWREZWFsLT5uYW1lID8/ICcnLAogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgJ3BsYWNlaG9sZGVyJyA9PiBfXygnU2VhcmNoIERlYWwnKSwKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICdyZXF1aXJlZCcgPT4gdHJ1ZSwKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgXSkKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICBAZW5kaWY=',
  ),
);
$classes = array (
  0 => 'App\\Http\\Controllers\\DealPurchaseController',
);
$backupPrefix = 'purchase-dropdown';

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
    $tmp = $full . '.tmp-purchase-dropdown';

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
echo "Then open deal-purchase/create?lead_id=<id>: the Deal field must be a dropdown.\n";
echo "To undo: php " . basename(__FILE__) . " --rollback\n";
exit(0);
