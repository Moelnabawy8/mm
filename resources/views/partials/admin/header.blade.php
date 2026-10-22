@php
    $users = \Auth::user();
    $profile = \App\Models\Utility::get_file('uploads/avatar/');
    $languages = \App\Models\Utility::languages();

    $lang = isset($users->lang) ? $users->lang : 'en';
    if ($lang == null) {
        $lang = 'en';
    }
    $LangName = cache()->remember('full_language_data_' . $lang, now()->addHours(24), function () use ($lang) {
        return \App\Models\Language::languageData($lang);
    });

    $setting = \App\Models\Utility::settings();

    $unseenCounter = App\Models\ChMessage::where('to_id', Auth::user()->id)
        ->where('seen', 0)
        ->count();

    // Get unread notifications count
    $unreadNotifications = \App\Models\Notification::where('user_id', Auth::user()->id)
        ->where('is_read', 0)
        ->orderBy('created_at', 'desc')
        ->get();
    $unreadCount = $unreadNotifications->count();
@endphp
@if (isset($setting['cust_theme_bg']) && $setting['cust_theme_bg'] == 'on')
    <header class="dash-header transprent-bg">
    @else
        <header class="dash-header">
@endif
<div class="header-wrapper">
    <div class="me-auto dash-mob-drp">
        <ul class="list-unstyled">
            <li class="dash-h-item mob-hamburger">
                <a href="#!" class="dash-head-link" id="mobile-collapse">
                    <div class="hamburger hamburger--arrowturn">
                        <div class="hamburger-box">
                            <div class="hamburger-inner"></div>
                        </div>
                    </div>
                </a>
            </li>

            <li class="dropdown dash-h-item drp-company">
                <a class="dash-head-link dropdown-toggle arrow-none me-0" data-bs-toggle="dropdown" href="#"
                    role="button" aria-haspopup="false" aria-expanded="false">
                    <span class="theme-avtar">
                        <img src="{{ !empty(\Auth::user()->avatar) ? $profile . \Auth::user()->avatar : $profile . 'avatar.png' }}"
                            class="img-fluid rounded border-2 border border-primary">
                    </span>
                    <span class="hide-mob ms-2">{{ __('Hi, ') }}{{ \Auth::user()->name }}!</span>
                    <i class="ti ti-chevron-down drp-arrow nocolor hide-mob"></i>
                </a>
                <div class="dropdown-menu dash-h-dropdown">

                    <a href="{{ route('profile') }}" class="dropdown-item">
                        <i class="ti ti-user text-dark"></i><span>{{ __('Profile') }}</span>
                    </a>

                    <a href="{{ route('logout') }}"
                        onclick="event.preventDefault(); document.getElementById('frm-logout').submit();"
                        class="dropdown-item">
                        <i class="ti ti-power text-dark"></i><span>{{ __('Logout') }}</span>
                    </a>

                    <form id="frm-logout" action="{{ route('logout') }}" method="POST" class="d-none">
                        {{ csrf_field() }}
                    </form>

                </div>
            </li>

        </ul>
    </div>
    <div class="ms-auto">
        <ul class="list-unstyled">
            @if (\Auth::user()->type == 'company')
                @impersonating($guard = null)
                    <li class="dropdown dash-h-item drp-company">
                        <a class="btn btn-danger btn-sm" href="{{ route('exit.company') }}"><i class="ti ti-ban"></i>
                            {{ __('Exit Company Login') }}
                        </a>
                    </li>
                @endImpersonating
            @endif

            @if (\Auth::user()->type != 'client' && \Auth::user()->type != 'super admin')
                <li class="dropdown dash-h-item drp-notification">
                    <a class="dash-head-link arrow-none me-0" href="{{ url('chats') }}" aria-haspopup="false"
                        aria-expanded="false">
                        <i class="ti ti-brand-hipchat"></i>
                        <span
                            class="bg-danger dash-h-badge message-toggle-msg  message-counter custom_messanger_counter beep">
                            {{ $unseenCounter }}<span class="sr-only"></span>
                        </span>
                    </a>
                </li>
            @endif

            @if (\Auth::user()->type != 'client' && \Auth::user()->type != 'super admin')
                <li class="dropdown dash-h-item drp-notification">
                    <a class="dash-head-link dropdown-toggle arrow-none me-0" data-bs-toggle="dropdown" href="#"
                        role="button" aria-haspopup="false" aria-expanded="false">
                        <i class="ti ti-bell"></i>
                        @if ($unreadCount > 0)
                            <span class="bg-danger dash-h-badge notification-badge beep">{{ $unreadCount }}</span>
                        @endif
                    </a>
                    <div class="dropdown-menu dash-h-dropdown dropdown-menu-end notification-dropdown">
                        <div class="noti-header">
                            <h5 class="m-0">{{ __('Notifications') }}</h5>
                            @if ($unreadCount > 0)
                                <a href="#" class="mark-all-read"
                                    onclick="event.preventDefault(); markAllAsRead();">
                                    <small>{{ __('Mark all as read') }}</small>
                                </a>
                            @endif
                        </div>
                        <div class="noti-body" style="max-height: 400px; overflow-y: auto;">
                            @forelse($unreadNotifications->take(10) as $notification)
                                <div class="notification-item" data-id="{{ $notification->id }}">
                                    {!! $notification->toHtml() !!}
                                </div>
                            @empty
                                <div class="text-center py-4">
                                    <i class="ti ti-bell-off" style="font-size: 48px; color: #ccc;"></i>
                                    <p class="text-muted mt-2">{{ __('No new notifications') }}</p>
                                </div>
                            @endforelse
                        </div>
                        @if ($unreadNotifications->count() > 0)
                            <div class="noti-footer text-center">
                                <a href="#"
                                    onclick="event.preventDefault(); viewAllNotifications();">{{ __('View All Notifications') }}</a>
                            </div>
                        @endif
                    </div>
                </li>
            @endif

            <li class="dropdown dash-h-item drp-language">
                <a class="dash-head-link dropdown-toggle arrow-none me-0" data-bs-toggle="dropdown" href="#"
                    role="button" aria-haspopup="false" aria-expanded="false">
                    <i class="ti ti-world nocolor"></i>
                    <span class="drp-text hide-mob">{{ ucfirst($LangName->full_name) }}</span>
                    <i class="ti ti-chevron-down drp-arrow nocolor"></i>
                </a>
                <div class="dropdown-menu dash-h-dropdown dropdown-menu-end">
                    @foreach ($languages as $code => $language)
                        <a href="{{ route('change.language', $code) }}"
                            class="dropdown-item {{ $lang == $code ? 'text-primary' : '' }}">
                            <span>{{ ucFirst($language) }}</span>
                        </a>
                    @endforeach

                    <h></h>
                    @if (\Auth::user()->type == 'super admin')
                        <a data-url="{{ route('create.language') }}" class="dropdown-item text-primary"
                            data-ajax-popup="true" data-title="{{ __('Create New Language') }}"
                            style="cursor: pointer">
                            {{ __('Create Language') }}
                        </a>
                        <a class="dropdown-item text-primary"
                            href="{{ route('manage.language', [isset($lang) ? $lang : 'english']) }}">{{ __('Manage Language') }}</a>
                    @endif
                </div>
            </li>
        </ul>
    </div>
</div>
</header>
