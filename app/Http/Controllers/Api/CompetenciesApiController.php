<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Competencies;
use App\Models\Performance_Type;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CompetenciesApiController extends Controller
{
    use ApiResponser;

    public function getCompetencies()
    {
        $competencies = Competencies::where('created_by', Auth::user()->creatorId())
            ->with(['types'])
            ->get();
        return $this->success(['competencies' => $competencies], 'Competencies retrieved.');
    }

    public function createCompetency(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:190',
            'type' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $competency = new Competencies();
        $competency->name = $request->name;
        $competency->type = $request->type;
        $competency->created_by = Auth::user()->creatorId();
        $competency->save();

        return $this->success(['competency' => $competency], 'Competency created successfully.');
    }

    public function getCompetency($id)
    {
        $competency = Competencies::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->with(['types'])
            ->first();

        if (!$competency) {
            return $this->error('Competency not found.', 404);
        }

        return $this->success(['competency' => $competency], 'Competency detail.');
    }

    public function updateCompetency(Request $request, $id)
    {
        $competency = Competencies::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$competency) {
            return $this->error('Competency not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:190',
            'type' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $competency->name = $request->name;
        $competency->type = $request->type;
        $competency->save();

        return $this->success(['competency' => $competency], 'Competency updated successfully.');
    }

    public function deleteCompetency($id)
    {
        $competency = Competencies::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$competency) {
            return $this->error('Competency not found.', 404);
        }

        $competency->delete();

        return $this->success([], 'Competency deleted successfully.');
    }

    public function getCompetencyTypes()
    {
        $types = Performance_Type::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['types' => $types], 'Competency types retrieved.');
    }
}
