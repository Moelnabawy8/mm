<?php
/**
 * restore_ar_translations — put back the Arabic keys a deploy package destroyed.
 *
 * On 2026-09-12 a deploy zip shipped a whole copy of resources/lang/ar.json taken
 * from a local checkout. The checkout had never carried the WhatsApp integration's
 * Arabic strings, so the overwrite deleted 174 translated keys — the entire WhatsApp
 * admin UI in Arabic. Nothing errored: a missing key in a JSON lang file falls back
 * to the key itself, so Arabic users simply started seeing English sentences.
 *
 * The donor is resources/lang/ar.json from a 2026-07-26 pull of production, which
 * still has every one of them.
 *
 * Two things this deliberately does NOT do:
 *
 *   - It never json_decode()s the file. ar.json carries duplicate keys; decoding and
 *     re-encoding would silently collapse them and lose more than it restores.
 *   - It does not match keys with a regular expression. Two keys in this file contain
 *     an escaped quote, and every convenient pattern gets them wrong, so the key is
 *     read by scanning characters and honouring backslash escapes.
 *
 *   php _fix/restore_ar_translations.php --dry-run
 *   php _fix/restore_ar_translations.php
 */

$dry    = in_array('--dry-run', $argv, true);
$target = __DIR__ . '/../resources/lang/ar.json';
$donor  = __DIR__ . '/ar.json.donor-20260726';

foreach ([$target, $donor] as $f) {
    if (!is_file($f)) {
        fwrite(STDERR, "missing: {$f}\n");
        exit(1);
    }
}

/**
 * Key of a `  "key": "value",` line, or null when the line is not an entry.
 * Scans instead of matching so that an escaped quote inside the key is read as
 * part of the key rather than ending it.
 */
function entryKey(string $line): ?string
{
    $s = ltrim($line);

    if ($s === '' || $s[0] !== '"') {
        return null;
    }

    $key = '';
    $len = strlen($s);

    for ($i = 1; $i < $len; $i++) {
        $c = $s[$i];

        if ($c === '\\') {          // keep the escape pair verbatim
            if ($i + 1 >= $len) {
                return null;        // trailing backslash: not a well-formed entry
            }
            $key .= $c . $s[$i + 1];
            $i++;
            continue;
        }

        if ($c === '"') {           // closing quote of the key
            $rest = ltrim(substr($s, $i + 1));

            return ($rest !== '' && $rest[0] === ':') ? $key : null;
        }

        $key .= $c;
    }

    return null;
}

$targetRaw   = file_get_contents($target);
$eol         = strpos($targetRaw, "\r\n") !== false ? "\r\n" : "\n";
$targetLines = explode("\n", str_replace("\r\n", "\n", $targetRaw));
$donorLines  = explode("\n", str_replace("\r\n", "\n", file_get_contents($donor)));

$have = [];
foreach ($targetLines as $l) {
    if (($k = entryKey($l)) !== null) {
        $have[$k] = true;
    }
}

if (count($have) < 1000) {
    fwrite(STDERR, "only " . count($have) . " keys parsed out of ar.json — the scanner is\n"
                 . "not reading this file correctly. Refusing to touch it.\n");
    exit(1);
}

// Each donor entry the target no longer has, in donor order, first occurrence only.
$missing = [];
$seen    = [];

foreach ($donorLines as $l) {
    $k = entryKey($l);

    if ($k === null || isset($have[$k]) || isset($seen[$k])) {
        continue;
    }

    $seen[$k]  = true;
    $missing[] = rtrim(trim($l), ',');
}

echo "  parsed " . count($have) . " keys in ar.json, " . count($seen) . " restorable in the donor\n";

if (!$missing) {
    echo "  nothing missing — ar.json already has every donor key.\n";
    exit(0);
}

echo "  " . count($missing) . " keys to restore, e.g.\n";
foreach (array_slice($missing, 0, 4) as $m) {
    echo "    " . mb_strimwidth($m, 0, 96, '…') . "\n";
}

// Place the block just before the closing brace, with the comma on the line above.
$close = null;
for ($i = count($targetLines) - 1; $i >= 0; $i--) {
    if (trim($targetLines[$i]) === '}') {
        $close = $i;
        break;
    }
}

if ($close === null) {
    fwrite(STDERR, "  could not find the closing brace of ar.json — refusing.\n");
    exit(1);
}

$last = null;
for ($i = $close - 1; $i >= 0; $i--) {
    if (trim($targetLines[$i]) !== '') {
        $last = $i;
        break;
    }
}

if ($last === null || entryKey($targetLines[$last]) === null) {
    fwrite(STDERR, "  the line before the closing brace is not an entry — refusing.\n");
    exit(1);
}

if (substr(rtrim($targetLines[$last]), -1) !== ',') {
    $targetLines[$last] = rtrim($targetLines[$last]) . ',';
}

$block   = [''];
$block[] = '    "_restored_2026_09_12": "WhatsApp Arabic strings recovered from the 2026-07-26 production pull after a deploy overwrote ar.json",';

foreach ($missing as $i => $m) {
    $block[] = '    ' . $m . ($i === count($missing) - 1 ? '' : ',');
}

array_splice($targetLines, $close, 0, $block);
$out = implode($eol, $targetLines);

// A broken lang file breaks every page, so validate before writing.
if (json_decode(str_replace("\r\n", "\n", $out)) === null) {
    fwrite(STDERR, "  RESULT IS NOT VALID JSON (" . json_last_error_msg() . ") — nothing written.\n");
    exit(1);
}

echo "\n  result parses as valid JSON\n";

if ($dry) {
    echo "  dry run — nothing written.\n";
    exit(0);
}

$bak = $target . '.bak-' . date('Ymd-His');
copy($target, $bak);
file_put_contents($target, $out);
clearstatcache();

echo "  backup  " . basename($bak) . "\n";
echo "  written resources/lang/ar.json  (" . filesize($target) . " bytes)\n";
echo "\nNow run:  php artisan optimize:clear\n";
