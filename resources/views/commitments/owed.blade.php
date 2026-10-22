@extends('layouts.admin')

@section('page-title'){{ __('Owed to me') }}@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('commitments.mine') }}">{{ __('My Commitments') }}</a></li>
    <li class="breadcrumb-item">{{ __('Owed to me') }}</li>
@endsection

@section('action-btn')
    @if (Gate::check('create commitment') || Gate::check('manage commitment'))
        <div class="float-end">
            <a href="{{ route('commitments.create') }}" class="btn btn-sm btn-primary btn-icon m-1">
                <i class="ti ti-plus"></i> {{ __('New commitment') }}
            </a>
        </div>
    @endif
@endsection

@section('content')
    <div class="row">
        <div class="col-12">@include('commitments._tally')</div>
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Commitments owed to me') }}</h5>
                    <small class="text-muted">{{ __('Everything anyone has committed to you, whatever their department.') }}</small>
                </div>
                <div class="card-body table-border-style">
                    @include('commitments._table', ['show' => 'owner', 'emptyText' => __('Nobody has committed anything to you yet.')])
                </div>
            </div>
        </div>
    </div>
@endsection
