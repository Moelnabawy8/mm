@extends('layouts.admin')

@section('page-title')
    {{ __('Custom Fields') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ticket-management.index') }}">{{ __('Public Tickets') }}</a></li>
    <li class="breadcrumb-item">{{ __('Custom Fields') }}</li>
@endsection

@section('action-btn')
    @if (Auth::user()->type == 'company')
        <div class="row align-items-center">
            <div class="col-auto">
                <a href="{{ route('ticket-management.custom-fields.create') }}" class="btn btn-sm btn-primary"
                    data-bs-toggle="tooltip" title="{{ __('Create Field') }}">
                    <i class="ti ti-plus"></i> {{ __('Create Field') }}
                </a>
            </div>
        </div>
    @endif
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="card">
                <div class="card-header">
                    <h5>{{ __('Custom Fields') }}</h5>
                    <small
                        class="text-muted">{{ __('These fields will appear in the public ticket submission form') }}</small>
                </div>
                <div class="card-body table-border-style">
                    <div class="table-responsive">
                        <table class="table datatable">
                            <thead>
                                <tr>
                                    <th>{{ __('Order') }}</th>
                                    <th>{{ __('Name') }}</th>
                                    <th>{{ __('Type') }}</th>
                                    <th>{{ __('Required') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($fields as $field)
                                    <tr>
                                        <td>{{ $field->order }}</td>
                                        <td>
                                            <strong>{{ $field->name }}</strong>
                                            @if ($field->name_ar)
                                                <br><small class="text-muted" dir="rtl">{{ $field->name_ar }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <span
                                                class="badge bg-secondary">{{ $fieldTypes[$field->type] ?? $field->type }}</span>
                                        </td>
                                        <td>
                                            @if ($field->is_required)
                                                <span class="badge bg-warning">{{ __('Required') }}</span>
                                            @else
                                                <span class="badge bg-light text-dark">{{ __('Optional') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($field->is_active)
                                                <span class="badge bg-success">{{ __('Active') }}</span>
                                            @else
                                                <span class="badge bg-danger">{{ __('Inactive') }}</span>
                                            @endif
                                        </td>
                                        <td class="Action">
                                            <div class="action-btn bg-info me-2">
                                                <a href="{{ route('ticket-management.custom-fields.edit', $field->id) }}"
                                                    class="mx-3 btn btn-sm align-items-center" data-bs-toggle="tooltip"
                                                    title="{{ __('Edit') }}">
                                                    <i class="ti ti-edit text-white"></i>
                                                </a>
                                            </div>
                                            @if (Auth::user()->type == 'company')
                                                <div class="action-btn bg-danger">
                                                    <form
                                                        action="{{ route('ticket-management.custom-fields.delete', $field->id) }}"
                                                        method="POST" class="d-inline"
                                                        onsubmit="return confirm('{{ __('Are you sure you want to delete this field?') }}');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="mx-3 btn btn-sm align-items-center"
                                                            data-bs-toggle="tooltip" title="{{ __('Delete') }}">
                                                            <i class="ti ti-trash text-white"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-4">
                                            <div class="text-muted">
                                                <i class="ti ti-forms" style="font-size: 2rem;"></i>
                                                <p class="mt-2 mb-0">{{ __('No custom fields found') }}</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
