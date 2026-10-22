@extends('layouts.admin')

@section('page-title')
    {{ __('Salary Structures') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ url('finance/dashboard') }}">{{ __('Finance') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Salary Structures') }}</li>
@endsection

@section('action-btn')
    <div class="float-end">
        <a href="{{ route('salary-structure.create') }}"
           data-bs-toggle="tooltip" title="{{ __('Create New Structure') }}"
           class="btn btn-sm btn-primary">
            <i class="ti ti-plus"></i>
        </a>
    </div>
@endsection

@section('content')
<div class="row">
    @forelse($structures as $s)
    <div class="col-md-4 col-sm-6">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h5 class="mb-1 fw-semibold">{{ $s->name }}</h5>
                        @if($s->status === 'active')
                            <span class="badge bg-success">{{ __('Active') }}</span>
                        @else
                            <span class="badge bg-secondary">{{ __('Inactive') }}</span>
                        @endif
                    </div>
                    <span class="bg-light-warning p-2 rounded">
                        <i class="ti ti-cash text-warning" style="font-size:22px"></i>
                    </span>
                </div>

                @if($s->description)
                    <p class="text-muted small mb-3">{{ Str::limit($s->description, 80) }}</p>
                @endif

                <div class="row g-2 mb-3">
                    <div class="col-4 text-center">
                        <div class="text-muted" style="font-size:11px">{{ __('Basic') }}</div>
                        <div class="fw-bold text-primary">{{ number_format($s->basic_salary, 0) }}</div>
                    </div>
                    <div class="col-4 text-center border-start border-end">
                        <div class="text-muted" style="font-size:11px">{{ __('Gross') }}</div>
                        <div class="fw-bold text-success">{{ number_format($s->total_gross, 0) }}</div>
                    </div>
                    <div class="col-4 text-center">
                        <div class="text-muted" style="font-size:11px">{{ __('Net') }}</div>
                        <div class="fw-bold text-info">{{ number_format($s->net_salary, 0) }}</div>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    @if($s->allowances)
                        <span class="badge bg-light-success text-success">
                            <i class="ti ti-plus-circle me-1"></i>{{ count($s->allowances) }} {{ __('Allowances') }}
                        </span>
                    @endif
                    @if($s->deductions)
                        <span class="badge bg-light-danger text-danger">
                            <i class="ti ti-minus-circle me-1"></i>{{ count($s->deductions) }} {{ __('Deductions') }}
                        </span>
                    @endif
                </div>
            </div>
            <div class="card-footer bg-transparent border-top-0 d-flex justify-content-end gap-2 pb-3">
                <a href="{{ route('salary-structure.edit', $s->id) }}" class="btn btn-sm bg-info text-white">
                    <i class="ti ti-pencil"></i>
                </a>
                {!! Form::open(['method' => 'DELETE', 'route' => ['salary-structure.destroy', $s->id], 'style' => 'display:inline']) !!}
                <button type="submit" class="btn btn-sm bg-danger text-white bs-pass-para"
                        data-bs-toggle="tooltip" title="{{ __('Delete') }}">
                    <i class="ti ti-trash"></i>
                </button>
                {!! Form::close() !!}
            </div>
        </div>
    </div>
    @empty
    <div class="col-12">
        <div class="card text-center py-5">
            <div class="card-body">
                <i class="ti ti-file-dollar" style="font-size:56px;color:#ccc"></i>
                <h5 class="mt-3">{{ __('No salary structures defined yet.') }}</h5>
                <p class="text-muted">{{ __('Create templates to standardize employee compensation.') }}</p>
                <a href="{{ route('salary-structure.create') }}" class="btn btn-primary mt-2">
                    <i class="ti ti-plus me-1"></i>{{ __('Create Structure') }}
                </a>
            </div>
        </div>
    </div>
    @endforelse
</div>
@endsection
