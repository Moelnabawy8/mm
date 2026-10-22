<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AllowedDomain;
use Illuminate\Http\Request;

class DomainController extends Controller
{
    public function index()
    {
        $domains = AllowedDomain::with('video')->latest()->paginate(15);
        return view('admin.domains.index', compact('domains'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'domain' => 'required|string|max:255',
            'video_id' => 'nullable|exists:videos,id',
        ]);

        AllowedDomain::create($validated);

        return redirect()->route('admin.domains.index')
            ->with('success', 'Domain added successfully.');
    }

    public function destroy(AllowedDomain $domain)
    {
        $domain->delete();

        return redirect()->route('admin.domains.index')
            ->with('success', 'Domain removed successfully.');
    }
}
