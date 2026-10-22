@extends('layouts.admin')

@php
    $setting = \App\Models\Utility::settings();
    $titles = ['data' => __('Data Reports'), 'financial' => __('Financial Reports')];
    $blurbs = [
        'data' => __('Leads, deals, sales meetings and everything about customers.'),
        'financial' => __('Purchases, collections, installments and revenue.'),
    ];
@endphp

@section('page-title')
    {{ $titles[$category] }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('CRM Reports') }}</li>
    <li class="breadcrumb-item">{{ $titles[$category] }}</li>
@endsection

@include('crm_reports._styles')

@section('content')
<div class="crm-reports {{ ($setting['cust_darklayout'] ?? '') === 'on' ? 'crm-dark' : '' }}">
    @include('crm_reports._nav', ['current' => $category])
    <div class="card">
        <div class="card-body crm-empty">
            <i class="ti ti-tools"></i>
            <h5>{{ $titles[$category] }}</h5>
            <p class="mb-0">{{ $blurbs[$category] }}</p>
            <p class="small">{{ __('This section is being prepared.') }}</p>
        </div>
    </div>
</div>
@endsection
