@extends('layouts.admin')

@section('page-title')
    {{ __('Inactive Accounts Report') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('Inactive Accounts Report') }}</li>
@endsection

@section('action-btn')
    @can('manage inactivity ban')
    <div class="float-end">
        <form method="POST" action="{{ route('inactivity.run') }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-sm btn-primary btn-icon m-1"
                    title="{{ __('Generate the report now and email it') }}">
                <i class="ti ti-refresh"></i> {{ __('Run now') }}
            </button>
        </form>
    </div>
    @endcan
@endsection

@section('content')
    <div class="row">

        {{-- شريط الحالة: أهم عنصر في الصفحة — يمنع أي التباس حول ما إذا كان الحظر شغّالًا فعلًا --}}
        <div class="col-12">
            @if ($report['enforcing'])
                <div class="alert alert-danger d-flex align-items-center" role="alert">
                    <i class="ti ti-shield-lock me-2 fs-5"></i>
                    <div>
                        <strong>{{ __('Enforcement is ON') }}.</strong>
                        {{ __('Accounts that pass the threshold are blocked automatically every Saturday.') }}
                    </div>
                </div>
            @else
                <div class="alert alert-warning d-flex align-items-center" role="alert">
                    <i class="ti ti-eye me-2 fs-5"></i>
                    <div>
                        <strong>{{ __('Report-only mode') }}.</strong>
                        {{ __('No account is blocked. Review the list below, then turn enforcement on when you are ready.') }}
                    </div>
                </div>
            @endif
        </div>

        {{-- بطاقات العدّ --}}
        @foreach ([
            ['candidates', __('Inactive'), 'danger', 'ti-user-off'],
            ['warning',    __('Approaching'), 'warning', 'ti-alert-triangle'],
            ['banned',     __('Blocked'), 'dark', 'ti-lock'],
            ['exempt',     __('Exempt'), 'info', 'ti-shield-check'],
            ['active',     __('Active'), 'success', 'ti-user-check'],
        ] as [$key, $label, $colour, $icon])
            <div class="col-lg col-md-4 col-6">
                <div class="card">
                    <div class="card-body text-center py-3">
                        <i class="ti {{ $icon }} text-{{ $colour }} fs-4"></i>
                        <h4 class="mt-1 mb-0 text-{{ $colour }}" dir="ltr">{{ count($report[$key]) }}</h4>
                        <small class="text-muted">{{ $label }}</small>
                    </div>
                </div>
            </div>
        @endforeach

        {{-- الإعدادات --}}
        @can('manage inactivity ban')
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">{{ __('Rules') }}</h5>
                    <button class="btn btn-sm btn-outline-secondary" type="button"
                            data-bs-toggle="collapse" data-bs-target="#inactivitySettings">
                        <i class="ti ti-settings"></i> {{ __('Edit') }}
                    </button>
                </div>
                <div class="collapse" id="inactivitySettings">
                    <div class="card-body">
                        <form method="POST" action="{{ route('inactivity.settings') }}">
                            @csrf
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">{{ __('Idle days before blocking') }}</label>
                                    <input type="number" name="inactivity_threshold_days" class="form-control" dir="ltr"
                                           min="1" max="365" value="{{ $settings['inactivity_threshold_days'] }}" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">{{ __('Grace period for new accounts (days)') }}</label>
                                    <input type="number" name="inactivity_grace_days" class="form-control" dir="ltr"
                                           min="0" max="365" value="{{ $settings['inactivity_grace_days'] }}" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">{{ __('Report recipients (comma separated)') }}</label>
                                    <input type="text" name="inactivity_report_emails" class="form-control" dir="ltr"
                                           value="{{ $settings['inactivity_report_emails'] }}">
                                </div>
                                <div class="col-12 mb-3">
                                    <label class="form-label">{{ __('Exempt roles (comma separated)') }}</label>
                                    <input type="text" name="inactivity_exempt_roles" class="form-control" dir="ltr"
                                           value="{{ $settings['inactivity_exempt_roles'] }}">
                                </div>
                                <div class="col-12 mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch"
                                               id="enforceSwitch" name="inactivity_enforce" value="1"
                                               {{ $report['enforcing'] ? 'checked' : '' }}>
                                        <label class="form-check-label" for="enforceSwitch">
                                            <strong>{{ __('Block inactive accounts automatically') }}</strong>
                                            <span class="text-danger d-block small">
                                                {{ __('Leave this off until you have reviewed the list — it currently affects') }}
                                                <span dir="ltr">{{ count($report['candidates']) }}</span>
                                                {{ __('accounts.') }}
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
                        </form>
                    </div>
                </div>
                <div class="card-footer text-muted small">
                    {{ __('Threshold') }}: <span dir="ltr">{{ $report['threshold'] }}</span> {{ __('days') }} ·
                    {{ __('Grace') }}: <span dir="ltr">{{ $report['grace'] }}</span> {{ __('days') }} ·
                    {{ __('Last run') }}:
                    <span dir="ltr">{{ $settings['inactivity_last_run_at'] ?: '—' }}</span>
                </div>
            </div>
        </div>
        @endcan

        {{-- الجداول --}}
        @foreach ([
            ['banned',     __('Blocked for inactivity'),   'dark'],
            ['candidates', __('Inactive — met the threshold'), 'danger'],
            ['warning',    __('Approaching the threshold'), 'warning'],
            ['exempt',     __('Exempt'),                    'info'],
        ] as [$key, $title, $colour])
            @if (count($report[$key]))
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0 text-{{ $colour }}">
                                {{ $title }} <span class="badge bg-{{ $colour }}" dir="ltr">{{ count($report[$key]) }}</span>
                            </h5>
                        </div>
                        <div class="card-body table-border-style">
                            <div class="table-responsive">
                                <table class="table datatable">
                                    <thead>
                                        <tr>
                                            <th>{{ __('Name') }}</th>
                                            <th>{{ __('Role') }}</th>
                                            <th>{{ __('Last login') }}</th>
                                            <th>{{ __('Last app use') }}</th>
                                            <th>{{ __('Idle days') }}</th>
                                            @if ($key === 'exempt')
                                                <th>{{ __('Reason') }}</th>
                                            @endif
                                            <th class="text-end">{{ __('Action') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($report[$key] as $r)
                                            <tr>
                                                <td>
                                                    {{ $r['name'] }}
                                                    <div class="text-muted small" dir="ltr">{{ $r['email'] }}</div>
                                                </td>
                                                <td dir="ltr">{{ $r['type'] ?: '—' }}</td>
                                                <td dir="ltr">
                                                    @if (!$r['last_login_at'])
                                                        <span class="text-danger">{{ __('Never') }}</span>
                                                    @else
                                                        {{ $r['last_login_at'] }}
                                                    @endif
                                                </td>
                                                {{-- عمود مستقل عمدًا: هو الذي يكشف مستخدمي التطبيق
                                                     الذين يبدون غائبين شهورًا في عمود دخول الويب --}}
                                                <td dir="ltr">
                                                    @if ($r['token_used_at'])
                                                        <span class="text-success">{{ $r['token_used_at'] }}</span>
                                                    @else
                                                        <span class="text-muted">—</span>
                                                    @endif
                                                </td>
                                                <td dir="ltr"><strong class="text-{{ $colour }}">{{ $r['idle_days'] }}</strong></td>
                                                @if ($key === 'exempt')
                                                    <td class="small text-muted">{{ $r['reason'] ?? '—' }}</td>
                                                @endif
                                                <td class="text-end">
                                                    @can('manage inactivity ban')
                                                    @if ($key === 'banned')
                                                        <form method="POST" action="{{ route('inactivity.unban', $r['id']) }}" class="d-inline">
                                                            @csrf
                                                            <button class="btn btn-sm btn-success">
                                                                <i class="ti ti-lock-open"></i> {{ __('Restore login') }}
                                                            </button>
                                                        </form>
                                                    @endif
                                                    <form method="POST" action="{{ route('inactivity.exempt', $r['id']) }}" class="d-inline">
                                                        @csrf
                                                        <button class="btn btn-sm btn-outline-secondary">
                                                            @if (in_array($r['id'], $exemptUsers, true))
                                                                <i class="ti ti-shield-off"></i> {{ __('Unpin') }}
                                                            @else
                                                                <i class="ti ti-shield-check"></i> {{ __('Exempt') }}
                                                            @endif
                                                        </button>
                                                    </form>
                                                    @endcan
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        @endforeach

        {{-- سجلّ التدقيق --}}
        @if (count($recent))
            <div class="col-12">
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">{{ __('Recent activity') }}</h5></div>
                    <div class="card-body table-border-style">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>{{ __('When') }}</th>
                                        <th>{{ __('Name') }}</th>
                                        <th>{{ __('Action') }}</th>
                                        <th>{{ __('Reason') }}</th>
                                        <th>{{ __('By') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($recent as $l)
                                        <tr>
                                            <td dir="ltr">{{ $l->created_at }}</td>
                                            <td>{{ $l->user_name ?: '#' . $l->user_id }}</td>
                                            <td><span class="badge bg-secondary" dir="ltr">{{ $l->action }}</span></td>
                                            <td class="small text-muted">{{ $l->reason ?: '—' }}</td>
                                            <td dir="ltr">{{ $l->actor }}</td>
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
