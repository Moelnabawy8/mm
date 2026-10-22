@php
use App\Models\Utility;
$company_small_logo = App\Models\Utility::getValByName('company_small_logo');
$mode_setting = \App\Models\Utility::mode_layout();
$logo = \App\Models\Utility::get_file('uploads/logo/');
$company_logo = Utility::get_company_logo();
$emailTemplate = App\Models\EmailTemplate::first();
$SITE_RTL = !empty($setting['SITE_RTL']) ? $setting['SITE_RTL'] : 'off';
@endphp

<nav class="dash-sidebar light-sidebar {{ isset($mode_setting['cust_theme_bg']) && $mode_setting['cust_theme_bg'] == 'on' ? 'transprent-bg' : '' }}">
    <div class="navbar-wrapper">
        <div class="m-header main-logo">
            <a href="" class="b-brand">
                <img src="{{ $logo . (isset($company_logo) && !empty($company_logo) ? $company_logo : 'logo-dark.png') . '?' . time() }}"
                    alt="{{ config('app.name', 'AccountGo') }}" class="logo logo-lg">
            </a>
        </div>

        <div class="navbar-content">
            <ul class="dash-navbar">
                <!-- Dashboard -->
                <li class="dash-item {{ Request::route()->getName() == 'dashboard' ? 'active' : '' }}">
                    <a href="{{ route('dashboard') }}" class="dash-link">
                        <span class="dash-micon"><i class="ti ti-home"></i></span>
                        <span class="dash-mtext">{{ __('Dashboard') }}</span>
                    </a>
                </li>

                <!-- Sales -->
                <li class="dash-item dash-hasmenu {{ Request::segment(1) == 'sales' ? 'active dash-trigger' : '' }}">
                    <a href="#!" class="dash-link">
                        <span class="dash-micon"><i class="ti ti-receipt"></i></span>
                        <span class="dash-mtext">{{ __('Sales') }}</span>
                        <span class="dash-arrow"><i data-feather="chevron-right"></i></span>
                    </a>
                    <ul class="dash-submenu">
                        <li class="dash-item">
                            <a class="dash-link" href="#">{{ __('Overview') }}</a>
                        </li>
                    </ul>
                </li>

                <!-- Clients -->
                <li class="dash-item dash-hasmenu {{ Request::segment(1) == 'clients' ? 'active dash-trigger' : '' }}">
                    <a href="#!" class="dash-link">
                        <span class="dash-micon"><i class="ti ti-user"></i></span>
                        <span class="dash-mtext">{{ __('Clients') }}</span>
                        <span class="dash-arrow"><i data-feather="chevron-right"></i></span>
                    </a>
                </li>

                <!-- Inventory -->
                <li class="dash-item dash-hasmenu {{ Request::segment(1) == 'inventory' ? 'active dash-trigger' : '' }}">
                    <a href="#!" class="dash-link">
                        <span class="dash-micon"><i class="ti ti-box"></i></span>
                        <span class="dash-mtext">{{ __('Inventory') }}</span>
                        <span class="dash-arrow"><i data-feather="chevron-right"></i></span>
                    </a>
                </li>

                <!-- Purchases -->
                <li class="dash-item dash-hasmenu {{ Request::segment(1) == 'purchases' ? 'active dash-trigger' : '' }}">
                    <a href="#!" class="dash-link">
                        <span class="dash-micon"><i class="ti ti-shopping-cart"></i></span>
                        <span class="dash-mtext">{{ __('Purchases') }}</span>
                        <span class="dash-arrow"><i data-feather="chevron-right"></i></span>
                    </a>
                </li>

                <!-- Finance -->
                <li class="dash-item dash-hasmenu active dash-trigger">
                    <a href="#!" class="dash-link">
                        <span class="dash-micon"><i class="ti ti-credit-card"></i></span>
                        <span class="dash-mtext">{{ __('Finance') }}</span>
                        <span class="dash-arrow"><i data-feather="chevron-right"></i></span>
                    </a>
                </li>

                <!-- Accounting -->
                <li class="dash-item dash-hasmenu {{ Request::segment(1) == 'accounting' ? 'active dash-trigger' : '' }}">
                    <a href="#!" class="dash-link">
                        <span class="dash-micon"><i class="ti ti-calculator"></i></span>
                        <span class="dash-mtext">{{ __('Accounting') }}</span>
                        <span class="dash-arrow"><i data-feather="chevron-right"></i></span>
                    </a>
                </li>

                <!-- Employees -->
                <li class="dash-item dash-hasmenu {{ Request::segment(1) == 'employees' ? 'active dash-trigger' : '' }}">
                    <a href="#!" class="dash-link">
                        <span class="dash-micon"><i class="ti ti-users"></i></span>
                        <span class="dash-mtext">{{ __('Employees') }}</span>
                        <span class="dash-arrow"><i data-feather="chevron-right"></i></span>
                    </a>
                </li>

                <!-- Organizational Structure -->
                <li class="dash-item dash-hasmenu">
                    <a href="#!" class="dash-link">
                        <span class="dash-micon"><i class="ti ti-sitemap"></i></span>
                        <span class="dash-mtext">{{ __('Organizational Structure') }}</span>
                        <span class="dash-arrow"><i data-feather="chevron-right"></i></span>
                    </a>
                </li>

                <!-- Attendance -->
                <li class="dash-item dash-hasmenu">
                    <a href="#!" class="dash-link">
                        <span class="dash-micon"><i class="ti ti-calendar-event"></i></span>
                        <span class="dash-mtext">{{ __('Attendance') }}</span>
                        <span class="dash-arrow"><i data-feather="chevron-right"></i></span>
                    </a>
                </li>

                <!-- Payroll -->
                <li class="dash-item dash-hasmenu">
                    <a href="#!" class="dash-link">
                        <span class="dash-micon"><i class="ti ti-report-money"></i></span>
                        <span class="dash-mtext">{{ __('Payroll') }}</span>
                        <span class="dash-arrow"><i data-feather="chevron-right"></i></span>
                    </a>
                </li>

                <!-- Reports -->
                <li class="dash-item dash-hasmenu">
                    <a href="#!" class="dash-link">
                        <span class="dash-micon"><i class="ti ti-chart-bar"></i></span>
                        <span class="dash-mtext">{{ __('Reports') }}</span>
                        <span class="dash-arrow"><i data-feather="chevron-right"></i></span>
                    </a>
                </li>

                <!-- Templates -->
                <li class="dash-item dash-hasmenu">
                    <a href="#!" class="dash-link">
                        <span class="dash-micon"><i class="ti ti-template"></i></span>
                        <span class="dash-mtext">{{ __('Templates') }}</span>
                        <span class="dash-arrow"><i data-feather="chevron-right"></i></span>
                    </a>
                </li>

                <!-- Settings -->
                <li class="dash-item dash-hasmenu">
                    <a href="#!" class="dash-link">
                        <span class="dash-micon"><i class="ti ti-settings"></i></span>
                        <span class="dash-mtext">{{ __('Settings') }}</span>
                        <span class="dash-arrow"><i data-feather="chevron-right"></i></span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>