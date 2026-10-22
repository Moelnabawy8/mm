@extends('layouts.admin')

@section('page-title')
    {{ __('Public Ticket Management') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item">{{ __('Public Tickets') }}</li>
@endsection

@section('action-btn')
    <div class="row align-items-center">
        <div class="col-auto">
            <a href="{{ route('ticket-management.settings') }}" class="btn btn-sm btn-primary" data-bs-toggle="tooltip"
                title="{{ __('Portal Settings') }}">
                <i class="ti ti-settings"></i> {{ __('Settings') }}
            </a>
            <a href="{{ route('ticket-management.categories') }}" class="btn btn-sm btn-info" data-bs-toggle="tooltip"
                title="{{ __('Categories') }}">
                <i class="ti ti-category"></i> {{ __('Categories') }}
            </a>
            <a href="{{ route('ticket-management.custom-fields') }}" class="btn btn-sm btn-warning"
                data-bs-toggle="tooltip" title="{{ __('Custom Fields') }}">
                <i class="ti ti-forms"></i> {{ __('Custom Fields') }}
            </a>
        </div>
    </div>
@endsection

@section('content')
    <div class="row">
        <div class="col-lg-2 col-md-4 col-sm-6 col-12">
            <div class="card">
                <div class="card-body">
                    <div class="theme-avtar bg-primary">
                        <i class="ti ti-ticket"></i>
                    </div>
                    <p class="text-muted text-sm mt-4 mb-2">{{ __('Total Tickets') }}</p>
                    <h6 class="mb-0">{{ $countTotal }}</h6>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6 col-12">
            <div class="card">
                <div class="card-body">
                    <div class="theme-avtar bg-info">
                        <i class="ti ti-bell"></i>
                    </div>
                    <p class="text-muted text-sm mt-4 mb-2">{{ __('New') }}</p>
                    <h6 class="mb-0">{{ $countNew }}</h6>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6 col-12">
            <div class="card">
                <div class="card-body">
                    <div class="theme-avtar bg-warning">
                        <i class="ti ti-folder-open"></i>
                    </div>
                    <p class="text-muted text-sm mt-4 mb-2">{{ __('Open') }}</p>
                    <h6 class="mb-0">{{ $countOpen }}</h6>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6 col-12">
            <div class="card">
                <div class="card-body">
                    <div class="theme-avtar bg-secondary">
                        <i class="ti ti-loader"></i>
                    </div>
                    <p class="text-muted text-sm mt-4 mb-2">{{ __('In Progress') }}</p>
                    <h6 class="mb-0">{{ $countInProgress }}</h6>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6 col-12">
            <div class="card">
                <div class="card-body">
                    <div class="theme-avtar bg-success">
                        <i class="ti ti-check"></i>
                    </div>
                    <p class="text-muted text-sm mt-4 mb-2">{{ __('Resolved') }}</p>
                    <h6 class="mb-0">{{ $countResolved }}</h6>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6 col-12">
            <div class="card">
                <div class="card-body">
                    <div class="theme-avtar bg-danger">
                        <i class="ti ti-x"></i>
                    </div>
                    <p class="text-muted text-sm mt-4 mb-2">{{ __('Closed') }}</p>
                    <h6 class="mb-0">{{ $countClosed }}</h6>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h5 class="mb-0">{{ __('Filter Tickets') }}</h5>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('ticket-management.index') }}">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <input type="text" class="form-control" name="search" value="{{ request('search') }}"
                                    placeholder="{{ __('Search by code, subject, name, email...') }}">
                            </div>
                            <div class="col-md-2 mb-3">
                                <select class="form-select" name="status">
                                    <option value="">{{ __('All Status') }}</option>
                                    @foreach ($statuses as $status)
                                        <option value="{{ $status }}"
                                            {{ request('status') == $status ? 'selected' : '' }}>{{ __($status) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2 mb-3">
                                <select class="form-select" name="category_id">
                                    <option value="">{{ __('All Categories') }}</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}"
                                            {{ request('category_id') == $category->id ? 'selected' : '' }}>
                                            {{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2 mb-3">
                                <select class="form-select" name="priority">
                                    <option value="">{{ __('All Priorities') }}</option>
                                    @foreach ($priorities as $key => $priority)
                                        <option value="{{ $key }}"
                                            {{ request('priority') !== null && request('priority') == $key ? 'selected' : '' }}>
                                            {{ __($priority) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="ti ti-search"></i> {{ __('Filter') }}
                                    </button>
                                    <a href="{{ route('ticket-management.index') }}" class="btn btn-secondary">
                                        <i class="ti ti-refresh"></i> {{ __('Reset') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>{{ __('Public Tickets') }}</h5>
                </div>
                <div class="card-body table-border-style">
                    <div class="table-responsive">
                        <table class="table datatable">
                            <thead>
                                <tr>
                                    <th>{{ __('Ticket Code') }}</th>
                                    <th>{{ __('Submitter') }}</th>
                                    <th>{{ __('Subject') }}</th>
                                    <th>{{ __('Category') }}</th>
                                    <th>{{ __('Priority') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Created') }}</th>
                                    <th>{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($tickets as $ticket)
                                    <tr>
                                        <td>
                                            <a href="{{ route('ticket-management.show', $ticket->id) }}"
                                                class="text-primary fw-bold">
                                                {{ $ticket->ticket_code }}
                                            </a>
                                        </td>
                                        <td>
                                            <div>{{ $ticket->submitter_name }}</div>
                                            <small class="text-muted">{{ $ticket->submitter_email }}</small>
                                        </td>
                                        <td>
                                            <a href="{{ route('ticket-management.show', $ticket->id) }}">
                                                {{ Str::limit($ticket->subject, 40) }}
                                            </a>
                                        </td>
                                        <td>
                                            @if ($ticket->category)
                                                <span class="badge"
                                                    style="background-color: {{ $ticket->category->color }}">
                                                    {{ $ticket->category->name }}
                                                </span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            @php
                                                $priorityClasses = [
                                                    'bg-secondary',
                                                    'bg-info',
                                                    'bg-warning',
                                                    'bg-danger',
                                                ];
                                            @endphp
                                            <span
                                                class="badge {{ $priorityClasses[$ticket->priority] ?? 'bg-secondary' }}">
                                                {{ $priorities[$ticket->priority] ?? 'Unknown' }}
                                            </span>
                                        </td>
                                        <td>
                                            @php
                                                $statusClasses = [
                                                    'New' => 'bg-info',
                                                    'Open' => 'bg-warning',
                                                    'In Progress' => 'bg-primary',
                                                    'Resolved' => 'bg-success',
                                                    'Closed' => 'bg-danger',
                                                ];
                                            @endphp
                                            <span class="badge {{ $statusClasses[$ticket->status] ?? 'bg-secondary' }}">
                                                {{ __($ticket->status) }}
                                            </span>
                                        </td>
                                        <td>
                                            <small>{{ $ticket->created_at->format('Y-m-d H:i') }}</small>
                                        </td>
                                        <td class="Action">
                                            <div class="action-btn bg-warning me-2">
                                                <a href="{{ route('ticket-management.show', $ticket->id) }}"
                                                    class="mx-3 btn btn-sm align-items-center" data-bs-toggle="tooltip"
                                                    title="{{ __('View') }}">
                                                    <i class="ti ti-eye text-white"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <div class="text-muted">
                                                <i class="ti ti-inbox" style="font-size: 2rem;"></i>
                                                <p class="mt-2 mb-0">{{ __('No tickets found') }}</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        {{ $tickets->appends(request()->query())->links('pagination::bootstrap-5') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
