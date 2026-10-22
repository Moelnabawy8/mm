@extends('layouts.admin')

@section('page-title')
    {{ __('Edit Profile') }} - {{ $user->name }}
@endsection

@section('action-button')
    <a href="{{ route('user.profile.show', $user->id) }}" class="btn btn-sm btn-secondary">
        <i class="ti ti-arrow-left"></i> {{ __('Back') }}
    </a>
@endsection

@section('content')
    <form method="POST" action="{{ route('user.profile.update', $user->id) }}">
        @csrf
        @method('PUT')
        
        <div class="row">
            <div class="col-lg-12">
                <!-- Basic Professional Info -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0">{{ __('Professional Information') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label>{{ __('Job Title') }}</label>
                                    <input type="text" name="job_title" class="form-control" value="{{ $profile->job_title }}" placeholder="{{ __('e.g., Senior Developer, HR Manager') }}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label>{{ __('Primary Role') }}</label>
                                    <input type="text" name="primary_role" class="form-control" value="{{ $profile->primary_role }}" placeholder="{{ __('e.g., Software Development, Human Resources') }}">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Official Responsibilities -->
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">{{ __('Official Responsibilities') }}</h5>
                        <button type="button" class="btn btn-sm btn-primary" onclick="addResponsibility()">
                            <i class="ti ti-plus"></i> {{ __('Add') }}
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="responsibilities-container">
                            @if($profile->official_responsibilities)
                                @foreach(json_decode($profile->official_responsibilities, true) as $index => $responsibility)
                                    <div class="input-group mb-2 responsibility-item">
                                        <input type="text" name="official_responsibilities[]" class="form-control" value="{{ $responsibility }}">
                                        <button type="button" class="btn btn-danger" onclick="removeItem(this)">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </div>
                                @endforeach
                            @else
                                <div class="input-group mb-2 responsibility-item">
                                    <input type="text" name="official_responsibilities[]" class="form-control" placeholder="{{ __('Enter responsibility') }}">
                                    <button type="button" class="btn btn-danger" onclick="removeItem(this)">
                                        <i class="ti ti-trash"></i>
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Daily Tasks -->
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">{{ __('Daily Tasks') }}</h5>
                        <button type="button" class="btn btn-sm btn-primary" onclick="addDailyTask()">
                            <i class="ti ti-plus"></i> {{ __('Add') }}
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="daily-tasks-container">
                            @if($profile->daily_tasks)
                                @foreach(json_decode($profile->daily_tasks, true) as $task)
                                    <div class="input-group mb-2 daily-task-item">
                                        <input type="text" name="daily_tasks[]" class="form-control" value="{{ $task }}">
                                        <button type="button" class="btn btn-danger" onclick="removeItem(this)">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </div>
                                @endforeach
                            @else
                                <div class="input-group mb-2 daily-task-item">
                                    <input type="text" name="daily_tasks[]" class="form-control" placeholder="{{ __('Enter daily task') }}">
                                    <button type="button" class="btn btn-danger" onclick="removeItem(this)">
                                        <i class="ti ti-trash"></i>
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Periodic Tasks -->
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">{{ __('Periodic Tasks') }}</h5>
                        <button type="button" class="btn btn-sm btn-primary" onclick="addPeriodicTask()">
                            <i class="ti ti-plus"></i> {{ __('Add') }}
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="periodic-tasks-container">
                            @if($profile->periodic_tasks)
                                @foreach(json_decode($profile->periodic_tasks, true) as $task)
                                    <div class="input-group mb-2 periodic-task-item">
                                        <input type="text" name="periodic_tasks[]" class="form-control" value="{{ $task }}">
                                        <button type="button" class="btn btn-danger" onclick="removeItem(this)">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </div>
                                @endforeach
                            @else
                                <div class="input-group mb-2 periodic-task-item">
                                    <input type="text" name="periodic_tasks[]" class="form-control" placeholder="{{ __('Enter periodic task (e.g., Weekly reports, Monthly reviews)') }}">
                                    <button type="button" class="btn btn-danger" onclick="removeItem(this)">
                                        <i class="ti ti-trash"></i>
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Work Details & Task Execution -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0">{{ __('Work Details & Execution Method') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="form-group mb-3">
                            <label>{{ __('Work Details') }}</label>
                            <textarea name="work_details" class="form-control" rows="4" placeholder="{{ __('Describe your daily work details...') }}">{{ $profile->work_details }}</textarea>
                        </div>
                        <div class="form-group">
                            <label>{{ __('Task Execution Method') }}</label>
                            <textarea name="task_execution_method" class="form-control" rows="4" placeholder="{{ __('Describe how you execute your tasks...') }}">{{ $profile->task_execution_method }}</textarea>
                        </div>
                    </div>
                </div>

                <!-- Department Connections -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0">{{ __('Department Connections') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="form-group mb-3">
                            <label>{{ __('Connected Departments') }}</label>
                            <div class="row">
                                @php
                                    $selectedDepts = $profile->department_connections ? json_decode($profile->department_connections, true) : [];
                                @endphp
                                @foreach($departments as $dept)
                                    <div class="col-md-4 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="department_connections[]" value="{{ $dept->id }}" id="dept{{ $dept->id }}" {{ in_array($dept->id, $selectedDepts) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="dept{{ $dept->id }}">
                                                {{ $dept->name }}
                                            </label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="form-group">
                            <label>{{ __('Collaboration Details') }}</label>
                            <textarea name="collaboration_details" class="form-control" rows="3" placeholder="{{ __('Describe your collaboration with other departments...') }}">{{ $profile->collaboration_details }}</textarea>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="card">
                    <div class="card-body text-end">
                        <a href="{{ route('user.profile.show', $user->id) }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-device-floppy"></i> {{ __('Save Profile') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('script-page')
<script>
    function addResponsibility() {
        const container = document.getElementById('responsibilities-container');
        const div = document.createElement('div');
        div.className = 'input-group mb-2 responsibility-item';
        div.innerHTML = `
            <input type="text" name="official_responsibilities[]" class="form-control" placeholder="{{ __('Enter responsibility') }}">
            <button type="button" class="btn btn-danger" onclick="removeItem(this)">
                <i class="ti ti-trash"></i>
            </button>
        `;
        container.appendChild(div);
    }

    function addDailyTask() {
        const container = document.getElementById('daily-tasks-container');
        const div = document.createElement('div');
        div.className = 'input-group mb-2 daily-task-item';
        div.innerHTML = `
            <input type="text" name="daily_tasks[]" class="form-control" placeholder="{{ __('Enter daily task') }}">
            <button type="button" class="btn btn-danger" onclick="removeItem(this)">
                <i class="ti ti-trash"></i>
            </button>
        `;
        container.appendChild(div);
    }

    function addPeriodicTask() {
        const container = document.getElementById('periodic-tasks-container');
        const div = document.createElement('div');
        div.className = 'input-group mb-2 periodic-task-item';
        div.innerHTML = `
            <input type="text" name="periodic_tasks[]" class="form-control" placeholder="{{ __('Enter periodic task') }}">
            <button type="button" class="btn btn-danger" onclick="removeItem(this)">
                <i class="ti ti-trash"></i>
            </button>
        `;
        container.appendChild(div);
    }

    function removeItem(button) {
        button.closest('.input-group').remove();
    }
</script>
@endpush
