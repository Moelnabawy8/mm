<?php

namespace App\Http\Controllers;

use App\Models\Label;
use App\Models\Lead;
use App\Models\LeadActivityLog;
use App\Models\LeadEmail;
use App\Models\LeadFile;
use App\Models\LeadDiscussion;
use App\Models\LeadStage;
use App\Models\Pipeline;
use App\Models\ProductService;
use App\Models\Source;
use App\Models\Stage;
use App\Models\User;

use App\Models\UserLead;
use App\Models\Utility;
use App\Models\Employee;
use App\Models\Department;
use App\Models\WebhookSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\LeadExport;
use App\Imports\LeadImport;
use App\Services\LeadScope;

class LeadController extends Controller
{
    /** Page sizes the lead views are allowed to request. */
    const PAGE_SIZES = [25, 50, 100, 250, 500, 1000];

    /**
     * Whether $usr is allowed to reach this individual lead, using exactly the
     * same rules index() uses to build the list. Assumes the lead already
     * belongs to the user's company.
     */
    private function canReachLead(Lead $lead, $usr)
    {
        // Sales Manager sees only leads assigned to someone in their sales group.
        if ($usr->hasRole('Sales Manager') || $usr->hasRole('sales manager')) {
            if (!$usr->sales_group) {
                return false;
            }
            $groupUserIds = User::where('created_by', $usr->creatorId())
                ->where('sales_group', $usr->sales_group)
                ->pluck('id')->toArray();

            return UserLead::where('lead_id', $lead->id)->whereIn('user_id', $groupUserIds)->exists();
        }

        if (in_array($usr->type, LeadScope::PRIVILEGED_TYPES) || $usr->can('view all leads')) {
            return true;
        }

        return UserLead::where('lead_id', $lead->id)->where('user_id', $usr->id)->exists();
    }

    /**
     * The lead identified by $id, or null when it does not exist or belongs to
     * another company. Use instead of Lead::find() in every lead endpoint.
     */
    private function ownedLead($id)
    {
        return Lead::where('id', $id)->where('created_by', \Auth::user()->creatorId())->first();
    }

    public function checkUniqueness(Request $request)
    {
        $creatorId = \Auth::user()->creatorId();
        $exists = false;
        $message = '';

        if ($request->has('email') && !empty($request->email)) {
            $query = Lead::where('email', $request->email)->where('created_by', $creatorId);
            if ($request->has('lead_id')) {
                $query->where('id', '!=', $request->lead_id);
            }
            if ($query->exists()) {
                $exists = true;
                $message = __('The email has already been taken.');
            }
        }

        if (!$exists && $request->has('phone') && !empty($request->phone)) {
            if ($this->phoneAlreadyTaken($request->phone, $request->lead_id)) {
                $exists = true;
                $message = __('The phone has already been taken.');
            }
        }

        return response()->json(['exists' => $exists, 'message' => $message]);
    }

    /**
     * Whether this phone number already belongs to another lead of the same
     * company. Compares the cleaned form on both sides, because that is the form
     * the number is stored in - comparing the raw input let the very same number
     * through again just by typing it with dashes or spaces.
     */
    private function phoneAlreadyTaken($phone, $excludeLeadId = null)
    {
        $clean = $this->cleanPhoneNumbers($phone);
        if (empty($clean)) {
            return false;
        }

        $inputNums = explode(',', $clean);

        $query = Lead::where('created_by', \Auth::user()->creatorId())
            ->where(function ($q) use ($inputNums) {
                foreach ($inputNums as $n) {
                    $q->orWhere('phone', 'LIKE', '%' . $n . '%');
                }
            });

        if (!empty($excludeLeadId)) {
            $query->where('id', '!=', $excludeLeadId);
        }

        foreach ($query->get() as $l) {
            foreach (explode(',', (string) $l->phone) as $dbN) {
                if (in_array(preg_replace('/[^0-9]/', '', $dbN), $inputNums, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (\Auth::user()->can('manage lead') || in_array(\Auth::user()->type, ['CSO', 'Alaa,Saba,aber', 'fadi'])) {
            if ($request->pipeline) {
                $pipeline = Pipeline::where('created_by', '=', \Auth::user()->creatorId())->where('id', '=', $request->pipeline)->first();
            } elseif (\Auth::user()->default_pipeline) {
                $pipeline = Pipeline::where('created_by', '=', \Auth::user()->creatorId())->where('id', '=', \Auth::user()->default_pipeline)->first();
                if (!$pipeline) {
                    $pipeline = Pipeline::where('created_by', '=', \Auth::user()->creatorId())->first();
                }
            } else {
                $pipeline = Pipeline::where('created_by', '=', \Auth::user()->creatorId())->first();
            }

            if (!$pipeline) {
                return redirect()->back()->with('error', __('Please create a pipeline first.'));
            }

            $pipelines = Pipeline::where('created_by', '=', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            // Sales people only - the filter used to list every employee in the company.
            $users = LeadScope::salesUsersQuery(\Auth::user())->get()->pluck('name', 'id');
            $products = ProductService::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $sources = Source::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $labels = Label::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $interests = \App\Models\Interest::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $stages = LeadStage::where('pipeline_id', '=', $pipeline->id)->where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $dealStatuses = \App\Services\LeadScope::dealStatuses(\Auth::user()->creatorId());
            // The same countries the create/edit form offers. Building this from
            // the values stored in leads.country listed phone numbers as countries.
            $countries = LeadScope::COUNTRIES;

            if ($request->ajax()) {
                $usr = \Auth::user();
                $query = Lead::select('leads.*')
                    ->where('leads.pipeline_id', '=', $pipeline->id);

                LeadScope::applyVisibility($query, $usr);
                LeadScope::applyFilters($query, $request, $usr);
                $limit = (int) $request->get('limit', 25);
                if (!in_array($limit, self::PAGE_SIZES)) {
                    $limit = 25;
                }
                $leads = $query->with(['users', 'stage'])
                    ->withCount(['deals', 'salesMeetings', 'dealPurchases'])
                    ->orderByRaw('CAST(COALESCE(leads.date, leads.created_at) AS DATETIME) DESC')
                    ->paginate($limit);

                $stage_leads = $leads->groupBy('stage_id');
                $lead_stages = $pipeline->leadStages;

                return view('leads.kanban_board', compact('stage_leads', 'lead_stages', 'pipeline', 'leads'));
            }

            // For non-AJAX request, also fetch leads to pass to the view (so we have sorting)
            $usr = \Auth::user();
            $query = Lead::select('leads.*')
                ->where('leads.pipeline_id', '=', $pipeline->id);

            LeadScope::applyVisibility($query, $usr);
            // Was a hard-coded 25, so the per-page selector changed nothing on the
            // first render. Same rule as the AJAX branch above and as /leads/list:
            // anything that is not one of the offered sizes falls back to 25.
            $limit = (int) $request->get('limit', 25);
            if (!in_array($limit, self::PAGE_SIZES)) {
                $limit = 25;
            }

            $leads = $query->with(['users', 'stage'])
                ->withCount(['deals', 'salesMeetings', 'dealPurchases'])
                ->orderByRaw('CAST(COALESCE(leads.date, leads.created_at) AS DATETIME) DESC')
                ->paginate($limit);

            $stage_leads = $leads->groupBy('stage_id');

            $campaigns = Lead::where('created_by', '=', $usr->creatorId())
                ->whereNotNull('campaign_name')
                ->where('campaign_name', '!=', '')
                ->distinct()
                ->pluck('campaign_name');

            return view('leads.index', compact('pipelines', 'pipeline', 'users', 'products', 'sources', 'labels', 'interests', 'stages', 'stage_leads', 'countries', 'leads', 'campaigns', 'dealStatuses'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    public function lead_list()
    {
        $usr = \Auth::user();

        if ($usr->can('manage lead')) {
            if ($usr->default_pipeline) {
                $pipeline = Pipeline::where('created_by', '=', $usr->creatorId())->where('id', '=', $usr->default_pipeline)->first();
                if (!$pipeline) {
                    $pipeline = Pipeline::where('created_by', '=', $usr->creatorId())->first();
                }
            } else {
                $pipeline = Pipeline::where('created_by', '=', $usr->creatorId())->first();
            }

            if (!$pipeline) {
                return redirect()->back()->with('error', __('Please create a pipeline first.'));
            }

            $pipelines = Pipeline::where('created_by', '=', $usr->creatorId())->get()->pluck('name', 'id');

            $query = Lead::select('leads.*')
                ->where('leads.pipeline_id', '=', $pipeline->id);

            LeadScope::applyVisibility($query, $usr);
            $limit = (int) request('limit', 25);
            if (!in_array($limit, self::PAGE_SIZES)) { $limit = 25; }

            $leads = $query->with(['users', 'stage'])
                ->withCount(['deals', 'salesMeetings', 'dealPurchases'])
                ->orderByRaw('CAST(COALESCE(leads.date, leads.created_at) AS DATETIME) DESC')
                ->paginate($limit);

            return view('leads.list', compact('pipelines', 'pipeline', 'leads'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {


        if (\Auth::user()->can('create lead')) {

            $pipelines = Pipeline::where('created_by', '=', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $pipelines->prepend(__('Select Pipeline'), '');
            $sources = Source::where('created_by', '=', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $products = ProductService::where('created_by', '=', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $labels = Label::where('created_by', '=', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $interests = \App\Models\Interest::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');

            return view('leads.create', compact('pipelines', 'sources', 'products', 'labels', 'interests'));
        } else {
            return response()->json(['error' => __('Permission Denied.')], 401);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        $usr = \Auth::user();
        if ($usr->can('create lead')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'subject' => 'nullable',
                    'name' => 'required',
                    'email' => [
                        'nullable',
                        'email',
                        function ($attribute, $value, $fail) {
                            if (!empty($value) && Lead::where('email', $value)->where('created_by', \Auth::user()->creatorId())->exists()) {
                                $fail(__('The email has already been taken.'));
                            }
                        },
                    ],
                    'phone' => [
                        'nullable',
                        function ($attribute, $value, $fail) {
                            if (!empty($value) && $this->phoneAlreadyTaken($value)) {
                                $fail(__('The phone has already been taken.'));
                            }
                        },
                    ],
                ]
            );

            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }

            $pipeline = null;
            $stage = null;

            if ($request->pipeline_id) {
                $pipeline = Pipeline::where('created_by', '=', $usr->creatorId())->where('id', '=', $request->pipeline_id)->first();
            } elseif ($usr->default_pipeline) {
                $pipeline = Pipeline::where('created_by', '=', $usr->creatorId())->where('id', '=', $usr->default_pipeline)->first();
            } else {
                $pipeline = Pipeline::where('created_by', '=', $usr->creatorId())->first();
            }

            if ($request->stage_id) {
                $stage = LeadStage::where('created_by', '=', $usr->creatorId())->where('id', '=', $request->stage_id)->first();
            } elseif ($pipeline) {
                $stage = LeadStage::where('pipeline_id', '=', $pipeline->id)->first();
            }

            $lead = new Lead();
            $lead->name = $request->name;
            $lead->subject = $request->subject ?? '';
            $lead->email = $request->email;
            $lead->phone = $request->phone ? $this->cleanPhoneNumbers($request->phone) : null;
            $lead->user_id = $request->user_id ?? \Auth::user()->id;
            $lead->pipeline_id = $pipeline ? $pipeline->id : null;
            $lead->stage_id = $stage ? $stage->id : null;
            $lead->created_by = $usr->creatorId();
            $lead->date = date('Y-m-d');

            // New fields
            $lead->gender = $request->gender;
            $lead->country = $request->country;
            $lead->city = $request->city;
            $lead->costing_type = $request->costing_type;
            $lead->campaign_name = $request->campaign_name;
            $lead->cost = $request->cost !== null && $request->cost !== "" ? $request->cost : 0;
            $lead->notes = $request->notes;
            $lead->telegram = $request->telegram;

            if ($request->sources) {
                $lead->sources = implode(",", array_filter($request->sources));
            }
            if ($request->products) {
                $lead->products = implode(",", array_filter($request->products));
            }
            if ($request->labels) {
                $lead->labels = implode(",", array_filter($request->labels));
            }
            if ($request->interests) {
                $lead->interests = implode(",", array_filter($request->interests));
            }
            $lead->save();


            if ($lead->user_id != \Auth::user()->id) {
                $usrLeads = [
                    $usr->id,
                    $lead->user_id,
                ];
            } else {
                $usrLeads = [
                    $lead->user_id,
                ];
            }

            foreach ($usrLeads as $usrLead) {
                UserLead::create(
                    [
                        'user_id' => $usrLead,
                        'lead_id' => $lead->id,
                    ]
                );
            }

            $leadArr = [
                'lead_id' => $lead->id,
                'name' => $lead->name,
                'updated_by' => $usr->id,
            ];
            $lArr = [
                'lead_name' => $lead->name,
                'lead_email' => $lead->email,
                'lead_pipeline' => $pipeline ? $pipeline->name : '',
                'lead_stage' => $stage ? $stage->name : '',
            ];

            $usrEmail = User::find($request->user_id);

            $lArr = [
                'lead_name' => $lead->name,
                'lead_email' => $lead->email,
                'lead_pipeline' => $pipeline ? $pipeline->name : '',
                'lead_stage' => $stage ? $stage->name : '',
            ];

            // Send Email. The lead is already saved at this point, so a mail or
            // notification problem must not turn a successful creation into an
            // error page.
            try {
                $setings = Utility::settings();
                if (!empty($setings['lead_assigned']) && $setings['lead_assigned'] == 1 && $lead->user_id) {
                    $usrEmail = User::find($lead->user_id);
                    if ($usrEmail) {
                        $leadAssignArr = [
                            'lead_name' => $lead->name,
                            'lead_email' => $lead->email,
                            'lead_subject' => $lead->subject,
                            'lead_pipeline' => $pipeline ? $pipeline->name : '',
                            'lead_stage' => $stage ? $stage->name : '',
                        ];
                        $resp = Utility::sendEmailTemplate('lead_assigned', [$usrEmail->id => $usrEmail->email], $leadAssignArr);
                    }
                }
            } catch (\Throwable $e) {
                \Log::error('Lead assignment email failed for lead ' . $lead->id . ': ' . $e->getMessage());
            }

            //For Notification
            try {
                $setting = Utility::settings(\Auth::user()->creatorId());
                $leadArr = [
                    'user_name' => \Auth::user()->name,
                    'lead_name' => $lead->name,
                    'lead_email' => $lead->email,
                ];
                //Slack Notification
                if (isset($setting['lead_notification']) && $setting['lead_notification'] == 1) {
                    Utility::send_slack_msg('new_lead', $leadArr);
                }

                //Telegram Notification
                if (isset($setting['telegram_lead_notification']) && $setting['telegram_lead_notification'] == 1) {
                    Utility::send_telegram_msg('new_lead', $leadArr);
                }
            } catch (\Throwable $e) {
                \Log::error('Lead notification failed for lead ' . $lead->id . ': ' . $e->getMessage());
            }

            //webhook
            try {
                $module = 'New Lead';
                $webhook = Utility::webhookSetting($module);
                if ($webhook) {
                    $parameter = json_encode($lead);
                    // 1 parameter is  URL , 2 parameter is data , 3 parameter is method
                    $status = Utility::WebhookCall($webhook['url'], $parameter, $webhook['method']);
                    if ($status == true) {
                        return redirect()->back()->with('success', __('Lead successfully created!') . ((!empty($resp) && $resp['is_success'] == false && !empty($resp['error'])) ? '<br> <span class="text-danger">' . $resp['error'] . '</span>' : ''));
                    } else {
                        return redirect()->back()->with('error', __('Lead successfully created, Webhook call failed.'));
                    }
                }
            } catch (\Throwable $e) {
                \Log::error('Lead webhook failed for lead ' . $lead->id . ': ' . $e->getMessage());
            }
            return redirect()->back()->with('success', __('Lead successfully created!') . ((!empty($resp) && $resp['is_success'] == false && !empty($resp['error'])) ? '<br> <span class="text-danger">' . $resp['error'] . '</span>' : ''));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    /**
     * Display the specified resource.
     *
     * @param \App\Lead $lead
     *
     * @return \Illuminate\Http\Response
     */
    public function show(Lead $lead)
    {
        $usr = \Auth::user();

        if (!$usr->can('manage lead') && !in_array($usr->type, ['CSO', 'Alaa,Saba,aber', 'fadi'])) {
            return redirect()->route('dashboard')->with('error', __('Permission Denied.'));
        }

        if ($lead->created_by != $usr->creatorId()) {
            return redirect()->route('dashboard')->with('error', __('Permission Denied.'));
        }

        if (!$this->canReachLead($lead, $usr)) {
            return redirect()->route('leads.index')->with('error', __('Permission Denied.'));
        }

        if ($lead->is_active) {
            $calenderTasks = [];

            $stageCnt = LeadStage::where('pipeline_id', '=', $lead->pipeline_id)->where('created_by', '=', $lead->created_by)->get();
            $i = 0;
            foreach ($stageCnt as $stage) {
                $i++;
                if ($stage->id == $lead->stage_id) {
                    break;
                }
            }
            $precentage = count($stageCnt) > 0 ? number_format(($i * 100) / count($stageCnt)) : 0;

            $labels = Label::whereIn('id', explode(',', $lead->labels))->get();

            return view('leads.show', compact('lead', 'calenderTasks', 'precentage', 'labels'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param \App\Lead $lead
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(Lead $lead)
    {
        if (\Auth::user()->can('edit lead')) {
            if ($lead && $lead->created_by == \Auth::user()->creatorId()) {
                $pipelines = Pipeline::where('created_by', '=', \Auth::user()->creatorId())->get()->pluck('name', 'id');
                $pipelines->prepend(__('Select Pipeline'), '');
                $sources = Source::where('created_by', '=', \Auth::user()->creatorId())->get()->pluck('name', 'id');
                $products = ProductService::where('created_by', '=', \Auth::user()->creatorId())->get()->pluck('name', 'id');
                $labels = Label::where('created_by', '=', \Auth::user()->creatorId())->get()->pluck('name', 'id');
                $labels = Label::where('created_by', '=', \Auth::user()->creatorId())->get()->pluck('name', 'id');
                return view('leads.edit', compact('lead', 'pipelines', 'sources', 'products', 'labels'));
            } else {
                return response()->json(['error' => __('Permission Denied.')], 401);
            }
        } else {
            return response()->json(['error' => __('Permission Denied.')], 401);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Lead $lead
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Lead $lead)
    {
        if (\Auth::user()->can('edit lead')) {
            if ($lead && $lead->created_by == \Auth::user()->creatorId()) {
                $validator = \Validator::make(
                    $request->all(),
                    [
                        'subject' => 'nullable',
                        'name' => 'required',
                        'email' => [
                            'nullable',
                            'email',
                            function ($attribute, $value, $fail) use ($lead) {
                                if (!empty($value) && Lead::where('email', $value)->where('created_by', \Auth::user()->creatorId())->where('id', '!=', $lead->id)->exists()) {
                                    $fail(__('The email has already been taken.'));
                                }
                            },
                        ],
                        'phone' => [
                            'nullable',
                            function ($attribute, $value, $fail) use ($lead) {
                                if (!empty($value) && $this->phoneAlreadyTaken($value, $lead->id)) {
                                    $fail(__('The phone has already been taken.'));
                                }
                            },
                        ],
                        'pipeline_id' => 'nullable',
                        'user_id' => 'nullable',
                        'stage_id' => 'nullable',
                        'sources' => 'nullable',
                        'products' => 'nullable',
                    ]
                );

                if ($validator->fails()) {
                    $messages = $validator->getMessageBag();

                    return redirect()->back()->with('error', $messages->first());
                }

                $lead->name = $request->name;
                $lead->email = $request->email;
                $lead->phone = $request->phone ? $this->cleanPhoneNumbers($request->phone) : null;

                if ($request->has('user_id')) {
                    $lead->user_id = $request->user_id;
                }
                $lead->pipeline_id = $request->pipeline_id;
                $lead->stage_id = $request->stage_id;
                $lead->sources = !empty($request->sources) ? implode(",", array_filter($request->sources)) : null;
                $lead->products = !empty($request->products) ? implode(",", array_filter($request->products)) : null;
                $lead->labels = !empty($request->labels) ? implode(",", array_filter($request->labels)) : null;
                $lead->interests = !empty($request->interests) ? implode(",", array_filter($request->interests)) : null;
                $lead->notes = $request->notes;

                // New fields
                $lead->gender = $request->gender;
                $lead->country = $request->country;
                $lead->city = $request->city;
                $lead->costing_type = $request->costing_type;
                $lead->campaign_name = $request->campaign_name;
                $lead->cost = $request->cost !== null && $request->cost !== "" ? $request->cost : 0;
                $lead->telegram = $request->telegram;

                $lead->save();

                return redirect()->back()->with('success', __('Lead successfully updated!'));
            } else {
                return redirect()->back()->with('error', __('Permission Denied.'));
            }
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Lead $lead
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(Lead $lead)
    {
        if (\Auth::user()->can('delete lead')) {
            if ($lead && $lead->created_by == \Auth::user()->creatorId()) {
                LeadDiscussion::where('lead_id', '=', $lead->id)->delete();
                LeadFile::where('lead_id', '=', $lead->id)->delete();
                UserLead::where('lead_id', '=', $lead->id)->delete();
                LeadActivityLog::where('lead_id', '=', $lead->id)->delete();
                $lead->delete();

                return redirect()->back()->with('success', __('Lead successfully deleted!'));
            } else {
                return redirect()->back()->with('error', __('Permission Denied.'));
            }
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    public function bulkDestroy(Request $request)
    {
        if (!\Auth::user()->can('delete lead')) {
            return response()->json(['status' => 'error', 'message' => __('Permission Denied.')], 403);
        }

        $ids = array_filter(array_map('intval', $request->input('ids', [])));

        if (empty($ids)) {
            return response()->json(['status' => 'error', 'message' => __('No leads selected.')], 422);
        }

        $leads = Lead::whereIn('id', $ids)->where('created_by', \Auth::user()->creatorId())->get();

        $deleted = 0;
        foreach ($leads as $lead) {
            LeadDiscussion::where('lead_id', $lead->id)->delete();
            LeadFile::where('lead_id', $lead->id)->delete();
            UserLead::where('lead_id', $lead->id)->delete();
            LeadActivityLog::where('lead_id', $lead->id)->delete();
            $lead->delete();
            $deleted++;
        }

        return response()->json([
            'status' => 'success',
            'deleted' => $deleted,
            'message' => $deleted . ' ' . __('leads successfully deleted!'),
        ]);
    }

    public function json(Request $request)
    {
        if ($request->pipeline_id && !empty($request->pipeline_id)) {
            $lead_stages = LeadStage::where('pipeline_id', '=', $request->pipeline_id)
                ->orderBy('order')
                ->get()
                ->unique('name')
                ->pluck('name', 'id');
        } else {
            $lead_stages = [];
        }

        return response()->json($lead_stages);
    }

    public function changePipeline(Request $request)
    {
        $user = \Auth::user();
        $user->default_pipeline = $request->default_pipeline_id;
        $user->save();

        return redirect()->back();
    }

    public function jsonDetail($id)
    {
        $usr = \Auth::user();

        if (!$usr->can('manage lead') && !in_array($usr->type, ['CSO', 'Alaa,Saba,aber', 'fadi'])) {
            return response()->json(['error' => __('Permission Denied.')], 403);
        }

        $lead = $this->ownedLead($id);
        if ($lead && $this->canReachLead($lead, $usr)) {
            $lead->product_details = $lead->products();
            return response()->json($lead);
        }
        return response()->json([]);
    }

    public function noteStore($id, Request $request)
    {
        if (\Auth::user()->can('edit lead')) {
            $lead = $this->ownedLead($id);
            if ($lead && $lead->created_by == \Auth::user()->creatorId()) {
                $lead->notes = $request->notes;
                $lead->save();

                return response()->json(
                    [
                        'is_success' => true,
                        'success' => __('Note successfully saved!'),
                    ],
                    200
                );
            } else {
                return response()->json(
                    [
                        'is_success' => false,
                        'error' => __('Permission Denied.'),
                    ],
                    401
                );
            }
        } else {
            return response()->json(
                [
                    'is_success' => false,
                    'error' => __('Permission Denied.'),
                ],
                401
            );
        }
    }

    public function labels($id)
    {
        if (\Auth::user()->can('edit lead')) {
            $lead = $this->ownedLead($id);
            if ($lead && $lead->created_by == \Auth::user()->creatorId()) {
                $labels = Label::where('pipeline_id', '=', $lead->pipeline_id)->where('created_by', \Auth::user()->creatorId())->get();
                $selected = $lead->labels();
                if ($selected) {
                    $selected = $selected->pluck('name', 'id')->toArray();
                } else {
                    $selected = [];
                }

                return view('leads.labels', compact('lead', 'labels', 'selected'));
            } else {
                return response()->json(['error' => __('Permission Denied.')], 401);
            }
        } else {
            return response()->json(['error' => __('Permission Denied.')], 401);
        }
    }

    public function labelStore($id, Request $request)
    {
        $validator = \Validator::make(
            $request->all(),
            [
                // was 'required', which made it impossible to take the last label
                // off a lead: unticking every box sends no labels[] at all, so the
                // form came back with "labels field is required" and saved nothing.
                'labels' => 'nullable|array',
                'labels.*' => [
                    'integer',
                    \Illuminate\Validation\Rule::exists('labels', 'id')
                        ->where('created_by', \Auth::user()->creatorId()),
                ],
            ]
        );

        if ($validator->fails()) {
            $messages = $validator->getMessageBag();
            return redirect()->back()->with('error', $messages->first());
        }

        if (\Auth::user()->can('edit lead')) {
            $leads = $this->ownedLead($id);
            if ($leads && $leads->created_by == \Auth::user()->creatorId()) {
                $leads->labels = $request->labels ? implode(',', $request->labels) : '';
                $leads->save();

                return redirect()->back()->with('success', __('Labels successfully updated!'));
            } else {
                return redirect()->back()->with('error', __('Permission Denied.'));
            }
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    public function userEdit($id)
    {
        if (\Auth::user()->can('edit lead')) {
            $lead = $this->ownedLead($id);

            if ($lead && $lead->created_by == \Auth::user()->creatorId()) {
                $users = User::select('users.name', 'users.id')
                    ->join('employees', 'users.id', '=', 'employees.user_id')
                    ->leftJoin('departments', 'employees.department_id', '=', 'departments.id')
                    ->where('users.created_by', '=', \Auth::user()->creatorId())
                    ->where('users.type', '!=', 'client')
                    ->where('users.type', '!=', 'company')
                    ->where(function($q) {
                        $q->where('departments.name', 'LIKE', '%Sales%')
                          ->orWhere('users.type', 'LIKE', '%Sales%')
                          ->orWhereExists(function($sub) {
                              $sub->select(\DB::raw(1))
                                  ->from('model_has_roles')
                                  ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                                  ->whereColumn('model_has_roles.model_id', 'users.id')
                                  ->where('roles.name', 'LIKE', '%Sales%');
                          });
                    })
                    ->whereNotIn(
                        'users.id',
                        function ($q) use ($lead) {
                            $q->select('user_id')->from('user_leads')->where('lead_id', '=', $lead->id);
                        }
                    )->distinct()->get();


                $users = $users->pluck('name', 'id');

                return view('leads.users', compact('lead', 'users'));
            } else {
                return response()->json(['error' => __('Permission Denied.')], 401);
            }
        } else {
            return response()->json(['error' => __('Permission Denied.')], 401);
        }
    }

    public function bulkReassign(Request $request)
    {
        if (\Auth::user()->can('edit lead')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'lead_ids' => 'required|array',
                    'user_id' => 'required',
                ]
            );

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
            }

            try {
                $leadIds = $request->lead_ids;
                $newUserId = $request->user_id;
                $usr = \Auth::user();
                $successCount = 0;

                // The lead may only be handed to someone inside the same company.
                $newUser = User::where('id', $newUserId)
                    ->where('created_by', $usr->creatorId())
                    ->first();

                if (!$newUser) {
                    return response()->json([
                        'success' => false,
                        'message' => __('The selected employee does not belong to your company.'),
                    ], 422);
                }
                $newUserId = $newUser->id;

                foreach ($leadIds as $leadId) {
                    $lead = Lead::find($leadId);

                    if ($lead && $lead->created_by == $usr->creatorId()) {
                        // Update lead's primary user
                        $lead->user_id = $newUserId;
                        $lead->save();

                        // Update user_leads pivot table - remove old assignments and add new one
                        \DB::table('user_leads')->where('lead_id', $leadId)->delete();
                        \DB::table('user_leads')->insert([
                            'lead_id' => $leadId,
                            'user_id' => $newUserId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $successCount++;
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => __('Successfully reassigned') . ' ' . $successCount . ' ' . __('leads!')
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => __('Error reassigning leads: ') . $e->getMessage()
                ], 500);
            }
        } else {
            return response()->json(['success' => false, 'message' => __('Permission Denied.')], 403);
        }
    }

    public function userUpdate($id, Request $request)
    {
        if (\Auth::user()->can('edit lead')) {
            $usr = \Auth::user();
            $lead = $this->ownedLead($id);

            if ($lead && $lead->created_by == $usr->creatorId()) {
                if (!empty($request->users)) {
                    $users = array_filter($request->users);
                    $leadArr = [
                        'lead_id' => $lead->id,
                        'name' => $lead->name,
                        'updated_by' => $usr->id,
                    ];

                    foreach ($users as $user) {
                        UserLead::create(
                            [
                                'lead_id' => $lead->id,
                                'user_id' => $user,
                            ]
                        );
                    }
                }

                if (!empty($users) && !empty($request->users)) {
                    return redirect()->back()->with('success', __('Users successfully updated!'));
                } else {
                    return redirect()->back()->with('error', __('Please Select Valid User!'));
                }
            } else {
                return redirect()->back()->with('error', __('Permission Denied.'));
            }
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    public function userDestroy($id, $user_id)
    {
        if (\Auth::user()->can('edit lead')) {
            $lead = $this->ownedLead($id);
            if ($lead && $lead->created_by == \Auth::user()->creatorId()) {
                UserLead::where('lead_id', '=', $lead->id)->where('user_id', '=', $user_id)->delete();

                return redirect()->back()->with('success', __('User successfully deleted!'));
            } else {
                return redirect()->back()->with('error', __('Permission Denied.'));
            }
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    public function productEdit($id)
    {
        if (\Auth::user()->can('edit lead')) {
            $lead = $this->ownedLead($id);
            if ($lead && $lead->created_by == \Auth::user()->creatorId()) {
                $products = ProductService::where('created_by', '=', \Auth::user()->creatorId())->whereNOTIn('id', explode(',', $lead->products))->get()->pluck('name', 'id');

                return view('leads.products', compact('lead', 'products'));
            } else {
                return response()->json(['error' => __('Permission Denied.')], 401);
            }
        } else {
            return response()->json(['error' => __('Permission Denied.')], 401);
        }
    }

    public function productUpdate($id, Request $request)
    {
        if (\Auth::user()->can('edit lead')) {
            $usr = \Auth::user();
            $lead = $this->ownedLead($id);
            $lead_users = $lead->users->pluck('id')->toArray();

            if ($lead && $lead->created_by == \Auth::user()->creatorId()) {
                if (!empty($request->products)) {
                    $products = array_filter($request->products);
                    $old_products = explode(',', $lead->products);
                    $lead->products = implode(',', array_merge($old_products, $products));
                    $lead->save();

                    $objProduct = ProductService::whereIN('id', $products)->get()->pluck('name', 'id')->toArray();

                    LeadActivityLog::create(
                        [
                            'user_id' => $usr->id,
                            'lead_id' => $lead->id,
                            'log_type' => 'Add Product',
                            'remark' => json_encode(['title' => implode(",", $objProduct)]),
                        ]
                    );

                    $productArr = [
                        'lead_id' => $lead->id,
                        'name' => $lead->name,
                        'updated_by' => $usr->id,
                    ];
                }

                if (!empty($products) && !empty($request->products)) {
                    return redirect()->back()->with('success', __('Products successfully updated!'))->with('status', 'products');
                } else {
                    return redirect()->back()->with('error', __('Please Select Valid Product!'))->with('status', 'general');
                }
            } else {
                return redirect()->back()->with('error', __('Permission Denied.'))->with('status', 'products');
            }
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'))->with('status', 'products');
        }
    }

    public function productDestroy($id, $product_id)
    {
        if (\Auth::user()->can('edit lead')) {
            $lead = $this->ownedLead($id);
            if ($lead && $lead->created_by == \Auth::user()->creatorId()) {
                $products = explode(',', $lead->products);
                foreach ($products as $key => $product) {
                    if ($product_id == $product) {
                        unset($products[$key]);
                    }
                }
                $lead->products = implode(',', $products);
                $lead->save();

                return redirect()->back()->with('success', __('Products successfully deleted!'))->with('status', 'products');
            } else {
                return redirect()->back()->with('error', __('Permission Denied.'))->with('status', 'products');
            }
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'))->with('status', 'products');
        }
    }

    public function sourceEdit($id)
    {
        if (\Auth::user()->can('edit lead')) {
            $lead = $this->ownedLead($id);
            if ($lead && $lead->created_by == \Auth::user()->creatorId()) {
                $sources = Source::where('created_by', '=', \Auth::user()->creatorId())->get();

                $selected = $lead->sources();
                if ($selected) {
                    $selected = $selected->pluck('name', 'id')->toArray();
                }

                return view('leads.sources', compact('lead', 'sources', 'selected'));
            } else {
                return response()->json(['error' => __('Permission Denied.')], 401);
            }
        } else {
            return response()->json(['error' => __('Permission Denied.')], 401);
        }
    }

    public function sourceUpdate($id, Request $request)
    {
        if (\Auth::user()->can('edit lead')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'sources' => 'required',
                ]
            );

            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return redirect()->back()->with('error', $messages->first());
            }

            $usr = \Auth::user();
            $lead = $this->ownedLead($id);
            $lead_users = $lead->users->pluck('id')->toArray();

            if ($lead && $lead->created_by == \Auth::user()->creatorId()) {
                if (!empty($request->sources) && count($request->sources) > 0) {
                    $lead->sources = implode(',', $request->sources);
                } else {
                    $lead->sources = "";
                }

                $lead->save();

                LeadActivityLog::create(
                    [
                        'user_id' => $usr->id,
                        'lead_id' => $lead->id,
                        'log_type' => 'Update Sources',
                        'remark' => json_encode(['title' => 'Update Sources']),
                    ]
                );

                $leadArr = [
                    'lead_id' => $lead->id,
                    'name' => $lead->name,
                    'updated_by' => $usr->id,
                ];

                return redirect()->back()->with('success', __('Sources successfully updated!'))->with('status', 'sources');
            } else {
                return redirect()->back()->with('error', __('Permission Denied.'))->with('status', 'sources');
            }
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'))->with('status', 'sources');
        }
    }

    public function sourceDestroy($id, $source_id)
    {
        if (\Auth::user()->can('edit lead')) {
            $lead = $this->ownedLead($id);
            if ($lead && $lead->created_by == \Auth::user()->creatorId()) {
                $sources = explode(',', $lead->sources);
                foreach ($sources as $key => $source) {
                    if ($source_id == $source) {
                        unset($sources[$key]);
                    }
                }
                $lead->sources = implode(',', $sources);
                $lead->save();

                return redirect()->back()->with('success', __('Sources successfully deleted!'))->with('status', 'sources');
            } else {
                return redirect()->back()->with('error', __('Permission Denied.'))->with('status', 'sources');
            }
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'))->with('status', 'sources');
        }
    }

    public function order(Request $request)
    {
        if (\Auth::user()->can('move lead')) {
            $usr = \Auth::user();
            $post = $request->all();

            $lead = $this->ownedLead($post['lead_id'] ?? null);
            if (!$lead || !$this->canReachLead($lead, $usr)) {
                return response()->json(['status' => 'error', 'message' => __('Permission Denied.')], 403);
            }

            // The destination stage has to belong to this company too.
            $newStage = LeadStage::where('id', $post['stage_id'] ?? null)
                ->where('created_by', $usr->creatorId())
                ->first();
            if (!$newStage) {
                return response()->json(['status' => 'error', 'message' => __('Permission Denied.')], 403);
            }

            $lead_users = $lead->users->pluck('email', 'id')->toArray();

            if ($lead->stage_id != $newStage->id) {
                $oldStageName = $lead->stage ? $lead->stage->name : '';

                LeadActivityLog::create(
                    [
                        'user_id' => $usr->id,
                        'lead_id' => $lead->id,
                        'log_type' => 'Move',
                        'remark' => json_encode(
                            [
                                'title' => $lead->name,
                                'old_status' => $oldStageName,
                                'new_status' => $newStage->name,
                            ]
                        ),
                    ]
                );

                $lArr = [
                    'lead_name' => $lead->name,
                    'lead_email' => $lead->email,
                    'lead_pipeline' => $lead->pipeline ? $lead->pipeline->name : '',
                    'lead_stage' => $oldStageName,
                    'lead_old_stage' => $oldStageName,
                    'lead_new_stage' => $newStage->name,
                ];

                // Send Email
                Utility::sendEmailTemplate('Move Lead', $lead_users, $lArr);
            }

            // Persist the new position of every card in the column. Each id is
            // looked up on its own - reusing one cached lead here silently threw
            // the reordering away.
            $orderedIds = array_filter(array_map('intval', (array) ($post['order'] ?? [])));
            $columnLeads = Lead::whereIn('id', $orderedIds)
                ->where('created_by', $usr->creatorId())
                ->get()
                ->keyBy('id');

            foreach ($orderedIds as $key => $item) {
                if (!isset($columnLeads[$item])) {
                    continue;
                }
                $columnLeads[$item]->order = $key;
                $columnLeads[$item]->stage_id = $newStage->id;
                $columnLeads[$item]->save();
            }

            return response()->json(['status' => 'success', 'message' => __('Lead successfully moved.')], 200);
        } else {
            return response()->json(['status' => 'error', 'message' => __('Permission Denied.')], 401);
        }
    }



    public function export()
    {
        // Exporting pulls the whole lead database out of the system, so it needs
        // the permission itself - the type-based shortcuts index() allows are not
        // enough here.
        if (!\Auth::user()->can('manage lead')) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        $name = 'Lead_' . date('Y-m-d i:h:s');
        $type = request()->get('type') == 'csv' ? 'csv' : 'xlsx';
        $data = Excel::download(new LeadExport(request()), $name . '.' . $type);

        return $data;
    }

    public function importFile()
    {
        return view('leads.import');
    }

    public function fileImport(Request $request)
    {
        $rules = [
            'file' => 'required|mimes:csv,txt,xlsx',
        ];

        $validator = \Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['status' => 'error', 'message' => $validator->getMessageBag()->first()]);
            }
            $messages = $validator->getMessageBag();
            return redirect()->back()->with('error', $messages->first());
        }

        try {
            // Always run dry-run first to check for DB duplicates (never allow bypass)
            $import = new LeadImport(true); // Dry Run
            Excel::import($import, request()->file('file'));

            $errors = $import->getErrors();

            // Check if we should ignore row-level validation errors (Continue button clicked)
            $continue = $request->input('continue') === '1' || $request->input('continue') === 'true';

            // DB duplicate errors (row=0) can NEVER be bypassed
            $dbDuplicateErrors = array_filter($errors, fn($e) => $e['row'] === 0);
            $rowErrors = array_filter($errors, fn($e) => $e['row'] !== 0);

            if (!empty($dbDuplicateErrors)) {
                // Always reject if DB duplicates found - cannot be bypassed
                $html = view('leads.import_errors', compact('errors'))->render();
                return response()->json([
                    'status' => 'validation_errors',
                    'html' => $html,
                    'total_rows' => $import->getImportedCount() + count($errors),
                    'error_count' => count($errors),
                    'can_continue' => false, // Signal to UI: Continue button should NOT be shown
                ]);
            }

            if (!$continue && !empty($rowErrors)) {
                // Return row-level validation errors (can be bypassed with Continue)
                $html = view('leads.import_errors', compact('errors'))->render();
                return response()->json([
                    'status' => 'validation_errors',
                    'html' => $html,
                    'total_rows' => $import->getImportedCount() + count($errors),
                    'error_count' => count($errors),
                    'can_continue' => true,
                ]);
            }

            // Execute: import all leads
            $import = new LeadImport(false); // Real Run
            Excel::import($import, request()->file('file'));
        } catch (\Exception $e) {
            if ($request->ajax()) {
                return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
            }
            return redirect()->back()->with('error', $e->getMessage());
        }

        if ($request->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Leads imported successfully.'
            ]);
        }
        return redirect()->route('leads.index')->with('success', 'Leads imported successfully.');
    }

    public function fileImportModal()
    {
        return view('leads.import_modal');
    }

    public function leadImportdata(Request $request)
    {
        $creatorId = \Auth::user()->creatorId();
        session_start();
        $html = '<h3 class="text-danger text-center">Below data is not inserted</h3></br>';
        $flag = 0;
        $html .= '<table class="table table-bordered"><tr>';
        try {
            $file_data = $_SESSION['file_data'];

            // foreach ($file_data as $validationKey => $value) {
            //     $validator = \Validator::make([
            //         'subject' => $value[$request->subject] ?? null,
            //         'name'    => $value[$request->name] ?? null,
            //         'email'   => $value[$request->email] ?? null,
            //         'phone'   => $value[$request->phone] ?? null,
            //     ], [
            //         'subject' => 'required|string|max:255',
            //         'name'    => 'required|string|max:255',
            //         'email'   => 'required|email|max:255',
            //         'phone' => 'required|regex:/^\+\d{1,3}\d{9,13}$/'
            //     ]);

            //     if ($validator->fails()) {
            //         return response()->json([
            //             'success' => false,
            //             'message' => $validator->errors()->first(),
            //         ]);
            //     }
            // }

            unset($_SESSION['file_data']);
        } catch (\Throwable $th) {
            $html = '<h3 class="text-danger text-center">Oops, Session Time Out!</h3></br>';
            return response()->json([
                'html' => true,
                'response' => $html,
            ]);
        }

        $user = \Auth::user();
        if ($user->default_pipeline) {
            $pipeline = Pipeline::where('created_by', '=', $creatorId)->where('id', '=', $user->default_pipeline)->first();
            if (!$pipeline) {
                $pipeline = Pipeline::where('created_by', '=', $creatorId)->first();
            }
        } else {
            $pipeline = Pipeline::where('created_by', '=', $creatorId)->first();
        }

        if (!empty($pipeline)) {
            $stage = LeadStage::where('pipeline_id', '=', $pipeline->id)->first();
            if (empty($stage)) {
                return response()->json([
                    'success' => false,
                    'message' => __('Please create stage for this pipeline.'),
                ]);
            }
        } else {
            return response()->json([
                'success' => false,
                'message' => __('Please create pipeline.'),
            ]);
        }

        foreach ($file_data as $key => $row) {
            try {
                $users = User::find($request->user[$key]);
                if (empty($users)) {
                    $users = User::where('created_by', \Auth::user()->id)->first();
                }

                $lead = Lead::create([
                    'subject' => $row[$request->subject],
                    'name' => $row[$request->name],
                    'user_id' => $users->id,
                    'email' => $row[$request->email],
                    'phone' => $row[$request->phone],
                    'pipeline_id' => $pipeline->id,
                    'stage_id' => $stage->id,
                    'created_by' => $creatorId,
                ]);
                UserLead::create([
                    'user_id' => $creatorId,
                    'lead_id' => $lead->id,
                ]);
            } catch (\Exception $e) {
                $flag = 1;
                $html .= '<tr>';

                $html .= '<td>' . (isset($row[$request->subject]) ? $row[$request->subject] : '-') . '</td>';
                $html .= '<td>' . (isset($row[$request->name]) ? $row[$request->name] : '-') . '</td>';
                $html .= '<td>' . (isset($row[$request->email]) ? $row[$request->email] : '-') . '</td>';
                $html .= '<td>' . (isset($row[$request->phone]) ? $row[$request->phone] : '-') . '</td>';

                $html .= '</tr>';
            }
        }
        $html .= '
                </table>
                <br />
                ';
        if ($flag == 1) {
            return response()->json([
                'html' => true,
                'response' => $html,
            ]);
        } else {
            return response()->json([
                'html' => false,
                'response' => __('Data has been imported.'),
            ]);
        }
    }

    /**
     * Clean phone numbers by removing spaces and formatting multiple numbers with comma separator
     * 
     * @param string $phone
     * @return string
     */
    private function cleanPhoneNumbers($phone)
    {
        if (empty($phone)) {
            return null;
        }

        // Split by comma to handle multiple numbers as requested
        $numbers = explode(',', $phone);
        $cleanedNumbers = [];

        foreach ($numbers as $number) {
            // Remove all spaces, +, and non-digit characters to treat "+963 998 37" same as "96399837"
            $cleaned = preg_replace('/[^0-9]/', '', $number);
            if (!empty($cleaned)) {
                $cleanedNumbers[] = $cleaned;
            }
        }

        return implode(',', $cleanedNumbers);
    }
}
