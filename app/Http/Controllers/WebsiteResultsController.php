<?php

namespace App\Http\Controllers;

use App\Services\CareerPathService;
use App\Services\HiringApiService;
use Illuminate\Http\Request;

class WebsiteResultsController extends Controller
{
    private CareerPathService $career;
    private HiringApiService $hiring;

    public function __construct(CareerPathService $career, HiringApiService $hiring)
    {
        $this->career = $career;
        $this->hiring = $hiring;

        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            if (!\Auth::check() || !\Auth::user()->canViewWebsitesDashboard()) {
                abort(403, 'You do not have permission to view this section.');
            }
            return $next($request);
        });
    }

    // ================================================================
    // Career Path (Supabase)
    // ================================================================

    public function careerDashboard()
    {
        $stats = $this->career->getStats();
        $recentUsers = $this->career->getUsers(1, 5);
        $recentAssessments = $this->career->getAssessments(1, 5);
        $recentCvs = $this->career->getCvs(1, 5);

        return view('websites_results.career_dashboard', compact(
            'stats', 'recentUsers', 'recentAssessments', 'recentCvs'
        ));
    }

    public function careerUsers(Request $request)
    {
        $page   = max(1, (int) $request->input('page', 1));
        $search = trim((string) $request->input('q', ''));
        $result = $this->career->getUsers($page, 20, $search);

        return view('websites_results.career_users', [
            'users'       => $result['users'] ?? [],
            'total'       => $result['total'] ?? 0,
            'currentPage' => $page,
            'limit'       => $result['limit'] ?? 20,
            'search'      => $search,
        ]);
    }

    public function careerAssessments(Request $request)
    {
        $page  = max(1, (int) $request->input('page', 1));
        $track = trim((string) $request->input('track', ''));
        $result = $this->career->getAssessments($page, 20, $track);

        return view('websites_results.career_assessments', [
            'assessments' => $result['assessments'] ?? [],
            'total'       => $result['total'] ?? 0,
            'currentPage' => $page,
            'limit'       => $result['limit'] ?? 20,
            'track'       => $track,
        ]);
    }

    public function careerCvs(Request $request)
    {
        $page   = max(1, (int) $request->input('page', 1));
        $result = $this->career->getCvs($page, 20);

        return view('websites_results.career_cvs', [
            'cvs'         => $result['cvs'] ?? [],
            'total'       => $result['total'] ?? 0,
            'currentPage' => $page,
            'limit'       => $result['limit'] ?? 20,
        ]);
    }

    // ================================================================
    // Hiring Site
    // ================================================================

    public function hiringJobs(Request $request)
    {
        $page      = max(1, (int) $request->input('page', 1));
        $companyId = $request->input('company_id') ? (int) $request->input('company_id') : null;
        $jobs      = $this->hiring->getJobs($page, $companyId);

        return view('websites_results.hiring_jobs', [
            'jobs'        => $jobs,
            'currentPage' => $page,
            'companyId'   => $companyId,
        ]);
    }

    public function hiringPosts(Request $request)
    {
        $page  = max(1, (int) $request->input('page', 1));
        $posts = $this->hiring->getPosts($page, 12);

        return view('websites_results.hiring_posts', [
            'posts'       => $posts,
            'currentPage' => $page,
        ]);
    }
}
