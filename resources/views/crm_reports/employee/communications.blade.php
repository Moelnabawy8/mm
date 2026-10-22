@include('crm_reports.employee._head', [
    'title' => __('Activity log'),
    'definition' => __('Sales meetings and filled-in quality evaluations during the period, with the customer and the details. A meeting is dated by its meeting date and counts for the employee recorded on it (older meetings: the employee the lead is assigned to); an evaluation counts for the employee who filled it in.'),
])

@php
    $ER = \App\Services\CrmReports\EmployeeReport::class;

    $kindLabels = ['meeting' => __('Sales meetings'), 'evaluation' => __('Evaluations')];
    $emps = collect($perEmployee);

    $workSpec = ['type' => 'bar', 'horizontal' => true, 'stacked' => true,
        'categories' => $emps->pluck('name')->values()->all(),
        'series' => [
            ['name' => __('Call meeting'), 'color' => 'cat1', 'data' => $emps->pluck('call')->values()->all()],
            ['name' => __('Presentation meeting'), 'color' => 'cat2', 'data' => $emps->pluck('presentation')->values()->all()],
            ['name' => __('Other meetings'), 'color' => 'cat3', 'data' => $emps->pluck('other')->values()->all()],
            ['name' => __('Evaluations'), 'color' => 'cat4', 'data' => $emps->pluck('evaluation')->values()->all()],
        ]];
    // Drop series that are empty for everyone (e.g. no "other" meeting types).
    $workSpec['series'] = array_values(array_filter($workSpec['series'], fn ($s) => array_sum($s['data']) > 0));

    $days = [__('Mon'), __('Tue'), __('Wed'), __('Thu'), __('Fri'), __('Sat'), __('Sun')];
    $heatSpec = ['type' => 'heatmap', 'height' => 280, 'categories' => array_map(fn ($h) => sprintf('%02d', $h), range(0, 23)),
        'series' => array_reverse(array_map(fn ($wd) => ['name' => $days[$wd], 'data' => $heatmap[$wd]], range(0, 6)))];
    $hasHeat = array_sum(array_map('array_sum', $heatmap)) > 0;
    $hasOther = $emps->sum('other') > 0;
@endphp

<form data-section-filter class="crm-no-print mb-3 d-flex align-items-center gap-2 flex-wrap">
    <label class="small text-muted mb-0" for="crm-kind">{{ __('Show') }}</label>
    <select name="kind" id="crm-kind" class="form-select form-select-sm" style="width:auto">
        <option value="">{{ __('Everything') }}</option>
        @foreach ($kindLabels as $k => $label)
            <option value="{{ $k }}" @selected($type === $k)>{{ $label }}</option>
        @endforeach
    </select>
</form>

@if ($emps->isEmpty())
    <div class="crm-empty">
        <i class="ti ti-calendar-off"></i>
        {{ __('No sales meetings or evaluations in this period.') }}
    </div>
@else
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card h-100 mb-0">
                <div class="card-header border-0 pb-0"><h6 class="crm-card-title">{{ __('Per employee') }}</h6></div>
                <div class="card-body pt-2">
                    <div class="table-responsive crm-table-wrap">
                        <table class="table crm-table table-hover">
                            <thead>
                            <tr>
                                <th data-sort="text">{{ __('Employee') }}</th>
                                <th class="num" data-sort="num">{{ __('Call meeting') }}</th>
                                <th class="num" data-sort="num">{{ __('Presentation meeting') }}</th>
                                @if ($hasOther)
                                    <th class="num" data-sort="num">{{ __('Other meetings') }}</th>
                                @endif
                                <th class="num" data-sort="num">{{ __('Evaluations') }}</th>
                                <th class="num" data-sort="num">{{ __('Potential Lead') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($emps as $e)
                                <tr>
                                    <td>{{ $e['name'] }}</td>
                                    <td class="num" data-v="{{ $e['call'] }}">{{ number_format($e['call']) }}</td>
                                    <td class="num" data-v="{{ $e['presentation'] }}">{{ number_format($e['presentation']) }}</td>
                                    @if ($hasOther)
                                        <td class="num" data-v="{{ $e['other'] }}">{{ number_format($e['other']) }}</td>
                                    @endif
                                    <td class="num" data-v="{{ $e['evaluation'] }}">{{ number_format($e['evaluation']) }}</td>
                                    <td class="num" data-v="{{ $e['potential'] }}">{{ number_format($e['potential']) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                            <tfoot>
                            <tr>
                                <td>{{ __('Total') }}</td>
                                <td class="num">{{ number_format($emps->sum('call')) }}</td>
                                <td class="num">{{ number_format($emps->sum('presentation')) }}</td>
                                @if ($hasOther)
                                    <td class="num">{{ number_format($emps->sum('other')) }}</td>
                                @endif
                                <td class="num">{{ number_format($emps->sum('evaluation')) }}</td>
                                <td class="num">{{ number_format($emps->sum('potential')) }}</td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div class="crm-card-sub mt-2">{{ __('Potential Lead = evaluations that marked the customer as a potential lead.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100 mb-0">
                <div class="card-header border-0 pb-0">
                    <h6 class="crm-card-title">{{ __('Work per employee') }}</h6>
                    <div class="crm-card-sub">{{ __('Sales meetings by type, and evaluations') }}</div>
                </div>
                <div class="card-body pt-2"><div class="crm-chart" data-spec="{{ json_encode($workSpec) }}"></div></div>
            </div>
        </div>
    </div>
@endif

<div class="row g-3 mt-0">
    <div class="col-12">
        <div class="card mb-0">
            <div class="card-header border-0 pb-0">
                <h6 class="crm-card-title">{{ __('When the team works leads') }}</h6>
                <div class="crm-card-sub">{{ __('Sales meetings booked and evaluations filled in, by weekday and hour') }}</div>
            </div>
            <div class="card-body pt-2">
                @if ($hasHeat)
                    <div class="crm-chart" data-spec="{{ json_encode($heatSpec) }}"></div>
                @else
                    <div class="crm-empty"><i class="ti ti-calendar-time"></i>{{ __('No activity in this period.') }}</div>
                @endif
            </div>
        </div>
    </div>

    @if ($emps->isNotEmpty())
        <div class="col-12">
            <div class="card mb-0">
                <div class="card-header border-0 pb-0">
                    <h6 class="crm-card-title">{{ __('Activity log') }}</h6>
                    <div class="crm-card-sub">{{ __('Newest first') }} · {{ number_format($log->total()) }} {{ __('entries') }}</div>
                </div>
                <div class="card-body pt-2">
                    <div class="table-responsive">
                        <table class="table crm-table table-hover">
                            <thead>
                            <tr>
                                <th>{{ __('Date') }}</th>
                                <th>{{ __('Employee') }}</th>
                                <th>{{ __('Type') }}</th>
                                <th>{{ __('Lead #') }}</th>
                                <th>{{ __('Customer') }}</th>
                                <th>{{ __('Status / result') }}</th>
                                <th>{{ __('Details') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($log as $ev)
                                @php
                                    $isMeeting = $ev->kind === 'meeting';
                                    $result = $isMeeting ? $ER::meetingStatusLabel($ev->detail) : $ER::evaluationLabel($ev->outcome, $ev->detail);
                                    $badge = $isMeeting
                                        ? (['done' => 'bg-light-success text-success', 'no_show' => 'bg-light-danger text-danger', 'cancelled' => 'bg-light-warning text-warning'][$ev->detail] ?? 'bg-light text-dark')
                                        : ($ev->outcome === 'yes' ? 'bg-light-success text-success' : ($ev->outcome === 'no' ? 'bg-light-danger text-danger' : 'bg-light text-dark'));
                                @endphp
                                <tr>
                                    <td class="text-nowrap">{{ \Carbon\Carbon::parse($ev->created_at)->format('Y-m-d H:i') }}</td>
                                    <td>{{ $ev->actor_name }}</td>
                                    <td class="text-nowrap">
                                        <i class="ti {{ $isMeeting ? 'ti-calendar-event' : 'ti-clipboard-check' }}"></i>
                                        {{ $isMeeting ? $ER::meetingTypeLabel($ev->outcome) : __('Evaluation') }}
                                    </td>
                                    <td><a href="{{ route('leads.show', $ev->lead_id) }}" target="_blank" rel="noopener">#{{ $ev->lead_id }}</a></td>
                                    <td>{{ $ev->lead_name }} <div class="small text-muted" dir="ltr">{{ $ev->lead_phone }}</div></td>
                                    <td class="text-nowrap">
                                        @if ($result !== '')<span class="badge {{ $badge }}">{{ $result }}</span>@else – @endif
                                    </td>
                                    <td style="max-width: 420px">
                                        @if ($ev->title)<div class="fw-semibold">{{ $ev->title }}</div>@endif
                                        <div class="small text-muted text-truncate" style="max-width: 420px" title="{{ $ev->body }}">{{ \Illuminate\Support\Str::limit($ev->body, 160) }}</div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-2 crm-no-print">{{ $log->appends(request()->except('page'))->links('pagination::bootstrap-5') }}</div>
                </div>
            </div>
        </div>
    @endif
</div>
