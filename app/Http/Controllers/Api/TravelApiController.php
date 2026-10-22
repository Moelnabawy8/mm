<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Travel;
use App\Models\Employee;
use App\Models\Utility;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class TravelApiController extends Controller
{
    use ApiResponser;

    public function getTravels()
    {
        if (Auth::user()->type == 'Employee') {
            $emp = Employee::where('user_id', '=', Auth::user()->id)->first();
            $travels = Travel::where('created_by', '=', Auth::user()->creatorId())->where('employee_id', '=', $emp->id)->with('employee')->get();
        } else {
            $travels = Travel::where('created_by', '=', Auth::user()->creatorId())->with('employee')->get();
        }
        return $this->success(['travels' => $travels], 'Travels retrieved.');
    }

    public function createTravel(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required',
            'start_date' => 'required',
            'end_date' => 'required',
            'purpose_of_visit' => 'required',
            'place_of_visit' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $travel = new Travel();
        $travel->employee_id = $request->employee_id;
        $travel->start_date = $request->start_date;
        $travel->end_date = $request->end_date;
        $travel->purpose_of_visit = $request->purpose_of_visit;
        $travel->place_of_visit = $request->place_of_visit;
        $travel->description = $request->description;
        $travel->created_by = Auth::user()->creatorId();
        $travel->save();

        // Send Email
        $settings = Utility::settings();
        if ($settings['trip_sent'] == 1) {
            $employee = Employee::find($travel->employee_id);

            $tripArr = [
                'trip_name' => $employee->name,
                'purpose_of_visit' => $travel->purpose_of_visit,
                'start_date' => $travel->start_date,
                'end_date' => $travel->end_date,
                'place_of_visit' => $travel->place_of_visit,
                'trip_description' => $travel->description,
            ];

            $resp = Utility::sendEmailTemplate('trip_sent', [$employee->id => $employee->email], $tripArr);
        }

        return $this->success(['travel' => $travel], 'Travel created successfully.');
    }

    public function getTravel($id)
    {
        $travel = Travel::where('id', $id)->where('created_by', Auth::user()->creatorId())->with('employee')->first();
        if (!$travel) {
            return $this->error('Travel not found.', 404);
        }
        return $this->success(['travel' => $travel], 'Travel detail.');
    }

    public function updateTravel(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required',
            'start_date' => 'required',
            'end_date' => 'required',
            'purpose_of_visit' => 'required',
            'place_of_visit' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $travel = Travel::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$travel) {
            return $this->error('Travel not found.', 404);
        }

        $travel->employee_id = $request->employee_id;
        $travel->start_date = $request->start_date;
        $travel->end_date = $request->end_date;
        $travel->purpose_of_visit = $request->purpose_of_visit;
        $travel->place_of_visit = $request->place_of_visit;
        $travel->description = $request->description;
        $travel->save();

        return $this->success(['travel' => $travel], 'Travel updated successfully.');
    }

    public function deleteTravel($id)
    {
        $travel = Travel::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$travel) {
            return $this->error('Travel not found.', 404);
        }
        $travel->delete();
        return $this->success([], 'Travel deleted successfully.');
    }

    public function getEmployees()
    {
        $employees = Employee::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['employees' => $employees], 'Employees retrieved.');
    }
}
