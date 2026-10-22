<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Stage;
use App\Models\Pipeline;
use App\Models\ProductService;
use App\Models\DealAttachment;
use App\Models\CustomField;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DealApiController extends Controller
{
    use ApiResponser;

    public function getDeals(Request $request)
    {
        $perPage = (int) ($request->per_page ?? 15);
        $query   = Deal::where('deals.created_by', Auth::user()->creatorId())
            ->with(['stage', 'lead', 'pipeline', 'attachments'])
            ->select('deals.*');

        if ($request->search) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('deals.name', 'like', "%{$s}%")
                  ->orWhere('deals.status', 'like', "%{$s}%");
            });
        } else {
            if ($request->name)      $query->where('deals.name', 'like', "%{$request->name}%");
            if ($request->status)    $query->where('deals.status', 'like', "%{$request->status}%");
        }

        if ($request->stage_id)    $query->where('deals.stage_id', $request->stage_id);
        if ($request->pipeline_id) $query->where('deals.pipeline_id', $request->pipeline_id);
        if ($request->lead_id)     $query->where('deals.lead_id', $request->lead_id);

        if ($request->date_from && $request->date_to) {
            $query->whereBetween('deals.datetime', [$request->date_from, $request->date_to]);
        } elseif ($request->date_from) {
            $query->where('deals.datetime', '>=', $request->date_from);
        } elseif ($request->date_to) {
            $query->where('deals.datetime', '<=', $request->date_to);
        }

        $result = $query->orderBy('deals.id', 'desc')->paginate($perPage);

        $result->getCollection()->transform(function ($deal) {
            $deal->products_list = $deal->products
                ? ProductService::whereIn('id', explode(',', $deal->products))->get()
                : [];
            return $deal;
        });

        return $this->success([
            'deals'      => $result->items(),
            'pagination' => [
                'total'        => $result->total(),
                'per_page'     => $result->perPage(),
                'current_page' => $result->currentPage(),
                'last_page'    => $result->lastPage(),
            ],
        ], 'Deals retrieved.');
    }

    public function createDeal(Request $request)
    {
        // The web form has always required this; the API never checked, so a
        // role meant nothing here. That is why an automation holding an
        // ordinary user's credentials could write deals at will.
        if (!Auth::user()->can('create deal')) {
            // logged like the rest: turning the permission off is how the
            // automation gets stopped, and the log is how that is confirmed
            $this->logDealApiCaller($request, 'refused', null);

            return $this->error('Permission Denied.', 403);
        }

        $ownerId = Auth::user()->creatorId();

        $validator = Validator::make(
            $request->all(),
            [
                'name' => 'required',
                // 'exists:leads,id' on its own let a deal be hung on another
                // company's lead. The web form was scoped months ago; this was
                // not.
                'lead_id' => [
                    'required',
                    Rule::exists('leads', 'id')->where('created_by', $ownerId),
                ],
                'stage_id' => 'required|exists:stages,id',
            ]
        );

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Between 2026-05-16 and 2026-10-08 this endpoint wrote over a hundred
        // deals a minute: something kept re-posting the same "<lead> - Deal"
        // and nothing here ever said no. Two identical deals on one lead carry
        // no information, so the first one wins and every repeat gets it back
        // instead of a new row. The named lock closes the gap between the
        // lookup and the insert when two requests arrive together.
        $lockName = 'deal_create_' . (int) $ownerId . '_' . (int) $request->lead_id;
        $lock = DB::selectOne('SELECT GET_LOCK(?, 5) AS got', [$lockName]);
        $locked = $lock && (int) $lock->got === 1;

        try {
            $existing = Deal::where('created_by', $ownerId)
                ->where('lead_id', $request->lead_id)
                ->where('name', $request->name)
                ->first();

            if ($existing) {
                $this->logDealApiCaller($request, 'duplicate', $existing->id);

                return $this->success(
                    ['deal' => $existing, 'duplicate' => true],
                    'A deal with this name already exists on this lead.'
                );
            }

            $lead = Lead::find($request->lead_id);
            $stage = Stage::find($request->stage_id);

            $deal = new Deal();
            $deal->name = $request->name;
            $deal->lead_id = $request->lead_id;
            if ($request->products) {
                $deal->products = implode(',', array_filter($request->products));
            }
            $deal->created_by = $ownerId;
            // Nothing in app/Http/Controllers/Api ever set this, which is why
            // every API row shows "-" under Created By on the lead page and
            // why the source of a row could not be told apart from the web.
            $deal->added_by = Auth::id();
            $deal->stage_id = $request->stage_id;
            $deal->status = $stage ? $stage->name : null;
            $deal->pipeline_id = $lead->pipeline_id ?? 0;
            $deal->notes = $request->notes;
            $deal->datetime = $request->datetime;
            $deal->save();

            $this->logDealApiCaller($request, 'created', $deal->id);
        } finally {
            if ($locked) {
                DB::select('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
            }
        }

        if ($request->customField) {
            CustomField::saveData($deal, $request->customField);
        }

        return $this->success(['deal' => $deal], 'Deal created successfully.');
    }

    /**
     * One line per write attempt on this endpoint, in
     * storage/logs/deal-api-<date>.log.
     *
     * Between 2026-05-16 and 2026-10-08 something re-posted the same deal over
     * and over -- more than a hundred rows a minute at its worst. The duplicate
     * guard stops the damage but says nothing about who is calling: added_by
     * names a user account that several tools could share. The IP, the
     * forwarded IP, the user agent and the token id name the machine.
     *
     * Nothing in here may cost the request, hence the catch-all.
     */
    private function logDealApiCaller(Request $request, $outcome, $dealId)
    {
        try {
            $user = Auth::user();
            $token = $user && method_exists($user, 'currentAccessToken') ? $user->currentAccessToken() : null;

            $line = implode(' | ', [
                date('Y-m-d H:i:s'),
                str_pad($outcome, 9),
                'user=' . ($user ? $user->id : '-'),
                'token=' . ($token && isset($token->id) ? $token->id : '-'),
                'ip=' . $request->ip(),
                'fwd=' . ($request->header('X-Forwarded-For') ?: '-'),
                'lead=' . (int) $request->lead_id,
                'deal=' . ($dealId ?: '-'),
                'agent=' . substr((string) $request->userAgent(), 0, 120),
                'name=' . substr((string) $request->name, 0, 80),
            ]);

            file_put_contents(
                storage_path('logs/deal-api-' . date('Y-m-d') . '.log'),
                $line . PHP_EOL,
                FILE_APPEND | LOCK_EX
            );
        } catch (\Throwable $e) {
            // a log line is never worth a failed request
        }
    }

    public function getDeal($id)
    {
        $deal = Deal::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->with([
                'stage',
                'lead.stage',
                'lead.pipeline',
                'lead.users',
                'pipeline',
                'attachments',
                'discussions',
                'calls',
                'dealPurchase',
            ])
            ->first();

        if (!$deal) {
            return $this->error('Deal not found.', 404);
        }

        // Resolve products
        if ($deal->products) {
            $deal->products_list = ProductService::whereIn('id', explode(',', $deal->products))->get();
        } else {
            $deal->products_list = [];
        }

        // Get Custom Fields
        $deal->custom_fields = CustomField::getData($deal, 'deal');

        return $this->success(['deal' => $deal], 'Deal details.');
    }

    public function updateDeal(Request $request, $id)
    {
        if (!Auth::user()->can('edit deal')) {
            return $this->error('Permission Denied.', 403);
        }

        $deal = Deal::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$deal) {
            return $this->error('Deal not found.', 404);
        }

        $validator = Validator::make(
            $request->all(),
            [
                'name' => 'required',
                'stage_id' => 'required|exists:stages,id',
            ]
        );

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $deal->name = $request->name;
        if ($request->has('lead_id')) {
            $deal->lead_id = $request->lead_id;
        }

        if ($request->products) {
            $deal->products = implode(',', array_filter($request->products));
        }

        $deal->stage_id = $request->stage_id;
        $stage = Stage::find($request->stage_id);
        $deal->status = $stage ? $stage->name : null;

        if ($request->has('notes')) {
            $deal->notes = $request->notes;
        }
        if ($request->has('datetime')) {
            $deal->datetime = $request->datetime;
        }

        $deal->save();

        if ($request->customField) {
            CustomField::saveData($deal, $request->customField);
        }

        return $this->success(['deal' => $deal], 'Deal updated successfully.');
    }

    public function deleteDeal($id)
    {
        if (!Auth::user()->can('delete deal')) {
            return $this->error('Permission Denied.', 403);
        }

        $deal = Deal::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$deal) {
            return $this->error('Deal not found.', 404);
        }
        $deal->delete();
        return $this->success([], 'Deal deleted successfully.');
    }

    public function addDealProduct(Request $request, $id)
    {
        $deal = Deal::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$deal) {
            return $this->error('Deal not found.', 404);
        }

        $validator = Validator::make(
            $request->all(),
            [
                'product_id' => 'required|exists:product_services,id',
            ]
        );

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $old_products = $deal->products ? explode(',', $deal->products) : [];
        if (!in_array($request->product_id, $old_products)) {
            $old_products[] = $request->product_id;
            $deal->products = implode(',', $old_products);
            $deal->save();
        }

        return $this->success(['deal' => $deal], 'Product added to deal.');
    }

    public function getDealAttachments($id)
    {
        $deal = Deal::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$deal) {
            return $this->error('Deal not found.', 404);
        }
        $attachments = DealAttachment::where('deal_id', $id)->get();
        return $this->success(['attachments' => $attachments], 'Deal attachments.');
    }

    public function uploadDealAttachment(Request $request, $id)
    {
        $deal = Deal::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$deal) {
            return $this->error('Deal not found.', 404);
        }

        $validator = Validator::make(
            $request->all(),
            [
                'file' => 'required|file|max:10240',
            ]
        );

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $file = $request->file('file');
        $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $storagePath = 'deal_attachments/' . $id;
        $file->storeAs($storagePath, $fileName, 'public');

        $attachment = DealAttachment::create([
            'deal_id' => $id,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $storagePath . '/' . $fileName,
            'file_type' => $file->getClientMimeType(), // Simplified
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'uploaded_by' => Auth::id(),
        ]);

        return $this->success(['attachment' => $attachment], 'Attachment uploaded successfully.');
    }

    public function getPipelines()
    {
        $pipelines = Pipeline::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['pipelines' => $pipelines], 'Pipelines retrieved.');
    }

    public function getDealStages()
    {
        $stages = Stage::where('created_by', Auth::user()->creatorId())->orderBy('order')->get();
        return $this->success(['stages' => $stages], 'Stages retrieved.');
    }

    public function getLeadDeals($id)
    {
        $lead = Lead::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$lead) {
            return $this->error('Lead not found.', 404);
        }

        $deals = Deal::where('lead_id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->with(['stage', 'pipeline', 'attachments'])
            ->get();

        return $this->success(['deals' => $deals], 'Lead deals.');
    }
}
