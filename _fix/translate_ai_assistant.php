<?php

/**
 * translate_ai_assistant — Arabic for the AI assistant's messages.
 *
 * These are all failure and limit messages, which is exactly the text a user sees when
 * something is wrong — leaving it in English is leaving the worst moment untranslated.
 *
 * Appended textually before the closing brace, never via json_decode: ar.json carries
 * duplicate keys and a decode/encode round trip collapses them. Keys are read by
 * scanning characters and returned decoded, so a key containing a quote compares equal
 * to its plain PHP counterpart instead of being re-added on every run.
 *
 *   php _fix/translate_ai_assistant.php --dry-run
 *   php _fix/translate_ai_assistant.php
 */

$dry    = in_array('--dry-run', $argv, true);
$target = __DIR__ . '/../resources/lang/ar.json';

if (!is_file($target)) {
    fwrite(STDERR, "missing: {$target}\n");
    exit(1);
}

$translations = [
    'The AI assistant is switched off. An administrator can turn it on in settings.'
        => 'المساعد الذكي مُوقَف. يمكن للمسؤول تفعيله من الإعدادات.',
    'No OpenAI key is configured, so the assistant cannot answer. An administrator needs to set one.'
        => 'لا يوجد مفتاح OpenAI مُعدّ، فالمساعد لا يستطيع الإجابة. المسؤول يحتاج إلى إضافة مفتاح.',
    'You have used your monthly allowance of AI questions.'
        => 'استهلكت حصتك الشهرية من أسئلة المساعد الذكي.',
    'That question needed too many lookups. Try asking it more simply.'
        => 'هذا السؤال احتاج عمليات بحث كثيرة. جرّب صياغته أبسط.',
    'Could not reach the AI service. Try again in a moment.'
        => 'تعذّر الوصول إلى خدمة الذكاء الاصطناعي. حاول بعد قليل.',
    'The OpenAI key is not valid.'
        => 'مفتاح OpenAI غير صالح.',
    'The AI service is rate limited or out of credit. Try again later.'
        => 'خدمة الذكاء الاصطناعي بلغت حد الاستخدام أو نفد رصيدها. حاول لاحقاً.',
    'The AI service returned an error.'
        => 'خدمة الذكاء الاصطناعي أرجعت خطأ.',
    'The AI service returned nothing usable.'
        => 'خدمة الذكاء الاصطناعي لم ترجع شيئاً صالحاً.',
    'The assistant had nothing to say. Try rephrasing.'
        => 'المساعد لم يكن لديه ما يقوله. جرّب إعادة الصياغة.',

    // Shown beside the chat box so a user knows where they stand before they run out.
    'AI Assistant'                  => 'المساعد الذكي',
    'questions left this month'     => 'سؤال متبقٍ هذا الشهر',
    'Unlimited'                     => 'بلا حد',
    'Answers from your own ERP data.' => 'يجيب من بيانات نظامك نفسه.',
];

/** Decoded key of a `  "key": "value",` line, or null when the line is not an entry. */
function entryKey(string $line): ?string
{
    $s = ltrim($line);

    if ($s === '' || $s[0] !== '"') {
        return null;
    }

    $raw = '';
    $len = strlen($s);

    for ($i = 1; $i < $len; $i++) {
        $c = $s[$i];

        if ($c === '\\') {
            if ($i + 1 >= $len) {
                return null;
            }
            $raw .= $c . $s[$i + 1];
            $i++;
            continue;
        }

        if ($c === '"') {
            $rest = ltrim(substr($s, $i + 1));

            if ($rest === '' || $rest[0] !== ':') {
                return null;
            }

            $decoded = json_decode('"' . $raw . '"');

            return is_string($decoded) ? $decoded : $raw;
        }

        $raw .= $c;
    }

    return null;
}

$raw   = file_get_contents($target);
$eol   = strpos($raw, "\r\n") !== false ? "\r\n" : "\n";
$lines = explode("\n", str_replace("\r\n", "\n", $raw));

$have = [];
foreach ($lines as $l) {
    if (($k = entryKey($l)) !== null) {
        $have[$k] = true;
    }
}

if (count($have) < 1000) {
    fwrite(STDERR, "  only " . count($have) . " keys parsed from ar.json — refusing to touch it.\n");
    exit(1);
}

$add = array_diff_key($translations, $have);

echo "  ar.json: " . count($have) . " keys present, " . count($add) . " to add\n";

if (!$add) {
    echo "  nothing to do.\n";
    exit(0);
}

$close = null;
for ($i = count($lines) - 1; $i >= 0; $i--) {
    if (trim($lines[$i]) === '}') {
        $close = $i;
        break;
    }
}

if ($close === null) {
    fwrite(STDERR, "  no closing brace in ar.json — refusing.\n");
    exit(1);
}

$last = null;
for ($i = $close - 1; $i >= 0; $i--) {
    if (trim($lines[$i]) !== '') {
        $last = $i;
        break;
    }
}

if ($last === null || entryKey($lines[$last]) === null) {
    fwrite(STDERR, "  the line before the closing brace is not an entry — refusing.\n");
    exit(1);
}

if (substr(rtrim($lines[$last]), -1) !== ',') {
    $lines[$last] = rtrim($lines[$last]) . ',';
}

$marker = '_ai_assistant_ar_2026_09_16';
$block  = [''];

if (!isset($have[$marker])) {
    $block[] = '    "' . $marker . '": "Arabic for the AI assistant (task 6)",';
}

$i   = 0;
$n   = count($add);
$enc = fn($s) => json_encode($s, JSON_UNESCAPED_UNICODE);

foreach ($add as $k => $v) {
    $block[] = '    ' . $enc($k) . ': ' . $enc($v) . (++$i === $n ? '' : ',');
}

array_splice($lines, $close, 0, $block);
$out = implode($eol, $lines);

if (json_decode(str_replace("\r\n", "\n", $out)) === null) {
    fwrite(STDERR, "  RESULT IS NOT VALID JSON (" . json_last_error_msg() . ") — nothing written.\n");
    exit(1);
}

echo "  ar.json: result parses as valid JSON\n";

if ($dry) {
    echo "\n  dry run — nothing written.\n";
    exit(0);
}

copy($target, $target . '.bak-' . date('Ymd-His'));
file_put_contents($target, $out);
clearstatcache();

echo "  ar.json: written (" . filesize($target) . " bytes)\n";
echo "\nNow run:  php artisan optimize:clear\n";
