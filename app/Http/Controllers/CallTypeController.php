<?php

namespace App\Http\Controllers;

use App\Models\CallType;
use Illuminate\Http\Request;

class CallTypeController extends Controller
{
    public function index()
    {
        if (\Auth::user()->can('create lead call')) {
            $types = CallType::where('created_by', '=', \Auth::user()->creatorId())->get();
            return view('call_type.index', compact('types'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function create()
    {
        return view('call_type.create');
    }

    public function store(Request $request)
    {
        if (\Auth::user()->can('create lead call')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'name' => 'required',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return redirect()->back()->with('error', $messages->first());
            }

            $types = new CallType();
            $types->name = $request->name;
            $types->created_by = \Auth::user()->creatorId();
            $types->save();

            return redirect()->route('call_type.index')->with('success', __('Call Type successfully created.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function edit(CallType $callType)
    {
        return view('call_type.edit', compact('callType'));
    }

    public function update(Request $request, CallType $callType)
    {
        if (\Auth::user()->can('edit lead call')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'name' => 'required',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return redirect()->back()->with('error', $messages->first());
            }

            $callType->name = $request->name;
            $callType->created_by = \Auth::user()->creatorId();
            $callType->save();

            return redirect()->route('call_type.index')->with('success', __('Call Type successfully updated.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function destroy(CallType $callType)
    {
        if (\Auth::user()->can('delete lead call')) {
            $callType->delete();
            return redirect()->route('call_type.index')->with('success', __('Call Type successfully deleted.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }
}
