@extends('layouts.admin')
@section('page-title')
    {{ __('Request Team Support') }}
@endsection

@section('title')
    {{ __('Request Team Support') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('support-requests.index') }}">{{ __('Support Requests') }}</a></li>
    <li class="breadcrumb-item">{{ __('Create') }}</li>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="ti ti-help me-2"></i>{{ __('Request Support from Teammate') }}</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('support-requests.store') }}">
                        @csrf

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="lead_id" class="form-label required">{{ __('Lead/Client') }}</label>
                                    @include('deals._lead_picker', [
                                        'pickerId' => 'lead_id',
                                        'fieldName' => 'lead_id',
                                        'selectedId' => $leadId ?? null,
                                        'selectedName' => $selectedLeadName ?? '',
                                        'required' => true,
                                        'searchUrl' => route('support-requests.leads.search'),
                                    ])
                                    <small class="form-text text-muted">
                                        {{ __('Select the client you need support with') }}
                                    </small>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="supporter_id"
                                        class="form-label required">{{ __('Request Support From') }}</label>
                                    <select class="form-control select2" id="supporter_id" name="supporter_id" required>
                                        <option value="">{{ __('Select Teammate') }}</option>
                                        @foreach ($teammates as $teammate)
                                            <option value="{{ $teammate->id }}">
                                                {{ $teammate->name }} - {{ $teammate->email }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="form-text text-muted">
                                        {{ __('Choose the colleague you want to request support from') }}
                                    </small>
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="form-group mb-3">
                                    <label for="priority" class="form-label required">{{ __('Priority Level') }}</label>
                                    <select class="form-control" id="priority" name="priority" required>
                                        <option value="normal" selected>{{ __('Normal') }}</option>
                                        <option value="low">{{ __('Low') }}</option>
                                        <option value="high">{{ __('High') }}</option>
                                        <option value="urgent">{{ __('Urgent') }}</option>
                                    </select>
                                    <small class="form-text text-muted">
                                        {{ __('Set the urgency level of this support request') }}
                                    </small>
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="form-group mb-3">
                                    <label for="notes" class="form-label required">{{ __('Support Notes') }}</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="8" required
                                        placeholder="{{ __('Describe what you have done with this client and what your colleague should do...') }}"></textarea>
                                    <small class="form-text text-muted">
                                        <strong>{{ __('Important:') }}</strong> {{ __('Explain clearly:') }}
                                        <ul class="mb-0 mt-1">
                                            <li>{{ __('What actions you have already taken with this client') }}</li>
                                            <li>{{ __('What the current situation is') }}</li>
                                            <li>{{ __('What you need your colleague to do') }}</li>
                                            <li>{{ __('Any important context or background information') }}</li>
                                        </ul>
                                    </small>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info mt-3">
                            <i class="ti ti-info-circle me-2"></i>
                            <strong>{{ __('Note:') }}</strong>
                            {{ __('Your teammate will be notified about this support request and can update the status as they work on it.') }}
                        </div>

                        <div class="form-group mt-4 text-end">
                            <a href="{{ route('support-requests.index') }}" class="btn btn-secondary">
                                <i class="ti ti-arrow-left me-1"></i>{{ __('Cancel') }}
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="ti ti-check me-1"></i>{{ __('Submit Support Request') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

{{-- No script belongs here.

     This file used to push a block that began with
     $('.select2').select2(...). The theme loads Choices.js, not
     the select2 jQuery plugin, so that call threw "select2 is not a function"
     on every visit -- and the throw killed the rest of the handler, which was
     the code reading ?lead_id= and pre-selecting the lead. Opening this form
     from a lead therefore arrived with nothing chosen.

     Nothing replaces it: SupportRequestController::create() already reads
     lead_id from the query string and the option above carries `selected`, so
     the right lead is picked before the page is even sent. --}}
