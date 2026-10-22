<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Training;
use App\Models\TrainingType;
use App\Models\Trainer;
use App\Models\Branch;
use App\Models\Employee;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class TrainingApiController extends Controller
{
    use ApiResponser;

    public function getTrainings()
    {
        $trainings = Training::where('created_by', Auth::user()->creatorId())->with(['branches', 'types', 'trainer', 'employee'])->get();
        return $this->success(['trainings' => $trainings], 'Trainings retrieved.');
    }

    public function createTraining(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'branch' => 'required',
            'training_type' => 'required',
            'training_cost' => 'required',
            'employee' => 'required',
            'start_date' => 'required',
            'end_date' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $training = new Training();
        $training->branch = $request->branch;
        $training->trainer_option = $request->trainer_option;
        $training->training_type = $request->training_type;
        $training->trainer = $request->trainer;
        $training->training_cost = $request->training_cost;
        $training->employee = $request->employee;
        $training->start_date = $request->start_date;
        $training->end_date = $request->end_date;
        $training->description = $request->description;
        $training->created_by = Auth::user()->creatorId();
        $training->save();

        return $this->success(['training' => $training], 'Training created successfully.');
    }

    public function getTraining($id)
    {
        $training = Training::where('id', $id)->where('created_by', Auth::user()->creatorId())->with(['branches', 'types', 'trainer', 'employee'])->first();
        if (!$training) {
            return $this->error('Training not found.', 404);
        }
        return $this->success(['training' => $training], 'Training detail.');
    }

    public function updateTraining(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'branch' => 'required',
            'training_type' => 'required',
            'training_cost' => 'required',
            'employee' => 'required',
            'start_date' => 'required',
            'end_date' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $training = Training::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$training) {
            return $this->error('Training not found.', 404);
        }

        $training->branch = $request->branch;
        $training->trainer_option = $request->trainer_option;
        $training->training_type = $request->training_type;
        $training->trainer = $request->trainer;
        $training->training_cost = $request->training_cost;
        $training->employee = $request->employee;
        $training->start_date = $request->start_date;
        $training->end_date = $request->end_date;
        $training->description = $request->description;
        $training->save();

        return $this->success(['training' => $training], 'Training updated successfully.');
    }

    public function deleteTraining($id)
    {
        $training = Training::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$training) {
            return $this->error('Training not found.', 404);
        }
        $training->delete();
        return $this->success([], 'Training deleted successfully.');
    }

    public function getTrainingTypes()
    {
        $types = TrainingType::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['types' => $types], 'Training types retrieved.');
    }

    public function getTrainers()
    {
        $trainers = Trainer::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['trainers' => $trainers], 'Trainers retrieved.');
    }

    public function getBranches()
    {
        $branches = Branch::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['branches' => $branches], 'Branches retrieved.');
    }

    public function getEmployees()
    {
        $employees = Employee::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['employees' => $employees], 'Employees retrieved.');
    }

    public function updateTrainingStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'performance' => 'required',
            'status' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $training = Training::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$training) {
            return $this->error('Training not found.', 404);
        }

        $training->performance = $request->performance;
        $training->status = $request->status;
        $training->remarks = $request->remarks;
        $training->save();

        return $this->success(['training' => $training], 'Training status updated successfully.');
    }
}
