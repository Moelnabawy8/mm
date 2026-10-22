<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActiveSession;
use App\Models\Video;
use App\Models\VideoAccessLog;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_videos' => Video::count(),
            'videos_processing' => Video::where('status', 'processing')->count(),
            'videos_ready' => Video::whereIn('status', ['ready', 'active'])->count(),
            'videos_failed' => Video::where('status', 'failed')->count(),
            'total_access' => VideoAccessLog::where('blocked', false)->count(),
            'blocked_access' => VideoAccessLog::where('blocked', true)->count(),
            'active_sessions' => ActiveSession::where('status', 'active')->count(),
            'frozen_videos' => Video::where('frozen', true)->count(),
            'views_today' => VideoAccessLog::whereDate('created_at', today())->where('blocked', false)->count(),
            'views_week' => VideoAccessLog::where('created_at', '>=', now()->subWeek())->where('blocked', false)->count(),
        ];

        $recentVideos = Video::with('creator')
            ->withCount('activeSessions')
            ->latest()
            ->take(5)
            ->get();

        $recentLogs = VideoAccessLog::with('video')
            ->latest()
            ->take(15)
            ->get();

        // Daily views for chart (last 14 days)
        $dailyViews = VideoAccessLog::where('blocked', false)
            ->where('created_at', '>=', now()->subDays(14))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as views')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Country distribution
        $countryData = VideoAccessLog::whereNotNull('country')
            ->where('blocked', false)
            ->selectRaw('country, COUNT(*) as total')
            ->groupBy('country')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return view('admin.dashboard', compact('stats', 'recentVideos', 'recentLogs', 'dailyViews', 'countryData'));
    }
}
