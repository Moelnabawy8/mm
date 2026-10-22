<?php
/**
 * apply_deal_purchase_patch.php
 *
 * Fixes /deal-purchase:
 *  - index/show/edit/update/destroy and the attachment routes had NO tenant check:
 *    any logged-in user reached (and could delete) another company's purchase, and
 *    download/serve had no authorisation at all -> scoped through deals.created_by
 *  - edit/update/destroy returned 500 instead of 404 on a missing id (bare find())
 *  - store/update accepted a negative price, a non-date purchase_date, a paid
 *    amount larger than the price, and another company's deal
 *  - the list ignored installments already marked paid, so it showed a different
 *    remaining balance than the purchase's own detail page
 *  - the Deal filter / create / edit forms rendered EVERY deal into Choices.js and
 *    froze the browser -> server-searched picker (LIMIT 25)
 *  - paid_amount looked editable for one-time payments while the controller always
 *    overwrote it with the price
 *  - totals silently added different currencies together -> warning banner
 *
 * Layout of the upload (Laravel root):
 *   apply_deal_purchase_patch.php
 *   dp_patch/<same relative paths as the app>
 *
 * Run from the Laravel root:   php apply_deal_purchase_patch.php
 *
 * SAFETY
 *  - Refuses to start unless resources/views/sales_meetings/_search_picker.blade.php
 *    is already on the server: the new blades @include it, and a missing partial
 *    would turn every /deal-purchase page into a 500.
 *  - Takes a full backup of every file it is about to touch, into
 *    storage/backups/deal_purchase_<timestamp>/, together with a restore.php.
 *    Nothing is written until that backup succeeded.
 *  - Whole-file replacement only when the server file still has the exact baseline
 *    hash measured on 2026-09-12 (erp_sync manifest, LF-normalised). A file that
 *    has drifted since is refused, not overwritten.
 *  - routes/web.php is patched by exact anchor, and only adds one route line while
 *    dropping a duplicated Route::resource. Nothing else in that file is touched.
 *  - Also leaves <file>.bak-dp-patch beside each changed file (once). Idempotent.
 */

$root = __DIR__;
while (!file_exists($root . '/artisan') && dirname($root) !== $root) {
    $root = dirname($root);
}
if (!file_exists($root . '/artisan')) {
    fwrite(STDERR, "Could not locate the Laravel root (no artisan found).\n");
    exit(1);
}
$src = __DIR__ . '/dp_patch';
if (!is_dir($src)) {
    fwrite(STDERR, "dp_patch/ folder not found next to this script.\n");
    exit(1);
}

function lfhash($contents)
{
    return substr(hash('sha256', str_replace("\r", '', $contents)), 0, 16);
}

function sidecar($path)
{
    $bak = $path . '.bak-dp-patch';
    if (file_exists($path) && !file_exists($bak)) {
        copy($path, $bak);
    }
}

/* ---------------------------------------------------------------- *
 * 0. Hard precondition: the shared picker partial must already be    *
 *    on this server (it ships with the sales-meetings patch).        *
 * ---------------------------------------------------------------- */
$picker = $root . '/resources/views/sales_meetings/_search_picker.blade.php';
if (!is_file($picker)) {
    fwrite(STDERR,
        "[FAIL] resources/views/sales_meetings/_search_picker.blade.php is missing on this server.\n"
        . "       The new deal-purchase blades @include it; installing them without it would\n"
        . "       turn every /deal-purchase page into a 500.\n"
        . "       Apply the sales-meetings patch first, then re-run this script.\n"
        . "Nothing was written.\n");
    exit(5);
}

// file => baseline hash on production (null = new file, nothing to back up)
$files = [
    'app/Http/Controllers/DealPurchaseController.php'        => '58bbb5a848065c25',
    'resources/views/admin/deal_purchase/index.blade.php'    => '1f48f61be6dcc85c',
    'resources/views/admin/deal_purchase/_table.blade.php'   => '3fd04950de7431e0',
    'resources/views/admin/deal_purchase/create.blade.php'   => '0c35529b0297f7fa',
    'resources/views/admin/deal_purchase/edit.blade.php'     => '60cfc481e3a8b867',
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
        echo "[FAIL] $rel -- missing from dp_patch/\n";
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

/* ---------------------------------------------------------------- *
 * 1b. routes/web.php -- one added route, one duplicate removed.      *
 * ---------------------------------------------------------------- */
$routeRel = 'routes/web.php';
$routeFile = $root . '/' . $routeRel;
$routeFrom = <<<'FROM'
Route::resource('deal-purchase', DealPurchaseController::class)->middleware(['auth', 'XSS']);
Route::get('deal-purchase/get-products/{deal_id}', [DealPurchaseController::class, 'getProducts'])->name('deal-purchase.get-products')->middleware(['auth', 'XSS']);
Route::resource('deal-purchase', DealPurchaseController::class)->middleware(['auth', 'XSS']);
FROM;
$routeTo = <<<'TO'
// Autocomplete for the Deal picker on the deal purchase screens. MUST stay above
// Route::resource('deal-purchase') or deal-purchase/{deal_purchase} would match it first.
Route::get('deal-purchase-deals-search', [DealPurchaseController::class, 'dealsSearch'])->name('deal-purchase.deals.search')->middleware(['auth', 'XSS']);
Route::get('deal-purchase/get-products/{deal_id}', [DealPurchaseController::class, 'getProducts'])->name('deal-purchase.get-products')->middleware(['auth', 'XSS']);
Route::resource('deal-purchase', DealPurchaseController::class)->middleware(['auth', 'XSS']);
TO;

$routePlan = false;
if (!is_file($routeFile)) {
    echo "[FAIL] routes/web.php -- not found\n";
    $failed++;
} else {
    $routes = file_get_contents($routeFile);
    if (strpos($routes, "'deal-purchase.deals.search'") !== false) {
        echo "[SKIP] routes/web.php -- already applied\n";
        $skipped++;
    } else {
        // Server is LF, local is CRLF: match on LF.
        $routesLf = str_replace("\r\n", "\n", $routes);
        $fromLf = str_replace("\r\n", "\n", $routeFrom);
        $count = substr_count($routesLf, $fromLf);
        if ($count !== 1) {
            echo "[FAIL] routes/web.php -- anchor matched $count times, refusing to guess\n";
            $failed++;
        } elseif (!is_writable($routeFile)) {
            echo "[FAIL] routes/web.php -- not writable\n";
            $failed++;
        } else {
            $routePlan = str_replace($fromLf, str_replace("\r\n", "\n", $routeTo), $routesLf);
        }
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
$backupDir = $root . '/storage/backups/deal_purchase_' . $stamp;
if (!is_dir(dirname($backupDir)) && !@mkdir(dirname($backupDir), 0775, true)) {
    // storage/ not writable: fall back to a folder next to this script
    $backupDir = __DIR__ . '/dp_backup_' . $stamp;
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
    echo "[ OK ] routes/web.php :: deal-purchase.deals.search added, duplicate resource removed\n";
    $applied++;
}

echo "\n--------------------------------------------------\n";
echo "applied: $applied   skipped: $skipped   failed: 0\n";
echo "backup:  $backupDir\n";
echo "\nNext: php artisan view:clear && php artisan route:clear\n";
echo "Then hard-refresh /deal-purchase in the browser (Ctrl+Shift+R).\n";
exit(0);
