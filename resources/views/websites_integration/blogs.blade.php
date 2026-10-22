@extends('layouts.admin')

@section('page-title')
    {{ __('Websites — Blogs') }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('websites.dashboard') }}">{{ __('Websites') }}</a></li>
    <li class="breadcrumb-item">{{ __('Blogs') }}</li>
@endsection

@section('content')
    <div class="row">
        <div class="col-lg-9">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="mb-0">{{ __('Posts') }} <span class="badge bg-light-secondary ms-1">{{ number_format($total) }}</span></h5>
                    <form method="GET" action="{{ route('websites.blogs') }}" class="d-flex gap-2 mb-0">
                        <input type="hidden" name="cat" value="{{ $catId }}">
                        <input type="search" name="q" value="{{ $search }}" class="form-control form-control-sm" placeholder="{{ __('Search posts…') }}" style="width: 220px;">
                        <button type="submit" class="btn btn-sm btn-primary"><i class="ti ti-search"></i></button>
                        @if ($search || $catId)
                            <a href="{{ route('websites.blogs') }}" class="btn btn-sm btn-outline-secondary"><i class="ti ti-x"></i></a>
                        @endif
                    </form>
                </div>
                <div class="card-body table-border-style">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>{{ __('Title') }}</th>
                                    <th>{{ __('Author') }}</th>
                                    <th>{{ __('Categories') }}</th>
                                    <th>{{ __('Published') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($posts as $post)
                                    <tr>
                                        <td>
                                            <strong>{{ $post['title'] ?: '(untitled)' }}</strong>
                                            @if (!empty($post['excerpt']))<br><small class="text-muted">{{ \Illuminate\Support\Str::limit($post['excerpt'], 100) }}</small>@endif
                                        </td>
                                        <td>{{ $post['author'] }}</td>
                                        <td>
                                            @foreach ($post['categories'] ?? [] as $cat)
                                                <span class="badge bg-light-secondary me-1">{{ $cat }}</span>
                                            @endforeach
                                        </td>
                                        <td><small>{{ $post['date'] ? \Carbon\Carbon::parse($post['date'])->format('Y-m-d') : '—' }}</small></td>
                                        <td>
                                            @if (!empty($post['link']))
                                                <a href="{{ $post['link'] }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
                                                    <i class="ti ti-external-link"></i>
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted py-5">
                                        <i class="ti ti-cloud-off fs-1 d-block mb-2 opacity-50"></i>
                                        {{ __('No posts match the filter.') }}
                                    </td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @include('websites_integration.partials.pagination', ['currentPage' => $currentPage, 'pages' => $pages, 'route' => 'websites.blogs', 'extra' => ['q' => $search, 'cat' => $catId]])
                </div>
            </div>
        </div>

        <div class="col-lg-3">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">{{ __('Categories') }}</h5></div>
                <div class="card-body p-0">
                    <a href="{{ route('websites.blogs') }}" class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom text-decoration-none {{ !$catId ? 'bg-light fw-bold' : 'text-body' }}">
                        <span>{{ __('All') }}</span><span class="badge bg-light-secondary">{{ $total }}</span>
                    </a>
                    @forelse ($categories as $cat)
                        <a href="{{ route('websites.blogs', ['cat' => $cat['id']]) }}"
                           class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom text-decoration-none {{ $catId == $cat['id'] ? 'bg-light fw-bold' : 'text-body' }}">
                            <span>{{ $cat['name'] }}</span>
                            <span class="badge bg-light-primary">{{ $cat['count'] }}</span>
                        </a>
                    @empty
                        <p class="text-muted text-center p-4 mb-0 small">{{ __('No categories.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
