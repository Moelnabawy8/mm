<?php

/**
 * fix_role_permission_screen — stop the roles screen from silently deleting permissions.
 *
 * The bug, in order:
 *
 *   1. role/edit.blade.php draws a checkbox only for permissions named "<verb> <module>"
 *      where the verb AND the module are both written into the blade by hand. Six lists,
 *      about 140 modules. Anything else gets no checkbox.
 *
 *   2. RoleController@update revokes EVERY permission from the role and then re-grants
 *      only the ids the form posted.
 *
 *   (1) + (2) = pressing Update on any role deletes every permission the screen cannot
 *   draw. The eight permissions added for the September tasks are all in that set:
 *
 *      manage quality criteria   review task quality      view quality report
 *      manage commitment         create commitment        record meeting minutes
 *      use ai assistant          manage ai assistant
 *
 *   which is why they could be granted by migration and then produce "Permission Denied"
 *   after somebody opened the roles screen, ticked everything they could see, and saved.
 *
 * The fix adds one more tab, "Other", rendered by a new partial that lists whatever the
 * six tabs missed. It needs no maintenance — a permission added next year appears there
 * on its own — and it makes the revoke-then-regrant harmless, because the form now
 * carries every permission the role is allowed to have.
 *
 * edit.blade.php and create.blade.php are shared files, so every change is an exact
 * anchored insertion and the script refuses to run when an anchor is missing or appears
 * more than once.
 *
 *   php _fix/fix_role_permission_screen.php --dry-run
 *   php _fix/fix_role_permission_screen.php
 */

$dry  = in_array('--dry-run', $argv, true);
$root = __DIR__ . '/..';

$navItem = <<<'A'
                <li class="nav-item">
                    <a class="nav-link" id="pills-other-tab" data-bs-toggle="pill" href="#other" role="tab" aria-selected="false">{{__('Other')}}</a>
                </li>
A;

// The two files differ only by a blank line, so each gets its own anchor rather than a
// loose pattern that could match twice.
$navAnchorEdit   = "                @endif

            </ul>";
$navAnchorCreate = "                @endif
            </ul>";

$navToEdit   = "                @endif
" . $navItem . "

            </ul>";
$navToCreate = "                @endif
" . $navItem . "
            </ul>";

$paneTail = "            </div>
        </div>

    </div>
</div>
";

$paneAnchorEdit   = "                </div>
" . $paneTail . "<div class=\"modal-footer\">";
$paneAnchorCreate = "                </div>
" . $paneTail . "
<div class=\"modal-footer\">";

$paneFor = fn(string $checked) =>
      "                </div>
"
    . "                <div class=\"tab-pane fade\" id=\"other\" role=\"tabpanel\" aria-labelledby=\"pills-other-tab\">
"
    . "                    @include('role._extra_permissions', ['checkedState' => {$checked}])
"
    . "                </div>
";

$paneToEdit   = $paneFor('$role->permission') . $paneTail . "<div class=\"modal-footer\">";
$paneToCreate = $paneFor('false')             . $paneTail . "
<div class=\"modal-footer\">";

$jsAnchor = <<<'A'
        $(".ischeck").click(function(){
A;

$jsEdit = <<<'A'
        $("#other_checkall").click(function(){
            $('.other_checkall').not(this).prop('checked', this.checked);
        });
        $(".ischeck").click(function(){
A;

$patches = [
    [
        'file'  => $root . '/resources/views/role/edit.blade.php',
        'label' => 'edit.blade.php  — "Other" tab link',
        'probe' => 'pills-other-tab',
        'from'  => $navAnchorEdit,
        'to'    => $navToEdit,
    ],
    [
        'file'  => $root . '/resources/views/role/edit.blade.php',
        'label' => 'edit.blade.php  — "Other" tab pane',
        'probe' => "role._extra_permissions",
        'from'  => $paneAnchorEdit,
        'to'    => $paneToEdit,
    ],
    [
        'file'  => $root . '/resources/views/role/edit.blade.php',
        'label' => 'edit.blade.php  — check-all for the new tab',
        'probe' => 'other_checkall',
        'from'  => $jsAnchor,
        'to'    => $jsEdit,
        // the pane above already puts one "other_checkall" in the file
        'skipIfProbeCount' => 2,
    ],
    [
        'file'  => $root . '/resources/views/role/create.blade.php',
        'label' => 'create.blade.php — "Other" tab link',
        'probe' => 'pills-other-tab',
        'from'  => $navAnchorCreate,
        'to'    => $navToCreate,
    ],
    [
        'file'  => $root . '/resources/views/role/create.blade.php',
        'label' => 'create.blade.php — "Other" tab pane',
        'probe' => "role._extra_permissions",
        'from'  => $paneAnchorCreate,
        'to'    => $paneToCreate,
    ],
    [
        'file'  => $root . '/resources/views/role/create.blade.php',
        'label' => 'create.blade.php — check-all for the new tab',
        'probe' => 'other_checkall',
        'from'  => $jsAnchor,
        'to'    => $jsEdit,
        'skipIfProbeCount' => 2,
    ],
];

$partial = $root . '/resources/views/role/_extra_permissions.blade.php';

if (!is_file($partial)) {
    fwrite(STDERR, "  MISSING resources/views/role/_extra_permissions.blade.php — upload it first.\n");
    exit(1);
}

echo "  partial present  role/_extra_permissions.blade.php (" . filesize($partial) . " bytes)\n\n";

$applied = 0;

foreach ($patches as $p) {
    if (!is_file($p['file'])) {
        fwrite(STDERR, "  MISSING {$p['file']} — refusing.\n");
        exit(1);
    }

    $raw = file_get_contents($p['file']);
    $eol = strpos($raw, "\r\n") !== false ? "\r\n" : "\n";
    $src = str_replace("\r\n", "\n", $raw);

    if (substr_count($src, $p['probe']) >= ($p['skipIfProbeCount'] ?? 1)) {
        echo "  already present  {$p['label']}\n";
        continue;
    }

    $hits = substr_count($src, $p['from']);

    if ($hits !== 1) {
        fwrite(STDERR, "  ANCHOR {$hits}x (need exactly 1) for {$p['label']} — refusing, nothing written.\n");
        exit(1);
    }

    $src = str_replace($p['from'], $p['to'], $src);

    echo "  patched          {$p['label']}\n";
    $applied++;

    if ($dry) {
        continue;
    }

    copy($p['file'], $p['file'] . '.bak-' . date('Ymd-His'));
    file_put_contents($p['file'], str_replace("\n", $eol, $src));
    clearstatcache();
}

/* ------------------------------------------------- does it still compile as blade? */

if (!$dry) {
    try {
        require $root . '/vendor/autoload.php';
        $app = require_once $root . '/bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        foreach (['role/edit', 'role/create', 'role/_extra_permissions'] as $v) {
            $file = $root . '/resources/views/' . $v . '.blade.php';
            $php  = app('blade.compiler')->compileString(file_get_contents($file));
            $tmp  = sys_get_temp_dir() . '/rp_' . getmypid() . '_' . mt_rand() . '.php';

            file_put_contents($tmp, $php);
            exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $out, $rc);
            unlink($tmp);
            $out = [];

            printf("  %-34s %s\n", $v, $rc === 0 ? 'compiles' : 'DOES NOT COMPILE');

            if ($rc !== 0) {
                fwrite(STDERR, "  ^ restore from the .bak-* file beside it.\n");
                exit(1);
            }
        }
    } catch (\Throwable $e) {
        echo "  blade compile check SKIPPED: " . explode("\n", $e->getMessage())[0] . "\n";
    }
}

echo "\n";

if (!$applied) {
    echo "Nothing to do.\n";
    exit(0);
}

echo $dry
    ? "dry run — nothing written.\n"
    : "Now run:\n  php artisan view:clear\n";
