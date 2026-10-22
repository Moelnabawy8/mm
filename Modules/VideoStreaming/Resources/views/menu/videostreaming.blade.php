<li class="dash-item dash-hasmenu
    {{ (Request::route()->getName() == 'videostreaming.index') 
    || (Request::route()->getName() == 'videostreaming.create')
    || (Request::route()->getName() == 'videostreaming.show')
    || (Request::route()->getName() == 'videostreaming.settings') ? ' active' : '' }}">
    <a href="{{ route('videostreaming.index') }}" class="dash-link">
        <span class="dash-micon"><i class="ti ti-video"></i></span>
        <span class="dash-mtext">{{__('Video Streaming')}}</span>
        <span class="dash-arrow"><i data-feather="chevron-right"></i></span>
    </a>
    <ul class="dash-submenu">
        <li class="dash-item {{ Request::route()->getName() == 'videostreaming.index' ? 'active' : '' }}">
            <a class="dash-link" href="{{ route('videostreaming.index') }}">{{__('My Videos')}}</a>
        </li>
        <li class="dash-item {{ Request::route()->getName() == 'videostreaming.create' ? 'active' : '' }}">
            <a class="dash-link" href="{{ route('videostreaming.create') }}">{{__('Upload Video')}}</a>
        </li>
        <li class="dash-item {{ Request::route()->getName() == 'videostreaming.settings' ? 'active' : '' }}">
            <a class="dash-link" href="{{ route('videostreaming.settings') }}">{{__('Settings')}}</a>
        </li>
    </ul>
</li>
