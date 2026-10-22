<?php

/**
 * fix_quality_menu_placement — lift the Quality link out of @can('manage deal').
 *
 * install_quality_module.php walked back from the quality-evaluations <a> to the <li>
 * that wraps it and inserted above that line. The <li> was correct, but an
 * @can('manage deal') opened on the line immediately above it, so the block landed
 * inside that gate: a user holding 'review task quality' but not 'manage deal' — which
 * is most reviewers — never saw the link.
 *
 * This swaps the two so the @can opens after the block, and gives the entry the icon
 * its neighbours have.
 *
 *   php _fix/fix_quality_menu_placement.php --dry-run
 *   php _fix/fix_quality_menu_placement.php
 */

$dry  = in_array('--dry-run', $argv, true);
$file = __DIR__ . '/../resources/views/partials/admin/menu.blade.php';

if (!is_file($file)) {
    fwrite(STDERR, "menu.blade.php not found — run from the Laravel root.\n");
    exit(1);
}

$raw = file_get_contents($file);
$eol = strpos($raw, "\r\n") !== false ? "\r\n" : "\n";
$src = str_replace("\r\n", "\n", $raw);

$can = "                                @can('manage deal')\n";

// The block exactly as install_quality_module.php wrote it.
$oldBlock = "                                        @if (Gate::check('manage quality criteria') || Gate::check('review task quality') || Gate::check('view quality report'))\n"
          . "                                            <li class=\"dash-item {{ Request::segment(1) == 'quality' ? 'active' : '' }}\">\n"
          . "                                                <a class=\"dash-link\" href=\"{{ route('quality.index') }}\">\n"
          . "                                                    {{ __('Quality by Department') }}\n"
          . "                                                </a>\n"
          . "                                            </li>\n"
          . "                                        @endif\n";

// Re-indented to sit alongside its siblings, and with an icon like theirs.
$newBlock = "                                @if (Gate::check('manage quality criteria') || Gate::check('review task quality') || Gate::check('view quality report'))\n"
          . "                                    <li class=\"dash-item {{ Request::segment(1) == 'quality' ? 'active' : '' }}\">\n"
          . "                                        <a class=\"dash-link\" href=\"{{ route('quality.index') }}\">\n"
          . "                                            <i class=\"ti ti-award me-1\"></i>{{ __('Quality by Department') }}\n"
          . "                                        </a>\n"
          . "                                    </li>\n"
          . "                                @endif\n";

if (strpos($src, $newBlock) !== false) {
    echo "  already correct — the Quality link sits outside @can('manage deal').\n";
    exit(0);
}

$wrong = $can . $oldBlock;

$hits = substr_count($src, $wrong);

if ($hits !== 1) {
    fwrite(STDERR, "  expected the block nested inside @can('manage deal') exactly once, found {$hits}.\n"
                 . "  The menu is not in the state this fix was written for — refusing to touch it.\n");
    exit(1);
}

$src = str_replace($wrong, $newBlock . $can, $src);

echo "  moved the Quality link above @can('manage deal')\n";
echo "  re-indented to match its siblings and added the ti-award icon\n";

// Every @if must still have its @endif, and every @can its @endcan.
foreach ([['@if(', '@if ', '@endif'], ['@can(', '@can ', '@endcan']] as [$a, $b, $end]) {
    $open  = substr_count($src, $a) + substr_count($src, $b);
    $close = substr_count($src, $end);

    if ($open !== $close) {
        fwrite(STDERR, "  directive imbalance after the edit ({$a} {$open} vs {$end} {$close}) — nothing written.\n");
        exit(1);
    }
}

echo "  blade directives balance\n";

if ($dry) {
    echo "\n  dry run — nothing written.\n";
    exit(0);
}

copy($file, $file . '.bak-' . date('Ymd-His'));
file_put_contents($file, str_replace("\n", $eol, $src));
clearstatcache();

echo "\n  written menu.blade.php\n";
echo "\nNow run:  php artisan view:clear\n";
