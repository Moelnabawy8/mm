@extends('layouts.admin')
@php
    $profile = \App\Models\Utility::get_file('uploads/avatar/');
@endphp
@section('page-title')
    {{ __('Profile Account') }}
@endsection
@push('script-page')
    <script>
        var scrollSpy = new bootstrap.ScrollSpy(document.body, {
            target: '#useradd-sidenav',
            offset: 300,
        })
        $(".list-group-item").click(function() {
            $('.list-group-item').filter(function() {
                return this.href == id;
            }).parent().removeClass('text-primary');
        });
    </script>

    <script>
        document.getElementById('avatar').onchange = function() {
            var src = URL.createObjectURL(this.files[0])
            document.getElementById('image').src = src
        }
    </script>
@endpush
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('Profile') }}</li>
@endsection

@section('content')
    <div class="row">
        <div class="col-xl-3">
            <div class="card sticky-top" style="top:30px">
                <div class="list-group list-group-flush" id="useradd-sidenav">
                    <a href="#personal_info"
                        class="list-group-item list-group-item-action border-0">{{ __('Personal Info') }} <div
                            class="float-end"><i class="ti ti-chevron-right"></i></div></a>

                    <a href="#change_password"
                        class="list-group-item list-group-item-action border-0">{{ __('Change Password') }}<div
                            class="float-end"><i class="ti ti-chevron-right"></i></div></a>
                    <a href="#employee_info"
                        class="list-group-item list-group-item-action border-0">{{ __('Employee Info') }} <div
                            class="float-end"><i class="ti ti-chevron-right"></i></div></a>
                    <a href="#mark_attendance"
                        class="list-group-item list-group-item-action border-0">{{ __('Mark Attendance') }} <div
                            class="float-end"><i class="ti ti-chevron-right"></i></div></a>
                </div>
            </div>
        </div>
        <div class="col-xl-9">
            <div id="personal_info" class="card">
                <div class="card-header">
                    <h5>{{ __('Personal Info') }}</h5>
                </div>
                <div class="card-body">
                    {{ Form::model($userDetail, ['route' => ['update.account'], 'method' => 'post', 'enctype' => 'multipart/form-data']) }}
                    @csrf
                    <div class="row">
                        <div class="col-lg-6 col-sm-6">
                            <div class="form-group">
                                <label class="col-form-label text-dark">{{ __('Name') }}</label>
                                <input class="form-control" name="name" type="text" id="name"
                                    placeholder="{{ __('Enter Your Name') }}" value="{{ $userDetail->name }}" required
                                    autocomplete="name">
                            </div>
                        </div>
                        <div class="col-lg-6 col-sm-6">
                            <div class="form-group">
                                <label for="email" class="col-form-label text-dark">{{ __('Email') }}</label>
                                <input class="form-control" name="email" type="email" id="email"
                                    placeholder="{{ __('Enter Your Email Address') }}" value="{{ $userDetail->email }}"
                                    required autocomplete="email">
                            </div>
                        </div>
                        <div class="col-lg-6 col-md-6">
                            <div class="form-group">
                                <div class="theme-avtar-logo mt-4">
                                    <img id="image"
                                        src="{{ $userDetail->avatar ? $profile . $userDetail->avatar : $profile . 'avatar.png' }}"
                                        class="big-logo">
                                </div>
                                <div class="choose-files mt-3">
                                    <label for="avatar">
                                        <div class=" bg-primary profile_update"> <i
                                                class="ti ti-upload px-1"></i>{{ __('Choose file here') }}</div>
                                        <input type="file" class="form-control file file-validate" name="profile"
                                            id="avatar" data-filename="profile_update">
                                        <p id="" class="file-error text-danger"></p>
                                    </label>
                                </div>
                                <span
                                    class="text-xs text-muted">{{ __('Please upload a valid image file. Size of image should not be more than 2MB.') }}</span>
                            </div>

                        </div>
                        <div class="col-lg-12 text-end">
                            <input type="submit" value="{{ __('Save Changes') }}"
                                class="btn btn-print-invoice  btn-primary m-r-10">
                        </div>
                    </div>
                    </form>
                </div>

            </div>
            <div id="change_password" class="card">
                <div class="card-header">
                    <h5>{{ __('Change Password') }}</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="{{ route('update.password') }}">
                        @csrf
                        <div class="row">
                            <div class="col-lg-6 col-sm-6 form-group">
                                <label for="old_password" class="col-form-label text-dark">{{ __('Old Password') }}</label>
                                <input class="form-control" name="old_password" type="password" id="old_password" required
                                    autocomplete="old_password" placeholder="{{ __('Enter Old Password') }}">
                            </div>

                            <div class="col-lg-6 col-sm-6 form-group">
                                <label for="password" class="col-form-label text-dark">{{ __('New Password') }}</label>
                                <input class="form-control" name="password" type="password" required
                                    autocomplete="new-password" id="password"
                                    placeholder="{{ __('Enter Your New Password') }}">
                            </div>
                            <div class="col-lg-6 col-sm-6 form-group">
                                <label for="password_confirmation"
                                    class="col-form-label text-dark">{{ __('New Confirm Password') }}</label>
                                <input class="form-control" name="password_confirmation" type="password" required
                                    autocomplete="new-password" id="password_confirmation"
                                    placeholder="{{ __('Enter Your Confirm Password') }}">
                            </div>
                            <div class="col-lg-12 text-end">
                                <input type="submit" value="{{ __('Change Password') }}"
                                    class="btn btn-print-invoice  btn-primary m-r-10">
                            </div>
                        </div>
                    </form>
                </div>

            </div>
            <div id="employee_info" class="card">
                <div class="card-header">
                    <h5>{{ __('Employee Info') }}</h5>
                </div>
                <div class="card-body">
                    @if ($userDetail->employee)
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-group mb-3">
                                    <label class="fw-bold">{{ __('Employee ID') }}</label>
                                    <p>{{ $userDetail->employee->employee_id ?? '-' }}</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-group mb-3">
                                    <label class="fw-bold">{{ __('Department') }}</label>
                                    <p>{{ $userDetail->employee->department ? $userDetail->employee->department->name : '-' }}
                                    </p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-group mb-3">
                                    <label class="fw-bold">{{ __('Designation') }}</label>
                                    <p>{{ $userDetail->employee->designation ? $userDetail->employee->designation->name : '-' }}
                                    </p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-group mb-3">
                                    <label class="fw-bold">{{ __('Date of Joining') }}</label>
                                    <p>{{ $userDetail->employee->company_doj ?? '-' }}</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-group mb-3">
                                    <label class="fw-bold">{{ __('Phone') }}</label>
                                    <p>{{ $userDetail->employee->phone ?? '-' }}</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-group mb-3">
                                    <label class="fw-bold">{{ __('Address') }}</label>
                                    <p>{{ $userDetail->employee->address ?? '-' }}</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-group mb-3">
                                    <label class="fw-bold">{{ __('Salary') }}</label>
                                    <p>{{ $userDetail->employee->salary ? \Auth::user()->priceFormat($userDetail->employee->salary) : '-' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="alert alert-warning">
                            {{ __('No employee details found for this user.') }}
                        </div>
                    @endif
                </div>
            </div>
            <div id="mark_attendance" class="card">
                <div class="card-header">
                    <h5>{{ __('Mark Attendance') }}</h5>
                </div>
                <div class="card-body">
                    @if ($userDetail->employee)
                        <p>{{ __('My Office Time: ' . $officeTime['startTime'] . ' to ' . $officeTime['endTime']) }}</p>
                        <center>
                            <div class="row">
                                <div class="col-md-6">
                                    {{ Form::open(['url' => 'attendanceemployee/attendance', 'method' => 'post']) }}
                                    @if (empty($employeeAttendance) || $employeeAttendance->clock_out != '00:00:00')
                                        <button type="submit" value="0" name="in" id="clock_in"
                                            class="btn btn-success">{{ __('CLOCK IN') }}</button>
                                    @else
                                        <button type="submit" value="0" name="in" id="clock_in"
                                            class="btn btn-success disabled" disabled>{{ __('CLOCK IN') }}</button>
                                    @endif
                                    {{ Form::close() }}
                                </div>
                                <div class="col-md-6">
                                    @if (!empty($employeeAttendance) && $employeeAttendance->clock_out == '00:00:00')
                                        {{ Form::model($employeeAttendance, ['route' => ['attendanceemployee.update', $employeeAttendance->id], 'method' => 'PUT']) }}
                                        <button type="submit" value="1" name="out" id="clock_out"
                                            class="btn btn-danger">{{ __('CLOCK OUT') }}</button>
                                    @else
                                        <button type="submit" value="1" name="out" id="clock_out"
                                            class="btn btn-danger disabled" disabled>{{ __('CLOCK OUT') }}</button>
                                    @endif
                                    {{ Form::close() }}
                                </div>
                            </div>
                        </center>
                    @else
                        <div class="alert alert-warning">
                            {{ __('No employee record found. Please contact HR to set up your employee profile.') }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endsection
