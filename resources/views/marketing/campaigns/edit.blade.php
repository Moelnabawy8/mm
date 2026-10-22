@extends('layouts.admin')

@section('page-title')
    {{ __('Edit Campaign') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('marketing.index') }}">{{ __('Marketing') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('marketing.campaigns') }}">{{ __('Campaigns') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Edit') }}</li>
@endsection

@section('content')
    <div class="row justify-content-center">
        <div class="col-xl-10">
            <div class="card">
                <div class="card-header">
                    <h5>{{ __('Edit Campaign') }}: {{ $campaign->campaign_name }}</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('marketing.campaigns.update', $campaign->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">{{ __('Account') }}</label>
                                <input type="text" class="form-control" value="{{ $campaign->account->account_name }} ({{ ucfirst($campaign->platform) }})" disabled>
                            </div>

                            <div class="col-md-12 mb-3">
                                <label class="form-label">{{ __('Campaign Name') }}</label>
                                <input type="text" name="campaign_name" class="form-control" value="{{ $campaign->campaign_name }}" required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Objective') }}</label>
                                <select name="objective" class="form-control" required>
                                    <option value="awareness" {{ $campaign->objective == 'awareness' ? 'selected' : '' }}>{{ __('Brand Awareness') }}</option>
                                    <option value="traffic" {{ $campaign->objective == 'traffic' ? 'selected' : '' }}>{{ __('Website Traffic') }}</option>
                                    <option value="engagement" {{ $campaign->objective == 'engagement' ? 'selected' : '' }}>{{ __('Engagement') }}</option>
                                    <option value="leads" {{ $campaign->objective == 'leads' ? 'selected' : '' }}>{{ __('Lead Generation') }}</option>
                                    <option value="sales" {{ $campaign->objective == 'sales' ? 'selected' : '' }}>{{ __('Sales') }}</option>
                                    <option value="conversions" {{ $campaign->objective == 'conversions' ? 'selected' : '' }}>{{ __('Conversions') }}</option>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Status') }}</label>
                                <select name="status" class="form-control" required>
                                    <option value="draft" {{ $campaign->status == 'draft' ? 'selected' : '' }}>{{ __('Draft') }}</option>
                                    <option value="active" {{ $campaign->status == 'active' ? 'selected' : '' }}>{{ __('Active') }}</option>
                                    <option value="paused" {{ $campaign->status == 'paused' ? 'selected' : '' }}>{{ __('Paused') }}</option>
                                    <option value="completed" {{ $campaign->status == 'completed' ? 'selected' : '' }}>{{ __('Completed') }}</option>
                                    <option value="cancelled" {{ $campaign->status == 'cancelled' ? 'selected' : '' }}>{{ __('Cancelled') }}</option>
                                </select>
                            </div>

                            <div class="col-md-12 mb-3">
                                <label class="form-label">{{ __('Budget') }}</label>
                                <div class="input-group">
                                    <input type="number" name="budget" class="form-control" step="0.01" min="0" value="{{ $campaign->budget }}" required>
                                    <span class="input-group-text">{{ $campaign->currency }}</span>
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Start Date') }}</label>
                                <input type="date" name="start_date" class="form-control" value="{{ $campaign->start_date ? $campaign->start_date->format('Y-m-d') : '' }}">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('End Date') }}</label>
                                <input type="date" name="end_date" class="form-control" value="{{ $campaign->end_date ? $campaign->end_date->format('Y-m-d') : '' }}">
                            </div>

                            <div class="col-md-12 mb-3">
                                <label class="form-label">{{ __('Description') }}</label>
                                <textarea name="description" class="form-control" rows="4">{{ $campaign->description }}</textarea>
                            </div>
                        </div>

                        <div class="text-end">
                            <a href="{{ route('marketing.campaigns.show', $campaign->id) }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
                            <button type="submit" class="btn btn-primary">{{ __('Update Campaign') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
