@extends('layouts.admin')

@section('page-title')
    {{ __('Telegram Accounts') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('Telegram Accounts') }}</li>
@endsection

@section('action-btn')
    <div class="float-end">
        @can('view telegram log')
            <a href="{{ route('telegram.log') }}" class="btn btn-sm btn-primary me-1"
                data-bs-toggle="tooltip" title="{{ __('Delivery log') }}">
                <i class="ti ti-history"></i>
            </a>
        @endcan
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
                    <div class="theme-avtar bg-success"><i class="ti ti-brand-telegram"></i></div>
                    <div class="ms-3">
                        <h6 class="mb-0">{{ __('Linked') }}</h6>
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
                            <h5 class="mb-0">{{ __('Telegram Accounts') }}</h5>
                            <small class="text-muted">
                                {{ __('Unlike phone numbers, chats cannot be typed in — each employee links their own account from the Telegram page. Use Unlink when someone leaves or linked the wrong account.') }}
                            </small>
                        </div>
                        <div class="col-md-4">
                            <form method="get" class="d-flex gap-1">
                                <input type="text" name="search" class="form-control form-control-sm"
                                    value="{{ request('search') }}" placeholder="{{ __('Search name / email / chat') }}">
                                <select name="filter" class="form-select form-select-sm" style="max-width:150px">
                                    <option value="">{{ __('All') }}</option>
                                    <option value="missing" {{ request('filter') == 'missing' ? 'selected' : '' }}>
                                        {{ __('Not linked') }}</option>
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
                                    <th>{{ __('Chat') }}</th>
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
                                            @if ($u->telegram_chat_id)
                                                <span dir="ltr"><code>{{ $u->telegram_chat_id }}</code></span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @php
                                                $tgBadge = ['unset' => 'secondary', 'valid' => 'success', 'invalid' => 'danger'];
                                                $tgLabel = ['unset' => __('Not verified'), 'valid' => __('Verified'), 'invalid' => __('Invalid')];
                                                $st = $u->telegram_status ?: 'unset';
                                            @endphp
                                            <span class="badge bg-{{ $tgBadge[$st] ?? 'secondary' }}">
                                                {{ $tgLabel[$st] ?? $st }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            @if ($u->telegram_chat_id)
                                                <a href="#" class="btn btn-sm btn-success tg-test me-1"
                                                    data-bs-toggle="tooltip" title="{{ __('Send a test message') }}">
                                                    <i class="ti ti-send"></i>
                                                </a>
                                                <form method="post" action="{{ route('telegram.recipients.unlink', $u->id) }}"
                                                    class="d-inline" onsubmit="return confirm('{{ __('Unlink this account?') }}')">
                                                    @csrf
                                                    <button class="btn btn-sm btn-outline-danger" data-bs-toggle="tooltip"
                                                        title="{{ __('Unlink') }}"><i class="ti ti-unlink"></i></button>
                                                </form>
                                            @endif
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
@endsection

@push('script-page')
    <script>
        (function () {
            const csrf = '{{ csrf_token() }}';

            function toast(type, msg) {
                if (typeof show_toastr === 'function') { show_toastr(type, msg); }
                else { alert(msg); }
            }

            document.querySelectorAll('.tg-test').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    const id = btn.closest('tr').dataset.user;
                    btn.classList.add('disabled');

                    fetch('{{ url('telegram/recipients') }}/' + id + '/test', {
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
        })();
    </script>
@endpush
