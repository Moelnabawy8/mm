@extends('layouts.admin')

@section('page-title'){{ __('Edit commitment') }}@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('commitments.show', $commitment->id) }}">{{ __('Commitment') }}</a></li>
    <li class="breadcrumb-item">{{ __('Edit') }}</li>
@endsection

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <form method="POST" action="{{ route('commitments.update', $commitment->id) }}">
                @csrf
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">{{ __('Edit commitment') }}</h5>
                        <small class="text-muted">
                            {{ __('Who owes it and its cadence cannot change — that would rewrite what was agreed, not edit it.') }}
                        </small>
                    </div>
                    <div class="card-body">
                        @include('commitments._form')
                    </div>
                    <div class="card-footer d-flex justify-content-between">
                        <a href="{{ route('commitments.show', $commitment->id) }}" class="btn btn-light">{{ __('Cancel') }}</a>
                        <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
