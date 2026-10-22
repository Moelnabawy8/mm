<?php
// The anchored edits for the CRM Reports deploy, shared by the anchor checker and the builder.
// Each: path, label, find, replace, optional (skip instead of fail when the anchor is missing).

$crmRoutes = <<<'TXT'

    // CRM Reports: one page per category (Employee / Data / Financial)
    Route::prefix('crm-reports')->name('crm-reports.')->middleware(['auth', 'XSS'])->group(function () {
        Route::get('employee', [\App\Http\Controllers\CrmReportController::class, 'employee'])->name('employee');
        Route::get('employee/section/{section}', [\App\Http\Controllers\CrmReportController::class, 'section'])
            ->where('section', '[a-z]+')->name('employee.section');
        Route::get('employee/export/{section}', [\App\Http\Controllers\CrmReportController::class, 'export'])
            ->where('section', '[a-z]+')->name('employee.export');
        Route::get('data', [\App\Http\Controllers\CrmReportController::class, 'data'])->name('data');
        Route::get('financial', [\App\Http\Controllers\CrmReportController::class, 'financial'])->name('financial');
    });
TXT;

$callRoutes = <<<'TXT'

    Route::get('/leads/{id}/calls/create', [\App\Http\Controllers\LeadCallController::class, 'create'])->name('leads.calls.create')->middleware(['auth', 'XSS']);
    Route::post('/leads/{id}/calls', [\App\Http\Controllers\LeadCallController::class, 'store'])->name('leads.calls.store')->middleware(['auth', 'XSS']);
    Route::delete('/leads/{id}/calls/{call}', [\App\Http\Controllers\LeadCallController::class, 'destroy'])->name('leads.calls.destroy')->middleware(['auth', 'XSS']);
TXT;

$menuBlock = <<<'TXT'
                                @can('show crm reports')
                                    <li class="dash-item dash-hasmenu {{ Request::segment(1) == 'crm-reports' ? 'active dash-trigger' : '' }}">
                                        <a class="dash-link" href="#"><i class="ti ti-chart-dots me-1"></i>{{ __('CRM Reports') }}<span
                                                class="dash-arrow"><i data-feather="chevron-right"></i></span></a>
                                        <ul class="dash-submenu">
                                            <li class="dash-item {{ Request::route()->getName() == 'crm-reports.employee' ? 'active' : '' }}">
                                                <a class="dash-link" href="{{ route('crm-reports.employee') }}">{{ __('Employee Reports') }}</a>
                                            </li>
                                            <li class="dash-item {{ Request::route()->getName() == 'crm-reports.data' ? 'active' : '' }}">
                                                <a class="dash-link" href="{{ route('crm-reports.data') }}">{{ __('Data Reports') }}</a>
                                            </li>
                                            <li class="dash-item {{ Request::route()->getName() == 'crm-reports.financial' ? 'active' : '' }}">
                                                <a class="dash-link" href="{{ route('crm-reports.financial') }}">{{ __('Financial Reports') }}</a>
                                            </li>
                                        </ul>
                                    </li>
                                @endcan

TXT;

$reportDeal = "    Route::get('reports-deal', [ReportController::class, 'dealReport'])->name('report.deal')->middleware(['auth', 'XSS']);";
$noteRoute = "    Route::post('/leads/{id}/note', [LeadController::class, 'noteStore'])->name('leads.note.store')->middleware(['auth']);";
$setupIf = "                                @if (Gate::check('manage lead stage') ||";
$notes = "                {{-- Notes Section --}}";
$durCreate = "            {{ Form::number('duration', '', ['class' => 'form-control', 'min' => '1', 'placeholder' => __('e.g. 30, 60, 90')]) }}\n        </div>";
$durEdit = "            {{ Form::number('duration', null, ['class' => 'form-control', 'min' => '1', 'placeholder' => __('e.g. 30, 60, 90')]) }}\n        </div>";
$routeProv = "    App\\Providers\\RouteServiceProvider::class,";

return [
    ['path' => 'routes/web.php', 'label' => 'crm-reports routes', 'find' => $reportDeal, 'replace' => $reportDeal . "
" . $crmRoutes],
    ['path' => 'routes/web.php', 'label' => 'lead call routes', 'find' => $noteRoute, 'replace' => $noteRoute . $callRoutes],
    ['path' => 'resources/views/partials/admin/menu.blade.php', 'label' => 'CRM Reports menu', 'find' => $setupIf, 'replace' => $menuBlock . $setupIf],
    ['path' => 'resources/views/partials/admin/menu.blade.php', 'label' => 'CRM menu opens on report pages', 'optional' => true,
        'find' => "Request::segment(1) == 'pr' ? 'show' : '' }}\">",
        'replace' => "Request::segment(1) == 'pr' || Request::segment(1) == 'crm-reports' ? 'show' : '' }}\">"],
    ['path' => 'resources/views/partials/admin/menu.blade.php', 'label' => 'CRM menu active on report pages', 'optional' => true,
        'find' => "Request::segment(1) == 'pr' ? ' active dash-trigger' : '' }}\">",
        'replace' => "Request::segment(1) == 'pr' || Request::segment(1) == 'crm-reports' ? ' active dash-trigger' : '' }}\">"],
    ['path' => 'resources/views/leads/show.blade.php', 'label' => 'Calls section', 'find' => $notes,
        'replace' => "                {{-- Calls Section --}}\n                @include('leads._calls_section')\n\n" . $notes],
    ['path' => 'resources/views/sales_meetings/create.blade.php', 'label' => 'meeting status (create)', 'find' => $durCreate,
        'replace' => $durCreate . "\n        @include('sales_meetings._status_field')"],
    ['path' => 'resources/views/sales_meetings/edit.blade.php', 'label' => 'meeting status (edit)', 'find' => $durEdit,
        'replace' => $durEdit . "\n        @include('sales_meetings._status_field')"],
    ['path' => 'bootstrap/providers.php', 'label' => 'register CrmReportsServiceProvider', 'find' => $routeProv,
        'replace' => $routeProv . "\n    App\\Providers\\CrmReportsServiceProvider::class,"],
];
