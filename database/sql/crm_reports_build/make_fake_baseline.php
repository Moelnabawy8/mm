<?php
// Turn the fake server tree back into "production before this patch":
// remove the new files, reverse the anchored edits, drop the appended translations.
$S = __DIR__;
$F = "$S/fakeserver";
$lf = fn ($s) => str_replace("\r\n", "\n", $s);

$script = file_get_contents('C:/xampp/htdocs/mm/database/sql/apply_crm_reports_v1.php');
preg_match('/\$newFiles = (array \(.*?\n\));\n\$translations/s', $script, $m);
$newFiles = eval('return ' . $m[1] . ';');
foreach (array_keys($newFiles) as $rel) {
    @unlink("$F/$rel");
}
foreach (["$F/resources/views/crm_reports/employee", "$F/resources/views/crm_reports", "$F/app/Services/CrmReports"] as $d) {
    @rmdir($d);
}

foreach (require "$S/crm_edits.php" as $e) {
    $p = "$F/{$e['path']}";
    $c = $lf(file_get_contents($p));
    $c = str_replace($lf($e['replace']), $lf($e['find']), $c);
    file_put_contents($p, $c);
}

// ar.json: cut the appended block (everything after the last pre-existing key).
$ar = "$F/resources/lang/ar.json";
$c = $lf(file_get_contents($ar));
$anchor = '    "Answers from your own ERP data.": "يجيب من بيانات نظامك نفسه.",';
$pos = strpos($c, $anchor);
$c = substr($c, 0, $pos) . rtrim($anchor, ',') . "\n}\n";
file_put_contents($ar, $c);
echo json_decode($c, true) === null ? "ar.json INVALID\n" : "ar.json ok, " . count(json_decode($c, true)) . " keys\n";
echo "baseline ready: " . count($newFiles) . " files removed\n";
