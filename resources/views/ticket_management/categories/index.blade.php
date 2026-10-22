@extends('layouts.admin')

@section('page-title')
    {{ __('Ticket Categories') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ticket-management.index') }}">{{ __('Public Tickets') }}</a></li>
    <li class="breadcrumb-item">{{ __('Categories') }}</li>
@endsection

@section('action-btn')
    @if (Auth::user()->type == 'company')
        <div class="row align-items-center">
            <div class="col-auto">
                <a href="{{ route('ticket-management.categories.create') }}" class="btn btn-sm btn-primary"
                    data-bs-toggle="tooltip" title="{{ __('Create Category') }}">
                    <i class="ti ti-plus"></i> {{ __('Create Category') }}
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
                    <h5>{{ __('Categories') }}</h5>
                </div>
                <div class="card-body table-border-style">
                    <div class="table-responsive">
                        <table class="table datatable">
                            <thead>
                                <tr>
                                    <th>{{ __('Order') }}</th>
                                    <th>{{ __('Name') }}</th>
                                    <th>{{ __('Name (Arabic)') }}</th>
                                    <th>{{ __('Color') }}</th>
                                    <th>{{ __('Tickets') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($categories as $category)
                                    <tr>
                                        <td>{{ $category->order }}</td>
                                        <td>
                                            @if ($category->icon)
                                                <i class="{{ $category->icon }} me-2"></i>
                                            @endif
                                            {{ $category->name }}
                                        </td>
                                        <td dir="rtl">{{ $category->name_ar ?: '-' }}</td>
                                        <td>
                                            <span class="badge" style="background-color: {{ $category->color }}">
                                                {{ $category->color }}
                                            </span>
                                        </td>
                                        <td>{{ $category->tickets_count }}</td>
                                        <td>
                                            @if ($category->is_active)
                                                <span class="badge bg-success">{{ __('Active') }}</span>
                                            @else
                                                <span class="badge bg-danger">{{ __('Inactive') }}</span>
                                            @endif
                                        </td>
                                        <td class="Action">
                                            <div class="action-btn bg-info me-2">
                                                <a href="{{ route('ticket-management.categories.edit', $category->id) }}"
                                                    class="mx-3 btn btn-sm align-items-center" data-bs-toggle="tooltip"
                                                    title="{{ __('Edit') }}">
                                                    <i class="ti ti-edit text-white"></i>
                                                </a>
                                            </div>
                                            @if (Auth::user()->type == 'company')
                                                <div class="action-btn bg-danger">
                                                    <form
                                                        action="{{ route('ticket-management.categories.delete', $category->id) }}"
                                                        method="POST" class="d-inline"
                                                        onsubmit="return confirm('{{ __('Are you sure you want to delete this category?') }}');">
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
                                        <td colspan="7" class="text-center py-4">
                                            <div class="text-muted">
                                                <i class="ti ti-category" style="font-size: 2rem;"></i>
                                                <p class="mt-2 mb-0">{{ __('No categories found') }}</p>
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
