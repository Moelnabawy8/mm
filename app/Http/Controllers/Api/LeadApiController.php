<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Interest;
use App\Models\Label;
use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\Pipeline;
use App\Models\Source;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class LeadApiController extends Controller
{
    use ApiResponser;

    public function getLeads(Request $request)
    {
        $user  = Auth::user();
        $query = Lead::where('leads.created_by', $user->creatorId())
            ->with(['stage', 'pipeline', 'users'])
            ->select('leads.*');

        // ---------- Filters (mirrors website) ----------

        // General search: if same value in `search`, match name OR phone
        if ($request->search) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('leads.name', 'like', "%{$s}%")
                  ->orWhere('leads.phone', 'like', "%{$s}%")
                  ->orWhere('leads.email', 'like', "%{$s}%");
            });
        } else {
            if ($request->name)  $query->where('leads.name', 'like', "%{$request->name}%");
            if ($request->phone) $query->where('leads.phone', 'like', "%{$request->phone}%");
            if ($request->email) $query->where('leads.email', 'like', "%{$request->email}%");
        }

        if ($request->subject)       $query->where('leads.subject', 'like', "%{$request->subject}%");
        if ($request->pipeline_id)   $query->where('leads.pipeline_id', $request->pipeline_id);
        if ($request->stage_id)      $query->where('leads.stage_id', $request->stage_id);
        if ($request->country)       $query->where('leads.country', 'like', "%{$request->country}%");
        if ($request->city)          $query->where('leads.city', 'like', "%{$request->city}%");
        if ($request->campaign_name) $query->where('leads.campaign_name', $request->campaign_name);
        if ($request->costing_type)  $query->where('leads.costing_type', $request->costing_type);
        if ($request->gender)        $query->where('leads.gender', $request->gender);

        if ($request->min_cost && $request->max_cost) {
            $query->whereBetween('leads.cost', [$request->min_cost, $request->max_cost]);
        } elseif ($request->min_cost) {
            $query->where('leads.cost', '>=', $request->min_cost);
        } elseif ($request->max_cost) {
            $query->where('leads.cost', '<=', $request->max_cost);
        }

        if ($request->date_from && $request->date_to) {
            $query->whereBetween('leads.date', [$request->date_from, $request->date_to]);
        } elseif ($request->date_from) {
            $query->where('leads.date', '>=', $request->date_from);
        } elseif ($request->date_to) {
            $query->where('leads.date', '<=', $request->date_to);
        }

        if ($request->updated_from && $request->updated_to) {
            $query->whereBetween('leads.updated_at', [
                $request->updated_from . ' 00:00:00',
                $request->updated_to   . ' 23:59:59',
            ]);
        } elseif ($request->updated_from) {
            $query->where('leads.updated_at', '>=', $request->updated_from . ' 00:00:00');
        } elseif ($request->updated_to) {
            $query->where('leads.updated_at', '<=', $request->updated_to . ' 23:59:59');
        }

        // Assigned users (comma-separated user IDs)
        if ($request->users) {
            $userIds = explode(',', $request->users);
            $query->where(function ($q) use ($userIds) {
                $q->whereIn('leads.user_id', $userIds)
                  ->orWhereExists(function ($sub) use ($userIds) {
                      $sub->selectRaw(1)->from('user_leads')
                          ->whereColumn('user_leads.lead_id', 'leads.id')
                          ->whereIn('user_leads.user_id', $userIds);
                  });
            });
        }

        // FIND_IN_SET filters (comma-separated stored fields)
        foreach (['products', 'sources', 'labels', 'interests'] as $field) {
            if ($request->$field) {
                $ids = explode(',', $request->$field);
                $query->where(function ($q) use ($field, $ids) {
                    foreach ($ids as $id) {
                        $q->orWhereRaw("FIND_IN_SET(?, leads.{$field})", [trim($id)]);
                    }
                });
            }
        }

        $perPage = (int) ($request->per_page ?? 15);
        $result  = $query->orderBy('leads.id', 'desc')->paginate($perPage);

        $result->getCollection()->transform(function ($lead) {
            $lead->products_list  = $lead->products();
            $lead->sources_list   = $lead->sources();
            $lead->labels_list    = $lead->labels();
            $lead->interests_list = $lead->interests();
            return $lead;
        });

        return $this->success([
            'leads'      => $result->items(),
            'pagination' => [
                'total'        => $result->total(),
                'per_page'     => $result->perPage(),
                'current_page' => $result->currentPage(),
                'last_page'    => $result->lastPage(),
            ],
        ], 'Leads retrieved.');
    }

    public function createLead(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'          => 'required|string|max:190',
            'email'         => 'nullable|email',
            'phone'         => 'nullable|string',
            'subject'       => 'nullable|string',
            'pipeline_id'   => 'nullable|exists:pipelines,id',
            'stage_id'      => 'nullable|exists:lead_stages,id',
            'gender'        => 'nullable|in:Male,Female',
            'country'       => 'nullable|string',
            'city'          => 'nullable|string',
            'costing_type'  => 'nullable|in:organic,paid',
            'campaign_name' => 'nullable|string',
            'cost'          => 'nullable|numeric|min:0',
            'telegram'      => 'nullable|string',
            'notes'         => 'nullable|string',
            'sources'       => 'nullable',
            'products'      => 'nullable',
            'labels'        => 'nullable',
            'interests'     => 'nullable',
            'assigned_users'   => 'nullable|array',
            'assigned_users.*' => 'integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $lead = new Lead();
        $lead->name          = $request->name;
        $lead->email         = $request->email;
        $lead->phone         = $request->phone;
        $lead->subject       = $request->subject;
        $lead->user_id       = Auth::user()->id;
        $lead->pipeline_id   = $request->pipeline_id ?? 0;
        $lead->stage_id      = $request->stage_id ?? 0;
        $lead->sources       = is_array($request->sources)   ? implode(',', array_filter($request->sources))   : $request->sources;
        $lead->products      = is_array($request->products)  ? implode(',', array_filter($request->products))  : $request->products;
        $lead->labels        = is_array($request->labels)    ? implode(',', array_filter($request->labels))    : $request->labels;
        $lead->interests     = is_array($request->interests) ? implode(',', array_filter($request->interests)) : $request->interests;
        $lead->notes         = $request->notes;
        $lead->gender        = $request->gender;
        $lead->country       = $request->country;
        $lead->city          = $request->city;
        $lead->costing_type  = $request->costing_type;
        $lead->campaign_name = $request->campaign_name;
        $lead->cost          = $request->cost;
        $lead->telegram      = $request->telegram;
        $lead->order         = 0;
        $lead->created_by    = Auth::user()->creatorId();
        $lead->date          = date('Y-m-d');
        $lead->save();

        if (!empty($request->assigned_users)) {
            foreach ($request->assigned_users as $uid) {
                \DB::table('user_leads')->updateOrInsert(
                    ['lead_id' => $lead->id, 'user_id' => $uid],
                    ['lead_id' => $lead->id, 'user_id' => $uid]
                );
            }
        }

        $lead->products_list  = $lead->products();
        $lead->sources_list   = $lead->sources();
        $lead->labels_list    = $lead->labels();
        $lead->interests_list = $lead->interests();

        $lead->load('users');
        return $this->success(['lead' => $lead], 'Lead created successfully.');
    }

    public function getLead($id)
    {
        $lead = Lead::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->with(['stage', 'pipeline', 'users', 'files', 'activities', 'discussions', 'calls', 'deals.stage', 'salesMeetings'])
            ->first();

        if (!$lead) return $this->error('Lead not found.', 404);

        $lead->products_list  = $lead->products();
        $lead->sources_list   = $lead->sources();
        $lead->labels_list    = $lead->labels();
        $lead->interests_list = $lead->interests();

        $stageCount = LeadStage::where('pipeline_id', $lead->pipeline_id)
            ->where('created_by', $lead->created_by)->get();
        $i = 0;
        foreach ($stageCount as $stage) {
            $i++;
            if ($stage->id == $lead->stage_id) break;
        }
        $lead->stage_progress = $stageCount->count() > 0
            ? (int) number_format(($i * 100) / $stageCount->count()) : 0;

        return $this->success(['lead' => $lead], 'Lead detail retrieved.');
    }

    public function updateLead(Request $request, $id)
    {
        $lead = Lead::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$lead) return $this->error('Lead not found.', 404);

        $validator = Validator::make($request->all(), [
            'name'          => 'sometimes|required|string|max:190',
            'email'         => 'nullable|email',
            'phone'         => 'nullable|string',
            'subject'       => 'nullable|string',
            'pipeline_id'   => 'nullable|exists:pipelines,id',
            'stage_id'      => 'nullable|exists:lead_stages,id',
            'gender'        => 'nullable|in:Male,Female',
            'costing_type'  => 'nullable|in:organic,paid',
            'cost'          => 'nullable|numeric|min:0',
            'assigned_users'   => 'nullable|array',
            'assigned_users.*' => 'integer|exists:users,id',
        ]);

        if ($validator->fails()) return $this->error($validator->errors()->first(), 422);

        $lead->fill($request->only([
            'name', 'email', 'phone', 'subject', 'pipeline_id', 'stage_id',
            'gender', 'country', 'city', 'costing_type',
            'campaign_name', 'cost', 'telegram', 'notes',
        ]));

        if ($request->has('sources'))   $lead->sources   = is_array($request->sources)   ? implode(',', array_filter($request->sources))   : $request->sources;
        if ($request->has('products'))  $lead->products  = is_array($request->products)  ? implode(',', array_filter($request->products))  : $request->products;
        if ($request->has('labels'))    $lead->labels    = is_array($request->labels)    ? implode(',', array_filter($request->labels))    : $request->labels;
        if ($request->has('interests')) $lead->interests = is_array($request->interests) ? implode(',', array_filter($request->interests)) : $request->interests;
        $lead->save();

        if ($request->has('assigned_users')) {
            \DB::table('user_leads')->where('lead_id', $lead->id)->delete();
            foreach ($request->assigned_users as $uid) {
                \DB::table('user_leads')->updateOrInsert(
                    ['lead_id' => $lead->id, 'user_id' => $uid],
                    ['lead_id' => $lead->id, 'user_id' => $uid]
                );
            }
        }

        $lead->products_list  = $lead->products();
        $lead->sources_list   = $lead->sources();
        $lead->labels_list    = $lead->labels();
        $lead->interests_list = $lead->interests();

        $lead->load('users');
        return $this->success(['lead' => $lead], 'Lead updated successfully.');
    }

    public function deleteLead($id)
    {
        $lead = Lead::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$lead) return $this->error('Lead not found.', 404);
        $lead->delete();
        return $this->success([], 'Lead deleted successfully.');
    }

    public function convertLead($id)
    {
        return $this->error('Lead conversion not supported via API yet.', 501);
    }

    // ---------- Lookup endpoints ----------

    public function getLeadStages(Request $request)
    {
        $query = LeadStage::where('created_by', Auth::user()->creatorId());
        if ($request->pipeline_id) $query->where('pipeline_id', $request->pipeline_id);
        return $this->success(['stages' => $query->orderBy('order')->get()], 'Lead stages retrieved.');
    }

    public function getLeadSources()
    {
        $sources = Source::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['sources' => $sources], 'Lead sources retrieved.');
    }

    public function getLeadLabels(Request $request)
    {
        $query = Label::where('created_by', Auth::user()->creatorId());
        if ($request->pipeline_id) $query->where('pipeline_id', $request->pipeline_id);
        return $this->success(['labels' => $query->get()], 'Labels retrieved.');
    }

    public function getLeadInterests()
    {
        $interests = Interest::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['interests' => $interests], 'Interests retrieved.');
    }

    public function getLeadPipelines()
    {
        $pipelines = Pipeline::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['pipelines' => $pipelines], 'Pipelines retrieved.');
    }

    public function getCostingTypes()
    {
        return $this->success([
            'costing_types' => [
                ['value' => 'organic', 'label' => 'Organic'],
                ['value' => 'paid',    'label' => 'Paid'],
            ],
        ], 'Costing types retrieved.');
    }
}
