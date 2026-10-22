@extends('layouts.admin')
@section('page-title')
    {{ __('Google Meet') }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('Google Meet') }}</li>
@endsection

@section('content')
    <a href="#" data-url="{{ route('google-meet.create') }}" data-ajax-popup="true" data-title="{{ __('Create Meet') }}"
        class="btn btn-sm btn-primary btn-icon"
        style="margin-bottom: 20px; margin-top: 20px; margin-left: auto; display: block; width: fit-content;">
        <i class="ti ti-plus"></i> {{ __('Create') }}
    </a>
    @if (isset($meetings) && !empty($meetings) && count($meetings) > 0)
        <div class="row">
            @foreach ($meetings as $meet)
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5>{{ $meet->meet_name }}</h5>
                            @if (Auth::user()->id == $meet->created_by)
                                <div class="dropdown">
                                    <button class="btn p-0" data-bs-toggle="dropdown"><i
                                            class="ti ti-dots-vertical"></i></button>
                                    <div class="dropdown-menu">
                                        <a href="#" data-url="{{ route('google-meet.edit', $meet->id) }}"
                                            data-ajax-popup="true" class="dropdown-item">{{ __('Edit') }}</a>
                                        {!! Form::open(['method' => 'DELETE', 'route' => ['google-meet.destroy', $meet->id]]) !!}
                                        <button class="dropdown-item text-danger">{{ __('Delete') }}</button>
                                        {!! Form::close() !!}
                                        @if ($meet->status != 'finished')
                                            {!! Form::open(['method' => 'POST', 'route' => ['google-meet.finish', $meet->id]]) !!}
                                            <button class="dropdown-item text-warning">{{ __('Finish') }}</button>
                                            {!! Form::close() !!}
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>
                        <div class="card-body">
                            <p><strong>Start:</strong>
                                {{ \Carbon\Carbon::parse($meet->start_date)->format('l, d M Y - h:i A') }}</p>
                            <p><strong>End:</strong>
                                {{ \Carbon\Carbon::parse($meet->end_date)->format('l, d M Y - h:i A') }}</p>
                            <hr>
                            @if ($meet->status == 'finished')
                                <p class="text-muted">****** (Finished)</p>
                            @else
                                <a href="{{ $meet->meet_link }}" target="_blank"
                                    class="btn btn-primary btn-block">{{ __('Join Meet') }}</a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="col-xl-12 col-lg-12 col-sm-12">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-center mb-0">{{ __('No Meets Found.') }}</h6>
                </div>
            </div>
        </div>
    @endif
@endsection
