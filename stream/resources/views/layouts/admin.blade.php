<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'My Communication Stream') — Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        // Load theme before render to prevent flash
        (function(){
            const t = localStorage.getItem('mcs_theme') || 'dark';
            document.documentElement.setAttribute('data-theme', t);
        })();
    </script>
    <style>
        [x-cloak] { display: none !important; }
        .transition-all { transition: all 0.2s ease; }
        .transition-theme { transition: background 0.3s ease, color 0.3s ease, border-color 0.3s ease; }

        /* ===== DARK THEME (default) ===== */
        [data-theme="dark"] body { background: #0b0f1a; color: #e2e8f0; }
        [data-theme="dark"] .sidebar { background: linear-gradient(180deg, #0f1629 0%, #0a0e1a 100%); }
        [data-theme="dark"] .card { background: #111827; border: 1px solid #1e293b; }
        [data-theme="dark"] .card:hover { border-color: #334155; }
        [data-theme="dark"] .glass { background: rgba(17, 24, 39, 0.8); backdrop-filter: blur(12px); border: 1px solid rgba(99, 102, 241, 0.15); }
        [data-theme="dark"] .stat-card { background: linear-gradient(135deg, #111827 0%, #1a1f35 100%); border: 1px solid #1e293b; }
        [data-theme="dark"] .stat-card:hover { border-color: #6366f1; transform: translateY(-2px); }
        [data-theme="dark"] .glow-indigo { box-shadow: 0 0 20px rgba(99, 102, 241, 0.15); }
        [data-theme="dark"] .glow-red { box-shadow: 0 0 20px rgba(239, 68, 68, 0.15); }
        [data-theme="dark"] input, [data-theme="dark"] select, [data-theme="dark"] textarea { background: #1e293b !important; border-color: #334155 !important; color: #e2e8f0 !important; }
        [data-theme="dark"] input:focus, [data-theme="dark"] select:focus, [data-theme="dark"] textarea:focus { border-color: #6366f1 !important; box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2) !important; }
        [data-theme="dark"] .tab-btn.active { color: #818cf8; border-bottom: 2px solid #6366f1; }
        [data-theme="dark"] .t-text { color: #e2e8f0; }
        [data-theme="dark"] .t-text-muted { color: #94a3b8; }
        [data-theme="dark"] .t-text-dim { color: #64748b; }
        [data-theme="dark"] .t-border { border-color: #1e293b; }
        [data-theme="dark"] .t-bg-hover { background: rgba(255,255,255,0.02); }
        [data-theme="dark"] .t-bg-secondary { background: #1e293b; }
        [data-theme="dark"] .t-table-row:hover { background: rgba(255,255,255,0.02); }
        [data-theme="dark"] ::-webkit-scrollbar { width: 6px; }
        [data-theme="dark"] ::-webkit-scrollbar-track { background: #0b0f1a; }
        [data-theme="dark"] ::-webkit-scrollbar-thumb { background: #334155; border-radius: 3px; }

        /* ===== LIGHT THEME ===== */
        [data-theme="light"] body { background: #f1f5f9; color: #1e293b; }
        [data-theme="light"] .sidebar { background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%); border-right: 1px solid #e2e8f0; }
        [data-theme="light"] .card { background: #ffffff; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
        [data-theme="light"] .card:hover { border-color: #cbd5e1; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        [data-theme="light"] .glass { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(12px); border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        [data-theme="light"] .stat-card { background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
        [data-theme="light"] .stat-card:hover { border-color: #6366f1; transform: translateY(-2px); box-shadow: 0 8px 24px rgba(99, 102, 241, 0.12); }
        [data-theme="light"] .glow-indigo { box-shadow: 0 4px 20px rgba(99, 102, 241, 0.12); }
        [data-theme="light"] .glow-red { box-shadow: 0 4px 20px rgba(239, 68, 68, 0.12); }
        [data-theme="light"] input, [data-theme="light"] select, [data-theme="light"] textarea { background: #f8fafc !important; border-color: #d1d5db !important; color: #1e293b !important; }
        [data-theme="light"] input:focus, [data-theme="light"] select:focus, [data-theme="light"] textarea:focus { border-color: #6366f1 !important; box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.15) !important; }
        [data-theme="light"] .tab-btn.active { color: #4f46e5; border-bottom: 2px solid #4f46e5; }
        [data-theme="light"] .t-text { color: #1e293b; }
        [data-theme="light"] .t-text-muted { color: #64748b; }
        [data-theme="light"] .t-text-dim { color: #94a3b8; }
        [data-theme="light"] .t-border { border-color: #e2e8f0; }
        [data-theme="light"] .t-bg-hover { background: #f8fafc; }
        [data-theme="light"] .t-bg-secondary { background: #f1f5f9; }
        [data-theme="light"] .t-table-row:hover { background: #f8fafc; }
        [data-theme="light"] ::-webkit-scrollbar { width: 6px; }
        [data-theme="light"] ::-webkit-scrollbar-track { background: #f1f5f9; }
        [data-theme="light"] ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }

        /* Light theme overrides for specific text elements */
        [data-theme="light"] .text-white { color: #1e293b !important; }
        [data-theme="light"] .text-gray-200 { color: #374151 !important; }
        [data-theme="light"] .text-gray-300 { color: #4b5563 !important; }
        [data-theme="light"] .text-gray-400 { color: #6b7280 !important; }
        [data-theme="light"] .text-gray-500 { color: #9ca3af !important; }
        [data-theme="light"] .text-gray-600 { color: #6b7280 !important; }
        [data-theme="light"] .border-gray-800 { border-color: #e5e7eb !important; }
        [data-theme="light"] .border-gray-800\/50 { border-color: rgba(229, 231, 235, 0.5) !important; }
        [data-theme="light"] .border-gray-800\/30 { border-color: rgba(229, 231, 235, 0.3) !important; }
        [data-theme="light"] .bg-gray-800 { background-color: #e5e7eb !important; }
        [data-theme="light"] .bg-gray-800\/50 { background-color: rgba(229, 231, 235, 0.5) !important; }
        [data-theme="light"] .bg-gray-800\/30 { background-color: rgba(229, 231, 235, 0.3) !important; }
        [data-theme="light"] .bg-gray-700 { background-color: #d1d5db !important; }
        [data-theme="light"] .bg-gray-900 { background-color: #f3f4f6 !important; }
        [data-theme="light"] .text-gray-700 { color: #374151 !important; }
        [data-theme="light"] .hover\:bg-white\/5:hover { background: rgba(0,0,0,0.03) !important; }
        [data-theme="light"] .hover\:bg-white\/\[0\.02\]:hover { background: rgba(0,0,0,0.02) !important; }
        [data-theme="light"] .hover\:text-white:hover { color: #1e293b !important; }
        [data-theme="light"] .hover\:bg-gray-600:hover { background-color: #9ca3af !important; }

        /* Keep bright accent colors the same */
        [data-theme="light"] .bg-indigo-500\/10 { background: rgba(99, 102, 241, 0.08) !important; }
        [data-theme="light"] .bg-indigo-500\/20 { background: rgba(99, 102, 241, 0.1) !important; }

        /* Sidebar nav links in light mode */
        [data-theme="light"] .sidebar a.text-gray-400 { color: #64748b !important; }
        [data-theme="light"] .sidebar a:hover { color: #1e293b !important; background: rgba(0,0,0,0.04) !important; }
        [data-theme="light"] .sidebar .text-gray-500 { color: #94a3b8 !important; }

        /* Theme toggle switch */
        .theme-toggle { position: relative; width: 52px; height: 28px; border-radius: 14px; cursor: pointer; transition: all 0.3s ease; }
        [data-theme="dark"] .theme-toggle { background: #334155; }
        [data-theme="light"] .theme-toggle { background: #93c5fd; }
        .theme-toggle-knob { position: absolute; top: 3px; width: 22px; height: 22px; border-radius: 50%; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center; font-size: 11px; }
        [data-theme="dark"] .theme-toggle-knob { left: 3px; background: #1e293b; color: #fbbf24; }
        [data-theme="light"] .theme-toggle-knob { left: 27px; background: #ffffff; color: #f59e0b; box-shadow: 0 1px 3px rgba(0,0,0,0.15); }

        /* Buttons keep their gradients in both themes */
        .btn-primary { background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white !important; }
        .btn-primary:hover { background: linear-gradient(135deg, #5558e6, #7c4fe0); }
        .btn-danger { background: linear-gradient(135deg, #ef4444, #dc2626); color: white !important; }
        .btn-danger:hover { background: linear-gradient(135deg, #e03838, #c72020); }
        .btn-success { background: linear-gradient(135deg, #10b981, #059669); color: white !important; }
        .btn-warning { background: linear-gradient(135deg, #f59e0b, #d97706); color: white !important; }
    </style>
    @stack('styles')
</head>
<body class="min-h-screen text-gray-200 transition-theme">
    <div class="flex min-h-screen" x-data="{ sidebarOpen: true, mobileMenu: false, darkMode: localStorage.getItem('mcs_theme') !== 'light' }" x-init="$watch('darkMode', val => { const t = val ? 'dark' : 'light'; document.documentElement.setAttribute('data-theme', t); localStorage.setItem('mcs_theme', t); })">
        <!-- Sidebar -->
        <aside class="sidebar fixed h-full z-30 flex flex-col transition-all duration-300"
               :class="sidebarOpen ? 'w-64' : 'w-20'">
            <div class="p-5 border-b border-gray-800/50">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-play text-white text-sm" style="color:white!important"></i>
                    </div>
                    <div x-show="sidebarOpen" x-cloak class="overflow-hidden">
                        <h1 class="text-sm font-bold truncate">MCS</h1>
                        <p class="text-[10px] text-gray-500">Control Center</p>
                    </div>
                </div>
            </div>

            <nav class="flex-1 py-4 overflow-y-auto">
                @php
                $navItems = [
                    ['route' => 'admin.dashboard', 'icon' => 'fas fa-th-large', 'label' => 'Dashboard', 'pattern' => 'admin.dashboard'],
                    ['route' => 'admin.videos.index', 'icon' => 'fas fa-video', 'label' => 'Videos', 'pattern' => 'admin.videos.*'],
                    ['route' => 'admin.sessions.index', 'icon' => 'fas fa-satellite-dish', 'label' => 'Sessions', 'pattern' => 'admin.sessions.*'],
                    ['route' => 'admin.users.index', 'icon' => 'fas fa-users', 'label' => 'Users', 'pattern' => 'admin.users.*'],
                    ['route' => 'admin.domains.index', 'icon' => 'fas fa-globe', 'label' => 'Domains', 'pattern' => 'admin.domains.*'],
                    ['route' => 'admin.presets.index', 'icon' => 'fas fa-shield-halved', 'label' => 'Presets', 'pattern' => 'admin.presets.*'],
                    ['route' => 'admin.analytics.index', 'icon' => 'fas fa-chart-line', 'label' => 'Analytics', 'pattern' => 'admin.analytics.*'],
                    ['route' => 'admin.settings.index', 'icon' => 'fas fa-cog', 'label' => 'Settings', 'pattern' => 'admin.settings.*'],
                ];
                @endphp
                @foreach($navItems as $item)
                <a href="{{ route($item['route']) }}"
                   class="flex items-center gap-3 px-5 py-3 text-sm transition-all group
                   {{ request()->routeIs($item['pattern']) ? 'text-indigo-400 bg-indigo-500/10 border-r-2 border-indigo-500' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
                    <i class="{{ $item['icon'] }} w-5 text-center {{ request()->routeIs($item['pattern']) ? 'text-indigo-400' : 'text-gray-500 group-hover:text-gray-300' }}"></i>
                    <span x-show="sidebarOpen" x-cloak>{{ $item['label'] }}</span>
                </a>
                @endforeach
            </nav>

            <div class="p-4 border-t border-gray-800/50">
                <!-- Theme Toggle -->
                <div class="flex items-center justify-between mb-3" x-show="sidebarOpen" x-cloak>
                    <span class="text-xs text-gray-500"><i class="fas" :class="darkMode ? 'fa-moon' : 'fa-sun'"></i></span>
                    <div class="theme-toggle" @click="darkMode = !darkMode">
                        <div class="theme-toggle-knob">
                            <i class="fas" :class="darkMode ? 'fa-moon' : 'fa-sun'"></i>
                        </div>
                    </div>
                </div>
                <!-- Collapse toggle (small mode: includes theme icon) -->
                <div class="flex items-center justify-center" x-show="!sidebarOpen" x-cloak>
                    <button @click="darkMode = !darkMode" class="text-gray-500 hover:text-indigo-400 text-sm mb-2 transition-all">
                        <i class="fas" :class="darkMode ? 'fa-moon' : 'fa-sun'"></i>
                    </button>
                </div>

                <button @click="sidebarOpen = !sidebarOpen" class="w-full flex items-center justify-center gap-2 text-gray-500 hover:text-indigo-400 text-xs py-2 transition-all">
                    <i class="fas" :class="sidebarOpen ? 'fa-chevron-left' : 'fa-chevron-right'"></i>
                    <span x-show="sidebarOpen" x-cloak>Collapse</span>
                </button>
                <div class="flex items-center gap-3 mt-3 pt-3 border-t border-gray-800/50">
                    <div class="w-8 h-8 bg-gradient-to-br from-indigo-500 to-purple-500 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0" style="color:white!important">
                        {{ substr(Auth::user()->name, 0, 1) }}
                    </div>
                    <div x-show="sidebarOpen" x-cloak class="flex-1 min-w-0">
                        <p class="text-xs font-medium truncate">{{ Auth::user()->name }}</p>
                        <p class="text-[10px] text-gray-500 truncate">{{ Auth::user()->email }}</p>
                    </div>
                </div>
                <form action="{{ route('logout') }}" method="POST" class="mt-2" x-show="sidebarOpen" x-cloak>
                    @csrf
                    <button type="submit" class="w-full text-left text-xs text-gray-500 hover:text-red-400 transition-all flex items-center gap-2 py-1">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </button>
                </form>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 transition-all duration-300" :class="sidebarOpen ? 'ml-64' : 'ml-20'">
            <!-- Top Bar -->
            <header class="glass sticky top-0 z-20 px-6 py-4 flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold">@yield('page-title', 'Dashboard')</h2>
                    <p class="text-xs text-gray-500">@yield('page-subtitle', '')</p>
                </div>
                <div class="flex items-center gap-3">
                    @yield('header-actions')
                </div>
            </header>

            <!-- Flash Messages -->
            @if(session('success'))
                <div class="mx-6 mt-4 bg-emerald-900/30 border border-emerald-700/50 text-emerald-300 px-4 py-3 rounded-xl flex items-center gap-3 text-sm" x-data="{ show: true }" x-show="show" x-transition>
                    <i class="fas fa-check-circle text-emerald-400"></i>
                    <span class="flex-1">{{ session('success') }}</span>
                    <button @click="show = false" class="text-emerald-500 hover:text-emerald-300"><i class="fas fa-times"></i></button>
                </div>
            @endif

            @if(session('error'))
                <div class="mx-6 mt-4 bg-red-900/30 border border-red-700/50 text-red-300 px-4 py-3 rounded-xl flex items-center gap-3 text-sm" x-data="{ show: true }" x-show="show" x-transition>
                    <i class="fas fa-exclamation-circle text-red-400"></i>
                    <span class="flex-1">{{ session('error') }}</span>
                    <button @click="show = false" class="text-red-500 hover:text-red-300"><i class="fas fa-times"></i></button>
                </div>
            @endif

            @if($errors->any())
                <div class="mx-6 mt-4 bg-red-900/30 border border-red-700/50 text-red-300 px-4 py-3 rounded-xl text-sm">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Page Content -->
            <div class="p-6">
                @yield('content')
            </div>
        </main>
    </div>

    @stack('scripts')
</body>
</html>
