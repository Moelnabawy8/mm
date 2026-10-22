<?php

namespace App\Http\Controllers;

use App\Models\SalesMeetingType;
use Illuminate\Http\Request;

class SalesMeetingTypeController extends Controller
{
    public function index()
    {
        if (\Auth::user()->can('manage lead stage')) {
            $types = SalesMeetingType::where('created_by', '=', \Auth::user()->creatorId())->get();
            return view('sales_meeting_types.index', compact('types'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function create()
    {
        if (\Auth::user()->can('create lead stage')) {
            return view('sales_meeting_types.create');
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    public function store(Request $request)
    {
        if (\Auth::user()->can('create lead stage')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'name' => 'required|max:20',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }

            $type = new SalesMeetingType();
            $type->name = $request->name;
            $type->created_by = \Auth::user()->creatorId();
            $type->save();

            return redirect()->route('sales-meeting-types.index')->with('success', __('Sales Meeting Type successfully created.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function edit($id)
    {
        if (\Auth::user()->can('edit lead stage')) {
            $salesMeetingType = SalesMeetingType::find($id);

            return view('sales_meeting_types.edit', compact('salesMeetingType'));
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    public function update(Request $request, $id)
    {
        if (\Auth::user()->can('edit lead stage')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'name' => 'required|max:20',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }

            $type = SalesMeetingType::find($id);
            $type->name = $request->name;
            $type->save();

            return redirect()->route('sales-meeting-types.index')->with('success', __('Sales Meeting Type successfully updated.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function destroy($id)
    {
        if (\Auth::user()->can('delete lead stage')) {
            $type = SalesMeetingType::find($id);
            $type->delete();

            return redirect()->route('sales-meeting-types.index')->with('success', __('Sales Meeting Type successfully deleted.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }
}
