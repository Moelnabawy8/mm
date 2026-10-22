<?php

namespace App\Http\Controllers;

use App\Services\SeoAnalysisService;
use Illuminate\Http\Request;

class WebsiteSeoController extends Controller
{
    private SeoAnalysisService $seo;

    public function __construct(SeoAnalysisService $seo)
    {
        $this->seo = $seo;

        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            if (!\Auth::check() || !\Auth::user()->canViewWebsitesDashboard()) {
                abort(403, 'You do not have permission to view this section.');
            }
            return $next($request);
        });
    }

    public function arabic(Request $request)
    {
        $data = $request->has('refresh')
            ? $this->seo->refresh('arabic')
            : $this->seo->analyze('arabic');

        return view('websites_seo.report', ['data' => $data, 'site' => 'arabic']);
    }

    public function english(Request $request)
    {
        $data = $request->has('refresh')
            ? $this->seo->refresh('english')
            : $this->seo->analyze('english');

        return view('websites_seo.report', ['data' => $data, 'site' => 'english']);
    }
}
