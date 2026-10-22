@extends('layouts.admin')

@section('page-title')
    {{ __('Career Path - Generated CVs') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('Websites') }}</li>
    <li class="breadcrumb-item"><a href="{{ route('websites.career.dashboard') }}">{{ __('Career Path') }}</a></li>
    <li class="breadcrumb-item">{{ __('CVs') }}</li>
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
                    <a class="nav-link" href="{{ route('websites.career.assessments') }}">
                        <i class="ti ti-clipboard-check me-1"></i>{{ __('Assessments') }}
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="{{ route('websites.career.cvs') }}">
                        <i class="ti ti-file-text me-1"></i>{{ __('CVs') }}
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">{{ __('Generated CVs') }} <span class="badge bg-info">{{ number_format($total) }}</span></h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('User') }}</th>
                                    <th>{{ __('Email') }}</th>
                                    <th>{{ __('Job Title') }}</th>
                                    <th>{{ __('Skills') }}</th>
                                    <th>{{ __('Date') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($cvs as $cv)
                                    <tr>
                                        <td><strong>{{ $cv['user_name'] ?? __('N/A') }}</strong></td>
                                        <td>{{ $cv['user_email'] ?? '' }}</td>
                                        <td>{{ $cv['job_title'] ?? '' }}</td>
                                        <td>
                                            @if(!empty($cv['skills']))
                                                @foreach (array_slice(explode(',', $cv['skills']), 0, 3) as $skill)
                                                    <span class="badge bg-light-info">{{ trim($skill) }}</span>
                                                @endforeach
                                                @if(count(explode(',', $cv['skills'])) > 3)
                                                    <span class="badge bg-light-secondary">+{{ count(explode(',', $cv['skills'])) - 3 }}</span>
                                                @endif
                                            @endif
                                        </td>
                                        <td><small>{{ isset($cv['created_at']) ? \Carbon\Carbon::parse($cv['created_at'])->format('Y-m-d H:i') : '' }}</small></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">{{ __('No CVs found') }}</td>
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
                                    <a class="page-link" href="{{ route('websites.career.cvs', ['page' => $currentPage - 1]) }}">&laquo;</a>
                                </li>
                                @for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++)
                                    <li class="page-item {{ $i == $currentPage ? 'active' : '' }}">
                                        <a class="page-link" href="{{ route('websites.career.cvs', ['page' => $i]) }}">{{ $i }}</a>
                                    </li>
                                @endfor
                                <li class="page-item {{ $currentPage >= $totalPages ? 'disabled' : '' }}">
                                    <a class="page-link" href="{{ route('websites.career.cvs', ['page' => $currentPage + 1]) }}">&raquo;</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
