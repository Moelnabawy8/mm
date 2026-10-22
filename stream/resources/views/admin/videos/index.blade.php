@extends('layouts.admin')
@section('page-title', 'Videos')
@section('page-subtitle'){{ $videos->total() }} total videos @endsection

@section('header-actions')
<a href="{{ route('admin.videos.create') }}" class="btn-primary text-white text-sm px-4 py-2 rounded-lg flex items-center gap-2 transition-all">
    <i class="fas fa-plus"></i> New Video
</a>
@endsection

@section('content')
<div x-data="videoManager()">
    <!-- Search & Filter Bar -->
    <div class="card rounded-xl p-4 mb-6">
        <form method="GET" class="flex flex-wrap items-center gap-3">
            <div class="flex-1 min-w-[200px]">
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm"></i>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search videos..."
                        class="w-full pl-10 pr-4 py-2 rounded-lg text-sm border outline-none">
                </div>
            </div>
            <select name="status" class="rounded-lg text-sm px-3 py-2 border outline-none">
                <option value="">All Status</option>
                <option value="ready" {{ request('status') == 'ready' ? 'selected' : '' }}>Ready</option>
                <option value="processing" {{ request('status') == 'processing' ? 'selected' : '' }}>Processing</option>
                <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="failed" {{ request('status') == 'failed' ? 'selected' : '' }}>Failed</option>
            </select>
            <button type="submit" class="btn-primary text-white text-sm px-4 py-2 rounded-lg">Filter</button>
        </form>
    </div>

    <!-- Bulk Actions Bar -->
    <div x-show="selectedIds.length > 0" x-transition class="card rounded-xl p-4 mb-4 border-indigo-500/30 glow-indigo">
        <div class="flex items-center gap-4 flex-wrap">
            <span class="text-sm text-indigo-300"><i class="fas fa-check-square mr-1"></i> <span x-text="selectedIds.length"></span> selected</span>
            <div class="flex items-center gap-2 flex-wrap">
                <select x-model="bulkPresetId" class="text-xs rounded-lg px-2 py-1.5 border outline-none">
                    <option value="">Apply Preset...</option>
                    @foreach($presets as $preset)
                        <option value="{{ $preset->id }}">{{ $preset->name }}</option>
                    @endforeach
                </select>
                <button @click="bulkAction('apply-preset')" x-show="bulkPresetId" class="btn-primary text-white text-xs px-3 py-1.5 rounded-lg">Apply</button>
                <button @click="bulkAction('disable')" class="btn-warning text-white text-xs px-3 py-1.5 rounded-lg"><i class="fas fa-snowflake mr-1"></i> Freeze</button>
                <button @click="bulkAction('enable')" class="btn-success text-white text-xs px-3 py-1.5 rounded-lg"><i class="fas fa-sun mr-1"></i> Unfreeze</button>
                <button @click="if(confirm('Delete selected videos?')) bulkAction('delete')" class="btn-danger text-white text-xs px-3 py-1.5 rounded-lg"><i class="fas fa-trash mr-1"></i> Delete</button>
            </div>
        </div>
    </div>

    <!-- Videos Table -->
    <div class="card rounded-xl overflow-hidden">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-800">
                    <th class="px-4 py-3 text-left">
                        <input type="checkbox" @change="toggleAll($event)" class="rounded border-gray-600">
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Video</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Views</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Sessions</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Security</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($videos as $video)
                <tr class="border-b border-gray-800/50 hover:bg-white/[0.02] transition-all">
                    <td class="px-4 py-3">
                        <input type="checkbox" value="{{ $video->id }}" x-model="selectedIds" class="rounded border-gray-600">
                    </td>
                    <td class="px-4 py-3">
                        <a href="{{ route('admin.videos.show', $video) }}" class="flex items-center gap-3 group">
                            <div class="w-12 h-8 bg-gray-800 rounded-lg flex items-center justify-center flex-shrink-0 group-hover:bg-indigo-900/30 transition-all">
                                <i class="fas fa-play text-indigo-400 text-[10px]"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm text-white truncate max-w-[200px] group-hover:text-indigo-300 transition-all">{{ $video->title }}</p>
                                <p class="text-[10px] text-gray-600">{{ $video->created_at->diffForHumans() }}</p>
                            </div>
                        </a>
                    </td>
                    <td class="px-4 py-3">
                        @if($video->frozen)
                            <span class="inline-flex items-center gap-1 text-xs bg-red-900/30 text-red-400 px-2 py-0.5 rounded-full"><i class="fas fa-snowflake text-[8px]"></i> Frozen</span>
                        @elseif($video->status === 'ready')
                            <span class="inline-flex items-center gap-1 text-xs bg-emerald-900/30 text-emerald-400 px-2 py-0.5 rounded-full"><i class="fas fa-circle text-[6px]"></i> Ready</span>
                        @elseif($video->status === 'processing')
                            <span class="inline-flex items-center gap-1 text-xs bg-yellow-900/30 text-yellow-400 px-2 py-0.5 rounded-full"><i class="fas fa-spinner fa-spin text-[8px]"></i> Processing</span>
                        @elseif($video->status === 'pending')
                            <span class="inline-flex items-center gap-1 text-xs bg-blue-900/30 text-blue-400 px-2 py-0.5 rounded-full"><i class="fas fa-clock text-[8px]"></i> Pending</span>
                        @else
                            <span class="inline-flex items-center gap-1 text-xs bg-red-900/30 text-red-400 px-2 py-0.5 rounded-full"><i class="fas fa-times text-[8px]"></i> Failed</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-300">{{ number_format($video->total_views_count) }}</td>
                    <td class="px-4 py-3">
                        @if($video->active_sessions_count > 0)
                            <span class="text-xs text-emerald-400">{{ $video->active_sessions_count }} <i class="fas fa-signal text-[8px]"></i></span>
                        @else
                            <span class="text-xs text-gray-600">0</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-1">
                            @if($video->ip_binding_enabled)<span class="w-5 h-5 rounded bg-indigo-900/30 flex items-center justify-center" title="IP Binding"><i class="fas fa-link text-indigo-400 text-[8px]"></i></span>@endif
                            @if($video->fingerprint_lock)<span class="w-5 h-5 rounded bg-purple-900/30 flex items-center justify-center" title="Fingerprint"><i class="fas fa-fingerprint text-purple-400 text-[8px]"></i></span>@endif
                            @if($video->watermark_enabled)<span class="w-5 h-5 rounded bg-blue-900/30 flex items-center justify-center" title="Watermark"><i class="fas fa-tint text-blue-400 text-[8px]"></i></span>@endif
                            @if($video->geo_restriction_enabled)<span class="w-5 h-5 rounded bg-orange-900/30 flex items-center justify-center" title="Geo"><i class="fas fa-globe text-orange-400 text-[8px]"></i></span>@endif
                        </div>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('admin.videos.show', $video) }}" class="text-gray-400 hover:text-indigo-400 text-sm transition-all" title="Control Center">
                            <i class="fas fa-sliders-h"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-4 py-16 text-center text-gray-500">
                        <i class="fas fa-video text-4xl mb-3 block"></i>
                        <p>No videos found</p>
                        <a href="{{ route('admin.videos.create') }}" class="btn-primary text-white text-sm px-4 py-2 rounded-lg inline-block mt-4">Upload First Video</a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $videos->links() }}</div>
</div>
@endsection

@push('scripts')
<script>
function videoManager() {
    return {
        selectedIds: [],
        bulkPresetId: '',
        toggleAll(e) {
            this.selectedIds = e.target.checked ? @json($videos->pluck('id')) : [];
        },
        bulkAction(action) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ url("admin/bulk") }}/' + action;
            const csrf = document.createElement('input');
            csrf.type = 'hidden'; csrf.name = '_token'; csrf.value = '{{ csrf_token() }}';
            form.appendChild(csrf);
            this.selectedIds.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden'; input.name = 'video_ids[]'; input.value = id;
                form.appendChild(input);
            });
            if (action === 'apply-preset' && this.bulkPresetId) {
                const pi = document.createElement('input');
                pi.type = 'hidden'; pi.name = 'preset_id'; pi.value = this.bulkPresetId;
                form.appendChild(pi);
            }
            document.body.appendChild(form);
            form.submit();
        }
    };
}
</script>
@endpush
