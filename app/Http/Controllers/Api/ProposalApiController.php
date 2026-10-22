<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Proposal;
use App\Models\ProposalProduct;
use App\Models\Customer;
use App\Models\CustomField;
use App\Models\ProductService;
use App\Models\Utility;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Crypt;

class ProposalApiController extends Controller
{
    use ApiResponser;

    public function getProposals()
    {
        $proposals = Proposal::where('created_by', Auth::user()->creatorId())->with(['customer', 'category'])->get();
        return $this->success(['proposals' => $proposals], 'Proposals retrieved.');
    }

    public function createProposal(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'customer_id' => 'required',
                'issue_date' => 'required',
                'category_id' => 'required',
                'items' => 'required|array',
            ]
        );

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $proposal                 = new Proposal();
        $proposal->proposal_id    = $this->proposalNumber();
        $proposal->customer_id    = $request->customer_id;
        $proposal->status         = 0;
        $proposal->issue_date     = $request->issue_date;
        $proposal->category_id    = $request->category_id;
        $proposal->created_by     = Auth::user()->creatorId();
        $proposal->save();

        if ($request->customField) {
            CustomField::saveData($proposal, $request->customField);
        }

        $products = $request->items;

        for ($i = 0; $i < count($products); $i++) {
            $proposalProduct              = new ProposalProduct();
            $proposalProduct->proposal_id = $proposal->id;
            $proposalProduct->product_id  = $products[$i]['product_id'];
            $proposalProduct->quantity    = $products[$i]['quantity'];
            $proposalProduct->tax         = $products[$i]['tax'] ?? 0;
            $proposalProduct->discount    = $products[$i]['discount'] ?? 0;
            $proposalProduct->price       = $products[$i]['price'];
            $proposalProduct->description = $products[$i]['description'] ?? '';
            $proposalProduct->save();
        }

        return $this->success(['proposal' => $proposal->load('items')], 'Proposal created successfully.');
    }

    public function getProposal($id)
    {
        $proposal = Proposal::where('id', $id)->where('created_by', Auth::user()->creatorId())->with(['customer', 'items', 'category'])->first();
        if (!$proposal) {
            return $this->error('Proposal not found.', 404);
        }
        $proposal->custom_fields = CustomField::getData($proposal, 'proposal');
        return $this->success(['proposal' => $proposal], 'Proposal details.');
    }

    public function updateProposal(Request $request, $id)
    {
        $proposal = Proposal::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$proposal) {
            return $this->error('Proposal not found.', 404);
        }

        $validator = Validator::make(
            $request->all(),
            [
                'customer_id' => 'required',
                'issue_date' => 'required',
                'category_id' => 'required',
                'items' => 'required|array',
            ]
        );

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $proposal->customer_id    = $request->customer_id;
        $proposal->issue_date     = $request->issue_date;
        $proposal->category_id    = $request->category_id;
        $proposal->save();

        if ($request->customField) {
            CustomField::saveData($proposal, $request->customField);
        }

        $products = $request->items;
        // Basic update strategy: delete old items and recreate new ones for simplicity in API, 
        // or update existing if IDs provided. For simplicity and consistency with create, we'll iterate.
        // But to avoid duplicates if not careful, better to handle IDs. 
        // Let's assume replacement for now or smart update.

        // Deleting existing products to replace with new set as a simple sync strategy
        // ProposalProduct::where('proposal_id', $proposal->id)->delete();

        // Better: Update if ID exists, create if not. 
        foreach ($products as $productData) {
            $proposalProduct = null;
            if (isset($productData['id'])) {
                $proposalProduct = ProposalProduct::find($productData['id']);
            }

            if (!$proposalProduct) {
                $proposalProduct = new ProposalProduct();
                $proposalProduct->proposal_id = $proposal->id;
            }

            $proposalProduct->product_id  = $productData['product_id'];
            $proposalProduct->quantity    = $productData['quantity'];
            $proposalProduct->tax         = $productData['tax'] ?? 0;
            $proposalProduct->discount    = $productData['discount'] ?? 0;
            $proposalProduct->price       = $productData['price'];
            $proposalProduct->description = $productData['description'] ?? '';
            $proposalProduct->save();
        }

        return $this->success(['proposal' => $proposal->fresh()->load('items')], 'Proposal updated successfully.');
    }

    public function deleteProposal($id)
    {
        $proposal = Proposal::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$proposal) {
            return $this->error('Proposal not found.', 404);
        }

        ProposalProduct::where('proposal_id', '=', $proposal->id)->delete();
        $proposal->delete();

        return $this->success([], 'Proposal deleted successfully.');
    }

    public function downloadProposalPdf($id)
    {
        $proposal = Proposal::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$proposal) {
            return $this->error('Proposal not found.', 404);
        }

        $proposalId = Crypt::encrypt($proposal->id);
        $url = route('proposal.pdf', $proposalId);

        return $this->success(['url' => $url], 'Proposal PDF URL.');
    }

    public function sendProposal($id)
    {
        $proposal = Proposal::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$proposal) {
            return $this->error('Proposal not found.', 404);
        }

        $customer = Customer::find($proposal->customer_id);
        if (!$customer) {
            return $this->error('Customer not found.', 404);
        }

        $proposal->send_date = date('Y-m-d');
        $proposal->status    = 1;
        $proposal->save();

        // Email sending logic
        // Utility::sendEmailTemplate('proposal_sent', ...)

        return $this->success([], 'Proposal sent successfully.');
    }

    function proposalNumber()
    {
        $latest = Proposal::where('created_by', '=', Auth::user()->creatorId())->latest('proposal_id')->first();
        if (!$latest) {
            return 1;
        }
        return $latest->proposal_id + 1;
    }
}
