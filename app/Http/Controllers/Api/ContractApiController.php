<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Contract;
use App\Models\ContractType;
use App\Models\User;
use App\Models\Project;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ContractApiController extends Controller
{
    use ApiResponser;

    public function getContracts()
    {
        $user = Auth::user();
        if ($user->type == 'client') {
            $contracts = Contract::where('client_name', $user->id)->with(['types', 'project'])->get();
        } else {
            $contracts = Contract::where('created_by', $user->creatorId())->with(['clients', 'projects', 'types'])->get();
        }
        return $this->success(['contracts' => $contracts], 'Contracts retrieved.');
    }

    public function createContract(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'client_name' => 'required',
                'subject' => 'required',
                'type' => 'required',
                'value' => 'required',
                'start_date' => 'required',
                'end_date' => 'required',
            ]
        );

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $contract              = new Contract();
        $contract->client_name      = $request->client_name;
        $contract->subject     = $request->subject;
        $contract->project_id  = $request->project_id;
        $contract->type        = $request->type;
        $contract->value       = $request->value;
        $contract->start_date  = $request->start_date;
        $contract->end_date    = $request->end_date;
        $contract->description = $request->description;
        $contract->created_by  = Auth::user()->creatorId();
        $contract->save();

        return $this->success(['contract' => $contract], 'Contract created successfully.');
    }

    public function getContract($id)
    {
        $contract = Contract::where('id', $id)->with(['clients', 'projects', 'types'])->first();
        if (!$contract) {
            return $this->error('Contract not found.', 404);
        }

        $user = Auth::user();
        if ($user->type == 'client' && $contract->client_name != $user->id) {
            return $this->error('Permission denied.', 403);
        } elseif ($user->type != 'client' && $contract->created_by != $user->creatorId()) {
            return $this->error('Permission denied.', 403);
        }

        return $this->success(['contract' => $contract], 'Contract details.');
    }

    public function updateContract(Request $request, $id)
    {
        $contract = Contract::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$contract) {
            return $this->error('Contract not found or permission denied.', 404);
        }

        $validator = Validator::make(
            $request->all(),
            [
                'client_name' => 'required',
                'subject' => 'required',
                'type' => 'required',
                'value' => 'required',
                'start_date' => 'required',
                'end_date' => 'required',
            ]
        );

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $contract->client_name      = $request->client_name;
        $contract->subject     = $request->subject;
        $contract->project_id  = $request->project_id;
        $contract->type        = $request->type;
        $contract->value       = $request->value;
        $contract->start_date  = $request->start_date;
        $contract->end_date    = $request->end_date;
        $contract->description = $request->description;
        $contract->save();

        return $this->success(['contract' => $contract], 'Contract updated successfully.');
    }

    public function deleteContract($id)
    {
        $contract = Contract::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$contract) {
            return $this->error('Contract not found or permission denied.', 404);
        }
        $contract->delete();
        return $this->success([], 'Contract deleted successfully.');
    }

    public function signContract(Request $request, $id)
    {
        $contract = Contract::find($id);
        if (!$contract) {
            return $this->error('Contract not found.', 404);
        }

        $user = Auth::user();
        if ($user->type == 'company' && $contract->created_by == $user->creatorId()) {
            $contract->company_signature = $request->signature;
        } elseif ($user->type == 'client' && $contract->client_name == $user->id) {
            $contract->client_signature = $request->signature;
        } else {
            return $this->error('Permission denied.', 403);
        }

        $contract->save();

        return $this->success(['contract' => $contract], 'Contract signed successfully.');
    }
}
