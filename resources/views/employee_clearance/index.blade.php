@extends('layouts.admin')

@section('page-title')
    {{ __('Manage Employee Clearance') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item">{{ __('Employee Clearance') }}</li>
@endsection


@section('content')
    <div class="row">
        <div class="col-12">
            <a href="#" data-url="{{ route('employee-clearance.create') }}" data-ajax-popup="true"
                data-title="{{ __('Create New Clearance') }}" data-bs-toggle="tooltip" title="{{ __('Create') }}"
                class="btn btn-sm btn-primary"
                style="margin-bottom: 10px; margin-left: auto; display: block; width: fit-content;">
                <span>{{ __('Create') }}</span>
                <i class="ti ti-plus"></i>
            </a>
            <div class="card">
                <div class="card-body table-border-style">
                    <div class="table-responsive">
                        <table class="table datatable">
                            <thead>
                                <tr>
                                    <th>{{ __('Employee Name') }}</th>
                                    <th>{{ __('Is Cleared') }}</th>
                                    <th>{{ __('Description') }}</th>
                                    <th>{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($clearances as $clearance)
                                    <tr>
                                        <td>{{ !empty($clearance->employee) ? $clearance->employee->name : '' }}</td>
                                        <td>
                                            @if ($clearance->is_cleared)
                                                <span class="badge bg-success p-2 px-3 rounded">{{ __('Yes') }}</span>
                                            @else
                                                <span class="badge bg-danger p-2 px-3 rounded">{{ __('No') }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $clearance->description }}</td>
                                        <td class="Action">
                                            <span>
                                                @can('edit termination')
                                                    <div class="action-btn bg-info ms-2">
                                                        <a href="#" class="mx-3 btn btn-sm  align-items-center"
                                                            data-url="{{ route('employee-clearance.edit', $clearance->id) }}"
                                                            data-ajax-popup="true" data-size="md" data-bs-toggle="tooltip"
                                                            title="" data-title="{{ __('Edit Clearance') }}"
                                                            data-original-title="{{ __('Edit') }}">
                                                            <i class="ti ti-pencil text-white"></i>
                                                        </a>
                                                    </div>
                                                @endcan

                                                @can('delete termination')
                                                    <div class="action-btn bg-danger ms-2">
                                                        {!! Form::open([
                                                            'method' => 'DELETE',
                                                            'route' => ['employee-clearance.destroy', $clearance->id],
                                                            'id' => 'delete-form-' . $clearance->id,
                                                        ]) !!}
                                                        <a href="#"
                                                            class="mx-3 btn btn-sm  align-items-center bs-pass-para"
                                                            data-bs-toggle="tooltip" title="" data-original-title="Delete"
                                                            aria-label="Delete"><i
                                                                class="ti ti-trash text-white text-white"></i></a>
                                                        </form>
                                                    </div>
                                                @endcan
                                                @if ($clearance->is_cleared)
                                                    <div class="action-btn bg-warning ms-2">
                                                        <a href="#"
                                                            data-url="{{ route('termination.create', ['employee_id' => $clearance->employee_id]) }}"
                                                            class="mx-3 btn btn-sm align-items-center"
                                                            data-ajax-popup="true"
                                                            data-title="{{ __('Create Termination') }}"
                                                            data-bs-toggle="tooltip" title="{{ __('Terminate') }}">
                                                            <i class="ti ti-user-x text-white"></i>
                                                        </a>
                                                    </div>
                                                @endif
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
