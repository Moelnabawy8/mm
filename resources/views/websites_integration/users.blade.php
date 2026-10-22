@extends('layouts.admin')

@section('page-title')
    {{ __('Websites — Customers') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('websites.dashboard') }}">{{ __('Websites') }}</a></li>
    <li class="breadcrumb-item">{{ __('Customers') }}</li>
@endsection

@section('action-btn')
    <div class="float-end">
        <a href="{{ route('websites.users.export') }}" class="btn btn-sm btn-outline-success" title="{{ __('Export CSV') }}">
            <i class="ti ti-download me-1"></i> {{ __('Export') }}
        </a>
    </div>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="mb-0">{{ __('Customers') }} <span class="badge bg-light-secondary ms-1">{{ number_format($total) }}</span></h5>
                    <form method="GET" action="{{ route('websites.users') }}" class="d-flex gap-2 mb-0">
                        <input type="search" name="q" value="{{ $search }}" class="form-control form-control-sm"
                               placeholder="{{ __('Search by name or email…') }}" style="width: 240px;">
                        <button type="submit" class="btn btn-sm btn-primary"><i class="ti ti-search"></i></button>
                        @if ($search)
                            <a href="{{ route('websites.users') }}" class="btn btn-sm btn-outline-secondary"><i class="ti ti-x"></i></a>
                        @endif
                    </form>
                </div>
                <div class="card-body table-border-style">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>{{ __('Avatar') }}</th>
                                    <th>{{ __('Name') }}</th>
                                    <th>{{ __('Email') }}</th>
                                    <th>{{ __('Phone') }}</th>
                                    <th>{{ __('Country') }}</th>
                                    <th>{{ __('Orders') }}</th>
                                    <th>{{ __('Total Spent') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Registered') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($users as $u)
                                    <tr>
                                        <td>{{ $u['id'] }}</td>
                                        <td>
                                            @if (!empty($u['avatar']))
                                                <img src="{{ $u['avatar'] }}" alt="" class="rounded-circle" width="36" height="36" loading="lazy">
                                            @else
                                                <span class="avtar avtar-s bg-light-secondary"><i class="ti ti-user"></i></span>
                                            @endif
                                        </td>
                                        <td><strong>{{ $u['name'] ?: '(no name)' }}</strong></td>
                                        <td><small>{{ $u['email'] }}</small></td>
                                        <td><small>{{ $u['phone'] ?: '—' }}</small></td>
                                        <td><small>{{ $u['country'] ?: '—' }}</small></td>
                                        <td><span class="badge bg-light-primary">{{ number_format($u['orders_count']) }}</span></td>
                                        <td><strong>{{ number_format($u['total_spent'], 2) }}</strong></td>
                                        <td>
                                            @if ($u['is_paying'])
                                                <span class="badge bg-light-success">{{ __('Paying') }}</span>
                                            @else
                                                <span class="badge bg-light-secondary">{{ __('Lead') }}</span>
                                            @endif
                                        </td>
                                        <td><small>{{ !empty($u['date_created']) ? \Carbon\Carbon::parse($u['date_created'])->format('Y-m-d') : '—' }}</small></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                    onclick="showCustomerDetail({{ $u['id'] }})" title="{{ __('Details') }}">
                                                <i class="ti ti-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="11" class="text-center text-muted py-5">
                                        <i class="ti ti-cloud-off fs-1 d-block mb-2 opacity-50"></i>
                                        {{ __('No customers match the search.') }}
                                    </td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @include('websites_integration.partials.pagination', ['currentPage' => $currentPage, 'pages' => $pages, 'route' => 'websites.users', 'extra' => ['q' => $search]])
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">{{ __('Customer') }}</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body" id="detailModalBody">
                    <div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script-page')
<script>
window.showCustomerDetail = function(id) {
    const body = document.getElementById('detailModalBody');
    body.replaceChildren();
    const wrap = document.createElement('div'); wrap.className = 'text-center py-5';
    const spinner = document.createElement('div'); spinner.className = 'spinner-border text-primary';
    spinner.setAttribute('role', 'status');
    wrap.appendChild(spinner); body.appendChild(wrap);
    new bootstrap.Modal(document.getElementById('detailModal')).show();
    fetch('/websites/users/' + id).then(r => r.text()).then(html => {
        const doc = new DOMParser().parseFromString(html, 'text/html');
        body.replaceChildren(...doc.body.childNodes);
    });
};
</script>
@endpush
