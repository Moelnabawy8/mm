@extends('layouts.admin')

@section('page-title'){{ __('Weekly Commitments') }}@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('Weekly Commitments') }}</li>
@endsection

@section('action-btn')
    <div class="float-end">
        <a href="{{ route('meetings.board') }}" class="btn btn-sm btn-light btn-icon m-1">
            <i class="ti ti-notes"></i> {{ __('Meeting minutes') }}
        </a>
        @if (Gate::check('create commitment') || Gate::check('manage commitment'))
            <a href="{{ route('commitments.create', ['week' => $week]) }}" class="btn btn-sm btn-primary btn-icon m-1">
                <i class="ti ti-plus"></i> {{ __('New commitment') }}
            </a>
        @endif
    </div>
@endsection

@section('content')
    <div class="row">

        {{-- التنقل بين الأسابيع أولًا: الشاشة بلا معنى بدون معرفة أي أسبوع معروض --}}
        <div class="col-12">
            <div class="card">
                <div class="card-body py-2 d-flex justify-content-between align-items-center flex-wrap">
                    <a href="{{ route('commitments.index', ['week' => $prevWeek]) }}" class="btn btn-sm btn-light">
                        <i class="ti ti-chevron-right"></i> {{ __('Previous week') }}
                    </a>
                    <div class="text-center">
                        <strong dir="ltr">
                            {{ \Carbon\Carbon::parse($week)->format('Y-m-d') }}
                            &ndash;
                            {{ \Carbon\Carbon::parse($week)->addDays(6)->format('Y-m-d') }}
                        </strong>
                        @if ($isCurrentWeek)
                            <span class="badge bg-primary ms-1">{{ __('This week') }}</span>
                        @else
                            <a href="{{ route('commitments.index') }}" class="small d-block">{{ __('Back to this week') }}</a>
                        @endif
                    </div>
                    <a href="{{ route('commitments.index', ['week' => $nextWeek]) }}" class="btn btn-sm btn-light">
                        {{ __('Next week') }} <i class="ti ti-chevron-left"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-12">@include('commitments._tally')</div>

        {{-- تاسك 4 معروض هنا عمدًا: الاجتماع بلا محضر قرارٌ لم يصبح التزامًا --}}
        @if ($meetingStats['missing'] > 0)
            <div class="col-12">
                <div class="alert alert-warning d-flex align-items-center justify-content-between flex-wrap">
                    <div class="d-flex align-items-center">
                        <i class="ti ti-notes-off me-2 fs-5"></i>
                        <div>
                            <strong>{{ $meetingStats['missing'] }} {{ __('meetings were held with no minutes written.') }}</strong>
                            <div class="small">{{ __('A decision nobody wrote down is a commitment nobody owes.') }}</div>
                        </div>
                    </div>
                    <a href="{{ route('meetings.board') }}" class="btn btn-sm btn-warning mt-2 mt-md-0">
                        {{ __('Write them up') }}
                    </a>
                </div>
            </div>
        @endif

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Commitments this week') }}</h5>
                </div>
                <div class="card-body table-border-style">
                    @include('commitments._table', ['show' => 'owner', 'emptyText' => __('No commitments recorded for this week.')])
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">{{ __('By department') }}</h5></div>
                <div class="card-body table-border-style">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr>
                                <th>{{ __('Department') }}</th>
                                <th class="text-center">{{ __('Open') }}</th>
                                <th class="text-center">{{ __('Overdue') }}</th>
                                <th class="text-center">{{ __('Kept rate') }}</th>
                            </tr></thead>
                            <tbody>
                                @forelse ($byDepartment as $d)
                                    <tr>
                                        <td class="small">{{ $d['name'] }}</td>
                                        <td class="text-center small" dir="ltr">{{ $d['open'] }}</td>
                                        <td class="text-center small" dir="ltr">
                                            @if ($d['overdue'])
                                                <span class="badge bg-danger">{{ $d['overdue'] }}</span>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="text-center small" dir="ltr">
                                            {{ $d['kept_rate'] === null ? '—' : $d['kept_rate'] . '%' }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted py-3">{{ __('Nothing this week.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Supervisors') }}</h5>
                    <small class="text-muted">{{ __('Who is being committed to, across all weeks.') }}</small>
                </div>
                <div class="card-body table-border-style">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr>
                                <th>{{ __('Name') }}</th>
                                <th class="text-center">{{ __('Open') }}</th>
                                <th class="text-center">{{ __('Kept') }}</th>
                                <th class="text-center">{{ __('Missed') }}</th>
                            </tr></thead>
                            <tbody>
                                @forelse ($supervisors as $s)
                                    <tr>
                                        <td class="small">{{ $s['name'] }}</td>
                                        <td class="text-center small" dir="ltr">{{ $s['open'] }}</td>
                                        <td class="text-center small text-success" dir="ltr">{{ $s['done'] }}</td>
                                        <td class="text-center small {{ $s['missed'] ? 'text-danger' : '' }}" dir="ltr">{{ $s['missed'] }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted py-3">{{ __('Nobody yet.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection
