<?php
/**
 * CRM Reports v1 — deploy script.
 *
 * Ships the CRM Reports (Employee page + Data/Financial placeholders), call logging on
 * leads, meeting status, and the hooks that record who did what.
 *
 * Shared files are never replaced whole: each change is an exact find -> replace pair
 * and nothing is written unless every required anchor matches exactly once. New files
 * are created only where nothing exists yet (or the same content is already there).
 * Translations are appended to resources/lang/ar.json as text, never re-encoded.
 *
 *   php apply_crm_reports_v1.php              dry run, writes nothing
 *   php apply_crm_reports_v1.php --apply      back up, write everything, run the 3 migrations
 *   php apply_crm_reports_v1.php --rollback   restore the newest backup, delete the new files
 *
 * Exit codes: 0 fine, 2 a preflight check failed (nothing written), 3 the backup could
 * not be taken, 4 a write failed (everything undone), 5 a new class does not load
 * (everything undone), 6 files written but a migration failed (see output).
 */

$BANNER = 'apply_crm_reports v1  (CRM Reports: Employee page, call logging, meeting status)';
$edits = __EDITS__;
$newFiles = __NEWFILES__;
$translations = __TRANSLATIONS__;
$conflicts = __CONFLICTS__;
$classes = __CLASSES__;
$migrations = __MIGRATIONS__;
$backupPrefix = 'crm-reports-v1';

echo $BANNER . "\n\n";

$root = __DIR__;
while (!file_exists($root . '/artisan') && dirname($root) !== $root) {
    $root = dirname($root);
}
if (!file_exists($root . '/artisan')) {
    fwrite(STDERR, "Could not locate the Laravel root (no artisan found).\n");
    exit(2);
}
$root = rtrim(str_replace('\\', '/', $root), '/');

$apply = in_array('--apply', $argv, true);
$rollback = in_array('--rollback', $argv, true);
$noMigrate = in_array('--no-migrate', $argv, true);
$simulateFail = null;
foreach ($argv as $a) {
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

function nearestExisting($path)
{
    $p = $path;
    while ($p && !file_exists($p) && dirname($p) !== $p) {
        $p = dirname($p);
    }
    return $p;
}

/** Why $rel cannot be created, or null. Missing parent directories must be creatable. */
function createProblem($root, $rel)
{
    $full = $root . '/' . $rel;
    $near = nearestExisting(dirname($full));
    if (is_file($near)) {
        return 'blocked by a file at ' . str_replace($root . '/', '', $near);
    }
    if (!is_writable($near)) {
        return 'the directory ' . str_replace($root . '/', '', $near) . ' is not writable by this user';
    }
    return null;
}

function writeProblem($root, $rel)
{
    $full = $root . '/' . $rel;
    if (!is_file($full)) {
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

/** The key of one `"key": value` line in a JSON file, honouring backslash escapes. */
function jsonLineKey($line)
{
    $line = ltrim($line);
    if ($line === '' || $line[0] !== '"') {
        return null;
    }
    $out = '';
    for ($i = 1, $n = strlen($line); $i < $n; $i++) {
        $c = $line[$i];
        if ($c === '\\' && $i + 1 < $n) {
            $out .= $c . $line[++$i];
            continue;
        }
        if ($c === '"') {
            return json_decode('"' . $out . '"');
        }
        $out .= $c;
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

    $kept = 0;
    $done = 0;
    foreach (@file($dir . '/MANIFEST.txt', FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $p = preg_split('/\s+/', trim($line));
        if (count($p) < 3) {
            continue;
        }
        [$kind, $rel] = [$p[0], $p[1]];
        $full = $root . '/' . $rel;
        if ($kind === 'NEW') {
            if (!is_file($full)) {
                continue;
            }
            if (h16(file_get_contents($full)) !== $p[2]) {
                echo "[KEEP] $rel -- changed since the patch created it, leaving it alone\n";
                $kept++;
                continue;
            }
            @unlink($full);
            echo "[ OK ] $rel removed\n";
            $done++;
            continue;
        }
        if ($kind === 'FILE') {
            $saved = $dir . '/' . $rel;
            if (!is_file($saved)) {
                echo "[SKIP] $rel -- not in the backup\n";
                continue;
            }
            if (is_file($full) && isset($p[3]) && h16(file_get_contents($full)) !== $p[3]) {
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
            $done++;
        }
    }

    // Directories the patch created (e.g. resources/views/crm_reports) go too, once empty.
    $dirsToCheck = [];
    foreach (@file($dir . '/MANIFEST.txt', FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $p = preg_split('/\s+/', trim($line));
        if (($p[0] ?? '') === 'NEW') {
            for ($d = dirname($root . '/' . $p[1]); strlen($d) > strlen($root); $d = dirname($d)) {
                $dirsToCheck[$d] = substr_count($d, '/');
            }
        }
    }
    arsort($dirsToCheck);
    foreach (array_keys($dirsToCheck) as $d) {
        if (is_dir($d) && count(scandir($d)) === 2) {
            @rmdir($d);
        }
    }

    echo "\nRolled back $done file(s)" . ($kept ? ", kept $kept" : '') . ".\n";
    echo "The database columns, indexes and permissions are left in place (they are harmless on their own).\n";
    echo "Next: php artisan view:clear && php artisan optimize:clear\n";
    exit($kept ? 2 : 0);
}

// ---- plan --------------------------------------------------------------------------

$blocked = 0;
$plan = [];      // rel => new content (edited shared files + ar.json)
$before = [];    // rel => original content
$create = [];    // rel => content (new files)

// 1. New files.
foreach ($newFiles as $rel => $b64) {
    $content = base64_decode($b64);
    $full = $root . '/' . $rel;
    if (is_file($full)) {
        if (h16(file_get_contents($full)) === h16($content)) {
            echo "[SKIP] $rel -- already there\n";
            continue;
        }
        echo "[FAIL] $rel -- a different file already exists here; it will not be overwritten\n";
        $blocked++;
        continue;
    }
    $problem = createProblem($root, $rel);
    if ($problem !== null) {
        echo "[FAIL] $rel -- $problem\n";
        $blocked++;
        continue;
    }
    $create[$rel] = $content;
}
echo '       ' . count($create) . " new file(s) to create\n";

// 2. Anchored edits.
$byPath = [];
foreach ($edits as $e) {
    $byPath[$e['path']][] = $e;
}
foreach ($byPath as $rel => $list) {
    $problem = writeProblem($root, $rel);
    if ($problem !== null) {
        echo "[FAIL] $rel -- $problem\n";
        $blocked++;
        continue;
    }
    $raw = file_get_contents($root . '/' . $rel);
    $content = lf($raw);
    $before[$rel] = $raw;
    $changed = 0;
    $already = 0;
    $skipped = 0;
    $bad = false;

    foreach ($list as $e) {
        $find = lf(base64_decode($e['find']));
        $repl = lf(base64_decode($e['replace']));
        $nFind = substr_count($content, $find);
        $nRepl = substr_count($content, $repl);

        if ($nRepl >= 1) {
            $already++;
            continue;
        }
        if ($nFind === 1) {
            $content = str_replace($find, $repl, $content);
            $changed++;
            continue;
        }
        if (!empty($e['optional'])) {
            echo "       note: $rel -- optional change \"{$e['label']}\" skipped (anchor found $nFind times)\n";
            $skipped++;
            continue;
        }
        echo '[FAIL] ' . $rel . ' -- anchor "' . $e['label'] . '" found ' . $nFind . " times, expected exactly 1\n";
        $bad = true;
    }

    // A route name or similar that must not already exist from some other change.
    foreach ($conflicts as $c) {
        if ($c['path'] === $rel && strpos(lf($raw), $c['needle']) !== false && $already === 0) {
            echo "[FAIL] $rel -- already contains \"{$c['needle']}\" from something else; refusing to add a second one\n";
            $bad = true;
        }
    }

    if ($bad) {
        $blocked++;
        continue;
    }
    if ($changed === 0) {
        echo "[SKIP] $rel -- already applied ($already change(s))\n";
        continue;
    }
    if (strpos($raw, "\r\n") !== false) {
        echo "       note: $rel had CRLF line endings; it will be written as LF\n";
    }
    echo "[READY] $rel -- $changed change(s)" . ($already ? ", $already already applied" : '') . ($skipped ? ", $skipped optional skipped" : '') . "\n";
    $plan[$rel] = $content;
}

// 3. Arabic translations: append the keys the file does not have yet.
$langRel = 'resources/lang/ar.json';
$problem = writeProblem($root, $langRel);
if ($problem !== null) {
    echo "[FAIL] $langRel -- $problem\n";
    $blocked++;
} else {
    $raw = file_get_contents($root . '/' . $langRel);
    if (json_decode($raw, true) === null) {
        echo "[FAIL] $langRel -- is not valid JSON now; not touching it\n";
        $blocked++;
    } else {
        $have = [];
        foreach (explode("\n", lf($raw)) as $line) {
            $k = jsonLineKey($line);
            if ($k !== null) {
                $have[$k] = true;
            }
        }
        $lines = [];
        foreach ($translations as $k => $v) {
            if (!isset($have[$k])) {
                $lines[] = '    ' . json_encode($k, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ': '
                    . json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }
        if (!$lines) {
            echo "[SKIP] $langRel -- translations already there\n";
        } else {
            $src = lf($raw);
            $end = strrpos($src, '}');
            $body = rtrim(substr($src, 0, $end));
            if (substr($body, -1) !== ',' && substr($body, -1) !== '{') {
                $body .= ',';
            }
            $new = $body . "\n" . implode(",\n", $lines) . "\n}\n";
            if (json_decode($new, true) === null) {
                echo "[FAIL] $langRel -- the result would not be valid JSON; not touching it\n";
                $blocked++;
            } else {
                echo "[READY] $langRel -- " . count($lines) . " translation(s) to append\n";
                $before[$langRel] = $raw;
                $plan[$langRel] = $new;
            }
        }
    }
}

echo "\n--------------------------------------------------\n";
echo 'new files: ' . count($create) . '   changed files: ' . count($plan) . '   blocked: ' . $blocked . "\n";

if ($blocked) {
    echo "\nNOTHING WAS WRITTEN. Something on the server is not what this patch was built\n";
    echo "against. Send the output (and the file it names) back so the change can be re-made.\n";
    exit(2);
}
if (!$plan && !$create) {
    echo "\nAll files are already in place.\n";
}
if (!$apply) {
    echo "\nDry run only. Re-run with --apply to write these files and run the migrations.\n";
    exit(0);
}

// ---- backup -------------------------------------------------------------------------

$stamp = date('Ymd-His');
$dir = $root . '/storage/backups/' . $backupPrefix . '-' . $stamp;
$backupsRoot = $root . '/storage/backups';
if (!is_dir($backupsRoot) && !@mkdir($backupsRoot, 0775, true)) {
    fwrite(STDERR, "Could not create the backup location $backupsRoot.\n");
    exit(3);
}
if (!is_writable($backupsRoot) || !@mkdir($dir, 0775, true)) {
    fwrite(STDERR, "Could not create $dir.\n");
    exit(3);
}
echo "Backing up to $dir\n";
$manifest = [];
foreach ($plan as $rel => $new) {
    $target = $dir . '/' . $rel;
    if (!is_dir(dirname($target)) && !@mkdir(dirname($target), 0775, true)) {
        fwrite(STDERR, "Could not create " . dirname($target) . ".\n");
        exit(3);
    }
    if (@file_put_contents($target, $before[$rel]) === false || h16(file_get_contents($target)) !== h16($before[$rel])) {
        fwrite(STDERR, "Could not back up $rel.\n");
        exit(3);
    }
    echo "       saved $rel\n";
    $manifest[] = 'FILE ' . $rel . ' ' . h16($before[$rel]) . ' ' . h16($new);
}
foreach ($create as $rel => $content) {
    $manifest[] = 'NEW ' . $rel . ' ' . h16($content);
}
file_put_contents($dir . '/MANIFEST.txt', implode("\n", $manifest) . "\n");
file_put_contents($dir . '/.htaccess', "Require all denied\n");
echo "       backup verified\n\n";

// ---- write --------------------------------------------------------------------------

$written = [];
$created = [];
$createdDirs = [];

function undo($root, $before, $written, $created, $createdDirs)
{
    foreach ($written as $rel) {
        @file_put_contents($root . '/' . $rel, $before[$rel]);
    }
    foreach ($created as $rel) {
        @unlink($root . '/' . $rel);
    }
    foreach (array_reverse($createdDirs) as $d) {
        @rmdir($d);
    }
    echo "\nEverything written so far was put back. The site is back exactly as it was.\n";
}

function putFile($full, $content, $tmpSuffix)
{
    $tmp = $full . $tmpSuffix;
    if (@file_put_contents($tmp, $content) === false || h16(file_get_contents($tmp)) !== h16($content)) {
        @unlink($tmp);
        return false;
    }
    if (!@rename($tmp, $full)) {
        @unlink($tmp);
        return false;
    }
    return h16(file_get_contents($full)) === h16($content);
}

// New files first: nothing references them until the shared files are edited.
foreach ($create as $rel => $content) {
    $full = $root . '/' . $rel;
    $d = dirname($full);
    $missing = [];
    while (!is_dir($d)) {
        $missing[] = $d;
        $d = dirname($d);
    }
    foreach (array_reverse($missing) as $m) {
        if (!@mkdir($m, 0775)) {
            fwrite(STDERR, "[FAIL] could not create directory " . str_replace($root . '/', '', $m) . "\n");
            undo($root, $before, $written, $created, $createdDirs);
            exit(4);
        }
        $createdDirs[] = $m;
    }
    if ($simulateFail === $rel || !putFile($full, $content, '.tmp-crm-reports')) {
        fwrite(STDERR, "[FAIL] $rel -- could not write\n");
        undo($root, $before, $written, $created, $createdDirs);
        exit(4);
    }
    echo "[ NEW] $rel\n";
    $created[] = $rel;
}

foreach ($plan as $rel => $new) {
    if ($simulateFail === $rel || !putFile($root . '/' . $rel, $new, '.tmp-crm-reports')) {
        fwrite(STDERR, "[FAIL] $rel -- could not write\n");
        undo($root, $before, $written, $created, $createdDirs);
        exit(4);
    }
    echo "[ OK ] $rel\n";
    $written[] = $rel;
}

// ---- every new class must load, and the app must still boot -------------------------

$code = "require " . var_export($root . '/vendor/autoload.php', true) . ";\n";
foreach ($classes as $c) {
    $code .= "if (!class_exists(" . var_export($c, true) . ")) { fwrite(STDERR, 'cannot load " . $c . "'); exit(1); }\n";
}
$code .= "\$app = require " . var_export($root . '/bootstrap/app.php', true) . ";\n";
$code .= "\$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();\n";
$code .= "foreach (['crm-reports.employee', 'leads.calls.store'] as \$r) { if (!\\Illuminate\\Support\\Facades\\Route::has(\$r)) { fwrite(STDERR, 'route missing: ' . \$r); exit(1); } }\n";
$code .= "exit(0);\n";
$probe = $root . '/storage/crm-reports-probe.php';
file_put_contents($probe, "<?php\n" . $code);
$out = [];
$rc = 0;
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($probe) . ' 2>&1', $out, $rc);
@unlink($probe);
if ($rc !== 0) {
    fwrite(STDERR, "\n[FAIL] " . implode("\n", $out) . "\n");
    undo($root, $before, $written, $created, $createdDirs);
    echo "If this keeps happening, run: composer dump-autoload\n";
    exit(5);
}
echo "\n[ OK ] new classes load, the app boots, and the new routes are registered\n";

// ---- migrations ---------------------------------------------------------------------

$migrateFailed = false;
if ($noMigrate) {
    echo "\nSkipping migrations (--no-migrate). Run them before opening the reports:\n";
    foreach ($migrations as $m) {
        echo "  php artisan migrate --force --path=$m\n";
    }
} else {
    echo "\nRunning migrations\n";
    foreach ($migrations as $m) {
        $out = [];
        $rc = 0;
        exec('cd ' . escapeshellarg($root) . ' && ' . escapeshellarg(PHP_BINARY) . ' artisan migrate --force --no-interaction --path=' . escapeshellarg($m) . ' 2>&1', $out, $rc);
        $text = trim(preg_replace('/\e\[[0-9;]*m/', '', implode("\n", $out)));
        echo ($rc === 0 ? '[ OK ] ' : '[FAIL] ') . basename($m) . "\n       " . str_replace("\n", "\n       ", $text) . "\n";
        if ($rc !== 0) {
            $migrateFailed = true;
        }
    }
}

echo "\nCreated " . count($created) . " file(s), changed " . count($written) . " file(s).\n";
echo "Next:\n";
echo "  php artisan view:clear\n";
echo "  php artisan optimize:clear\n";
echo "  php artisan permission:cache-reset\n";
echo "Then open CRM System > CRM Reports > Employee Reports, and log a call from any lead page.\n";
echo "To undo the files: php " . basename(__FILE__) . " --rollback\n";
exit($migrateFailed ? 6 : 0);
