<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle" id="support-requests-table">
                <thead>
                    <tr>
                        <th>{{ __('ID') }}</th>
                        <th>{{ __('Lead/Client') }}</th>
                        <th>{{ __('Requester') }}</th>
                        <th>{{ __('Supporter') }}</th>
                        <th>{{ __('Priority') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Support Value') }}</th>
                        <th>{{ __('Created') }}</th>
                        <th>{{ __('Response Time') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($supportRequests as $request)
                        <tr>
                            <td>
                                <a href="{{ route('support-requests.show', $request->id) }}"
                                    class="text-primary fw-bold">
                                    #{{ $request->id }}
                                </a>
                            </td>
                            <td>
                                @if ($request->lead)
                                    <a href="{{ route('leads.show', $request->lead->id) }}" class="text-dark">
                                        <i class="ti ti-user me-1"></i>{{ $request->lead->name }}
                                    </a>
                                @else
                                    <span class="text-muted">{{ __('N/A') }}</span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar avatar-sm me-2">
                                        <span class="avatar-title rounded-circle bg-primary text-white">
                                            {{ substr($request->requester->name, 0, 1) }}
                                        </span>
                                    </div>
                                    <div>
                                        <span class="fw-medium">{{ $request->requester->name }}</span>
                                        @if ($request->requester_id == Auth::user()->id)
                                            <span class="badge bg-info ms-1">{{ __('You') }}</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar avatar-sm me-2">
                                        <span class="avatar-title rounded-circle bg-success text-white">
                                            {{ substr($request->supporter->name, 0, 1) }}
                                        </span>
                                    </div>
                                    <div>
                                        <span class="fw-medium">{{ $request->supporter->name }}</span>
                                        @if ($request->supporter_id == Auth::user()->id)
                                            <span class="badge bg-info ms-1">{{ __('You') }}</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-{{ $request->priority_badge }}">
                                    @if ($request->priority == 'low')
                                        <i class="ti ti-arrow-down me-1"></i>
                                    @elseif($request->priority == 'high')
                                        <i class="ti ti-arrow-up me-1"></i>
                                    @elseif($request->priority == 'urgent')
                                        <i class="ti ti-alert-triangle me-1"></i>
                                    @endif
                                    {{ __(ucfirst($request->priority)) }}
                                </span>
                            </td>
                            <td>
                                @if ($request->supporter_id == Auth::user()->id && $request->status != 'completed' && $request->status != 'cancelled')
                                    <select class="form-select form-select-sm status-quick-update"
                                        data-request-id="{{ $request->id }}" style="width: auto;">
                                        <option value="pending" {{ $request->status == 'pending' ? 'selected' : '' }}>
                                            {{ __('Pending') }}</option>
                                        <option value="in_progress"
                                            {{ $request->status == 'in_progress' ? 'selected' : '' }}>
                                            {{ __('In Progress') }}</option>
                                        <option value="completed"
                                            {{ $request->status == 'completed' ? 'selected' : '' }}>
                                            {{ __('Completed') }}</option>
                                        <option value="cancelled"
                                            {{ $request->status == 'cancelled' ? 'selected' : '' }}>
                                            {{ __('Cancelled') }}</option>
                                    </select>
                                @else
                                    <span class="badge bg-{{ $request->status_badge }}">
                                        @if ($request->status == 'pending')
                                            <i class="ti ti-clock me-1"></i>
                                        @elseif($request->status == 'in_progress')
                                            <i class="ti ti-refresh me-1"></i>
                                        @elseif($request->status == 'completed')
                                            <i class="ti ti-check me-1"></i>
                                        @elseif($request->status == 'cancelled')
                                            <i class="ti ti-x me-1"></i>
                                        @endif
                                        {{ __(ucfirst(str_replace('_', ' ', $request->status))) }}
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if ($request->client_subscribed !== null)
                                    @if ($request->client_subscribed)
                                        <span class="badge bg-success">
                                            <i class="ti ti-check me-1"></i>{{ __('Yes') }}
                                        </span>
                                        @if ($request->subscription_amount)
                                            <div class="text-success fw-bold mt-1">
                                                ${{ number_format($request->subscription_amount, 2) }}
                                            </div>
                                        @endif
                                    @else
                                        <span class="badge bg-secondary">
                                            <i class="ti ti-x me-1"></i>{{ __('No') }}
                                        </span>
                                    @endif
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                <small class="text-muted">
                                    {{ $request->created_at->diffForHumans() }}
                                </small>
                            </td>
                            <td>
                                @if ($request->responded_at)
                                    <span class="badge bg-success">
                                        <i class="ti ti-check me-1"></i>
                                        {{ $request->responded_at->diffForHumans($request->created_at, true) }}
                                    </span>
                                @else
                                    <span class="badge bg-warning">
                                        <i class="ti ti-clock me-1"></i>
                                        {{ __('Waiting') }}
                                    </span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="btn-group" role="group">
                                    <a href="{{ route('support-requests.show', $request->id) }}"
                                        class="btn btn-sm btn-info" data-bs-toggle="tooltip"
                                        title="{{ __('View Details') }}">
                                        <i class="ti ti-eye"></i>
                                    </a>
                                    @if ($request->requester_id == Auth::user()->id || $request->supporter_id == Auth::user()->id)
                                        @can('edit deal')
                                            <a href="{{ route('support-requests.edit', $request->id) }}"
                                                class="btn btn-sm btn-primary" data-bs-toggle="tooltip"
                                                title="{{ __('Edit') }}">
                                                <i class="ti ti-pencil"></i>
                                            </a>
                                        @endcan
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="ti ti-inbox" style="font-size: 48px;"></i>
                                    <p class="mt-3">{{ __('No support requests found') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($supportRequests->hasPages())
        <div class="d-flex justify-content-center mt-3">
            {{ $supportRequests->links('pagination::bootstrap-5') }}
        </div>
        @endif
    </div>
</div>
