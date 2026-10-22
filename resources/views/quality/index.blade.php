@extends('layouts.admin')

@section('page-title')
    {{ __('Quality by Department') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('Quality by Department') }}</li>
@endsection

@section('action-btn')
    @can('view quality report')
        <div class="float-end">
            <a href="{{ route('quality.report') }}" class="btn btn-sm btn-primary btn-icon m-1"
               title="{{ __('Quality Report') }}">
                <i class="ti ti-chart-bar"></i> {{ __('Report') }}
            </a>
        </div>
    @endcan
@endsection

@section('content')
    <div class="row">

        <div class="col-12">
            <div class="row">
                <div class="col-md-4 col-6 mb-3">
                    <div class="card h-100 mb-0">
                        <div class="card-body py-3">
                            <h6 class="text-muted mb-1 small">{{ __('Departments producing work') }}</h6>
                            <h4 class="mb-0" dir="ltr">{{ $totals['active'] }}</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-6 mb-3">
                    <div class="card h-100 mb-0">
                        <div class="card-body py-3">
                            <h6 class="text-muted mb-1 small">{{ __('Awaiting review') }}</h6>
                            <h4 class="mb-0 text-warning" dir="ltr">{{ number_format($totals['pending']) }}</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-12 mb-3">
                    <div class="card h-100 mb-0">
                        <div class="card-body py-3">
                            <h6 class="text-muted mb-1 small">{{ __('Reviews recorded') }}</h6>
                            <h4 class="mb-0 text-success" dir="ltr">{{ number_format($totals['reviews']) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Departments') }}</h5>
                    <small class="text-muted">
                        {{ __('A department is judged on the tasks its projects complete. Departments with no completed tasks are listed last — their work is not tracked as project tasks.') }}
                    </small>
                </div>
                <div class="card-body table-border-style">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>{{ __('Department') }}</th>
                                    <th class="text-center">{{ __('Completed') }}</th>
                                    <th class="text-center">{{ __('Awaiting review') }}</th>
                                    <th class="text-center">{{ __('Reviewed') }}</th>
                                    <th class="text-center">{{ __('Average score') }}</th>
                                    <th class="text-center">{{ __('Setup') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($rows as $row)
                                    <tr class="{{ $row['is_active'] ? '' : 'text-muted' }}">
                                        <td>
                                            <strong>{{ $row['name'] }}</strong>
                                            @unless ($row['is_active'])
                                                <span class="badge bg-light text-muted ms-1">{{ __('No task activity') }}</span>
                                            @endunless
                                        </td>
                                        <td class="text-center" dir="ltr">{{ number_format($row['completed']) }}</td>
                                        <td class="text-center" dir="ltr">
                                            @if ($row['pending'] > 0)
                                                <span class="badge bg-warning">{{ number_format($row['pending']) }}</span>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="text-center" dir="ltr">{{ number_format($row['reviews']) }}</td>
                                        <td class="text-center" dir="ltr">
                                            @if ($row['avg_score'] === null)
                                                —
                                            @else
                                                @php
                                                    $band = $row['avg_score'] >= 85 ? 'success'
                                                        : ($row['avg_score'] >= 70 ? 'info'
                                                        : ($row['avg_score'] >= 50 ? 'warning' : 'danger'));
                                                @endphp
                                                <span class="badge bg-{{ $band }}">{{ $row['avg_score'] }}%</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if ($row['criteria'] === 0)
                                                <span class="badge bg-light text-danger">{{ __('No criteria') }}</span>
                                            @elseif ($row['reviewers'] === 0)
                                                <span class="badge bg-light text-warning">{{ __('No reviewer') }}</span>
                                            @else
                                                <i class="ti ti-check text-success"></i>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('quality.department', $row['id']) }}"
                                               class="btn btn-sm btn-outline-primary">
                                                {{ __('Open') }}
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            {{ __('You are not named as a reviewer for any department yet.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection
