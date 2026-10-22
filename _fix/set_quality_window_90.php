<?php

/**
 * set_quality_window_90 — narrow the quality review window from 180 days to 90.
 *
 * After the marked_at backfill the 180-day queue holds ~467 tasks. Completions run at
 * roughly 67 a month, so a 180-day window always carries about six months of backlog
 * and a team reviewing 15-20 a week can never reach the bottom of it. A queue nobody
 * can clear stops being a queue and starts being wallpaper.
 *
 * 90 days puts ~283 in the queue against ~200 completions in the same period, so the
 * backlog is roughly one month of catching up rather than six. The recovered dates
 * keep their value either way: they feed the on-time calculation and every completion
 * report, not only this list.
 *
 * Reversible — edit the constant back, or re-run install with 180.
 *
 *   php _fix/set_quality_window_90.php --dry-run
 *   php _fix/set_quality_window_90.php
 */

$dry  = in_array('--dry-run', $argv, true);
$file = __DIR__ . '/../app/Services/Quality/QualityBoard.php';

if (!is_file($file)) {
    fwrite(STDERR, "QualityBoard.php not found — run from the Laravel root.\n");
    exit(1);
}

$raw = file_get_contents($file);
$eol = strpos($raw, "\r\n") !== false ? "\r\n" : "\n";
$src = str_replace("\r\n", "\n", $raw);

$old = 'private const REVIEW_WINDOW_DAYS = 180;';
$new = 'private const REVIEW_WINDOW_DAYS = 90;';

if (strpos($src, $new) !== false) {
    echo "  already 90 days.\n";
    exit(0);
}

if (substr_count($src, $old) !== 1) {
    fwrite(STDERR, "  could not find the 180-day constant exactly once — refusing.\n");
    exit(1);
}

$src = str_replace($old, $new, $src);

// The blade states the window in words; leaving it saying 180 would make the screen lie.
$blade    = __DIR__ . '/../resources/views/quality/department.blade.php';
$oldPhrase = 'Completed tasks that carry a completion date, from the last 180 days.';
$newPhrase = 'Completed tasks that carry a completion date, from the last 90 days.';

echo "  QualityBoard: REVIEW_WINDOW_DAYS 180 -> 90\n";

if ($dry) {
    echo "  department.blade.php: would update the wording too\n";
    echo "\n  dry run — nothing written.\n";
    exit(0);
}

copy($file, $file . '.bak-' . date('Ymd-His'));
file_put_contents($file, str_replace("\n", $eol, $src));
clearstatcache();

if (is_file($blade)) {
    $braw = file_get_contents($blade);
    $beol = strpos($braw, "\r\n") !== false ? "\r\n" : "\n";
    $bsrc = str_replace("\r\n", "\n", $braw);

    if (strpos($bsrc, $oldPhrase) !== false) {
        copy($blade, $blade . '.bak-' . date('Ymd-His'));
        file_put_contents($blade, str_replace("\n", $beol, str_replace($oldPhrase, $newPhrase, $bsrc)));
        echo "  department.blade.php: wording now says 90 days\n";
    }
}

// A new English sentence with no Arabic entry renders as English for Arabic users, so
// the translation goes in with the change rather than as a follow-up nobody makes.
$lang = __DIR__ . '/../resources/lang/ar.json';

if (is_file($lang)) {
    $json = file_get_contents($lang);
    $key  = json_encode($newPhrase, JSON_UNESCAPED_UNICODE);

    if (strpos($json, $key . ':') !== false) {
        echo "  ar.json: translation already present\n";
    } else {
        $value = json_encode('المهام المكتملة التي تحمل تاريخ إكمال، خلال آخر 90 يوماً.', JSON_UNESCAPED_UNICODE);
        $close = strrpos($json, '}');

        if ($close === false) {
            fwrite(STDERR, "  ar.json: no closing brace — translation not added.\n");
        } else {
            $head = rtrim(substr($json, 0, $close));

            if (substr($head, -1) !== ',') {
                $head .= ',';
            }

            $candidate = $head . "\n    " . $key . ': ' . $value . "\n}\n";

            // Never leave this file unparseable: it is loaded on every page.
            if (json_decode($candidate) === null) {
                fwrite(STDERR, "  ar.json: result would not be valid JSON — left unchanged.\n");
            } else {
                copy($lang, $lang . '.bak-' . date('Ymd-His'));
                file_put_contents($lang, $candidate);
                clearstatcache();
                echo "  ar.json: Arabic added (" . filesize($lang) . " bytes)\n";
            }
        }
    }
}

echo "\nNow run:  php artisan optimize:clear\n";
