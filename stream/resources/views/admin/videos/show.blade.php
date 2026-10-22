@extends('layouts.admin')
@section('page-title', $video->title)
@section('page-subtitle', 'Video Control Center')

@section('header-actions')
@if($video->frozen)
    <form action="{{ route('admin.videos.unfreeze', $video) }}" method="POST">
        @csrf
        <button class="btn-success text-white text-sm px-4 py-2 rounded-lg flex items-center gap-2"><i class="fas fa-sun"></i> Unfreeze</button>
    </form>
@else
    <form action="{{ route('admin.videos.freeze', $video) }}" method="POST" onsubmit="return confirm('🚨 FREEZE this video? All active sessions will be terminated!')">
        @csrf
        <button class="btn-danger text-white text-sm px-4 py-2 rounded-lg flex items-center gap-2 animate-pulse hover:animate-none"><i class="fas fa-snowflake"></i> Emergency Freeze</button>
    </form>
@endif
@endsection

@section('content')
<div x-data="{ activeTab: 'overview' }">

    @if($video->frozen)
    <div class="bg-red-900/20 border border-red-700/50 rounded-xl p-4 mb-6 flex items-center gap-3 glow-red">
        <i class="fas fa-snowflake text-red-400 text-xl animate-pulse"></i>
        <div>
            <p class="text-red-300 font-medium">This video is FROZEN</p>
            <p class="text-red-400/70 text-xs">All streaming and access is suspended. Click Unfreeze to restore.</p>
        </div>
    </div>
    @endif

    <!-- Tabs -->
    <div class="flex items-center gap-1 border-b border-gray-800 mb-6 overflow-x-auto">
        @foreach(['overview' => 'Overview', 'security' => 'Security', 'watermark' => 'Watermark', 'sessions' => 'Sessions', 'analytics' => 'Analytics', 'edit' => 'Edit Tools'] as $tab => $label)
        <button @click="activeTab = '{{ $tab }}'"
            class="tab-btn px-4 py-3 text-sm font-medium transition-all whitespace-nowrap"
            :class="activeTab === '{{ $tab }}' ? 'active text-indigo-400 border-b-2 border-indigo-500' : 'text-gray-500 hover:text-gray-300'">
            {{ $label }}
        </button>
        @endforeach
    </div>

    <!-- OVERVIEW TAB -->
    <div x-show="activeTab === 'overview'" x-transition>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <!-- Basic Info Card -->
                <div class="card rounded-xl p-6">
                    <h3 class="text-sm font-semibold text-white mb-4"><i class="fas fa-info-circle text-indigo-400 mr-2"></i>Video Information</h3>
                    <form method="POST" action="{{ route('admin.videos.update', $video) }}">
                        @csrf @method('PUT')
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs text-gray-400 mb-1">Title</label>
                                <input type="text" name="title" value="{{ $video->title }}" class="w-full rounded-lg px-3 py-2 text-sm border outline-none">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-400 mb-1">Description</label>
                                <textarea name="description" rows="3" class="w-full rounded-lg px-3 py-2 text-sm border outline-none">{{ $video->description }}</textarea>
                            </div>
                            <div class="flex items-center gap-4">
                                <div class="flex-1">
                                    <label class="block text-xs text-gray-400 mb-1">Visibility</label>
                                    <select name="visibility" class="w-full rounded-lg px-3 py-2 text-sm border outline-none">
                                        <option value="public" {{ $video->visibility === 'public' ? 'selected' : '' }}>Public</option>
                                        <option value="private" {{ $video->visibility === 'private' ? 'selected' : '' }}>Private</option>
                                    </select>
                                </div>
                                <div class="flex-1">
                                    <label class="block text-xs text-gray-400 mb-1">Status</label>
                                    <span class="inline-flex items-center gap-1 text-sm px-3 py-2">
                                        @if($video->status === 'ready')<i class="fas fa-circle text-emerald-400 text-[8px]"></i> Ready
                                        @elseif($video->status === 'processing')<i class="fas fa-spinner fa-spin text-yellow-400 text-[8px]"></i> Processing
                                        @else <i class="fas fa-circle text-gray-400 text-[8px]"></i> {{ ucfirst($video->status) }}
                                        @endif
                                    </span>
                                </div>
                            </div>
                            <button type="submit" class="btn-primary text-white text-sm px-4 py-2 rounded-lg">Save Changes</button>
                        </div>
                    </form>
                </div>

                <!-- Embed & Share -->
                <div class="card rounded-xl p-6">
                    <h3 class="text-sm font-semibold text-white mb-4"><i class="fas fa-code text-purple-400 mr-2"></i>Embed & Share</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">Watch Link</label>
                            <div class="flex gap-2">
                                <input type="text" readonly value="{{ $shareLink }}" id="shareLink" class="flex-1 rounded-lg px-3 py-2 text-xs font-mono border outline-none">
                                <button onclick="navigator.clipboard.writeText(document.getElementById('shareLink').value)" class="bg-gray-700 hover:bg-gray-600 text-white text-xs px-3 py-2 rounded-lg"><i class="fas fa-copy"></i></button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">Embed Code</label>
                            <div class="flex gap-2">
                                <input type="text" readonly value="{{ $embedCode }}" id="embedCode" class="flex-1 rounded-lg px-3 py-2 text-xs font-mono border outline-none">
                                <button onclick="navigator.clipboard.writeText(document.getElementById('embedCode').value)" class="bg-gray-700 hover:bg-gray-600 text-white text-xs px-3 py-2 rounded-lg"><i class="fas fa-copy"></i></button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Allowed Domains -->
                <div class="card rounded-xl p-6">
                    <h3 class="text-sm font-semibold text-white mb-4"><i class="fas fa-globe text-blue-400 mr-2"></i>Allowed Domains</h3>
                    <form method="POST" action="{{ route('admin.videos.domains.store', $video) }}" class="flex gap-2 mb-4">
                        @csrf
                        <input type="text" name="domain" placeholder="example.com" class="flex-1 rounded-lg px-3 py-2 text-sm border outline-none">
                        <button class="btn-primary text-white text-sm px-4 py-2 rounded-lg">Add</button>
                    </form>
                    <div class="space-y-2">
                        @forelse($video->allowedDomains as $domain)
                        <div class="flex items-center justify-between bg-gray-800/50 rounded-lg px-3 py-2">
                            <span class="text-sm text-gray-300 font-mono">{{ $domain->domain }}</span>
                            <form method="POST" action="{{ route('admin.videos.domains.destroy', [$video, $domain->id]) }}">
                                @csrf @method('DELETE')
                                <button class="text-red-400 hover:text-red-300 text-xs"><i class="fas fa-times"></i></button>
                            </form>
                        </div>
                        @empty
                        <p class="text-xs text-gray-500">No domain restrictions. All domains allowed.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Sidebar Stats -->
            <div class="space-y-6">
                <div class="card rounded-xl p-6">
                    <h3 class="text-sm font-semibold text-white mb-4"><i class="fas fa-chart-pie text-indigo-400 mr-2"></i>Quick Stats</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between"><span class="text-xs text-gray-400">Total Views</span><span class="text-sm font-medium text-white">{{ number_format($video->total_views_count) }}</span></div>
                        <div class="flex justify-between"><span class="text-xs text-gray-400">Views Today</span><span class="text-sm font-medium text-emerald-400">{{ $viewsToday }}</span></div>
                        <div class="flex justify-between"><span class="text-xs text-gray-400">Views This Week</span><span class="text-sm font-medium text-blue-400">{{ $viewsWeek }}</span></div>
                        <div class="flex justify-between"><span class="text-xs text-gray-400">Blocked Attempts</span><span class="text-sm font-medium text-red-400">{{ $blockedAttempts }}</span></div>
                        <div class="flex justify-between"><span class="text-xs text-gray-400">Active Sessions</span><span class="text-sm font-medium text-purple-400">{{ $video->activeSessions->count() }}</span></div>
                        <hr class="border-gray-800">
                        <div class="flex justify-between"><span class="text-xs text-gray-400">Duration</span><span class="text-sm text-gray-300">{{ $video->duration ? gmdate('H:i:s', $video->duration) : 'N/A' }}</span></div>
                        <div class="flex justify-between"><span class="text-xs text-gray-400">Resolution</span><span class="text-sm text-gray-300">{{ $video->resolution ?? 'N/A' }}</span></div>
                        <div class="flex justify-between"><span class="text-xs text-gray-400">Created</span><span class="text-sm text-gray-300">{{ $video->created_at->format('M j, Y') }}</span></div>
                    </div>
                </div>

                <!-- Apply Preset -->
                <div class="card rounded-xl p-6">
                    <h3 class="text-sm font-semibold text-white mb-4"><i class="fas fa-shield-halved text-yellow-400 mr-2"></i>Security Preset</h3>
                    <form method="POST" action="{{ route('admin.videos.apply-preset', $video) }}">
                        @csrf
                        <select name="preset_id" class="w-full rounded-lg px-3 py-2 text-sm border outline-none mb-3">
                            @foreach($presets as $preset)
                                <option value="{{ $preset->id }}" {{ $video->security_preset_id == $preset->id ? 'selected' : '' }}>
                                    {{ $preset->name }} {{ $preset->is_default ? '(Default)' : '' }}
                                </option>
                            @endforeach
                        </select>
                        <button class="btn-primary text-white text-xs px-3 py-2 rounded-lg w-full">Apply Preset</button>
                    </form>
                </div>

                <!-- Danger Zone -->
                <div class="card rounded-xl p-6 border-red-900/30">
                    <h3 class="text-sm font-semibold text-red-400 mb-4"><i class="fas fa-exclamation-triangle mr-2"></i>Danger Zone</h3>
                    <form method="POST" action="{{ route('admin.videos.destroy', $video) }}" onsubmit="return confirm('Permanently delete this video?')">
                        @csrf @method('DELETE')
                        <button class="btn-danger text-white text-xs px-3 py-2 rounded-lg w-full"><i class="fas fa-trash mr-1"></i> Delete Video</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- SECURITY TAB -->
    <div x-show="activeTab === 'security'" x-transition>
        <form method="POST" action="{{ route('admin.videos.security.update', $video) }}">
            @csrf
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Access Controls -->
                <div class="card rounded-xl p-6">
                    <h3 class="text-sm font-semibold text-white mb-4"><i class="fas fa-lock text-indigo-400 mr-2"></i>Access Controls</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">Max Concurrent Streams (0 = unlimited)</label>
                            <input type="number" name="max_concurrent_streams" value="{{ $video->max_concurrent_streams }}" min="0" class="w-full rounded-lg px-3 py-2 text-sm border outline-none">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">Max Total Views (0 = unlimited)</label>
                            <input type="number" name="max_total_views" value="{{ $video->max_total_views }}" min="0" class="w-full rounded-lg px-3 py-2 text-sm border outline-none">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">Expiration Date</label>
                            <input type="datetime-local" name="expires_at" value="{{ $video->expires_at?->format('Y-m-d\TH:i') }}" class="w-full rounded-lg px-3 py-2 text-sm border outline-none">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">Token Duration (minutes)</label>
                            <input type="number" name="token_duration" value="{{ $video->token_duration }}" min="1" max="1440" class="w-full rounded-lg px-3 py-2 text-sm border outline-none">
                        </div>
                    </div>
                </div>

                <!-- Security Toggles -->
                <div class="card rounded-xl p-6">
                    <h3 class="text-sm font-semibold text-white mb-4"><i class="fas fa-shield-alt text-purple-400 mr-2"></i>Security Features</h3>
                    <div class="space-y-4">
                        @foreach([
                            'ip_binding_enabled' => ['IP Binding', 'Lock video to viewer IP address', 'fas fa-link', 'indigo'],
                            'fingerprint_lock' => ['Fingerprint Lock', 'Lock video to browser fingerprint', 'fas fa-fingerprint', 'purple'],
                            'key_rotation_enabled' => ['Key Rotation', 'Periodically rotate encryption keys', 'fas fa-sync-alt', 'emerald'],
                            'geo_restriction_enabled' => ['Geo Restriction', 'Restrict by country', 'fas fa-globe', 'orange'],
                        ] as $field => [$label, $desc, $icon, $color])
                        <label class="flex items-center gap-4 p-3 rounded-lg bg-gray-800/30 hover:bg-gray-800/50 cursor-pointer transition-all">
                            <input type="checkbox" name="{{ $field }}" value="1" {{ $video->$field ? 'checked' : '' }}
                                class="w-4 h-4 rounded border-gray-600 text-indigo-500 focus:ring-indigo-500">
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <i class="{{ $icon }} text-{{ $color }}-400 text-xs"></i>
                                    <span class="text-sm text-white">{{ $label }}</span>
                                </div>
                                <p class="text-xs text-gray-500 mt-0.5">{{ $desc }}</p>
                            </div>
                        </label>
                        @endforeach
                    </div>

                    <!-- Countries (shown when geo restriction enabled) -->
                    <div class="mt-4" x-data="{ geoEnabled: {{ $video->geo_restriction_enabled ? 'true' : 'false' }} }">
                        <div x-show="geoEnabled || document.querySelector('[name=geo_restriction_enabled]')?.checked">
                            <label class="block text-xs text-gray-400 mb-1">Allowed Countries (comma-separated ISO codes)</label>
                            <input type="text" name="allowed_countries[]" value="{{ implode(',', $video->allowed_countries ?? []) }}" placeholder="US,GB,DE" class="w-full rounded-lg px-3 py-2 text-sm border outline-none">
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-6">
                <button class="btn-primary text-white text-sm px-6 py-2 rounded-lg"><i class="fas fa-save mr-2"></i>Save Security Settings</button>
            </div>
        </form>
    </div>

    <!-- WATERMARK TAB -->
    <div x-show="activeTab === 'watermark'" x-transition>
        <form method="POST" action="{{ route('admin.videos.watermark.update', $video) }}">
            @csrf
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="card rounded-xl p-6">
                    <h3 class="text-sm font-semibold text-white mb-4"><i class="fas fa-tint text-blue-400 mr-2"></i>Watermark Settings</h3>
                    <div class="space-y-4">
                        <label class="flex items-center gap-3 p-3 rounded-lg bg-gray-800/30 cursor-pointer">
                            <input type="checkbox" name="watermark_enabled" value="1" {{ $video->watermark_enabled ? 'checked' : '' }}
                                class="w-4 h-4 rounded border-gray-600 text-indigo-500">
                            <span class="text-sm text-white">Enable Watermark</span>
                        </label>
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">Watermark Type</label>
                            <select name="watermark_type" class="w-full rounded-lg px-3 py-2 text-sm border outline-none">
                                @foreach(['ip' => 'Viewer IP', 'email' => 'User Email', 'text' => 'Custom Text', 'session' => 'Session ID', 'timestamp' => 'Timestamp'] as $val => $label)
                                    <option value="{{ $val }}" {{ $video->watermark_type === $val ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">Custom Text (for "Custom Text" type)</label>
                            <input type="text" name="watermark_text" value="{{ $video->watermark_text }}" placeholder="Your custom watermark" class="w-full rounded-lg px-3 py-2 text-sm border outline-none">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">Opacity ({{ $video->watermark_opacity }})</label>
                            <input type="range" name="watermark_opacity" min="0.05" max="1" step="0.05" value="{{ $video->watermark_opacity }}" class="w-full accent-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">Position</label>
                            <select name="watermark_position" class="w-full rounded-lg px-3 py-2 text-sm border outline-none">
                                @foreach(['random' => 'Random', 'top-left' => 'Top Left', 'top-right' => 'Top Right', 'bottom-left' => 'Bottom Left', 'bottom-right' => 'Bottom Right', 'center' => 'Center'] as $val => $label)
                                    <option value="{{ $val }}" {{ $video->watermark_position === $val ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">Animation</label>
                            <select name="watermark_animation" class="w-full rounded-lg px-3 py-2 text-sm border outline-none">
                                <option value="move" {{ $video->watermark_animation === 'move' ? 'selected' : '' }}>Moving</option>
                                <option value="static" {{ $video->watermark_animation === 'static' ? 'selected' : '' }}>Static</option>
                                <option value="fade" {{ $video->watermark_animation === 'fade' ? 'selected' : '' }}>Fade In/Out</option>
                            </select>
                        </div>
                    </div>
                    <button class="btn-primary text-white text-sm px-6 py-2 rounded-lg mt-4"><i class="fas fa-save mr-2"></i>Save Watermark</button>
                </div>

                <!-- Preview -->
                <div class="card rounded-xl p-6">
                    <h3 class="text-sm font-semibold text-white mb-4"><i class="fas fa-eye text-gray-400 mr-2"></i>Preview</h3>
                    <div class="relative bg-gray-900 rounded-lg aspect-video flex items-center justify-center">
                        <i class="fas fa-play text-gray-700 text-4xl"></i>
                        <div class="absolute top-4 left-4 text-xs font-mono text-white/30">
                            {{ $video->watermark_type === 'text' ? ($video->watermark_text ?: 'Custom Text') : '192.168.1.xxx' }}
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- SESSIONS TAB -->
    <div x-show="activeTab === 'sessions'" x-transition>
        <div class="card rounded-xl overflow-hidden">
            <div class="p-4 border-b border-gray-800">
                <h3 class="text-sm font-semibold text-white"><i class="fas fa-satellite-dish text-emerald-400 mr-2"></i>Active Sessions ({{ $video->activeSessions->count() }})</h3>
            </div>
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-800">
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">IP</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Fingerprint</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Country</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Started</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Last Beat</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($video->activeSessions as $session)
                    <tr class="border-b border-gray-800/50">
                        <td class="px-4 py-3 text-sm text-gray-300 font-mono">{{ $session->ip_address }}</td>
                        <td class="px-4 py-3 text-xs text-gray-500 font-mono">{{ Str::limit($session->fingerprint ?? 'N/A', 12) }}</td>
                        <td class="px-4 py-3 text-sm text-gray-300">{{ $session->country ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs text-gray-400">{{ $session->started_at->diffForHumans() }}</td>
                        <td class="px-4 py-3 text-xs text-gray-400">{{ $session->last_heartbeat->diffForHumans() }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <form method="POST" action="{{ route('admin.sessions.terminate', $session) }}">
                                    @csrf
                                    <button class="text-xs bg-red-900/30 text-red-400 px-2 py-1 rounded hover:bg-red-900/50"><i class="fas fa-times mr-1"></i>Kill</button>
                                </form>
                                <form method="POST" action="{{ route('admin.sessions.block-ip') }}">
                                    @csrf
                                    <input type="hidden" name="ip_address" value="{{ $session->ip_address }}">
                                    <input type="hidden" name="video_id" value="{{ $video->id }}">
                                    <button class="text-xs bg-orange-900/30 text-orange-400 px-2 py-1 rounded hover:bg-orange-900/50"><i class="fas fa-ban mr-1"></i>Block IP</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500 text-sm">No active sessions</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Blocked IPs for this video -->
        @if($video->blockedIps->count() > 0)
        <div class="card rounded-xl p-6 mt-6">
            <h3 class="text-sm font-semibold text-white mb-4"><i class="fas fa-ban text-red-400 mr-2"></i>Blocked IPs</h3>
            <div class="space-y-2">
                @foreach($video->blockedIps as $blocked)
                <div class="flex items-center justify-between bg-gray-800/50 rounded-lg px-3 py-2">
                    <span class="text-sm text-gray-300 font-mono">{{ $blocked->ip_address }}</span>
                    <div class="flex items-center gap-3">
                        <span class="text-xs text-gray-500">{{ $blocked->reason }}</span>
                        <form method="POST" action="{{ route('admin.sessions.unblock-ip', $blocked) }}">
                            @csrf @method('DELETE')
                            <button class="text-xs text-emerald-400 hover:text-emerald-300">Unblock</button>
                        </form>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    <!-- ANALYTICS TAB -->
    <div x-show="activeTab === 'analytics'" x-transition>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="stat-card rounded-xl p-4"><p class="text-xs text-gray-400">Today</p><p class="text-xl font-bold text-white mt-1">{{ $viewsToday }}</p></div>
            <div class="stat-card rounded-xl p-4"><p class="text-xs text-gray-400">This Week</p><p class="text-xl font-bold text-white mt-1">{{ $viewsWeek }}</p></div>
            <div class="stat-card rounded-xl p-4"><p class="text-xs text-gray-400">Total</p><p class="text-xl font-bold text-white mt-1">{{ number_format($video->total_views_count) }}</p></div>
            <div class="stat-card rounded-xl p-4"><p class="text-xs text-gray-400">Blocked</p><p class="text-xl font-bold text-red-400 mt-1">{{ $blockedAttempts }}</p></div>
        </div>

        @if($countries->count() > 0)
        <div class="card rounded-xl p-6 mb-6">
            <h3 class="text-sm font-semibold text-white mb-4"><i class="fas fa-globe text-purple-400 mr-2"></i>Country Distribution</h3>
            <div class="space-y-2">
                @foreach($countries as $c)
                <div class="flex items-center gap-3">
                    <span class="text-sm font-mono text-gray-400 w-8">{{ $c->country }}</span>
                    <div class="flex-1 bg-gray-800 rounded-full h-2">
                        <div class="bg-indigo-500 h-2 rounded-full" style="width: {{ $countries->max('total') > 0 ? ($c->total / $countries->max('total') * 100) : 0 }}%"></div>
                    </div>
                    <span class="text-xs text-gray-400">{{ $c->total }}</span>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <!-- Recent Access Logs -->
        <div class="card rounded-xl overflow-hidden">
            <div class="p-4 border-b border-gray-800">
                <h3 class="text-sm font-semibold text-white"><i class="fas fa-list text-gray-400 mr-2"></i>Recent Access Logs</h3>
            </div>
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-800">
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-400">Time</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-400">IP</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-400">Country</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-400">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($video->accessLogs->take(20) as $log)
                    <tr class="border-b border-gray-800/30">
                        <td class="px-4 py-2 text-xs text-gray-400">{{ $log->created_at->format('M j H:i') }}</td>
                        <td class="px-4 py-2 text-xs text-gray-300 font-mono">{{ $log->ip_address }}</td>
                        <td class="px-4 py-2 text-xs text-gray-400">{{ $log->country ?? '—' }}</td>
                        <td class="px-4 py-2">
                            @if($log->blocked)
                                <span class="text-xs text-red-400"><i class="fas fa-times-circle"></i> Blocked</span>
                            @else
                                <span class="text-xs text-emerald-400"><i class="fas fa-check-circle"></i> Allowed</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- EDIT TOOLS TAB -->
    <div x-show="activeTab === 'edit'" x-transition>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Thumbnail -->
            <div class="card rounded-xl p-6">
                <h3 class="text-sm font-semibold text-white mb-4"><i class="fas fa-image text-blue-400 mr-2"></i>Thumbnail</h3>
                <div class="space-y-3">
                    <form method="POST" action="{{ route('admin.videos.edit.regenerate-thumbnail', $video) }}">
                        @csrf
                        <button class="btn-primary text-white text-xs px-4 py-2 rounded-lg w-full"><i class="fas fa-sync mr-1"></i> Auto-Generate Thumbnail</button>
                    </form>
                    <form method="POST" action="{{ route('admin.videos.edit.extract-frame', $video) }}" class="flex gap-2">
                        @csrf
                        <input type="number" name="time" min="0" max="{{ $video->duration ?? 999 }}" placeholder="Time (seconds)" class="flex-1 rounded-lg px-3 py-2 text-sm border outline-none">
                        <button class="btn-primary text-white text-xs px-4 py-2 rounded-lg"><i class="fas fa-camera mr-1"></i> Extract</button>
                    </form>
                </div>
            </div>

            <!-- HLS -->
            <div class="card rounded-xl p-6">
                <h3 class="text-sm font-semibold text-white mb-4"><i class="fas fa-film text-purple-400 mr-2"></i>HLS Stream</h3>
                <form method="POST" action="{{ route('admin.videos.edit.regenerate-hls', $video) }}">
                    @csrf
                    <p class="text-xs text-gray-400 mb-3">Regenerate HLS segments from the original source file. Existing secure URLs will remain valid.</p>
                    <button class="btn-warning text-white text-xs px-4 py-2 rounded-lg w-full"><i class="fas fa-redo mr-1"></i> Regenerate HLS</button>
                </form>
            </div>

            <!-- Trim -->
            <div class="card rounded-xl p-6">
                <h3 class="text-sm font-semibold text-white mb-4"><i class="fas fa-cut text-emerald-400 mr-2"></i>Trim Video</h3>
                <form method="POST" action="{{ route('admin.videos.edit.trim', $video) }}">
                    @csrf
                    <div class="flex gap-3 mb-3">
                        <div class="flex-1">
                            <label class="block text-xs text-gray-400 mb-1">Start (seconds)</label>
                            <input type="number" name="start_time" min="0" step="0.1" class="w-full rounded-lg px-3 py-2 text-sm border outline-none">
                        </div>
                        <div class="flex-1">
                            <label class="block text-xs text-gray-400 mb-1">End (seconds)</label>
                            <input type="number" name="end_time" min="0" step="0.1" value="{{ $video->duration }}" class="w-full rounded-lg px-3 py-2 text-sm border outline-none">
                        </div>
                    </div>
                    <button class="btn-primary text-white text-xs px-4 py-2 rounded-lg w-full"><i class="fas fa-cut mr-1"></i> Trim Video</button>
                </form>
            </div>

            <!-- Bitrate -->
            <div class="card rounded-xl p-6">
                <h3 class="text-sm font-semibold text-white mb-4"><i class="fas fa-tachometer-alt text-orange-400 mr-2"></i>Change Bitrate</h3>
                <form method="POST" action="{{ route('admin.videos.edit.change-bitrate', $video) }}">
                    @csrf
                    <select name="bitrate" class="w-full rounded-lg px-3 py-2 text-sm border outline-none mb-3">
                        <option value="500k">500 Kbps (Low)</option>
                        <option value="1000k">1 Mbps (SD)</option>
                        <option value="2000k" selected>2 Mbps (HD)</option>
                        <option value="4000k">4 Mbps (Full HD)</option>
                        <option value="8000k">8 Mbps (4K)</option>
                    </select>
                    <button class="btn-warning text-white text-xs px-4 py-2 rounded-lg w-full"><i class="fas fa-exchange-alt mr-1"></i> Re-encode</button>
                </form>
            </div>

            <!-- Replace Source -->
            <div class="card rounded-xl p-6 lg:col-span-2">
                <h3 class="text-sm font-semibold text-white mb-4"><i class="fas fa-upload text-red-400 mr-2"></i>Replace Source File</h3>
                <form method="POST" action="{{ route('admin.videos.edit.replace-source', $video) }}" enctype="multipart/form-data">
                    @csrf
                    <p class="text-xs text-gray-400 mb-3">Upload a new video file to replace the current source. All secure URLs remain unchanged.</p>
                    <input type="file" name="video_file" accept="video/mp4,video/mkv,video/avi,video/quicktime" class="text-sm mb-3">
                    <br>
                    <button class="btn-danger text-white text-xs px-4 py-2 rounded-lg"><i class="fas fa-upload mr-1"></i> Replace & Re-process</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
