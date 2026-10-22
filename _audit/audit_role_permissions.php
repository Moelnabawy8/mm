<?php

/**
 * audit_role_permissions — READ ONLY. Writes nothing, changes nothing.
 *
 * Answers one question: which roles still hold the eight permissions added for the
 * September tasks, and which permissions can the roles screen not draw at all?
 *
 *   php _audit/audit_role_permissions.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$NEW = [
    'manage quality criteria',
    'review task quality',
    'view quality report',
    'manage commitment',
    'create commitment',
    'record meeting minutes',
    'use ai assistant',
    'manage ai assistant',
];

$line = str_repeat('=', 78);

echo "\n{$line}\n  ROLE / PERMISSION AUDIT  " . date('Y-m-d H:i') . "\n{$line}\n";

/* ------------------------------------------------------------ 1. do they exist? */

echo "\n1. THE EIGHT NEW PERMISSIONS\n\n";

$perms = DB::table('permissions')->whereIn('name', $NEW)->pluck('id', 'name')->toArray();

foreach ($NEW as $n) {
    printf("   %-26s %s\n", $n, isset($perms[$n]) ? 'exists (id ' . $perms[$n] . ')' : 'MISSING FROM permissions TABLE');
}

$ids = array_values($perms);

if (!$ids) {
    echo "\n   None of them exist. Run: php artisan migrate --force\n";
    exit(0);
}

/* ------------------------------------------------------- 2. who holds them now? */

echo "\n2. WHICH ROLES HOLD THEM\n\n";

$held = DB::table('role_has_permissions')
    ->whereIn('permission_id', $ids)
    ->get()
    ->groupBy('role_id');

$roles = DB::table('roles')->orderBy('id')->get();
$users = DB::table('model_has_roles')
    ->select('role_id', DB::raw('COUNT(*) as n'))
    ->groupBy('role_id')
    ->pluck('n', 'role_id')
    ->toArray();

$byId = array_flip($perms);   // id => name

printf("   %-4s %-26s %-6s %s\n", 'id', 'role', 'users', 'holds');
echo '   ' . str_repeat('-', 72) . "\n";

$anyHolder = false;

foreach ($roles as $r) {
    $mine = $held[$r->id] ?? collect();
    $names = $mine->map(fn($x) => $byId[$x->permission_id] ?? '?')->sort()->values()->all();

    if (!$names && ($users[$r->id] ?? 0) === 0) {
        continue;   // an empty role that holds none of them tells us nothing
    }

    if ($names) {
        $anyHolder = true;
    }

    printf("   %-4d %-26s %-6d %s\n", $r->id, mb_strimwidth($r->name, 0, 26),
        $users[$r->id] ?? 0,
        $names ? count($names) . '/8' : '— none —');

    foreach ($names as $n) {
        echo "        · {$n}\n";
    }
}

if (!$anyHolder) {
    echo "\n   NO ROLE HOLDS ANY OF THEM. They were granted by migration and then wiped.\n";
}

/* ----------------------------------------------- 3. what the screen cannot draw */

echo "\n3. PERMISSIONS THE ROLES SCREEN CANNOT DRAW A CHECKBOX FOR\n\n";
echo "   Every one of these is deleted from a role the moment somebody presses Update\n";
echo "   on that role, because the form cannot carry it and update() revokes first.\n\n";

$modules = [
    'user','role','client','product & service','constant unit','constant tax','constant category','zoom meeting','company settings','language','permission',
    'crm dashboard','lead','convert','pipeline','lead stage','source','label','lead email','lead call','deal','stage','task','form builder','form response','form field','contract','contract type','support request','quality evaluation','reports dashboard','sod','eod',
    'project dashboard','project','milestone','grant chart','project stage','timesheet','project expense','project task','activity','CRM activity','project task stage','bug report','bug status',
    'hrm dashboard','employee','employee profile','department','designation','branch','document type','document','payslip type','allowance','commission','allowance option','loan option','deduction option','loan','saturation deduction','other payment','overtime','set salary','pay slip','company policy','appraisal','goal tracking','goal type','indicator','event','meeting','training','trainer','training type','award','award type','resignation','travel','promotion','complaint','warning','termination','termination type','job application','job application note','job onBoard','job category','job','job stage','custom question','interview schedule','career','estimation','holiday','transfer','announcement','leave','leave type','attendance',
    'account dashboard','proposal','invoice','bill','revenue','payment','proposal product','invoice product','bill product','goal','credit note','debit note','bank account','bank transfer','transaction','customer','vender','constant custom field','assets','chart of account','journal entry','report',
    'pos dashboard','warehouse','quotation','purchase','pos','barcode',
];

$verbs = [
    'view','add','move','manage','create','edit','delete','show','send','copy','duplicate',
    'reply','convert','create payment','delete payment','invoice','bill','expense','income',
    'tax','ledger','balance sheet','trial balance','loss & profit','income vs expense',
];

$covered = [];

foreach ($modules as $m) {
    foreach ($verbs as $v) {
        $covered[$v . ' ' . $m] = true;
    }

    $covered[$m . ' lead to deal'] = true;   // the CRM "Convert" row
}

$all     = DB::table('permissions')->orderBy('name')->pluck('name')->toArray();
$orphans = array_values(array_filter($all, fn($n) => !isset($covered[$n])));

printf("   %d permissions total, %d of them undrawable:\n\n", count($all), count($orphans));

foreach ($orphans as $n) {
    $isNew = in_array($n, $NEW, true);
    printf("     %s %s\n", $isNew ? '>>' : '  ', $n);
}

echo "\n   (>> = added for the September tasks)\n";

/* ------------------------------------------------------------ 4. who is affected */

echo "\n4. THE ACCOUNT YOU ARE TESTING WITH\n\n";
echo "   Run this with your own e-mail to see exactly what that login can do:\n\n";
echo "     php _audit/audit_role_permissions.php --user=someone@example.com\n";

foreach ($argv as $a) {
    if (strpos($a, '--user=') !== 0) {
        continue;
    }

    $email = substr($a, 7);
    $u     = \App\Models\User::where('email', $email)->first();

    if (!$u) {
        echo "\n   No user with e-mail {$email}\n";
        break;
    }

    echo "\n   {$u->name} <{$u->email}>  type={$u->type}  roles=" . $u->roles->pluck('name')->implode(', ') . "\n\n";

    foreach ($NEW as $n) {
        printf("     %-26s %s\n", $n, $u->can($n) ? 'YES' : 'no');
    }
}

echo "\n{$line}\n  read-only — nothing was changed\n{$line}\n\n";
