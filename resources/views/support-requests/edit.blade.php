@extends('layouts.admin')
@section('page-title')
    {{ __('Edit Support Request') }}
@endsection

@section('title')
    {{ __('Edit Support Request') }} #{{ $supportRequest->id }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('support-requests.index') }}">{{ __('Support Requests') }}</a></li>
    <li class="breadcrumb-item">{{ __('Edit') }}</li>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="ti ti-pencil me-2"></i>
                        @if ($supportRequest->requester_id == Auth::user()->id)
                            {{ __('Edit Support Request') }}
                        @else
                            {{ __('Update Support Status & Response') }}
                        @endif
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('support-requests.update', $supportRequest->id) }}">
                        @csrf
                        @method('PUT')
                        @if (isset($leadId))
                            <input type="hidden" name="lead_id" value="{{ $leadId }}">
                        @endif

                        @if ($supportRequest->requester_id == Auth::user()->id)
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="lead_id" class="form-label required">{{ __('Lead/Client') }}</label>
                                        @include('deals._lead_picker', [
                                            'pickerId' => 'lead_id',
                                            'fieldName' => 'lead_id',
                                            'selectedId' => $supportRequest->lead_id,
                                            'selectedName' => $selectedLeadName ?? '',
                                            'required' => true,
                                            'searchUrl' => route('support-requests.leads.search'),
                                        ])
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="supporter_id"
                                            class="form-label required">{{ __('Request Support From') }}</label>
                                        <select class="form-control select2" id="supporter_id" name="supporter_id" required>
                                            <option value="">{{ __('Select Teammate') }}</option>
                                            @foreach ($teammates as $teammate)
                                                <option value="{{ $teammate->id }}"
                                                    {{ $supportRequest->supporter_id == $teammate->id ? 'selected' : '' }}>
                                                    {{ $teammate->name }} - {{ $teammate->email }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-12">
                                    <div class="form-group mb-3">
                                        <label for="priority"
                                            class="form-label required">{{ __('Priority Level') }}</label>
                                        <select class="form-control" id="priority" name="priority" required>
                                            <option value="low"
                                                {{ $supportRequest->priority == 'low' ? 'selected' : '' }}>
                                                {{ __('Low') }}</option>
                                            <option value="normal"
                                                {{ $supportRequest->priority == 'normal' ? 'selected' : '' }}>
                                                {{ __('Normal') }}</option>
                                            <option value="high"
                                                {{ $supportRequest->priority == 'high' ? 'selected' : '' }}>
                                                {{ __('High') }}</option>
                                            <option value="urgent"
                                                {{ $supportRequest->priority == 'urgent' ? 'selected' : '' }}>
                                                {{ __('Urgent') }}</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-12">
                                    <div class="form-group mb-3">
                                        <label for="notes" class="form-label required">{{ __('Support Notes') }}</label>
                                        <textarea class="form-control" id="notes" name="notes" rows="8" required>{{ $supportRequest->notes }}</textarea>
                                        <small class="form-text text-muted">
                                            {{ __('Describe what you have done with this client and what your colleague should do') }}
                                        </small>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if ($supportRequest->supporter_id == Auth::user()->id)
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="alert alert-info mb-3">
                                        <h6><i class="ti ti-info-circle me-2"></i>{{ __('Original Request') }}</h6>
                                        <div class="mb-2">
                                            <strong>{{ __('From:') }}</strong>
                                            {{ $supportRequest->requester->name }}<br>
                                            <strong>{{ __('Client:') }}</strong>
                                            {{ $supportRequest->lead->name ?? 'N/A' }}<br>
                                            <strong>{{ __('Priority:') }}</strong>
                                            <span
                                                class="badge bg-{{ $supportRequest->priority_badge }}">{{ __(ucfirst($supportRequest->priority)) }}</span>
                                        </div>
                                        <div class="border-top pt-2 mt-2">
                                            <strong>{{ __('Notes:') }}</strong>
                                            <p class="mb-0 mt-1" style="white-space: pre-wrap;">
                                                {{ $supportRequest->notes }}</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-12">
                                    <div class="form-group mb-3">
                                        <label for="status" class="form-label required">{{ __('Status') }}</label>
                                        <select class="form-control" id="status" name="status" required>
                                            <option value="pending"
                                                {{ $supportRequest->status == 'pending' ? 'selected' : '' }}>
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
                                        <small class="form-text text-muted">
                                            {{ __('Update the current status of this support request') }}
                                        </small>
                                    </div>
                                </div>

                                <div class="col-md-12">
                                    <div class="form-group mb-3">
                                        <label for="response_notes"
                                            class="form-label">{{ __('Your Response Notes') }}</label>
                                        <textarea class="form-control" id="response_notes" name="response_notes" rows="8"
                                            placeholder="{{ __('Add notes about what you did to help with this client...') }}">{{ $supportRequest->response_notes }}</textarea>
                                        <small class="form-text text-muted">
                                            {{ __('Describe the actions you took and any important information for your colleague') }}
                                        </small>
                                    </div>
                                </div>

                                <div class="col-md-12">
                                    <div class="card bg-light border-primary mb-3">
                                        <div class="card-header bg-primary text-white">
                                            <h6 class="mb-0"><i
                                                    class="ti ti-currency-dollar me-2"></i>{{ __('Support Value') }}</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group mb-3">
                                                        <label for="client_subscribed"
                                                            class="form-label">{{ __('Did the client subscribe?') }}</label>
                                                        <select class="form-control" id="client_subscribed"
                                                            name="client_subscribed">
                                                            <option value="">{{ __('Select') }}</option>
                                                            <option value="1"
                                                                {{ old('client_subscribed', $supportRequest->client_subscribed) == '1' ? 'selected' : '' }}>
                                                                {{ __('Yes') }}</option>
                                                            <option value="0"
                                                                {{ old('client_subscribed', $supportRequest->client_subscribed) === '0' || old('client_subscribed', $supportRequest->client_subscribed) === 0 ? 'selected' : '' }}>
                                                                {{ __('No') }}</option>
                                                        </select>
                                                        <small class="form-text text-muted">
                                                            {{ __('Indicate whether the client subscribed after your support') }}
                                                        </small>
                                                    </div>
                                                </div>

                                                <div class="col-md-6" id="subscription_amount_field"
                                                    style="display: none;">
                                                    <div class="form-group mb-3">
                                                        <label for="subscription_amount"
                                                            class="form-label">{{ __('Subscription Amount') }}</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text"><i
                                                                    class="ti ti-currency-dollar"></i></span>
                                                            <input type="number" class="form-control"
                                                                id="subscription_amount" name="subscription_amount"
                                                                value="{{ old('subscription_amount', $supportRequest->subscription_amount) }}"
                                                                placeholder="{{ __('Enter amount') }}" step="0.01"
                                                                min="0">
                                                        </div>
                                                        <small class="form-text text-muted">
                                                            {{ __('Enter the subscription amount in your currency') }}
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if (
                            $supportRequest->requester_id == Auth::user()->id &&
                                $supportRequest->status != 'cancelled' &&
                                $supportRequest->status != 'completed')
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="alert alert-warning mb-3">
                                        <h6><i class="ti ti-alert-triangle me-2"></i>{{ __('Cancel Request') }}</h6>
                                        <p class="mb-2">
                                            {{ __('If the supporter is taking too long or you no longer need support, you can cancel this request.') }}
                                        </p>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="status"
                                                value="cancelled" id="cancel_request">
                                            <label class="form-check-label text-danger fw-bold" for="cancel_request">
                                                {{ __('Cancel this support request') }}
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="form-group mt-4 text-end">
                            <a href="{{ route('support-requests.index') }}" class="btn btn-secondary">
                                <i class="ti ti-arrow-left me-1"></i>{{ __('Back') }}
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="ti ti-check me-1"></i>{{ __('Update') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script-page')
    <script>
        $(document).ready(function() {
            // Initialize select2 only if it exists
            if (typeof $.fn.select2 !== 'undefined') {
                try {
                    $('.select2').select2({
                        width: '100%'
                    });
                } catch (e) {
                    console.log('Select2 initialization skipped:', e);
                }
            }

            // Show/hide subscription amount field based on client_subscribed selection
            function toggleSubscriptionAmount() {
                var clientSubscribed = $('#client_subscribed').val();
                var $field = $('#subscription_amount_field');

                console.log('toggleSubscriptionAmount called - value:', clientSubscribed);

                if (clientSubscribed == '1' || clientSubscribed === 1 || clientSubscribed === true) {
                    $field.slideDown(300);
                    console.log('Showing subscription amount field');
                } else {
                    $field.slideUp(300);
                    $('#subscription_amount').val('');
                    console.log('Hiding subscription amount field');
                }
            }

            // Wait for DOM to be fully ready
            setTimeout(function() {
                console.log('Initial toggle check...');
                toggleSubscriptionAmount();
            }, 200);

            // Listen for changes on the select element
            $(document).on('change', '#client_subscribed', function() {
                console.log('Client subscribed dropdown changed');
                toggleSubscriptionAmount();
            });
        });
    </script>
@endpush
