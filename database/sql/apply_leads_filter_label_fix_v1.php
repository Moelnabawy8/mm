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
 *   php apply_leads_filter_label_fix_v1.php              dry run, writes nothing
 *   php apply_leads_filter_label_fix_v1.php --apply      back up, then apply everything
 *   php apply_leads_filter_label_fix_v1.php --rollback   restore the newest backup
 *
 * Exit codes: 0 fine, 2 an anchor or a preflight check failed (nothing written),
 * 3 the backup could not be taken, 4 a write failed (everything undone),
 * 5 a changed class no longer loads (everything undone).
 */

$BANNER = 'apply_leads_filter_label_fix v1  (the saved filter was lost on every page reload, and the last label could not be removed)';
$edits = array (
  0 => 
  array (
    'path' => 'resources/views/leads/index.blade.php',
    'label' => 'getStages callback',
    'find' => 'ICAgICAgICAgICAgICAgIHN1Y2Nlc3M6IGZ1bmN0aW9uKGRhdGEpIHsKICAgICAgICAgICAgICAgICAgICB2YXIgc3RhZ2VfY250ID0gT2JqZWN0LmtleXMoZGF0YSkubGVuZ3RoOwogICAgICAgICAgICAgICAgICAgICQoIiNmaWx0ZXJfc3RhZ2UiKS5lbXB0eSgpOwogICAgICAgICAgICAgICAgICAgICQoIiNmaWx0ZXJfc3RhZ2UiKS5hcHBlbmQoJzxvcHRpb24gdmFsdWU9IiI+e3sgX18oJ1NlbGVjdCBTdGFnZScpIH19PC9vcHRpb24+Jyk7CiAgICAgICAgICAgICAgICAgICAgaWYgKHN0YWdlX2NudCA+IDApIHsKICAgICAgICAgICAgICAgICAgICAgICAgJC5lYWNoKGRhdGEsIGZ1bmN0aW9uKGtleSwgZGF0YTEpIHsKICAgICAgICAgICAgICAgICAgICAgICAgICAgICQoIiNmaWx0ZXJfc3RhZ2UiKS5hcHBlbmQoJzxvcHRpb24gdmFsdWU9IicgKyBrZXkgKyAnIj4nICsgZGF0YTEgKwogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICc8L29wdGlvbj4nKTsKICAgICAgICAgICAgICAgICAgICAgICAgfSk7CiAgICAgICAgICAgICAgICAgICAgfQogICAgICAgICAgICAgICAgICAgICQoIiNmaWx0ZXJfc3RhZ2UiKS5zZWxlY3QyKHsKICAgICAgICAgICAgICAgICAgICAgICAgcGxhY2Vob2xkZXI6ICJ7eyBfXygnU2VsZWN0IFN0YWdlJykgfX0iCiAgICAgICAgICAgICAgICAgICAgfSk7CiAgICAgICAgICAgICAgICAgICAgaWYgKHR5cGVvZiBjYWxsYmFjayA9PT0gJ2Z1bmN0aW9uJykgY2FsbGJhY2soKTsKICAgICAgICAgICAgICAgIH0KICAgICAgICAgICAgfSkKICAgICAgICB9',
    'replace' => 'ICAgICAgICAgICAgICAgIHN1Y2Nlc3M6IGZ1bmN0aW9uKGRhdGEpIHsKICAgICAgICAgICAgICAgICAgICAvLyBUaGlzIGJsb2NrIHVzZWQgdG8gZW5kIHdpdGggJCgiI2ZpbHRlcl9zdGFnZSIpLnNlbGVjdDIoLi4uKS4KICAgICAgICAgICAgICAgICAgICAvLyBUaGUgdGhlbWUgc2hpcHMgQ2hvaWNlcy5qcywgbm90IHRoZSBzZWxlY3QyIGpRdWVyeSBwbHVnaW4sIHNvCiAgICAgICAgICAgICAgICAgICAgLy8gdGhhdCBjYWxsIHRocmV3ICJzZWxlY3QyIGlzIG5vdCBhIGZ1bmN0aW9uIiBldmVyeSB0aW1lIC0tIGFuZAogICAgICAgICAgICAgICAgICAgIC8vIGl0IHRocmV3IEJFRk9SRSB0aGUgY2FsbGJhY2sgYmVsb3csIHdoaWNoIGlzIHRoZSBsaW5lIHRoYXQKICAgICAgICAgICAgICAgICAgICAvLyByZS1hcHBsaWVzIHRoZSBzYXZlZCBmaWx0ZXIgYWZ0ZXIgYSBwYWdlIHJlbG9hZC4gVGhhdCBpcyB3aHkKICAgICAgICAgICAgICAgICAgICAvLyB0aGUgZmlsdGVyIGFwcGVhcmVkIHRvIGJlIHdpcGVkIHdoZW5ldmVyIGFueXRoaW5nIHJlbG9hZGVkIHRoZQogICAgICAgICAgICAgICAgICAgIC8vIHBhZ2UsIHNhdmluZyBhIGxhYmVsIGJlaW5nIHRoZSB1c3VhbCB0cmlnZ2VyLgogICAgICAgICAgICAgICAgICAgIHRyeSB7CiAgICAgICAgICAgICAgICAgICAgICAgIHZhciAkc3RhZ2UgPSAkKCcjZmlsdGVyX3N0YWdlJykuZW1wdHkoKTsKICAgICAgICAgICAgICAgICAgICAgICAgJHN0YWdlLmFwcGVuZCgkKCc8b3B0aW9uPjwvb3B0aW9uPicpLmF0dHIoJ3ZhbHVlJywgJycpLnRleHQoCiAgICAgICAgICAgICAgICAgICAgICAgICAgICAie3sgX18oJ1NlbGVjdCBTdGFnZScpIH19IikpOwogICAgICAgICAgICAgICAgICAgICAgICAkLmVhY2goZGF0YSwgZnVuY3Rpb24oa2V5LCBuYW1lKSB7CiAgICAgICAgICAgICAgICAgICAgICAgICAgICAkc3RhZ2UuYXBwZW5kKCQoJzxvcHRpb24+PC9vcHRpb24+JykuYXR0cigndmFsdWUnLCBrZXkpLnRleHQobmFtZSkpOwogICAgICAgICAgICAgICAgICAgICAgICB9KTsKICAgICAgICAgICAgICAgICAgICB9IGNhdGNoIChlcnIpIHsKICAgICAgICAgICAgICAgICAgICAgICAgY29uc29sZS5sb2coJ3N0YWdlIGxpc3QgcmVmcmVzaCBmYWlsZWQ6JywgZXJyKTsKICAgICAgICAgICAgICAgICAgICB9CgogICAgICAgICAgICAgICAgICAgIC8vIG91dHNpZGUgdGhlIHRyeTogbm90aGluZyBhYm91dCB0aGUgZHJvcGRvd24gbWF5IHN0b3AgdGhlCiAgICAgICAgICAgICAgICAgICAgLy8gZmlsdGVyIGZyb20gYmVpbmcgcmVzdG9yZWQgYWdhaW4KICAgICAgICAgICAgICAgICAgICBpZiAodHlwZW9mIGNhbGxiYWNrID09PSAnZnVuY3Rpb24nKSBjYWxsYmFjaygpOwogICAgICAgICAgICAgICAgfSwKICAgICAgICAgICAgICAgIGVycm9yOiBmdW5jdGlvbigpIHsKICAgICAgICAgICAgICAgICAgICBpZiAodHlwZW9mIGNhbGxiYWNrID09PSAnZnVuY3Rpb24nKSBjYWxsYmFjaygpOwogICAgICAgICAgICAgICAgfQogICAgICAgICAgICB9KQogICAgICAgIH0=',
  ),
  1 => 
  array (
    'path' => 'resources/views/leads/index.blade.php',
    'label' => 'stage select markup',
    'find' => 'ICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgPGxhYmVsIGZvcj0iZmlsdGVyX3N0YWdlIiBjbGFzcz0iZm9ybS1sYWJlbCI+e3sgX18oJ1N0YWdlJykgfX08L2xhYmVsPgogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICA8c2VsZWN0IGNsYXNzPSJmb3JtLWNvbnRyb2wgc2VsZWN0MiIgaWQ9ImZpbHRlcl9zdGFnZSIgbmFtZT0ic3RhZ2UiPg==',
    'replace' => 'ICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgPGxhYmVsIGZvcj0iZmlsdGVyX3N0YWdlIiBjbGFzcz0iZm9ybS1sYWJlbCI+e3sgX18oJ1N0YWdlJykgfX08L2xhYmVsPgogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICB7ey0tIERlbGliZXJhdGVseSBub3QgLnNlbGVjdDI6IGN1c3RvbS5qcyB3cmFwcyBldmVyeSAuc2VsZWN0MiBpbiBhCiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgQ2hvaWNlcyB3aWRnZXQgYW5kIGtlZXBzIG5vIHJlZmVyZW5jZSB0byBpdCwgc28gdGhlIG9wdGlvbnMgdGhpcwogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIGxpc3QgcmVidWlsZHMgd2hlbmV2ZXIgdGhlIHBpcGVsaW5lIGNoYW5nZXMgY291bGQgbmV2ZXIgcmVhY2ggdGhlCiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgdmlzaWJsZSB3aWRnZXQuIEEgcGxhaW4gc2VsZWN0IGFsd2F5cyBzaG93cyB0aGUgcmlnaHQgc3RhZ2VzLiAtLX19CiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIDxzZWxlY3QgY2xhc3M9ImZvcm0tY29udHJvbCIgaWQ9ImZpbHRlcl9zdGFnZSIgbmFtZT0ic3RhZ2UiPg==',
  ),
  2 => 
  array (
    'path' => 'resources/views/leads/index.blade.php',
    'label' => 'restore race',
    'find' => 'ICAgICAgICAgICAgICAgICAgICAgICAgaWYgKF9zZi5waXBlbGluZSkgewogICAgICAgICAgICAgICAgICAgICAgICAgICAgJCgnI2ZpbHRlcl9waXBlbGluZScpLnZhbChfc2YucGlwZWxpbmUpLnRyaWdnZXIoJ2NoYW5nZScpOwogICAgICAgICAgICAgICAgICAgICAgICAgICAgZ2V0U3RhZ2VzKF9zZi5waXBlbGluZSwgZnVuY3Rpb24oKSB7',
    'replace' => 'ICAgICAgICAgICAgICAgICAgICAgICAgaWYgKF9zZi5waXBlbGluZSkgewogICAgICAgICAgICAgICAgICAgICAgICAgICAgLy8gTm8gLnRyaWdnZXIoJ2NoYW5nZScpIGhlcmU6IHRoZSBjaGFuZ2UgaGFuZGxlciBmaXJlcyBpdHMKICAgICAgICAgICAgICAgICAgICAgICAgICAgIC8vIG93biBnZXRTdGFnZXMoKSB3aXRoIG5vIGNhbGxiYWNrLCBhbmQgdGhhdCBzZWNvbmQgcmVxdWVzdAogICAgICAgICAgICAgICAgICAgICAgICAgICAgLy8gcmFjZWQgdGhpcyBvbmUgYW5kIGVtcHRpZWQgdGhlIGxpc3QgYWdhaW4gYWZ0ZXIgdGhlIHNhdmVkCiAgICAgICAgICAgICAgICAgICAgICAgICAgICAvLyBzdGFnZSBoYWQgYmVlbiBwdXQgYmFjay4gV2UgY2FsbCBnZXRTdGFnZXMgb3Vyc2VsdmVzIGJlbG93LgogICAgICAgICAgICAgICAgICAgICAgICAgICAgJCgnI2ZpbHRlcl9waXBlbGluZScpLnZhbChfc2YucGlwZWxpbmUpOwogICAgICAgICAgICAgICAgICAgICAgICAgICAgZ2V0U3RhZ2VzKF9zZi5waXBlbGluZSwgZnVuY3Rpb24oKSB7',
  ),
  3 => 
  array (
    'path' => 'app/Http/Controllers/LeadController.php',
    'label' => 'label validation',
    'find' => 'ICAgICAgICAkdmFsaWRhdG9yID0gXFZhbGlkYXRvcjo6bWFrZSgKICAgICAgICAgICAgJHJlcXVlc3QtPmFsbCgpLAogICAgICAgICAgICBbCiAgICAgICAgICAgICAgICAnbGFiZWxzJyA9PiAncmVxdWlyZWQnLAogICAgICAgICAgICBdCiAgICAgICAgKTsKCiAgICAgICAgaWYgKCR2YWxpZGF0b3ItPmZhaWxzKCkpIHsKICAgICAgICAgICAgJG1lc3NhZ2VzID0gJHZhbGlkYXRvci0+Z2V0TWVzc2FnZUJhZygpOwogICAgICAgICAgICByZXR1cm4gcmVkaXJlY3QoKS0+YmFjaygpLT53aXRoKCdlcnJvcicsICRtZXNzYWdlcy0+Zmlyc3QoKSk7CiAgICAgICAgfQoKICAgICAgICBpZiAoXEF1dGg6OnVzZXIoKS0+Y2FuKCdlZGl0IGxlYWQnKSkgewogICAgICAgICAgICAkbGVhZHMgPSAkdGhpcy0+b3duZWRMZWFkKCRpZCk7CiAgICAgICAgICAgIGlmICgkbGVhZHMgJiYgJGxlYWRzLT5jcmVhdGVkX2J5ID09IFxBdXRoOjp1c2VyKCktPmNyZWF0b3JJZCgpKSB7CiAgICAgICAgICAgICAgICBpZiAoJHJlcXVlc3QtPmxhYmVscykgewogICAgICAgICAgICAgICAgICAgICRsZWFkcy0+bGFiZWxzID0gaW1wbG9kZSgnLCcsICRyZXF1ZXN0LT5sYWJlbHMpOwogICAgICAgICAgICAgICAgfSBlbHNlIHsKICAgICAgICAgICAgICAgICAgICAkbGVhZHMtPmxhYmVscyA9ICRyZXF1ZXN0LT5sYWJlbHM7CiAgICAgICAgICAgICAgICB9CiAgICAgICAgICAgICAgICAkbGVhZHMtPnNhdmUoKTs=',
    'replace' => 'ICAgICAgICAkdmFsaWRhdG9yID0gXFZhbGlkYXRvcjo6bWFrZSgKICAgICAgICAgICAgJHJlcXVlc3QtPmFsbCgpLAogICAgICAgICAgICBbCiAgICAgICAgICAgICAgICAvLyB3YXMgJ3JlcXVpcmVkJywgd2hpY2ggbWFkZSBpdCBpbXBvc3NpYmxlIHRvIHRha2UgdGhlIGxhc3QgbGFiZWwKICAgICAgICAgICAgICAgIC8vIG9mZiBhIGxlYWQ6IHVudGlja2luZyBldmVyeSBib3ggc2VuZHMgbm8gbGFiZWxzW10gYXQgYWxsLCBzbyB0aGUKICAgICAgICAgICAgICAgIC8vIGZvcm0gY2FtZSBiYWNrIHdpdGggImxhYmVscyBmaWVsZCBpcyByZXF1aXJlZCIgYW5kIHNhdmVkIG5vdGhpbmcuCiAgICAgICAgICAgICAgICAnbGFiZWxzJyA9PiAnbnVsbGFibGV8YXJyYXknLAogICAgICAgICAgICAgICAgJ2xhYmVscy4qJyA9PiBbCiAgICAgICAgICAgICAgICAgICAgJ2ludGVnZXInLAogICAgICAgICAgICAgICAgICAgIFxJbGx1bWluYXRlXFZhbGlkYXRpb25cUnVsZTo6ZXhpc3RzKCdsYWJlbHMnLCAnaWQnKQogICAgICAgICAgICAgICAgICAgICAgICAtPndoZXJlKCdjcmVhdGVkX2J5JywgXEF1dGg6OnVzZXIoKS0+Y3JlYXRvcklkKCkpLAogICAgICAgICAgICAgICAgXSwKICAgICAgICAgICAgXQogICAgICAgICk7CgogICAgICAgIGlmICgkdmFsaWRhdG9yLT5mYWlscygpKSB7CiAgICAgICAgICAgICRtZXNzYWdlcyA9ICR2YWxpZGF0b3ItPmdldE1lc3NhZ2VCYWcoKTsKICAgICAgICAgICAgcmV0dXJuIHJlZGlyZWN0KCktPmJhY2soKS0+d2l0aCgnZXJyb3InLCAkbWVzc2FnZXMtPmZpcnN0KCkpOwogICAgICAgIH0KCiAgICAgICAgaWYgKFxBdXRoOjp1c2VyKCktPmNhbignZWRpdCBsZWFkJykpIHsKICAgICAgICAgICAgJGxlYWRzID0gJHRoaXMtPm93bmVkTGVhZCgkaWQpOwogICAgICAgICAgICBpZiAoJGxlYWRzICYmICRsZWFkcy0+Y3JlYXRlZF9ieSA9PSBcQXV0aDo6dXNlcigpLT5jcmVhdG9ySWQoKSkgewogICAgICAgICAgICAgICAgJGxlYWRzLT5sYWJlbHMgPSAkcmVxdWVzdC0+bGFiZWxzID8gaW1wbG9kZSgnLCcsICRyZXF1ZXN0LT5sYWJlbHMpIDogJyc7CiAgICAgICAgICAgICAgICAkbGVhZHMtPnNhdmUoKTs=',
  ),
);
$classes = array (
  0 => 'App\\Http\\Controllers\\LeadController',
);
$backupPrefix = 'leads-filter-fix';

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
    $tmp = $full . '.tmp-leads-filter-fix';

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
echo "Then filter /leads, save a label on a card, and check the filter is still on.\n";
echo "To undo: php " . basename(__FILE__) . " --rollback\n";
exit(0);
