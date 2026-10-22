@php
    use App\Models\Utility;

    $setting = Utility::settings();
    $lang = \App::getLocale();
    $rtl = ($setting['SITE_RTL'] ?? '') == 'on' || in_array($lang, ['ar', 'he']);
    $dir = $rtl ? 'rtl' : 'ltr';
    $align = $rtl ? 'right' : 'left';
    $mode = $mode ?? 'pdf';
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}" dir="{{ $dir }}"
    xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Task Report') }} - {{ $meta['period'] }}</title>

    {{-- Word page setup: A4 landscape with sane margins --}}
    <!--[if gte mso 9]>
    <xml>
        <w:WordDocument>
            <w:View>Print</w:View>
            <w:Zoom>100</w:Zoom>
            <w:DoNotOptimizeForBrowser/>
        </w:WordDocument>
    </xml>
    <![endif]-->

    <style>
        @page {
            size: A4 landscape;
            margin: 1.2cm 1cm;
            mso-page-orientation: landscape;
        }

        body {
            font-family: "Segoe UI", Tahoma, "Traditional Arabic", Arial, sans-serif;
            font-size: 11px;
            color: #1f2933;
            margin: 0;
            padding: 0;
            background: #ffffff;
            direction: {{ $dir }};
        }

        .sheet {
            width: 100%;
            padding: 10px 14px;
            box-sizing: border-box;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        /* ---------- Header ---------- */
        .doc-head {
            border-bottom: 3px solid #1b4f8a;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }

        .doc-title {
            font-size: 22px;
            font-weight: bold;
            color: #1b4f8a;
            margin: 0 0 4px 0;
        }

        .doc-company {
            font-size: 14px;
            font-weight: bold;
            color: #33475b;
            margin: 0 0 2px 0;
        }

        .doc-sub {
            font-size: 11px;
            color: #5b6b7c;
            margin: 1px 0;
        }

        .doc-logo {
            max-height: 56px;
            max-width: 190px;
        }

        /* ---------- Section titles ---------- */
        h2.section {
            font-size: 13px;
            font-weight: bold;
            color: #1b4f8a;
            border-{{ $align }}: 4px solid #1b4f8a;
            padding-{{ $align }}: 8px;
            margin: 18px 0 8px 0;
            background: #eef4fb;
            padding-top: 5px;
            padding-bottom: 5px;
        }

        /* ---------- KPI cards ---------- */
        .kpi td {
            width: 16.6%;
            border: 1px solid #d6dee8;
            padding: 9px 10px;
            text-align: center;
            vertical-align: middle;
            background: #fbfcfe;
        }

        .kpi .kpi-value {
            font-size: 19px;
            font-weight: bold;
            line-height: 1.2;
            display: block;
        }

        .kpi .kpi-label {
            font-size: 10px;
            color: #5b6b7c;
            display: block;
            margin-top: 3px;
        }

        /* ---------- Data tables ---------- */
        table.data {
            border: 1px solid #c9d4e0;
        }

        table.data th {
            background: #1b4f8a;
            color: #ffffff;
            font-size: 10px;
            font-weight: bold;
            padding: 7px 6px;
            border: 1px solid #17416f;
            text-align: {{ $align }};
        }

        table.data td {
            font-size: 10px;
            padding: 6px;
            border: 1px solid #d9e1ea;
            text-align: {{ $align }};
            vertical-align: top;
        }

        table.data tr.zebra td {
            background: #f6f9fc;
        }

        .num {
            text-align: center !important;
        }

        .pill {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 9px;
            font-size: 9px;
            font-weight: bold;
            color: #ffffff;
        }

        .done {
            background: #1e874b;
        }

        .notdone {
            background: #8a94a0;
        }

        .late {
            background: #c62828;
        }

        .p-critical {
            background: #c62828;
        }

        .p-high {
            background: #e08600;
        }

        .p-medium {
            background: #1b4f8a;
        }

        .p-low {
            background: #16849b;
        }

        .row-late td {
            background: #fdf1f1 !important;
        }

        /* ---------- Bar chart (table based, Word safe) ---------- */
        .bar-track {
            width: 100%;
            height: 11px;
            background: #e6ecf3;
            border: 0;
        }

        .bar-fill {
            height: 11px;
            background: #1b4f8a;
            font-size: 1px;
            line-height: 1px;
        }

        .muted {
            color: #7b8794;
        }

        .foot {
            margin-top: 16px;
            padding-top: 8px;
            border-top: 1px solid #d6dee8;
            font-size: 9px;
            color: #7b8794;
        }

        .empty {
            padding: 26px;
            text-align: center;
            color: #7b8794;
            border: 1px dashed #c9d4e0;
        }

        /* Keep sections from splitting badly across printed pages */
        .avoid-break {
            page-break-inside: avoid;
        }

        .page-break {
            page-break-before: always;
        }

        /* ---------- On-screen toolbar (never printed / never in Word) ---------- */
        .toolbar {
            background: #1b4f8a;
            color: #fff;
            padding: 10px 16px;
            font-size: 13px;
        }

        .toolbar button {
            background: #ffffff;
            color: #1b4f8a;
            border: 0;
            border-radius: 4px;
            padding: 6px 14px;
            font-size: 12px;
            font-weight: bold;
            cursor: pointer;
            margin-{{ $rtl ? 'right' : 'left' }}: 8px;
        }

        @media print {
            .toolbar {
                display: none;
            }
        }
    </style>
</head>

<body>

@if ($mode == 'pdf')
    <div class="toolbar" id="toolbar">
        <span id="tb-status">{{ __('Preparing your PDF...') }}</span>
        <button type="button" onclick="downloadPdf()">{{ __('Download PDF') }}</button>
        <button type="button" onclick="window.print()">{{ __('Print') }}</button>
        <button type="button" onclick="window.close()">{{ __('Close') }}</button>
    </div>
@endif

<div class="sheet" id="report-sheet">

    {{-- ============ HEADER ============ --}}
    <table class="doc-head">
        <tr>
            <td style="width:62%; vertical-align:middle; text-align:{{ $align }};">
                <p class="doc-company">{{ $meta['company_name'] }}</p>
                <p class="doc-title">{{ __('Task Report') }}</p>
                <p class="doc-sub"><strong>{{ __('Period') }}:</strong> {{ $meta['period'] }}
                    <span class="muted">({{ __($meta['basis_label']) }})</span>
                </p>
                <p class="doc-sub"><strong>{{ __('Filter') }}:</strong> {{ __($meta['status_label']) }}</p>
            </td>
            <td style="width:38%; vertical-align:middle; text-align:{{ $rtl ? 'left' : 'right' }};">
                @if (!empty($meta['logo']))
                    <img src="{{ $meta['logo'] }}" class="doc-logo" alt="">
                @endif
                <p class="doc-sub">{{ __('Generated on') }}: {{ $meta['generated_at'] }}</p>
                <p class="doc-sub">{{ __('Generated by') }}: {{ $meta['generated_by'] }}</p>
            </td>
        </tr>
    </table>

    {{-- ============ KPI ============ --}}
    <table class="kpi avoid-break">
        <tr>
            <td>
                <span class="kpi-value" style="color:#1b4f8a;">{{ $stats['total'] }}</span>
                <span class="kpi-label">{{ __('Total Tasks') }}</span>
            </td>
            <td>
                <span class="kpi-value" style="color:#1e874b;">{{ $stats['completed'] }}</span>
                <span class="kpi-label">{{ __('Completed') }}</span>
            </td>
            <td>
                <span class="kpi-value" style="color:#e08600;">{{ $stats['in_progress'] }}</span>
                <span class="kpi-label">{{ __('In Progress') }}</span>
            </td>
            <td>
                <span class="kpi-value" style="color:#8a94a0;">{{ $stats['not_started'] }}</span>
                <span class="kpi-label">{{ __('Not Started') }}</span>
            </td>
            <td>
                <span class="kpi-value" style="color:#c62828;">{{ $stats['overdue'] }}</span>
                <span class="kpi-label">{{ __('Overdue') }}</span>
            </td>
            <td>
                <span class="kpi-value" style="color:#1b4f8a;">{{ $stats['rate'] }}%</span>
                <span class="kpi-label">{{ __('Completion Rate') }}</span>
            </td>
        </tr>
    </table>

    <table class="kpi avoid-break" style="margin-top:6px;">
        <tr>
            <td>
                <span class="kpi-value" style="color:#33475b;">{{ $stats['estimated_hrs'] }}</span>
                <span class="kpi-label">{{ __('Estimated Hours') }}</span>
            </td>
            <td>
                <span class="kpi-value" style="color:#33475b;">{{ $stats['actual_hrs'] }}</span>
                <span class="kpi-label">{{ __('Actual Hours') }}</span>
            </td>
            <td>
                <span class="kpi-value" style="color:#33475b;">
                    {{ $stats['avg_days'] !== null ? $stats['avg_days'] : '-' }}
                </span>
                <span class="kpi-label">{{ __('Avg. Days to Complete') }}</span>
            </td>
        </tr>
    </table>

    @if ($tasks->isEmpty())
        <p class="empty">{{ __('No tasks match the selected filters.') }}</p>
    @else

        {{-- ============ BY EMPLOYEE ============ --}}
        <h2 class="section">{{ __('Summary by Employee') }}</h2>
        <table class="data avoid-break">
            <thead>
                <tr>
                    <th style="width:20%">{{ __('Employee') }}</th>
                    <th class="num" style="width:7%">{{ __('Total') }}</th>
                    <th class="num" style="width:7%">{{ __('Completed') }}</th>
                    <th class="num" style="width:7%">{{ __('Pending') }}</th>
                    <th class="num" style="width:7%">{{ __('Overdue') }}</th>
                    <th class="num" style="width:8%">{{ __('Est. Hrs') }}</th>
                    <th class="num" style="width:8%">{{ __('Actual Hrs') }}</th>
                    <th class="num" style="width:9%">{{ __('Avg. Days') }}</th>
                    <th style="width:27%">{{ __('Completion Rate') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($byUser as $i => $row)
                    <tr class="{{ $i % 2 ? 'zebra' : '' }}">
                        <td><strong>{{ $row['label'] }}</strong></td>
                        <td class="num">{{ $row['total'] }}</td>
                        <td class="num" style="color:#1e874b;font-weight:bold;">{{ $row['completed'] }}</td>
                        <td class="num">{{ $row['pending'] }}</td>
                        <td class="num" style="color:#c62828;font-weight:bold;">{{ $row['overdue'] }}</td>
                        <td class="num">{{ $row['estimated_hrs'] }}</td>
                        <td class="num">{{ $row['actual_hrs'] }}</td>
                        <td class="num">{{ $row['avg_days'] !== null ? number_format($row['avg_days'], 1) : '-' }}</td>
                        <td>
                            <table class="bar-track">
                                <tr>
                                    <td class="bar-fill"
                                        style="width:{{ max($row['rate'], 1) }}%; background:{{ $row['rate'] >= 75 ? '#1e874b' : ($row['rate'] >= 40 ? '#e08600' : '#c62828') }};">
                                        &nbsp;</td>
                                    <td style="width:{{ 100 - $row['rate'] }}%;">&nbsp;</td>
                                </tr>
                            </table>
                            <span style="font-size:9px;">{{ number_format($row['rate'], 1) }}%</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- ============ BY DEPARTMENT ============ --}}
        <h2 class="section">{{ __('Summary by Department') }}</h2>
        <table class="data avoid-break">
            <thead>
                <tr>
                    <th style="width:34%">{{ __('Department') }}</th>
                    <th class="num" style="width:10%">{{ __('Total') }}</th>
                    <th class="num" style="width:10%">{{ __('Completed') }}</th>
                    <th class="num" style="width:10%">{{ __('Pending') }}</th>
                    <th class="num" style="width:10%">{{ __('Overdue') }}</th>
                    <th style="width:26%">{{ __('Completion Rate') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($byProject as $i => $row)
                    <tr class="{{ $i % 2 ? 'zebra' : '' }}">
                        <td><strong>{{ $row['label'] }}</strong></td>
                        <td class="num">{{ $row['total'] }}</td>
                        <td class="num" style="color:#1e874b;font-weight:bold;">{{ $row['completed'] }}</td>
                        <td class="num">{{ $row['pending'] }}</td>
                        <td class="num" style="color:#c62828;font-weight:bold;">{{ $row['overdue'] }}</td>
                        <td>
                            <table class="bar-track">
                                <tr>
                                    <td class="bar-fill"
                                        style="width:{{ max($row['rate'], 1) }}%; background:{{ $row['rate'] >= 75 ? '#1e874b' : ($row['rate'] >= 40 ? '#e08600' : '#c62828') }};">
                                        &nbsp;</td>
                                    <td style="width:{{ 100 - $row['rate'] }}%;">&nbsp;</td>
                                </tr>
                            </table>
                            <span style="font-size:9px;">{{ number_format($row['rate'], 1) }}%</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- ============ BY STAGE / PRIORITY ============ --}}
        <table style="margin-top:18px;" class="avoid-break">
            <tr>
                <td style="width:49%; vertical-align:top;">
                    <h2 class="section" style="margin-top:0;">{{ __('Tasks by Stage') }}</h2>
                    <table class="data">
                        <thead>
                            <tr>
                                <th>{{ __('Stage') }}</th>
                                <th class="num" style="width:18%">{{ __('Tasks') }}</th>
                                <th class="num" style="width:18%">{{ __('Share') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($byStage as $i => $row)
                                <tr class="{{ $i % 2 ? 'zebra' : '' }}">
                                    <td>{{ $row['label'] }}</td>
                                    <td class="num">{{ $row['total'] }}</td>
                                    <td class="num">{{ $row['percent'] }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </td>
                <td style="width:2%">&nbsp;</td>
                <td style="width:49%; vertical-align:top;">
                    <h2 class="section" style="margin-top:0;">{{ __('Tasks by Priority') }}</h2>
                    <table class="data">
                        <thead>
                            <tr>
                                <th>{{ __('Priority') }}</th>
                                <th class="num" style="width:18%">{{ __('Tasks') }}</th>
                                <th class="num" style="width:18%">{{ __('Share') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($byPriority as $i => $row)
                                <tr class="{{ $i % 2 ? 'zebra' : '' }}">
                                    <td>{{ __($row['label']) }}</td>
                                    <td class="num">{{ $row['total'] }}</td>
                                    <td class="num">{{ $row['percent'] }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </td>
            </tr>
        </table>

        {{-- ============ DETAILED TABLE ============ --}}
        <h2 class="section page-break">{{ __('Detailed Task List') }} ({{ $tasks->count() }})</h2>
        <table class="data">
            <thead>
                <tr>
                    <th class="num" style="width:3%">#</th>
                    <th style="width:20%">{{ __('Task') }}</th>
                    <th style="width:12%">{{ __('Department') }}</th>
                    <th style="width:13%">{{ __('Assigned To') }}</th>
                    <th class="num" style="width:7%">{{ __('Priority') }}</th>
                    <th style="width:9%">{{ __('Stage') }}</th>
                    <th class="num" style="width:7%">{{ __('Status') }}</th>
                    <th class="num" style="width:7%">{{ __('Start Date') }}</th>
                    <th class="num" style="width:7%">{{ __('Due Date') }}</th>
                    <th class="num" style="width:5%">{{ __('Late') }}</th>
                    <th class="num" style="width:5%">{{ __('Progress') }}</th>
                    <th class="num" style="width:5%">{{ __('Est.') }}</th>
                    <th class="num" style="width:5%">{{ __('Actual') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($tasks as $i => $task)
                    <tr class="{{ $task->is_overdue ? 'row-late' : ($i % 2 ? 'zebra' : '') }}">
                        <td class="num">{{ $i + 1 }}</td>
                        <td>{{ $task->name }}</td>
                        <td>{{ $task->project_name }}</td>
                        <td>{{ $task->assignee_text }}</td>
                        <td class="num">
                            <span class="pill p-{{ $task->priority }}">{{ __($task->priority_label) }}</span>
                        </td>
                        <td>{{ $task->stage_name }}</td>
                        <td class="num">
                            @if ($task->is_done)
                                <span class="pill done">{{ __('Done') }}</span>
                            @elseif ($task->is_overdue)
                                <span class="pill late">{{ __('Late') }}</span>
                            @else
                                <span class="pill notdone">{{ __('Open') }}</span>
                            @endif
                        </td>
                        <td class="num">{{ $task->start_date ?: '-' }}</td>
                        <td class="num">{{ $task->end_date ?: '-' }}</td>
                        <td class="num" style="color:{{ $task->days_late > 0 ? '#c62828' : '#7b8794' }};font-weight:bold;">
                            {{ $task->days_late > 0 ? $task->days_late : '-' }}
                        </td>
                        <td class="num">{{ $task->progress }}%</td>
                        <td class="num">{{ $task->estimated_hrs }}</td>
                        <td class="num">{{ $task->actual_hrs }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="foot">
        {{ $meta['company_name'] }} &mdash; {{ __('Task Report') }} &mdash;
        {{ __('Period') }}: {{ $meta['period'] }} &mdash;
        {{ __('Generated on') }} {{ $meta['generated_at'] }} {{ __('by') }} {{ $meta['generated_by'] }}
    </div>
</div>

@if ($mode == 'pdf')
    <script src="{{ asset('js/html2pdf.bundle.min.js') }}"></script>
    <script>
        function downloadPdf() {
            var status = document.getElementById('tb-status');
            status.innerHTML = @json(__('Generating PDF, please wait...'));

            html2pdf()
                .set({
                    margin: [6, 6, 8, 6],
                    filename: 'task-report-{{ date('Y-m-d') }}.pdf',
                    image: { type: 'jpeg', quality: 0.98 },
                    html2canvas: { scale: 2, useCORS: true, letterRendering: true, scrollY: 0 },
                    jsPDF: { unit: 'mm', format: 'a4', orientation: 'landscape' },
                    pagebreak: { mode: ['css', 'legacy'], avoid: '.avoid-break' }
                })
                .from(document.getElementById('report-sheet'))
                .save()
                .then(function () {
                    status.innerHTML = @json(__('PDF downloaded.'));
                })
                .catch(function () {
                    status.innerHTML = @json(__('Could not build the PDF automatically - use Print instead.'));
                });
        }

        window.addEventListener('load', function () {
            setTimeout(downloadPdf, 400);
        });
    </script>
@endif

</body>

</html>
