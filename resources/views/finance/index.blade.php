@extends('layouts.admin')

@section('page-title')
    {{ __($pageTitle) }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ url('finance/dashboard') }}">{{ __('Finance') }}</a></li>
    <li class="breadcrumb-item">{{ __($sectionName) }}</li>
    @if($subSectionName)
        <li class="breadcrumb-item active">{{ __($subSectionName) }}</li>
    @endif
@endsection

@section('content')
<div class="row justify-content-center mt-4">
    <div class="col-md-8 col-lg-6">
        <div class="card shadow-sm border-0 text-center py-5">
            <div class="card-body">

                <div class="mb-4">
                    <span style="
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        width: 90px; height: 90px;
                        border-radius: 50%;
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        box-shadow: 0 8px 24px rgba(102,126,234,.35);
                    ">
                        <i class="ti {{ $icon }}" style="font-size: 40px; color: #fff;"></i>
                    </span>
                </div>

                <h3 class="fw-bold mb-2">{{ __($pageTitle) }}</h3>

                <p class="text-muted fs-6 mb-4">
                    {{ __('هذه الصفحة قيد الإنشاء وستكون متاحة قريباً.') }}<br>
                    <span class="text-muted" style="font-size:.85rem;">
                        This section is under development and will be available soon.
                    </span>
                </p>

                <div class="d-flex justify-content-center gap-3 flex-wrap">
                    <a href="{{ url()->previous() }}" class="btn btn-outline-secondary btn-sm px-4">
                        <i class="ti ti-arrow-left me-1"></i>{{ __('Back') }}
                    </a>
                    <a href="{{ url('finance/dashboard') }}" class="btn btn-primary btn-sm px-4">
                        <i class="ti ti-home me-1"></i>{{ __('Finance Home') }}
                    </a>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection
