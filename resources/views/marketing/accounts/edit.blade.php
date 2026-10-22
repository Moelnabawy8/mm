@extends('layouts.admin')

@section('page-title')
    {{ __('Edit Account') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('marketing.index') }}">{{ __('Marketing') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('marketing.accounts') }}">{{ __('Accounts') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Edit') }}</li>
@endsection

@section('content')
    <div class="row justify-content-center">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header">
                    <h5>{{ __('Edit Account') }}: {{ $account->account_name }}</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('marketing.accounts.update', $account->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">{{ __('Platform') }}</label>
                                <input type="text" class="form-control" value="{{ ucfirst($account->platform) }}" disabled>
                            </div>

                            <div class="col-md-12 mb-3">
                                <label class="form-label">{{ __('Account Name') }}</label>
                                <input type="text" name="account_name" class="form-control" value="{{ $account->account_name }}" required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Page ID') }}</label>
                                <input type="text" name="page_id" class="form-control" value="{{ $account->page_id }}">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Page Name') }}</label>
                                <input type="text" name="page_name" class="form-control" value="{{ $account->page_name }}">
                            </div>

                            <div class="col-md-12 mb-3">
                                <label class="form-label">{{ __('Access Token') }}</label>
                                <textarea name="access_token" class="form-control" rows="3" placeholder="{{ __('Update access token if needed') }}">{{ $account->access_token }}</textarea>
                            </div>

                            <div class="col-md-12 mb-3">
                                <label class="form-label">{{ __('Status') }}</label>
                                <select name="status" class="form-control" required>
                                    <option value="active" {{ $account->status == 'active' ? 'selected' : '' }}>{{ __('Active') }}</option>
                                    <option value="inactive" {{ $account->status == 'inactive' ? 'selected' : '' }}>{{ __('Inactive') }}</option>
                                    <option value="expired" {{ $account->status == 'expired' ? 'selected' : '' }}>{{ __('Expired') }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="text-end">
                            <a href="{{ route('marketing.accounts') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
                            <button type="submit" class="btn btn-primary">{{ __('Update Account') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
