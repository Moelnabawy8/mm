@extends('layouts.admin')

@section('page-title'){{ __('Meeting Minutes') }}@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('Meeting Minutes') }}</li>
@endsection

@section('action-btn')
    @can('manage meeting')
        <div class="float-end">
            <a href="{{ route('meeting.index') }}" class="btn btn-sm btn-light btn-icon m-1">
                <i class="ti ti-calendar"></i> {{ __('All meetings') }}
            </a>
            <a href="{{ route('meeting.create') }}" class="btn btn-sm btn-primary btn-icon m-1">
                <i class="ti ti-plus"></i> {{ __('Schedule a meeting') }}
            </a>
        </div>
    @endcan
@endsection

@section('content')
    <div class="row">

        <div class="col-12">
            <div class="row">
                <div class="col-md-3 col-6 mb-3">
                    <div class="card h-100 mb-0"><div class="card-body py-3">
                        <h6 class="text-muted mb-1 small">{{ __('Awaiting minutes') }}</h6>
                        <h4 class="mb-0 {{ $stats['missing'] ? 'text-danger' : '' }}" dir="ltr">
                            {{ number_format($stats['missing']) }}
                        </h4>
                        <small class="text-muted">{{ __('meetings already held') }}</small>
                    </div></div>
                </div>
                <div class="col-md-3 col-6 mb-3">
                    <div class="card h-100 mb-0"><div class="card-body py-3">
                        <h6 class="text-muted mb-1 small">{{ __('Written up') }}</h6>
                        <h4 class="mb-0 text-success" dir="ltr">{{ number_format($stats['documented']) }}</h4>
                        <small class="text-muted">{{ __('of') }} <span dir="ltr">{{ number_format($stats['held']) }}</span> {{ __('held') }}</small>
                    </div></div>
                </div>
                <div class="col-md-3 col-6 mb-3">
                    <div class="card h-100 mb-0"><div class="card-body py-3">
                        <h6 class="text-muted mb-1 small">{{ __('Documented rate') }}</h6>
                        <h4 class="mb-0" dir="ltr">{{ $stats['rate'] === null ? '—' : $stats['rate'] . '%' }}</h4>
                        <small class="text-muted">{{ __('last 90 days') }}</small>
                    </div></div>
                </div>
                <div class="col-md-3 col-6 mb-3">
                    <div class="card h-100 mb-0"><div class="card-body py-3">
                        <h6 class="text-muted mb-1 small">{{ __('Coming up') }}</h6>
                        <h4 class="mb-0" dir="ltr">{{ number_format($stats['upcoming']) }}</h4>
                        <small class="text-muted">{{ __('next 14 days') }}</small>
                    </div></div>
                </div>
            </div>
        </div>

        {{-- الاجتماعات التي مضت بلا محضر أولًا: هذا هو الرقم الذي توجد الميزة لتخفيضه --}}
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-start">
                    <div>
                        <h5 class="mb-0">{{ __('Held, but not written up') }}</h5>
                        <small class="text-muted">
                            {{ __('A decision nobody wrote down is a commitment nobody owes.') }}
                        </small>
                    </div>
                    @if (count($awaiting))
                        <span class="badge bg-danger">{{ count($awaiting) }}</span>
                    @endif
                </div>
                <div class="card-body table-border-style">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr>
                                <th>{{ __('Meeting') }}</th>
                                <th>{{ __('Date') }}</th>
                                <th></th>
                            </tr></thead>
                            <tbody>
                                @forelse ($awaiting as $m)
                                    <tr>
                                        <td>{{ $m->title }}</td>
                                        <td class="small" dir="ltr">
                                            @php $on = $m->dateOn(); @endphp
                                            {{ $on ? $on->format('Y-m-d') : '—' }}
                                            <span class="text-danger d-block">
                                                {{ $on ? (int) $on->diffInDays(now()) : 0 }} {{ __('days ago') }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('meetings.minutes', $m->id) }}"
                                               class="btn btn-sm btn-primary">{{ __('Write up') }}</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-muted py-4">
                                        @if ($stats['held'] === 0)
                                            {{ __('No meetings have been held in the last 90 days.') }}
                                        @else
                                            {{ __('Every meeting held has been written up.') }}
                                        @endif
                                    </td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">{{ __('Coming up') }}</h5></div>
                <div class="card-body table-border-style">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr>
                                <th>{{ __('Meeting') }}</th>
                                <th>{{ __('When') }}</th>
                                <th></th>
                            </tr></thead>
                            <tbody>
                                @forelse ($upcoming as $m)
                                    <tr>
                                        <td class="small">{{ $m->title }}</td>
                                        <td class="small" dir="ltr">
                                            {{ optional($m->dateOn())->format('Y-m-d') ?: '—' }} {{ $m->time }}
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('meetings.minutes', $m->id) }}"
                                               class="btn btn-sm btn-outline-primary">{{ __('Open') }}</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-muted py-3">
                                        {{ __('Nothing scheduled in the next 14 days.') }}
                                    </td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection
