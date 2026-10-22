<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActiveSession;
use App\Models\BlockedFingerprint;
use App\Models\BlockedIp;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function index()
    {
        $sessions = ActiveSession::with('video')
            ->where('status', 'active')
            ->latest('last_heartbeat')
            ->paginate(25);

        $blockedIps = BlockedIp::latest()->limit(50)->get();
        $blockedFingerprints = BlockedFingerprint::latest()->limit(50)->get();

        return view('admin.sessions.index', compact('sessions', 'blockedIps', 'blockedFingerprints'));
    }

    public function terminate(ActiveSession $session)
    {
        $session->update(['status' => 'terminated']);

        return back()->with('success', 'Session terminated successfully.');
    }

    public function blockIp(Request $request)
    {
        $request->validate([
            'ip_address' => 'required|string|max:45',
            'video_id' => 'nullable|uuid',
            'reason' => 'nullable|string|max:255',
        ]);

        BlockedIp::create([
            'ip_address' => $request->ip_address,
            'video_id' => $request->video_id,
            'reason' => $request->reason ?? 'Blocked by admin',
        ]);

        // Terminate active sessions from this IP
        ActiveSession::where('ip_address', $request->ip_address)
            ->where('status', 'active')
            ->update(['status' => 'terminated']);

        return back()->with('success', 'IP blocked and sessions terminated.');
    }

    public function blockFingerprint(Request $request)
    {
        $request->validate([
            'fingerprint' => 'required|string|max:128',
            'video_id' => 'nullable|uuid',
            'reason' => 'nullable|string|max:255',
        ]);

        BlockedFingerprint::create([
            'fingerprint' => $request->fingerprint,
            'video_id' => $request->video_id,
            'reason' => $request->reason ?? 'Blocked by admin',
        ]);

        ActiveSession::where('fingerprint', $request->fingerprint)
            ->where('status', 'active')
            ->update(['status' => 'terminated']);

        return back()->with('success', 'Fingerprint blocked and sessions terminated.');
    }

    public function unblockIp(BlockedIp $blockedIp)
    {
        $blockedIp->delete();
        return back()->with('success', 'IP unblocked.');
    }

    public function unblockFingerprint(BlockedFingerprint $blockedFingerprint)
    {
        $blockedFingerprint->delete();
        return back()->with('success', 'Fingerprint unblocked.');
    }
}
