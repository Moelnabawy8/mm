@extends('layouts.admin')

@section('page-title'){{ __('Create Customer') }}@endsection

@push('script-page')
<script>
    $(document).on('click', '#billing_data', function () {
        $("[name='shipping_name']").val($("[name='billing_name']").val());
        $("[name='shipping_country']").val($("[name='billing_country']").val());
        $("[name='shipping_state']").val($("[name='billing_state']").val());
        $("[name='shipping_city']").val($("[name='billing_city']").val());
        $("[name='shipping_phone']").val($("[name='billing_phone']").val());
        $("[name='shipping_zip']").val($("[name='billing_zip']").val());
        $("[name='shipping_address']").val($("[name='billing_address']").val());
    });
</script>
@endpush

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('customer.index') }}">{{ __('Customer') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Create Customer') }}</li>
@endsection

@section('action-btn')
    <div class="float-end">
        <a href="{{ route('customer.index') }}" class="btn btn-sm btn-secondary">
            <i class="ti ti-arrow-left me-1"></i>{{ __('Back') }}
        </a>
    </div>
@endsection

@section('content')

{{Form::open(['url' => 'customer', 'method' => 'post', 'class' => 'needs-validation', 'novalidate' => true])}}

<div class="row">

    {{-- Basic Info --}}
    <div class="col-12 mb-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header d-flex align-items-center gap-2">
                <span class="bg-primary text-white rounded p-1">
                    <i class="ti ti-user" style="font-size:16px"></i>
                </span>
                <h5 class="mb-0">{{ __('Basic Info') }}</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-lg-4 col-md-6">
                        <div class="form-group">
                            {{ Form::label('name', __('Name'), ['class' => 'form-label']) }}<x-required></x-required>
                            {{ Form::text('name', null, ['class' => 'form-control', 'required' => 'required', 'placeholder' => __('Enter Name')]) }}
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <div class="form-group">
                            <x-mobile label="{{ __('Contact') }}" name="contact" value="{{ old('contact') }}" required placeholder="Enter Contact"></x-mobile>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <div class="form-group">
                            {{ Form::label('email', __('Email'), ['class' => 'form-label']) }}<x-required></x-required>
                            {{ Form::email('email', null, ['class' => 'form-control', 'placeholder' => __('Enter email'), 'required' => 'required']) }}
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <div class="form-group">
                            {{ Form::label('tax_number', __('Tax Number'), ['class' => 'form-label']) }}
                            {{ Form::text('tax_number', null, ['class' => 'form-control', 'placeholder' => __('Enter Tax Number')]) }}
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <div class="form-group">
                            {{ Form::label('balance', __('Balance'), ['class' => 'form-label']) }}
                            {{ Form::number('balance', null, ['class' => 'form-control', 'placeholder' => __('Enter Balance')]) }}
                        </div>
                    </div>
                    @if(!$customFields->isEmpty())
                        <div class="col-12">
                            @include('customFields.formBuilder')
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Billing Address --}}
    <div class="col-md-6 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header d-flex align-items-center gap-2">
                <span class="bg-success text-white rounded p-1">
                    <i class="ti ti-map-pin" style="font-size:16px"></i>
                </span>
                <h5 class="mb-0">{{ __('Billing Address') }}</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6">
                        <div class="form-group">
                            {{ Form::label('billing_name', __('Name'), ['class' => 'form-label']) }}
                            {{ Form::text('billing_name', null, ['class' => 'form-control', 'placeholder' => __('Enter Name')]) }}
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            {{ Form::label('billing_phone', __('Phone'), ['class' => 'form-label']) }}
                            {{ Form::text('billing_phone', null, ['class' => 'form-control', 'placeholder' => __('Enter Phone')]) }}
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-group">
                            {{ Form::label('billing_address', __('Address'), ['class' => 'form-label']) }}
                            {{ Form::textarea('billing_address', null, ['class' => 'form-control', 'rows' => 3, 'placeholder' => __('Enter Address')]) }}
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            {{ Form::label('billing_city', __('City'), ['class' => 'form-label']) }}
                            {{ Form::text('billing_city', null, ['class' => 'form-control', 'placeholder' => __('Enter City')]) }}
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            {{ Form::label('billing_state', __('State'), ['class' => 'form-label']) }}
                            {{ Form::text('billing_state', null, ['class' => 'form-control', 'placeholder' => __('Enter State')]) }}
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            {{ Form::label('billing_country', __('Country'), ['class' => 'form-label']) }}
                            {{ Form::text('billing_country', null, ['class' => 'form-control', 'placeholder' => __('Enter Country')]) }}
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            {{ Form::label('billing_zip', __('Zip Code'), ['class' => 'form-label']) }}
                            {{ Form::text('billing_zip', null, ['class' => 'form-control', 'placeholder' => __('Enter Zip Code')]) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Shipping Address --}}
    @if(App\Models\Utility::getValByName('shipping_display') == 'on')
    <div class="col-md-6 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header d-flex align-items-center gap-2 justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <span class="bg-info text-white rounded p-1">
                        <i class="ti ti-truck-delivery" style="font-size:16px"></i>
                    </span>
                    <h5 class="mb-0">{{ __('Shipping Address') }}</h5>
                </div>
                <button type="button" id="billing_data" class="btn btn-sm btn-outline-primary">
                    <i class="ti ti-copy me-1"></i>{{ __('Same as Billing') }}
                </button>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6">
                        <div class="form-group">
                            {{ Form::label('shipping_name', __('Name'), ['class' => 'form-label']) }}
                            {{ Form::text('shipping_name', null, ['class' => 'form-control', 'placeholder' => __('Enter Name')]) }}
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            {{ Form::label('shipping_phone', __('Phone'), ['class' => 'form-label']) }}
                            {{ Form::text('shipping_phone', null, ['class' => 'form-control', 'placeholder' => __('Enter Phone')]) }}
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-group">
                            {{ Form::label('shipping_address', __('Address'), ['class' => 'form-label']) }}
                            {{ Form::textarea('shipping_address', null, ['class' => 'form-control', 'rows' => 3, 'placeholder' => __('Enter Address')]) }}
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            {{ Form::label('shipping_city', __('City'), ['class' => 'form-label']) }}
                            {{ Form::text('shipping_city', null, ['class' => 'form-control', 'placeholder' => __('Enter City')]) }}
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            {{ Form::label('shipping_state', __('State'), ['class' => 'form-label']) }}
                            {{ Form::text('shipping_state', null, ['class' => 'form-control', 'placeholder' => __('Enter State')]) }}
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            {{ Form::label('shipping_country', __('Country'), ['class' => 'form-label']) }}
                            {{ Form::text('shipping_country', null, ['class' => 'form-control', 'placeholder' => __('Enter Country')]) }}
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            {{ Form::label('shipping_zip', __('Zip Code'), ['class' => 'form-label']) }}
                            {{ Form::text('shipping_zip', null, ['class' => 'form-control', 'placeholder' => __('Enter Zip Code')]) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

</div>

{{-- Submit Row --}}
<div class="d-flex justify-content-end gap-2 mb-4">
    <a href="{{ route('customer.index') }}" class="btn btn-secondary px-4">
        <i class="ti ti-x me-1"></i>{{ __('Cancel') }}
    </a>
    <button type="submit" class="btn btn-primary px-4">
        <i class="ti ti-check me-1"></i>{{ __('Create Customer') }}
    </button>
</div>

{{Form::close()}}

@endsection
