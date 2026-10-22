<?php

namespace App\Http\Controllers;

use App\Services\InactivityAudit;
use App\Support\Inactivity\InactivityConfig;
use Illuminate\Http\Request;

class InactivityReportController extends Controller
{
    public function index(InactivityAudit $audit)
    {
        if (!\Auth::user()->can('manage inactivity report')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $report = $audit->scan();

        $recent = \DB::table('inactivity_ban_logs as l')
            ->leftJoin('users as u', 'u.id', '=', 'l.user_id')
            ->select('l.*', 'u.name as user_name')
            ->orderByDesc('l.id')
            ->limit(30)
            ->get();

        return view('report.inactivity', [
            'report'      => $report,
            'recent'      => $recent,
            'settings'    => InactivityConfig::all(),
            'exemptUsers' => InactivityConfig::exemptUsers(),
        ]);
    }

    public function unban($id, InactivityAudit $audit)
    {
        if (!\Auth::user()->can('manage inactivity ban')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $done = $audit->unban((int) $id, 'user:' . \Auth::id());

        return redirect()->route('report.inactivity')->with(
            $done ? 'success' : 'error',
            $done
                ? __('Login restored.')
                : __('Nothing to lift — this account was not blocked automatically.')
        );
    }

    /** Toggle a user on/off the permanent exempt list. */
    public function exempt($id, InactivityAudit $audit)
    {
        if (!\Auth::user()->can('manage inactivity ban')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $id     = (int) $id;
        $pinned = in_array($id, InactivityConfig::exemptUsers(), true);

        $pinned
            ? $audit->unpinExempt($id, 'user:' . \Auth::id())
            : $audit->pinExempt($id, 'user:' . \Auth::id());

        return redirect()->route('report.inactivity')->with(
            'success',
            $pinned ? __('Removed from the exempt list') : __('Pinned as exempt by an administrator')
        );
    }

    public function saveSettings(Request $request)
    {
        if (!\Auth::user()->can('manage inactivity ban')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $request->validate([
            'inactivity_threshold_days' => 'required|integer|min:1|max:365',
            'inactivity_grace_days'     => 'required|integer|min:0|max:365',
            'inactivity_report_emails'  => 'nullable|string|max:500',
            'inactivity_exempt_roles'   => 'nullable|string|max:1000',
        ]);

        InactivityConfig::put('inactivity_enforce', $request->boolean('inactivity_enforce') ? '1' : '0');
        InactivityConfig::put('inactivity_threshold_days', (string) $request->input('inactivity_threshold_days'));
        InactivityConfig::put('inactivity_grace_days', (string) $request->input('inactivity_grace_days'));
        InactivityConfig::put('inactivity_report_emails', (string) $request->input('inactivity_report_emails', ''));
        InactivityConfig::put('inactivity_exempt_roles', (string) $request->input('inactivity_exempt_roles', ''));

        return redirect()->route('report.inactivity')->with('success', __('Settings saved.'));
    }

    /**
     * Run the sweep on demand.
     *
     * Always passes --dry-run: the button is for producing a report now, never for
     * triggering a mass ban from a stray click. Enforcement stays the cron's job.
     */
    public function runNow()
    {
        if (!\Auth::user()->can('manage inactivity ban')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        \Artisan::call('inactivity:weekly-run', ['--dry-run' => true]);

        return redirect()->route('report.inactivity')
            ->with('success', __('Report generated and emailed.'));
    }
}
