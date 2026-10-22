@extends('layouts.admin')

@section('page-title')
    {{ __('Ticket Details') }} - {{ $ticket->ticket_code }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ticket-management.index') }}">{{ __('Public Tickets') }}</a></li>
    <li class="breadcrumb-item">{{ $ticket->ticket_code }}</li>
@endsection

@section('action-btn')
    <div class="row align-items-center">
        <div class="col-auto">
            <a href="{{ route('ticket-management.index') }}" class="btn btn-sm btn-secondary">
                <i class="ti ti-arrow-left"></i> {{ __('Back') }}
            </a>
        </div>
    </div>
@endsection

@section('content')
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex align-items-center justify-content-between">
                        <h5 class="mb-0">
                            <i class="ti ti-ticket me-2"></i>{{ $ticket->ticket_code }}
                        </h5>
                        @php
                            $statusClasses = [
                                'New' => 'bg-info',
                                'Open' => 'bg-warning',
                                'In Progress' => 'bg-primary',
                                'Resolved' => 'bg-success',
                                'Closed' => 'bg-danger',
                            ];
                        @endphp
                        <span class="badge {{ $statusClasses[$ticket->status] ?? 'bg-secondary' }}">
                            {{ __($ticket->status) }}
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <h4 class="mb-3">{{ $ticket->subject }}</h4>

                    <div class="bg-light p-3 rounded mb-4">
                        <h6 class="mb-2">{{ __('Description') }}</h6>
                        <p class="mb-0">{!! nl2br(e($ticket->description)) !!}</p>
                    </div>

                    @if ($ticket->custom_fields_data && count($ticket->custom_fields_data) > 0)
                        <div class="mb-4">
                            <h6 class="mb-3">{{ __('Custom Fields') }}</h6>
                            <div class="row">
                                @foreach ($customFields as $field)
                                    @php $value = $ticket->getCustomFieldValue($field->id); @endphp
                                    @if ($value)
                                        <div class="col-md-6 mb-2">
                                            <strong>{{ $field->name }}:</strong>
                                            @if (is_array($value))
                                                {{ implode(', ', $value) }}
                                            @else
                                                {{ $value }}
                                            @endif
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($ticket->attachments->where('reply_id', null)->count() > 0)
                        <div class="mb-4">
                            <h6 class="mb-3">{{ __('Attachments') }}</h6>
                            <div class="row">
                                @foreach ($ticket->attachments->where('reply_id', null) as $attachment)
                                    <div class="col-md-4 mb-2">
                                        <a href="{{ asset('storage/' . $attachment->file_path) }}" target="_blank"
                                            class="d-flex align-items-center bg-light p-2 rounded text-decoration-none">
                                            <i class="ti ti-file me-2 text-primary"></i>
                                            <span class="text-truncate">{{ $attachment->original_name }}</span>
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="ti ti-messages me-2"></i>{{ __('Conversation') }}</h5>
                </div>
                <div class="card-body">
                    <div id="alertContainer"></div>

                    @forelse($ticket->replies as $reply)
                        <div class="mb-3 p-3 rounded {{ $reply->reply_from == 'staff' ? 'bg-primary-subtle' : 'bg-light' }}"
                            style="{{ $reply->reply_from == 'staff' ? 'border-left: 4px solid var(--bs-primary);' : 'border-left: 4px solid #ccc;' }}">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <strong>
                                        @if ($reply->reply_from == 'staff')
                                            @if ($reply->users)
                                                <i class="ti ti-user-circle me-1"></i>{{ $reply->users->name }}
                                            @else
                                                <i class="ti ti-headset me-1"></i>{{ __('Support Team') }}
                                            @endif
                                            @if ($reply->is_internal_note)
                                                <span class="badge bg-warning ms-2">{{ __('Internal Note') }}</span>
                                            @endif
                                        @else
                                            <i class="ti ti-user me-1"></i>{{ $ticket->submitter_name }}
                                        @endif
                                    </strong>
                                </div>
                                <small class="text-muted">{{ $reply->created_at->format('Y-m-d H:i') }}</small>
                            </div>
                            <p class="mb-0">{!! nl2br(e($reply->description)) !!}</p>

                            @if ($reply->attachment)
                                <div class="mt-2">
                                    <a href="{{ asset('storage/ticket_attachments/' . $ticket->id . '/' . $reply->attachment) }}"
                                        target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="ti ti-paperclip me-1"></i>{{ __('Attachment') }}
                                    </a>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="text-center py-4 text-muted">
                            <i class="ti ti-messages" style="font-size: 2rem;"></i>
                            <p class="mt-2 mb-0">{{ __('No replies yet') }}</p>
                        </div>
                    @endforelse

                    <hr class="my-4">

                    <form id="replyForm" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label for="message" class="form-label">{{ __('Add Reply') }}</label>
                            <textarea class="form-control" id="message" name="message" rows="4" required
                                placeholder="{{ __('Type your reply here...') }}"></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="attachment" class="form-label">{{ __('Attachment') }}</label>
                                <input type="file" class="form-control" id="attachment" name="attachment">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">&nbsp;</label>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" id="is_internal" name="is_internal"
                                        value="1">
                                    <label class="form-check-label" for="is_internal">
                                        {{ __('Internal Note') }} <small
                                            class="text-muted">({{ __('Not visible to customer') }})</small>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary" id="submitReplyBtn">
                            <i class="ti ti-send me-2"></i>{{ __('Send Reply') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Ticket Details') }}</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <th>{{ __('Submitter') }}</th>
                            <td>{{ $ticket->submitter_name }}</td>
                        </tr>
                        <tr>
                            <th>{{ __('Email') }}</th>
                            <td>{{ $ticket->submitter_email ?: '-' }}</td>
                        </tr>
                        <tr>
                            <th>{{ __('Phone') }}</th>
                            <td>{{ $ticket->submitter_phone ?: '-' }}</td>
                        </tr>
                        <tr>
                            <th>{{ __('Category') }}</th>
                            <td>
                                @if ($ticket->category)
                                    <span class="badge" style="background-color: {{ $ticket->category->color }}">
                                        {{ $ticket->category->name }}
                                    </span>
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>{{ __('Priority') }}</th>
                            <td>
                                @php
                                    $priorityClasses = ['bg-secondary', 'bg-info', 'bg-warning', 'bg-danger'];
                                @endphp
                                <span class="badge {{ $priorityClasses[$ticket->priority] ?? 'bg-secondary' }}">
                                    {{ \App\Models\Support::$priority[$ticket->priority] ?? 'Unknown' }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>{{ __('Created') }}</th>
                            <td>{{ $ticket->created_at->format('Y-m-d H:i') }}</td>
                        </tr>
                        @if ($ticket->resolved_at)
                            <tr>
                                <th>{{ __('Resolved') }}</th>
                                <td>{{ $ticket->resolved_at->format('Y-m-d H:i') }}</td>
                            </tr>
                        @endif
                        @if ($ticket->closed_at)
                            <tr>
                                <th>{{ __('Closed') }}</th>
                                <td>{{ $ticket->closed_at->format('Y-m-d H:i') }}</td>
                            </tr>
                        @endif
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Actions') }}</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="status" class="form-label">{{ __('Status') }}</label>
                        <select class="form-select" id="status" data-ticket-id="{{ $ticket->id }}">
                            @foreach ($statuses as $status)
                                <option value="{{ $status }}" {{ $ticket->status == $status ? 'selected' : '' }}>
                                    {{ __($status) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="assigned_user" class="form-label">{{ __('Assign To') }}</label>
                        <select class="form-select" id="assigned_user" data-ticket-id="{{ $ticket->id }}">
                            <option value="">{{ __('Unassigned') }}</option>
                            @foreach ($users as $id => $name)
                                <option value="{{ $id }}" {{ $ticket->user == $id ? 'selected' : '' }}>
                                    {{ $name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="bg-light p-3 rounded">
                        <h6 class="mb-2">{{ __('Tracking Link') }}</h6>
                        <small class="text-muted d-block text-break">{{ $ticket->tracking_url }}</small>
                        <button class="btn btn-sm btn-outline-primary mt-2"
                            onclick="copyToClipboard('{{ $ticket->tracking_url }}')">
                            <i class="ti ti-copy"></i> {{ __('Copy Link') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Ticket management script loaded');
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
            console.log('CSRF Token:', csrfToken);

            // Status change
            document.getElementById('status').addEventListener('change', async function() {
                console.log('Status change triggered');
                const ticketId = this.dataset.ticketId;
                const status = this.value;
                console.log('Ticket ID:', ticketId, 'New Status:', status);

                try {
                    console.log('Sending status update request...');
                    const response = await fetch(`/ticket-management/ticket/${ticketId}/status`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            status: status
                        })
                    });

                    console.log('Response status:', response.status);
                    const data = await response.json();
                    console.log('Response data:', data);

                    if (data.success) {
                        show_toastr('success', data.message, 'success');
                    } else {
                        show_toastr('Error', data.error || 'Failed to update status', 'error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    show_toastr('Error', 'Something went wrong', 'error');
                }
            });

            // User assignment
            document.getElementById('assigned_user').addEventListener('change', async function() {
                console.log('Assign user change triggered');
                const ticketId = this.dataset.ticketId;
                const userId = this.value;
                console.log('Ticket ID:', ticketId, 'User ID:', userId);

                try {
                    console.log('Sending assign user request...');
                    const response = await fetch(`/ticket-management/ticket/${ticketId}/assign`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            user_id: userId
                        })
                    });

                    console.log('Response status:', response.status);
                    const data = await response.json();
                    console.log('Response data:', data);

                    if (data.success) {
                        show_toastr('success', data.message, 'success');
                    } else {
                        show_toastr('Error', data.error || 'Failed to assign user', 'error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    show_toastr('Error', 'Something went wrong', 'error');
                }
            });

            // Reply form
            document.getElementById('replyForm').addEventListener('submit', async function(e) {
                e.preventDefault();

                const submitBtn = document.getElementById('submitReplyBtn');
                submitBtn.disabled = true;
                submitBtn.innerHTML =
                    '<span class="spinner-border spinner-border-sm me-2"></span>{{ __('Sending...') }}';

                const formData = new FormData(this);

                try {
                    const response = await fetch(
                        `/ticket-management/ticket/{{ $ticket->id }}/reply`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json'
                            },
                            body: formData
                        });

                    const data = await response.json();

                    if (data.success) {
                        show_toastr('success', data.message, 'success');
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="ti ti-send me-2"></i>{{ __('Send Reply') }}';

                        if (data.errors) {
                            let errorMessages = [];
                            for (const [field, messages] of Object.entries(data.errors)) {
                                errorMessages.push(messages.join(', '));
                            }
                            show_toastr('Error', errorMessages.join('<br>'), 'error');
                        } else {
                            show_toastr('Error', data.error || 'Failed to send reply', 'error');
                        }
                    }
                } catch (error) {
                    console.error('Error:', error);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="ti ti-send me-2"></i>{{ __('Send Reply') }}';
                    show_toastr('Error', 'Something went wrong', 'error');
                }
            });
        });

        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                show_toastr('success', '{{ __('Link copied to clipboard') }}', 'success');
            });
        }
    </script>
@endpush
