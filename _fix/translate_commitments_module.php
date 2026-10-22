<?php

/**
 * translate_commitments_module — Arabic for commitments and meeting minutes.
 *
 * Appended textually before the closing brace, never via json_decode: ar.json carries
 * duplicate keys and a decode/encode round trip collapses them. Keys are read by
 * scanning characters rather than by regex, because two keys in this file contain an
 * escaped quote and every convenient pattern gets them wrong.
 *
 *   php _fix/translate_commitments_module.php --dry-run
 *   php _fix/translate_commitments_module.php
 */

$dry    = in_array('--dry-run', $argv, true);
$target = __DIR__ . '/../resources/lang/ar.json';

if (!is_file($target)) {
    fwrite(STDERR, "missing: {$target}\n");
    exit(1);
}

$translations = [
    // الصفحات والتنقل
    'My Commitments'                   => 'التزاماتي',
    'Weekly Commitments'               => 'الالتزامات الأسبوعية',
    'Commitments owed to me'           => 'الالتزامات المستحقة لي',
    'Owed to me'                       => 'المستحق لي',
    'What I owe'                       => 'ما عليّ',
    'Commitment'                       => 'التزام',
    'New commitment'                   => 'التزام جديد',
    'Edit commitment'                  => 'تعديل الالتزام',
    'Commitments this week'            => 'التزامات هذا الأسبوع',
    'Previous week'                    => 'الأسبوع السابق',
    'Next week'                        => 'الأسبوع التالي',
    'Back to this week'                => 'العودة لهذا الأسبوع',
    'By department'                    => 'حسب الإدارة',
    'Supervisors'                      => 'المشرفون',

    // الحقول
    'What is being committed to'       => 'ما الملتزَم به',
    'One clear sentence — what will be delivered' => 'جملة واحدة واضحة — ما الذي سيُسلَّم',
    'Detail'                           => 'التفاصيل',
    'Detail (optional)'                => 'تفاصيل (اختياري)',
    'What counts as done, and anything the supervisor should know.'
        => 'ما الذي يُعتبر إنجازاً، وأي شيء يجب أن يعرفه المشرف.',
    'Who owes it'                      => 'من عليه الالتزام',
    'An employee'                      => 'موظف',
    'A whole department'               => 'إدارة كاملة',
    'Choose an employee'               => 'اختر موظفاً',
    'Choose a department'              => 'اختر إدارة',
    'Choose'                           => 'اختر',
    'Owed to'                          => 'مستحق لـ',
    'Owed by'                          => 'على',
    'Choose the supervisor this is owed to' => 'اختر المشرف المستحق له',
    'They will see it under "Owed to me".' => 'سيظهر له تحت «المستحق لي».',
    'Cadence'                          => 'التكرار',
    'One-off, with a due date'         => 'لمرة واحدة، بموعد نهائي',
    'One-off'                          => 'لمرة واحدة',
    'For a week'                       => 'لأسبوع',
    'Weekly'                           => 'أسبوعي',
    'weekly'                           => 'أسبوعي',
    'recurring'                        => 'متكرر',
    'Due date'                         => 'الموعد النهائي',
    'Week starting (Monday)'           => 'الأسبوع يبدأ (الإثنين)',
    'Repeat this every week'           => 'كرّر هذا كل أسبوع',
    'A copy is created each Monday until you turn this off.'
        => 'تُنشأ نسخة كل يوم إثنين حتى توقف هذا.',
    'department'                       => 'إدارة',
    'By when'                          => 'بحلول متى',
    'Who will do it'                   => 'من سينفّذه',

    // الحالات
    'Kept'                             => 'مُنجَز',
    'Missed'                           => 'فائت',
    'Withdrawn'                        => 'مسحوب',
    'Kept rate'                        => 'نسبة الإنجاز',
    'of commitments that reached a verdict' => 'من الالتزامات التي حُسم أمرها',
    'days late'                        => 'يوم تأخير',
    'days left'                        => 'يوم متبقٍ',
    'due today'                        => 'مستحق اليوم',
    'days ago'                         => 'يوماً مضى',
    'from a meeting'                   => 'من اجتماع',

    // التحديثات
    'Add an update'                    => 'إضافة تحديث',
    'Record update'                    => 'تسجيل التحديث',
    'Update recorded.'                 => 'تم تسجيل التحديث.',
    'Change status'                    => 'تغيير الحالة',
    'Leave as it is'                   => 'اتركها كما هي',
    'Kept — it is done'                => 'مُنجَز — تم',
    'Marking it kept sets progress to 100%.' => 'تحديده كمُنجَز يجعل التقدم 100%.',
    'Where it stands, and anything blocking it.' => 'أين وصل، وما الذي يعطّله.',
    'A commitment that only changes on its last day tells a supervisor nothing in time to help.'
        => 'الالتزام الذي لا يتغيّر إلا في يومه الأخير لا يفيد المشرف في وقت يسمح بالمساعدة.',
    'No updates yet.'                  => 'لا توجد تحديثات بعد.',

    // الرسائل
    'Commitment created.'              => 'تم إنشاء الالتزام.',
    'Commitment updated.'              => 'تم تحديث الالتزام.',
    'Commitment deleted.'              => 'تم حذف الالتزام.',
    'Commitment not found.'            => 'الالتزام غير موجود.',
    'Commitment added from this meeting.' => 'تمت إضافة الالتزام من هذا الاجتماع.',
    'Choose who owes this commitment.' => 'اختر من عليه هذا الالتزام.',
    'A one-off commitment needs a due date.' => 'الالتزام لمرة واحدة يحتاج موعداً نهائياً.',
    'Write a note, set progress, or change the status.' => 'اكتب ملاحظة، أو حدّد التقدم، أو غيّر الحالة.',
    'Delete this commitment and its history?' => 'حذف هذا الالتزام وسجله؟',
    'A commitment is something one person or department owes another by a date.'
        => 'الالتزام شيء يترتب على شخص أو إدارة تجاه شخص آخر بموعد محدد.',
    'Who owes it and its cadence cannot change — that would rewrite what was agreed, not edit it.'
        => 'لا يمكن تغيير من عليه الالتزام ولا تكراره — هذا إعادة كتابة لما اتُّفق عليه وليس تعديلاً.',

    // الحالات الفارغة
    'Nothing here.'                    => 'لا يوجد شيء هنا.',
    'You owe nothing right now.'       => 'لا يترتب عليك شيء حالياً.',
    'Nobody has committed anything to you yet.' => 'لم يلتزم لك أحد بشيء بعد.',
    'No commitments recorded for this week.' => 'لا توجد التزامات مسجّلة لهذا الأسبوع.',
    'Nothing this week.'               => 'لا شيء هذا الأسبوع.',
    'Nobody yet.'                      => 'لا أحد بعد.',
    'Open and overdue first, then what has been settled.'
        => 'المفتوح والمتأخر أولاً، ثم ما حُسم.',
    'Everything anyone has committed to you, whatever their department.'
        => 'كل ما التزم به أي شخص تجاهك، أياً كانت إدارته.',
    'Who is being committed to, across all weeks.' => 'لمن تُقدَّم الالتزامات، عبر كل الأسابيع.',

    // الاجتماعات
    'Meeting Minutes'                  => 'محاضر الاجتماعات',
    'Meeting minutes'                  => 'محاضر الاجتماعات',
    'Minutes'                          => 'المحضر',
    'Save minutes'                     => 'حفظ المحضر',
    'Minutes saved.'                   => 'تم حفظ المحضر.',
    'Meeting not found.'               => 'الاجتماع غير موجود.',
    'Meeting status'                   => 'حالة الاجتماع',
    'Held'                             => 'انعقد',
    'held'                             => 'انعقد',
    'Awaiting minutes'                 => 'بانتظار المحضر',
    'Written up'                       => 'مكتوب',
    'Documented rate'                  => 'نسبة التوثيق',
    'Held, but not written up'         => 'انعقدت ولم تُكتب محاضرها',
    'Every meeting held has been written up.' => 'كل اجتماع انعقد تمت كتابة محضره.',
    'No meetings have been held in the last 90 days.' => 'لم ينعقد أي اجتماع خلال آخر 90 يوماً.',
    'Nothing scheduled in the next 14 days.' => 'لا شيء مجدول خلال الـ14 يوماً القادمة.',
    'Coming up'                        => 'القادم',
    'next 14 days'                     => 'الـ14 يوماً القادمة',
    'last 90 days'                     => 'آخر 90 يوماً',
    'meetings already held'            => 'اجتماعات انعقدت بالفعل',
    'All meetings'                     => 'كل الاجتماعات',
    'Schedule a meeting'               => 'جدولة اجتماع',
    'Write up'                         => 'اكتب المحضر',
    'Write them up'                    => 'اكتب محاضرها',
    'Last written by'                  => 'آخر من كتبه',
    'Agenda note from the organiser'   => 'ملاحظة جدول الأعمال من المنظِّم',
    'What was discussed and what was agreed. Decisions with an owner go below, not here.'
        => 'ما نوقش وما اتُّفق عليه. القرارات التي لها مسؤول تُكتب بالأسفل لا هنا.',
    'Writing minutes marks the meeting as held automatically.'
        => 'كتابة المحضر تعلّم الاجتماع كمنعقد تلقائياً.',
    'Unticked means absent once you save; before the first save nobody is marked either way.'
        => 'غير المؤشَّر يعني غائباً بمجرد الحفظ؛ قبل أول حفظ لا أحد مُعلَّم بشيء.',
    'meetings were held with no minutes written.' => 'اجتماعات انعقدت بلا محاضر مكتوبة.',
    'A decision nobody wrote down is a commitment nobody owes.'
        => 'القرار الذي لم يكتبه أحد التزام لا يترتب على أحد.',

    // القرارات
    'Decisions'                        => 'القرارات',
    'Add decision'                     => 'إضافة قرار',
    'What was decided'                 => 'ما الذي تقرر',
    'Decision recorded as a commitment.' => 'تم تسجيل القرار كالتزام.',
    'No decisions recorded from this meeting yet.' => 'لم تُسجَّل قرارات من هذا الاجتماع بعد.',
    'Each one becomes a tracked commitment with an owner and a date.'
        => 'كل قرار يصبح التزاماً متابَعاً له مسؤول وموعد.',
    'Came out of the meeting'          => 'نتج عن اجتماع',
    'This commitment comes from the meeting' => 'هذا الالتزام ناتج عن اجتماع',
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

// The marker is a note, not data. Adding it on every run leaves a duplicate behind each
// time the script is re-run with new keys — which happened twice with the quality one.
$marker = '_commitments_module_ar_2026_09_13';
$block  = [''];

if (!isset($have[$marker])) {
    $block[] = '    "' . $marker . '": "Arabic for commitments (tasks 2 and 3) and meeting minutes (task 4)",';
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
