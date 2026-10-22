<?php

namespace App\Http\Controllers;

use App\Models\Interest;
use Illuminate\Http\Request;

class InterestController extends Controller
{
    public function __construct()
    {
        $this->middleware(
            [
                'auth',
                'XSS',
            ]
        );
    }

    public function index()
    {
        if (\Auth::user()->can('manage lead')) {
            $interests = Interest::where('created_by', '=', \Auth::user()->creatorId())->get();

            return view('interests.index')->with('interests', $interests);
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    public function create()
    {
        if (\Auth::user()->can('create lead')) {
            return view('interests.create');
        } else {
            return response()->json(['error' => __('Permission Denied.')], 401);
        }
    }

    public function store(Request $request)
    {
        if (\Auth::user()->can('create lead')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'name' => 'required|max:50',
                ]
            );

            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->route('interests.index')->with('error', $messages->first());
            }

            $interest = new Interest();
            $interest->name = $request->name;
            $interest->created_by = \Auth::user()->creatorId();
            $interest->save();

            return redirect()->route('interests.index')->with('success', __('Interest successfully created!'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    public function edit(Interest $interest)
    {
        if (\Auth::user()->can('edit lead')) {
            if ($interest->created_by == \Auth::user()->creatorId()) {
                return view('interests.edit', compact('interest'));
            } else {
                return response()->json(['error' => __('Permission Denied.')], 401);
            }
        } else {
            return response()->json(['error' => __('Permission Denied.')], 401);
        }
    }

    public function update(Request $request, Interest $interest)
    {
        if (\Auth::user()->can('edit lead')) {
            if ($interest->created_by == \Auth::user()->creatorId()) {
                $validator = \Validator::make(
                    $request->all(),
                    [
                        'name' => 'required|max:50',
                    ]
                );

                if ($validator->fails()) {
                    $messages = $validator->getMessageBag();

                    return redirect()->route('interests.index')->with('error', $messages->first());
                }

                $interest->name = $request->name;
                $interest->save();

                return redirect()->route('interests.index')->with('success', __('Interest successfully updated!'));
            } else {
                return redirect()->back()->with('error', __('Permission Denied.'));
            }
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    public function destroy(Interest $interest)
    {
        if (\Auth::user()->can('delete lead')) {
            if ($interest->created_by == \Auth::user()->creatorId()) {
                $interest->delete();

                return redirect()->route('interests.index')->with('success', __('Interest successfully deleted!'));
            } else {
                return redirect()->back()->with('error', __('Permission Denied.'));
            }
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }
}
