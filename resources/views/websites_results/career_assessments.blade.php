@extends('layouts.admin')

@section('page-title')
    {{ __('Career Path - Assessments') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('Websites') }}</li>
    <li class="breadcrumb-item"><a href="{{ route('websites.career.dashboard') }}">{{ __('Career Path') }}</a></li>
    <li class="breadcrumb-item">{{ __('Assessments') }}</li>
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
                    <a class="nav-link" href="{{ route('websites.career.users') }}">
                        <i class="ti ti-users me-1"></i>{{ __('Users') }}
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="{{ route('websites.career.assessments') }}">
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
                    <h5 class="mb-0">{{ __('Assessment Results') }} <span class="badge bg-success">{{ number_format($total) }}</span></h5>
                    <form method="GET" action="{{ route('websites.career.assessments') }}" class="d-flex gap-2">
                        <input type="text" name="track" class="form-control form-control-sm" placeholder="{{ __('Filter by track...') }}" value="{{ $track }}" style="min-width:180px;">
                        <button type="submit" class="btn btn-sm btn-success"><i class="ti ti-filter"></i></button>
                        @if($track)
                            <a href="{{ route('websites.career.assessments') }}" class="btn btn-sm btn-outline-secondary"><i class="ti ti-x"></i></a>
                        @endif
                    </form>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('User') }}</th>
                                    <th>{{ __('Email') }}</th>
                                    <th>{{ __('Track') }}</th>
                                    <th>{{ __('Date') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($assessments as $a)
                                    <tr>
                                        <td><strong>{{ $a['user_name'] ?? __('N/A') }}</strong></td>
                                        <td>{{ $a['user_email'] ?? '' }}</td>
                                        <td><span class="badge bg-light-primary">{{ $a['track_id'] ?? __('N/A') }}</span></td>
                                        <td><small>{{ isset($a['created_at']) ? \Carbon\Carbon::parse($a['created_at'])->format('Y-m-d H:i') : '' }}</small></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">{{ __('No assessments found') }}</td>
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
                                    <a class="page-link" href="{{ route('websites.career.assessments', ['page' => $currentPage - 1, 'track' => $track]) }}">&laquo;</a>
                                </li>
                                @for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++)
                                    <li class="page-item {{ $i == $currentPage ? 'active' : '' }}">
                                        <a class="page-link" href="{{ route('websites.career.assessments', ['page' => $i, 'track' => $track]) }}">{{ $i }}</a>
                                    </li>
                                @endfor
                                <li class="page-item {{ $currentPage >= $totalPages ? 'disabled' : '' }}">
                                    <a class="page-link" href="{{ route('websites.career.assessments', ['page' => $currentPage + 1, 'track' => $track]) }}">&raquo;</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
