@extends('layouts.admin')
@section('page-title')
    {{ __('Manage Payment Method') }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('Payment Method') }}</li>
@endsection

@section('action-btn')
    <div class="float-end">
        @can('create lead stage')
            <a href="#" data-url="{{ route('sales-meeting-payment-methods.create') }}" data-ajax-popup="true"
                data-title="{{ __('Create Payment Method') }}" data-bs-toggle="tooltip" title="{{ __('Create') }}"
                class="btn btn-sm btn-primary">
                <i class="ti ti-plus"></i>
            </a>
        @endcan
    </div>
@endsection

@section('content')
    <div class="row">
        @include('layouts.crm_setup')
        <div class="col-9">
            <div class="card">
                <div class="card-body table-border-style">
                    <div class="table-responsive">
                        <table class="table datatable">
                            <thead>
                                <tr>
                                    <th>{{ __('Payment Method') }}</th>
                                    <th width="250px">{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($paymentMethods as $paymentMethod)
                                    <tr>
                                        <td>{{ $paymentMethod->name }}</td>
                                        <td class="Action">
                                            <span>
                                                @can('edit lead stage')
                                                    <div class="action-btn me-2">
                                                        <a href="#" class="mx-3 btn btn-sm align-items-center bg-info"
                                                            data-url="{{ route('sales-meeting-payment-methods.edit', $paymentMethod->id) }}"
                                                            data-ajax-popup="true" data-title="{{ __('Edit Payment Method') }}"
                                                            data-bs-toggle="tooltip" title="{{ __('Edit') }}">
                                                            <i class="ti ti-pencil text-white"></i>
                                                        </a>
                                                    </div>
                                                @endcan
                                                @can('delete lead stage')
                                                    <div class="action-btn me-2">
                                                        {!! Form::open(['method' => 'DELETE', 'route' => ['sales-meeting-payment-methods.destroy', $paymentMethod->id]]) !!}
                                                        <a href="#"
                                                            class="mx-3 btn btn-sm align-items-center bs-pass-para bg-danger"
                                                            data-bs-toggle="tooltip" title="{{ __('Delete') }}"><i
                                                                class="ti ti-trash text-white"></i></a>
                                                        {!! Form::close() !!}
                                                    </div>
                                                @endcan
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
