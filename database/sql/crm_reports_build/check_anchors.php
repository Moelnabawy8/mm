<?php
// For each edit: does the CURRENT local file contain the replacement (i.e. the edit
// reproduces what I did), and does each BASELINE (git HEAD, pulled_prod) contain the
// anchor exactly once (i.e. the patch would apply to a server copy without my edits)?
chdir('C:/xampp/htdocs/mm');
$edits = require __DIR__ . '/crm_edits.php';
$lf = fn ($s) => str_replace("\r\n", "\n", $s);
foreach ($edits as $e) {
    $find = $lf($e['find']);
    $repl = $lf($e['replace']);
    $cur = $lf(file_get_contents($e['path']));
    $head = $lf((string) shell_exec('git show HEAD:' . escapeshellarg($e['path']) . ' 2>NUL'));
    $prodPath = 'pulled_prod/' . $e['path'];
    $prod = is_file($prodPath) ? $lf(file_get_contents($prodPath)) : null;
    printf("%-34s local:repl=%d  HEAD:find=%s repl=%s  prod:%s%s\n",
        $e['label'],
        substr_count($cur, $repl),
        $head === '' ? '-' : substr_count($head, $find),
        $head === '' ? '-' : substr_count($head, $repl),
        $prod === null ? 'n/a' : 'find=' . substr_count($prod, $find),
        !empty($e['optional']) ? '  (optional)' : ''
    );
}
