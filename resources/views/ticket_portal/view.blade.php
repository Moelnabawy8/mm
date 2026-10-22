@extends('ticket_portal.layout')

@section('title', $ticket->ticket_code . ' - ' . $settings->localized_title)

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <a href="{{ route('ticket-portal.track', $settings->portal_slug) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>{{ __('Back to Tracking') }}
                </a>
                <span class="status-badge {{ $ticket->status_badge_class }}">{{ $ticket->status }}</span>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <div class="d-flex align-items-center justify-content-between">
                        <h5 class="mb-0">
                            <i class="bi bi-ticket-perforated me-2"></i>
                            <span class="text-primary">{{ $ticket->ticket_code }}</span>
                        </h5>
                        <span class="priority-badge {{ $ticket->priority_badge_class }}">
                            {{ \App\Models\Support::$priority[$ticket->priority] ?? 'Unknown' }}
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <h4 class="mb-3">{{ $ticket->subject }}</h4>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>{{ __('Submitted by') }}:</strong> {{ $ticket->submitter_name }}</p>
                            @if ($ticket->submitter_email)
                                <p class="mb-1"><strong>{{ __('Email') }}:</strong> {{ $ticket->submitter_email }}</p>
                            @endif
                            @if ($ticket->submitter_phone)
                                <p class="mb-1"><strong>{{ __('Phone') }}:</strong> {{ $ticket->submitter_phone }}</p>
                            @endif
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong>{{ __('Created') }}:</strong>
                                {{ $ticket->created_at->format('Y-m-d H:i') }}</p>
                            @if ($ticket->category)
                                <p class="mb-1"><strong>{{ __('Category') }}:</strong>
                                    {{ $ticket->category->localized_name }}</p>
                            @endif
                        </div>
                    </div>

                    <div class="bg-light p-3 rounded mb-4">
                        <h6 class="mb-2">{{ __('Description') }}</h6>
                        <p class="mb-0">{!! nl2br(e($ticket->description)) !!}</p>
                    </div>

                    @if ($ticket->custom_fields_data && count($ticket->custom_fields_data) > 0)
                        <div class="mb-4">
                            <h6 class="mb-3">{{ __('Additional Information') }}</h6>
                            <div class="row">
                                @foreach ($customFields as $field)
                                    @php $value = $ticket->getCustomFieldValue($field->id); @endphp
                                    @if ($value)
                                        <div class="col-md-6 mb-2">
                                            <strong>{{ $field->localized_name }}:</strong>
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

                    @if ($ticket->attachments->count() > 0)
                        <div class="mb-4">
                            <h6 class="mb-3">{{ __('Attachments') }}</h6>
                            <div class="row">
                                @foreach ($ticket->attachments->where('reply_id', null) as $attachment)
                                    <div class="col-md-4 mb-2">
                                        <a href="{{ asset('storage/' . $attachment->file_path) }}" target="_blank"
                                            class="d-flex align-items-center bg-light p-2 rounded text-decoration-none">
                                            <i class="bi bi-file-earmark me-2 text-primary"></i>
                                            <span class="text-truncate">{{ $attachment->original_name }}</span>
                                            <small class="text-muted ms-auto">{{ $attachment->formatted_size }}</small>
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-chat-dots me-2"></i>{{ __('Conversation') }}</h5>
                </div>
                <div class="card-body">
                    <div id="alertContainer"></div>

                    @forelse($ticket->replies as $reply)
                        <div class="reply-card {{ $reply->reply_from == 'staff' ? 'staff' : 'customer' }} p-3">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <strong>
                                        @if ($reply->reply_from == 'staff')
                                            <i class="bi bi-headset me-1"></i>{{ __('Support Team') }}
                                        @else
                                            <i class="bi bi-person me-1"></i>{{ $ticket->submitter_name }}
                                        @endif
                                    </strong>
                                </div>
                                <small class="text-muted">{{ $reply->created_at->format('Y-m-d H:i') }}</small>
                            </div>
                            <p class="mb-0">{!! nl2br(e($reply->description)) !!}</p>

                            @if ($reply->attachment)
                                <div class="mt-2">
                                    <a href="{{ asset('storage/ticket_attachments/' . $ticket->id . '/' . $reply->attachment) }}"
                                        target="_blank" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-paperclip me-1"></i>{{ __('Attachment') }}
                                    </a>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-chat-square-dots" style="font-size: 2rem;"></i>
                            <p class="mt-2 mb-0">{{ __('No replies yet') }}</p>
                        </div>
                    @endforelse
                </div>
            </div>

            @if (!in_array($ticket->status, ['Closed', 'Close']))
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-reply me-2"></i>{{ __('Add Reply') }}</h5>
                    </div>
                    <div class="card-body">
                        <form id="replyForm" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-3">
                                <textarea class="form-control" id="message" name="message" rows="4" required
                                    placeholder="{{ __('Type your message here...') }}"></textarea>
                            </div>

                            @if ($settings->allow_attachments)
                                <div class="mb-3">
                                    <label for="attachment" class="form-label">{{ __('Attachment') }}
                                        ({{ __('Optional') }})</label>
                                    <input type="file" class="form-control" id="attachment" name="attachment"
                                        accept=".{{ str_replace(',', ',.', $settings->allowed_file_types) }}">
                                    <small class="text-muted">
                                        {{ __('Max') }} {{ $settings->max_file_size }}MB.
                                        {{ __('Allowed') }}: {{ $settings->allowed_file_types }}
                                    </small>
                                </div>
                            @endif

                            <button type="submit" class="btn btn-primary">
                                <span class="spinner-border spinner-border-sm loading-spinner me-2" role="status"></span>
                                <span class="btn-text"><i class="bi bi-send me-2"></i>{{ __('Send Reply') }}</span>
                            </button>
                        </form>
                    </div>
                </div>
            @else
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    {{ __('This ticket is closed and cannot receive new replies.') }}
                </div>
            @endif
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('replyForm');
            if (!form) return;

            const submitBtn = form.querySelector('button[type="submit"]');

            form.addEventListener('submit', async function(e) {
                e.preventDefault();

                showLoading(submitBtn);

                const formData = new FormData(form);

                try {
                    const response = await fetch(
                        '{{ route('ticket-portal.reply', $ticket->tracking_token) }}', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json'
                            },
                            body: formData
                        });

                    const data = await response.json();

                    if (response.ok && data.success) {
                        showAlert(document.getElementById('alertContainer'), 'success', data.message);
                        form.reset();
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        if (data.errors) {
                            let errorMessages = [];
                            for (const [field, messages] of Object.entries(data.errors)) {
                                errorMessages.push(messages.join(', '));
                            }
                            showAlert(document.getElementById('alertContainer'), 'danger', errorMessages
                                .join('<br>'));
                        } else {
                            showAlert(document.getElementById('alertContainer'), 'danger', data.error ||
                                '{{ __('Something went wrong. Please try again.') }}');
                        }
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showAlert(document.getElementById('alertContainer'), 'danger',
                        '{{ __('Something went wrong. Please try again.') }}');
                } finally {
                    hideLoading(submitBtn);
                }
            });
        });
    </script>
@endsection
