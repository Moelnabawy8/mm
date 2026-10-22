@extends('layouts.admin')

@section('page-title'){{ __('New commitment') }}@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('commitments.index') }}">{{ __('Weekly Commitments') }}</a></li>
    <li class="breadcrumb-item">{{ __('New commitment') }}</li>
@endsection

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <form method="POST" action="{{ route('commitments.store') }}">
                @csrf
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">{{ __('New commitment') }}</h5>
                        <small class="text-muted">
                            {{ __('A commitment is something one person or department owes another by a date.') }}
                        </small>
                    </div>
                    <div class="card-body">
                        @include('commitments._form')
                    </div>
                    <div class="card-footer d-flex justify-content-between">
                        <a href="{{ route('commitments.index') }}" class="btn btn-light">{{ __('Cancel') }}</a>
                        <button type="submit" class="btn btn-primary">{{ __('Create') }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
