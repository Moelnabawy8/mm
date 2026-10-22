@extends('layouts.admin')

@section('page-title')
    {{ __('Allocated Shifts') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ url('finance/dashboard') }}">{{ __('Finance') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Allocated Shifts') }}</li>
@endsection

@section('action-btn')
    <div class="float-end">
        <a href="#" data-url="{{ route('employee-shift.create') }}" data-ajax-popup="true" data-size="lg"
           data-title="{{ __('Allocate Shift') }}" data-bs-toggle="tooltip" title="{{ __('Allocate') }}"
           class="btn btn-sm btn-primary">
            <i class="ti ti-plus"></i>
        </a>
    </div>
@endsection

@section('content')
<div class="row">
    <div class="col-sm-12">
        <div class="card">
            <div class="card-body table-border-style">
                <div class="table-responsive">
                    <table class="table datatable">
                        <thead>
                            <tr>
                                <th>{{ __('Employee') }}</th>
                                <th>{{ __('Shift') }}</th>
                                <th>{{ __('Start Date') }}</th>
                                <th>{{ __('End Date') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th width="180px">{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($employeeShifts as $es)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="avatar avatar-sm bg-primary text-white rounded-circle">
                                            {{ strtoupper(substr(optional($es->employee)->name ?? 'U', 0, 1)) }}
                                        </span>
                                        {{ optional($es->employee)->name ?? __('Unknown') }}
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light-primary text-primary">
                                        {{ optional($es->shift)->name ?? '—' }}
                                    </span>
                                </td>
                                <td>{{ $es->start_date ? $es->start_date->format('d M Y') : '—' }}</td>
                                <td>{{ $es->end_date ? $es->end_date->format('d M Y') : '<span class="text-success">'.  __('Ongoing') .'</span>' }}</td>
                                <td>
                                    @php
                                        $today = now()->toDateString();
                                        $isActive = !$es->end_date || $es->end_date->toDateString() >= $today;
                                    @endphp
                                    @if($isActive)
                                        <span class="badge bg-success">{{ __('Active') }}</span>
                                    @else
                                        <span class="badge bg-secondary">{{ __('Ended') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="action-btn me-2">
                                        <a href="#"
                                           data-url="{{ route('employee-shift.edit', $es->id) }}"
                                           data-ajax-popup="true" data-size="lg"
                                           data-title="{{ __('Edit Allocation') }}"
                                           data-bs-toggle="tooltip" title="{{ __('Edit') }}"
                                           class="mx-3 btn btn-sm align-items-center bg-info">
                                            <i class="ti ti-pencil text-white"></i>
                                        </a>
                                    </div>
                                    <div class="action-btn">
                                        {!! Form::open(['method' => 'DELETE', 'route' => ['employee-shift.destroy', $es->id], 'id' => 'del-es-'.$es->id]) !!}
                                        <a href="#" class="mx-3 btn btn-sm align-items-center bs-pass-para bg-danger"
                                           data-bs-toggle="tooltip" title="{{ __('Delete') }}">
                                            <i class="ti ti-trash text-white"></i>
                                        </a>
                                        {!! Form::close() !!}
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="ti ti-clipboard-list" style="font-size:40px;color:#ccc"></i>
                                    <p class="mt-2">{{ __('No shifts allocated yet.') }}</p>
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
