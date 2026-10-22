<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SecurityPreset;
use Illuminate\Http\Request;

class PresetController extends Controller
{
    public function index()
    {
        $presets = SecurityPreset::withCount('videos')->get();
        return view('admin.presets.index', compact('presets'));
    }

    public function create()
    {
        return view('admin.presets.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'settings' => 'required|array',
        ]);

        SecurityPreset::create($validated);

        return redirect()->route('admin.presets.index')
            ->with('success', 'Preset created successfully.');
    }

    public function edit(SecurityPreset $preset)
    {
        return view('admin.presets.edit', compact('preset'));
    }

    public function update(Request $request, SecurityPreset $preset)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'settings' => 'required|array',
        ]);

        $preset->update($validated);

        return redirect()->route('admin.presets.index')
            ->with('success', 'Preset updated successfully.');
    }

    public function destroy(SecurityPreset $preset)
    {
        $preset->delete();
        return redirect()->route('admin.presets.index')
            ->with('success', 'Preset deleted successfully.');
    }
}
