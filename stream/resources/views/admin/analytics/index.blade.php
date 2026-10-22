@extends('layouts.admin')

@section('title', 'Access Logs')
@section('page-title', 'Access Logs & Analytics')

@section('content')
<!-- Stats -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <p class="text-sm text-gray-500 mb-1">Total Access</p>
        <p class="text-2xl font-bold text-gray-800">{{ number_format($totalAccess) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <p class="text-sm text-gray-500 mb-1">Blocked Attempts</p>
        <p class="text-2xl font-bold text-red-600">{{ number_format($blockedAccess) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <p class="text-sm text-gray-500 mb-1">Unique IPs</p>
        <p class="text-2xl font-bold text-gray-800">{{ number_format($uniqueIps) }}</p>
    </div>
</div>

<!-- Filter -->
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-8">
    <form method="GET" class="flex items-center gap-4">
        <select name="video_id" class="border border-gray-300 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none">
            <option value="">All Videos</option>
            @foreach($videos as $video)
                <option value="{{ $video->id }}" {{ $videoId == $video->id ? 'selected' : '' }}>{{ $video->title }}</option>
            @endforeach
        </select>
        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">Filter</button>
        @if($videoId)
            <a href="{{ route('admin.analytics.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Clear</a>
        @endif
    </form>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
    <!-- Top Videos by Access -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="font-semibold text-gray-800">Top Videos by Access</h3>
        </div>
        <div class="divide-y divide-gray-100">
            @forelse($topVideos as $item)
                <div class="px-6 py-4 flex items-center gap-4">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-800 truncate">{{ $item->video->title ?? 'Deleted' }}</p>
                        <p class="text-xs text-gray-500">{{ $item->access_count }} accesses &middot; {{ $item->blocked_count }} blocked</p>
                    </div>
                </div>
            @empty
                <div class="px-6 py-8 text-center text-gray-400 text-sm">No data yet</div>
            @endforelse
        </div>
    </div>

    <!-- Suspicious Activity -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="font-semibold text-gray-800 flex items-center gap-2">
                <i class="fas fa-exclamation-triangle text-amber-500"></i>
                Suspicious Activity
            </h3>
        </div>
        <div class="divide-y divide-gray-100">
            @forelse($suspiciousActivity as $alert)
                <div class="px-6 py-4">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 bg-amber-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-shield-alt text-amber-600 text-xs"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-800 font-mono">{{ $alert['ip'] }}</p>
                            <p class="text-xs text-gray-500">{{ $alert['type'] }}: {{ $alert['detail'] }}</p>
                        </div>
                    </div>
                </div>
            @empty
                <div class="px-6 py-8 text-center text-green-500 text-sm">
                    <i class="fas fa-check-circle text-2xl mb-2"></i>
                    <p>No suspicious activity detected</p>
                </div>
            @endforelse
        </div>
    </div>
</div>

<!-- Recent Access Logs -->
<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="font-semibold text-gray-800">Recent Access Logs</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Video</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">IP Address</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Referer</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($recentLogs as $log)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-3 text-sm text-gray-800 max-w-[200px] truncate">{{ $log->video->title ?? 'Deleted' }}</td>
                        <td class="px-6 py-3 text-sm text-gray-500 font-mono">{{ $log->ip_address }}</td>
                        <td class="px-6 py-3 text-sm text-gray-500 max-w-[200px] truncate">{{ $log->referer ?? '—' }}</td>
                        <td class="px-6 py-3">
                            @if($log->blocked)
                                <span class="px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-700 font-medium">Blocked</span>
                            @else
                                <span class="px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-700 font-medium">Allowed</span>
                            @endif
                        </td>
                        <td class="px-6 py-3 text-sm text-gray-500">{{ $log->created_at->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-6 py-8 text-center text-gray-400">No access logs recorded</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="px-6 py-4 border-t border-gray-200">{{ $recentLogs->links() }}</div>
</div>
@endsection
