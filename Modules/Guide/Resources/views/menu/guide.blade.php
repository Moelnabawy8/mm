<li class="dash-item dash-hasmenu
    {{ (Request::route()->getName() == 'guide.index') 
    || (Request::route()->getName() == 'guide.videos')
    || (Request::route()->getName() == 'guide.videos.show')
    || (Request::route()->getName() == 'guide.documentation')
    || (Request::route()->getName() == 'guide.documentation.show')
    || (Request::route()->getName() == 'guide.category')
    || (Request::route()->getName() == 'guide.search') ? ' active' : '' }}">
    <a href="{{ route('guide.index') }}" class="dash-link">
        <span class="dash-micon"><i class="ti ti-book-2" style="color: #6c5ce7;"></i></span>
        <span class="dash-mtext">{{ __('System Guide') }}</span>
        <span class="dash-arrow"><i data-feather="chevron-right"></i></span>
    </a>
    <ul class="dash-submenu">
        <li class="dash-item {{ Request::route()->getName() == 'guide.index' ? 'active' : '' }}">
            <a class="dash-link" href="{{ route('guide.index') }}">
                <i class="ti ti-home me-2"></i>{{ __('Guide Home') }}
            </a>
        </li>
        <li class="dash-item {{ Request::route()->getName() == 'guide.videos' || Request::route()->getName() == 'guide.videos.show' ? 'active' : '' }}">
            <a class="dash-link" href="{{ route('guide.videos') }}">
                <i class="ti ti-video me-2"></i>{{ __('Video Tutorials') }}
            </a>
        </li>
        <li class="dash-item {{ Request::route()->getName() == 'guide.documentation' || Request::route()->getName() == 'guide.documentation.show' ? 'active' : '' }}">
            <a class="dash-link" href="{{ route('guide.documentation') }}">
                <i class="ti ti-file-text me-2"></i>{{ __('Documentation') }}
            </a>
        </li>
    </ul>
</li>
