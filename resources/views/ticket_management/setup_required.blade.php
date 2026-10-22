@extends('layouts.admin')

@section('page-title')
    {{ __('Setup Required') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item">{{ __('Public Tickets') }}</li>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body text-center py-5">
                <div class="mb-4">
                    <i class="ti ti-database text-warning" style="font-size: 4rem;"></i>
                </div>
                <h3 class="mb-3">{{ __('Database Setup Required') }}</h3>
                <p class="text-muted mb-4">
                    {{ __('The public ticket system requires database setup before it can be used.') }}
                </p>
                
                <div class="alert alert-info text-start">
                    <h5>{{ __('Import SQL File') }}</h5>
                    <p class="mb-0">
                        {{ __('Please import the SQL file from') }}: <code>database/ticket_system.sql</code>
                    </p>
                </div>
                
                <div class="mt-4">
                    <a href="{{ url()->current() }}" class="btn btn-primary">
                        <i class="ti ti-refresh me-2"></i>{{ __('Refresh Page') }}
                    </a>
                    <a href="{{ route('dashboard') }}" class="btn btn-secondary">
                        <i class="ti ti-home me-2"></i>{{ __('Go Home') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
