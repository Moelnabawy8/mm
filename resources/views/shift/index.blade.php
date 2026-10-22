@extends('layouts.admin')

@section('page-title')
    {{ __('Shifts Management') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ url('finance/dashboard') }}">{{ __('Finance') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Shifts') }}</li>
@endsection

@section('action-btn')
    <div class="float-end">
        <a href="#" data-url="{{ route('shift.create') }}" data-ajax-popup="true" data-size="lg"
           data-title="{{ __('Create Shift') }}" data-bs-toggle="tooltip" title="{{ __('Create') }}"
           class="btn btn-sm btn-primary">
            <i class="ti ti-plus"></i>
        </a>
    </div>
@endsection

@section('content')
<div class="row">
    @forelse($shifts as $shift)
    <div class="col-md-4 col-sm-6">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h5 class="mb-1 fw-semibold">{{ $shift->name }}</h5>
                        @if($shift->status === 'active')
                            <span class="badge bg-success">{{ __('Active') }}</span>
                        @else
                            <span class="badge bg-secondary">{{ __('Inactive') }}</span>
                        @endif
                    </div>
                    <span class="bg-light-primary p-2 rounded">
                        <i class="ti ti-clock text-primary" style="font-size:22px"></i>
                    </span>
                </div>

                <div class="row text-center mb-3">
                    <div class="col-4 border-end">
                        <div class="text-muted small">{{ __('Start') }}</div>
                        <div class="fw-bold">{{ \Carbon\Carbon::createFromTimeString($shift->start_time)->format('h:i A') }}</div>
                    </div>
                    <div class="col-4 border-end">
                        <div class="text-muted small">{{ __('End') }}</div>
                        <div class="fw-bold">{{ \Carbon\Carbon::createFromTimeString($shift->end_time)->format('h:i A') }}</div>
                    </div>
                    <div class="col-4">
                        <div class="text-muted small">{{ __('Duration') }}</div>
                        <div class="fw-bold">{{ $shift->duration }}</div>
                    </div>
                </div>

                @if($shift->working_days)
                <div class="mb-3">
                    @foreach(explode(',', $shift->working_days) as $day)
                        <span class="badge bg-light-info text-info me-1">{{ $day }}</span>
                    @endforeach
                </div>
                @endif

                @if($shift->break_duration > 0)
                <div class="text-muted small mb-3">
                    <i class="ti ti-coffee me-1"></i>{{ __('Break') }}: {{ $shift->break_duration }} {{ __('min') }}
                </div>
                @endif

                <div class="d-flex justify-content-end gap-2">
                    <a href="#"
                       data-url="{{ route('shift.edit', $shift->id) }}"
                       data-ajax-popup="true" data-size="lg"
                       data-title="{{ __('Edit Shift') }}"
                       class="btn btn-sm bg-info text-white">
                        <i class="ti ti-pencil"></i>
                    </a>
                    {!! Form::open(['method' => 'DELETE', 'route' => ['shift.destroy', $shift->id], 'style' => 'display:inline']) !!}
                    <button type="submit" class="btn btn-sm bg-danger text-white bs-pass-para"
                            data-bs-toggle="tooltip" title="{{ __('Delete') }}">
                        <i class="ti ti-trash"></i>
                    </button>
                    {!! Form::close() !!}
                </div>
            </div>
        </div>
    </div>
    @empty
    <div class="col-12">
        <div class="card text-center py-5">
            <div class="card-body">
                <i class="ti ti-clock-off" style="font-size:56px;color:#ccc"></i>
                <h5 class="mt-3">{{ __('No shifts defined yet.') }}</h5>
                <p class="text-muted">{{ __('Create your first shift to start allocating employees.') }}</p>
            </div>
        </div>
    </div>
    @endforelse
</div>
@endsection
