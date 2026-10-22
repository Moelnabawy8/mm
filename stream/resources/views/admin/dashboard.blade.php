@extends('layouts.admin')
@section('page-title', 'Dashboard')
@section('page-subtitle', 'Overview of your video platform')

@section('content')
<!-- Stats Grid -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
    <div class="stat-card rounded-xl p-5 transition-all">
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 bg-indigo-500/20 rounded-lg flex items-center justify-center">
                <i class="fas fa-video text-indigo-400"></i>
            </div>
            <span class="text-2xl font-bold text-white">{{ $stats['total_videos'] }}</span>
        </div>
        <p class="text-xs text-gray-400">Total Videos</p>
    </div>
    <div class="stat-card rounded-xl p-5 transition-all">
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 bg-emerald-500/20 rounded-lg flex items-center justify-center">
                <i class="fas fa-satellite-dish text-emerald-400"></i>
            </div>
            <span class="text-2xl font-bold text-white">{{ $stats['active_sessions'] }}</span>
        </div>
        <p class="text-xs text-gray-400">Active Sessions</p>
    </div>
    <div class="stat-card rounded-xl p-5 transition-all">
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 bg-blue-500/20 rounded-lg flex items-center justify-center">
                <i class="fas fa-eye text-blue-400"></i>
            </div>
            <span class="text-2xl font-bold text-white">{{ $stats['views_today'] }}</span>
        </div>
        <p class="text-xs text-gray-400">Views Today</p>
    </div>
    <div class="stat-card rounded-xl p-5 transition-all">
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 bg-red-500/20 rounded-lg flex items-center justify-center">
                <i class="fas fa-shield-alt text-red-400"></i>
            </div>
            <span class="text-2xl font-bold text-white">{{ $stats['blocked_access'] }}</span>
        </div>
        <p class="text-xs text-gray-400">Blocked Attempts</p>
    </div>
</div>

<!-- Secondary Stats -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
    <div class="card rounded-xl p-4">
        <div class="flex items-center gap-2">
            <span class="w-2 h-2 bg-emerald-400 rounded-full"></span>
            <span class="text-xs text-gray-400">Ready</span>
        </div>
        <p class="text-lg font-semibold text-white mt-1">{{ $stats['videos_ready'] }}</p>
    </div>
    <div class="card rounded-xl p-4">
        <div class="flex items-center gap-2">
            <span class="w-2 h-2 bg-yellow-400 rounded-full animate-pulse"></span>
            <span class="text-xs text-gray-400">Processing</span>
        </div>
        <p class="text-lg font-semibold text-white mt-1">{{ $stats['videos_processing'] }}</p>
    </div>
    <div class="card rounded-xl p-4">
        <div class="flex items-center gap-2">
            <span class="w-2 h-2 bg-red-400 rounded-full"></span>
            <span class="text-xs text-gray-400">Failed</span>
        </div>
        <p class="text-lg font-semibold text-white mt-1">{{ $stats['videos_failed'] }}</p>
    </div>
    <div class="card rounded-xl p-4">
        <div class="flex items-center gap-2">
            <span class="w-2 h-2 bg-blue-400 rounded-full"></span>
            <span class="text-xs text-gray-400">Frozen</span>
        </div>
        <p class="text-lg font-semibold text-white mt-1">{{ $stats['frozen_videos'] }}</p>
    </div>
</div>

<!-- Charts Row -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <div class="card rounded-xl p-6">
        <h3 class="text-sm font-semibold text-white mb-4"><i class="fas fa-chart-area text-indigo-400 mr-2"></i>Views (Last 14 Days)</h3>
        <canvas id="viewsChart" height="200"></canvas>
    </div>
    <div class="card rounded-xl p-6">
        <h3 class="text-sm font-semibold text-white mb-4"><i class="fas fa-globe text-purple-400 mr-2"></i>Country Distribution</h3>
        @if($countryData->count() > 0)
            <canvas id="countryChart" height="200"></canvas>
        @else
            <div class="flex items-center justify-center h-48 text-gray-500 text-sm">
                <div class="text-center">
                    <i class="fas fa-globe text-3xl mb-2"></i>
                    <p>No geographic data yet</p>
                </div>
            </div>
        @endif
    </div>
</div>

<!-- Recent Videos + Recent Activity -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="card rounded-xl p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-white"><i class="fas fa-film text-indigo-400 mr-2"></i>Recent Videos</h3>
            <a href="{{ route('admin.videos.index') }}" class="text-xs text-indigo-400 hover:text-indigo-300">View All →</a>
        </div>
        @forelse($recentVideos as $video)
        <a href="{{ route('admin.videos.show', $video) }}" class="flex items-center gap-3 py-3 border-b border-gray-800/50 last:border-0 hover:bg-white/5 rounded-lg px-2 -mx-2 transition-all">
            <div class="w-10 h-10 bg-gray-800 rounded-lg flex items-center justify-center flex-shrink-0">
                <i class="fas fa-play text-indigo-400 text-xs"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm text-white truncate">{{ $video->title }}</p>
                <p class="text-xs text-gray-500">{{ $video->status }} • {{ $video->created_at->diffForHumans() }}</p>
            </div>
            @if($video->frozen)
                <span class="text-xs bg-red-900/30 text-red-400 px-2 py-0.5 rounded-full">Frozen</span>
            @elseif($video->active_sessions_count > 0)
                <span class="text-xs bg-emerald-900/30 text-emerald-400 px-2 py-0.5 rounded-full">{{ $video->active_sessions_count }} live</span>
            @endif
        </a>
        @empty
        <div class="text-center text-gray-500 py-8">
            <i class="fas fa-video text-3xl mb-2"></i>
            <p class="text-sm">No videos yet</p>
        </div>
        @endforelse
    </div>

    <div class="card rounded-xl p-6">
        <h3 class="text-sm font-semibold text-white mb-4"><i class="fas fa-stream text-purple-400 mr-2"></i>Recent Activity</h3>
        <div class="space-y-0">
            @forelse($recentLogs as $log)
            <div class="flex items-center gap-3 py-2 border-b border-gray-800/30 last:border-0">
                <div class="w-2 h-2 rounded-full flex-shrink-0 {{ $log->blocked ? 'bg-red-400' : 'bg-emerald-400' }}"></div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs text-gray-300 truncate">
                        <span class="font-mono text-gray-500">{{ $log->ip_address }}</span>
                        <span class="text-gray-600 mx-1">→</span>
                        {{ $log->video?->title ?? 'Deleted' }}
                    </p>
                </div>
                <span class="text-[10px] text-gray-600 flex-shrink-0">{{ $log->created_at->diffForHumans() }}</span>
            </div>
            @empty
            <div class="text-center text-gray-500 py-8">
                <i class="fas fa-stream text-3xl mb-2"></i>
                <p class="text-sm">No activity yet</p>
            </div>
            @endforelse
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const chartDefaults = {
    color: '#64748b',
    borderColor: '#1e293b',
    font: { family: '-apple-system, BlinkMacSystemFont, sans-serif' }
};
Chart.defaults.color = chartDefaults.color;

// Views Chart
const viewsData = @json($dailyViews);
new Chart(document.getElementById('viewsChart'), {
    type: 'line',
    data: {
        labels: viewsData.map(d => d.date),
        datasets: [{
            label: 'Views',
            data: viewsData.map(d => d.views),
            borderColor: '#6366f1',
            backgroundColor: 'rgba(99, 102, 241, 0.1)',
            fill: true,
            tension: 0.4,
            pointRadius: 3,
            pointBackgroundColor: '#6366f1',
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { color: '#1e293b' }, ticks: { font: { size: 10 } } },
            y: { grid: { color: '#1e293b' }, beginAtZero: true, ticks: { font: { size: 10 } } }
        }
    }
});

// Country Chart
@if($countryData->count() > 0)
const countryData = @json($countryData);
const colors = ['#6366f1', '#8b5cf6', '#a855f7', '#d946ef', '#ec4899', '#f43f5e', '#f97316', '#eab308', '#22c55e', '#14b8a6'];
new Chart(document.getElementById('countryChart'), {
    type: 'doughnut',
    data: {
        labels: countryData.map(d => d.country),
        datasets: [{
            data: countryData.map(d => d.total),
            backgroundColor: colors,
            borderColor: '#111827',
            borderWidth: 2,
        }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 8, font: { size: 10 } } } } }
});
@endif
</script>
@endpush
