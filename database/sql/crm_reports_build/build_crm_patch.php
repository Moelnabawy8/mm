<?php
// Builds database/sql/apply_crm_reports_v1.php from crm_template.php.
chdir('C:/xampp/htdocs/mm');
$S = __DIR__;  // database/sql/crm_reports_build
$lf = fn ($s) => str_replace("\r\n", "\n", $s);

$newFiles = array_merge(
    [
        'app/Http/Controllers/CrmReportController.php',
        'app/Http/Controllers/LeadCallController.php',
        'app/Services/CrmReports/ReportContext.php',
        'app/Services/CrmReports/EmployeeReport.php',
        'app/Exports/CrmReportExport.php',
        'app/Providers/CrmReportsServiceProvider.php',
        'database/migrations/2026_10_08_100000_add_crm_reports_permissions.php',
        'database/migrations/2026_10_08_100001_add_report_fields_to_crm_tables.php',
        'database/migrations/2026_10_08_100002_add_crm_report_indexes.php',
        'resources/views/leads/call_form.blade.php',
        'resources/views/leads/_calls_section.blade.php',
        'resources/views/sales_meetings/_status_field.blade.php',
    ],
    array_map(fn ($p) => str_replace('\\', '/', $p), glob('resources/views/crm_reports/*.blade.php')),
    array_map(fn ($p) => str_replace('\\', '/', $p), glob('resources/views/crm_reports/employee/*.blade.php'))
);
$files = [];
foreach ($newFiles as $rel) {
    if (!is_file($rel)) {
        exit("missing $rel\n");
    }
    $files[$rel] = base64_encode($lf(file_get_contents($rel)));
}

$edits = [];
foreach (require "$S/crm_edits.php" as $e) {
    $edits[] = ['path' => $e['path'], 'label' => $e['label'], 'optional' => !empty($e['optional']),
        'find' => base64_encode($lf($e['find'])), 'replace' => base64_encode($lf($e['replace']))];
}

$translations = json_decode(file_get_contents("$S/ar_crm_reports.json"), true);

$conflicts = [
    ['path' => 'routes/web.php', 'needle' => "->name('leads.calls.store')"],
    ['path' => 'routes/web.php', 'needle' => "name('crm-reports.')"],
    ['path' => 'resources/views/partials/admin/menu.blade.php', 'needle' => "@can('show crm reports')"],
];

$classes = [
    'App\\Http\\Controllers\\CrmReportController',
    'App\\Http\\Controllers\\LeadCallController',
    'App\\Services\\CrmReports\\ReportContext',
    'App\\Services\\CrmReports\\EmployeeReport',
    'App\\Exports\\CrmReportExport',
    'App\\Providers\\CrmReportsServiceProvider',
];

$migrations = [
    'database/migrations/2026_10_08_100000_add_crm_reports_permissions.php',
    'database/migrations/2026_10_08_100001_add_report_fields_to_crm_tables.php',
    'database/migrations/2026_10_08_100002_add_crm_report_indexes.php',
];

$tpl = file_get_contents("$S/crm_template.php");
$out = strtr($tpl, [
    '__EDITS__'        => var_export($edits, true),
    '__NEWFILES__'     => var_export($files, true),
    '__TRANSLATIONS__' => var_export($translations, true),
    '__CONFLICTS__'    => var_export($conflicts, true),
    '__CLASSES__'      => var_export($classes, true),
    '__MIGRATIONS__'   => var_export($migrations, true),
]);
file_put_contents('database/sql/apply_crm_reports_v1.php', $out);
echo "wrote database/sql/apply_crm_reports_v1.php: " . count($files) . " new files, " . count($edits) . " edits, "
    . count($translations) . " translations, " . strlen($out) . " bytes\n";
