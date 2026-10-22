@extends('layouts.admin')

@section('page-title')
    {{ __('Public Relationships') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('Public Relationships') }}</li>
@endsection

@push('script-page')
    <script>
        $(document).ready(function() {
            if ($('#filter_date_from').length > 0) $('#filter_date_from').flatpickr({});
            if ($('#filter_date_to').length > 0) $('#filter_date_to').flatpickr({});
        });

        $(document).on('click', '#pr-apply-filter', function() {
            var data = {
                type: $('#filter_type').val(),
                region: $('#filter_region').val(),
                status: $('#filter_status').val(),
                search: $('#filter_search').val(),
                date_from: $('#filter_date_from').val(),
                date_to: $('#filter_date_to').val(),
            };

            $.ajax({
                url: '{{ route('pr.index') }}',
                type: 'GET',
                data: data,
                success: function(response) {
                    $('#pr-entities-table-container').html(response);
                }
            });
            $('#prAdvancedFilterModal').modal('hide');
        });

        $(document).on('click', '#pr-clear-filter', function() {
            window.location.href = '{{ route('pr.index') }}';
        });

        $(document).on('click', '#pr-entities-table-container .pagination a', function(e) {
            e.preventDefault();
            var url = $(this).attr('href');
            if (!url) return;
            $.ajax({
                url: url,
                type: 'GET',
                success: function(response) {
                    $('#pr-entities-table-container').html(response);
                    $('html, body').animate({ scrollTop: $('#pr-entities-table-container').offset().top - 80 }, 200);
                }
            });
        });
    </script>
@endpush

@section('action-btn')
    <div class="float-end">
        @can('manage pr custom field')
            <a href="{{ route('pr.field.index') }}" class="btn btn-sm btn-secondary me-1" data-bs-toggle="tooltip"
                title="{{ __('Custom Fields') }}">
                <i class="ti ti-forms"></i>
            </a>
        @endcan
        <a href="#" class="btn btn-sm btn-success me-1" data-bs-toggle="modal" data-bs-target="#prAdvancedFilterModal"
            title="{{ __('Advanced Filter') }}">
            <i class="ti ti-filter"></i>
        </a>
        @can('create pr entity')
            <a href="#" data-size="lg" data-url="{{ route('pr.create') }}" data-ajax-popup="true"
                data-bs-toggle="tooltip" title="{{ __('Add Entity') }}" data-title="{{ __('Add Public Relationships Entity') }}"
                class="btn btn-sm btn-primary">
                <i class="ti ti-plus"></i>
            </a>
        @endcan
    </div>
@endsection

@section('content')
    <div class="row">
        <div class="col-xl-12">
            <div class="card">
                <div class="card-body table-border-style">
                    <div id="pr-entities-table-container">
                        @include('publicrelations::entities._table')
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="prAdvancedFilterModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('Advanced Filter') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label for="filter_type" class="form-label">{{ __('Type') }}</label>
                            <select class="form-control select2" id="filter_type" name="type">
                                <option value="">{{ __('All Types') }}</option>
                                @foreach (\Modules\PublicRelations\Enums\PrEntityType::options() as $value => $label)
                                    <option value="{{ $value }}" {{ ($filters['type'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="filter_region" class="form-label">{{ __('Region') }}</label>
                            <select class="form-control select2" id="filter_region" name="region">
                                <option value="">{{ __('All Regions') }}</option>
                                @foreach (\Modules\PublicRelations\Enums\PrRegion::options() as $value => $label)
                                    <option value="{{ $value }}" {{ ($filters['region'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="filter_status" class="form-label">{{ __('Status') }}</label>
                            <select class="form-control select2" id="filter_status" name="status">
                                <option value="">{{ __('All Statuses') }}</option>
                                @foreach (\Modules\PublicRelations\Enums\PrEntityStatus::options() as $value => $label)
                                    <option value="{{ $value }}" {{ ($filters['status'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-12 form-group">
                            <label for="filter_search" class="form-label">{{ __('Search') }}</label>
                            <input type="text" class="form-control" id="filter_search" name="search"
                                placeholder="{{ __('Name, contact person or email') }}" value="{{ $filters['search'] ?? '' }}">
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="filter_date_from" class="form-label">{{ __('Created From') }}</label>
                            <input type="text" class="form-control" id="filter_date_from" name="date_from"
                                placeholder="YYYY-MM-DD" autocomplete="off" value="{{ $filters['date_from'] ?? '' }}">
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="filter_date_to" class="form-label">{{ __('Created To') }}</label>
                            <input type="text" class="form-control" id="filter_date_to" name="date_to"
                                placeholder="YYYY-MM-DD" autocomplete="off" value="{{ $filters['date_to'] ?? '' }}">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="pr-clear-filter">{{ __('Clear') }}</button>
                    <button type="button" class="btn btn-primary" id="pr-apply-filter">{{ __('Apply') }}</button>
                </div>
            </div>
        </div>
    </div>
@endsection
