<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Video;
use App\Models\VideoAccessLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $videoId = $request->get('video_id');

        $query = VideoAccessLog::query();
        if ($videoId) {
            $query->where('video_id', $videoId);
        }

        $totalAccess = (clone $query)->count();
        $blockedAccess = (clone $query)->where('blocked', true)->count();
        $uniqueIps = (clone $query)->distinct('ip_address')->count('ip_address');

        $topVideos = VideoAccessLog::select('video_id', DB::raw('COUNT(*) as access_count'), DB::raw('SUM(blocked) as blocked_count'))
            ->groupBy('video_id')
            ->orderByDesc('access_count')
            ->take(10)
            ->with('video')
            ->get();

        $recentLogs = VideoAccessLog::with('video')
            ->latest()
            ->paginate(20);

        $suspiciousActivity = $this->detectSuspiciousActivity();

        $videos = Video::where('status', 'ready')->orderBy('title')->get();

        return view('admin.analytics.index', compact(
            'totalAccess',
            'blockedAccess',
            'uniqueIps',
            'topVideos',
            'recentLogs',
            'suspiciousActivity',
            'videos',
            'videoId'
        ));
    }

    protected function detectSuspiciousActivity(): array
    {
        $suspicious = [];

        // IPs with many blocked attempts in 24h
        $blockedIps = VideoAccessLog::select('ip_address', DB::raw('COUNT(*) as blocked_count'))
            ->where('blocked', true)
            ->where('created_at', '>=', now()->subHours(24))
            ->groupBy('ip_address')
            ->having('blocked_count', '>', 5)
            ->get();

        foreach ($blockedIps as $record) {
            $suspicious[] = [
                'type' => 'repeated_blocked',
                'ip' => $record->ip_address,
                'detail' => "{$record->blocked_count} blocked attempts in 24 hours",
            ];
        }

        // IPs accessing too many different videos in short time
        $highFreqIps = VideoAccessLog::select('ip_address', DB::raw('COUNT(DISTINCT video_id) as video_count'))
            ->where('created_at', '>=', now()->subHours(1))
            ->groupBy('ip_address')
            ->having('video_count', '>', 10)
            ->get();

        foreach ($highFreqIps as $record) {
            $suspicious[] = [
                'type' => 'high_frequency',
                'ip' => $record->ip_address,
                'detail' => "{$record->video_count} different videos accessed in 1 hour",
            ];
        }

        return $suspicious;
    }
}
