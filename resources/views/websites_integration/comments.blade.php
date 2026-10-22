@extends('layouts.admin')

@section('page-title')
    {{ __('Websites — Comments') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('websites.dashboard') }}">{{ __('Websites') }}</a></li>
    <li class="breadcrumb-item">{{ __('Comments') }}</li>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="alert alert-warning d-flex align-items-center" role="alert">
                <i class="ti ti-clock-pause me-2 fs-4"></i>
                <div>
                    <strong>{{ __('Coming Soon') }}.</strong>
                    {{ __('Comment moderation is deferred to a later release. This page is reserved as a placeholder.') }}
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="ti ti-message-2 fs-1 text-muted opacity-25 d-block mb-3"></i>
                    <h4 class="text-muted">{{ __('Comments Module — Phase 2') }}</h4>
                    <p class="text-muted">{{ __('Once enabled, this module will let you view and reply to comments across all sites from a single inbox.') }}</p>
                </div>
            </div>
        </div>
    </div>
@endsection
