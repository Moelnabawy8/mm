@extends('layouts.admin')

@section('page-title')
    {{ __('Career Path - Users') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('Websites') }}</li>
    <li class="breadcrumb-item"><a href="{{ route('websites.career.dashboard') }}">{{ __('Career Path') }}</a></li>
    <li class="breadcrumb-item">{{ __('Users') }}</li>
@endsection

@section('content')
    {{-- Navigation Tabs --}}
    <div class="row mb-3">
        <div class="col-12">
            <ul class="nav nav-pills">
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('websites.career.dashboard') }}">
                        <i class="ti ti-dashboard me-1"></i>{{ __('Dashboard') }}
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="{{ route('websites.career.users') }}">
                        <i class="ti ti-users me-1"></i>{{ __('Users') }}
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('websites.career.assessments') }}">
                        <i class="ti ti-clipboard-check me-1"></i>{{ __('Assessments') }}
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('websites.career.cvs') }}">
                        <i class="ti ti-file-text me-1"></i>{{ __('CVs') }}
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="mb-0">{{ __('Users') }} <span class="badge bg-primary">{{ number_format($total) }}</span></h5>
                    <form method="GET" action="{{ route('websites.career.users') }}" class="d-flex gap-2">
                        <input type="text" name="q" class="form-control form-control-sm" placeholder="{{ __('Search by name or email...') }}" value="{{ $search }}" style="min-width:220px;">
                        <button type="submit" class="btn btn-sm btn-primary"><i class="ti ti-search"></i></button>
                        @if($search)
                            <a href="{{ route('websites.career.users') }}" class="btn btn-sm btn-outline-secondary"><i class="ti ti-x"></i></a>
                        @endif
                    </form>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('Name') }}</th>
                                    <th>{{ __('Email') }}</th>
                                    <th class="text-center">{{ __('Tries Left') }}</th>
                                    <th class="text-center">{{ __('AI Tokens') }}</th>
                                    <th class="text-center">{{ __('CV Builder') }}</th>
                                    <th>{{ __('Last Login') }}</th>
                                    <th>{{ __('Joined') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($users as $user)
                                    <tr>
                                        <td><strong>{{ $user['display_name'] ?? __('N/A') }}</strong></td>
                                        <td>{{ $user['email'] ?? '' }}</td>
                                        <td class="text-center">{{ $user['tries_left'] ?? 0 }}</td>
                                        <td class="text-center">
                                            <span class="text-primary">{{ number_format($user['ai_tokens_used'] ?? 0) }}</span>
                                            <small class="text-muted">/ {{ number_format($user['ai_token_limit'] ?? 0) }}</small>
                                        </td>
                                        <td class="text-center">
                                            <span class="text-info">{{ $user['cv_builder_used'] ?? 0 }}</span>
                                            <small class="text-muted">/ {{ $user['cv_builder_limit'] ?? 0 }}</small>
                                        </td>
                                        <td>
                                            @if(!empty($user['last_login_at']))
                                                <small>{{ \Carbon\Carbon::parse($user['last_login_at'])->diffForHumans() }}</small>
                                            @else
                                                <small class="text-muted">{{ __('Never') }}</small>
                                            @endif
                                        </td>
                                        <td><small>{{ isset($user['created_at']) ? \Carbon\Carbon::parse($user['created_at'])->format('Y-m-d') : '' }}</small></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">{{ __('No users found') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($total > $limit)
                    <div class="card-footer">
                        @php $totalPages = ceil($total / $limit); @endphp
                        <nav>
                            <ul class="pagination pagination-sm mb-0 justify-content-center">
                                <li class="page-item {{ $currentPage <= 1 ? 'disabled' : '' }}">
                                    <a class="page-link" href="{{ route('websites.career.users', ['page' => $currentPage - 1, 'q' => $search]) }}">&laquo;</a>
                                </li>
                                @for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++)
                                    <li class="page-item {{ $i == $currentPage ? 'active' : '' }}">
                                        <a class="page-link" href="{{ route('websites.career.users', ['page' => $i, 'q' => $search]) }}">{{ $i }}</a>
                                    </li>
                                @endfor
                                <li class="page-item {{ $currentPage >= $totalPages ? 'disabled' : '' }}">
                                    <a class="page-link" href="{{ route('websites.career.users', ['page' => $currentPage + 1, 'q' => $search]) }}">&raquo;</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
