<?php
/**
 * fix_setsalary_tenancy — scope SetSalaryController's employee lookups to the
 * caller's own company.
 *
 * Four actions load the employee with a bare Employee::find($id) / findOrFail($id).
 * The id comes straight off the URL and nothing ties it to the caller's tenant, so
 * anyone holding 'edit set salary' can read and write another company's payroll —
 * allowances, commissions, deductions, basic salary — by changing the number in the
 * address bar. Every other controller in this codebase scopes on created_by =
 * creatorId(); these four were missed.
 *
 * find() on a foreign id also returns null, and setsalary/edit.blade.php dereferences
 * $employee->id with no guard, so the two paths that lack a null check render a 500
 * rather than a message.
 *
 *   php _fix/fix_setsalary_tenancy.php --dry-run
 *   php _fix/fix_setsalary_tenancy.php
 */

$dry  = in_array('--dry-run', $argv, true);
$file = __DIR__ . '/../app/Http/Controllers/SetSalaryController.php';

if (!is_file($file)) {
    fwrite(STDERR, "SetSalaryController.php not found — run from the Laravel root.\n");
    exit(1);
}

$raw = file_get_contents($file);
$eol = strpos($raw, "\r\n") !== false ? "\r\n" : "\n";
$src = str_replace("\r\n", "\n", $raw);

$guard = <<<'G'

                if (empty($employee)) {
                    return redirect()->route('setsalary.index')->with('error', __('Employee not found.'));
                }
G;

$patches = [
    [
        'label' => 'edit()  — scope + null guard',
        'from'  => "                \$employee             = Employee::find(\$id);",
        'to'    => "                // Scope to the caller's own company - an unscoped find() exposed\n"
                 . "                // other tenants' payroll to anyone holding 'edit set salary'.\n"
                 . "                \$employee             = Employee::where('created_by', \Auth::user()->creatorId())->find(\$id);",
    ],
    [
        'label' => 'show()  — scope + null guard',
        'from'  => "            \$employee             = Employee::with('salaryType')->find(\$id);",
        'to'    => "            \$employee             = Employee::where('created_by', \Auth::user()->creatorId())->with('salaryType')->find(\$id);\n"
                 . "\n"
                 . "            if (empty(\$employee)) {\n"
                 . "                return redirect()->route('setsalary.index')->with('error', __('Employee not found.'));\n"
                 . "            }",
    ],
    [
        'label' => 'update() — scope replaces findOrFail',
        'from'  => "        \$employee = Employee::findOrFail(\$id);",
        'to'    => "        \$employee = Employee::where('created_by', \Auth::user()->creatorId())->find(\$id);\n"
                 . "\n"
                 . "        if (empty(\$employee)) {\n"
                 . "            return redirect()->route('setsalary.index')->with('error', __('Employee not found.'));\n"
                 . "        }\n",
    ],
    [
        'label' => 'basicSalary() — scope + null guard',
        'from'  => "        \$employee     = Employee::find(\$id);",
        'to'    => "        \$employee     = Employee::where('created_by', \Auth::user()->creatorId())->find(\$id);\n"
                 . "\n"
                 . "        if (empty(\$employee)) {\n"
                 . "            return redirect()->route('setsalary.index')->with('error', __('Employee not found.'));\n"
                 . "        }\n",
    ],
];

$applied = 0;

foreach ($patches as $p) {
    if (strpos($src, "Employee::where('created_by', \Auth::user()->creatorId())") !== false
        && strpos($src, $p['from']) === false) {
        echo "  already fixed    {$p['label']}\n";
        continue;
    }

    $hits = substr_count($src, $p['from']);
    if ($hits !== 1) {
        fwrite(STDERR, "  ANCHOR {$hits}x (need exactly 1) for {$p['label']} — refusing.\n");
        exit(1);
    }

    $src = str_replace($p['from'], $p['to'], $src);
    echo "  patched          {$p['label']}\n";
    $applied++;
}

if (!$applied) {
    echo "\nNothing to do.\n";
    exit(0);
}

if ($dry) {
    echo "\n  dry run — nothing written.\n";
    exit(0);
}

$tmp = sys_get_temp_dir() . '/ssc_' . getmypid() . '.php';
file_put_contents($tmp, str_replace("\n", $eol, $src));
exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $out, $rc);
if ($rc !== 0) {
    unlink($tmp);
    fwrite(STDERR, "  PHP SYNTAX ERROR — nothing written:\n  " . implode("\n  ", $out) . "\n");
    exit(1);
}
unlink($tmp);

$bak = $file . '.bak-' . date('Ymd-His');
copy($file, $bak);
file_put_contents($file, str_replace("\n", $eol, $src));

echo "\n  backup  " . basename($bak) . "\n  written app/Http/Controllers/SetSalaryController.php\n";
