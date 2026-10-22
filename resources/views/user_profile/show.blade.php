@extends('layouts.admin')

@section('page-title')
    {{ __('User Profile') }} - {{ $user->name }}
@endsection

@section('action-button')
    @if(Auth::user()->type == 'super admin' || Auth::id() == $user->id)
        <a href="{{ route('user.profile.edit', $user->id) }}" class="btn btn-sm btn-primary">
            <i class="ti ti-pencil"></i> {{ __('Edit Profile') }}
        </a>
    @endif
@endsection

@section('content')
    <div class="row">
        <!-- User Basic Info -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <div class="text-center">
                        <img src="{{ $user->profile }}" class="avatar avatar-xl rounded-circle mb-3" alt="{{ $user->name }}">
                        <h4 class="mb-0">{{ $user->name }}</h4>
                        <p class="text-muted">{{ $user->email }}</p>
                        @if($profile->job_title)
                            <span class="badge bg-primary">{{ $profile->job_title }}</span>
                        @endif
                    </div>
                    <hr>
                    <div class="mt-3">
                        <h6 class="mb-3">{{ __('Basic Information') }}</h6>
                        <div class="row mb-2">
                            <div class="col-6 text-muted">{{ __('Type') }}:</div>
                            <div class="col-6"><strong>{{ __(ucfirst($user->type)) }}</strong></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-6 text-muted">{{ __('Primary Role') }}:</div>
                            <div class="col-6"><strong>{{ $profile->primary_role ?? '-' }}</strong></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-6 text-muted">{{ __('Member Since') }}:</div>
                            <div class="col-6"><strong>{{ $user->created_at->format('M d, Y') }}</strong></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profile Details -->
        <div class="col-lg-8">
            <!-- Official Responsibilities -->
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="ti ti-clipboard-check me-2"></i>{{ __('Official Responsibilities') }}</h5>
                </div>
                <div class="card-body">
                    @if($profile->official_responsibilities)
                        <ul class="list-unstyled mb-0">
                            @foreach(json_decode($profile->official_responsibilities, true) as $responsibility)
                                <li class="mb-2"><i class="ti ti-circle-check text-success me-2"></i>{{ $responsibility }}</li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-muted mb-0">{{ __('No responsibilities defined yet.') }}</p>
                    @endif
                </div>
            </div>

            <!-- Daily Tasks -->
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="ti ti-calendar-event me-2"></i>{{ __('Daily Tasks') }}</h5>
                </div>
                <div class="card-body">
                    @if($profile->daily_tasks)
                        <ul class="list-unstyled mb-0">
                            @foreach(json_decode($profile->daily_tasks, true) as $task)
                                <li class="mb-2"><i class="ti ti-point text-primary me-2"></i>{{ $task }}</li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-muted mb-0">{{ __('No daily tasks defined yet.') }}</p>
                    @endif
                </div>
            </div>

            <!-- Periodic Tasks -->
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="ti ti-repeat me-2"></i>{{ __('Periodic Tasks') }}</h5>
                </div>
                <div class="card-body">
                    @if($profile->periodic_tasks)
                        <ul class="list-unstyled mb-0">
                            @foreach(json_decode($profile->periodic_tasks, true) as $task)
                                <li class="mb-2"><i class="ti ti-clock text-warning me-2"></i>{{ $task }}</li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-muted mb-0">{{ __('No periodic tasks defined yet.') }}</p>
                    @endif
                </div>
            </div>

            <!-- Work Details -->
            @if($profile->work_details)
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="ti ti-file-text me-2"></i>{{ __('Work Details') }}</h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-0">{{ $profile->work_details }}</p>
                    </div>
                </div>
            @endif

            <!-- Task Execution Method -->
            @if($profile->task_execution_method)
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="ti ti-settings me-2"></i>{{ __('Task Execution Method') }}</h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-0">{{ $profile->task_execution_method }}</p>
                    </div>
                </div>
            @endif

            <!-- Department Connections -->
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="ti ti-sitemap me-2"></i>{{ __('Department Connections') }}</h5>
                </div>
                <div class="card-body">
                    @if($profile->department_connections)
                        @php
                            $connections = json_decode($profile->department_connections, true);
                        @endphp
                        @foreach($departments as $dept)
                            @if(in_array($dept->id, $connections))
                                <span class="badge bg-info mb-2 me-1">{{ $dept->name }}</span>
                            @endif
                        @endforeach
                    @else
                        <p class="text-muted mb-0">{{ __('No department connections defined yet.') }}</p>
                    @endif

                    @if($profile->collaboration_details)
                        <hr>
                        <h6>{{ __('Collaboration Details') }}</h6>
                        <p class="mb-0">{{ $profile->collaboration_details }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- User Requests Section -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="ti ti-message-circle me-2"></i>{{ __('Requests & Requirements') }}</h5>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createRequestModal">
                        <i class="ti ti-plus"></i> {{ __('New Request') }}
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>{{ __('Title') }}</th>
                                    <th>{{ __('Type') }}</th>
                                    <th>{{ __('Priority') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Date') }}</th>
                                    <th>{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($requests as $request)
                                    <tr>
                                        <td>{{ $request->title }}</td>
                                        <td><span class="badge bg-secondary">{{ __(ucfirst($request->type)) }}</span></td>
                                        <td><span class="badge bg-{{ \App\Models\UserRequest::$priority_colors[$request->priority] }}">{{ __(ucfirst($request->priority)) }}</span></td>
                                        <td><span class="badge bg-{{ \App\Models\UserRequest::$status_colors[$request->status] }}">{{ __(ucfirst($request->status)) }}</span></td>
                                        <td>{{ $request->created_at->format('M d, Y') }}</td>
                                        <td>
                                            <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#viewRequestModal{{ $request->id }}">
                                                <i class="ti ti-eye"></i>
                                            </button>
                                            @if(Auth::user()->type == 'super admin' && $request->status == 'pending')
                                                <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#updateRequestModal{{ $request->id }}">
                                                    <i class="ti ti-pencil"></i>
                                                </button>
                                            @endif
                                        </td>
                                    </tr>

                                    <!-- View Request Modal -->
                                    <div class="modal fade" id="viewRequestModal{{ $request->id }}" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">{{ $request->title }}</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p><strong>{{ __('Description') }}:</strong></p>
                                                    <p>{{ $request->description ?? '-' }}</p>
                                                    @if($request->admin_notes)
                                                        <hr>
                                                        <p><strong>{{ __('Admin Notes') }}:</strong></p>
                                                        <p>{{ $request->admin_notes }}</p>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Update Request Modal (Super Admin Only) -->
                                    @if(Auth::user()->type == 'super admin' && $request->status == 'pending')
                                        <div class="modal fade" id="updateRequestModal{{ $request->id }}" tabindex="-1">
                                            <div class="modal-dialog">
                                                <form method="POST" action="{{ route('user.request.update', $request->id) }}">
                                                    @csrf
                                                    @method('PUT')
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">{{ __('Update Request') }}</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="form-group mb-3">
                                                                <label>{{ __('Status') }}</label>
                                                                <select name="status" class="form-control" required>
                                                                    <option value="approved">{{ __('Approved') }}</option>
                                                                    <option value="rejected">{{ __('Rejected') }}</option>
                                                                    <option value="fulfilled">{{ __('Fulfilled') }}</option>
                                                                </select>
                                                            </div>
                                                            <div class="form-group">
                                                                <label>{{ __('Admin Notes') }}</label>
                                                                <textarea name="admin_notes" class="form-control" rows="3">{{ $request->admin_notes }}</textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
                                                            <button type="submit" class="btn btn-primary">{{ __('Update') }}</button>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    @endif
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">{{ __('No requests found.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Custom Subscriptions Section -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="ti ti-credit-card me-2"></i>{{ __('My Subscriptions') }}</h5>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createSubscriptionModal">
                        <i class="ti ti-plus"></i> {{ __('Add Subscription') }}
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>{{ __('Subscription Name') }}</th>
                                    <th>{{ __('Plan Type') }}</th>
                                    <th>{{ __('Price') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Duration') }}</th>
                                    <th>{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($customSubscriptions as $sub)
                                    <tr>
                                        <td>{{ $sub->subscription_name }}</td>
                                        <td>{{ $sub->plan_type ?? '-' }}</td>
                                        <td>{{ \App\Models\Utility::getValByName('site_currency') . number_format($sub->price, 2) }}</td>
                                        <td><span class="badge bg-{{ \App\Models\UserSubscription::$status_colors[$sub->status] }}">{{ __(ucfirst($sub->status)) }}</span></td>
                                        <td>{{ $sub->start_date ? $sub->start_date->format('M d, Y') : '-' }} - {{ $sub->end_date ? $sub->end_date->format('M d, Y') : '-' }}</td>
                                        <td>
                                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editSubscriptionModal{{ $sub->id }}">
                                                <i class="ti ti-pencil"></i>
                                            </button>
                                            <form method="POST" action="{{ route('user.subscription.delete', $sub->id) }}" class="d-inline" onsubmit="return confirm('{{ __('Are you sure?') }}')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger">
                                                    <i class="ti ti-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>

                                    <!-- Edit Subscription Modal -->
                                    <div class="modal fade" id="editSubscriptionModal{{ $sub->id }}" tabindex="-1">
                                        <div class="modal-dialog">
                                            <form method="POST" action="{{ route('user.subscription.update', $sub->id) }}">
                                                @csrf
                                                @method('PUT')
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">{{ __('Edit Subscription') }}</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="form-group mb-3">
                                                            <label>{{ __('Subscription Name') }} <span class="text-danger">*</span></label>
                                                            <input type="text" name="subscription_name" class="form-control" value="{{ $sub->subscription_name }}" required>
                                                        </div>
                                                        <div class="form-group mb-3">
                                                            <label>{{ __('Plan Type') }}</label>
                                                            <input type="text" name="plan_type" class="form-control" value="{{ $sub->plan_type }}">
                                                        </div>
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <div class="form-group mb-3">
                                                                    <label>{{ __('Price') }}</label>
                                                                    <input type="number" step="0.01" name="price" class="form-control" value="{{ $sub->price }}">
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="form-group mb-3">
                                                                    <label>{{ __('Status') }} <span class="text-danger">*</span></label>
                                                                    <select name="status" class="form-control" required>
                                                                        @foreach(\App\Models\UserSubscription::$statuses as $key => $label)
                                                                            <option value="{{ $key }}" {{ $sub->status == $key ? 'selected' : '' }}>{{ __($label) }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <div class="form-group mb-3">
                                                                    <label>{{ __('Start Date') }}</label>
                                                                    <input type="date" name="start_date" class="form-control" value="{{ $sub->start_date ? $sub->start_date->format('Y-m-d') : '' }}">
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="form-group mb-3">
                                                                    <label>{{ __('End Date') }}</label>
                                                                    <input type="date" name="end_date" class="form-control" value="{{ $sub->end_date ? $sub->end_date->format('Y-m-d') : '' }}">
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="form-group">
                                                            <label>{{ __('Notes') }}</label>
                                                            <textarea name="notes" class="form-control" rows="2">{{ $sub->notes }}</textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
                                                        <button type="submit" class="btn btn-primary">{{ __('Update') }}</button>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">{{ __('No subscriptions found.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Subscription Modal -->
    <div class="modal fade" id="createSubscriptionModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('user.subscription.store', $user->id) }}">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('Add New Subscription') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group mb-3">
                            <label>{{ __('Subscription Name') }} <span class="text-danger">*</span></label>
                            <input type="text" name="subscription_name" class="form-control" required placeholder="{{ __('e.g., Premium Plan, Enterprise License') }}">
                        </div>
                        <div class="form-group mb-3">
                            <label>{{ __('Plan Type') }}</label>
                            <input type="text" name="plan_type" class="form-control" placeholder="{{ __('e.g., Monthly, Yearly, Lifetime') }}">
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label>{{ __('Price') }}</label>
                                    <input type="number" step="0.01" name="price" class="form-control" value="0">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label>{{ __('Status') }} <span class="text-danger">*</span></label>
                                    <select name="status" class="form-control" required>
                                        @foreach(\App\Models\UserSubscription::$statuses as $key => $label)
                                            <option value="{{ $key }}" {{ $key == 'active' ? 'selected' : '' }}>{{ __($label) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label>{{ __('Start Date') }}</label>
                                    <input type="date" name="start_date" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label>{{ __('End Date') }}</label>
                                    <input type="date" name="end_date" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>{{ __('Notes') }}</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="{{ __('Additional notes about this subscription...') }}"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('Add Subscription') }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Create Request Modal -->
    <div class="modal fade" id="createRequestModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('user.request.store', $user->id) }}">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('Create New Request') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group mb-3">
                            <label>{{ __('Title') }} <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        <div class="form-group mb-3">
                            <label>{{ __('Description') }}</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="form-group mb-3">
                            <label>{{ __('Type') }} <span class="text-danger">*</span></label>
                            <select name="type" class="form-control" required>
                                <option value="requirement">{{ __('Requirement') }}</option>
                                <option value="request">{{ __('Request') }}</option>
                                <option value="tool">{{ __('Tool') }}</option>
                                <option value="resource">{{ __('Resource') }}</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>{{ __('Priority') }} <span class="text-danger">*</span></label>
                            <select name="priority" class="form-control" required>
                                <option value="low">{{ __('Low') }}</option>
                                <option value="medium" selected>{{ __('Medium') }}</option>
                                <option value="high">{{ __('High') }}</option>
                                <option value="urgent">{{ __('Urgent') }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('Create Request') }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
