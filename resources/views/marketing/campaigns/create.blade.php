@extends('layouts.admin')

@section('page-title')
    {{ __('Create Campaign') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('marketing.index') }}">{{ __('Marketing') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('marketing.campaigns') }}">{{ __('Campaigns') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Create') }}</li>
@endsection

@section('content')
    <div class="row justify-content-center">
        <div class="col-xl-10">
            <div class="card">
                <div class="card-header">
                    <h5>{{ __('Create New Campaign') }}</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('marketing.campaigns.store') }}" method="POST">
                        @csrf
                        
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">{{ __('Select Account') }}</label>
                                <select name="account_id" class="form-control" required>
                                    <option value="">{{ __('Choose an account') }}</option>
                                    @foreach($accounts as $account)
                                        <option value="{{ $account->id }}">
                                            {{ $account->account_name }} ({{ ucfirst($account->platform) }})
                                        </option>
                                    @endforeach
                                </select>
                                @if($accounts->isEmpty())
                                    <small class="text-danger">{{ __('Please connect an account first') }}</small>
                                @endif
                            </div>

                            <div class="col-md-12 mb-3">
                                <label class="form-label">{{ __('Campaign Name') }}</label>
                                <input type="text" name="campaign_name" class="form-control" placeholder="{{ __('Summer Sale 2024') }}" required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Objective') }}</label>
                                <select name="objective" class="form-control" required>
                                    <option value="awareness">{{ __('Brand Awareness') }}</option>
                                    <option value="traffic">{{ __('Website Traffic') }}</option>
                                    <option value="engagement">{{ __('Engagement') }}</option>
                                    <option value="leads">{{ __('Lead Generation') }}</option>
                                    <option value="sales">{{ __('Sales') }}</option>
                                    <option value="conversions">{{ __('Conversions') }}</option>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Budget') }}</label>
                                <div class="input-group">
                                    <input type="number" name="budget" class="form-control" step="0.01" min="0" placeholder="1000.00" required>
                                    <select name="currency" class="form-control" style="max-width: 100px;">
                                        <option value="USD">USD</option>
                                        <option value="EUR">EUR</option>
                                        <option value="GBP">GBP</option>
                                        <option value="EGP">EGP</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Start Date') }}</label>
                                <input type="date" name="start_date" class="form-control">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('End Date') }}</label>
                                <input type="date" name="end_date" class="form-control">
                            </div>

                            <div class="col-md-12 mb-3">
                                <label class="form-label">{{ __('Description') }}</label>
                                <textarea name="description" class="form-control" rows="4" placeholder="{{ __('Campaign description and notes...') }}"></textarea>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <i class="ti ti-info-circle"></i>
                            <strong>{{ __('Note') }}:</strong> {{ __('Campaign will be created in draft status. You can activate it later from the campaign details page.') }}
                        </div>

                        <div class="text-end">
                            <a href="{{ route('marketing.campaigns') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
                            <button type="submit" class="btn btn-primary">{{ __('Create Campaign') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
