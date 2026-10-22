<?php
// Lists __('...') keys in the CRM report files that neither ar.json nor the patch's
// translation file has, and patch keys nothing uses any more.
//     php database/sql/crm_reports_build/check_translations.php
chdir(dirname(__DIR__, 3));
$files = array_merge(glob('resources/views/crm_reports/*.blade.php'), glob('resources/views/crm_reports/employee/*.blade.php'), [
    'app/Services/CrmReports/EmployeeReport.php', 'app/Http/Controllers/CrmReportController.php',
    'resources/views/sales_meetings/_status_field.blade.php',
]);
$ar = json_decode(file_get_contents('resources/lang/ar.json'), true);
$patch = json_decode(file_get_contents('database/sql/crm_reports_build/ar_crm_reports.json'), true);

// Character scanner: __( then a single-quoted PHP string.
$used = [];
foreach ($files as $f) {
    $src = file_get_contents($f);
    $pos = 0;
    while (($pos = strpos($src, '__(', $pos)) !== false) {
        $i = $pos + 3;
        while ($i < strlen($src) && ctype_space($src[$i])) {
            $i++;
        }
        $pos = $i;
        if (($src[$i] ?? '') !== "'") {
            continue;
        }
        $i++;
        $s = '';
        while ($i < strlen($src) && $src[$i] !== "'") {
            if ($src[$i] === '\\' && in_array($src[$i + 1] ?? '', ["'", '\\'], true)) {
                $i++;
            }
            $s .= $src[$i++];
        }
        $used[$s] = $f;
    }
}
// Sections and tab titles are translated from the controller's constants.
foreach (['Leads per employee', 'Actions & conversion', 'Lead stages', 'Neglected leads', 'Activity log', 'Meetings', 'Quick comparison', 'Detailed comparison'] as $k) {
    $used[$k] = 'CrmReportController::EMPLOYEE_SECTIONS';
}
echo "used: " . count($used) . "\nmissing:\n";
foreach (array_diff_key($used, $ar, $patch) as $k => $f) {
    echo "  $k\n";
}
echo "in patch but no longer used:\n";
foreach (array_diff_key($patch, $used) as $k => $v) {
    echo "  $k\n";
}
