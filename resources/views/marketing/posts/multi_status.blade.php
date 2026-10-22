@extends('layouts.admin')

@section('page-title')
    {{ __('Multi-Post Status') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('marketing.index') }}">{{ __('Marketing') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('marketing.posts') }}">{{ __('Posts') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Multi-Post Status') }}</li>
@endsection

@section('content')
    <div class="row justify-content-center">
        <div class="col-xl-8">
            {{-- Summary Cards --}}
            <div class="row mb-4">
                <div class="col-md-3 col-6">
                    <div class="card text-center">
                        <div class="card-body py-3">
                            <h3 class="mb-0" id="totalCount">{{ $summary['total'] }}</h3>
                            <small class="text-muted">{{ __('Total') }}</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card text-center border-success">
                        <div class="card-body py-3">
                            <h3 class="mb-0 text-success" id="successCount">{{ $summary['success'] }}</h3>
                            <small class="text-muted">{{ __('Success') }}</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card text-center border-danger">
                        <div class="card-body py-3">
                            <h3 class="mb-0 text-danger" id="failedCount">{{ $summary['failed'] }}</h3>
                            <small class="text-muted">{{ __('Failed') }}</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card text-center border-warning">
                        <div class="card-body py-3">
                            <h3 class="mb-0 text-warning" id="pendingCount">{{ $summary['pending'] }}</h3>
                            <small class="text-muted">{{ __('Pending') }}</small>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Results Table --}}
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="ti ti-list-check me-2"></i>{{ __('Publishing Results') }}</h5>
                    <div>
                        <a href="{{ route('marketing.multipost') }}" class="btn btn-sm btn-primary">
                            <i class="ti ti-plus me-1"></i>{{ __('New Multi-Post') }}
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="resultsTable">
                            <thead>
                                <tr>
                                    <th>{{ __('Platform') }}</th>
                                    <th>{{ __('Account') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Details') }}</th>
                                    <th>{{ __('Time') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($logs as $log)
                                    <tr data-log-id="{{ $log->id }}">
                                        <td>
                                            <i class="{{ $log->account->platform_icon ?? 'ti ti-share' }}"
                                               style="color: {{ $log->account->platform_color ?? '#6c757d' }}; font-size: 1.3rem;"></i>
                                            <span class="ms-1 text-capitalize">{{ $log->platform }}</span>
                                        </td>
                                        <td>{{ $log->account->account_name ?? '-' }}</td>
                                        <td>
                                            @if($log->status === 'success')
                                                <span class="badge bg-success"><i class="ti ti-check me-1"></i>{{ __('Success') }}</span>
                                            @elseif($log->status === 'failed')
                                                <span class="badge bg-danger"><i class="ti ti-x me-1"></i>{{ __('Failed') }}</span>
                                            @elseif($log->status === 'publishing')
                                                <span class="badge bg-info"><i class="ti ti-loader me-1"></i>{{ __('Publishing...') }}</span>
                                            @else
                                                <span class="badge bg-warning"><i class="ti ti-clock me-1"></i>{{ __('Pending') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($log->status === 'failed' && $log->error_message)
                                                <small class="text-danger">{{ Str::limit($log->error_message, 60) }}</small>
                                            @elseif($log->remote_post_id)
                                                <small class="text-muted">ID: {{ $log->remote_post_id }}</small>
                                            @else
                                                <small class="text-muted">-</small>
                                            @endif
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                {{ $log->published_at ? $log->published_at->diffForHumans() : '-' }}
                                            </small>
                                        </td>
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

@if($summary['pending'] > 0)
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const batchId = '{{ $batchId }}';
    const statusUrl = '{{ route("marketing.multipost.status.json", $batchId) }}';
    let polling = null;

    function pollStatus() {
        fetch(statusUrl)
            .then(r => r.json())
            .then(data => {
                document.getElementById('successCount').textContent = data.summary.success;
                document.getElementById('failedCount').textContent = data.summary.failed;
                document.getElementById('pendingCount').textContent = data.summary.pending;

                data.logs.forEach(log => {
                    const row = document.querySelector(`tr[data-log-id="${log.id}"]`);
                    if (!row) return;

                    const statusCell = row.children[2];
                    const detailCell = row.children[3];
                    const timeCell = row.children[4];

                    if (log.status === 'success') {
                        statusCell.innerHTML = '<span class="badge bg-success"><i class="ti ti-check me-1"></i>{{ __("Success") }}</span>';
                        if (log.remote_post_id) {
                            detailCell.innerHTML = `<small class="text-muted">ID: ${log.remote_post_id}</small>`;
                        }
                    } else if (log.status === 'failed') {
                        statusCell.innerHTML = '<span class="badge bg-danger"><i class="ti ti-x me-1"></i>{{ __("Failed") }}</span>';
                        if (log.error_message) {
                            detailCell.innerHTML = `<small class="text-danger">${log.error_message.substring(0, 60)}</small>`;
                        }
                    } else if (log.status === 'publishing') {
                        statusCell.innerHTML = '<span class="badge bg-info"><i class="ti ti-loader me-1"></i>{{ __("Publishing...") }}</span>';
                    }
                });

                if (data.summary.pending === 0) {
                    clearInterval(polling);
                }
            })
            .catch(() => {});
    }

    polling = setInterval(pollStatus, 3000);
});
</script>
@endpush
@endif
