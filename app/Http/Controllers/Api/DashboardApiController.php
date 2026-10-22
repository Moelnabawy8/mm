<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use App\Models\Revenue;
use App\Models\Payment;
use App\Models\Invoice;
use App\Models\Bill;
use App\Models\Goal;
use App\Models\BankAccount;
use App\Models\ProductServiceCategory;
use App\Models\ProductServiceUnit;
use App\Models\Tax;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\Expense;
use App\Models\Timesheet;
use App\Models\Employee;
use App\Models\Announcement;
use App\Models\Meeting;
use App\Models\Event;
use App\Models\AttendanceEmployee;
use App\Models\Lead;
use App\Models\Deal;
use App\Models\Contract;
use App\Models\Pos;
use App\Models\Purchase;
use App\Models\Utility;

class DashboardApiController extends Controller
{
    use ApiResponser;

    public function dashboard()
    {
        if (Auth::user()->type == 'company') {
            return $this->accountDashboard();
        } elseif (Auth::user()->type == 'Employee') {
            return $this->hrmDashboard();
        } elseif (Auth::user()->type == 'client') {
            return $this->projectDashboard();
        } else {
            return $this->accountDashboard();
        }
    }

    public function dashboardStats()
    {
        // Generic stats could go here
        return $this->success([], 'Stats endpoint.');
    }

    public function accountDashboard()
    {
        if (Auth::user()->can('show account dashboard')) {
            $data['latestIncome'] = Revenue::with(['customer'])->where('created_by', '=', Auth::user()->creatorId())->orderBy('id', 'desc')->limit(5)->get();
            $data['latestExpense'] = Payment::with(['vender'])->where('created_by', '=', Auth::user()->creatorId())->orderBy('id', 'desc')->limit(5)->get();

            $data['currentYear'] = date('Y');
            $data['currentMonth'] = date('M');

            $data['constant']['taxes'] = Tax::where('created_by', Auth::user()->creatorId())->count();
            $data['constant']['category'] = ProductServiceCategory::where('created_by', Auth::user()->creatorId())->count();
            $data['constant']['units'] = ProductServiceUnit::where('created_by', Auth::user()->creatorId())->count();
            $data['constant']['bankAccount'] = BankAccount::where('created_by', Auth::user()->creatorId())->count();

            $data['bankAccountDetail'] = BankAccount::where('created_by', '=', Auth::user()->creatorId())->limit(5)->get();

            $data['recentInvoice'] = Invoice::join('customers', 'invoices.customer_id', '=', 'customers.id')
                ->where('invoices.created_by', '=', Auth::user()->creatorId())
                ->orderBy('invoices.id', 'desc')
                ->limit(5)
                ->select('invoices.*', 'customers.name as customer_name')
                ->get();

            $data['weeklyInvoice'] = Auth::user()->weeklyInvoice();
            $data['monthlyInvoice'] = Auth::user()->monthlyInvoice();

            $data['recentBill'] = Bill::join('venders', 'bills.vender_id', '=', 'venders.id')
                ->where('bills.created_by', '=', Auth::user()->creatorId())
                ->orderBy('bills.id', 'desc')
                ->limit(5)
                ->select('bills.*', 'venders.name as vender_name')
                ->get();

            $data['weeklyBill'] = Auth::user()->weeklyBill();
            $data['monthlyBill'] = Auth::user()->monthlyBill();

            $data['goals'] = Goal::where('created_by', '=', Auth::user()->creatorId())->where('is_display', 1)->get();

            return $this->success($data, 'Account Dashboard data.');
        }
        return $this->error('Permission denied.', 403);
    }

    public function projectDashboard()
    {
        if (Auth::user()->can('show project dashboard')) {
            $user = Auth::user();
            $home_data = [];

            $user_projects = $user->projects()->pluck('project_id')->toArray();
            $project_tasks = ProjectTask::whereIn('project_id', $user_projects)->get();
            $project_expense = Expense::whereIn('project_id', $user_projects)->get();

            // Total Projects
            $complete_project = $user->projects()->where('status', 'LIKE', 'complete')->count();
            $home_data['total_project'] = [
                'total' => count($user_projects),
                'percentage' => Utility::getPercentage($complete_project, count($user_projects)),
            ];

            // Total Tasks
            if (Auth::user()->type == 'company') {
                $complete_task = ProjectTask::where('is_complete', '=', 1)->whereIn('project_id', $user_projects)->count();
            } else {
                $complete_task = ProjectTask::where('is_complete', '=', 1)->whereRaw("find_in_set('" . $user->id . "',assign_to)")->whereIn('project_id', $user_projects)->count();
            }
            $home_data['total_task'] = [
                'total' => $project_tasks->count(),
                'percentage' => Utility::getPercentage($complete_task, $project_tasks->count()),
            ];

            // Total Expense
            $total_expense = 0;
            $total_project_amount = 0;
            foreach ($user->projects as $pr) {
                $total_project_amount += $pr->budget;
            }
            foreach ($project_expense as $expense) {
                $total_expense += $expense->amount;
            }
            $home_data['total_expense'] = [
                'total' => $project_expense->count(),
                'percentage' => Utility::getPercentage($total_expense, $total_project_amount),
            ];

            // Total Users
            $home_data['total_user'] = Auth::user()->contacts->count();

            // Project Status
            $total_project = count($user_projects);
            $project_status = [];
            foreach (Project::$project_status as $k => $v) {
                $project_status[$k]['total'] = $user->projects->where('status', 'LIKE', $k)->count();
                $project_status[$k]['percentage'] = Utility::getPercentage($project_status[$k]['total'], $total_project);
            }
            $home_data['project_status'] = $project_status;

            // Top Due Project
            $home_data['due_project'] = $user->projects()->orderBy('end_date', 'DESC')->limit(5)->get();

            // Top Due Tasks
            $home_data['due_tasks'] = ProjectTask::where('is_complete', '=', 0)->whereIn('project_id', $user_projects)->orderBy('end_date', 'DESC')->limit(5)->get();

            return $this->success($home_data, 'Project Dashboard data.');
        }
        return $this->error('Permission denied.', 403);
    }

    public function hrmDashboard()
    {
        if (Auth::user()->can('show hrm dashboard')) {
            $user = Auth::user();

            // Assuming company/HR view logic primarily
            $announcements = Announcement::where('end_date', '>=', date('Y-m-d'))->orderBy('announcements.id', 'desc')->take(5)->where('created_by', '=', Auth::user()->creatorId())->get();

            $countTrainer = \App\Models\Trainer::where('created_by', '=', Auth::user()->creatorId())->count();
            $onGoingTraining = \App\Models\Training::where('status', '=', 1)->where('created_by', '=', Auth::user()->creatorId())->count();
            $doneTraining = \App\Models\Training::where('status', '=', 2)->where('created_by', '=', Auth::user()->creatorId())->count();

            $currentDate = date('Y-m-d');
            $notClockIn = AttendanceEmployee::where('date', '=', $currentDate)->get()->pluck('employee_id');
            $notClockIns = Employee::where('created_by', '=', Auth::user()->creatorId())->whereNotIn('id', $notClockIn)->get();

            $activeJob = \App\Models\Job::where('status', 'active')->where('created_by', '=', Auth::user()->creatorId())->count();
            $inActiveJOb = \App\Models\Job::where('status', 'in_active')->where('created_by', '=', Auth::user()->creatorId())->count();

            $meetings = Meeting::where('created_by', '=', Auth::user()->creatorId())->limit(5)->get();
            $events = Event::where('created_by', '=', Auth::user()->creatorId())->limit(5)->get();

            $data = [
                'announcements' => $announcements,
                'trainers_count' => $countTrainer,
                'ongoing_training_count' => $onGoingTraining,
                'done_training_count' => $doneTraining,
                'active_jobs' => $activeJob,
                'inactive_jobs' => $inActiveJOb,
                'meetings' => $meetings,
                'events' => $events,
                'not_clock_in_employees' => $notClockIns
            ];

            return $this->success($data, 'HRM Dashboard data.');
        }
        return $this->error('Permission denied.', 403);
    }

    public function crmDashboard()
    {
        if (Auth::user()->can('show crm dashboard')) {
            $crm_data = [];

            $leads = Lead::where('created_by', Auth::user()->creatorId())->get();
            $deals = Deal::where('created_by', Auth::user()->creatorId())->get();

            $crm_data['total_leads'] = count($leads);
            $crm_data['total_deals'] = count($deals);
            $crm_data['total_contracts'] = Contract::where('created_by', Auth::user()->creatorId())->count();

            $crm_data['latestContract'] = Contract::where('created_by', '=', Auth::user()->creatorId())->orderBy('id', 'desc')->limit(5)->with(['clients', 'projects', 'types'])->get();

            return $this->success($crm_data, 'CRM Dashboard data.');
        }
        return $this->error('Permission denied.', 403);
    }

    public function posDashboard()
    {
        if (Auth::user()->can('show pos dashboard')) {
            $pos_data = [];
            $pos_data['monthlyPosAmount'] = Pos::totalPosAmount(true);
            $pos_data['totalPosAmount'] = Pos::totalPosAmount();
            $pos_data['monthlyPurchaseAmount'] = Purchase::totalPurchaseAmount(true);
            $pos_data['totalPurchaseAmount'] = Purchase::totalPurchaseAmount();

            return $this->success($pos_data, 'POS Dashboard data.');
        }
        return $this->error('Permission denied.', 403);
    }
}
