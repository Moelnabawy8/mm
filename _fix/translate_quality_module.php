<?php

/**
 * translate_quality_module — Arabic for the per-department Quality screens.
 *
 * Appended textually before the closing brace, never via json_decode: ar.json carries
 * duplicate keys and a decode/encode round trip collapses them. Keys are read by
 * scanning characters rather than by regex, because two keys in this file contain an
 * escaped quote and every convenient pattern gets them wrong.
 *
 *   php _fix/translate_quality_module.php --dry-run
 *   php _fix/translate_quality_module.php
 */

$dry    = in_array('--dry-run', $argv, true);
$target = __DIR__ . '/../resources/lang/ar.json';

if (!is_file($target)) {
    fwrite(STDERR, "missing: {$target}\n");
    exit(1);
}

$translations = [
    // الصفحة والتنقل
    'Quality'                          => 'الجودة',
    'Quality by Department'            => 'الجودة حسب الإدارة',
    'Quality Report'                   => 'تقرير الجودة',
    'Review quality'                   => 'مراجعة الجودة',
    'Departments'                      => 'الإدارات',
    'Setup'                            => 'الإعداد',

    // العدادات
    'Departments producing work'       => 'الإدارات المنتجة للعمل',
    'Awaiting review'                  => 'في انتظار المراجعة',
    'Reviews recorded'                 => 'المراجعات المسجّلة',
    'Average score'                    => 'متوسط الدرجة',
    'Review coverage'                  => 'نسبة التغطية',
    'Coverage'                         => 'التغطية',
    'Delivered on time'                => 'التسليم في الموعد',
    'On time'                          => 'في الموعد',
    'completed tasks that were reviewed' => 'من المهام المكتملة تمت مراجعتها',
    'of reviewed tasks'                => 'من المهام المُراجَعة',
    'vs previous 30 days'              => 'مقارنة بالـ30 يوم السابقة',

    // الجداول
    'Completed on'                     => 'تاريخ الإكمال',
    'Reviewed'                         => 'تمت مراجعته',
    'Reviewed on'                      => 'تاريخ المراجعة',
    'Reviewer'                         => 'المراجع',
    'Reviewers'                        => 'المراجعون',
    'Reviews'                          => 'المراجعات',
    'Recent reviews'                   => 'أحدث المراجعات',
    'Review'                           => 'مراجعة',
    'Score'                            => 'الدرجة',
    'Average'                          => 'المتوسط',
    'Lowest'                           => 'الأدنى',
    'Highest'                          => 'الأعلى',
    'By employee'                      => 'حسب الموظف',
    'Scores by department'             => 'الدرجات حسب الإدارة',

    // المعايير
    'Quality criteria'                 => 'معايير الجودة',
    'Criterion name'                   => 'اسم المعيار',
    'Short description (optional)'     => 'وصف مختصر (اختياري)',
    'Add criterion'                    => 'إضافة معيار',
    'Add the standard criteria'        => 'إضافة المعايير القياسية',
    'Weight'                           => 'الوزن',
    'weight'                           => 'وزن',
    'Top of scale'                     => 'أعلى درجة',
    'Retire'                           => 'إيقاف',
    'Retired'                          => 'موقوف',
    'Re-activate'                      => 'إعادة تفعيل',
    'Score each criterion'             => 'قيّم كل معيار',
    'Save review'                      => 'حفظ المراجعة',
    'Update review'                    => 'تحديث المراجعة',
    'Remove review'                    => 'حذف المراجعة',
    'Save reviewers'                   => 'حفظ المراجعين',

    // الحالات الفارغة والشروح
    'This department judges its own work by these.'
        => 'الإدارة تقيس شغلها بالمعايير دي.',
    'Named people, not roles.'
        => 'أشخاص محددون بالاسم، وليس أدوار.',
    'A department is judged on the tasks its projects complete. Departments with no completed tasks are listed last — their work is not tracked as project tasks.'
        => 'تُقيَّم الإدارة على المهام التي تكتملها مشاريعها. الإدارات التي لا تكمل مهاماً تظهر في الآخر — فعملها لا يُسجَّل كمهام مشاريع.',
    'A high score on very few reviews says little. This is how much of the completed work was actually looked at.'
        => 'الدرجة العالية على عدد مراجعات قليل لا تعني الكثير. هذه نسبة العمل المكتمل الذي جرت مراجعته فعلاً.',
    'The total is weighted, so a heavier criterion moves the score further.'
        => 'الإجمالي محسوب بالأوزان، فالمعيار الأثقل يحرّك الدرجة أكثر.',
    'Reviews recorded between the selected dates.'
        => 'المراجعات المسجّلة بين التاريخين المختارين.',
    'What was good, what needs to change next time.'
        => 'ما كان جيداً، وما يحتاج إلى تغيير في المرة القادمة.',

    'This department has no active quality criteria.'
        => 'لا توجد معايير جودة مفعّلة لهذه الإدارة.',
    'Nothing can be reviewed until at least one is defined.'
        => 'لا يمكن مراجعة أي شيء قبل تعريف معيار واحد على الأقل.',
    'This department has not completed any project tasks in the last 180 days.'
        => 'لم تكمل هذه الإدارة أي مهام مشاريع خلال آخر 180 يوماً.',
    'Everything completed has been reviewed.'
        => 'تمت مراجعة كل ما اكتمل.',
    'Completed tasks that carry a completion date, from the last 180 days.'
        => 'المهام المكتملة التي تحمل تاريخ إكمال، خلال آخر 180 يوماً.',
    'No reviews yet.'                  => 'لا توجد مراجعات بعد.',
    'No reviews were recorded in this period.' => 'لم تُسجَّل مراجعات في هذه الفترة.',
    'None defined.'                    => 'لا يوجد.',
    'Nobody is named yet.'             => 'لم يُحدَّد أحد بعد.',
    'No criteria'                      => 'بلا معايير',
    'No reviewer'                      => 'بلا مراجع',
    'No task activity'                 => 'لا نشاط مهام',
    'You are not named as a reviewer for any department yet.'
        => 'لم يتم تعيينك مراجعاً لأي إدارة حتى الآن.',
    '(task deleted)'                   => '(المهمة محذوفة)',

    // الرسائل
    'Criterion added.'                 => 'تمت إضافة المعيار.',
    'Standard criteria added.'         => 'تمت إضافة المعايير القياسية.',
    'Criterion retired.'               => 'تم إيقاف المعيار.',
    'Criterion re-activated.'          => 'تمت إعادة تفعيل المعيار.',
    'Criterion not found.'             => 'المعيار غير موجود.',
    'Reviewers saved.'                 => 'تم حفظ المراجعين.',
    'Review saved.'                    => 'تم حفظ المراجعة.',
    'Review not found.'                => 'المراجعة غير موجودة.',
    'Review removed. The task is back in the queue.'
        => 'تم حذف المراجعة. عادت المهمة إلى قائمة الانتظار.',
    'Remove this review and put the task back in the queue?'
        => 'حذف هذه المراجعة وإعادة المهمة إلى قائمة الانتظار؟',
    'Score every criterion before saving.' => 'قيّم كل المعايير قبل الحفظ.',
    'Add at least one criterion before reviewing.' => 'أضف معياراً واحداً على الأقل قبل المراجعة.',
    'This department already has criteria.' => 'هذه الإدارة لديها معايير بالفعل.',
    'You are not a reviewer for this department.' => 'أنت لست مراجعاً لهذه الإدارة.',
    'Department not found.'            => 'الإدارة غير موجودة.',
    'Task not found.'                  => 'المهمة غير موجودة.',
];

/** Key of a `  "key": "value",` line, honouring escaped quotes. */
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

        if ($c === '\\') {
            if ($i + 1 >= $len) {
                return null;
            }
            $key .= $c . $s[$i + 1];
            $i++;
            continue;
        }

        if ($c === '"') {
            $rest = ltrim(substr($s, $i + 1));

            if ($rest === '' || $rest[0] !== ':') {
                return null;
            }

            // Return the DECODED key. entryKey used to hand back the raw source form
            // with its backslashes intact, which never compares equal to the plain PHP
            // array key it is checked against — so a key containing a quote was judged
            // missing and re-appended on every run.
            $decoded = json_decode('"' . $key . '"');

            return is_string($decoded) ? $decoded : $key;
        }

        $key .= $c;
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

// The marker is a note to whoever reads this file later, not data. Adding it on every
// run that has work leaves a duplicate behind each time the script is re-run with new
// keys — which is exactly what happened on production, twice.
$marker = '_quality_module_ar_2026_09_12';
$block  = [''];

if (!isset($have[$marker])) {
    $block[] = '    "' . $marker . '": "Arabic for the per-department Quality section",';
}

$i = 0;
$n = count($add);
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
