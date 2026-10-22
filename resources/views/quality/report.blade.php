@extends('layouts.admin')

@section('page-title')
    {{ __('Quality Report') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('quality.index') }}">{{ __('Quality by Department') }}</a></li>
    <li class="breadcrumb-item">{{ __('Quality Report') }}</li>
@endsection

@section('content')
    <div class="row">

        <div class="col-12">
            <div class="card">
                <div class="card-body py-3">
                    <form method="GET" action="{{ route('quality.report') }}" class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label small mb-0">{{ __('From') }}</label>
                            <input type="date" name="from" class="form-control form-control-sm" dir="ltr"
                                   value="{{ $from }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small mb-0">{{ __('To') }}</label>
                            <input type="date" name="to" class="form-control form-control-sm" dir="ltr"
                                   value="{{ $to }}">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-sm btn-primary w-100">{{ __('Apply') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Scores by department') }}</h5>
                    <small class="text-muted">{{ __('Reviews recorded between the selected dates.') }}</small>
                </div>
                <div class="card-body table-border-style">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('Department') }}</th>
                                    <th class="text-center">{{ __('Reviews') }}</th>
                                    <th class="text-center">{{ __('Average score') }}</th>
                                    <th class="text-center">{{ __('Lowest') }}</th>
                                    <th class="text-center">{{ __('Highest') }}</th>
                                    <th class="text-center">{{ __('On time') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($rows as $r)
                                    @php
                                        $band = $r->avg_score >= 85 ? 'success'
                                            : ($r->avg_score >= 70 ? 'info'
                                            : ($r->avg_score >= 50 ? 'warning' : 'danger'));
                                    @endphp
                                    <tr>
                                        <td>
                                            <a href="{{ route('quality.department', $r->id) }}">{{ $r->name }}</a>
                                        </td>
                                        <td class="text-center" dir="ltr">{{ number_format($r->reviews) }}</td>
                                        <td class="text-center" dir="ltr">
                                            <span class="badge bg-{{ $band }}">{{ $r->avg_score }}%</span>
                                        </td>
                                        <td class="text-center text-muted" dir="ltr">{{ round($r->worst) }}%</td>
                                        <td class="text-center text-muted" dir="ltr">{{ round($r->best) }}%</td>
                                        <td class="text-center" dir="ltr">
                                            {{ $r->reviews ? round($r->on_time / $r->reviews * 100) : 0 }}%
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            {{ __('No reviews were recorded in this period.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- التغطية منفصلة عن الدرجة عمدًا: قسم بمتوسط 95% ومراجعة واحدة من أربعين مهمة
             ليس قسمًا ممتازًا، بل قسمًا غير مُراجَع. --}}
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Review coverage') }}</h5>
                    <small class="text-muted">
                        {{ __('A high score on very few reviews says little. This is how much of the completed work was actually looked at.') }}
                    </small>
                </div>
                <div class="card-body table-border-style">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('Department') }}</th>
                                    <th class="text-center">{{ __('Completed') }}</th>
                                    <th class="text-center">{{ __('Reviewed') }}</th>
                                    <th class="text-center">{{ __('Awaiting review') }}</th>
                                    <th style="min-width:140px">{{ __('Coverage') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($board as $b)
                                    @continue(!$b['is_active'])
                                    @php
                                        $cov = $b['completed'] > 0 ? round($b['reviews'] / $b['completed'] * 100) : 0;
                                    @endphp
                                    <tr>
                                        <td>{{ $b['name'] }}</td>
                                        <td class="text-center" dir="ltr">{{ number_format($b['completed']) }}</td>
                                        <td class="text-center" dir="ltr">{{ number_format($b['reviews']) }}</td>
                                        <td class="text-center" dir="ltr">{{ number_format($b['pending']) }}</td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="progress flex-grow-1" style="height:6px" dir="ltr">
                                                    <div class="progress-bar bg-{{ $cov >= 70 ? 'success' : ($cov >= 30 ? 'warning' : 'danger') }}"
                                                         style="width: {{ min($cov, 100) }}%"></div>
                                                </div>
                                                <span class="ms-2 small" dir="ltr">{{ $cov }}%</span>
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

    </div>
@endsection
