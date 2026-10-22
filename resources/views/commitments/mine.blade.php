@extends('layouts.admin')

@section('page-title'){{ __('My Commitments') }}@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('My Commitments') }}</li>
@endsection

@section('action-btn')
    <div class="float-end">
        <a href="{{ route('commitments.owed') }}" class="btn btn-sm btn-primary btn-icon m-1">
            <i class="ti ti-user-check"></i> {{ __('Owed to me') }}
        </a>
    </div>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">@include('commitments._tally')</div>
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('What I owe') }}</h5>
                    <small class="text-muted">{{ __('Open and overdue first, then what has been settled.') }}</small>
                </div>
                <div class="card-body table-border-style">
                    @include('commitments._table', ['show' => 'owed_to', 'emptyText' => __('You owe nothing right now.')])
                </div>
            </div>
        </div>
    </div>
@endsection
