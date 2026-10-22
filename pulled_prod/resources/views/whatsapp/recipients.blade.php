@extends('layouts.admin')

@section('page-title')
    {{ __('WhatsApp Numbers') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('WhatsApp Numbers') }}</li>
@endsection

@section('action-btn')
    <div class="float-end">
        @can('view whatsapp log')
            <a href="{{ route('whatsapp.log') }}" class="btn btn-sm btn-primary me-1"
                data-bs-toggle="tooltip" title="{{ __('Delivery log') }}">
                <i class="ti ti-history"></i>
            </a>
        @endcan
        <a href="#" id="wa-import-btn" class="btn btn-sm btn-success me-1" data-bs-toggle="tooltip"
            title="{{ __('Import numbers from employee records') }}">
            <i class="ti ti-download"></i>
        </a>
    </div>
@endsection

@section('content')
    <div class="row">
        <div class="col-lg-4 col-md-6">
            <div class="card">
                <div class="card-body d-flex align-items-center">
                    <div class="theme-avtar bg-primary"><i class="ti ti-users"></i></div>
                    <div class="ms-3">
                        <h6 class="mb-0">{{ __('Users') }}</h6>
                        <h4 class="mb-0">{{ $stats['total'] }}</h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6">
            <div class="card">
                <div class="card-body d-flex align-items-center">
                    <div class="theme-avtar bg-success"><i class="ti ti-brand-whatsapp"></i></div>
                    <div class="ms-3">
                        <h6 class="mb-0">{{ __('With a number') }}</h6>
                        <h4 class="mb-0">{{ $stats['with'] }}</h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6">
            <div class="card">
                <div class="card-body d-flex align-items-center">
                    <div class="theme-avtar bg-danger"><i class="ti ti-alert-triangle"></i></div>
                    <div class="ms-3">
                        <h6 class="mb-0">{{ __('Invalid') }}</h6>
                        <h4 class="mb-0">{{ $stats['invalid'] }}</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="row align-items-center g-2">
                        <div class="col-md-8">
                            <h5 class="mb-0">{{ __('WhatsApp Numbers') }}</h5>
                            <small class="text-muted">
                                {{ __('Only an administrator can set these. Employees cannot change their own number.') }}
                            </small>
                        </div>
                        <div class="col-md-4">
                            <form method="get" class="d-flex gap-1">
                                <input type="text" name="search" class="form-control form-control-sm"
                                    value="{{ request('search') }}" placeholder="{{ __('Search name / email / number') }}">
                                <select name="filter" class="form-select form-select-sm" style="max-width:150px">
                                    <option value="">{{ __('All') }}</option>
                                    <option value="missing" {{ request('filter') == 'missing' ? 'selected' : '' }}>
                                        {{ __('No number') }}</option>
                                    <option value="invalid" {{ request('filter') == 'invalid' ? 'selected' : '' }}>
                                        {{ __('Invalid') }}</option>
                                </select>
                                <button class="btn btn-sm btn-primary"><i class="ti ti-search"></i></button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="card-body table-border-style p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('Name') }}</th>
                                    <th>{{ __('Role') }}</th>
                                    <th style="min-width:220px">{{ __('WhatsApp number') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th class="text-end">{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($users as $u)
                                    <tr data-user="{{ $u->id }}">
                                        <td>
                                            <strong>{{ $u->name }}</strong>
                                            <div class="text-muted small">{{ $u->email }}</div>
                                        </td>
                                        <td><span class="badge bg-light text-dark">{{ $u->type }}</span></td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm wa-number"
                                                dir="ltr" value="{{ $u->whatsapp }}"
                                                placeholder="+962791234567">
                                        </td>
                                        <td>
                                            @php
                                                $waBadge = ['unset' => 'secondary', 'valid' => 'success', 'invalid' => 'danger'];
                                                $waLabel = ['unset' => __('Not verified'), 'valid' => __('Verified'), 'invalid' => __('Invalid')];
                                                $st = $u->whatsapp_status ?: 'unset';
                                            @endphp
                                            <span class="badge bg-{{ $waBadge[$st] ?? 'secondary' }} wa-status">
                                                {{ $waLabel[$st] ?? $st }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <a href="#" class="btn btn-sm btn-primary wa-save me-1"
                                                data-bs-toggle="tooltip" title="{{ __('Save') }}">
                                                <i class="ti ti-device-floppy"></i>
                                            </a>
                                            <a href="#" class="btn btn-sm btn-success wa-test"
                                                data-bs-toggle="tooltip" title="{{ __('Send a test message') }}">
                                                <i class="ti ti-send"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">{{ __('No users found.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if ($users->hasPages())
                    <div class="card-footer">{{ $users->links('pagination::bootstrap-5') }}</div>
                @endif
            </div>
        </div>
    </div>

    {{-- Import preview. Deliberately a preview-then-confirm flow: employees.phone contains
         placeholder rows (+971501234567 appears 8 times) and multi-number values, so a blind
         data migration would wire real notifications to junk numbers. --}}
    <div class="modal fade" id="wa-import-modal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('Import from employee records') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="wa-import-summary" class="mb-2"></div>
                    <div class="table-responsive" style="max-height:50vh">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>{{ __('Name') }}</th>
                                    <th>{{ __('Stored value') }}</th>
                                    <th>{{ __('Will be imported as') }}</th>
                                </tr>
                            </thead>
                            <tbody id="wa-import-rows"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <form method="post" action="{{ route('whatsapp.recipients.import') }}" class="d-inline">
                        @csrf
                        <button class="btn btn-primary">{{ __('Import the valid numbers') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script-page')
    <script>
        (function () {
            const csrf = '{{ csrf_token() }}';

            function toast(type, msg) {
                if (typeof show_toastr === 'function') { show_toastr(type, msg); }
                else { alert(msg); }
            }

            document.querySelectorAll('.wa-save').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    const row = btn.closest('tr');
                    const id = row.dataset.user;
                    const input = row.querySelector('.wa-number');

                    fetch('{{ url('whatsapp/recipients') }}/' + id, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                        body: JSON.stringify({ whatsapp: input.value })
                    })
                    .then(r => r.json().then(d => ({ ok: r.ok, d })))
                    .then(function (res) {
                        if (!res.ok) { toast('error', res.d.error || 'Error'); return; }
                        input.value = res.d.whatsapp;
                        const badge = row.querySelector('.wa-status');
                        badge.className = 'badge bg-secondary wa-status';
                        badge.textContent = '{{ __('Not verified') }}';
                        toast('success', '{{ __('Saved.') }}');
                    })
                    .catch(() => toast('error', 'Network error'));
                });
            });

            document.querySelectorAll('.wa-test').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    const id = btn.closest('tr').dataset.user;
                    btn.classList.add('disabled');

                    fetch('{{ url('whatsapp/recipients') }}/' + id + '/test', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
                    })
                    .then(r => r.json().then(d => ({ ok: r.ok, d })))
                    .then(function (res) {
                        btn.classList.remove('disabled');
                        toast(res.ok ? 'success' : 'error', res.ok ? res.d.message : (res.d.error || 'Error'));
                    })
                    .catch(() => { btn.classList.remove('disabled'); toast('error', 'Network error'); });
                });
            });

            document.getElementById('wa-import-btn').addEventListener('click', function (e) {
                e.preventDefault();
                const body = document.getElementById('wa-import-rows');
                const summary = document.getElementById('wa-import-summary');
                body.innerHTML = '<tr><td colspan="3">{{ __('Loading...') }}</td></tr>';
                new bootstrap.Modal(document.getElementById('wa-import-modal')).show();

                fetch('{{ route('whatsapp.recipients.import.preview') }}', {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }
                })
                .then(r => r.json())
                .then(function (d) {
                    summary.innerHTML = '<span class="badge bg-success me-1">' + d.valid +
                        ' {{ __('valid') }}</span><span class="badge bg-danger">' + d.invalid +
                        ' {{ __('will be skipped') }}</span>';
                    body.innerHTML = d.rows.map(function (r) {
                        return '<tr class="' + (r.ok ? '' : 'text-muted') + '">' +
                            '<td>' + r.name + '</td>' +
                            '<td dir="ltr"><code>' + r.raw + '</code></td>' +
                            '<td dir="ltr">' + (r.ok
                                ? '<span class="text-success">' + r.e164 + '</span>'
                                : '<span class="text-danger">— {{ __('skipped') }}</span>') +
                            '</td></tr>';
                    }).join('') || '<tr><td colspan="3">{{ __('Nothing to import.') }}</td></tr>';
                })
                .catch(() => { body.innerHTML = '<tr><td colspan="3">{{ __('Failed to load.') }}</td></tr>'; });
            });
        })();
    </script>
@endpush
