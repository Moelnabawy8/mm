<?php
/**
 * restore_lost_routes — put back the routes a deploy package deleted.
 *
 * On 2026-09-12 a deploy shipped a whole copy of routes/web.php taken from a
 * local checkout. That copy silently dropped every route the server had and
 * the checkout did not:
 *
 *   - 5 inactivity routes  (already restored, this script leaves them alone)
 *   - 13 WhatsApp admin routes — the controller, views, services and observers
 *     all survived, so the feature is intact but unreachable. The menu links
 *     are Route::has() guarded, which is why nothing 500'd and nobody noticed.
 *   - leads.bulk.destroy — leads/list.blade.php calls route() on it with no
 *     guard, so the Leads list page throws RouteNotFoundException.
 *
 * Anchored insertion, never a whole-file write: each block is placed relative
 * to a line that must already exist, and the script refuses to run if an
 * anchor is missing or ambiguous. Re-running is safe; present blocks are
 * skipped.
 *
 *   php _fix/restore_lost_routes.php --dry-run
 *   php _fix/restore_lost_routes.php
 */

$dry  = in_array('--dry-run', $argv, true);
$file = __DIR__ . '/../routes/web.php';

if (!is_file($file)) {
    fwrite(STDERR, "routes/web.php not found — run from the Laravel root.\n");
    exit(1);
}

$raw = file_get_contents($file);
$eol = strpos($raw, "\r\n") !== false ? "\r\n" : "\n";
$src = str_replace("\r\n", "\n", $raw);

$whatsapp = <<<'BLOCK'
            // WhatsApp task notifications (Meta Cloud API). Restored 2026-09-12 after a
            // deploy package overwrote routes/web.php and dropped the whole block.
            Route::post('whatsapp-settings', [\App\Http\Controllers\WhatsAppController::class, 'saveSettings'])->name('whatsapp.settings');
            Route::post('whatsapp/connection', [\App\Http\Controllers\WhatsAppController::class, 'connection'])->name('whatsapp.connection');
            Route::post('whatsapp/link/discover', [\App\Http\Controllers\WhatsAppController::class, 'linkDiscover'])->name('whatsapp.link.discover');
            Route::post('whatsapp/link/select', [\App\Http\Controllers\WhatsAppController::class, 'linkSelect'])->name('whatsapp.link.select');
            Route::post('whatsapp/link/template', [\App\Http\Controllers\WhatsAppController::class, 'linkCreateTemplate'])->name('whatsapp.link.template');
            Route::get('whatsapp/recipients', [\App\Http\Controllers\WhatsAppController::class, 'recipients'])->name('whatsapp.recipients');
            Route::post('whatsapp/recipients/import/preview', [\App\Http\Controllers\WhatsAppController::class, 'previewImport'])->name('whatsapp.recipients.import.preview');
            Route::get('whatsapp/recipients/import/preview', [\App\Http\Controllers\WhatsAppController::class, 'previewImport']);
            Route::post('whatsapp/recipients/import', [\App\Http\Controllers\WhatsAppController::class, 'runImport'])->name('whatsapp.recipients.import');
            Route::post('whatsapp/recipients/{id}/test', [\App\Http\Controllers\WhatsAppController::class, 'testSend'])->name('whatsapp.recipients.test');
            Route::post('whatsapp/recipients/{id}', [\App\Http\Controllers\WhatsAppController::class, 'updateRecipient'])->name('whatsapp.recipients.update');
            Route::get('whatsapp/log', [\App\Http\Controllers\WhatsAppController::class, 'log'])->name('whatsapp.log');
            Route::post('whatsapp/log/{id}/resend', [\App\Http\Controllers\WhatsAppController::class, 'resend'])->name('whatsapp.log.resend');
            Route::get('whatsapp/task/{taskId}', [\App\Http\Controllers\WhatsAppController::class, 'taskTab'])->name('whatsapp.task.tab');

BLOCK;

// leads/list.blade.php posts the whole selection to one endpoint; the old
// per-id loop it replaced is gone, so without this the page cannot render.
$leadsBulk = "    Route::post('/leads/bulk-destroy', [LeadController::class, 'bulkDestroy'])->name('leads.bulk.destroy')->middleware(['auth', 'XSS']);";

$patches = [
    [
        'label'  => 'WhatsApp admin routes (13)',
        'probe'  => "->name('whatsapp.recipients')",
        'anchor' => "            Route::get('print-setting', [SystemController::class, 'printIndex'])->name('print.setting');",
        'where'  => 'before',
        'block'  => $whatsapp,
    ],
    [
        'label'  => 'leads.bulk.destroy',
        'probe'  => "->name('leads.bulk.destroy')",
        'anchor' => "    Route::post('/leads/bulk-reassign', [LeadController::class, 'bulkReassign'])->name('leads.bulk.reassign')->middleware(['auth', 'XSS']);",
        'where'  => 'after',
        'block'  => $leadsBulk,
    ],
];

$before  = substr_count($src, "\n");
$applied = 0;

foreach ($patches as $p) {
    if (strpos($src, $p['probe']) !== false) {
        echo "  already present  {$p['label']}\n";
        continue;
    }

    $hits = substr_count($src, $p['anchor']);
    if ($hits !== 1) {
        fwrite(STDERR, "  ANCHOR {$hits}x (need exactly 1) for {$p['label']} — refusing.\n");
        exit(1);
    }

    $replacement = $p['where'] === 'before'
        ? $p['block'] . "\n" . $p['anchor']
        : $p['anchor'] . "\n" . $p['block'];

    $src = str_replace($p['anchor'], $replacement, $src);
    echo "  inserted         {$p['label']}\n";
    $applied++;
}

if (!$applied) {
    echo "\nNothing to do.\n";
    exit(0);
}

$added = substr_count($src, "\n") - $before;
echo "\n  +{$added} lines\n";

if ($dry) {
    echo "  dry run — nothing written.\n";
    exit(0);
}

// Syntax-check the result before it can replace a working file.
$tmp = sys_get_temp_dir() . '/web_' . getmypid() . '.php';
file_put_contents($tmp, str_replace("\n", $eol, $src));
exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $out, $rc);
if ($rc !== 0) {
    unlink($tmp);
    fwrite(STDERR, "  PHP SYNTAX ERROR — nothing written:\n  " . implode("\n  ", $out) . "\n");
    exit(1);
}
unlink($tmp);

$bak = $file . '.bak-' . date('Ymd-His');
copy($file, $bak);
file_put_contents($file, str_replace("\n", $eol, $src));

echo "  backup  " . basename($bak) . "\n  written routes/web.php\n";
echo "\nNow run:  php artisan optimize:clear\n";
