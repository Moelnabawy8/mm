@extends('layouts.admin')

@section('page-title')
    {{ __('Websites — Subdomains') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('websites.dashboard') }}">{{ __('Websites') }}</a></li>
    <li class="breadcrumb-item">{{ __('Subdomains') }}</li>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="alert alert-info"><i class="ti ti-info-circle me-1"></i> {{ __('Placeholder — Career, Career Path, and Hiring subdomains will be linked here.') }}</div>
        </div>

        @php
            $subs = [
                ['name' => __('Careers / Jobs'),     'url' => '—', 'icon' => 'briefcase',  'color' => 'primary',  'desc' => __('Public job listings & applications')],
                ['name' => __('Career Path'),        'url' => '—', 'icon' => 'route',      'color' => 'info',     'desc' => __('Self-service career roadmap')],
                ['name' => __('Hiring / Recruitment'),'url' => '—','icon' => 'users-plus', 'color' => 'success',  'desc' => __('Internal recruiter tools')],
            ];
        @endphp

        @foreach ($subs as $s)
            <div class="col-xl-4 col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <span class="avtar avtar-l bg-light-{{ $s['color'] }} me-3"><i class="ti ti-{{ $s['icon'] }} fs-3"></i></span>
                            <div>
                                <h5 class="mb-0">{{ $s['name'] }}</h5>
                                <p class="text-muted small mb-0">{{ $s['desc'] }}</p>
                            </div>
                        </div>
                        <ul class="list-unstyled">
                            <li class="d-flex justify-content-between py-1"><span class="text-muted">{{ __('URL') }}</span><strong>{{ $s['url'] }}</strong></li>
                            <li class="d-flex justify-content-between py-1"><span class="text-muted">{{ __('Status') }}</span><span class="badge bg-light-warning">{{ __('Not connected') }}</span></li>
                            <li class="d-flex justify-content-between py-1"><span class="text-muted">{{ __('API Spec') }}</span><strong>—</strong></li>
                        </ul>
                        <button class="btn btn-sm btn-outline-secondary w-100" disabled>
                            <i class="ti ti-plug-connected me-1"></i>{{ __('Connect API') }}
                        </button>
                    </div>
                </div>
            </div>
        @endforeach

        <div class="col-12">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">{{ __('Discovered Server Subdomains') }}</h5></div>
                <div class="card-body table-border-style">
                    <p class="text-muted small mb-2">{{ __('These subdomains exist on the server. Decide which ones to add to v1.') }}</p>
                    <div class="table-responsive">
                        <table class="table">
                            <thead><tr>
                                <th>{{ __('Subdomain') }}</th>
                                <th>{{ __('Path') }}</th>
                                <th>{{ __('Decision') }}</th>
                            </tr></thead>
                            <tbody>
                                @foreach (['agent', 'app', 'cv', 'hiring', 'inventory', 'iosapp', 'panel', 'stream', 'student', 'new'] as $sub)
                                    <tr>
                                        <td>{{ $sub }}.my-communication.uk</td>
                                        <td>/home/{{ $sub }}.my-communication.uk/public_html</td>
                                        <td><span class="badge bg-light-secondary">{{ __('Pending review') }}</span></td>
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
