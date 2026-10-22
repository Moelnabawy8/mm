<?php
/**
 * apply_deals_perf_patch.php
 *
 * Fixes the /deals list page. It rendered EVERY lead of the company as <option>
 * elements inside a Choices.js-initialised <select>, which froze the browser on
 * page load, and it never passed $productsMap to the view, so the Products
 * column was always empty.
 *
 * Run from the Laravel root:   php apply_deals_perf_patch.php
 *
 * - Exact-anchor replacements only. A missing or ambiguous anchor aborts that
 *   single patch and reports it; nothing is half-written.
 * - Writes <file>.bak-deals-perf next to every file it changes (once).
 * - Idempotent: a patch already applied is skipped.
 */

$root = __DIR__;
while (!file_exists($root . '/artisan') && dirname($root) !== $root) {
    $root = dirname($root);
}
if (!file_exists($root . '/artisan')) {
    fwrite(STDERR, "Could not locate the Laravel root (no artisan found).\n");
    exit(1);
}

$patches = [];

/* ------------------------------------------------------------------ *
 * 1. DealController@index - lead visibility as a subquery            *
 * ------------------------------------------------------------------ */
$patches[] = [
    'file' => 'app/Http/Controllers/DealController.php',
    'name' => 'index: lead visibility subquery',
    'done' => 'visibleLeadIdSubQuery($usr)',
    'from' => <<<'FROM'
            // Filter deals by employee's assigned leads
            // Company, CSO users can see all deals
            // Sales Managers can only see deals for their sales_group
            $canViewAllDeals = (
                $usr->type == 'company' ||
                $usr->type == 'CSO' ||
                $usr->type == 'Alaa,Saba,aber' ||
                $usr->type == 'fadi' ||
                $usr->can('view all leads')
            );

            $isSalesManager = $usr->hasRole('Sales Manager') || $usr->hasRole('sales manager');

            if ($isSalesManager && $usr->sales_group) {
                // Sales Manager: only see deals from leads assigned to their group members
                $groupUserIds = User::where('created_by', $usr->creatorId())
                    ->where('sales_group', $usr->sales_group)
                    ->pluck('id')->toArray();

                $groupLeadIds = \DB::table('user_leads')
                    ->whereIn('user_id', $groupUserIds)
                    ->pluck('lead_id')
                    ->toArray();

                if (!empty($groupLeadIds)) {
                    $query->whereIn('lead_id', $groupLeadIds);
                } else {
                    $query->whereRaw('1 = 0');
                }
            } elseif ($isSalesManager && !$usr->sales_group) {
                // Sales Manager without group sees no deals
                $query->whereRaw('1 = 0');
            } elseif (!$canViewAllDeals) {
                // Get lead IDs assigned to this user
                $assignedLeadIds = \DB::table('user_leads')
                    ->where('user_id', $usr->id)
                    ->pluck('lead_id')
                    ->toArray();

                if (!empty($assignedLeadIds)) {
                    $query->whereIn('lead_id', $assignedLeadIds);
                } else {
                    // If no leads assigned, show no deals
                    $query->whereRaw('1 = 0');
                }
            }
FROM,
    'to' => <<<'TO'
            // Which leads this user may see, resolved as a SQL subquery instead of
            // pluck()->toArray(): on a big tenant the old code pulled every assigned
            // lead id into PHP and sent it back as a multi-thousand element IN (...).
            $isSalesManager = $usr->hasRole('Sales Manager') || $usr->hasRole('sales manager');
            $visibleLeadIds = $this->visibleLeadIdSubQuery($usr);

            if ($visibleLeadIds === false) {
                $query->whereRaw('1 = 0');
            } elseif ($visibleLeadIds !== null) {
                $query->whereIn('lead_id', $visibleLeadIds);
            }
TO,
];

/* ------------------------------------------------------------------ *
 * 2. DealController@index - user_id filter as a subquery              *
 * ------------------------------------------------------------------ */
$patches[] = [
    'file' => 'app/Http/Controllers/DealController.php',
    'name' => 'index: user_id filter subquery',
    'done' => '// Filter by user - subquery over user_leads',
    'from' => <<<'FROM'
            if ($request->user_id) {
                // Filter by user - get leads assigned to this user
                $userLeadIds = \DB::table('user_leads')
                    ->where('user_id', $request->user_id)
                    ->pluck('lead_id')
                    ->toArray();
                if (!empty($userLeadIds)) {
                    $query->whereIn('lead_id', $userLeadIds);
                } else {
                    $query->whereRaw('1 = 0');
                }
            }
FROM,
    'to' => <<<'TO'
            if ($request->user_id) {
                // Filter by user - subquery over user_leads
                $query->whereIn('lead_id', \DB::table('user_leads')
                    ->select('lead_id')
                    ->where('user_id', $request->user_id));
            }
TO,
];

/* ------------------------------------------------------------------ *
 * 3. DealController@index - withCount instead of loading attachments  *
 * ------------------------------------------------------------------ */
$patches[] = [
    'file' => 'app/Http/Controllers/DealController.php',
    'name' => 'index: withCount(attachments)',
    'done' => "->withCount('attachments')",
    'from' => <<<'FROM'
            $deals = $query->with(['lead', 'lead.assignedUser', 'stage', 'attachments'])
                ->orderBy('id', 'desc')
FROM,
    'to' => <<<'TO'
            $deals = $query->with(['lead', 'lead.assignedUser', 'stage'])
                ->withCount('attachments')
                ->orderBy('id', 'desc')
TO,
];

/* ------------------------------------------------------------------ *
 * 4. DealController@index - drop the full lead list from the filters  *
 * ------------------------------------------------------------------ */
$patches[] = [
    'file' => 'app/Http/Controllers/DealController.php',
    'name' => 'index: replace full lead list with $selectedLead',
    'done' => '$selectedLead = null;',
    'from' => <<<'FROM'
            // Filter options for the view - only show leads assigned to this employee
            if ($isSalesManager && $usr->sales_group) {
                // Sales Manager sees leads of their group
                $groupLeadIdsForFilter = !empty($groupLeadIds) ? $groupLeadIds : [];
                if (!empty($groupLeadIdsForFilter)) {
                    $leads = Lead::where('created_by', $usr->ownerId())
                        ->whereIn('id', $groupLeadIdsForFilter)
                        ->pluck('name', 'id');
                } else {
                    $leads = collect([]);
                }
            } elseif (!$canViewAllDeals) {
                $assignedLeadIds = \DB::table('user_leads')
                    ->where('user_id', $usr->id)
                    ->pluck('lead_id')
                    ->toArray();

                if (!empty($assignedLeadIds)) {
                    $leads = Lead::where('created_by', $usr->ownerId())
                        ->whereIn('id', $assignedLeadIds)
                        ->pluck('name', 'id');
                } else {
                    $leads = collect([]);
                }
            } else {
                $leads = Lead::where('created_by', $usr->ownerId())->pluck('name', 'id');
            }
FROM,
    'to' => <<<'TO'
            // The Lead filter is an AJAX autocomplete (deals.leads.search). The full
            // lead list is NEVER rendered here: it produced one <option> per lead and
            // then Choices.js built a DOM node for each one, which froze the browser.
            // Only the currently filtered lead is needed, to label the input.
            $selectedLead = null;
            if ($request->lead_id) {
                $selectedLead = Lead::where('created_by', $usr->ownerId())
                    ->where('id', $request->lead_id)
                    ->first(['id', 'name']);
            }
TO,
];

/* ------------------------------------------------------------------ *
 * 5. DealController@index - pass $productsMap to BOTH views           *
 * ------------------------------------------------------------------ */
$patches[] = [
    'file' => 'app/Http/Controllers/DealController.php',
    'name' => 'index: pass productsMap / selectedLead to the views',
    'done' => "compact('deals', 'productsMap')",
    'from' => <<<'FROM'
            if ($request->ajax()) {
                return view('deals._table', compact('deals'));
            }

            return view('deals.index', compact('deals', 'leads', 'stages', 'products', 'users'));
FROM,
    'to' => <<<'TO'
            if ($request->ajax()) {
                return view('deals._table', compact('deals', 'productsMap'));
            }

            return view('deals.index', compact('deals', 'selectedLead', 'stages', 'products', 'users', 'productsMap'));
TO,
];

/* ------------------------------------------------------------------ *
 * 6. DealController - leadsSearch() + visibleLeadIdSubQuery() helpers *
 * ------------------------------------------------------------------ */
$patches[] = [
    'file' => 'app/Http/Controllers/DealController.php',
    'name' => 'add leadsSearch() + visibleLeadIdSubQuery()',
    'done' => 'public function leadsSearch(',
    'from' => <<<'FROM'
    /**
     * Show the form for creating a new redeal.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (\Auth::user()->can('create deal')) {
            $leads = Lead::where('created_by', '=', \Auth::user()->ownerId())->get()->pluck('name', 'id');
            $stages = \App\Models\Stage::where('created_by', '=', \Auth::user()->ownerId())->orderBy('order')->get()->pluck('name', 'id');
FROM,
    'to' => <<<'TO'
    /**
     * Lead ids this user is allowed to see deals/leads for.
     *
     * null  => no restriction (may see everything in the tenant)
     * false => may see nothing
     * otherwise a query builder selecting lead_id, usable as a subquery.
     */
    private function visibleLeadIdSubQuery($usr)
    {
        $isSalesManager = $usr->hasRole('Sales Manager') || $usr->hasRole('sales manager');

        // Sales Manager is checked first, exactly as the original index() did:
        // a manager stays limited to their group even if they can 'view all leads'.
        if ($isSalesManager) {
            if (!$usr->sales_group) {
                return false;
            }

            return \DB::table('user_leads')
                ->select('lead_id')
                ->whereIn(
                    'user_id',
                    User::where('created_by', $usr->creatorId())
                        ->where('sales_group', $usr->sales_group)
                        ->select('id')
                );
        }

        $canViewAllDeals = (
            $usr->type == 'company' ||
            $usr->type == 'CSO' ||
            $usr->type == 'Alaa,Saba,aber' ||
            $usr->type == 'fadi' ||
            $usr->can('view all leads')
        );

        if ($canViewAllDeals) {
            return null;
        }

        return \DB::table('user_leads')
            ->select('lead_id')
            ->where('user_id', $usr->id);
    }

    /**
     * Autocomplete source for the Lead pickers on the deal screens.
     * Returns at most 25 rows, so neither the payload nor the widget grows with
     * the size of the leads table.
     */
    public function leadsSearch(Request $request)
    {
        $usr = \Auth::user();

        if (!$usr->can('manage deal') && !$usr->can('create deal') && !$usr->can('edit deal')) {
            return response()->json([], 401);
        }

        $visibleLeadIds = $this->visibleLeadIdSubQuery($usr);
        if ($visibleLeadIds === false) {
            return response()->json([]);
        }

        $query = Lead::where('created_by', '=', $usr->ownerId());
        if ($visibleLeadIds !== null) {
            $query->whereIn('id', $visibleLeadIds);
        }

        $term = trim((string) $request->get('q'));
        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', '%' . $term . '%')
                    ->orWhere('phone', 'like', '%' . $term . '%')
                    ->orWhere('email', 'like', '%' . $term . '%');
            });
        }

        $leads = $query->orderBy('id', 'desc')->limit(25)->get(['id', 'name', 'phone']);

        return response()->json(
            $leads->map(function ($lead) {
                return [
                    'id' => $lead->id,
                    'text' => $lead->name . ($lead->phone ? ' - ' . $lead->phone : ''),
                ];
            })->values()
        );
    }

    /**
     * Show the form for creating a new redeal.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (\Auth::user()->can('create deal')) {
            $stages = \App\Models\Stage::where('created_by', '=', \Auth::user()->ownerId())->orderBy('order')->get()->pluck('name', 'id');
TO,
];

/* ------------------------------------------------------------------ *
 * 7. DealController@create - stop passing $leads                     *
 * ------------------------------------------------------------------ */
$patches[] = [
    'file' => 'app/Http/Controllers/DealController.php',
    'name' => 'create: drop $leads from the view data',
    'done' => "compact('stages', 'customFields', 'products')",
    'from' => <<<'FROM'
            return view('deals.create', compact('leads', 'stages', 'customFields', 'products'));
FROM,
    'to' => <<<'TO'
            return view('deals.create', compact('stages', 'customFields', 'products'));
TO,
];

/* ------------------------------------------------------------------ *
 * 8. DealController@edit - stop loading every lead                   *
 * ------------------------------------------------------------------ */
$patches[] = [
    'file' => 'app/Http/Controllers/DealController.php',
    'name' => 'edit: drop the full lead list',
    'done' => "\$deal->load('lead:id,name');",
    'from' => <<<'FROM'
                $leads = Lead::where('created_by', '=', \Auth::user()->ownerId())->get()->pluck('name', 'id');
                $deal->customField = CustomField::getData($deal, 'deal');
                $customFields = CustomField::where('module', '=', 'deal')->get();

                $deal->products = explode(',', $deal->products);
FROM,
    'to' => <<<'TO'
                $deal->load('lead:id,name');
                $deal->customField = CustomField::getData($deal, 'deal');
                $customFields = CustomField::where('module', '=', 'deal')->get();

                $deal->products = explode(',', (string) $deal->products);
TO,
];

$patches[] = [
    'file' => 'app/Http/Controllers/DealController.php',
    'name' => 'edit: view data without $leads',
    'done' => "return view('deals.edit', compact('deal', 'customFields', 'stages', 'products'));",
    'from' => <<<'FROM'
                return view('deals.edit', compact('deal', 'leads', 'customFields', 'stages', 'products'));
FROM,
    'to' => <<<'TO'
                return view('deals.edit', compact('deal', 'customFields', 'stages', 'products'));
TO,
];

/* ------------------------------------------------------------------ *
 * 9. DealController@show - permission + tenant check                 *
 * ------------------------------------------------------------------ */
$patches[] = [
    'file' => 'app/Http/Controllers/DealController.php',
    'name' => 'show: add permission + tenant check',
    'done' => "if (!\\Auth::user()->can('view deal'))",
    'from' => <<<'FROM'
    public function show(Deal $deal)
    {
        if ($deal->is_active) {
FROM,
    'to' => <<<'TO'
    public function show(Deal $deal)
    {
        // Without these two checks any logged-in user could open any deal id,
        // including deals belonging to another company.
        if (!\Auth::user()->can('view deal')) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        if ($deal->created_by != \Auth::user()->ownerId()) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        if ($deal->is_active) {
TO,
];

/* ------------------------------------------------------------------ *
 * 10. Attachment download/serve - tenant check                       *
 * ------------------------------------------------------------------ */
$patches[] = [
    'file' => 'app/Http/Controllers/DealController.php',
    'name' => 'downloadAttachment: tenant check',
    'done' => '// Only the owning tenant may download',
    'from' => <<<'FROM'
    public function downloadAttachment($id, $attachmentId)
    {
        $deal = Deal::findOrFail($id);
        $attachment = DealAttachment::where('deal_id', $id)->findOrFail($attachmentId);
FROM,
    'to' => <<<'TO'
    public function downloadAttachment($id, $attachmentId)
    {
        $deal = Deal::findOrFail($id);

        // Only the owning tenant may download
        if ($deal->created_by != \Auth::user()->ownerId()) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        $attachment = DealAttachment::where('deal_id', $id)->findOrFail($attachmentId);
TO,
];

$patches[] = [
    'file' => 'app/Http/Controllers/DealController.php',
    'name' => 'serveAttachment: tenant check',
    'done' => '// Only the owning tenant may view',
    'from' => <<<'FROM'
    public function serveAttachment($id, $attachmentId)
    {
        $deal = Deal::findOrFail($id);
        $attachment = DealAttachment::where('deal_id', $id)->findOrFail($attachmentId);
FROM,
    'to' => <<<'TO'
    public function serveAttachment($id, $attachmentId)
    {
        $deal = Deal::findOrFail($id);

        // Only the owning tenant may view
        if ($deal->created_by != \Auth::user()->ownerId()) {
            abort(403);
        }

        $attachment = DealAttachment::where('deal_id', $id)->findOrFail($attachmentId);
TO,
];

/* ------------------------------------------------------------------ *
 * 11. productUpdate / productDestroy - null-safe explode + dedupe    *
 * ------------------------------------------------------------------ */
$patches[] = [
    'file' => 'app/Http/Controllers/DealController.php',
    'name' => 'productUpdate: null-safe explode + dedupe',
    'done' => 'array_unique(array_filter(array_merge($old_products, $products)))',
    'from' => <<<'FROM'
                    $products = array_filter($request->products);
                    $old_products = explode(',', $deal->products);
                    $deal->products = implode(',', array_merge($old_products, $products));
FROM,
    'to' => <<<'TO'
                    $products = array_filter($request->products);
                    $old_products = explode(',', (string) $deal->products);
                    $deal->products = implode(',', array_unique(array_filter(array_merge($old_products, $products))));
TO,
];

$patches[] = [
    'file' => 'app/Http/Controllers/DealController.php',
    'name' => 'productDestroy: null-safe explode',
    'done' => "\$products = explode(',', (string) \$deal->products);\n                foreach",
    'from' => <<<'FROM'
                $products = explode(',', $deal->products);
                foreach ($products as $key => $product) {
FROM,
    'to' => <<<'TO'
                $products = explode(',', (string) $deal->products);
                foreach ($products as $key => $product) {
TO,
];

/* ------------------------------------------------------------------ *
 * 12. routes/web.php - the autocomplete endpoint.                    *
 *     Must be declared BEFORE Route::resource('deals') or            *
 *     deals/{deal} would match it first.                             *
 * ------------------------------------------------------------------ */
$patches[] = [
    'file' => 'routes/web.php',
    'name' => 'route: deals.leads.search',
    'done' => "'deals.leads.search'",
    'from' => <<<'FROM'
    Route::resource('deals', DealController::class)->middleware(['auth', 'XSS']);
FROM,
    'to' => <<<'TO'
    // Autocomplete for the Lead pickers on the deal screens. MUST stay above
    // Route::resource('deals') or deals/{deal} would match it first.
    Route::get('/deals-leads-search', [DealController::class, 'leadsSearch'])->name('deals.leads.search')->middleware(['auth', 'XSS']);
    Route::resource('deals', DealController::class)->middleware(['auth', 'XSS']);
TO,
];

/* ------------------------------------------------------------------ *
 * 13. deals/index.blade.php - AJAX lead picker                       *
 * ------------------------------------------------------------------ */
$patches[] = [
    'file' => 'resources/views/deals/index.blade.php',
    'name' => 'index view: lead picker',
    'done' => "@include('deals._lead_picker'",
    'from' => <<<'FROM'
                                    <select class="form-control select2" id="filter_lead" name="lead_id">
                                        <option value="">{{ __('Select Lead') }}</option>
                                        @foreach ($leads as $id => $name)
                                            <option value="{{ $id }}">{{ $name }}</option>
                                        @endforeach
                                    </select>
FROM,
    'to' => <<<'TO'
                                    @include('deals._lead_picker', [
                                        'pickerId' => 'filter_lead',
                                        'fieldName' => 'lead_id',
                                        'selectedId' => $selectedLead->id ?? null,
                                        'selectedName' => $selectedLead->name ?? '',
                                    ])
TO,
];

$patches[] = [
    'file' => 'resources/views/deals/index.blade.php',
    'name' => 'index view: restore the lead label with the saved filter',
    'done' => "if (_sf.lead_name)",
    'from' => <<<'FROM'
                    if (_sf.lead_id) $('#filter_lead').val(_sf.lead_id).trigger('change');
FROM,
    'to' => <<<'TO'
                    if (_sf.lead_id) $('#filter_lead').val(_sf.lead_id);
                    if (_sf.lead_name) $('#filter_lead_search').val(_sf.lead_name);
TO,
];

$patches[] = [
    'file' => 'resources/views/deals/index.blade.php',
    'name' => 'index view: send the lead label with the filter',
    'done' => "lead_name: \$('#filter_lead')",
    'from' => <<<'FROM'
                lead_id: $('#filter_lead').val(),
FROM,
    'to' => <<<'TO'
                lead_id: $('#filter_lead').val(),
                lead_name: $('#filter_lead').val() ? $('#filter_lead_search').val() : '',
TO,
];

/* ------------------------------------------------------------------ *
 * 14. deals/_table.blade.php                                         *
 * ------------------------------------------------------------------ */
$patches[] = [
    'file' => 'resources/views/deals/_table.blade.php',
    'name' => 'table view: productsMap guard',
    'done' => '@php($productsMap = $productsMap ?? collect())',
    'from' => <<<'FROM'
<div class="table-responsive">
FROM,
    'to' => <<<'TO'
@php($productsMap = $productsMap ?? collect())
<div class="table-responsive">
TO,
];

$patches[] = [
    'file' => 'resources/views/deals/_table.blade.php',
    'name' => 'table view: attachments_count',
    'done' => '$deal->attachments_count',
    'from' => <<<'FROM'
                            @php($attachmentCount = $deal->attachments->count())
FROM,
    'to' => <<<'TO'
                            @php($attachmentCount = $deal->attachments_count ?? $deal->attachments->count())
TO,
];

$patches[] = [
    'file' => 'resources/views/deals/_table.blade.php',
    'name' => 'table view: do not print a 12:00 AM that does not exist',
    'done' => "format('H:i') !== '00:00'",
    'from' => <<<'FROM'
                        <td>{{ $deal->datetime ? \Auth::user()->dateFormat($deal->datetime) . ' ' . $deal->datetime->format('h:i A') : '-' }}
FROM,
    'to' => <<<'TO'
                        <td>{{ $deal->datetime ? \Auth::user()->dateFormat($deal->datetime) . ($deal->datetime->format('H:i') !== '00:00' ? ' ' . $deal->datetime->format('h:i A') : '') : '-' }}
TO,
];

/* ------------------------------------------------------------------ *
 * 15. deals/create.blade.php + edit.blade.php - lead picker          *
 * ------------------------------------------------------------------ */
$patches[] = [
    'file' => 'resources/views/deals/create.blade.php',
    'name' => 'create view: lead picker',
    'done' => "@include('deals._lead_picker'",
    'from' => <<<'FROM'
                {{ Form::select('lead_id', $leads, null, ['class' => 'form-control select2', 'required' => 'required', 'placeholder' => __('Select Lead')]) }}
FROM,
    'to' => <<<'TO'
                @include('deals._lead_picker', [
                    'pickerId' => 'create_deal_lead',
                    'fieldName' => 'lead_id',
                    'required' => true,
                ])
TO,
];

$patches[] = [
    'file' => 'resources/views/deals/edit.blade.php',
    'name' => 'edit view: lead picker',
    'done' => "@include('deals._lead_picker'",
    'from' => <<<'FROM'
                {{ Form::select('lead_id', $leads, $deal->lead_id, ['class' => 'form-control select2', 'required' => 'required', 'placeholder' => __('Select Lead')]) }}
FROM,
    'to' => <<<'TO'
                @include('deals._lead_picker', [
                    'pickerId' => 'edit_deal_lead_' . $deal->id,
                    'fieldName' => 'lead_id',
                    'selectedId' => $deal->lead_id,
                    'selectedName' => $deal->lead->name ?? '',
                    'required' => true,
                ])
TO,
];

$patches[] = [
    'file' => 'resources/views/deals/edit.blade.php',
    'name' => 'edit view: attachment count without loading the rows',
    'done' => '$deal->attachments()->count()',
    'from' => <<<'FROM'
                    @php($attachmentCount = $deal->attachments->count())
FROM,
    'to' => <<<'TO'
                    @php($attachmentCount = $deal->attachments()->count())
TO,
];

/* ------------------------------------------------------------------ *
 * 16/17. store() + update() - the lead must belong to this company    *
 * ------------------------------------------------------------------ */
$patches[] = [
    'file' => 'app/Http/Controllers/DealController.php',
    'name' => 'store: lead_id must belong to this company',
    'done' => "\$this->leadExistsRule(\$usr)",
    'from' => <<<'FROM'
            $validator = \Validator::make(
                $request->all(),
                [
                    'name' => 'required',
                    'lead_id' => 'required|exists:leads,id',
                    'stage_id' => 'required',
                ]
            );

            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return redirect()->back()->with('error', $messages->first());
            }

            $lead = Lead::find($request->lead_id);
FROM,
    'to' => <<<'TO'
            $validator = \Validator::make(
                $request->all(),
                [
                    'name' => 'required',
                    // 'exists:leads,id' alone let a deal be attached to another
                    // company's lead, so the rule is scoped to the tenant.
                    'lead_id' => ['required', $this->leadExistsRule($usr)],
                    'stage_id' => 'required',
                ]
            );

            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return redirect()->back()->with('error', $messages->first());
            }

            $lead = Lead::find($request->lead_id);
TO,
];

$patches[] = [
    'file' => 'app/Http/Controllers/DealController.php',
    'name' => 'update: lead_id must belong to this company',
    'done' => "\$this->leadExistsRule(\\Auth::user())",
    'from' => <<<'FROM'
                $validator = \Validator::make(
                    $request->all(),
                    [
                        'name' => 'required',
                        'lead_id' => 'required|exists:leads,id',
                        'stage_id' => 'required',
                    ]
                );
FROM,
    'to' => <<<'TO'
                $validator = \Validator::make(
                    $request->all(),
                    [
                        'name' => 'required',
                        'lead_id' => ['required', $this->leadExistsRule(\Auth::user())],
                        'stage_id' => 'required',
                    ]
                );
TO,
];

$patches[] = [
    'file' => 'app/Http/Controllers/DealController.php',
    'name' => 'add leadExistsRule() helper',
    'done' => 'private function leadExistsRule(',
    'from' => <<<'FROM'
    /**
     * Lead ids this user is allowed to see deals/leads for.
FROM,
    'to' => <<<'TO'
    /**
     * A lead must exist AND belong to the current tenant.
     */
    private function leadExistsRule($usr)
    {
        return \Illuminate\Validation\Rule::exists('leads', 'id')
            ->where('created_by', $usr->ownerId());
    }

    /**
     * Lead ids this user is allowed to see deals/leads for.
TO,
];

/* ------------------------------------------------------------------ *
 * 18. Deleting a deal must not leave attachment rows or files behind  *
 * ------------------------------------------------------------------ */
$patches[] = [
    'file' => 'app/Models/Deal.php',
    'name' => 'model: clean up attachments on delete',
    'done' => 'foreach ($deal->attachments as $attachment)',
    'from' => <<<'FROM'
        static::deleting(function ($deal) {
            if ($deal->dealPurchase) {
                $deal->dealPurchase->delete();
            }
        });
FROM,
    'to' => <<<'TO'
        static::deleting(function ($deal) {
            if ($deal->dealPurchase) {
                $deal->dealPurchase->delete();
            }

            // deal_attachments has no cascade on every environment, so the rows
            // (and the uploaded files) were left behind on every deal deletion.
            foreach ($deal->attachments as $attachment) {
                if ($attachment->file_path && \Storage::disk('public')->exists($attachment->file_path)) {
                    \Storage::disk('public')->delete($attachment->file_path);
                }
                $attachment->delete();
            }
        });
TO,
];
/* ================================================================== *
 *                              apply                                  *
 * ================================================================== */
$applied = 0;
$skipped = 0;
$failed = 0;
$backedUp = [];

foreach ($patches as $p) {
    $path = $root . '/' . $p['file'];
    $label = $p['file'] . ' :: ' . $p['name'];

    if (!is_file($path)) {
        echo "[FAIL] $label -- file not found\n";
        $failed++;
        continue;
    }

    $contents = file_get_contents($path);

    if (strpos($contents, $p['done']) !== false) {
        echo "[SKIP] $label -- already applied\n";
        $skipped++;
        continue;
    }

    $count = substr_count($contents, $p['from']);
    if ($count === 0) {
        echo "[FAIL] $label -- anchor not found (file differs from the expected baseline)\n";
        $failed++;
        continue;
    }
    if ($count > 1) {
        echo "[FAIL] $label -- anchor matches $count times, refusing to guess\n";
        $failed++;
        continue;
    }

    if (!isset($backedUp[$path])) {
        $bak = $path . '.bak-deals-perf';
        if (!file_exists($bak)) {
            copy($path, $bak);
            echo "       backup -> " . basename($bak) . "\n";
        }
        $backedUp[$path] = true;
    }

    file_put_contents($path, str_replace($p['from'], $p['to'], $contents));
    echo "[ OK ] $label\n";
    $applied++;
}

echo "\n--------------------------------------------------\n";
echo "applied: $applied   skipped: $skipped   failed: $failed\n";

if ($failed > 0) {
    echo "\nOne or more anchors did not match. Review those files by hand;\n";
    echo "everything else was applied and is safe to keep.\n";
    exit(2);
}

echo "\nNext: php artisan view:clear && php artisan route:clear\n";
exit(0);
