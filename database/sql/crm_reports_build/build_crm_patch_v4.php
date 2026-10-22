<?php
// Builds database/sql/apply_crm_reports_v4.php on top of v3 (applied on prod).
//   php database/sql/crm_reports_build/build_crm_patch_v4.php
//
// - CRM Reports files that changed since v3 are replaced, and only when the server
//   has exactly what v3 wrote (their hashes come out of apply_crm_reports_v3.php).
// - Shared files get anchored edits: each find must be there the stated number of
//   times, and `done` (text only the edited file has) makes a rerun a no-op.
// - The WhatsApp partial is a new file.
chdir(dirname(__DIR__, 3));
$lf = fn ($s) => str_replace("\r\n", "\n", $s);
$b64 = fn ($s) => base64_encode($lf($s));

$v3 = file_get_contents('database/sql/apply_crm_reports_v3.php');
preg_match('/\$replace = (array \(.*?\n\));\n/s', $v3, $m);
$v3Files = eval('return ' . $m[1] . ';');

$replace = [];
foreach ($v3Files as $rel => $f) {
    $now = $lf(file_get_contents($rel));
    if (hash('sha256', $now) !== $f['newHash']) {
        $replace[$rel] = ['expected' => [$f['newHash']], 'new' => base64_encode($now), 'newHash' => hash('sha256', $now)];
    }
}

$create = [];
foreach (['resources/views/partials/admin/whatsapp_links.blade.php'] as $rel) {
    $now = $lf(file_get_contents($rel));
    $create[$rel] = ['new' => base64_encode($now), 'newHash' => hash('sha256', $now)];
}

$edit = fn ($path, $label, $find, $replace, $done, $times = 1, $optional = false, $regex = false) => [
    'path' => $path, 'label' => $label, 'find' => $regex ? base64_encode($find) : $b64($find), 'replace' => $b64($replace),
    'done' => $lf($done), 'times' => $times, 'optional' => $optional, 'regex' => $regex,
];

// The new filter block exactly as the local view has it.
$view = $lf(file_get_contents('resources/views/leads/index.blade.php'));
$a = strpos($view, '<label for="filter_deal_status"');
$selectBlock = substr($view, $a, strpos($view, '</select>', $a) + strlen('</select>') - $a);

$edits = [
    $edit('app/Services/LeadScope.php', 'deal status filter',
        "        if (\$request->stage) {\n            \$query->where('leads.stage_id', \$request->stage);\n        }\n",
        <<<'TXT'
        // By the status of the lead's deals: any deal with that status, or no deal at all.
        if ($request->deal_status === self::NO_DEAL) {
            $query->whereNotExists(function ($s) {
                $s->select(\DB::raw(1))->from('deals as dsf')->whereColumn('dsf.lead_id', 'leads.id');
            });
        } elseif ($request->deal_status) {
            $query->whereExists(function ($s) use ($request) {
                $s->select(\DB::raw(1))->from('deals as dsf')->whereColumn('dsf.lead_id', 'leads.id')
                    ->where('dsf.status', $request->deal_status);
            });
        }

TXT,
        'if ($request->deal_status === self::NO_DEAL)'),
    $edit('app/Services/LeadScope.php', 'deal status list',
        "    public static function applyFilters(\$query, \$request, \$usr = null)\n",
        <<<'TXT'
    /** The deal-status filter value for leads without any deal. */
    const NO_DEAL = 'none';

    /** The deal statuses the company's deals use, most used first, for the filter. */
    public static function dealStatuses(int $companyId): array
    {
        return \DB::table('deals')->where('created_by', $companyId)->whereNotNull('status')->where('status', '<>', '')
            ->groupBy('status')->orderByRaw('COUNT(*) DESC')->pluck('status')->all();
    }

    public static function applyFilters($query, $request, $usr = null)

TXT,
        "const NO_DEAL = 'none';"),
    $edit('app/Http/Controllers/LeadController.php', 'deal statuses for the filter',
        "            \$stages = LeadStage::where('pipeline_id', '=', \$pipeline->id)->where('created_by', \\Auth::user()->creatorId())->get()->pluck('name', 'id');\n",
        "            \$stages = LeadStage::where('pipeline_id', '=', \$pipeline->id)->where('created_by', \\Auth::user()->creatorId())->get()->pluck('name', 'id');\n"
        . "            \$dealStatuses = \\App\\Services\\LeadScope::dealStatuses(\\Auth::user()->creatorId());\n",
        '$dealStatuses = \App\Services\LeadScope::dealStatuses('),
    $edit('app/Http/Controllers/LeadController.php', 'pass them to the view',
        "'countries', 'leads', 'campaigns'));",
        "'countries', 'leads', 'campaigns', 'dealStatuses'));",
        "'campaigns', 'dealStatuses'));"),
    $edit('resources/views/leads/index.blade.php', 'filter and export send deal_status',
        "stage: \$('#filter_stage').val(),",
        "deal_status: \$('#filter_deal_status').val(),",
        "deal_status: \$('#filter_deal_status').val(),", 2),
    $edit('resources/views/leads/index.blade.php', 'saved filter restores deal_status',
        "                    if (_sf.name) \$('#filter_name').val(_sf.name);\n",
        "                    if (_sf.deal_status) \$('#filter_deal_status').val(_sf.deal_status);\n"
        . "                    if (_sf.name) \$('#filter_name').val(_sf.name);\n",
        'if (_sf.deal_status)', 1, true),
    $edit('resources/views/leads/index.blade.php', 'saved filter no longer sets a stage',
        "                        getStages(_sf.pipeline, function() {\n                            if (_sf.stage) \$('#filter_stage').val(_sf.stage);\n                        });",
        "                        getStages(_sf.pipeline);",
        "                        getStages(_sf.pipeline);\n", 1, true),
    // The Stage <select> differs between versions of this view (select2 class, a comment),
    // so match from its label to its closing tag.
    $edit('resources/views/leads/index.blade.php', 'Stage filter becomes Deal status',
        '~<label for="filter_stage" class="form-label">\{\{ __\(\'Stage\'\) \}\}</label>.*?</select>~s',
        $selectBlock,
        'id="filter_deal_status" name="deal_status"', 1, false, true),
    $edit('resources/views/partials/admin/footer.blade.php', 'WhatsApp links partial',
        "<script src=\"{{ asset('js/custom.js') }}\"></script>\n",
        "<script src=\"{{ asset('js/custom.js') }}\"></script>\n@include('partials.admin.whatsapp_links')\n",
        "@include('partials.admin.whatsapp_links')"),
];

$append = json_decode(file_get_contents(__DIR__ . '/ar_crm_reports_v4.json'), true);

$out = strtr(file_get_contents(__DIR__ . '/crm_template_v4.php'), [
    '__REPLACE__' => var_export($replace, true),
    '__CREATE__'  => var_export($create, true),
    '__EDITS__'   => var_export($edits, true),
    '__APPEND__'  => var_export($append, true),
]);
file_put_contents('database/sql/apply_crm_reports_v4.php', $out);
echo 'wrote database/sql/apply_crm_reports_v4.php: ' . count($replace) . ' replaced, ' . count($create) . ' new, '
    . count($edits) . ' edits, ' . count($append) . ' translations, ' . strlen($out) . " bytes\n";
foreach (array_keys($replace) as $r) {
    echo "  replace $r\n";
}
