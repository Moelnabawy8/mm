<?php

namespace App\Http\Controllers;

use App\Models\QualityEvaluation;
use App\Models\Lead;
use App\Models\User;
use App\Models\Pipeline;
use App\Models\LeadStage;
use App\Models\ProductService;
use App\Models\Source;
use App\Models\Label;
use App\Models\Interest;
use App\Services\LeadScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class QualityEvaluationController extends Controller
{
    public function index(Request $request)
    {
        if (Auth::user()->can('manage quality evaluation')) {
            $userId = Auth::user()->id;

            // Privileged users check
            $privilegedNames = ['Alaa', 'Saba', 'aber'];
            $isPrivileged = Auth::user()->type == 'company' ||
                Auth::user()->type == 'CSO' ||
                Auth::user()->hasRole('Sales Manager') ||
                Auth::user()->type == 'Alaa,Saba,aber' ||
                Auth::user()->type == 'fadi' ||
                in_array(Auth::user()->name, $privilegedNames) ||
                Auth::user()->hasAnyRole($privilegedNames);

            // Every assigned lead gets a placeholder evaluation, default 'unknown'.
            //
            // This used to pull the leads into PHP and then run a SELECT and an
            // INSERT per lead on every single page load -- tens of thousands of
            // queries per GET on a large tenant -- and neither branch was scoped to
            // the company, so one privileged user opening the page created
            // evaluations against other companies' leads. It is now one anti-join
            // to find what is missing plus chunked bulk inserts, scoped to the
            // company both ways.
            $this->createMissingEvaluations(
                Auth::user()->ownerId(),
                $isPrivileged ? null : $userId
            );

            $query = QualityEvaluation::with(['lead.users', 'evaluator'])
                ->join('leads', 'quality_evaluations.lead_id', '=', 'leads.id')
                ->where('leads.created_by', Auth::user()->ownerId());

            // Apply Lead Assignment Filtering
            // Use whereExists instead of JOIN to avoid row duplication when the
            // user_leads pivot has duplicate (user_id, lead_id) pairs.
            if (!$isPrivileged) {
                $query->whereExists(function ($sub) use ($userId) {
                    $sub->select(DB::raw(1))
                        ->from('user_leads')
                        ->whereColumn('user_leads.lead_id', 'leads.id')
                        ->where('user_leads.user_id', $userId);
                });
                $query->where('quality_evaluations.evaluator_id', $userId);
            } else {
                if ($request->evaluator_id) {
                    $query->whereExists(function ($sub) use ($request) {
                        $sub->select(DB::raw(1))
                            ->from('user_leads')
                            ->whereColumn('user_leads.lead_id', 'leads.id')
                            ->where('user_leads.user_id', $request->evaluator_id);
                    });
                    $query->where('quality_evaluations.evaluator_id', $request->evaluator_id);
                }
            }

            // Apply all filters (shared with export)
            $this->applyFilters($query, $request);

            // For privileged users with no evaluator filter, show ONE evaluation per lead (latest)
            if ($isPrivileged && !$request->evaluator_id) {
                $query->whereIn('quality_evaluations.id', function ($sub) {
                    $sub->selectRaw('MAX(id)')
                        ->from('quality_evaluations')
                        ->groupBy('lead_id');
                });
            }

            $statsQuery = clone $query;

            $evaluations = $query->select('quality_evaluations.*')
                ->distinct()
                ->orderBy('quality_evaluations.lead_id', 'desc')
                ->paginate(20);

            $stats = [
                'total'          => (clone $statsQuery)->count(),
                'potential'      => (clone $statsQuery)->where('quality_evaluations.is_potential_lead', QualityEvaluation::POTENTIAL_YES)->count(),
                'not_potential'  => (clone $statsQuery)->where('quality_evaluations.is_potential_lead', QualityEvaluation::POTENTIAL_NO)->count(),
                'unknown'        => (clone $statsQuery)->where('quality_evaluations.is_potential_lead', QualityEvaluation::POTENTIAL_UNKNOWN)->count(),
                'highly_engaged' => (clone $statsQuery)->where('quality_evaluations.engagement_level', 'highly_engaged')->count(),
                'perfect_fit'    => (clone $statsQuery)->where('quality_evaluations.course_fit_level', 'perfect_fit')->count(),
                'avg_rating'     => (clone $statsQuery)->whereNotNull('quality_evaluations.rating')->avg('quality_evaluations.rating'),
            ];

            // Evaluators and Users both offer sales people, using the one definition
            // in LeadScope that the leads page uses. The lists here were hard-coded
            // sets of user types and role names, and the Users list inner-joined
            // employees, so a sales person without an employee record vanished.
            $evaluators = LeadScope::salesUsersQuery(Auth::user())->orderBy('users.name')->get();

            // Scoped to the company: this listed every campaign name in the database,
            // including other companies'.
            $campaigns = Lead::where('created_by', Auth::user()->ownerId())
                ->whereNotNull('campaign_name')->where('campaign_name', '!=', '')
                ->distinct()->pluck('campaign_name');

            // Lead-related filter data (mirrors /leads)
            $pipelines = Pipeline::where('created_by', Auth::user()->creatorId())->pluck('name', 'id');
            $stages    = LeadStage::where('created_by', Auth::user()->creatorId())->pluck('name', 'id');
            $users     = LeadScope::salesUsersQuery(Auth::user())->get()->pluck('name', 'id');
            $products  = ProductService::where('created_by', Auth::user()->creatorId())->pluck('name', 'id');
            $sources   = Source::where('created_by', Auth::user()->creatorId())->pluck('name', 'id');
            $labels    = Label::where('created_by', Auth::user()->creatorId())->pluck('name', 'id');
            $interests = Interest::where('created_by', Auth::user()->creatorId())->pluck('name', 'id');
            // The same countries the create/edit lead form offers. Building this
            // from the values stored in leads.country listed phone numbers as
            // countries, exactly as it did on /leads.
            $countries = LeadScope::COUNTRIES;

            if ($request->ajax()) {
                return view('quality-evaluations._main_content', compact('evaluations', 'stats'));
            }

            return view('quality-evaluations.index', compact(
                'evaluations', 'stats', 'evaluators', 'campaigns',
                'pipelines', 'stages', 'users', 'products', 'sources', 'labels', 'interests', 'countries',
                'isPrivileged'
            ));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Insert the placeholder evaluations that are missing, for one company.
     *
     * @param int      $ownerId        the company the leads must belong to
     * @param int|null $onlyEvaluator  limit to one evaluator, or null for everyone
     */
    private function createMissingEvaluations($ownerId, $onlyEvaluator = null)
    {
        // One backfiller per company at a time. The insert below acts on a select
        // that ran a moment earlier, so two page loads landing together could
        // otherwise insert the same (lead, evaluator) pair twice. A named MySQL
        // lock makes that impossible without depending on a unique index being
        // present on the table -- this database has been altered by hand before,
        // and the page must be correct either way.
        $lockName = 'qe_backfill_' . (int) $ownerId;
        $lock = DB::selectOne('SELECT GET_LOCK(?, 0) AS got', [$lockName]);
        if (!$lock || (int) $lock->got !== 1) {
            // Someone else is already doing it; their run covers this request too.
            return;
        }

        try {
            $this->insertMissingEvaluations($ownerId, $onlyEvaluator);
        } finally {
            DB::select('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
        }
    }

    /**
     * The backfill itself. Only ever called while the company's lock is held.
     */
    private function insertMissingEvaluations($ownerId, $onlyEvaluator = null)
    {
        $missing = DB::table('user_leads')
            ->join('leads', 'leads.id', '=', 'user_leads.lead_id')
            ->leftJoin('quality_evaluations', function ($join) {
                $join->on('quality_evaluations.lead_id', '=', 'user_leads.lead_id')
                    ->on('quality_evaluations.evaluator_id', '=', 'user_leads.user_id');
            })
            ->where('leads.created_by', $ownerId)
            ->whereNull('quality_evaluations.id');

        if ($onlyEvaluator !== null) {
            $missing->where('user_leads.user_id', $onlyEvaluator);
        }

        // distinct(): a duplicated user_leads row would otherwise produce the same
        // (lead, evaluator) pair twice in one insert.
        $rows = $missing->distinct()->get(['user_leads.lead_id', 'user_leads.user_id']);

        if ($rows->isEmpty()) {
            return;
        }

        $now = now();
        foreach ($rows->chunk(500) as $chunk) {
            $batch = [];
            foreach ($chunk as $row) {
                $batch[] = [
                    'lead_id'           => $row->lead_id,
                    'evaluator_id'      => $row->user_id,
                    'is_potential_lead' => QualityEvaluation::POTENTIAL_UNKNOWN,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ];
            }
            // insertOrIgnore: another request may have created the same row between
            // the select above and this write.
            DB::table('quality_evaluations')->insertOrIgnore($batch);
        }
    }

    /**
     * Apply shared filters to a QualityEvaluation query (already joined to `leads`).
     * Adds JOINs for user_leads / sources / labels / products / interests as needed.
     */
    private function applyFilters($query, Request $request)
    {
        if ($request->lead_id) {
            $query->where('quality_evaluations.lead_id', $request->lead_id);
        }

        // QE-specific filters
        if ($request->filled('is_potential_lead')) {
            $query->where('quality_evaluations.is_potential_lead', $request->is_potential_lead);
        }
        if ($request->engagement_level) {
            $query->where('quality_evaluations.engagement_level', $request->engagement_level);
        }
        if ($request->course_fit_level) {
            $query->where('quality_evaluations.course_fit_level', $request->course_fit_level);
        }
        if ($request->rating) {
            $query->where('quality_evaluations.rating', $request->rating);
        }
        if ($request->career_stage) {
            $query->where('quality_evaluations.career_stage', $request->career_stage);
        }

        // Lead-based filters (mirrors /leads)
        if ($request->name && $request->phone && $request->name === $request->phone) {
            $term = $request->name;
            $query->where(function ($q) use ($term) {
                $q->where('leads.name', 'like', "%{$term}%")
                    ->orWhere('leads.phone', 'like', "%{$term}%");
            });
        } else {
            if ($request->name)  $query->where('leads.name', 'like', '%' . $request->name . '%');
            if ($request->phone) $query->where('leads.phone', 'like', '%' . $request->phone . '%');
        }

        if ($request->subject)       $query->where('leads.subject', 'like', '%' . $request->subject . '%');
        if ($request->pipeline)      $query->where('leads.pipeline_id', $request->pipeline);
        if ($request->stage)         $query->where('leads.stage_id', $request->stage);
        if ($request->country)       $query->where('leads.country', 'like', '%' . $request->country . '%');
        if ($request->city)          $query->where(function ($q) use ($request) {
            $q->where('leads.city', 'like', '%' . $request->city . '%')
                ->orWhere('quality_evaluations.city', 'like', '%' . $request->city . '%');
        });
        if ($request->campaign_name) $query->where('leads.campaign_name', $request->campaign_name);
        if ($request->costing_type)  $query->where('leads.costing_type', 'like', '%' . $request->costing_type . '%');
        if ($request->gender)        $query->where('leads.gender', $request->gender);

        if ($request->min_cost && $request->max_cost) {
            $query->whereBetween('leads.cost', [$request->min_cost, $request->max_cost]);
        } elseif ($request->min_cost) {
            $query->where('leads.cost', '>=', $request->min_cost);
        } elseif ($request->max_cost) {
            $query->where('leads.cost', '<=', $request->max_cost);
        }

        if ($request->users) {
            $user_ids = explode(',', $request->users);
            $query->where(function ($q) use ($user_ids) {
                $q->whereIn('leads.user_id', $user_ids)
                    ->orWhereExists(function ($subQ) use ($user_ids) {
                        $subQ->select(DB::raw(1))
                            ->from('user_leads')
                            ->whereColumn('user_leads.lead_id', 'leads.id')
                            ->whereIn('user_leads.user_id', $user_ids);
                    });
            });
        }

        foreach (['products', 'sources', 'labels', 'interests'] as $csvField) {
            if ($request->$csvField) {
                $ids = explode(',', $request->$csvField);
                $query->where(function ($q) use ($ids, $csvField) {
                    foreach ($ids as $id) {
                        $q->orWhereRaw("FIND_IN_SET(?, leads.$csvField)", [$id]);
                    }
                });
            }
        }

        if ($request->date) {
            $date = explode(' to ', $request->date);
            if (count($date) == 2) {
                $query->whereBetween('leads.date', [$date[0], $date[1]]);
            } else {
                $query->where('leads.date', $date[0]);
            }
        }

        if ($request->updated_date) {
            $d = explode(' to ', $request->updated_date);
            if (count($d) == 2) {
                $query->whereBetween('leads.updated_at', [$d[0] . ' 00:00:00', $d[1] . ' 23:59:59']);
            } else {
                $query->whereDate('leads.updated_at', $request->updated_date);
            }
        }

        if ($request->sales_group) {
            $groupUserIds = User::where('created_by', Auth::user()->creatorId())
                ->where('sales_group', $request->sales_group)
                ->pluck('id')->toArray();
            if (!empty($groupUserIds)) {
                $query->whereExists(function ($subQ) use ($groupUserIds) {
                    $subQ->select(DB::raw(1))
                        ->from('user_leads')
                        ->whereColumn('user_leads.lead_id', 'leads.id')
                        ->whereIn('user_leads.user_id', $groupUserIds);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if ($request->unassigned) {
            $query->whereNotExists(function ($subQ) {
                $subQ->select(DB::raw(1))
                    ->from('user_leads')
                    ->whereColumn('user_leads.lead_id', 'leads.id');
            });
        }

        if ($request->no_activity) {
            $query->whereNotExists(function ($subQ) {
                    $subQ->select(DB::raw(1))->from('lead_activity_logs')->whereColumn('lead_activity_logs.lead_id', 'leads.id');
                })
                ->whereNotExists(function ($subQ) {
                    $subQ->select(DB::raw(1))->from('lead_discussions')->whereColumn('lead_discussions.lead_id', 'leads.id');
                })
                ->whereNotExists(function ($subQ) {
                    $subQ->select(DB::raw(1))->from('lead_calls')->whereColumn('lead_calls.lead_id', 'leads.id');
                })
                ->whereNotExists(function ($subQ) {
                    $subQ->select(DB::raw(1))->from('deals')->whereColumn('deals.lead_id', 'leads.id');
                })
                ->whereNotExists(function ($subQ) {
                    $subQ->select(DB::raw(1))->from('sales_meetings')->whereColumn('sales_meetings.lead_id', 'leads.id');
                });
        }
    }

    /**
     * The lead must exist AND belong to this company.
     */
    private function ownLeadRule()
    {
        return \Illuminate\Validation\Rule::exists('leads', 'id')
            ->where('created_by', Auth::user()->ownerId());
    }

    /**
     * Autocomplete source for the Lead picker on the create/edit forms.
     * At most 25 rows, so neither the payload nor the widget grows with the
     * size of the leads table.
     */
    public function leadsSearch(Request $request)
    {
        if (!Auth::user()->can('create quality evaluation') && !Auth::user()->can('edit quality evaluation')) {
            return response()->json([], 401);
        }

        $query = Lead::where('created_by', Auth::user()->ownerId());

        // An ordinary evaluator only evaluates the leads assigned to them.
        if (!$this->isPrivileged()) {
            $userId = Auth::user()->id;
            $query->whereExists(function ($sub) use ($userId) {
                $sub->select(DB::raw(1))
                    ->from('user_leads')
                    ->whereColumn('user_leads.lead_id', 'leads.id')
                    ->where('user_leads.user_id', $userId);
            });
        }

        $term = trim((string) $request->get('q'));
        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', '%' . $term . '%')
                    ->orWhere('phone', 'like', '%' . $term . '%')
                    ->orWhere('email', 'like', '%' . $term . '%');
            });
        }

        $leads = $query->orderBy('id', 'desc')->limit(25)->get(['id', 'name', 'phone']);

        // name and phone apart, so the picker can put them on their own lines
        return response()->json($leads->map(function ($lead) {
            return [
                'id'    => $lead->id,
                'name'  => $lead->name,
                'phone' => (string) $lead->phone,
                'text'  => $lead->name . ($lead->phone ? ' - ' . $lead->phone : ''),
            ];
        })->values());
    }

    public function create(Request $request)
    {
        if (Auth::user()->can('create quality evaluation')) {
            // Only the lead that was passed in, if any. This used to load every
            // lead in the company and render one <option> for each.
            $leadId = $request->query('lead_id');
            $selectedLead = $leadId
                ? Lead::where('created_by', Auth::user()->ownerId())->where('id', $leadId)->first(['id', 'name'])
                : null;

            return view('quality-evaluations.create', compact('selectedLead', 'leadId'));
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    public function store(Request $request)
    {
        if (Auth::user()->can('create quality evaluation')) {
            $validator = Validator::make($request->all(), [
                // scoped: 'exists:leads,id' accepted another company's lead id
                'lead_id' => ['required', $this->ownLeadRule()],
                'university_major' => 'nullable|string|max:255',
                'university_major_other' => 'nullable|string|max:255',
                'career_stage' => 'nullable|in:student,graduate_employed_same_field,graduate_employed_different_field,graduate_unemployed,other',
                'engagement_level' => 'required|in:highly_engaged,moderate,low_engagement,not_engaged,other',
                'interest_indicators' => 'nullable|array',
                'course_fit_level' => 'required|in:perfect_fit,good_fit,partial_fit,not_a_fit',
                'is_potential_lead' => 'required|in:yes,no,unknown',
                'rejection_reasons' => 'nullable|array',
                'city' => 'nullable|string|max:255',
                'rating' => 'required|integer|min:1|max:5',
                'notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->with('error', $validator->errors()->first())->withInput();
            }

            $evaluation = new QualityEvaluation();
            $evaluation->lead_id = $request->lead_id;
            $evaluation->evaluator_id = Auth::user()->id;
            $evaluation->university_major = $request->university_major;
            $evaluation->university_major_other = $request->university_major_other;
            $evaluation->career_stage = $request->career_stage;
            $evaluation->engagement_level = $request->engagement_level;
            $evaluation->interest_indicators = $request->interest_indicators;
            $evaluation->course_fit_level = $request->course_fit_level;
            $evaluation->is_potential_lead = $request->is_potential_lead;
            $evaluation->rejection_reasons = $request->rejection_reasons;
            $evaluation->city = $request->city;
            $evaluation->rating = $request->rating;
            $evaluation->notes = $request->notes;
            $evaluation->save();

            if ($request->has('lead_id') && $request->lead_id) {
                return redirect()->route('leads.show', $request->lead_id)->with('success', __('Quality evaluation created successfully.'));
            }

            return redirect()->route('quality-evaluations.index')->with('success', __('Quality evaluation created successfully.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Users who see every evaluator's rows. Same rule index() and export() apply;
     * kept in one place so a fourth copy cannot drift from the other three.
     */
    private function isPrivileged()
    {
        $privilegedNames = ['Alaa', 'Saba', 'aber'];

        return Auth::user()->type == 'company' ||
            Auth::user()->type == 'CSO' ||
            Auth::user()->hasRole('Sales Manager') ||
            Auth::user()->type == 'Alaa,Saba,aber' ||
            Auth::user()->type == 'fadi' ||
            in_array(Auth::user()->name, $privilegedNames) ||
            Auth::user()->hasAnyRole($privilegedNames);
    }

    /**
     * An evaluation this user is allowed to reach, or a 404.
     *
     * Ownership goes through the lead, which is the row every other screen already
     * treats as owning the record. show/edit/update/destroy all used a bare
     * findOrFail($id), so any logged-in user reached any company's evaluation by id.
     */
    private function ownedEvaluation($id, array $with = [])
    {
        $query = QualityEvaluation::whereHas('lead', function ($q) {
            $q->where('created_by', Auth::user()->ownerId());
        });

        if ($with) {
            $query->with($with);
        }

        return $query->findOrFail($id);
    }

    public function show($id)
    {
        if (Auth::user()->can('manage quality evaluation')) {
            $evaluation = $this->ownedEvaluation($id, ['lead', 'evaluator']);

            // The list already limits an ordinary user to their own evaluations;
            // without this, a guessed id showed them a colleague's.
            $isPrivileged = $this->isPrivileged();
            if (!$isPrivileged && $evaluation->evaluator_id != Auth::user()->id) {
                return redirect()->back()->with('error', __('You can only view your own evaluations.'));
            }

            return view('quality-evaluations.show', compact('evaluation'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function edit($id, Request $request)
    {
        $isPrivileged = in_array(Auth::user()->type, ['Alaa,Saba,aber', 'fadi']);

        if (Auth::user()->can('edit quality evaluation') || $isPrivileged) {
            $evaluation = $this->ownedEvaluation($id, ['lead']);

            if (!$isPrivileged && $evaluation->evaluator_id != Auth::user()->id) {
                return redirect()->back()->with('error', __('You can only edit your own evaluations.'));
            }

            // Only the lead this evaluation already points at. This used to load
            // every lead in the company and render one <option> for each, which on
            // a large tenant exhausted PHP's memory before anything was sent --
            // the blank page. The field is a server-searched picker now.
            $selectedLead = $evaluation->lead;
            $leadId = $request->query('lead_id');

            return view('quality-evaluations.edit', compact('evaluation', 'selectedLead', 'leadId'));
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    public function update(Request $request, $id)
    {
        $isPrivileged = in_array(Auth::user()->type, ['Alaa,Saba,aber', 'fadi']);

        if (Auth::user()->can('edit quality evaluation') || $isPrivileged) {
            $evaluation = $this->ownedEvaluation($id);

            if (!$isPrivileged && $evaluation->evaluator_id != Auth::user()->id) {
                return redirect()->back()->with('error', __('You can only edit your own evaluations.'));
            }

            $validator = Validator::make($request->all(), [
                // scoped: 'exists:leads,id' accepted another company's lead id
                'lead_id' => ['required', $this->ownLeadRule()],
                'university_major' => 'nullable|string|max:255',
                'university_major_other' => 'nullable|string|max:255',
                'career_stage' => 'nullable|in:student,graduate_employed_same_field,graduate_employed_different_field,graduate_unemployed,other',
                'engagement_level' => 'required|in:highly_engaged,moderate,low_engagement,not_engaged,other',
                'interest_indicators' => 'nullable|array',
                'course_fit_level' => 'required|in:perfect_fit,good_fit,partial_fit,not_a_fit',
                'is_potential_lead' => 'required|in:yes,no,unknown',
                'rejection_reasons' => 'nullable|array',
                'city' => 'nullable|string|max:255',
                'rating' => 'required|integer|min:1|max:5',
                'notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->with('error', $validator->errors()->first())->withInput();
            }

            $evaluation->lead_id = $request->lead_id;
            $evaluation->university_major = $request->university_major;
            $evaluation->university_major_other = $request->university_major_other;
            $evaluation->career_stage = $request->career_stage;
            $evaluation->engagement_level = $request->engagement_level;
            $evaluation->interest_indicators = $request->interest_indicators;
            $evaluation->course_fit_level = $request->course_fit_level;
            $evaluation->is_potential_lead = $request->is_potential_lead;
            $evaluation->rejection_reasons = $request->rejection_reasons;
            $evaluation->city = $request->city;
            $evaluation->rating = $request->rating;
            $evaluation->notes = $request->notes;
            $evaluation->save();

            return redirect()->route('quality-evaluations.index')->with('success', __('Quality evaluation updated successfully.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function export(Request $request)
    {
        if (!Auth::user()->can('manage quality evaluation')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $userId = Auth::user()->id;
        $privilegedNames = ['Alaa', 'Saba', 'aber'];
        $isPrivileged = Auth::user()->type == 'company' ||
            Auth::user()->type == 'CSO' ||
            Auth::user()->hasRole('Sales Manager') ||
            Auth::user()->type == 'Alaa,Saba,aber' ||
            Auth::user()->type == 'fadi' ||
            in_array(Auth::user()->name, $privilegedNames) ||
            Auth::user()->hasAnyRole($privilegedNames);

        $query = QualityEvaluation::with(['lead', 'evaluator'])
            ->join('leads', 'quality_evaluations.lead_id', '=', 'leads.id')
            // the export joined leads with no company scope, so a privileged user
            // downloaded every company's evaluations
            ->where('leads.created_by', Auth::user()->ownerId());

        if (!$isPrivileged) {
            $query->whereExists(function ($sub) use ($userId) {
                $sub->select(DB::raw(1))
                    ->from('user_leads')
                    ->whereColumn('user_leads.lead_id', 'leads.id')
                    ->where('user_leads.user_id', $userId);
            })->where('quality_evaluations.evaluator_id', $userId);
        } else {
            if ($request->evaluator_id) {
                $query->whereExists(function ($sub) use ($request) {
                    $sub->select(DB::raw(1))
                        ->from('user_leads')
                        ->whereColumn('user_leads.lead_id', 'leads.id')
                        ->where('user_leads.user_id', $request->evaluator_id);
                })->where('quality_evaluations.evaluator_id', $request->evaluator_id);
            } else {
                $query->whereIn('quality_evaluations.id', function ($sub) {
                    $sub->selectRaw('MAX(id)')->from('quality_evaluations')->groupBy('lead_id');
                });
            }
        }

        $this->applyFilters($query, $request);

        $evaluations = $query->select('quality_evaluations.*')
            ->distinct()
            ->orderBy('quality_evaluations.lead_id', 'desc')
            ->get();

        $careerStageLabels = [
            'student' => 'Student',
            'graduate_employed_same_field' => 'Graduate - Employed in Same Field',
            'graduate_employed_different_field' => 'Graduate - Employed in Different Field',
            'graduate_unemployed' => 'Graduate - Unemployed',
        ];
        $engagementLabels = [
            'highly_engaged' => 'Highly Engaged',
            'moderate' => 'Moderate',
            'low_engagement' => 'Low Engagement',
            'not_engaged' => 'Not Engaged',
        ];
        $courseFitLabels = [
            'perfect_fit' => 'Perfect Fit',
            'good_fit' => 'Good Fit',
            'partial_fit' => 'Partial Fit',
            'not_a_fit' => 'Not a Fit',
        ];
        $potentialLabels = [
            QualityEvaluation::POTENTIAL_YES     => 'Yes',
            QualityEvaluation::POTENTIAL_NO      => 'No',
            QualityEvaluation::POTENTIAL_UNKNOWN => 'Unknown',
        ];

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="quality_evaluations_' . now()->format('Y-m-d_His') . '.csv"',
        ];

        $callback = function () use ($evaluations, $careerStageLabels, $engagementLabels, $courseFitLabels, $potentialLabels) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, [
                'ID', 'Lead Name', 'Lead Phone', 'Campaign', 'Evaluator',
                'University Major', 'Age', 'Career Stage', 'Engagement Level',
                'Course Fit Level', 'Potential Lead', 'Rating', 'City',
                'Interest Indicators', 'Rejection Reasons', 'Notes', 'Date',
            ]);

            foreach ($evaluations as $eval) {
                $major = $eval->university_major === 'other' ? ($eval->university_major_other ?? 'Other') : ($eval->university_major ?? '');
                $interestIndicators = is_array($eval->interest_indicators) ? implode(', ', $eval->interest_indicators) : '';
                $rejectionReasons = is_array($eval->rejection_reasons) ? implode(', ', $eval->rejection_reasons) : '';

                fputcsv($handle, [
                    $eval->id,
                    $eval->lead->name ?? '',
                    $eval->lead->phone ?? '',
                    $eval->lead->campaign_name ?? '',
                    $eval->evaluator->name ?? '',
                    $major,
                    $eval->age ?? '',
                    $careerStageLabels[$eval->career_stage] ?? ($eval->career_stage ?? ''),
                    $engagementLabels[$eval->engagement_level] ?? ($eval->engagement_level ?? ''),
                    $courseFitLabels[$eval->course_fit_level] ?? ($eval->course_fit_level ?? ''),
                    $potentialLabels[$eval->is_potential_lead] ?? 'Unknown',
                    $eval->rating ?? '',
                    $eval->city ?? '',
                    $interestIndicators,
                    $rejectionReasons,
                    $eval->notes ?? '',
                    $eval->created_at ? $eval->created_at->format('Y-m-d H:i') : '',
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Users Activity Report — given a date range, return per-user evaluation
     * counts and last activity, flagging users with zero/low activity.
     *
     * Scope: only users whose creator_id matches the current user's creator
     * and who belong to the sales/CSO roles (same set used as evaluators).
     */
    public function usersActivityReport(Request $request)
    {
        if (!Auth::user()->can('manage quality evaluation')) {
            return response()->json(['error' => __('Permission denied.')], 403);
        }

        $privilegedNames = ['Alaa', 'Saba', 'aber'];
        $isPrivileged = Auth::user()->type == 'company' ||
            Auth::user()->type == 'CSO' ||
            Auth::user()->hasRole('Sales Manager') ||
            Auth::user()->type == 'Alaa,Saba,aber' ||
            Auth::user()->type == 'fadi' ||
            in_array(Auth::user()->name, $privilegedNames) ||
            Auth::user()->hasAnyRole($privilegedNames);

        if (!$isPrivileged) {
            return response()->json(['error' => __('Permission denied.')], 403);
        }

        $dateRange = $request->input('date_range');
        $threshold = (int) $request->input('threshold', 0);
        $statusFilter = $request->input('status'); // inactive | low | active | all

        $startDate = null;
        $endDate = null;
        if ($dateRange) {
            $parts = explode(' to ', $dateRange);
            $startDate = $parts[0] ?? null;
            $endDate = $parts[1] ?? $startDate;
        }
        if (!$startDate || !$endDate) {
            // Default to last 30 days if no range provided
            $endDate = now()->format('Y-m-d');
            $startDate = now()->subDays(30)->format('Y-m-d');
        }

        $startDateTime = $startDate . ' 00:00:00';
        $endDateTime   = $endDate . ' 23:59:59';

        // Same scope used to populate the Evaluators dropdown
        $users = User::where(function ($q) {
            $q->where('created_by', Auth::user()->creatorId())
                ->orWhere('id', Auth::user()->creatorId());
        })
            ->where(function ($q) {
                $q->where('type', 'Sales Employee')
                    ->orWhere('type', 'CSO')
                    ->orWhere('type', 'Alaa,Saba,aber')
                    ->orWhere('type', 'fadi')
                    ->orWhereHas('roles', function ($r) {
                        $r->whereIn('name', ['Sales Employee', 'sales employee', 'CSO', 'Alaa,Saba,aber', 'fadi']);
                    });
            })
            ->select('id', 'name', 'email', 'type', 'sales_group')
            ->get();

        $userIds = $users->pluck('id')->toArray();

        // Counts in range — exclude auto-created "unknown" placeholders so we
        // measure actual evaluation work, not auto-bootstrapped rows.
        $rangeCounts = QualityEvaluation::select('evaluator_id', DB::raw('COUNT(*) as total'))
            ->whereIn('evaluator_id', $userIds)
            ->whereBetween('created_at', [$startDateTime, $endDateTime])
            ->where('is_potential_lead', '!=', QualityEvaluation::POTENTIAL_UNKNOWN)
            ->groupBy('evaluator_id')
            ->pluck('total', 'evaluator_id');

        // Last activity per user (ever) — likewise excludes the unknown defaults
        $lastActivity = QualityEvaluation::select('evaluator_id', DB::raw('MAX(created_at) as last_at'))
            ->whereIn('evaluator_id', $userIds)
            ->where('is_potential_lead', '!=', QualityEvaluation::POTENTIAL_UNKNOWN)
            ->groupBy('evaluator_id')
            ->pluck('last_at', 'evaluator_id');

        // Assigned leads count per user (workload context)
        $assignedLeadCounts = DB::table('user_leads')
            ->select('user_id', DB::raw('COUNT(DISTINCT lead_id) as total'))
            ->whereIn('user_id', $userIds)
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $rows = $users->map(function ($u) use ($rangeCounts, $lastActivity, $assignedLeadCounts, $threshold) {
            $count = (int) ($rangeCounts[$u->id] ?? 0);
            $assignedLeads = (int) ($assignedLeadCounts[$u->id] ?? 0);
            $last = $lastActivity[$u->id] ?? null;

            if ($count === 0) {
                $status = 'inactive';
            } elseif ($threshold > 0 && $count < $threshold) {
                $status = 'low';
            } else {
                $status = 'active';
            }

            return [
                'id'             => $u->id,
                'name'           => $u->name,
                'email'          => $u->email,
                'type'           => $u->type,
                'sales_group'    => $u->sales_group,
                'count'          => $count,
                'assigned_leads' => $assignedLeads,
                'last_activity'  => $last ? \Carbon\Carbon::parse($last)->format('Y-m-d H:i') : null,
                'last_human'     => $last ? \Carbon\Carbon::parse($last)->diffForHumans() : __('Never'),
                'status'         => $status,
            ];
        })->values();

        if ($statusFilter && in_array($statusFilter, ['inactive', 'low', 'active'])) {
            $rows = $rows->where('status', $statusFilter)->values();
        }

        // Sort: inactive first, then low, then active; within each, by count asc
        $rows = $rows->sortBy([
            fn ($a, $b) => ['inactive' => 0, 'low' => 1, 'active' => 2][$a['status']] <=> ['inactive' => 0, 'low' => 1, 'active' => 2][$b['status']],
            fn ($a, $b) => $a['count'] <=> $b['count'],
        ])->values();

        return response()->json([
            'start_date' => $startDate,
            'end_date'   => $endDate,
            'threshold'  => $threshold,
            'totals'     => [
                'users'    => $users->count(),
                'inactive' => $rows->where('status', 'inactive')->count(),
                'low'      => $rows->where('status', 'low')->count(),
                'active'   => $rows->where('status', 'active')->count(),
            ],
            'rows'       => $rows,
        ]);
    }

    public function destroy($id)
    {
        if (Auth::user()->can('delete quality evaluation')) {
            $evaluation = $this->ownedEvaluation($id);

            if ($evaluation->evaluator_id != Auth::user()->id) {
                return redirect()->back()->with('error', __('You can only delete your own evaluations.'));
            }

            $evaluation->delete();

            if (request()->has('lead_id') && request()->lead_id) {
                return redirect()->route('leads.show', request()->lead_id)->with('success', __('Quality evaluation deleted successfully.'));
            }

            return redirect()->route('quality-evaluations.index')->with('success', __('Quality evaluation deleted successfully.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }
}
