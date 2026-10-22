<?php

namespace App\Http\Controllers;

use App\Models\Sheet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SheetController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $sheets = Sheet::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('sheets.index', compact('sheets'));
    }

    public function show($id)
    {
        $userId = Auth::id();
        $sheet = Sheet::where('user_id', $userId)
            ->where('id', $id)
            ->first();

        if (!$sheet) {
            return redirect()->route('google-sheet.index')
                ->with('error', 'Sheet not found');
        }

        return view('sheets.show', compact('sheet'));
    }

    public function save(Request $request, $id)
    {
        $userId = Auth::id();
        $sheet = Sheet::where('user_id', $userId)
            ->where('id', $id)
            ->first();

        if (!$sheet) {
            return response()->json(['status' => 'error', 'message' => 'Sheet not found'], 404);
        }

        $validatedData = $request->validate([
            'data' => 'required|array',
        ]);

        $sheet->data = $validatedData['data'];
        $sheet->save();

        return response()->json(['status' => 'saved']);
    }

    public function create(Request $request)
    {
        $userId = Auth::id();

        $validatedData = $request->validate([
            'name' => 'nullable|string|max:255'
        ]);

        $defaultData = array_fill(0, 10, array_fill(0, 10, ''));

        $sheet = Sheet::create([
            'user_id' => $userId,
            'name' => $validatedData['name'] ?? 'New Sheet',
            'data' => $defaultData
        ]);

        return redirect()->route('google-sheet.show', $sheet->id)
            ->with('success', __('Created'));
    }

    public function delete($id)
    {
        $userId = Auth::id();
        $sheet = Sheet::where('user_id', $userId)
            ->where('id', $id)
            ->first();

        if (!$sheet) {
            return redirect()->route('google-sheet.index')
                ->with('error', 'Sheet not found');
        }

        $sheet->delete();

        return redirect()->route('google-sheet.index')
            ->with('success', __('Delete'));
    }
}
