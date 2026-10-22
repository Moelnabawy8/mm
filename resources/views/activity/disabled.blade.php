@extends('layouts.admin')

@section('page-title')
    {{ __('Activity Tracking') }}
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="ti ti-clock-pause" style="font-size:48px; color:#999;"></i>
                <h4 class="mt-3">{{ __('Activity Tracking is currently disabled') }}</h4>
                <p class="text-muted">
                    {{ __('Ask a system admin to enable it from System Settings → "activity_tracking_enabled".') }}
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
