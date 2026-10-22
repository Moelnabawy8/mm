@extends('layouts.admin')
@section('page-title', 'Security Presets')
@section('page-subtitle', 'Manage security configuration templates')

@section('content')
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    @foreach($presets as $preset)
    <div class="card rounded-xl p-6 hover:glow-indigo transition-all">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg flex items-center justify-center
                    {{ $preset->name === 'High Security' ? 'bg-red-500/20' : ($preset->name === 'Balanced' ? 'bg-yellow-500/20' : 'bg-emerald-500/20') }}">
                    <i class="fas fa-shield-halved
                        {{ $preset->name === 'High Security' ? 'text-red-400' : ($preset->name === 'Balanced' ? 'text-yellow-400' : 'text-emerald-400') }}"></i>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-white">{{ $preset->name }}</h3>
                    @if($preset->is_default)
                        <span class="text-[10px] text-indigo-400">Default</span>
                    @endif
                </div>
            </div>
            <span class="text-xs text-gray-500">{{ $preset->videos_count }} videos</span>
        </div>

        <p class="text-xs text-gray-400 mb-4">{{ $preset->description }}</p>

        <div class="space-y-2 mb-4">
            @php $s = $preset->settings; @endphp
            <div class="flex items-center gap-2 text-xs">
                <i class="fas fa-{{ ($s['ip_binding_enabled'] ?? false) ? 'check text-emerald-400' : 'times text-gray-600' }} w-3"></i>
                <span class="text-gray-400">IP Binding</span>
            </div>
            <div class="flex items-center gap-2 text-xs">
                <i class="fas fa-{{ ($s['fingerprint_lock'] ?? false) ? 'check text-emerald-400' : 'times text-gray-600' }} w-3"></i>
                <span class="text-gray-400">Fingerprint Lock</span>
            </div>
            <div class="flex items-center gap-2 text-xs">
                <i class="fas fa-{{ ($s['key_rotation_enabled'] ?? false) ? 'check text-emerald-400' : 'times text-gray-600' }} w-3"></i>
                <span class="text-gray-400">Key Rotation</span>
            </div>
            <div class="flex items-center gap-2 text-xs">
                <i class="fas fa-{{ ($s['watermark_enabled'] ?? false) ? 'check text-emerald-400' : 'times text-gray-600' }} w-3"></i>
                <span class="text-gray-400">Watermark</span>
            </div>
            <div class="flex items-center gap-2 text-xs">
                <i class="fas fa-signal text-gray-500 w-3"></i>
                <span class="text-gray-400">Max Streams: {{ ($s['max_concurrent_streams'] ?? 0) ?: '∞' }}</span>
            </div>
            <div class="flex items-center gap-2 text-xs">
                <i class="fas fa-clock text-gray-500 w-3"></i>
                <span class="text-gray-400">Token: {{ $s['token_duration'] ?? 10 }}min</span>
            </div>
        </div>

        <div class="flex gap-2">
            <form method="POST" action="{{ route('admin.presets.destroy', $preset) }}" class="flex-1" onsubmit="return confirm('Delete this preset?')">
                @csrf @method('DELETE')
                <button class="w-full text-xs bg-red-900/20 text-red-400 px-3 py-2 rounded-lg hover:bg-red-900/30 transition-all">
                    <i class="fas fa-trash mr-1"></i> Delete
                </button>
            </form>
        </div>
    </div>
    @endforeach
</div>
@endsection
