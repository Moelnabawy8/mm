<?php
// Builds database/sql/apply_crm_reports_v3.php: the CRM Reports follow-up on top of v1.
//   php database/sql/crm_reports_build/build_crm_patch_v3.php
//
// What v3 does on the server:
//  - replaces every CRM Reports file that changed since v1 (collation fix, "action" =
//    sales meeting or deal, meeting types Call / Presentation, Leads tab trimmed,
//    Activity log instead of Calls & messages, two KPIs dropped);
//  - deletes the call-logging files v1 added, and takes its two edits back out of
//    routes/web.php and leads/show.blade.php;
//  - rewords one translation line and appends the new ones to ar.json.
// A file is only replaced or deleted when it is byte-identical to a version we
// shipped; a shared file is only edited when v1's exact text is there.
chdir(dirname(__DIR__, 3));
$lf = fn ($s) => str_replace("\r\n", "\n", $s);
$enc = fn ($k, $v) => '    ' . json_encode($k, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ': '
    . json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$v1 = file_get_contents('database/sql/apply_crm_reports_v1.php');
$extract = function ($var) use ($v1) {
    preg_match('/\$' . $var . ' = (array \(.*?\n\));\n/s', $v1, $m);

    return eval('return ' . $m[1] . ';');
};
$v1Files = $extract('newFiles');
$v1Translations = $extract('translations');

// Other versions we shipped: the hand-uploaded collation fix of EmployeeReport, and
// the three files the first v2 (Leads-tab trim, applied on prod 2026-10-18) wrote.
// Those three were rebuilt from v1 + the same edits and matched the hashes prod reported.
$alsoShipped = [
    'app/Services/CrmReports/EmployeeReport.php' => [
        '40dfd88e9d0c2bc86bcf3bde5641d143d9d0d561d1a781d464aa87e5a54705a1',
        '71ecb46e61e23439d58119670b14c6f62fe6e0cd7c63ec27e8221f83f569e166',
    ],
    'app/Http/Controllers/CrmReportController.php'   => ['2813f272d1ccdb5d16c4fab60039069488bee4cd0986f9bce8d980a4e9e7d5d6'],
    'resources/views/crm_reports/employee.blade.php' => ['146d500c0ade6d6ee8b595ba5ef4edae503a272691d65389e435b9d8fe715289'],
];

$replace = [];
$delete = [];
foreach ($v1Files as $rel => $b64) {
    $expected = array_merge([hash('sha256', base64_decode($b64))], $alsoShipped[$rel] ?? []);
    if (!is_file($rel)) {
        $delete[$rel] = ['expected' => $expected];
        continue;
    }
    $new = $lf(file_get_contents($rel));
    if ($new !== base64_decode($b64)) {
        $replace[$rel] = ['expected' => $expected, 'new' => base64_encode($new), 'newHash' => hash('sha256', $new)];
    }
}

// v1's two call-logging edits, taken back out (find = what v1 wrote, replace = what was there).
$edits = [];
foreach (require __DIR__ . '/crm_edits.php' as $e) {
    if (in_array($e['label'], ['lead call routes', 'Calls section'], true)) {
        $edits[] = ['path' => $e['path'], 'label' => 'remove ' . $e['label'],
            'find' => base64_encode($lf($e['replace'])), 'replace' => base64_encode($lf($e['find']))];
    }
}

$old = 'Leads created in the period, credited to the employees they are assigned to. A lead assigned to two employees counts for both, so the rows can add up to more than the total. Change compares with the previous period of the same length.';
$newKey = 'Leads created in the period, credited to the employees they are assigned to. A lead assigned to two employees counts for both, so the rows can add up to more than the total. Previous period = the same number of days right before the chosen period.';
$patch = json_decode(file_get_contents(__DIR__ . '/ar_crm_reports.json'), true);
$lang = [
    'swapFind'    => $enc($old, $v1Translations[$old]),
    'swapReplace' => $enc($newKey, $patch[$newKey]),
    'append'      => json_decode(file_get_contents(__DIR__ . '/ar_crm_reports_v3.json'), true),
];

$tpl = file_get_contents(__DIR__ . '/crm_template_v3.php');
$out = strtr($tpl, [
    '__REPLACE__' => var_export($replace, true),
    '__DELETE__'  => var_export($delete, true),
    '__EDITS__'   => var_export($edits, true),
    '__LANG__'    => var_export($lang, true),
]);
file_put_contents('database/sql/apply_crm_reports_v3.php', $out);
echo 'wrote database/sql/apply_crm_reports_v3.php: ' . count($replace) . ' replaced, ' . count($delete) . ' deleted, '
    . count($edits) . ' edits, ' . count($lang['append']) . " translations appended, " . strlen($out) . " bytes\n";
foreach (array_keys($replace) as $r) {
    echo "  replace $r\n";
}
foreach (array_keys($delete) as $r) {
    echo "  delete  $r\n";
}
