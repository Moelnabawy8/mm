@extends('layouts.admin')

@section('page-title')
    {{ __('Cost Centers') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ url('finance/dashboard') }}">{{ __('Finance') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Cost Centers') }}</li>
@endsection

@section('action-btn')
    <div class="float-end">
        <a href="#" data-url="{{ route('cost-center.create') }}" data-ajax-popup="true" data-size="md"
           data-title="{{ __('Create Cost Center') }}" data-bs-toggle="tooltip" title="{{ __('Create') }}"
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
                                <th>{{ __('Code') }}</th>
                                <th>{{ __('Name') }}</th>
                                <th>{{ __('Description') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th width="180px">{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($costCenters as $cc)
                            <tr>
                                <td><span class="badge bg-light-primary text-primary">{{ $cc->code ?: '—' }}</span></td>
                                <td>{{ $cc->name }}</td>
                                <td class="text-muted" style="max-width:260px">{{ Str::limit($cc->description, 60) ?: '—' }}</td>
                                <td>
                                    @if($cc->status === 'active')
                                        <span class="badge bg-success">{{ __('Active') }}</span>
                                    @else
                                        <span class="badge bg-secondary">{{ __('Inactive') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="action-btn me-2">
                                        <a href="#"
                                           data-url="{{ route('cost-center.edit', $cc->id) }}"
                                           data-ajax-popup="true" data-size="md"
                                           data-title="{{ __('Edit Cost Center') }}"
                                           data-bs-toggle="tooltip" title="{{ __('Edit') }}"
                                           class="mx-3 btn btn-sm align-items-center bg-info">
                                            <i class="ti ti-pencil text-white"></i>
                                        </a>
                                    </div>
                                    <div class="action-btn">
                                        {!! Form::open(['method' => 'DELETE', 'route' => ['cost-center.destroy', $cc->id], 'id' => 'del-cc-'.$cc->id]) !!}
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
                                <td colspan="5" class="text-center text-muted py-4">
                                    <i class="ti ti-building-bank" style="font-size:40px;color:#ccc"></i>
                                    <p class="mt-2">{{ __('No cost centers found. Create your first one!') }}</p>
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
