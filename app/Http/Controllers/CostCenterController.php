<?php

namespace App\Http\Controllers;

use App\Models\CostCenter;
use Illuminate\Http\Request;

class CostCenterController extends Controller
{
    public function index()
    {
        $costCenters = CostCenter::where('created_by', \Auth::user()->creatorId())->get();
        return view('costcenter.index', compact('costCenters'));
    }

    public function create()
    {
        return view('costcenter.create');
    }

    public function store(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'name' => 'required|max:100',
            'code' => 'nullable|max:20',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->with('error', $validator->getMessageBag()->first());
        }

        CostCenter::create([
            'name'        => $request->name,
            'code'        => $request->code,
            'description' => $request->description,
            'status'      => $request->status ?? 'active',
            'created_by'  => \Auth::user()->creatorId(),
        ]);

        return redirect()->route('cost-center.index')->with('success', __('Cost Center created successfully.'));
    }

    public function edit(CostCenter $costCenter)
    {
        if ($costCenter->created_by != \Auth::user()->creatorId()) {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
        return view('costcenter.edit', compact('costCenter'));
    }

    public function update(Request $request, CostCenter $costCenter)
    {
        if ($costCenter->created_by != \Auth::user()->creatorId()) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $validator = \Validator::make($request->all(), [
            'name' => 'required|max:100',
            'code' => 'nullable|max:20',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->with('error', $validator->getMessageBag()->first());
        }

        $costCenter->update([
            'name'        => $request->name,
            'code'        => $request->code,
            'description' => $request->description,
            'status'      => $request->status ?? 'active',
        ]);

        return redirect()->route('cost-center.index')->with('success', __('Cost Center updated successfully.'));
    }

    public function destroy(CostCenter $costCenter)
    {
        if ($costCenter->created_by != \Auth::user()->creatorId()) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $costCenter->delete();
        return redirect()->route('cost-center.index')->with('success', __('Cost Center deleted successfully.'));
    }
}
