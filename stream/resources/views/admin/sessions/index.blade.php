@extends('layouts.admin')
@section('page-title', 'Active Sessions')
@section('page-subtitle', 'Monitor and manage streaming sessions')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Active Sessions -->
    <div class="lg:col-span-2">
        <div class="card rounded-xl overflow-hidden">
            <div class="p-4 border-b border-gray-800 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-white"><i class="fas fa-satellite-dish text-emerald-400 mr-2"></i>Active Sessions ({{ $sessions->total() }})</h3>
            </div>
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-800">
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Video</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">IP</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Country</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Duration</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sessions as $session)
                    <tr class="border-b border-gray-800/50">
                        <td class="px-4 py-3 text-sm text-gray-300 truncate max-w-[200px]">{{ $session->video?->title ?? 'Deleted' }}</td>
                        <td class="px-4 py-3 text-xs text-gray-300 font-mono">{{ $session->ip_address }}</td>
                        <td class="px-4 py-3 text-xs text-gray-400">{{ $session->country ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs text-gray-400">{{ $session->started_at->diffForHumans() }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <form method="POST" action="{{ route('admin.sessions.terminate', $session) }}">
                                    @csrf
                                    <button class="text-xs bg-red-900/30 text-red-400 px-2 py-1 rounded hover:bg-red-900/50"><i class="fas fa-times mr-1"></i>Kill</button>
                                </form>
                                <form method="POST" action="{{ route('admin.sessions.block-ip') }}">
                                    @csrf
                                    <input type="hidden" name="ip_address" value="{{ $session->ip_address }}">
                                    <button class="text-xs bg-orange-900/30 text-orange-400 px-2 py-1 rounded hover:bg-orange-900/50"><i class="fas fa-ban mr-1"></i>Block</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4 py-12 text-center text-gray-500">
                            <i class="fas fa-satellite-dish text-3xl mb-3 block"></i>
                            <p class="text-sm">No active sessions</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            <div class="p-4">{{ $sessions->links() }}</div>
        </div>
    </div>

    <!-- Blocked Lists -->
    <div class="space-y-6">
        <div class="card rounded-xl p-6">
            <h3 class="text-sm font-semibold text-white mb-4"><i class="fas fa-ban text-red-400 mr-2"></i>Blocked IPs ({{ $blockedIps->count() }})</h3>
            <form method="POST" action="{{ route('admin.sessions.block-ip') }}" class="flex gap-2 mb-4">
                @csrf
                <input type="text" name="ip_address" placeholder="IP address" class="flex-1 rounded-lg px-3 py-2 text-sm border outline-none">
                <button class="btn-danger text-white text-xs px-3 py-2 rounded-lg">Block</button>
            </form>
            <div class="space-y-2 max-h-64 overflow-y-auto">
                @forelse($blockedIps as $blocked)
                <div class="flex items-center justify-between bg-gray-800/50 rounded-lg px-3 py-2">
                    <span class="text-xs text-gray-300 font-mono">{{ $blocked->ip_address }}</span>
                    <form method="POST" action="{{ route('admin.sessions.unblock-ip', $blocked) }}">
                        @csrf @method('DELETE')
                        <button class="text-xs text-emerald-400 hover:text-emerald-300">Unblock</button>
                    </form>
                </div>
                @empty
                <p class="text-xs text-gray-500">No blocked IPs</p>
                @endforelse
            </div>
        </div>

        <div class="card rounded-xl p-6">
            <h3 class="text-sm font-semibold text-white mb-4"><i class="fas fa-fingerprint text-purple-400 mr-2"></i>Blocked Fingerprints ({{ $blockedFingerprints->count() }})</h3>
            <form method="POST" action="{{ route('admin.sessions.block-fingerprint') }}" class="flex gap-2 mb-4">
                @csrf
                <input type="text" name="fingerprint" placeholder="Fingerprint hash" class="flex-1 rounded-lg px-3 py-2 text-sm border outline-none">
                <button class="btn-danger text-white text-xs px-3 py-2 rounded-lg">Block</button>
            </form>
            <div class="space-y-2 max-h-64 overflow-y-auto">
                @forelse($blockedFingerprints as $blocked)
                <div class="flex items-center justify-between bg-gray-800/50 rounded-lg px-3 py-2">
                    <span class="text-xs text-gray-300 font-mono">{{ Str::limit($blocked->fingerprint, 20) }}</span>
                    <form method="POST" action="{{ route('admin.sessions.unblock-fingerprint', $blocked) }}">
                        @csrf @method('DELETE')
                        <button class="text-xs text-emerald-400 hover:text-emerald-300">Unblock</button>
                    </form>
                </div>
                @empty
                <p class="text-xs text-gray-500">No blocked fingerprints</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
