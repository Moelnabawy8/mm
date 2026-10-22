@extends('layouts.admin')

@section('page-title')
    {{ __('Tasks') }}
@endsection


@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('projects.index') }}">{{ __('Project') }}</a></li>
    <li class="breadcrumb-item">{{ __('Task') }}</li>
@endsection

@section('action-btn')
    <div class="float-end">

        <a href="#" class="btn bg-primary text-white btn-sm me-1" role="button" data-bs-toggle="dropdown"
            aria-haspopup="true" aria-expanded="false" data-bs-toggle="tooltip" title="{{ __('Filter') }}"
            data-original-title="{{ __('Filter') }}">
            <span class="btn-inner-icon"><i class="ti ti-filter"></i></span>
        </a>
        <div class="dropdown-menu dropdown-menu-right dropdown-steady" id="task_sort">
            <a class="dropdown-item active" href="#" data-val="created_at-desc">
                <i class="ti ti-sort-amount-down"></i>{{ __('Newest') }}
            </a>
            <a class="dropdown-item" href="#" data-val="created_at-asc">
                <i class="ti ti-sort-amount-up"></i>{{ __('Oldest') }}
            </a>
            <a class="dropdown-item" href="#" data-val="name-asc">
                <i class="ti ti-sort-alpha-down"></i>{{ __('From A-Z') }}
            </a>
            <a class="dropdown-item" href="#" data-val="name-desc">
                <i class="ti ti-sort-alpha-up"></i>{{ __('From Z-A') }}
            </a>
        </div>

        {{-- Assigned User Filter --}}
        <a href="#" class="btn btn-info btn-sm me-1" role="button" data-bs-toggle="dropdown" aria-haspopup="true"
            aria-expanded="false" data-bs-toggle="tooltip" title="{{ __('Filter by Assigned User') }}">
            <span class="btn-inner--icon"><i class="ti ti-user"></i> {{ __('Assigned To') }}</span>
        </a>
        <div class="dropdown-menu dropdown-menu-right dropdown-steady" id="user_filter"
            style="max-height: 400px; overflow-y: auto; min-width: 280px;">
            <a class="dropdown-item filter-by-assignee" href="#" data-assign-to="">
                <i class="ti ti-users"></i> {{ __('All Users') }}
            </a>
            <hr class="my-0">
            @isset($users)
                @foreach ($users as $user)
                    <a class="dropdown-item filter-by-assignee d-flex justify-content-between align-items-center" href="#"
                        data-assign-to="{{ $user->id }}">
                        <span><i class="ti ti-user"></i> {{ $user->name }}</span>
                        <small class="text-muted ms-2">
                            @if ($user->last_login_at)
                                <i class="ti ti-clock"></i> {{ \Carbon\Carbon::parse($user->last_login_at)->diffForHumans() }}
                            @else
                                <span class="text-warning">{{ __('Never') }}</span>
                            @endif
                        </small>
                    </a>
                @endforeach
            @endisset
        </div>

        <a href="#" class="btn btn-primary-subtle text-white btn-sm me-1" role="button" data-bs-toggle="dropdown"
            aria-haspopup="true" aria-expanded="false">
            <span class="btn-inner--icon">{{ __('Status') }}</span>
        </a>
        <div class="dropdown-menu dropdown-menu-right task-filter-actions dropdown-steady" id="task_status">
            <a class="dropdown-item filter-action filter-show-all pl-4" href="#">{{ __('Show All') }}</a>
            <hr class="my-0">
            <a class="dropdown-item filter-action pl-4 active" href="#"
                data-val="see_my_tasks">{{ __('See My Tasks') }}</a>
            <hr class="my-0">
            @foreach (\App\Models\ProjectTask::$priority as $key => $val)
                <a class="dropdown-item filter-action pl-4" href="#"
                    data-val="{{ $key }}">{{ __($val) }}</a>
            @endforeach
            <hr class="my-0">
            <a class="dropdown-item filter-action filter-other pl-4" href="#"
                data-val="due_today">{{ __('Due Today') }}</a>
            <a class="dropdown-item filter-action filter-other pl-4" href="#"
                data-val="over_due">{{ __('Over Due') }}</a>
            <a class="dropdown-item filter-action filter-other pl-4" href="#"
                data-val="starred">{{ __('Starred') }}</a>
        </div>

        @if ($view == 'grid')
            <a href="{{ route('taskBoard.view', 'list') }}" class="btn btn-primary btn-sm" data-bs-toggle="tooltip"
                title="{{ __('List View') }}">
                <span class="btn-inner--text"><i class="ti ti-list"></i>{{ __('List View') }}</span>
            </a>
        @else
            <a href="{{ route('taskBoard.view', 'grid') }}" class="btn btn-primary btn-sm" data-bs-toggle="tooltip"
                title="{{ __('Grid View') }}">
                <span class="btn-inner--text"><i class="ti ti-table"></i></span>
            </a>
        @endif

    </div>
@endsection

@section('content')
    {{-- Top Users with Most Tasks --}}
    @isset($topUsers)
        @if (count($topUsers) > 0)
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="ti ti-chart-bar me-2"></i>{{ __('Top Users by Tasks') }}</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                @foreach ($topUsers as $index => $stat)
                                    @if ($stat['total_tasks'] > 0)
                                        <div class="col-xl col-lg-4 col-md-6 col-12 mb-3">
                                            <div class="card shadow-sm h-100 {{ $index == 0 ? 'border-primary' : '' }}">
                                                <div class="card-body text-center p-3">
                                                    @if ($index == 0)
                                                        <span class="badge bg-warning text-dark position-absolute"
                                                            style="top: 5px; right: 5px;">
                                                            <i class="ti ti-crown"></i> #1
                                                        </span>
                                                    @endif
                                                    <div class="avatar avatar-lg bg-primary text-white rounded-circle mx-auto mb-2"
                                                        style="width: 50px; height: 50px; line-height: 50px;">
                                                        {{ strtoupper(substr($stat['user']->name, 0, 1)) }}
                                                    </div>
                                                    <h6 class="mb-1">{{ $stat['user']->name }}</h6>
                                                    <div class="d-flex justify-content-center gap-3 mb-2">
                                                        <span class="badge bg-info">{{ $stat['total_tasks'] }}
                                                            {{ __('Tasks') }}</span>
                                                        <span class="badge bg-success">{{ $stat['completed_tasks'] }}
                                                            {{ __('Done') }}</span>
                                                    </div>
                                                    <div class="progress" style="height: 8px;">
                                                        <div class="progress-bar bg-success" role="progressbar"
                                                            style="width: {{ $stat['completion_rate'] }}%"
                                                            aria-valuenow="{{ $stat['completion_rate'] }}" aria-valuemin="0"
                                                            aria-valuemax="100">
                                                        </div>
                                                    </div>
                                                    <small class="text-muted">{{ $stat['completion_rate'] }}%
                                                        {{ __('Completed') }}</small>
                                                    @if ($stat['user']->last_login_at)
                                                        <div class="mt-1">
                                                            <small class="text-muted">
                                                                <i class="ti ti-clock"></i>
                                                                {{ \Carbon\Carbon::parse($stat['user']->last_login_at)->diffForHumans() }}
                                                            </small>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endisset

    <div class="row min-750" id="taskboard_view"></div>
@endsection

@push('script-page')
    <script>
        // ready
        $(function() {
            var sort = 'created_at-desc';
            var status = '';
            var assign_to = '';
            ajaxFilterTaskView('created_at-desc', '', ['see_my_tasks']);

            // when change status
            $(".task-filter-actions").on('click', '.filter-action', function(e) {
                if ($(this).hasClass('filter-show-all')) {
                    $('.filter-action').removeClass('active');
                    $(this).addClass('active');
                } else {

                    $('.filter-show-all').removeClass('active');
                    if ($(this).hasClass('filter-other')) {
                        $('.filter-other').removeClass('active');
                    }
                    if ($(this).hasClass('active')) {
                        $(this).removeClass('active');
                        $(this).blur();
                    } else {
                        $(this).addClass('active');
                    }
                }

                var filterArray = [];
                var url = $(this).parents('.task-filter-actions').attr('data-url');
                $('div.task-filter-actions').find('.active').each(function() {
                    filterArray.push($(this).attr('data-val'));
                });
                status = filterArray;
                status = filterArray;
                ajaxFilterTaskView(sort, $('#task_keyword').val(), status, assign_to);
            });

            // when change sorting order
            $('#task_sort').on('click', 'a', function() {
                sort = $(this).attr('data-val');
                ajaxFilterTaskView(sort, $('#task_keyword').val(), status, assign_to);
                $('#task_sort a').removeClass('active');
                $(this).addClass('active');
            });

            // when searching by task name
            $(document).on('keyup', '#task_keyword', function() {
                ajaxFilterTaskView(sort, $(this).val(), status, assign_to);
            });

            // when click on assignee
            $(document).on('click', '.filter-by-assignee', function(e) {
                e.preventDefault();
                // Remove active class from all and add to clicked
                $('.filter-by-assignee').removeClass('active');
                $(this).addClass('active');

                assign_to = $(this).attr('data-assign-to');
                ajaxFilterTaskView(sort, $('#task_keyword').val(), status, assign_to);
            });
        });

        // For Filter
        function ajaxFilterTaskView(task_sort, keyword = '', status = '', assign_to = '') {
            var mainEle = $('#taskboard_view');
            var view = '{{ $view }}';
            var data = {
                view: view,
                sort: task_sort,
                keyword: keyword,
                status: status,
                assign_to: assign_to,
            }

            $.ajax({
                url: '{{ route('project.taskboard.view') }}',
                data: data,
                success: function(data) {
                    mainEle.html(data.html);
                }
            });
        }
    </script>
@endpush
