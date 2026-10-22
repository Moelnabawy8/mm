@extends('layouts.admin')

@section('page-title'){{ __('Activity Dashboard') }}@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('Activity Tracking') }}</li>
    <li class="breadcrumb-item">{{ __('Dashboard') }}</li>
@endsection

@push('css-page')
<style>
    .ad-card { background:#fff; border:1px solid #e4eaf2; border-radius:12px; padding:18px 20px; height:100%; }
    .ad-num { font-size:28px; font-weight:700; color:#0f1f36; }
    .ad-label { color:#5b6b82; font-size:13px; }
    .ad-row-item { display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid #f0f3f7; font-size:13px; }
    .ad-row-item:last-child { border-bottom:none; }
    .ad-stars { color:#f5b400; letter-spacing:1px; }
    .ad-pill { padding:3px 10px; border-radius:999px; font-size:11px; font-weight:600; }
    .ad-pill-low    { background:#e3f2fd; color:#1565c0; }
    .ad-pill-medium { background:#fff4e5; color:#ed6c02; }
    .ad-pill-high   { background:#fdecea; color:#d32f2f; }
</style>
@endpush

@section('content')
<div class="row gy-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="ad-card">
            <div class="ad-label"><i class="ti ti-trending-up"></i> {{ __('Top performer (avg)') }}</div>
            <div class="ad-num" id="adTopName">—</div>
            <div class="ad-stars" id="adTopStars">—</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="ad-card">
            <div class="ad-label"><i class="ti ti-clock-x"></i> {{ __('Highest idle ratio') }}</div>
            <div class="ad-num" id="adIdleName">—</div>
            <div class="text-muted small" id="adIdleRatio">—</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="ad-card">
            <div class="ad-label"><i class="ti ti-hourglass"></i> {{ __('Avg task time (30d)') }}</div>
            <div class="ad-num" id="adAvgTaskHrs">—</div>
            <div class="text-muted small">{{ __('hours per completed task') }}</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="ad-card">
            <div class="ad-label"><i class="ti ti-alert-triangle"></i> {{ __('Active bottlenecks') }}</div>
            <div class="ad-num" id="adBottleneckTotal">—</div>
            <div class="text-muted small" id="adBottleneckBreakdown">—</div>
        </div>
    </div>
</div>

<div class="row gy-3">
    <div class="col-lg-6">
        <div class="ad-card">
            <h6 class="mb-3"><i class="ti ti-trophy"></i> {{ __('Top 10 productive employees (last 30 days)') }}</h6>
            <div id="adTopList"><p class="text-muted small">{{ __('Loading...') }}</p></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="ad-card">
            <h6 class="mb-3"><i class="ti ti-clock-pause"></i> {{ __('High-idle employees') }}</h6>
            <div id="adIdleList"><p class="text-muted small">{{ __('Loading...') }}</p></div>
        </div>
    </div>
    <div class="col-12">
        <div class="ad-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0"><i class="ti ti-alert-octagon"></i> {{ __('Bottleneck alerts') }}</h6>
                <button id="adBottleneckRun" class="btn btn-sm btn-outline-primary">
                    <i class="ti ti-refresh"></i> {{ __('Run detection now') }}
                </button>
            </div>
            <div id="adBottleneckList"><p class="text-muted small">{{ __('Loading...') }}</p></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function() {
    'use strict';
    const ROUTES = {
        metrics: "{{ route('activity.metrics') }}",
        bottlenecks: "{{ route('activity.bottlenecks') }}",
        bottlenecksRun: "{{ route('activity.bottlenecks.run') }}",
    };
    const csrf = '{{ csrf_token() }}';
    const fmtPct = (n) => (Number(n) * 100).toFixed(1) + '%';
    const stars = (n) => '★'.repeat(n) + '☆'.repeat(Math.max(0, 5 - n));
    const $ = (id) => document.getElementById(id);

    function setText(id, txt) { const el = $(id); if (el) el.textContent = txt; }

    function placeholder(text) {
        const p = document.createElement('p');
        p.className = 'text-muted small';
        p.textContent = text;
        return p;
    }

    function buildRow(leftEl, rightEl) {
        const row = document.createElement('div');
        row.className = 'ad-row-item';
        row.appendChild(leftEl);
        row.appendChild(rightEl);
        return row;
    }

    function renderTopList(items) {
        const wrap = $('adTopList');
        wrap.replaceChildren();
        if (!items || !items.length) { wrap.appendChild(placeholder('No data yet — run scheduler.')); return; }
        items.forEach((it, i) => {
            const left = document.createElement('div');
            const rank = document.createElement('strong'); rank.textContent = '#' + (i + 1);
            left.appendChild(rank);
            left.appendChild(document.createTextNode(' ' + (it.name || ('User ' + it.user_id))));

            const right = document.createElement('div');
            const starsSpan = document.createElement('span');
            starsSpan.className = 'ad-stars'; starsSpan.textContent = stars(it.stars);
            const sep = document.createTextNode(' · ');
            const score = document.createElement('span');
            score.className = 'text-muted small'; score.textContent = (Number(it.score) * 100).toFixed(0) + '%';
            right.appendChild(starsSpan); right.appendChild(sep); right.appendChild(score);

            wrap.appendChild(buildRow(left, right));
        });
    }

    function renderIdleList(items) {
        const wrap = $('adIdleList');
        wrap.replaceChildren();
        if (!items || !items.length) { wrap.appendChild(placeholder('No data yet.')); return; }
        items.forEach(it => {
            const left = document.createElement('div');
            left.textContent = it.name;
            const right = document.createElement('div');
            const pct = document.createElement('span');
            pct.className = 'text-danger fw-bold'; pct.textContent = fmtPct(it.idle_ratio);
            right.appendChild(pct);
            right.appendChild(document.createTextNode(' '));
            const hrs = document.createElement('span');
            hrs.className = 'text-muted small'; hrs.textContent = '(' + it.idle_hours + 'h)';
            right.appendChild(hrs);
            wrap.appendChild(buildRow(left, right));
        });
    }

    function renderBottleneckList(alerts) {
        const wrap = $('adBottleneckList');
        wrap.replaceChildren();
        if (!alerts || !alerts.length) {
            const ok = document.createElement('p');
            ok.className = 'text-success small';
            ok.textContent = '✓ No active bottlenecks';
            wrap.appendChild(ok);
            return;
        }
        alerts.forEach(a => {
            const left = document.createElement('div');
            const pill = document.createElement('span');
            pill.className = 'ad-pill ad-pill-' + (a.severity || 'low');
            pill.textContent = (a.severity || 'low').toUpperCase();
            const text = document.createElement('span');
            text.style.marginLeft = '10px';
            text.textContent = a.message;
            left.appendChild(pill);
            left.appendChild(text);

            const right = document.createElement('span');
            right.className = 'text-muted small';
            right.textContent = a.type;

            wrap.appendChild(buildRow(left, right));
        });
    }

    async function loadMetrics() {
        const r = await fetch(ROUTES.metrics, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
        const d = await r.json();
        if (!d.enabled) {
            document.querySelectorAll('.ad-num').forEach(n => n.textContent = '—');
            return;
        }
        if (d.top_productive && d.top_productive[0]) {
            setText('adTopName', d.top_productive[0].name);
            $('adTopStars').textContent = stars(d.top_productive[0].stars);
        }
        if (d.high_idle && d.high_idle[0]) {
            setText('adIdleName', d.high_idle[0].name);
            setText('adIdleRatio', fmtPct(d.high_idle[0].idle_ratio));
        }
        setText('adAvgTaskHrs', d.avg_task_hours !== null && d.avg_task_hours !== undefined ? d.avg_task_hours + ' h' : '—');
        setText('adBottleneckTotal', d.bottleneck_count);
        const sev = d.bottleneck_by_severity || {};
        setText('adBottleneckBreakdown',
            (sev.high || 0) + ' high · ' + (sev.medium || 0) + ' med · ' + (sev.low || 0) + ' low');
        renderTopList(d.top_productive);
        renderIdleList(d.high_idle);
    }

    async function loadBottlenecks() {
        const r = await fetch(ROUTES.bottlenecks, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
        const d = await r.json();
        renderBottleneckList(d.alerts || []);
    }

    $('adBottleneckRun').addEventListener('click', async function() {
        this.disabled = true;
        this.textContent = 'Running...';
        await fetch(ROUTES.bottlenecksRun, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
        });
        await loadMetrics();
        await loadBottlenecks();
        this.disabled = false;
        // Use simple text — avoids innerHTML
        this.textContent = 'Run detection now';
    });

    loadMetrics();
    loadBottlenecks();
})();
</script>
@endpush
