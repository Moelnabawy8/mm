@extends('layouts.admin')
@section('page-title')
    {{ __('Manage Deal Status') }}
@endsection
@push('script-page')
    <script src="{{ asset('js/jquery-ui.min.js') }}"></script>
    <script>
        $(function() {
            $(".sortable").sortable();
            $(".sortable").disableSelection();
            $(".sortable").sortable({
                stop: function() {
                    var order = [];
                    $(this).find('li').each(function(index, data) {
                        order[index] = $(data).attr('data-id');
                    });

                    $.ajax({
                        url: "{{ route('deal-statuses.order') }}",
                        data: {
                            order: order,
                            _token: $('meta[name="csrf-token"]').attr('content')
                        },
                        type: 'POST',
                        success: function(data) {},
                        error: function(data) {
                            data = data.responseJSON;
                            show_toastr('Error', data.error, 'error')
                        }
                    })
                }
            });
        });
    </script>
@endpush
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('Deal Status') }}</li>
@endsection
@section('action-btn')
    <div class="float-end">
        <a href="#" data-size="md" data-url="{{ route('deal-statuses.create') }}" data-ajax-popup="true"
            data-bs-toggle="tooltip" title="{{ __('Create Deal Status') }}" class="btn btn-sm btn-primary">
            <i class="ti ti-plus"></i>
        </a>
    </div>
@endsection
@section('content')
    <div class="row">
        @include('layouts.crm_setup')
        <div class="col-lg-9">
            <div class="row justify-content-center">
                <div class="card">
                    <div class="card-body">
                        <ul class="list-unstyled list-group sortable stage">
                            @foreach ($stages as $stage)
                                <li class="d-flex align-items-center justify-content-between list-group-item"
                                    data-id="{{ $stage->id }}">
                                    <h6 class="mb-0">
                                        <i class="me-3 ti ti-arrows-maximize " data-feather="move"></i>
                                        <span>{{ $stage->name }}</span>
                                    </h6>
                                    <span class="float-end">

                                        @can('edit stage')
                                            <div class="action-btn me-2">
                                                <a href="#" class="mx-3 btn btn-sm align-items-center bg-info"
                                                    data-url="{{ URL::to('deal-statuses/' . $stage->id . '/edit') }}"
                                                    data-ajax-popup="true" data-size="md" data-bs-toggle="tooltip"
                                                    title="{{ __('Edit') }}" data-title="{{ __('Edit Deal Status') }}">
                                                    <i class="ti ti-pencil text-white"></i>
                                                </a>
                                            </div>
                                        @endcan
                                        @can('delete stage')
                                            <div class="action-btn ">
                                                {!! Form::open(['method' => 'DELETE', 'route' => ['deal-statuses.destroy', $stage->id]]) !!}
                                                <a href="#"
                                                    class="mx-3 btn btn-sm  align-items-center bs-pass-para bg-danger"
                                                    data-bs-toggle="tooltip" title="{{ __('Delete') }}"><i
                                                        class="ti ti-trash text-white"></i></a>
                                                {!! Form::close() !!}
                                            </div>
                                        @endcan
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                        <p class=" mt-4"><strong>{{ __('Note') }} :
                            </strong><b>{{ __('You can easily change order of deal status using drag & drop.') }}</b></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
