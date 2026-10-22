@extends('layouts.admin')

@section('page-title')
    {{ __('Manage Resignation') }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('Resignation') }}</li>
@endsection

@section('action-btn')
    <div class="float-end">
        @if (\Auth::user()->can('create resignation') || \Auth::user()->type != 'company')
            <a href="#" data-size="lg" data-url="{{ route('resignation.create') }}" data-ajax-popup="true"
                data-bs-toggle="tooltip" title="{{ __('Create') }}" data-title="{{ __('Create New Resignation') }}"
                class="btn btn-sm btn-primary">
                <i class="ti ti-plus"></i>
            </a>
        @endif
    </div>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body table-border-style">
                    <div class="table-responsive">
                        <table class="table datatable">
                            <thead>
                                <tr>
                                    @if (\Auth::user()->type == 'company' || \Auth::user()->type == 'HR')
                                        <th>{{ __('Employee Name') }}</th>
                                    @endif
                                    <th>{{ __('Resignation Date') }}</th>
                                    <th>{{ __('Last Working Date') }}</th>
                                    <th>{{ __('Reason') }}</th>
                                    @if (Gate::check('edit resignation') || Gate::check('delete resignation') || \Auth::user()->type != 'company')
                                        <th width="200px">{{ __('Action') }}</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="font-style">
                                @foreach ($resignations as $resignation)
                                    <tr>
                                        @if (\Auth::user()->type == 'company' || \Auth::user()->type == 'HR')
                                            <td>{{ !empty($resignation->employee) ? $resignation->employee->name : '' }}
                                            </td>
                                        @endif
                                        <td>{{ \Auth::user()->dateFormat($resignation->notice_date) }}</td>
                                        <td>{{ \Auth::user()->dateFormat($resignation->resignation_date) }}</td>
                                        <td>{{ $resignation->description }}</td>
                                        @if (Gate::check('edit resignation') || Gate::check('delete resignation') || \Auth::user()->type != 'company')
                                            <td>

                                                @if (Gate::check('edit resignation') || \Auth::user()->type != 'company')
                                                    <div class="action-btn me-2">
                                                        <a href="#" class="mx-3 btn btn-sm align-items-center bg-info"
                                                            data-size="lg"
                                                            data-url="{{ URL::to('resignation/' . $resignation->id . '/edit') }}"
                                                            data-ajax-popup="true"
                                                            data-title="{{ __('Edit Resignation') }}"
                                                            data-bs-toggle="tooltip" title="{{ __('Edit') }}"
                                                            data-original-title="{{ __('Edit') }}">
                                                            <i class="ti ti-pencil text-white"></i>
                                                        </a>
                                                    </div>
                                                @endif


                                                @if (Gate::check('delete resignation') || \Auth::user()->type != 'company')
                                                    <div class="action-btn ">
                                                        {!! Form::open([
                                                            'method' => 'DELETE',
                                                            'route' => ['resignation.destroy', $resignation->id],
                                                            'id' => 'delete-form-' . $resignation->id,
                                                        ]) !!}
                                                        <a href="#"
                                                            class="mx-3 btn btn-sm align-items-center bs-pass-para bg-danger"
                                                            data-bs-toggle="tooltip" title="{{ __('Delete') }}"
                                                            data-original-title="{{ __('Delete') }}"
                                                            data-confirm="{{ __('Are You Sure?') . '|' . __('This action can not be undone. Do you want to continue?') }}"
                                                            data-confirm-yes="document.getElementById('delete-form-{{ $resignation->id }}').submit();">
                                                            <i class="ti ti-trash text-white"></i>
                                                        </a>
                                                        {!! Form::close() !!}
                                                    </div>
                                                @endif
                                            </td>
                                        @endif
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
