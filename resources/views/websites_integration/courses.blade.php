@extends('layouts.admin')

@section('page-title')
    {{ __('Websites — Courses') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('websites.dashboard') }}">{{ __('Websites') }}</a></li>
    <li class="breadcrumb-item">{{ __('Courses') }}</li>
@endsection

@section('content')
    @php
        $totalStudents = collect($courses)->sum('students_count');
        $totalLessons  = collect($courses)->sum('lessons_count');
        $totalQuizzes  = collect($courses)->sum('quizzes_count');
    @endphp

    {{-- Summary KPIs --}}
    <div class="row">
        <div class="col-12">
            <div class="alert alert-info"><i class="ti ti-school me-1"></i>
                {{ __('LearnPress catalog from') }} <strong>my-communication.com</strong> ·
                <strong>{{ count($courses) }}</strong> {{ __('courses') }}
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card"><div class="card-body">
                <p class="text-muted mb-1">{{ __('Total Courses') }}</p>
                <h3 class="mb-0 text-primary">{{ number_format($total) }}</h3>
            </div></div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card"><div class="card-body">
                <p class="text-muted mb-1">{{ __('Total Enrollments') }}</p>
                <h3 class="mb-0 text-success">{{ number_format($totalStudents) }}</h3>
            </div></div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card"><div class="card-body">
                <p class="text-muted mb-1">{{ __('Total Lessons') }}</p>
                <h3 class="mb-0 text-info">{{ number_format($totalLessons) }}</h3>
            </div></div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card"><div class="card-body">
                <p class="text-muted mb-1">{{ __('Total Quizzes') }}</p>
                <h3 class="mb-0 text-warning">{{ number_format($totalQuizzes) }}</h3>
            </div></div>
        </div>
    </div>

    {{-- Course cards grid --}}
    <div class="row">
        @forelse ($courses as $c)
            <div class="col-xl-4 col-md-6">
                <div class="card h-100 shadow-sm">
                    @if (!empty($c['image']))
                        <img src="{{ $c['image'] }}" alt="" class="card-img-top" style="height: 180px; object-fit: cover;" loading="lazy">
                    @else
                        <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 180px;">
                            <i class="ti ti-school fs-1 text-muted opacity-50"></i>
                        </div>
                    @endif
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="badge bg-light-primary">{{ $c['level'] ?: __('All Levels') }}</span>
                            <strong class="text-success">{{ $c['price'] ?: __('Free') }}</strong>
                        </div>

                        <h6 class="mb-2" style="line-height: 1.4; min-height: 42px;">{{ $c['name'] }}</h6>

                        <p class="text-muted small mb-2">
                            <i class="ti ti-user me-1"></i>{{ $c['instructor'] }}
                        </p>

                        @if (!empty($c['categories']))
                            <p class="small mb-3">
                                <i class="ti ti-tag me-1 text-muted"></i>
                                <span class="text-muted">{{ \Illuminate\Support\Str::limit($c['categories'], 60) }}</span>
                            </p>
                        @endif

                        <div class="row text-center border-top pt-3 g-2">
                            <div class="col-3">
                                <i class="ti ti-clock text-info"></i>
                                <small class="d-block text-muted">{{ $c['duration'] ?: '—' }}</small>
                            </div>
                            <div class="col-3">
                                <i class="ti ti-users text-success"></i>
                                <small class="d-block">{{ number_format($c['students_count']) }}</small>
                                <small class="text-muted" style="font-size: 9px;">{{ __('students') }}</small>
                            </div>
                            <div class="col-3">
                                <i class="ti ti-book-2 text-primary"></i>
                                <small class="d-block">{{ $c['lessons_count'] }}</small>
                                <small class="text-muted" style="font-size: 9px;">{{ __('lessons') }}</small>
                            </div>
                            <div class="col-3">
                                <i class="ti ti-help text-warning"></i>
                                <small class="d-block">{{ $c['quizzes_count'] }}</small>
                                <small class="text-muted" style="font-size: 9px;">{{ __('quizzes') }}</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card"><div class="card-body text-center py-5">
                    <i class="ti ti-book-off fs-1 d-block mb-2 opacity-50"></i>
                    <p class="text-muted mb-0">{{ __('No courses found or LearnPress API unreachable.') }}</p>
                </div></div>
            </div>
        @endforelse
    </div>

    {{-- Optional table view for power users --}}
    @if (count($courses))
        <div class="row mt-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">{{ __('Courses Table View') }}</h5></div>
                    <div class="card-body table-border-style">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('Course Title') }}</th>
                                        <th>{{ __('Instructor') }}</th>
                                        <th>{{ __('Duration') }}</th>
                                        <th>{{ __('Lessons') }}</th>
                                        <th>{{ __('Quizzes') }}</th>
                                        <th>{{ __('Students') }}</th>
                                        <th>{{ __('Level') }}</th>
                                        <th>{{ __('Price') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($courses as $c)
                                        <tr>
                                            <td>{{ $c['id'] }}</td>
                                            <td><strong>{{ \Illuminate\Support\Str::limit($c['name'], 60) }}</strong></td>
                                            <td><small>{{ $c['instructor'] }}</small></td>
                                            <td><small>{{ $c['duration'] }}</small></td>
                                            <td>{{ $c['lessons_count'] }}</td>
                                            <td>{{ $c['quizzes_count'] }}</td>
                                            <td><span class="badge bg-light-primary">{{ number_format($c['students_count']) }}</span></td>
                                            <td><small>{{ $c['level'] }}</small></td>
                                            <td><strong class="text-success">{{ $c['price'] ?: __('Free') }}</strong></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection
