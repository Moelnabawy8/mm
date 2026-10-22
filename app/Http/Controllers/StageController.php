<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\Stage;
use Illuminate\Http\Request;

class StageController extends Controller
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

    /**
     * Display a listing of the restage.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (\Auth::user()->can('manage stage')) {
            $stages = Stage::where('created_by', '=', \Auth::user()->creatorId())->orderBy('order')->get();
            return view('deal_statuses.index')->with('stages', $stages);
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    /**
     * Show the form for creating a new restage.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (\Auth::user()->can('create stage')) {
            return view('deal_statuses.create');
        } else {
            return response()->json(['error' => __('Permission Denied.')], 401);
        }
    }

    /**
     * Store a newly created restage in storage.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (\Auth::user()->can('create stage')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'name' => 'required|max:20',
                ]
            );

            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return redirect()->route('deal-statuses.index')->with('error', $messages->first());
            }
            $stage = new Stage();
            $stage->name = $request->name;
            $stage->created_by = \Auth::user()->creatorId();
            $stage->save();

            return redirect()->route('deal-statuses.index')->with('success', __('Deal Status successfully created!'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    /**
     * Display the specified restage.
     *
     * @param \App\Stage $deal_status
     *
     * @return \Illuminate\Http\Response
     */
    public function show(Stage $deal_status)
    {
        return redirect()->route('deal-statuses.index');
    }

    /**
     * Show the form for editing the specified restage.
     *
     * @param \App\Stage $deal_status
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(Stage $deal_status)
    {
        // No permission check here to match CallTypeController::edit behavior
        // The view button is already protected by @can check.
        return view('deal_statuses.edit', ['stage' => $deal_status]);
    }

    /**
     * Update the specified restage in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Stage $deal_status
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Stage $deal_status)
    {
        if (\Auth::user()->can('edit stage')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'name' => 'required|max:20',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return redirect()->route('deal-statuses.index')->with('error', $messages->first());
            }

            $deal_status->name = $request->name;
            $deal_status->save();
            return redirect()->route('deal-statuses.index')->with('success', __('Deal Status successfully updated!'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    /**
     * Remove the specified restage from storage.
     *
     * @param \App\Stage $deal_status
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(Stage $deal_status)
    {
        if (\Auth::user()->can('delete stage')) {
            $deals = Deal::where('stage_id', '=', $deal_status->id)->where('created_by', '=', $deal_status->created_by)->count();

            if ($deals == 0) {
                $deal_status->delete();

                return redirect()->route('deal-statuses.index')->with('success', __('Deal Status successfully deleted!'));
            } else {
                return redirect()->route('deal-statuses.index')->with('error', __('There are some deals on status, please remove it first!'));
            }
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    public function order(Request $request)
    {
        $post = $request->all();
        foreach ($post['order'] as $key => $item) {
            $stage = Stage::where('id', '=', $item)->first();
            $stage->order = $key;
            $stage->save();
        }
    }

    public function json(Request $request)
    {
        $stage = new Stage();
        // Return all stages or implement new logic if needed, previously filtered by pipeline
        $stage = $stage->get()->pluck('name', 'id');
        return response()->json($stage);
    }
}
