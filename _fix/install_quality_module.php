<?php

/**
 * install_quality_module — wire up the per-department Quality section.
 *
 * Adds routes and one menu entry. Everything else the feature needs (controller,
 * models, service, views, migrations) ships as new files and needs no patching.
 *
 * Anchored in-place edits, never a whole-file write. On 2026-09-12 a deploy shipped
 * a complete routes/web.php from a local checkout and silently deleted 18 routes the
 * server had; the same package overwrote menu.blade.php and ar.json. Both files are
 * touched here by exact-anchor replacement only, and the script refuses to run if an
 * anchor is missing or appears more than once.
 *
 *   php _fix/install_quality_module.php --dry-run
 *   php _fix/install_quality_module.php
 */

$dry  = in_array('--dry-run', $argv, true);
$root = __DIR__ . '/..';

/* --------------------------------------------------------------------- routes */

$routesBlock = <<<'BLOCK'

// Quality by Department — a quality section inside each department, scoring the tasks
// its projects complete. Distinct from QualityEvaluationController above, which scores
// sales leads and has no department or task.
Route::middleware(['auth', 'XSS'])->group(function () {
    Route::get('quality', [\App\Http\Controllers\QualityReviewController::class, 'index'])
        ->name('quality.index');
    Route::get('quality/report', [\App\Http\Controllers\QualityReviewController::class, 'report'])
        ->name('quality.report');
    Route::get('quality/department/{id}', [\App\Http\Controllers\QualityReviewController::class, 'department'])
        ->name('quality.department');

    Route::post('quality/department/{id}/criteria', [\App\Http\Controllers\QualityReviewController::class, 'storeCriterion'])
        ->name('quality.criteria.store');
    Route::post('quality/department/{id}/criteria/seed', [\App\Http\Controllers\QualityReviewController::class, 'seedCriteria'])
        ->name('quality.criteria.seed');
    Route::post('quality/criteria/{id}/toggle', [\App\Http\Controllers\QualityReviewController::class, 'toggleCriterion'])
        ->name('quality.criteria.toggle');
    Route::post('quality/department/{id}/reviewers', [\App\Http\Controllers\QualityReviewController::class, 'saveReviewers'])
        ->name('quality.reviewers.save');

    Route::get('quality/task/{id}/review', [\App\Http\Controllers\QualityReviewController::class, 'reviewForm'])
        ->name('quality.review.form');
    Route::post('quality/task/{id}/review', [\App\Http\Controllers\QualityReviewController::class, 'storeReview'])
        ->name('quality.review.store');
    Route::post('quality/review/{id}/delete', [\App\Http\Controllers\QualityReviewController::class, 'destroyReview'])
        ->name('quality.review.destroy');
});

BLOCK;

/* ----------------------------------------------------------------------- menu */

$menuBlock = <<<'BLOCK'
                                @if (Gate::check('manage quality criteria') || Gate::check('review task quality') || Gate::check('view quality report'))
                                    <li class="dash-item {{ Request::segment(1) == 'quality' ? 'active' : '' }}">
                                        <a class="dash-link" href="{{ route('quality.index') }}">
                                            <i class="ti ti-award me-1"></i>{{ __('Quality by Department') }}
                                        </a>
                                    </li>
                                @endif
BLOCK;

/* -------------------------------------------------------------------- patches */

$patches = [
    [
        'file'   => $root . '/routes/web.php',
        'label'  => 'routes  — 10 quality routes',
        'probe'  => "->name('quality.index')",
        'anchor' => "Route::get('quality-evaluations-export', [QualityEvaluationController::class, 'export'])->middleware(['auth', 'XSS'])->name('quality-evaluations.export');",
        'where'  => 'after',
        'block'  => $routesBlock,
        'lint'   => true,
    ],
    [
        'file'   => $root . '/resources/views/partials/admin/menu.blade.php',
        'label'  => 'menu    — one entry beside the lead evaluations link',
        'probe'  => "route('quality.index')",
        'anchor' => "                                        <a class=\"dash-link\" href=\"{{ route('quality-evaluations.index') }}\">",
        'where'  => 'before-line-open',
        'block'  => $menuBlock,
        'lint'   => false,
    ],
];

$applied = 0;

foreach ($patches as $p) {
    if (!is_file($p['file'])) {
        fwrite(STDERR, "  missing file: {$p['file']}\n");
        exit(1);
    }

    $raw = file_get_contents($p['file']);
    $eol = strpos($raw, "\r\n") !== false ? "\r\n" : "\n";
    $src = str_replace("\r\n", "\n", $raw);

    if (strpos($src, $p['probe']) !== false) {
        echo "  already present  {$p['label']}\n";
        continue;
    }

    // The menu anchor is the <a>; the block belongs before the <li> that wraps it.
    $anchor = $p['anchor'];

    if ($p['where'] === 'before-line-open') {
        $pos = strpos($src, $anchor);

        if ($pos === false || strpos($src, $anchor, $pos + 1) !== false) {
            fwrite(STDERR, "  ANCHOR not unique for {$p['label']} — refusing.\n");
            exit(1);
        }

        // Walk back to the enclosing <li ...>, then keep walking past any blade
        // directive that OPENS immediately above it. Inserting between an @can and
        // its <li> nests the Quality entry inside that gate — which is how the first
        // install hid it behind @can('manage deal') from every reviewer who does not
        // hold that unrelated permission.
        $lineStart = strrpos(substr($src, 0, $pos), "\n") + 1;
        $prevStart = strrpos(substr($src, 0, $lineStart - 1), "\n") + 1;
        $prevLine  = substr($src, $prevStart, $lineStart - $prevStart);

        if (strpos($prevLine, '<li') === false) {
            fwrite(STDERR, "  the line above the anchor is not an <li> — refusing.\n"
                         . "  saw: " . trim($prevLine) . "\n");
            exit(1);
        }

        $insertAt = $prevStart;

        while ($insertAt > 0) {
            $aboveStart = strrpos(substr($src, 0, $insertAt - 1), "\n") + 1;
            $aboveLine  = trim(substr($src, $aboveStart, $insertAt - $aboveStart));

            // A closing directive is a sibling boundary — that is exactly where the
            // block should sit, so stop. Anything that is not an opener stops us too.
            if (!preg_match('/^@(can|canany|if)\b/', $aboveLine)) {
                break;
            }

            $insertAt = $aboveStart;
        }

        $src = substr($src, 0, $insertAt) . $p['block'] . "\n" . substr($src, $insertAt);
    } else {
        $hits = substr_count($src, $anchor);

        if ($hits !== 1) {
            fwrite(STDERR, "  ANCHOR {$hits}x (need exactly 1) for {$p['label']} — refusing.\n");
            exit(1);
        }

        $src = str_replace($anchor, $anchor . "\n" . $p['block'], $src);
    }

    echo "  patched          {$p['label']}\n";
    $applied++;

    if ($dry) {
        continue;
    }

    $out = str_replace("\n", $eol, $src);

    if ($p['lint']) {
        $tmp = sys_get_temp_dir() . '/q_' . getmypid() . '.php';
        file_put_contents($tmp, $out);
        exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $lines, $rc);
        unlink($tmp);

        if ($rc !== 0) {
            fwrite(STDERR, "  PHP SYNTAX ERROR — nothing written:\n  " . implode("\n  ", $lines) . "\n");
            exit(1);
        }
    }

    copy($p['file'], $p['file'] . '.bak-' . date('Ymd-His'));
    file_put_contents($p['file'], $out);
    echo "                   written " . basename($p['file']) . "\n";
}

if (!$applied) {
    echo "\nNothing to do.\n";
    exit(0);
}

echo $dry
    ? "\ndry run — nothing written.\n"
    : "\nNow run:\n  php artisan migrate --force\n  php artisan optimize:clear\n";
