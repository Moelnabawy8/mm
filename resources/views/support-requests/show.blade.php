@extends('layouts.admin')
@section('page-title')
    {{ __('Support Request Details') }} #{{ $supportRequest->id }}
@endsection

@section('title')
    {{ __('Support Request') }} #{{ $supportRequest->id }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('support-requests.index') }}">{{ __('Support Requests') }}</a></li>
    <li class="breadcrumb-item">{{ __('Details') }}</li>
@endsection

@section('action-btn')
    <div class="col-12 text-end">
        @if ($supportRequest->requester_id == Auth::user()->id || $supportRequest->supporter_id == Auth::user()->id)
            @can('edit deal')
                <a href="{{ route('support-requests.edit', $supportRequest->id) }}" class="btn btn-sm btn-primary">
                    <i class="ti ti-pencil me-1"></i>{{ __('Edit') }}
                </a>
            @endcan
        @endif
        @if ($supportRequest->requester_id == Auth::user()->id)
            @can('delete deal')
                <form method="POST" action="{{ route('support-requests.destroy', $supportRequest->id) }}"
                    onsubmit="return confirm('{{ __('Are you sure?') }}');" style="display: inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-danger">
                        <i class="ti ti-trash me-1"></i>{{ __('Delete') }}
                    </button>
                </form>
            @endcan
        @endif
    </div>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="ti ti-help me-2"></i>{{ __('Support Request Details') }}
                        </h5>
                        <div>
                            <span class="badge bg-{{ $supportRequest->status_badge }} me-2">
                                {{ __(ucfirst(str_replace('_', ' ', $supportRequest->status))) }}
                            </span>
                            <span class="badge bg-{{ $supportRequest->priority_badge }}">
                                {{ __(ucfirst($supportRequest->priority)) }}
                            </span>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 class="text-muted mb-2">{{ __('Lead/Client Information') }}</h6>
                            @if ($supportRequest->lead)
                                <div class="d-flex align-items-center mb-3">
                                    <div class="avatar avatar-lg me-3">
                                        <span class="avatar-title rounded-circle bg-primary text-white fs-4">
                                            {{ substr($supportRequest->lead->name, 0, 1) }}
                                        </span>
                                    </div>
                                    <div>
                                        <h5 class="mb-0">{{ $supportRequest->lead->name }}</h5>
                                        <small class="text-muted">
                                            <i class="ti ti-mail me-1"></i>{{ $supportRequest->lead->email ?? 'N/A' }}
                                        </small><br>
                                        <small class="text-muted">
                                            <i class="ti ti-phone me-1"></i>{{ $supportRequest->lead->phone ?? 'N/A' }}
                                        </small>
                                    </div>
                                </div>
                                <a href="{{ route('leads.show', $supportRequest->lead->id) }}"
                                    class="btn btn-sm btn-outline-primary">
                                    <i class="ti ti-external-link me-1"></i>{{ __('View Lead Details') }}
                                </a>
                            @else
                                <p class="text-muted">{{ __('Lead not found') }}</p>
                            @endif
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted mb-2">{{ __('Timeline') }}</h6>
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <i class="ti ti-calendar text-primary me-2"></i>
                                    <strong>{{ __('Created:') }}</strong>
                                    {{ $supportRequest->created_at->format('Y-m-d H:i') }}
                                    <small class="text-muted">({{ $supportRequest->created_at->diffForHumans() }})</small>
                                </li>
                                @if ($supportRequest->responded_at)
                                    <li class="mb-2">
                                        <i class="ti ti-clock text-success me-2"></i>
                                        <strong>{{ __('Responded:') }}</strong>
                                        {{ $supportRequest->responded_at->format('Y-m-d H:i') }}
                                        <small
                                            class="text-muted">({{ $supportRequest->responded_at->diffForHumans() }})</small>
                                    </li>
                                @endif
                                @if ($supportRequest->completed_at)
                                    <li class="mb-2">
                                        <i class="ti ti-check text-success me-2"></i>
                                        <strong>{{ __('Completed:') }}</strong>
                                        {{ $supportRequest->completed_at->format('Y-m-d H:i') }}
                                        <small
                                            class="text-muted">({{ $supportRequest->completed_at->diffForHumans() }})</small>
                                    </li>
                                @endif
                            </ul>
                        </div>
                    </div>

                    <hr>

                    <div class="mb-4">
                        <h6 class="text-muted mb-3">
                            <i class="ti ti-notes me-2"></i>{{ __('Request Notes') }}
                        </h6>
                        <div class="alert alert-light border">
                            <div class="d-flex align-items-start mb-2">
                                <div class="avatar avatar-sm me-2">
                                    <span class="avatar-title rounded-circle bg-primary text-white">
                                        {{ substr($supportRequest->requester->name, 0, 1) }}
                                    </span>
                                </div>
                                <div>
                                    <strong>{{ $supportRequest->requester->name }}</strong>
                                    <small class="text-muted ms-2">{{ __('Requester') }}</small>
                                </div>
                            </div>
                            <div class="ps-5">
                                <p class="mb-0" style="white-space: pre-wrap;">{{ $supportRequest->notes }}</p>
                            </div>
                        </div>
                    </div>

                    @if ($supportRequest->response_notes)
                        <div class="mb-4">
                            <h6 class="text-muted mb-3">
                                <i class="ti ti-message-circle me-2"></i>{{ __('Response Notes') }}
                            </h6>
                            <div class="alert alert-success border">
                                <div class="d-flex align-items-start mb-2">
                                    <div class="avatar avatar-sm me-2">
                                        <span class="avatar-title rounded-circle bg-success text-white">
                                            {{ substr($supportRequest->supporter->name, 0, 1) }}
                                        </span>
                                    </div>
                                    <div>
                                        <strong>{{ $supportRequest->supporter->name }}</strong>
                                        <small class="text-muted ms-2">{{ __('Supporter') }}</small>
                                    </div>
                                </div>
                                <div class="ps-5">
                                    <p class="mb-0" style="white-space: pre-wrap;">{{ $supportRequest->response_notes }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if ($supportRequest->client_subscribed !== null)
                        <div class="mb-4">
                            <h6 class="text-muted mb-3">
                                <i class="ti ti-currency-dollar me-2"></i>{{ __('Support Value') }}
                            </h6>
                            <div class="card border-primary">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label class="text-muted small">{{ __('Client Subscribed?') }}</label>
                                            <div class="mt-1">
                                                @if ($supportRequest->client_subscribed)
                                                    <span class="badge bg-success fs-6">
                                                        <i class="ti ti-check me-1"></i>{{ __('Yes') }}
                                                    </span>
                                                @else
                                                    <span class="badge bg-danger fs-6">
                                                        <i class="ti ti-x me-1"></i>{{ __('No') }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                        @if ($supportRequest->client_subscribed && $supportRequest->subscription_amount)
                                            <div class="col-md-6">
                                                <label class="text-muted small">{{ __('Subscription Amount') }}</label>
                                                <div class="mt-1">
                                                    <h4 class="text-success mb-0">
                                                        <i
                                                            class="ti ti-currency-dollar"></i>{{ number_format($supportRequest->subscription_amount, 2) }}
                                                    </h4>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if (
                        $supportRequest->supporter_id == Auth::user()->id &&
                            $supportRequest->status != 'completed' &&
                            $supportRequest->status != 'cancelled')
                        <div class="alert alert-info">
                            <i class="ti ti-info-circle me-2"></i>
                            <strong>{{ __('Action Required:') }}</strong>
                            {{ __('This support request is assigned to you. Please update the status and add response notes.') }}
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="ti ti-users me-2"></i>{{ __('People Involved') }}</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="text-muted small">{{ __('Requester') }}</label>
                        <div class="d-flex align-items-center mt-1">
                            <div class="avatar avatar-sm me-2">
                                <span class="avatar-title rounded-circle bg-primary text-white">
                                    {{ substr($supportRequest->requester->name, 0, 1) }}
                                </span>
                            </div>
                            <div>
                                <div class="fw-medium">{{ $supportRequest->requester->name }}</div>
                                <small class="text-muted">{{ $supportRequest->requester->email }}</small>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div>
                        <label class="text-muted small">{{ __('Supporter') }}</label>
                        <div class="d-flex align-items-center mt-1">
                            <div class="avatar avatar-sm me-2">
                                <span class="avatar-title rounded-circle bg-success text-white">
                                    {{ substr($supportRequest->supporter->name, 0, 1) }}
                                </span>
                            </div>
                            <div>
                                <div class="fw-medium">{{ $supportRequest->supporter->name }}</div>
                                <small class="text-muted">{{ $supportRequest->supporter->email }}</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @if ($supportRequest->supporter_id == Auth::user()->id)
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0"><i class="ti ti-refresh me-2"></i>{{ __('Quick Status Update') }}</h6>
                    </div>
                    <div class="card-body">
                        <form id="quick-status-form">
                            @csrf
                            <div class="form-group mb-3">
                                <label for="status" class="form-label">{{ __('Status') }}</label>
                                <select class="form-control" id="status" name="status">
                                    <option value="pending" {{ $supportRequest->status == 'pending' ? 'selected' : '' }}>
                                        {{ __('Pending') }}</option>
                                    <option value="in_progress"
                                        {{ $supportRequest->status == 'in_progress' ? 'selected' : '' }}>
                                        {{ __('In Progress') }}</option>
                                    <option value="completed"
                                        {{ $supportRequest->status == 'completed' ? 'selected' : '' }}>
                                        {{ __('Completed') }}</option>
                                    <option value="cancelled"
                                        {{ $supportRequest->status == 'cancelled' ? 'selected' : '' }}>
                                        {{ __('Cancelled') }}</option>
                                </select>
                            </div>
                            <button type="button" id="update-status-btn" class="btn btn-success w-100">
                                <i class="ti ti-check me-1"></i>{{ __('Update Status') }}
                            </button>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection

@push('script-page')
    <script>
        $(document).on('click', '#update-status-btn', function() {
            var status = $('#status').val();

            $.ajax({
                url: '{{ route('support-requests.update-status', $supportRequest->id) }}',
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    status: status
                },
                success: function(response) {
                    if (response.success) {
                        show_toastr('Success', response.message, 'success');
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    }
                },
                error: function(xhr) {
                    show_toastr('Error', xhr.responseJSON.error || 'Something went wrong', 'error');
                }
            });
        });
    </script>
@endpush
