@extends('layouts.admin')

@section('page-title')
    {{ __('SEO Report') }} — {{ $data['site_name'] ?? '' }}
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('Websites') }}</li>
    <li class="breadcrumb-item">{{ __('SEO') }}</li>
    <li class="breadcrumb-item">{{ $data['site_name'] ?? '' }}</li>
@endsection

@push('css-page')
<style>
    .seo-score-ring{position:relative;width:140px;height:140px;margin:0 auto}
    .seo-score-ring svg{transform:rotate(-90deg)}
    .seo-score-ring .score-text{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:32px;font-weight:800}
    .seo-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:600}
    .seo-badge-pass{background:#e8f5e9;color:#2e7d32}
    .seo-badge-fail{background:#fdecea;color:#d32f2f}
    .seo-badge-warn{background:#fff4e5;color:#ed6c02}
    .seo-badge-info{background:#e3f2fd;color:#1976d2}
    .seo-check-row{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #f0f3f7;font-size:13px}
    .seo-check-row:last-child{border-bottom:none}
    .seo-section{margin-bottom:24px}
    .issue-critical{border-left:3px solid #d32f2f;background:#fdecea}
    .issue-warning{border-left:3px solid #ed6c02;background:#fff4e5}
    .issue-info{border-left:3px solid #1976d2;background:#e3f2fd}
    .issue-item{padding:10px 14px;margin-bottom:6px;border-radius:0 6px 6px 0;font-size:13px}
    .meta-preview{background:#fff;border:1px solid #e4eaf2;border-radius:10px;padding:16px;max-width:600px}
    .meta-preview .mp-url{color:#1a73e8;font-size:12px;margin-bottom:2px}
    .meta-preview .mp-title{color:#1a0dab;font-size:18px;font-weight:400;margin-bottom:4px;cursor:pointer}
    .meta-preview .mp-title:hover{text-decoration:underline}
    .meta-preview .mp-desc{color:#545454;font-size:13px;line-height:1.5}
</style>
@endpush

@section('content')
    {{-- Site Toggle + Refresh --}}
    <div class="row mb-3">
        <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <ul class="nav nav-pills">
                <li class="nav-item">
                    <a class="nav-link {{ $site === 'arabic' ? 'active' : '' }}" href="{{ route('websites.seo.arabic') }}">
                        <i class="ti ti-world me-1"></i>{{ __('Arabic Website') }}
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $site === 'english' ? 'active' : '' }}" href="{{ route('websites.seo.english') }}">
                        <i class="ti ti-world me-1"></i>{{ __('English Website') }}
                    </a>
                </li>
            </ul>
            <div class="d-flex align-items-center gap-2">
                @if(!empty($data['analyzed_at']))
                    <small class="text-muted"><i class="ti ti-clock me-1"></i>{{ \Carbon\Carbon::parse($data['analyzed_at'])->diffForHumans() }}</small>
                @endif
                <a href="{{ route('websites.seo.' . $site, ['refresh' => 1]) }}" class="btn btn-sm btn-primary">
                    <i class="ti ti-refresh me-1"></i>{{ __('Refresh') }}
                </a>
            </div>
        </div>
    </div>

    @if(!empty($data['error']))
        <div class="row">
            <div class="col-12">
                <div class="alert alert-danger"><i class="ti ti-alert-circle me-1"></i> {{ $data['error'] }}</div>
            </div>
        </div>
    @else

    {{-- Score + Overview --}}
    <div class="row">
        <div class="col-xl-3 col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h6 class="text-muted mb-3">{{ __('SEO Score') }}</h6>
                    @php
                        $score = $data['score'] ?? 0;
                        $color = $score >= 80 ? '#2e7d32' : ($score >= 50 ? '#ed6c02' : '#d32f2f');
                        $circumference = 2 * 3.14159 * 54;
                        $offset = $circumference - ($circumference * $score / 100);
                    @endphp
                    <div class="seo-score-ring">
                        <svg width="140" height="140">
                            <circle cx="70" cy="70" r="54" fill="none" stroke="#e8eaed" stroke-width="12"/>
                            <circle cx="70" cy="70" r="54" fill="none" stroke="{{ $color }}" stroke-width="12"
                                    stroke-dasharray="{{ $circumference }}" stroke-dashoffset="{{ $offset }}"
                                    stroke-linecap="round"/>
                        </svg>
                        <div class="score-text" style="color:{{ $color }}">{{ $score }}</div>
                    </div>
                    <div class="mt-2">
                        @if($score >= 80)
                            <span class="seo-badge seo-badge-pass"><i class="ti ti-check"></i> {{ __('Good') }}</span>
                        @elseif($score >= 50)
                            <span class="seo-badge seo-badge-warn"><i class="ti ti-alert-triangle"></i> {{ __('Needs Work') }}</span>
                        @else
                            <span class="seo-badge seo-badge-fail"><i class="ti ti-x"></i> {{ __('Poor') }}</span>
                        @endif
                    </div>
                    <a href="{{ $data['url'] }}" target="_blank" class="d-block mt-3 small text-muted">
                        <i class="ti ti-external-link me-1"></i>{{ $data['url'] }}
                    </a>
                </div>
            </div>
        </div>

        {{-- Quick Stats --}}
        <div class="col-xl-9 col-md-8">
            <div class="row">
                @php
                    $checks = [
                        ['label' => __('Title Tag'),      'pass' => $data['meta']['has_title'] ?? false,       'icon' => 'heading'],
                        ['label' => __('Meta Desc'),      'pass' => $data['meta']['has_description'] ?? false, 'icon' => 'file-description'],
                        ['label' => __('H1 Tag'),         'pass' => $data['headings']['has_h1'] ?? false,      'icon' => 'h-1'],
                        ['label' => __('Open Graph'),     'pass' => $data['meta']['has_og'] ?? false,          'icon' => 'brand-facebook'],
                        ['label' => __('Twitter Card'),   'pass' => $data['meta']['has_twitter'] ?? false,     'icon' => 'brand-twitter'],
                        ['label' => __('Canonical'),      'pass' => $data['meta']['has_canonical'] ?? false,   'icon' => 'link'],
                        ['label' => __('Schema.org'),     'pass' => $data['schema']['has_schema'] ?? false,    'icon' => 'code'],
                        ['label' => __('Sitemap'),        'pass' => $data['sitemap']['exists'] ?? false,       'icon' => 'sitemap'],
                        ['label' => __('Robots.txt'),     'pass' => $data['robots']['exists'] ?? false,        'icon' => 'robot'],
                        ['label' => __('SSL/HTTPS'),      'pass' => $data['ssl']['https'] ?? false,            'icon' => 'lock'],
                        ['label' => __('Viewport'),       'pass' => $data['meta']['has_viewport'] ?? false,    'icon' => 'device-mobile'],
                        ['label' => __('Compression'),    'pass' => $data['performance']['has_gzip'] ?? false, 'icon' => 'file-zip'],
                    ];
                @endphp
                @foreach ($checks as $ck)
                    <div class="col-xl-3 col-md-4 col-6 mb-2">
                        <div class="card mb-0 h-100">
                            <div class="card-body py-3 px-3 d-flex align-items-center gap-2">
                                <i class="ti ti-{{ $ck['icon'] }} fs-5 {{ $ck['pass'] ? 'text-success' : 'text-danger' }}"></i>
                                <div>
                                    <small class="d-block text-muted" style="font-size:11px">{{ $ck['label'] }}</small>
                                    @if($ck['pass'])
                                        <span class="seo-badge seo-badge-pass"><i class="ti ti-check"></i> {{ __('Pass') }}</span>
                                    @else
                                        <span class="seo-badge seo-badge-fail"><i class="ti ti-x"></i> {{ __('Fail') }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Issues --}}
    @if(!empty($data['issues']))
    <div class="row seo-section">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="ti ti-alert-circle me-1"></i>{{ __('Issues Found') }}
                        <span class="badge bg-danger ms-1">{{ count($data['issues']) }}</span>
                    </h5>
                </div>
                <div class="card-body">
                    @foreach ($data['issues'] as $issue)
                        <div class="issue-item issue-{{ $issue['level'] }}">
                            @if($issue['level'] === 'critical')
                                <i class="ti ti-alert-octagon text-danger me-1"></i>
                            @elseif($issue['level'] === 'warning')
                                <i class="ti ti-alert-triangle text-warning me-1"></i>
                            @else
                                <i class="ti ti-info-circle text-primary me-1"></i>
                            @endif
                            {{ $issue['msg'] }}
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="row">
        {{-- Google Preview --}}
        <div class="col-xl-6 col-md-12 seo-section">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0"><i class="ti ti-search me-1"></i> {{ __('Google Search Preview') }}</h5></div>
                <div class="card-body">
                    <div class="meta-preview">
                        <div class="mp-url">{{ $data['url'] ?? '' }}</div>
                        <div class="mp-title">{{ $data['meta']['title'] ?? __('No Title') }}</div>
                        <div class="mp-desc">{{ $data['meta']['description'] ?? __('No description available.') }}</div>
                    </div>
                    <div class="mt-3">
                        <div class="seo-check-row">
                            <span>{{ __('Title Length') }}</span>
                            <span>
                                <strong>{{ $data['meta']['title_length'] ?? 0 }}</strong>
                                <small class="text-muted">/ 65 {{ __('chars') }}</small>
                                @if(($data['meta']['title_length'] ?? 0) >= 30 && ($data['meta']['title_length'] ?? 0) <= 65)
                                    <span class="seo-badge seo-badge-pass ms-1">OK</span>
                                @else
                                    <span class="seo-badge seo-badge-warn ms-1">{{ ($data['meta']['title_length'] ?? 0) > 65 ? __('Too Long') : __('Too Short') }}</span>
                                @endif
                            </span>
                        </div>
                        <div class="seo-check-row">
                            <span>{{ __('Description Length') }}</span>
                            <span>
                                <strong>{{ $data['meta']['desc_length'] ?? 0 }}</strong>
                                <small class="text-muted">/ 160 {{ __('chars') }}</small>
                                @if(($data['meta']['desc_length'] ?? 0) >= 120 && ($data['meta']['desc_length'] ?? 0) <= 160)
                                    <span class="seo-badge seo-badge-pass ms-1">OK</span>
                                @else
                                    <span class="seo-badge seo-badge-warn ms-1">{{ ($data['meta']['desc_length'] ?? 0) > 160 ? __('Too Long') : __('Too Short') }}</span>
                                @endif
                            </span>
                        </div>
                        <div class="seo-check-row">
                            <span>{{ __('Language') }}</span>
                            <span><code>{{ $data['meta']['lang'] ?? __('Not set') }}</code></span>
                        </div>
                        <div class="seo-check-row">
                            <span>{{ __('Charset') }}</span>
                            <span><code>{{ $data['meta']['charset'] ?? __('Not set') }}</code></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Performance --}}
        <div class="col-xl-6 col-md-12 seo-section">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0"><i class="ti ti-gauge me-1"></i> {{ __('Performance') }}</h5></div>
                <div class="card-body">
                    @php $perf = $data['performance'] ?? []; @endphp
                    <div class="seo-check-row">
                        <span>{{ __('Load Time') }}</span>
                        <span>
                            <strong>{{ $perf['load_time_ms'] ?? 0 }} ms</strong>
                            @if(($perf['load_time_ms'] ?? 0) < 1000)
                                <span class="seo-badge seo-badge-pass ms-1">{{ __('Fast') }}</span>
                            @elseif(($perf['load_time_ms'] ?? 0) < 3000)
                                <span class="seo-badge seo-badge-warn ms-1">{{ __('Average') }}</span>
                            @else
                                <span class="seo-badge seo-badge-fail ms-1">{{ __('Slow') }}</span>
                            @endif
                        </span>
                    </div>
                    <div class="seo-check-row">
                        <span>{{ __('Page Size') }}</span>
                        <span><strong>{{ $perf['page_size_kb'] ?? 0 }} KB</strong></span>
                    </div>
                    <div class="seo-check-row">
                        <span>{{ __('HTTP Status') }}</span>
                        <span>
                            <span class="seo-badge {{ ($perf['status_code'] ?? 0) === 200 ? 'seo-badge-pass' : 'seo-badge-fail' }}">
                                {{ $perf['status_code'] ?? 'N/A' }}
                            </span>
                        </span>
                    </div>
                    <div class="seo-check-row">
                        <span>{{ __('Compression (Gzip/BR)') }}</span>
                        <span>
                            @if($perf['has_gzip'] ?? false)
                                <span class="seo-badge seo-badge-pass"><i class="ti ti-check"></i> {{ __('Enabled') }}</span>
                            @else
                                <span class="seo-badge seo-badge-fail"><i class="ti ti-x"></i> {{ __('Disabled') }}</span>
                            @endif
                        </span>
                    </div>
                    <div class="seo-check-row">
                        <span>{{ __('Server') }}</span>
                        <span><code>{{ $perf['server'] ?? 'Unknown' }}</code></span>
                    </div>
                    <div class="seo-check-row">
                        <span>{{ __('Cache-Control') }}</span>
                        <span><code style="font-size:11px">{{ $perf['cache_control'] ?? __('Not set') }}</code></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        {{-- SSL Certificate --}}
        <div class="col-xl-4 col-md-6 seo-section">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0"><i class="ti ti-lock me-1"></i> {{ __('SSL Certificate') }}</h5></div>
                <div class="card-body">
                    @php $ssl = $data['ssl'] ?? []; @endphp
                    <div class="seo-check-row">
                        <span>{{ __('HTTPS') }}</span>
                        <span>
                            @if($ssl['https'] ?? false)
                                <span class="seo-badge seo-badge-pass"><i class="ti ti-check"></i> {{ __('Yes') }}</span>
                            @else
                                <span class="seo-badge seo-badge-fail"><i class="ti ti-x"></i> {{ __('No') }}</span>
                            @endif
                        </span>
                    </div>
                    @if(!empty($ssl['issuer']))
                        <div class="seo-check-row"><span>{{ __('Issuer') }}</span><span>{{ $ssl['issuer'] }}</span></div>
                    @endif
                    @if(!empty($ssl['valid_from']))
                        <div class="seo-check-row"><span>{{ __('Valid From') }}</span><span>{{ $ssl['valid_from'] }}</span></div>
                    @endif
                    @if(!empty($ssl['valid_to']))
                        <div class="seo-check-row"><span>{{ __('Expires') }}</span><span>{{ $ssl['valid_to'] }}</span></div>
                    @endif
                    @if(isset($ssl['days_left']))
                        <div class="seo-check-row">
                            <span>{{ __('Days Left') }}</span>
                            <span>
                                <strong>{{ $ssl['days_left'] }}</strong>
                                @if($ssl['days_left'] > 30)
                                    <span class="seo-badge seo-badge-pass ms-1">OK</span>
                                @elseif($ssl['days_left'] > 0)
                                    <span class="seo-badge seo-badge-warn ms-1">{{ __('Expiring Soon') }}</span>
                                @else
                                    <span class="seo-badge seo-badge-fail ms-1">{{ __('Expired') }}</span>
                                @endif
                            </span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Sitemap & Robots --}}
        <div class="col-xl-4 col-md-6 seo-section">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0"><i class="ti ti-sitemap me-1"></i> {{ __('Sitemap & Robots') }}</h5></div>
                <div class="card-body">
                    @php $sm = $data['sitemap'] ?? []; $rb = $data['robots'] ?? []; @endphp
                    <h6 class="text-muted mb-2">Sitemap.xml</h6>
                    <div class="seo-check-row">
                        <span>{{ __('Status') }}</span>
                        <span>
                            @if($sm['exists'] ?? false)
                                <span class="seo-badge seo-badge-pass"><i class="ti ti-check"></i> {{ __('Found') }}</span>
                            @else
                                <span class="seo-badge seo-badge-fail"><i class="ti ti-x"></i> {{ __('Not Found') }}</span>
                            @endif
                        </span>
                    </div>
                    @if($sm['url_count'] ?? 0)
                        <div class="seo-check-row"><span>{{ __('URLs') }}</span><span><strong>{{ number_format($sm['url_count']) }}</strong></span></div>
                    @endif

                    <hr class="my-2">
                    <h6 class="text-muted mb-2">Robots.txt</h6>
                    <div class="seo-check-row">
                        <span>{{ __('Status') }}</span>
                        <span>
                            @if($rb['exists'] ?? false)
                                <span class="seo-badge seo-badge-pass"><i class="ti ti-check"></i> {{ __('Found') }}</span>
                            @else
                                <span class="seo-badge seo-badge-fail"><i class="ti ti-x"></i> {{ __('Not Found') }}</span>
                            @endif
                        </span>
                    </div>
                    <div class="seo-check-row">
                        <span>{{ __('References Sitemap') }}</span>
                        <span>
                            @if($rb['has_sitemap'] ?? false)
                                <span class="seo-badge seo-badge-pass">{{ __('Yes') }}</span>
                            @else
                                <span class="seo-badge seo-badge-warn">{{ __('No') }}</span>
                            @endif
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Schema --}}
        <div class="col-xl-4 col-md-12 seo-section">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0"><i class="ti ti-code me-1"></i> {{ __('Structured Data') }}</h5></div>
                <div class="card-body">
                    @php $sch = $data['schema'] ?? []; @endphp
                    <div class="seo-check-row">
                        <span>{{ __('JSON-LD Blocks') }}</span>
                        <span><strong>{{ $sch['count'] ?? 0 }}</strong></span>
                    </div>
                    @if(!empty($sch['types']))
                        <div class="mt-2">
                            @foreach ($sch['types'] as $type)
                                <span class="badge bg-light-info mb-1">{{ is_array($type) ? json_encode($type) : $type }}</span>
                            @endforeach
                        </div>
                    @else
                        <p class="text-muted small mt-2 mb-0">{{ __('No structured data detected. Consider adding Schema.org markup.') }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        {{-- Headings --}}
        <div class="col-xl-4 col-md-6 seo-section">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0"><i class="ti ti-heading me-1"></i> {{ __('Headings') }}</h5></div>
                <div class="card-body p-0">
                    @php $hd = $data['headings'] ?? []; @endphp
                    <div class="table-responsive">
                        <table class="table mb-0" style="font-size:13px">
                            <thead><tr><th>{{ __('Tag') }}</th><th>{{ __('Count') }}</th><th>{{ __('Content') }}</th></tr></thead>
                            <tbody>
                                @for ($i = 1; $i <= 6; $i++)
                                    @php $items = $hd['h'.$i] ?? []; @endphp
                                    @if(count($items) > 0)
                                        @foreach ($items as $idx => $text)
                                            <tr>
                                                @if($idx === 0)
                                                    <td rowspan="{{ count($items) }}"><span class="badge bg-{{ $i === 1 ? 'primary' : 'secondary' }}">H{{ $i }}</span></td>
                                                    <td rowspan="{{ count($items) }}">{{ count($items) }}</td>
                                                @endif
                                                <td>{{ \Illuminate\Support\Str::limit($text, 60) }}</td>
                                            </tr>
                                        @endforeach
                                    @endif
                                @endfor
                                @if(($hd['total'] ?? 0) === 0)
                                    <tr><td colspan="3" class="text-center text-muted py-3">{{ __('No headings found') }}</td></tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Links --}}
        <div class="col-xl-4 col-md-6 seo-section">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0"><i class="ti ti-link me-1"></i> {{ __('Links') }}</h5></div>
                <div class="card-body">
                    @php $lk = $data['links'] ?? []; @endphp
                    <div class="seo-check-row"><span>{{ __('Total Links') }}</span><span><strong>{{ $lk['total'] ?? 0 }}</strong></span></div>
                    <div class="seo-check-row"><span>{{ __('Internal') }}</span><span><span class="badge bg-primary">{{ $lk['internal'] ?? 0 }}</span></span></div>
                    <div class="seo-check-row"><span>{{ __('External') }}</span><span><span class="badge bg-info">{{ $lk['external'] ?? 0 }}</span></span></div>
                    <div class="seo-check-row"><span>{{ __('Nofollow') }}</span><span><span class="badge bg-secondary">{{ $lk['nofollow'] ?? 0 }}</span></span></div>
                    <div class="seo-check-row">
                        <span>{{ __('Empty/Broken') }}</span>
                        <span>
                            @if(($lk['empty'] ?? 0) > 0)
                                <span class="badge bg-warning">{{ $lk['empty'] }}</span>
                            @else
                                <span class="seo-badge seo-badge-pass">0</span>
                            @endif
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Images --}}
        <div class="col-xl-4 col-md-12 seo-section">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0"><i class="ti ti-photo me-1"></i> {{ __('Images') }}</h5></div>
                <div class="card-body">
                    @php $img = $data['images'] ?? []; @endphp
                    <div class="seo-check-row"><span>{{ __('Total Images') }}</span><span><strong>{{ $img['total'] ?? 0 }}</strong></span></div>
                    <div class="seo-check-row">
                        <span>{{ __('Missing Alt Text') }}</span>
                        <span>
                            @if(($img['without_alt'] ?? 0) > 0)
                                <span class="badge bg-warning">{{ $img['without_alt'] }}</span>
                            @else
                                <span class="seo-badge seo-badge-pass">0</span>
                            @endif
                        </span>
                    </div>
                    <div class="seo-check-row">
                        <span>{{ __('Lazy Loaded') }}</span>
                        <span>
                            @if(($img['lazy_loaded'] ?? 0) > 0)
                                <span class="seo-badge seo-badge-pass">{{ $img['lazy_loaded'] }}</span>
                            @else
                                <span class="seo-badge seo-badge-warn">0</span>
                            @endif
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Open Graph & Twitter Preview --}}
    <div class="row seo-section">
        <div class="col-xl-6 col-md-12">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0"><i class="ti ti-brand-facebook me-1"></i> {{ __('Open Graph Tags') }}</h5></div>
                <div class="card-body">
                    @php $m = $data['meta'] ?? []; @endphp
                    <div class="seo-check-row"><span>og:title</span><span><code>{{ $m['og_title'] ?? __('Not set') }}</code></span></div>
                    <div class="seo-check-row"><span>og:description</span><span style="max-width:300px;text-align:right"><code style="font-size:11px">{{ \Illuminate\Support\Str::limit($m['og_description'] ?? __('Not set'), 80) }}</code></span></div>
                    <div class="seo-check-row"><span>og:image</span><span>
                        @if(!empty($m['og_image']))
                            <span class="seo-badge seo-badge-pass">{{ __('Set') }}</span>
                        @else
                            <span class="seo-badge seo-badge-fail">{{ __('Not set') }}</span>
                        @endif
                    </span></div>
                    <div class="seo-check-row"><span>og:type</span><span><code>{{ $m['og_type'] ?? __('Not set') }}</code></span></div>
                    <div class="seo-check-row"><span>og:url</span><span><code style="font-size:11px">{{ $m['og_url'] ?? __('Not set') }}</code></span></div>
                </div>
            </div>
        </div>
        <div class="col-xl-6 col-md-12">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0"><i class="ti ti-brand-twitter me-1"></i> {{ __('Twitter Card Tags') }}</h5></div>
                <div class="card-body">
                    <div class="seo-check-row"><span>twitter:card</span><span><code>{{ $m['tw_card'] ?? __('Not set') }}</code></span></div>
                    <div class="seo-check-row"><span>twitter:title</span><span><code>{{ $m['tw_title'] ?? __('Not set') }}</code></span></div>
                    <div class="seo-check-row"><span>twitter:description</span><span style="max-width:300px;text-align:right"><code style="font-size:11px">{{ \Illuminate\Support\Str::limit($m['tw_description'] ?? __('Not set'), 80) }}</code></span></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Robots.txt Content --}}
    @if(!empty($data['robots']['content']))
    <div class="row seo-section">
        <div class="col-12">
            <div class="card">
                <div class="card-header"><h5 class="mb-0"><i class="ti ti-robot me-1"></i> {{ __('Robots.txt Content') }}</h5></div>
                <div class="card-body">
                    <pre class="bg-dark text-light p-3 rounded" style="font-size:12px;max-height:300px;overflow:auto">{{ $data['robots']['content'] }}</pre>
                </div>
            </div>
        </div>
    </div>
    @endif

    @endif
@endsection
