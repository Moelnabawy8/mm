@extends('layouts.admin')

@section('page-title'){{ __('My Task Timer') }}@endsection

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('Activity Tracking') }}</li>
    <li class="breadcrumb-item">{{ __('My Timer') }}</li>
@endsection

@push('css-page')
<style>
    .at-wrap { --at-primary:#1976d2; }
    .at-clock { font-family: 'Courier New', monospace; font-size:48px; font-weight:700; letter-spacing:2px; color:var(--at-primary); }
    .at-clock.paused { color:#ed6c02; }
    .at-status-pill { display:inline-flex; align-items:center; gap:6px; padding:4px 12px; border-radius:999px; font-size:12px; font-weight:600; }
    .at-status-active  { background:#e8f5e9; color:#2e7d32; }
    .at-status-paused  { background:#fff4e5; color:#ed6c02; }
    .at-status-idle    { background:#fdecea; color:#d32f2f; }
    .at-pulse { width:8px; height:8px; border-radius:50%; background:currentColor; animation: at-pulse 1.5s ease-in-out infinite; }
    @@keyframes at-pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.5;transform:scale(1.4)} }
    .at-task-card { padding:14px 18px; border:1px solid #e4eaf2; border-radius:10px; cursor:pointer; transition:all .15s; }
    .at-task-card:hover { border-color:var(--at-primary); box-shadow:0 4px 12px rgba(25,118,210,.08); }
    .at-task-card.selected { border-color:var(--at-primary); background:#e3f2fd; }
    .at-priority-high   { color:#d32f2f; }
    .at-priority-medium { color:#ed6c02; }
    .at-priority-low    { color:#2e7d32; }
</style>
@endpush

@section('content')
<div class="at-wrap">
    {{-- Active timer card --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <div>
                            <h5 class="mb-1">{{ __('Current Timer') }}</h5>
                            <div id="atCurrentTaskName" class="text-muted">
                                @if($current && $current->task)
                                    {{ $current->task->name }}
                                @else
                                    {{ __('No active timer') }}
                                @endif
                            </div>
                        </div>
                        <div id="atClock" class="at-clock {{ $current && $current->status === 'paused' ? 'paused' : '' }}">
                            00:00:00
                        </div>
                        <div>
                            <span id="atStatusPill" class="at-status-pill at-status-idle">
                                <span class="at-pulse"></span>
                                <span id="atStatusText">{{ $current ? ucfirst($current->status) : __('Idle') }}</span>
                            </span>
                        </div>
                    </div>
                    <div class="mt-3 d-flex gap-2 flex-wrap">
                        <button id="atBtnPause"  type="button" class="btn btn-warning" {{ !$current || $current->status !== 'active' ? 'disabled' : '' }}>
                            <i class="ti ti-player-pause"></i> {{ __('Pause') }}
                        </button>
                        <button id="atBtnResume" type="button" class="btn btn-success" {{ !$current || $current->status !== 'paused' ? 'disabled' : '' }}>
                            <i class="ti ti-player-play"></i> {{ __('Resume') }}
                        </button>
                        <button id="atBtnStop"   type="button" class="btn btn-danger" {{ !$current ? 'disabled' : '' }}>
                            <i class="ti ti-player-stop"></i> {{ __('Stop') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Available tasks --}}
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('My Tasks') }} <small class="text-muted">— {{ count($myTasks) }} {{ __('open') }}</small></h5>
                </div>
                <div class="card-body">
                    @if($myTasks->isEmpty())
                        <p class="text-muted text-center py-4">{{ __('No open tasks assigned to you.') }}</p>
                    @else
                        <div class="row gy-3">
                            @foreach($myTasks as $task)
                                <div class="col-md-6 col-lg-4">
                                    <div class="at-task-card" data-task-id="{{ $task->id }}" data-task-name="{{ $task->name }}">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <strong>{{ $task->name }}</strong>
                                            @if($task->priority)
                                                <span class="at-priority-{{ strtolower($task->priority) }}">
                                                    <i class="ti ti-flag-filled"></i> {{ ucfirst($task->priority) }}
                                                </span>
                                            @endif
                                        </div>
                                        <div class="text-muted small">
                                            @if($task->end_date)
                                                <i class="ti ti-calendar"></i> {{ __('Due') }}: {{ \Carbon\Carbon::parse($task->end_date)->format('M d, Y') }}
                                            @endif
                                            @if($task->estimated_hrs)
                                                · <i class="ti ti-clock"></i> {{ $task->estimated_hrs }}h {{ __('est') }}
                                            @endif
                                        </div>
                                        <button class="btn btn-sm btn-primary mt-3 at-start-btn">
                                            <i class="ti ti-player-play"></i> {{ __('Start Timer') }}
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function() {
    'use strict';
    const csrf = '{{ csrf_token() }}';
    const ROUTES = {
        start:     "{{ route('activity.start') }}",
        stop:      "{{ route('activity.stop') }}",
        pause:     "{{ route('activity.pause') }}",
        resume:    "{{ route('activity.resume') }}",
        heartbeat: "{{ route('activity.heartbeat') }}",
        current:   "{{ route('activity.current') }}",
    };
    const HEARTBEAT_MS = 60 * 1000;

    let state = {
        active: {!! $current ? 'true' : 'false' !!},
        status: {!! $current ? json_encode($current->status) : 'null' !!},
        startedAt: {!! $current ? json_encode($current->start_time->toIso8601String()) : 'null' !!},
        activeSec: {!! $current ? (int) $current->active_seconds : 0 !!},
        clockHandle: null,
        heartbeatHandle: null,
        userActiveSinceLastBeat: true,
    };

    const $ = (id) => document.getElementById(id);

    function fmt(sec) {
        const h = Math.floor(sec / 3600).toString().padStart(2, '0');
        const m = Math.floor((sec % 3600) / 60).toString().padStart(2, '0');
        const s = Math.floor(sec % 60).toString().padStart(2, '0');
        return `${h}:${m}:${s}`;
    }

    function renderClock() {
        $('atClock').textContent = fmt(state.activeSec);
        $('atClock').classList.toggle('paused', state.status === 'paused');
        const pill = $('atStatusPill');
        pill.classList.remove('at-status-active', 'at-status-paused', 'at-status-idle');
        if (state.status === 'active') pill.classList.add('at-status-active');
        else if (state.status === 'paused') pill.classList.add('at-status-paused');
        else pill.classList.add('at-status-idle');
        $('atStatusText').textContent = state.active ? state.status.charAt(0).toUpperCase() + state.status.slice(1) : 'Idle';
        $('atBtnPause').disabled  = !(state.active && state.status === 'active');
        $('atBtnResume').disabled = !(state.active && state.status === 'paused');
        $('atBtnStop').disabled   = !state.active;
    }

    function tick() { if (state.status === 'active') { state.activeSec += 1; renderClock(); } }

    async function post(url, body = {}) {
        body._token = csrf;
        const res = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: new URLSearchParams(body).toString(),
        });
        return res.json();
    }

    async function startForTask(taskId, taskName) {
        const r = await post(ROUTES.start, { task_id: taskId });
        if (!r.ok) { alert('Failed to start timer'); return; }
        state.active = true; state.status = 'active'; state.activeSec = 0;
        $('atCurrentTaskName').textContent = taskName;
        renderClock();
    }

    document.querySelectorAll('.at-start-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const card = this.closest('.at-task-card');
            const taskId = card.getAttribute('data-task-id');
            const taskName = card.getAttribute('data-task-name');
            if (state.active && !confirm('A timer is already running. Stop it and start a new one?')) return;
            startForTask(taskId, taskName);
        });
    });

    $('atBtnPause').addEventListener('click', async () => {
        const r = await post(ROUTES.pause); if (r.ok) { state.status = 'paused'; renderClock(); }
    });
    $('atBtnResume').addEventListener('click', async () => {
        const r = await post(ROUTES.resume); if (r.ok) { state.status = 'active'; renderClock(); }
    });
    $('atBtnStop').addEventListener('click', async () => {
        if (!confirm('Stop the current timer?')) return;
        const r = await post(ROUTES.stop);
        if (r.ok) { state.active = false; state.status = null; state.activeSec = 0; $('atCurrentTaskName').textContent = 'No active timer'; renderClock(); }
    });

    // User-activity tracking → "userActiveSinceLastBeat" gate
    ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'].forEach(ev => {
        document.addEventListener(ev, () => { state.userActiveSinceLastBeat = true; }, { passive: true });
    });

    async function heartbeat() {
        if (!state.active || state.status !== 'active') return;
        if (!state.userActiveSinceLastBeat) return; // skip → server will auto-pause if it persists
        state.userActiveSinceLastBeat = false;
        const r = await post(ROUTES.heartbeat);
        if (r.ok) { state.activeSec = r.active_seconds; renderClock(); }
    }

    state.clockHandle = setInterval(tick, 1000);
    state.heartbeatHandle = setInterval(heartbeat, HEARTBEAT_MS);
    renderClock();
})();
</script>
@endpush
