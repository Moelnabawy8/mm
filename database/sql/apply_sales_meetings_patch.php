<?php
/**
 * apply_sales_meetings_patch.php
 *
 * Fixes /sales-meetings:
 *  - the page, create and edit forms rendered EVERY lead and deal into Choices.js
 *    dropdowns and froze the browser -> server-searched pickers (LIMIT 25)
 *  - show/edit/update/destroy/note/products/get-deals had no tenant check and
 *    returned 500 on a missing id
 *  - store/update accepted another company's lead and a deal of a different lead
 *  - empty Duration => 500; time was dropped (every row said 12:00 AM)
 *  - attachment links pointed at the wrong disk; Add Product modal printed before <html>
 *  - one query per row for Assigned Staff
 *
 * Layout of the upload (Laravel root):
 *   apply_sales_meetings_patch.php
 *   sm_patch/<same relative paths as the app>
 *
 * Run from the Laravel root:   php apply_sales_meetings_patch.php
 *
 * SAFETY
 *  - Takes a full backup of every file it is about to touch, into
 *    storage/backups/sales_meetings_<timestamp>/, together with a restore.php
 *    that puts them all back. Nothing is written until that backup succeeded.
 *  - Whole-file replacement only when the server file still has the exact baseline
 *    hash measured on 2026-09-12 (erp_sync manifest, LF-normalised). A file that
 *    has drifted since is refused, not overwritten.
 *  - routes/web.php is patched by exact anchor.
 *  - Also leaves <file>.bak-sm-patch beside each changed file (once). Idempotent.
 */

$root = __DIR__;
while (!file_exists($root . '/artisan') && dirname($root) !== $root) {
    $root = dirname($root);
}
if (!file_exists($root . '/artisan')) {
    fwrite(STDERR, "Could not locate the Laravel root (no artisan found).\n");
    exit(1);
}
$src = __DIR__ . '/sm_patch';
if (!is_dir($src)) {
    fwrite(STDERR, "sm_patch/ folder not found next to this script.\n");
    exit(1);
}

function lfhash($contents)
{
    return substr(hash('sha256', str_replace("\r", '', $contents)), 0, 16);
}

function sidecar($path)
{
    $bak = $path . '.bak-sm-patch';
    if (file_exists($path) && !file_exists($bak)) {
        copy($path, $bak);
    }
}

// file => baseline hash on production (null = new file, nothing to back up)
$files = [
    'app/Http/Controllers/SalesMeetingController.php' => '186d5da9fa004486',
    'app/Models/SalesMeeting.php' => '052187a6d398f87f',
    'resources/views/sales_meetings/index.blade.php' => 'e133b2de9f008a6e',
    'resources/views/sales_meetings/_table.blade.php' => '72b3c38a529a31ff',
    'resources/views/sales_meetings/create.blade.php' => '72aa0d533e9e04ea',
    'resources/views/sales_meetings/edit.blade.php' => 'ba3afdb9cd66575d',
    'resources/views/sales_meetings/show.blade.php' => '48230075de18e786',
    'resources/views/sales_meetings/_search_picker.blade.php' => null,
];

$applied = $skipped = $failed = 0;

/* ---------------------------------------------------------------- *
 * 1. Plan: check every file first, so a drifted file never leaves    *
 *    a half-applied deploy.                                          *
 * ---------------------------------------------------------------- */
$plan = [];
foreach ($files as $rel => $baseline) {
    $new = $src . '/' . $rel;
    $dst = $root . '/' . $rel;
    if (!is_file($new)) {
        echo "[FAIL] $rel -- missing from sm_patch/\n";
        $failed++;
        continue;
    }
    $newHash = lfhash(file_get_contents($new));
    if (is_file($dst)) {
        $curHash = lfhash(file_get_contents($dst));
        if ($curHash === $newHash) {
            echo "[SKIP] $rel -- already applied\n";
            $skipped++;
            continue;
        }
        if ($baseline === null || $curHash !== $baseline) {
            echo "[FAIL] $rel -- server copy ($curHash) differs from the expected baseline ($baseline); refusing to overwrite\n";
            $failed++;
            continue;
        }
    }
    if (!is_writable(dirname($dst)) || (is_file($dst) && !is_writable($dst))) {
        echo "[FAIL] $rel -- not writable by " . get_current_user() . " (root-owned directory?)\n";
        $failed++;
        continue;
    }
    $plan[$rel] = [$new, $dst];
}

$routeRel = 'routes/web.php';
$routeFile = $root . '/' . $routeRel;
$routeFrom = <<<'FROM'
    Route::resource('sales-meetings', SalesMeetingController::class)->middleware(['auth', 'XSS']);
    Route::post('/sales-meetings/get-deals', [SalesMeetingController::class, 'getDeals'])->name('sales-meetings.get-deals');
FROM;
$routeTo = <<<'TO'
    // Autocomplete for the Lead / Deal pickers on the sales meeting screens.
    Route::get('/sales-meetings-leads-search', [SalesMeetingController::class, 'leadsSearch'])->name('sales-meetings.leads.search')->middleware(['auth', 'XSS']);
    Route::get('/sales-meetings-deals-search', [SalesMeetingController::class, 'dealsSearch'])->name('sales-meetings.deals.search')->middleware(['auth', 'XSS']);
    Route::resource('sales-meetings', SalesMeetingController::class)->middleware(['auth', 'XSS']);
    Route::post('/sales-meetings/get-deals', [SalesMeetingController::class, 'getDeals'])->name('sales-meetings.get-deals')->middleware(['auth', 'XSS']);
    Route::get('/sales-meetings/{id}/attachment', [SalesMeetingController::class, 'attachment'])->name('sales-meetings.attachment')->middleware(['auth', 'XSS']);
TO;
$routes = file_get_contents($routeFile);
$routePlan = false;
if (strpos($routes, "'sales-meetings.leads.search'") !== false) {
    echo "[SKIP] routes/web.php -- already applied\n";
    $skipped++;
} else {
    // Server is LF, local is CRLF: match on LF.
    $routesLf = str_replace("\r\n", "\n", $routes);
    $count = substr_count($routesLf, str_replace("\r\n", "\n", $routeFrom));
    if ($count !== 1) {
        echo "[FAIL] routes/web.php -- anchor matched $count times, refusing to guess\n";
        $failed++;
    } elseif (!is_writable($routeFile)) {
        echo "[FAIL] routes/web.php -- not writable\n";
        $failed++;
    } else {
        $routePlan = str_replace(str_replace("\r\n", "\n", $routeFrom), str_replace("\r\n", "\n", $routeTo), $routesLf);
    }
}

if ($failed > 0) {
    echo "\nNothing was written: fix the failures above first (applied: 0).\n";
    exit(2);
}

if (!$plan && $routePlan === false) {
    echo "\n--------------------------------------------------\n";
    echo "applied: 0   skipped: $skipped   failed: 0   (nothing to do)\n";
    exit(0);
}

/* ---------------------------------------------------------------- *
 * 2. Back up every file about to be touched, BEFORE writing any.     *
 * ---------------------------------------------------------------- */
$toBackup = array_keys($plan);
if ($routePlan !== false) {
    $toBackup[] = $routeRel;
}
$toBackup = array_values(array_filter($toBackup, function ($rel) use ($root) {
    return is_file($root . '/' . $rel); // a brand new file has nothing to back up
}));

$stamp = date('Ymd_His');
$backupDir = $root . '/storage/backups/sales_meetings_' . $stamp;
if (!is_dir(dirname($backupDir)) && !@mkdir(dirname($backupDir), 0775, true)) {
    // storage/ not writable: fall back to a folder next to this script
    $backupDir = __DIR__ . '/sm_backup_' . $stamp;
}
if (!@mkdir($backupDir, 0775, true)) {
    fwrite(STDERR, "[FAIL] could not create the backup directory: $backupDir\nNothing was written.\n");
    exit(4);
}

$manifest = [];
foreach ($toBackup as $rel) {
    $from = $root . '/' . $rel;
    $to = $backupDir . '/' . $rel;
    if (!is_dir(dirname($to)) && !@mkdir(dirname($to), 0775, true)) {
        fwrite(STDERR, "[FAIL] could not create $to\nNothing was written.\n");
        exit(4);
    }
    if (!@copy($from, $to) || filesize($to) !== filesize($from)) {
        fwrite(STDERR, "[FAIL] backup of $rel failed\nNothing was written.\n");
        exit(4);
    }
    $manifest[] = lfhash(file_get_contents($from)) . '  ' . $rel;
    echo "[BACK] $rel\n";
}
file_put_contents($backupDir . '/MANIFEST.txt', implode("\n", $manifest) . "\n");

// A restore script that lives with the backup, so rolling back needs no zip.
$restore = <<<'RESTORE'
<?php
/**
 * Restore the files this backup was taken from. Run:   php restore.php
 * Add --force to overwrite even when the current file changed after the backup.
 */
$backupDir = __DIR__;
$root = $backupDir;
while (!file_exists($root . '/artisan') && dirname($root) !== $root) {
    $root = dirname($root);
}
if (!file_exists($root . '/artisan')) {
    fwrite(STDERR, "Could not locate the Laravel root.\n");
    exit(1);
}
$force = in_array('--force', $argv, true);
$restored = 0;
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($backupDir, FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
    if (!$file->isFile()) {
        continue;
    }
    $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($backupDir) + 1));
    if ($rel === 'restore.php' || $rel === 'MANIFEST.txt') {
        continue;
    }
    $dst = $root . '/' . $rel;
    if (!is_writable(dirname($dst)) || (is_file($dst) && !is_writable($dst))) {
        echo "[FAIL] $rel -- not writable\n";
        continue;
    }
    if (!$force && is_file($dst) && md5_file($dst) === md5_file($file->getPathname())) {
        echo "[SKIP] $rel -- identical\n";
        continue;
    }
    copy($file->getPathname(), $dst);
    echo "[ OK ] $rel restored\n";
    $restored++;
}
echo "\nrestored: $restored\nNext: php artisan view:clear && php artisan route:clear\n";
RESTORE;
file_put_contents($backupDir . '/restore.php', $restore);
echo "\nBackup: " . $backupDir . "\n";
echo "Roll back at any time with:  php " . $backupDir . "/restore.php\n\n";

/* ---------------------------------------------------------------- *
 * 3. Apply.                                                          *
 * ---------------------------------------------------------------- */
foreach ($plan as $rel => $pair) {
    list($new, $dst) = $pair;
    sidecar($dst);
    if (!copy($new, $dst)) {
        echo "[FAIL] $rel -- copy failed\n";
        echo "Restore with: php " . $backupDir . "/restore.php\n";
        exit(3);
    }
    echo "[ OK ] $rel\n";
    $applied++;
}
if ($routePlan !== false) {
    sidecar($routeFile);
    file_put_contents($routeFile, $routePlan);
    echo "[ OK ] routes/web.php :: search + attachment routes, auth on get-deals\n";
    $applied++;
}

echo "\n--------------------------------------------------\n";
echo "applied: $applied   skipped: $skipped   failed: 0\n";
echo "backup:  $backupDir\n";
echo "\nNext: php artisan view:clear && php artisan route:clear\n";
exit(0);
