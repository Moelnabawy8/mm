@extends('layouts.admin')

@section('page-title')
    {{ __('Task Report') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('Task Report') }}</li>
@endsection

@push('css-page')
    <style>
        .tr-kpi .card {
            border: 0;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(27, 79, 138, .08);
            height: 100%;
        }

        .tr-kpi .kpi-value {
            font-size: 26px;
            font-weight: 700;
            line-height: 1.1;
        }

        .tr-kpi .kpi-label {
            font-size: 12px;
            color: #6c757d;
            margin-top: 2px;
        }

        .tr-kpi .kpi-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .tr-filter .form-label {
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .tr-preset .btn {
            margin: 2px;
        }

        .tr-table td,
        .tr-table th {
            vertical-align: middle;
            font-size: 13px;
        }

        .tr-row-late {
            background-color: #fdf1f1 !important;
        }

        .tr-progress {
            height: 6px;
            border-radius: 4px;
        }

        .badge-soft {
            font-weight: 600;
            font-size: 11px;
        }

        /* Long summaries scroll inside the card instead of stretching the page.
           NOTE: the theme bleeds .table-responsive out by -25px so the table
           meets the card edge, which only lines up while the card body keeps
           its own 25px padding - never put p-0 on these card bodies. */
        .tr-scroll {
            max-height: 430px;
            overflow-y: auto;
            overflow-x: auto;
        }

        .tr-scroll thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: #fff;
            box-shadow: inset 0 -1px 0 #e9ecef;
        }

        /* Numeric columns stay on one line and share the row evenly */
        .tr-table .num {
            white-space: nowrap;
            text-align: center;
        }

        /* Column widths come from each table's <colgroup>; a fixed layout makes
           the browser honour them exactly instead of scattering the metric
           columns across the row. Under min-width the wrapper scrolls rather
           than crushing the cells. */
        .tr-summary {
            table-layout: fixed;
            min-width: 900px;
        }

        /* A long name wraps inside its column instead of widening the table */
        .tr-summary td:first-child,
        .tr-summary th:first-child {
            white-space: normal;
            overflow-wrap: anywhere;
        }

        /* Rate cell: bar and percentage side by side so rows stay short */
        .tr-rate .rate-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tr-rate .progress {
            flex: 1 1 auto;
            min-width: 60px;
        }

        .tr-rate small {
            flex: 0 0 auto;
            font-size: 11px;
            min-width: 46px;
            text-align: end;
        }

        /* Only the long detail table keeps every cell on one line */
        .tr-detailed th,
        .tr-detailed td {
            white-space: nowrap;
        }

        .tr-detailed td:first-child {
            white-space: normal;
            min-width: 170px;
        }
    </style>
@endpush

@section('action-btn')
    <div class="float-end">
        <button type="submit" form="task-report-filter" formaction="{{ route('task_report.export.pdf') }}"
            formtarget="_blank" class="btn btn-sm btn-danger me-1" title="{{ __('Export PDF') }}">
            <i class="ti ti-file-text"></i> {{ __('PDF') }}
        </button>
        <button type="submit" form="task-report-filter" formaction="{{ route('task_report.export.word') }}"
            class="btn btn-sm btn-primary me-1" title="{{ __('Export Word') }}">
            <i class="ti ti-file-description"></i> {{ __('Word') }}
        </button>
        <button type="submit" form="task-report-filter" formaction="{{ route('task_report.export.excel') }}"
            class="btn btn-sm btn-success" title="{{ __('Export Excel') }}">
            <i class="ti ti-file-spreadsheet"></i> {{ __('Excel') }}
        </button>
    </div>
@endsection

@section('content')
    <div class="row">

        {{-- ==================== FILTERS ==================== --}}
        <div class="col-12">
            <div class="card tr-filter">
                <div class="card-body pb-2">
                    <form method="GET" action="{{ route('task_report.index') }}" id="task-report-filter">

                        {{-- Quick period presets --}}
                        <div class="row mb-3">
                            <div class="col-12 tr-preset">
                                <span class="form-label me-2">{{ __('Quick Period') }}:</span>
                                <button type="button" class="btn btn-outline-primary btn-sm"
                                    data-preset="today">{{ __('Today') }}</button>
                                <button type="button" class="btn btn-outline-primary btn-sm"
                                    data-preset="week">{{ __('This Week') }}</button>
                                <button type="button" class="btn btn-outline-primary btn-sm"
                                    data-preset="month">{{ __('This Month') }}</button>
                                <button type="button" class="btn btn-outline-primary btn-sm"
                                    data-preset="last30">{{ __('Last 30 Days') }}</button>
                                <button type="button" class="btn btn-outline-primary btn-sm"
                                    data-preset="quarter">{{ __('This Quarter') }}</button>
                                <button type="button" class="btn btn-outline-primary btn-sm"
                                    data-preset="year">{{ __('This Year') }}</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm"
                                    data-preset="clear">{{ __('All Time') }}</button>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-2 col-sm-6 mb-2">
                                <label class="form-label">{{ __('Start Date') }}</label>
                                <input type="date" name="start_date" id="tr-start" class="form-control"
                                    value="{{ $filters['start_date'] }}">
                            </div>
                            <div class="col-md-2 col-sm-6 mb-2">
                                <label class="form-label">{{ __('End Date') }}</label>
                                <input type="date" name="end_date" id="tr-end" class="form-control"
                                    value="{{ $filters['end_date'] }}">
                            </div>
                            <div class="col-md-2 col-sm-6 mb-2">
                                <label class="form-label">{{ __('Filter Dates By') }}</label>
                                <select name="date_basis" class="form-control">
                                    @foreach ($dateBasis as $key => $label)
                                        <option value="{{ $key }}"
                                            {{ $filters['date_basis'] == $key ? 'selected' : '' }}>{{ __($label) }}
                                        </option>
                                    @endforeach
                                </select>
                                {{-- The hidden 0 is what lets an unticked box be submitted at all. --}}
                                <input type="hidden" name="include_undated" value="0">
                                <div class="form-check mt-1">
                                    <input type="checkbox" name="include_undated" value="1" id="tr-include-undated"
                                        class="form-check-input" {{ $filters['include_undated'] ? 'checked' : '' }}>
                                    <label for="tr-include-undated" class="form-check-label"
                                        style="font-size:12px;">{{ __('Include tasks with no date') }}</label>
                                </div>
                            </div>
                            <div class="col-md-2 col-sm-6 mb-2">
                                <label class="form-label">{{ __('Status') }}</label>
                                <select name="status" class="form-control">
                                    @foreach ($statusList as $key => $label)
                                        <option value="{{ $key }}"
                                            {{ $filters['status'] == $key ? 'selected' : '' }}>{{ __($label) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 col-sm-12 mb-2">
                                <label class="form-label">{{ __('Search') }}</label>
                                <input type="text" name="search" class="form-control"
                                    placeholder="{{ __('Task name or description') }}"
                                    value="{{ $filters['search'] }}">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-3 col-sm-6 mb-2">
                                <label class="form-label">{{ __('Department') }}</label>
                                <select name="project_id[]" id="tr-projects" class="form-control multi-select" multiple>
                                    @foreach ($projects as $project)
                                        <option value="{{ $project->id }}"
                                            {{ in_array($project->id, $filters['project_id']) ? 'selected' : '' }}>
                                            {{ $project->project_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 col-sm-6 mb-2">
                                <label class="form-label">{{ __('Employee') }}</label>
                                <select name="assign_to[]" id="tr-employees" class="form-control multi-select" multiple>
                                    @foreach ($employees as $employee)
                                        <option value="{{ $employee->id }}"
                                            {{ in_array($employee->id, $filters['assign_to']) ? 'selected' : '' }}>
                                            {{ $employee->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 col-sm-6 mb-2">
                                <label class="form-label">{{ __('Stage') }}</label>
                                <select name="stage_id[]" id="tr-stages" class="form-control multi-select" multiple>
                                    @foreach ($stages as $stage)
                                        <option value="{{ $stage->id }}"
                                            {{ in_array($stage->id, $filters['stage_id']) ? 'selected' : '' }}>
                                            {{ $stage->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 col-sm-6 mb-2">
                                <label class="form-label">{{ __('Priority') }}</label>
                                <select name="priority[]" id="tr-priority" class="form-control multi-select" multiple>
                                    @foreach ($priorities as $key => $label)
                                        <option value="{{ $key }}"
                                            {{ in_array($key, $filters['priority']) ? 'selected' : '' }}>
                                            {{ __($label) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12 text-end mb-2">
                                <a href="{{ route('task_report.index') }}"
                                    class="btn btn-light btn-sm">{{ __('Reset') }}</a>
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="ti ti-filter"></i> {{ __('Apply Filters') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- A period filter drops every task with no date in the column it
             filters on, which is most of them on a board where due dates are
             optional. Say so rather than quietly showing a short report. --}}
        @if (!empty($undated))
            <div class="col-12">
                <div class="alert alert-warning d-flex align-items-center" role="alert">
                    <i class="ti ti-alert-triangle me-2"></i>
                    <div>
                        {{ __('Hidden by the selected period') }}:
                        <strong>{{ $undated }}</strong>
                        {{ __('task(s) have no') }} <strong>{{ __($meta['basis_label']) }}</strong>.
                        {{ __('Tick "Include tasks with no date" to show them alongside the period.') }}
                    </div>
                </div>
            </div>
        @endif

        {{-- ==================== KPI ==================== --}}
        <div class="col-12">
            <div class="row tr-kpi">
                @php
                    $cards = [
                        ['label' => __('Total Tasks'), 'value' => $stats['total'], 'color' => 'primary', 'icon' => 'ti ti-list-check'],
                        ['label' => __('Completed'), 'value' => $stats['completed'], 'color' => 'success', 'icon' => 'ti ti-circle-check'],
                        ['label' => __('In Progress'), 'value' => $stats['in_progress'], 'color' => 'warning', 'icon' => 'ti ti-progress'],
                        ['label' => __('Not Started'), 'value' => $stats['not_started'], 'color' => 'secondary', 'icon' => 'ti ti-player-pause'],
                        ['label' => __('Overdue'), 'value' => $stats['overdue'], 'color' => 'danger', 'icon' => 'ti ti-alert-triangle'],
                        ['label' => __('Completion Rate'), 'value' => $stats['rate'] . '%', 'color' => 'info', 'icon' => 'ti ti-chart-pie'],
                    ];
                @endphp
                @foreach ($cards as $card)
                    <div class="col-lg-2 col-md-4 col-6 mb-3">
                        <div class="card">
                            <div class="card-body d-flex align-items-center">
                                <div class="kpi-icon bg-light-{{ $card['color'] }} text-{{ $card['color'] }} me-3">
                                    <i class="{{ $card['icon'] }}"></i>
                                </div>
                                <div>
                                    <div class="kpi-value text-{{ $card['color'] }}">{{ $card['value'] }}</div>
                                    <div class="kpi-label">{{ $card['label'] }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- ==================== SECONDARY METRICS ==================== --}}
        <div class="col-12">
            <div class="row tr-kpi">
                <div class="col-md-4 col-6 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="kpi-value">{{ $stats['estimated_hrs'] }}</div>
                            <div class="kpi-label">{{ __('Estimated Hours') }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-6 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="kpi-value">{{ $stats['actual_hrs'] }}</div>
                            <div class="kpi-label">{{ __('Actual Hours (Timesheet)') }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-12 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="kpi-value">{{ $stats['avg_days'] !== null ? $stats['avg_days'] : '-' }}</div>
                            <div class="kpi-label">{{ __('Avg. Days to Complete') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if ($tasks->isEmpty())
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="ti ti-clipboard-off" style="font-size:42px;color:#adb5bd;"></i>
                        <h5 class="mt-3 text-muted">{{ __('No tasks match the selected filters.') }}</h5>
                    </div>
                </div>
            </div>
        @else

            {{-- ==================== CHARTS ==================== --}}
            <div class="col-lg-4 col-md-6">
                <div class="card">
                    <div class="card-header"><h5>{{ __('Tasks by Stage') }}</h5></div>
                    <div class="card-body"><div id="chart-stage"></div></div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="card">
                    <div class="card-header"><h5>{{ __('Tasks by Priority') }}</h5></div>
                    <div class="card-body"><div id="chart-priority"></div></div>
                </div>
            </div>
            <div class="col-lg-4 col-md-12">
                <div class="card">
                    <div class="card-header"><h5>{{ __('Completed vs Pending by Employee') }}</h5></div>
                    <div class="card-body"><div id="chart-employee"></div></div>
                </div>
            </div>

            {{-- ==================== BY EMPLOYEE ==================== --}}
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('Summary by Employee') }} <span
                                class="badge bg-primary ms-2">{{ $byUser->count() }}</span></h5>
                    </div>
                    <div class="card-body table-border-style">
                        <div class="table-responsive tr-scroll">
                            <table class="table tr-table tr-summary mb-0">
                                <colgroup>
                                    <col style="width:30%">
                                    <col style="width:10%">
                                    <col style="width:10%">
                                    <col style="width:10%">
                                    <col style="width:10%">
                                    <col style="width:10%">
                                    <col style="width:20%">
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th>{{ __('Employee') }}</th>
                                        <th class="num">{{ __('Total') }}</th>
                                        <th class="num">{{ __('Done') }}</th>
                                        <th class="num">{{ __('Pending') }}</th>
                                        <th class="num">{{ __('Overdue') }}</th>
                                        <th class="num">{{ __('Avg. Days') }}</th>
                                        <th class="tr-rate">{{ __('Rate') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($byUser as $row)
                                        <tr>
                                            <td><strong>{{ $row['label'] }}</strong></td>
                                            <td class="num">{{ $row['total'] }}</td>
                                            <td class="num text-success fw-bold">{{ $row['completed'] }}</td>
                                            <td class="num">{{ $row['pending'] }}</td>
                                            <td class="num text-danger fw-bold">{{ $row['overdue'] }}</td>
                                            <td class="num">{{ $row['avg_days'] !== null ? number_format($row['avg_days'], 1) : '-' }}</td>
                                            <td class="tr-rate">
                                                <div class="rate-wrap">
                                                    <div class="progress tr-progress">
                                                        <div class="progress-bar bg-{{ $row['rate'] >= 75 ? 'success' : ($row['rate'] >= 40 ? 'warning' : 'danger') }}"
                                                            style="width: {{ $row['rate'] }}%"></div>
                                                    </div>
                                                    <small class="text-muted">{{ number_format($row['rate'], 1) }}%</small>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ==================== BY DEPARTMENT ==================== --}}
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('Summary by Department') }} <span
                                class="badge bg-primary ms-2">{{ $byProject->count() }}</span></h5>
                    </div>
                    <div class="card-body table-border-style">
                        <div class="table-responsive tr-scroll">
                            <table class="table tr-table tr-summary mb-0">
                                <colgroup>
                                    <col style="width:30%">
                                    <col style="width:12.5%">
                                    <col style="width:12.5%">
                                    <col style="width:12.5%">
                                    <col style="width:12.5%">
                                    <col style="width:20%">
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th>{{ __('Department') }}</th>
                                        <th class="num">{{ __('Total') }}</th>
                                        <th class="num">{{ __('Done') }}</th>
                                        <th class="num">{{ __('Pending') }}</th>
                                        <th class="num">{{ __('Overdue') }}</th>
                                        <th class="tr-rate">{{ __('Rate') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($byProject as $row)
                                        <tr>
                                            <td><strong>{{ $row['label'] }}</strong></td>
                                            <td class="num">{{ $row['total'] }}</td>
                                            <td class="num text-success fw-bold">{{ $row['completed'] }}</td>
                                            <td class="num">{{ $row['pending'] }}</td>
                                            <td class="num text-danger fw-bold">{{ $row['overdue'] }}</td>
                                            <td class="tr-rate">
                                                <div class="rate-wrap">
                                                    <div class="progress tr-progress">
                                                        <div class="progress-bar bg-{{ $row['rate'] >= 75 ? 'success' : ($row['rate'] >= 40 ? 'warning' : 'danger') }}"
                                                            style="width: {{ $row['rate'] }}%"></div>
                                                    </div>
                                                    <small class="text-muted">{{ number_format($row['rate'], 1) }}%</small>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ==================== DETAILED LIST ==================== --}}
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('Detailed Task List') }} <span
                                class="badge bg-primary ms-2">{{ $tasks->count() }}</span></h5>
                    </div>
                    <div class="card-body table-border-style">
                        <div class="table-responsive">
                            <table class="table tr-table tr-detailed datatable">
                                <thead>
                                    <tr>
                                        <th>{{ __('Task') }}</th>
                                        <th>{{ __('Department') }}</th>
                                        <th>{{ __('Assigned To') }}</th>
                                        <th>{{ __('Priority') }}</th>
                                        <th>{{ __('Stage') }}</th>
                                        <th>{{ __('Status') }}</th>
                                        <th>{{ __('Start Date') }}</th>
                                        <th>{{ __('Due Date') }}</th>
                                        <th>{{ __('Days Late') }}</th>
                                        <th>{{ __('Progress') }}</th>
                                        <th>{{ __('Est.') }}</th>
                                        <th>{{ __('Actual') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($tasks as $task)
                                        <tr class="{{ $task->is_overdue ? 'tr-row-late' : '' }}">
                                            <td><strong>{{ $task->name }}</strong></td>
                                            <td>{{ $task->project_name }}</td>
                                            <td>{{ $task->assignee_text }}</td>
                                            <td>
                                                <span
                                                    class="badge badge-soft bg-{{ $task->priority_color }}">{{ __($task->priority_label) }}</span>
                                            </td>
                                            <td>
                                                <span class="badge badge-soft"
                                                    style="background-color: {{ $task->stage_color }}">{{ $task->stage_name }}</span>
                                            </td>
                                            <td>
                                                @if ($task->is_done)
                                                    <span
                                                        class="badge badge-soft bg-success">{{ __('Done') }}</span>
                                                @elseif ($task->is_overdue)
                                                    <span class="badge badge-soft bg-danger">{{ __('Late') }}</span>
                                                @else
                                                    <span
                                                        class="badge badge-soft bg-secondary">{{ __('Open') }}</span>
                                                @endif
                                            </td>
                                            <td>{{ $task->start_date ?: '-' }}</td>
                                            <td>{{ $task->end_date ?: '-' }}</td>
                                            <td class="{{ $task->days_late > 0 ? 'text-danger fw-bold' : 'text-muted' }}">
                                                {{ $task->days_late > 0 ? $task->days_late : '-' }}</td>
                                            <td>
                                                <div class="progress tr-progress" style="min-width:60px;">
                                                    <div class="progress-bar bg-primary"
                                                        style="width: {{ $task->progress }}%"></div>
                                                </div>
                                                <small class="text-muted">{{ $task->progress }}%</small>
                                            </td>
                                            <td>{{ $task->estimated_hrs }}</td>
                                            <td>{{ $task->actual_hrs }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection

@push('script-page')
    <script>
        $(document).ready(function () {

            // Multi-selects
            if (typeof Choices !== 'undefined') {
                $('.multi-select').each(function () {
                    new Choices('#' + $(this).attr('id'), {
                        removeItemButton: true,
                        shouldSort: false,
                        placeholderValue: '{{ __('All') }}',
                    });
                });
            }

            // Quick period presets
            function fmt(d) {
                var m = ('0' + (d.getMonth() + 1)).slice(-2);
                var day = ('0' + d.getDate()).slice(-2);
                return d.getFullYear() + '-' + m + '-' + day;
            }

            $('[data-preset]').on('click', function () {
                var preset = $(this).data('preset');
                var now = new Date();
                var from = null, to = null;

                if (preset === 'today') {
                    from = to = now;
                } else if (preset === 'week') {
                    var day = now.getDay();
                    from = new Date(now); from.setDate(now.getDate() - day);
                    to = new Date(from); to.setDate(from.getDate() + 6);
                } else if (preset === 'month') {
                    from = new Date(now.getFullYear(), now.getMonth(), 1);
                    to = new Date(now.getFullYear(), now.getMonth() + 1, 0);
                } else if (preset === 'last30') {
                    to = now;
                    from = new Date(now); from.setDate(now.getDate() - 29);
                } else if (preset === 'quarter') {
                    var q = Math.floor(now.getMonth() / 3);
                    from = new Date(now.getFullYear(), q * 3, 1);
                    to = new Date(now.getFullYear(), q * 3 + 3, 0);
                } else if (preset === 'year') {
                    from = new Date(now.getFullYear(), 0, 1);
                    to = new Date(now.getFullYear(), 11, 31);
                }

                $('#tr-start').val(from ? fmt(from) : '');
                $('#tr-end').val(to ? fmt(to) : '');
                $('#task-report-filter').submit();
            });

            @if (!$tasks->isEmpty())
                if (typeof ApexCharts !== 'undefined') {
                    // Stage donut
                    new ApexCharts(document.querySelector('#chart-stage'), {
                        chart: { type: 'donut', height: 280, animations: { animateGradually: { enabled: false }, dynamicAnimation: { enabled: false } } },
                        series: {!! json_encode($byStage->pluck('total')) !!},
                        labels: {!! json_encode($byStage->pluck('label')) !!},
                        colors: {!! json_encode($byStage->pluck('color')) !!},
                        legend: { position: 'bottom' },
                        dataLabels: { enabled: true },
                    }).render();

                    // Priority bars - coloured by severity, not by position
                    @php
                        $priorityColors = [
                            'Critical' => '#c62828',
                            'High'     => '#e08600',
                            'Medium'   => '#1b4f8a',
                            'Low'      => '#16849b',
                        ];
                        $priorityBarColors = $byPriority
                            ->map(fn($row) => $priorityColors[$row['label']] ?? '#5b6b7c')
                            ->values();
                    @endphp
                    new ApexCharts(document.querySelector('#chart-priority'), {
                        chart: { type: 'bar', height: 280, animations: { animateGradually: { enabled: false }, dynamicAnimation: { enabled: false } } },
                        series: [{ name: '{{ __('Tasks') }}', data: {!! json_encode($byPriority->pluck('total')) !!} }],
                        xaxis: { categories: {!! json_encode($byPriority->map(fn($r) => __($r['label']))->values()) !!} },
                        colors: {!! json_encode($priorityBarColors) !!},
                        plotOptions: { bar: { borderRadius: 4, distributed: true, horizontal: true } },
                        legend: { show: false },
                    }).render();

                    // Employee stacked bars (top 8)
                    new ApexCharts(document.querySelector('#chart-employee'), {
                        chart: { type: 'bar', height: 280, stacked: true, animations: { animateGradually: { enabled: false }, dynamicAnimation: { enabled: false } } },
                        series: [
                            { name: '{{ __('Completed') }}', data: {!! json_encode($byUser->take(8)->pluck('completed')) !!} },
                            { name: '{{ __('Pending') }}', data: {!! json_encode($byUser->take(8)->pluck('pending')) !!} },
                        ],
                        xaxis: { categories: {!! json_encode($byUser->take(8)->pluck('label')) !!} },
                        colors: ['#1e874b', '#c9d4e0'],
                        plotOptions: { bar: { borderRadius: 3, horizontal: true } },
                        legend: { position: 'bottom' },
                    }).render();
                }
            @endif
        });
    </script>
@endpush
