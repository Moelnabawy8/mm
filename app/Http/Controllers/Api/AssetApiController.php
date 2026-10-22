<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Asset;
use App\Models\Employee;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AssetApiController extends Controller
{
    use ApiResponser;

    public function getAssets()
    {
        $assets = Asset::where('created_by', Auth::user()->creatorId())
            ->with(['employees'])
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->success(['assets' => $assets], 'Assets retrieved.');
    }

    public function createAsset(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'purchase_date' => 'required|date|date_format:Y-m-d',
            'supported_date' => 'required|date|date_format:Y-m-d|after_or_equal:purchase_date',
            'amount' => 'required|numeric|min:0',
            'employee_id' => 'nullable|array',
            'employee_id.*' => 'exists:employees,id',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Check if employees belong to user's company
        if ($request->has('employee_id') && !empty($request->employee_id)) {
            $employees = Employee::whereIn('id', $request->employee_id)
                ->where('created_by', Auth::user()->creatorId())
                ->count();

            if ($employees != count($request->employee_id)) {
                return $this->error('One or more employees not found or access denied.', 404);
            }
        }

        $asset = new Asset();
        $asset->employee_id = !empty($request->employee_id) ? implode(',', $request->employee_id) : '';
        $asset->name = $request->name;
        $asset->purchase_date = $request->purchase_date;
        $asset->supported_date = $request->supported_date;
        $asset->amount = $request->amount;
        $asset->description = $request->description;
        $asset->created_by = Auth::user()->creatorId();
        $asset->save();

        return $this->success(['asset' => $asset], 'Asset created successfully.');
    }

    public function getAsset($id)
    {
        $asset = Asset::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->with(['employees'])
            ->first();

        if (!$asset) {
            return $this->error('Asset not found.', 404);
        }

        // Convert employee_id string to array for API response
        $asset->employee_ids = $asset->employee_id ? explode(',', $asset->employee_id) : [];

        return $this->success(['asset' => $asset], 'Asset detail.');
    }

    public function updateAsset(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'purchase_date' => 'required|date|date_format:Y-m-d',
            'supported_date' => 'required|date|date_format:Y-m-d|after_or_equal:purchase_date',
            'amount' => 'required|numeric|min:0',
            'employee_id' => 'nullable|array',
            'employee_id.*' => 'exists:employees,id',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $asset = Asset::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$asset) {
            return $this->error('Asset not found.', 404);
        }

        // Check if employees belong to user's company
        if ($request->has('employee_id') && !empty($request->employee_id)) {
            $employees = Employee::whereIn('id', $request->employee_id)
                ->where('created_by', Auth::user()->creatorId())
                ->count();

            if ($employees != count($request->employee_id)) {
                return $this->error('One or more employees not found or access denied.', 404);
            }
        }

        $asset->employee_id = !empty($request->employee_id) ? implode(',', $request->employee_id) : '';
        $asset->name = $request->name;
        $asset->purchase_date = $request->purchase_date;
        $asset->supported_date = $request->supported_date;
        $asset->amount = $request->amount;
        $asset->description = $request->description;
        $asset->save();

        return $this->success(['asset' => $asset], 'Asset updated successfully.');
    }

    public function deleteAsset($id)
    {
        $asset = Asset::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$asset) {
            return $this->error('Asset not found.', 404);
        }

        $asset->delete();

        return $this->success([], 'Asset deleted successfully.');
    }

    public function getEmployees()
    {
        $employees = Employee::where('created_by', Auth::user()->creatorId())
            ->get(['id', 'name']);

        return $this->success(['employees' => $employees], 'Employees retrieved.');
    }

    public function getAssetsByEmployee($employeeId)
    {
        // Check if employee belongs to user's company
        $employee = Employee::where('id', $employeeId)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$employee) {
            return $this->error('Employee not found or access denied.', 404);
        }

        $assets = Asset::where('created_by', Auth::user()->creatorId())
            ->whereRaw("FIND_IN_SET(?, employee_id)", [$employeeId])
            ->with(['employees'])
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->success(['assets' => $assets], 'Assets by employee retrieved.');
    }

    public function getAssetsByDateRange(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date|date_format:Y-m-d',
            'end_date' => 'required|date|date_format:Y-m-d|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $assets = Asset::where('created_by', Auth::user()->creatorId())
            ->whereBetween('purchase_date', [$request->start_date, $request->end_date])
            ->with(['employees'])
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->success(['assets' => $assets], 'Assets by date range retrieved.');
    }

    public function getAssetsByAmountRange(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'min_amount' => 'required|numeric|min:0',
            'max_amount' => 'required|numeric|min:0|gte:min_amount',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $assets = Asset::where('created_by', Auth::user()->creatorId())
            ->whereBetween('amount', [$request->min_amount, $request->max_amount])
            ->with(['employees'])
            ->orderBy('amount', 'desc')
            ->get();

        return $this->success(['assets' => $assets], 'Assets by amount range retrieved.');
    }

    public function getExpiringAssets(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'days' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $days = $request->days ?: 30;
        $expiryDate = date('Y-m-d', strtotime("+$days days"));

        $assets = Asset::where('created_by', Auth::user()->creatorId())
            ->where('supported_date', '<=', $expiryDate)
            ->where('supported_date', '>=', date('Y-m-d'))
            ->with(['employees'])
            ->orderBy('supported_date', 'asc')
            ->get();

        return $this->success(['assets' => $assets], 'Expiring assets retrieved.');
    }

    public function getExpiredAssets()
    {
        $assets = Asset::where('created_by', Auth::user()->creatorId())
            ->where('supported_date', '<', date('Y-m-d'))
            ->with(['employees'])
            ->orderBy('supported_date', 'desc')
            ->get();

        return $this->success(['assets' => $assets], 'Expired assets retrieved.');
    }

    public function getAssetStats()
    {
        $totalAssets = Asset::where('created_by', Auth::user()->creatorId())->count();
        $totalValue = Asset::where('created_by', Auth::user()->creatorId())->sum('amount');

        // Assets expiring in next 30 days
        $expiryDate = date('Y-m-d', strtotime('+30 days'));
        $expiringAssets = Asset::where('created_by', Auth::user()->creatorId())
            ->where('supported_date', '<=', $expiryDate)
            ->where('supported_date', '>=', date('Y-m-d'))
            ->count();

        // Expired assets
        $expiredAssets = Asset::where('created_by', Auth::user()->creatorId())
            ->where('supported_date', '<', date('Y-m-d'))
            ->count();

        // Group by employee
        $assetsByEmployee = Asset::where('created_by', Auth::user()->creatorId())
            ->get()
            ->filter(function ($asset) {
                return !empty($asset->employee_id);
            })
            ->map(function ($asset) {
                $employeeIds = explode(',', $asset->employee_id);
                return collect($employeeIds)->map(function ($employeeId) use ($asset) {
                    $employee = Employee::find($employeeId);
                    return [
                        'employee_id' => $employeeId,
                        'employee_name' => $employee ? $employee->name : 'Unknown',
                        'asset_name' => $asset->name,
                        'amount' => $asset->amount,
                    ];
                });
            })
            ->flatten(1)
            ->groupBy('employee_name')
            ->map(function ($group) {
                return [
                    'employee_name' => $group->first()['employee_name'],
                    'asset_count' => $group->count(),
                    'total_value' => $group->sum('amount'),
                ];
            });

        // Group by month
        $assetsByMonth = Asset::where('created_by', Auth::user()->creatorId())
            ->get()
            ->groupBy(function ($asset) {
                return date('Y-m', strtotime($asset->purchase_date));
            })
            ->map(function ($group) {
                return [
                    'month' => $group->first()->purchase_date,
                    'asset_count' => $group->count(),
                    'total_value' => $group->sum('amount'),
                ];
            });

        return $this->success([
            'total_assets' => $totalAssets,
            'total_value' => $totalValue,
            'expiring_assets' => $expiringAssets,
            'expired_assets' => $expiredAssets,
            'assets_by_employee' => $assetsByEmployee,
            'assets_by_month' => $assetsByMonth
        ], 'Asset statistics retrieved.');
    }

    public function getRecentAssets($limit = 10)
    {
        $assets = Asset::where('created_by', Auth::user()->creatorId())
            ->with(['employees'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $this->success(['assets' => $assets], 'Recent assets retrieved.');
    }

    public function searchAssets(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $query = $request->query;

        $assets = Asset::where('created_by', Auth::user()->creatorId())
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                    ->orWhere('description', 'LIKE', "%{$query}%")
                    ->orWhere('amount', 'LIKE', "%{$query}%");
            })
            ->with(['employees'])
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->success(['assets' => $assets], 'Search results retrieved.');
    }
}
