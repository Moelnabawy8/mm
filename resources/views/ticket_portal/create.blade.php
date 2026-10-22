@extends('ticket_portal.layout')

@section('title', __('Create New Ticket') . ' - ' . $settings->localized_title)

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <ul class="nav nav-tabs mb-4 justify-content-center">
                <li class="nav-item">
                    <a class="nav-link active" href="{{ route('ticket-portal.show', $settings->portal_slug) }}">
                        <i class="bi bi-plus-circle me-2"></i>{{ __('Create Ticket') }}
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('ticket-portal.track', $settings->portal_slug) }}">
                        <i class="bi bi-search me-2"></i>{{ __('Track Ticket') }}
                    </a>
                </li>
            </ul>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-ticket-perforated me-2"></i>{{ __('Submit a New Support Ticket') }}
                    </h5>
                </div>
                <div class="card-body p-4">
                    <div id="alertContainer"></div>

                    <div id="successMessage" class="d-none">
                        <div class="text-center py-5">
                            <div class="mb-4">
                                <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                            </div>
                            <h4 class="text-success mb-3">{{ __('Ticket Submitted Successfully!') }}</h4>
                            <p class="text-muted mb-4" id="successText"></p>
                            <div class="bg-light p-4 rounded-3 mb-4">
                                <p class="mb-2"><strong>{{ __('Ticket Code') }}:</strong> <span id="ticketCode"
                                        class="text-primary"></span></p>
                                <p class="mb-0"><strong>{{ __('Tracking Link') }}:</strong></p>
                                <a href="#" id="trackingLink" target="_blank" class="text-break"></a>
                            </div>
                            <a href="{{ route('ticket-portal.show', $settings->portal_slug) }}" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-2"></i>{{ __('Create Another Ticket') }}
                            </a>
                        </div>
                    </div>

                    <form id="ticketForm" enctype="multipart/form-data">
                        @csrf
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">{{ __('Your Name') }} <span
                                        class="required-star">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">
                                    {{ __('Email Address') }}
                                    @if ($settings->require_email)
                                        <span class="required-star">*</span>
                                    @endif
                                </label>
                                <input type="email" class="form-control" id="email" name="email"
                                    {{ $settings->require_email ? 'required' : '' }}>
                            </div>
                        </div>

                        @if ($settings->require_phone || true)
                            <div class="mb-3">
                                <label for="phone" class="form-label">
                                    {{ __('Phone Number') }}
                                    @if ($settings->require_phone)
                                        <span class="required-star">*</span>
                                    @endif
                                </label>
                                <input type="tel" class="form-control" id="phone" name="phone"
                                    {{ $settings->require_phone ? 'required' : '' }}>
                            </div>
                        @endif

                        <div class="mb-3">
                            <label for="subject" class="form-label">{{ __('Subject') }} <span
                                    class="required-star">*</span></label>
                            <input type="text" class="form-control" id="subject" name="subject" required>
                        </div>

                        <div class="row">
                            @if ($categories->count() > 0)
                                <div class="col-md-6 mb-3">
                                    <label for="category_id" class="form-label">{{ __('Category') }}</label>
                                    <select class="form-select" id="category_id" name="category_id">
                                        <option value="">{{ __('Select Category') }}</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}">{{ $category->localized_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            <div class="col-md-{{ $categories->count() > 0 ? '6' : '12' }} mb-3">
                                <label for="priority" class="form-label">{{ __('Priority') }} <span
                                        class="required-star">*</span></label>
                                <select class="form-select" id="priority" name="priority" required>
                                    @foreach ($priorities as $key => $priority)
                                        <option value="{{ $key }}" {{ $key == 1 ? 'selected' : '' }}>
                                            {{ __($priority) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">{{ __('Description') }} <span
                                    class="required-star">*</span></label>
                            <textarea class="form-control" id="description" name="description" rows="5" required
                                placeholder="{{ __('Please describe your issue in detail...') }}"></textarea>
                        </div>

                        @foreach ($customFields as $field)
                            <div class="mb-3">
                                <label for="custom_field_{{ $field->id }}" class="form-label">
                                    {{ $field->localized_name }}
                                    @if ($field->is_required)
                                        <span class="required-star">*</span>
                                    @endif
                                </label>

                                @switch($field->type)
                                    @case('text')
                                        <input type="text" class="form-control" id="custom_field_{{ $field->id }}"
                                            name="custom_field_{{ $field->id }}"
                                            placeholder="{{ $field->localized_placeholder }}"
                                            {{ $field->is_required ? 'required' : '' }}>
                                    @break

                                    @case('textarea')
                                        <textarea class="form-control" id="custom_field_{{ $field->id }}" name="custom_field_{{ $field->id }}"
                                            rows="3" placeholder="{{ $field->localized_placeholder }}" {{ $field->is_required ? 'required' : '' }}></textarea>
                                    @break

                                    @case('email')
                                        <input type="email" class="form-control" id="custom_field_{{ $field->id }}"
                                            name="custom_field_{{ $field->id }}"
                                            placeholder="{{ $field->localized_placeholder }}"
                                            {{ $field->is_required ? 'required' : '' }}>
                                    @break

                                    @case('number')
                                        <input type="number" class="form-control" id="custom_field_{{ $field->id }}"
                                            name="custom_field_{{ $field->id }}"
                                            placeholder="{{ $field->localized_placeholder }}"
                                            {{ $field->is_required ? 'required' : '' }}>
                                    @break

                                    @case('date')
                                        <input type="date" class="form-control" id="custom_field_{{ $field->id }}"
                                            name="custom_field_{{ $field->id }}" {{ $field->is_required ? 'required' : '' }}>
                                    @break

                                    @case('select')
                                        <select class="form-select" id="custom_field_{{ $field->id }}"
                                            name="custom_field_{{ $field->id }}" {{ $field->is_required ? 'required' : '' }}>
                                            <option value="">{{ __('Select an option') }}</option>
                                            @foreach ($field->options ?? [] as $option)
                                                <option value="{{ $option }}">{{ $option }}</option>
                                            @endforeach
                                        </select>
                                    @break

                                    @case('radio')
                                        @foreach ($field->options ?? [] as $index => $option)
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio"
                                                    name="custom_field_{{ $field->id }}"
                                                    id="custom_field_{{ $field->id }}_{{ $index }}"
                                                    value="{{ $option }}"
                                                    {{ $field->is_required && $index == 0 ? 'required' : '' }}>
                                                <label class="form-check-label"
                                                    for="custom_field_{{ $field->id }}_{{ $index }}">
                                                    {{ $option }}
                                                </label>
                                            </div>
                                        @endforeach
                                    @break

                                    @case('checkbox')
                                        @foreach ($field->options ?? [] as $index => $option)
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox"
                                                    name="custom_field_{{ $field->id }}[]"
                                                    id="custom_field_{{ $field->id }}_{{ $index }}"
                                                    value="{{ $option }}">
                                                <label class="form-check-label"
                                                    for="custom_field_{{ $field->id }}_{{ $index }}">
                                                    {{ $option }}
                                                </label>
                                            </div>
                                        @endforeach
                                    @break
                                @endswitch
                            </div>
                        @endforeach

                        @if ($settings->allow_attachments)
                            <div class="mb-4">
                                <label class="form-label">{{ __('Attachments') }}</label>
                                <div class="file-upload-area" id="fileUploadArea">
                                    <i class="bi bi-cloud-upload"
                                        style="font-size: 2rem; color: var(--primary-color);"></i>
                                    <p class="mb-1 mt-2">{{ __('Drag & drop files here or click to browse') }}</p>
                                    <small class="text-muted">
                                        {{ __('Max') }} {{ $settings->max_attachments }} {{ __('files') }},
                                        {{ $settings->max_file_size }}MB {{ __('each') }}.
                                        {{ __('Allowed') }}: {{ $settings->allowed_file_types }}
                                    </small>
                                    <input type="file" id="attachments" name="attachments[]" multiple class="d-none"
                                        accept=".{{ str_replace(',', ',.', $settings->allowed_file_types) }}">
                                </div>
                                <div id="fileList" class="mt-2"></div>
                            </div>
                        @endif

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <span class="spinner-border spinner-border-sm loading-spinner me-2" role="status"></span>
                                <span class="btn-text"><i class="bi bi-send me-2"></i>{{ __('Submit Ticket') }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('ticketForm');
            const fileUploadArea = document.getElementById('fileUploadArea');
            const fileInput = document.getElementById('attachments');
            const fileList = document.getElementById('fileList');
            const submitBtn = form.querySelector('button[type="submit"]');

            // File upload handling
            if (fileUploadArea && fileInput) {
                fileUploadArea.addEventListener('click', () => fileInput.click());

                fileUploadArea.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    fileUploadArea.classList.add('dragover');
                });

                fileUploadArea.addEventListener('dragleave', () => {
                    fileUploadArea.classList.remove('dragover');
                });

                fileUploadArea.addEventListener('drop', (e) => {
                    e.preventDefault();
                    fileUploadArea.classList.remove('dragover');
                    fileInput.files = e.dataTransfer.files;
                    updateFileList();
                });

                fileInput.addEventListener('change', updateFileList);
            }

            function updateFileList() {
                fileList.innerHTML = '';
                const files = fileInput.files;

                Array.from(files).forEach((file, index) => {
                    const fileItem = document.createElement('div');
                    fileItem.className = 'd-flex align-items-center bg-light rounded p-2 mb-2';
                    fileItem.innerHTML = `
                <i class="bi bi-file-earmark me-2"></i>
                <span class="flex-grow-1">${file.name}</span>
                <small class="text-muted me-2">${(file.size / 1024 / 1024).toFixed(2)} MB</small>
            `;
                    fileList.appendChild(fileItem);
                });
            }

            // Form submission
            form.addEventListener('submit', async function(e) {
                e.preventDefault();

                showLoading(submitBtn);

                const formData = new FormData(form);

                try {
                    const response = await fetch(
                        '{{ route('ticket-portal.submit', $settings->portal_slug) }}', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json'
                            },
                            body: formData
                        });

                    const data = await response.json();

                    if (response.ok && data.success) {
                        document.getElementById('ticketForm').classList.add('d-none');
                        document.getElementById('successMessage').classList.remove('d-none');
                        document.getElementById('successText').textContent = data.message;
                        document.getElementById('ticketCode').textContent = data.ticket_code;
                        document.getElementById('trackingLink').href = data.tracking_url;
                        document.getElementById('trackingLink').textContent = data.tracking_url;
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
