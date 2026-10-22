@extends('layouts.admin')

@section('page-title')
    {{ __('Telegram Delivery Log') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('Telegram Delivery Log') }}</li>
@endsection

@section('action-btn')
    <div class="float-end">
        @can('manage telegram recipient')
            <a href="{{ route('telegram.recipients') }}" class="btn btn-sm btn-primary me-1"
                data-bs-toggle="tooltip" title="{{ __('Telegram accounts') }}">
                <i class="ti ti-users"></i>
            </a>
        @endcan
    </div>
@endsection

@section('content')
    <div class="row">
        @php
            $tgCards = [
                ['ti-send',           'primary', __('Sent (24h)'),   $stats['sent_24h']],
                ['ti-alert-circle',   'danger',  __('Failed (24h)'), $stats['failed_24h']],
                ['ti-clock',          'warning', __('Waiting'),      $stats['pending']],
                ['ti-chart-pie',      'success', __('Success rate'), $stats['success_rate'] . '%'],
            ];
        @endphp
        @foreach ($tgCards as $c)
            <div class="col-lg-3 col-md-6">
                <div class="card">
                    <div class="card-body d-flex align-items-center">
                        <div class="theme-avtar bg-{{ $c[1] }}"><i class="ti {{ $c[0] }}"></i></div>
                        <div class="ms-3">
                            <h6 class="mb-0">{{ $c[2] }}</h6>
                            <h4 class="mb-0">{{ $c[3] }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if ($stats['suppressed'] > 0)
        <div class="alert alert-light border">
            <i class="ti ti-info-circle me-1"></i>
            {{ __('Repeat events collapsed by de-duplication:') }} <strong>{{ $stats['suppressed'] }}</strong>.
            {{ __('These were suppressed on purpose so nobody is messaged twice for the same thing — nothing was lost.') }}
        </div>
    @endif

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <form method="get" class="row g-2 align-items-end">
                        <div class="col-md-2">
                            <label class="form-label small mb-1">{{ __('Status') }}</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="">{{ __('All') }}</option>
                                @foreach (['pending', 'sending', 'sent', 'failed', 'cancelled'] as $s)
                                    <option value="{{ $s }}" {{ request('status') == $s ? 'selected' : '' }}>
                                        {{ __(ucfirst($s)) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-1">{{ __('Event') }}</label>
                            <select name="event" class="form-select form-select-sm">
                                <option value="">{{ __('All') }}</option>
                                @foreach (\App\Models\TelegramMessage::EVENT_LABELS as $k => $v)
                                    <option value="{{ $k }}" {{ request('event') == $k ? 'selected' : '' }}>
                                        {{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small mb-1">{{ __('Recipient') }}</label>
                            <select name="user" class="form-select form-select-sm">
                                <option value="">{{ __('All') }}</option>
                                @foreach ($users as $id => $name)
                                    <option value="{{ $id }}" {{ request('user') == $id ? 'selected' : '' }}>
                                        {{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-1">{{ __('From') }}</label>
                            <input type="date" name="from" value="{{ request('from') }}"
                                class="form-control form-control-sm">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-1">{{ __('To') }}</label>
                            <input type="date" name="to" value="{{ request('to') }}"
                                class="form-control form-control-sm">
                        </div>
                        <div class="col-md-1">
                            <button class="btn btn-sm btn-primary w-100"><i class="ti ti-filter"></i></button>
                        </div>
                    </form>
                </div>

                <div class="card-body table-border-style p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>{{ __('Recipient') }}</th>
                                    <th>{{ __('Event') }}</th>
                                    <th>{{ __('Task') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Time') }}</th>
                                    <th class="text-end">{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($messages as $m)
                                    <tr>
                                        <td class="text-muted">{{ $m->id }}</td>
                                        <td>
                                            {{ $m->user->name ?? '—' }}
                                            <div class="text-muted small" dir="ltr">{{ $m->chat_id }}</div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark">{{ $m->eventLabel() }}</span>
                                            @if ($m->duplicate_hits > 0)
                                                <span class="badge bg-secondary" data-bs-toggle="tooltip"
                                                    title="{{ __('The same event fired again and was de-duplicated') }}">
                                                    +{{ $m->duplicate_hits }}
                                                </span>
                                            @endif
                                        </td>
                                        <td>
                                            {{ Str::limit($m->task->name ?? '—', 40) }}
                                            <div class="text-muted small">{{ Str::limit($m->project->project_name ?? '', 30) }}</div>
                                        </td>
                                        <td>
                                            <span class="badge bg-{{ $m->statusColor() }}">{{ __(ucfirst($m->status)) }}</span>
                                            @if ($m->provider_message_id === 'DRYRUN')
                                                <span class="badge bg-warning">{{ __('dry run') }}</span>
                                            @endif
                                            @if ($m->error_code)
                                                <div class="text-danger small" data-bs-toggle="tooltip"
                                                    title="{{ $m->error_message }}">
                                                    {{ __('Error') }} {{ $m->error_code }}
                                                </div>
                                            @endif
                                            @if ($m->attempts > 1)
                                                <div class="text-muted small">{{ __('Attempts:') }} {{ $m->attempts }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            <div>{{ $m->created_at?->format('Y-m-d H:i') }}</div>
                                            @if ($m->sent_at)
                                                <div class="text-muted small">
                                                    {{ __('Sent:') }} {{ $m->sent_at->format('H:i') }}
                                                </div>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            @if (in_array($m->status, ['failed', 'cancelled']))
                                                <form method="post" action="{{ route('telegram.log.resend', $m->id) }}"
                                                    class="d-inline">
                                                    @csrf
                                                    <button class="btn btn-sm btn-primary" data-bs-toggle="tooltip"
                                                        title="{{ __('Resend') }}"><i class="ti ti-refresh"></i></button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                    @if ($m->body_preview)
                                        <tr class="border-0">
                                            <td></td>
                                            <td colspan="6" class="pt-0 text-muted small border-0">
                                                {{ Str::limit($m->body_preview, 160) }}
                                            </td>
                                        </tr>
                                    @endif
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            {{ __('No messages yet.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if ($messages->hasPages())
                    <div class="card-footer">{{ $messages->links('pagination::bootstrap-5') }}</div>
                @endif
            </div>
        </div>
    </div>
@endsection
