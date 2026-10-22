<?php

namespace App\Exports;

use App\Models\Label;
use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\Pipeline;
use App\Models\ProductService;
use App\Models\Source;
use App\Services\LeadScope;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class LeadExport implements FromQuery, WithHeadings, WithMapping, WithCustomCsvSettings
{
    protected $request;

    /** id => name lookups, loaded once instead of per exported row. */
    protected $pipelines = [];
    protected $stages = [];
    protected $sources = [];
    protected $products = [];
    protected $labels = [];

    public function __construct($request)
    {
        $this->request = $request;
        $this->loadLookups();
    }

    public function getCsvSettings(): array
    {
        return [
            'use_bom' => true,
        ];
    }

    /**
     * Rows are streamed in chunks by the FromQuery concern, so exporting a large
     * pipeline no longer loads every lead into memory at once.
     */
    public function query()
    {
        $usr = \Auth::user();
        $request = $this->request;

        $query = Lead::select('leads.*')->with('users:id,name');

        // Same visibility and the same filters as the leads page itself, so the
        // downloaded file always matches what was on screen.
        LeadScope::applyVisibility($query, $usr);
        LeadScope::applyFilters($query, $request, $usr);

        if ($request->pipeline) {
            $query->where('leads.pipeline_id', $request->pipeline);
        }

        return $query->orderBy('leads.id', 'desc');
    }

    public function map($lead): array
    {
        return [
            $lead->name,
            $lead->email,
            $lead->phone,
            $lead->subject,
            $this->pipelines[$lead->pipeline_id] ?? '',
            $this->stages[$lead->stage_id] ?? '',
            $this->namesFor($lead->sources, $this->sources),
            $this->namesFor($lead->products, $this->products),
            $this->namesFor($lead->labels, $this->labels),
            $lead->date,
            $lead->gender,
            $lead->country,
            $lead->city,
            $lead->costing_type,
            $lead->member_id,
            $lead->campaign_name,
            $lead->cost,
            $lead->notes,
            $lead->users->pluck('name')->implode(','),
        ];
    }

    public function headings(): array
    {
        return [
            "Name",
            "Email",
            "Phone",
            "Subject",
            "Pipeline",
            "Lead Stage",
            "Lead Sources",
            "Products",
            "Labels",
            "Date",
            "Gender",
            "Country",
            "City",
            "Costing Type",
            "Member ID",
            "Campaign Name",
            "Cost",
            "Notes",
            "Users",
        ];
    }

    /**
     * Every name the export can print, fetched once. This used to be eight
     * queries per exported row.
     */
    protected function loadLookups()
    {
        $creatorId = \Auth::user()->creatorId();

        $this->pipelines = Pipeline::where('created_by', $creatorId)->pluck('name', 'id')->toArray();
        $this->stages = LeadStage::where('created_by', $creatorId)->pluck('name', 'id')->toArray();
        $this->sources = Source::where('created_by', $creatorId)->pluck('name', 'id')->toArray();
        $this->products = ProductService::where('created_by', $creatorId)->pluck('name', 'id')->toArray();
        $this->labels = Label::where('created_by', $creatorId)->pluck('name', 'id')->toArray();
    }

    /** Turn a comma separated id list into a comma separated name list. */
    protected function namesFor($idList, array $lookup)
    {
        if (empty($idList)) {
            return '';
        }

        $names = [];
        foreach (explode(',', $idList) as $id) {
            $id = trim($id);
            if ($id !== '' && isset($lookup[$id])) {
                $names[] = $lookup[$id];
            }
        }

        return implode(',', $names);
    }
}
