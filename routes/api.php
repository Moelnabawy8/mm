<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\EmployeeApiController;
use App\Http\Controllers\Api\AttendanceApiController;
use App\Http\Controllers\Api\LeaveApiController;
use App\Http\Controllers\Api\ProjectApiController;
use App\Http\Controllers\Api\TaskApiController;
use App\Http\Controllers\Api\CustomerApiController;
use App\Http\Controllers\Api\VendorApiController;
use App\Http\Controllers\Api\InvoiceApiController;
use App\Http\Controllers\Api\BillApiController;
use App\Http\Controllers\Api\ProductApiController;
use App\Http\Controllers\Api\DepartmentApiController;
use App\Http\Controllers\Api\DesignationApiController;
use App\Http\Controllers\Api\PayrollApiController;
use App\Http\Controllers\Api\LeadApiController;
use App\Http\Controllers\Api\DealApiController;
use App\Http\Controllers\Api\ReportApiController;
use App\Http\Controllers\Api\AssetApiController;
use App\Http\Controllers\Api\AnnouncementApiController;
use App\Http\Controllers\Api\MeetingApiController;
use App\Http\Controllers\Api\GoogleMeetApiController;
use App\Http\Controllers\Api\EventApiController;
use App\Http\Controllers\Api\TicketApiController;
use App\Http\Controllers\Api\ContractApiController;
use App\Http\Controllers\Api\TrainingApiController;
use App\Http\Controllers\Api\AwardApiController;
use App\Http\Controllers\Api\ComplaintApiController;
use App\Http\Controllers\Api\WarningApiController;
use App\Http\Controllers\Api\TerminationApiController;
use App\Http\Controllers\Api\PromotionApiController;
use App\Http\Controllers\Api\TransferApiController;
use App\Http\Controllers\Api\ResignationApiController;
use App\Http\Controllers\Api\TravelApiController;
use App\Http\Controllers\Api\TimesheetApiController;
use App\Http\Controllers\Api\IndicatorApiController;
use App\Http\Controllers\Api\AppraisalApiController;
use App\Http\Controllers\Api\GoalApiController;
use App\Http\Controllers\Api\CompetenciesApiController;
use App\Http\Controllers\Api\ExpenseApiController;
use App\Http\Controllers\Api\BudgetApiController;
use App\Http\Controllers\Api\RevenueApiController;
use App\Http\Controllers\Api\TransactionApiController;
use App\Http\Controllers\Api\BankAccountApiController;
use App\Http\Controllers\Api\ChartOfAccountApiController;
use App\Http\Controllers\Api\JournalEntryApiController;
use App\Http\Controllers\Api\PurchaseApiController;
use App\Http\Controllers\Api\PosApiController;
use App\Http\Controllers\Api\WarehouseApiController;
use App\Http\Controllers\Api\ProposalApiController;
use App\Http\Controllers\Api\JobApiController;
use App\Http\Controllers\Api\InterviewApiController;
use App\Http\Controllers\Api\HolidayApiController;
use App\Http\Controllers\Api\BranchApiController;
use App\Http\Controllers\Api\UserApiController;
use App\Http\Controllers\Api\RoleApiController;
use App\Http\Controllers\Api\SettingsApiController;
use App\Http\Controllers\Api\DashboardApiController;
use App\Http\Controllers\Api\MilestoneApiController;
use App\Http\Controllers\Api\BugApiController;
use App\Http\Controllers\Api\JobApplicationApiController;
use App\Http\Controllers\Api\PolicyApiController;
use App\Http\Controllers\Api\TimeTrackerApiController;
use App\Http\Controllers\Api\NotificationApiController;
use App\Http\Controllers\Api\SearchApiController;
use App\Http\Controllers\Api\SalesMeetingApiController;
use App\Http\Controllers\Api\DealPurchaseApiController;
use App\Http\Controllers\Api\InstallmentApiController;
use App\Http\Controllers\Api\PayrollSetupApiController;
use App\Http\Controllers\Api\EmployeeSalaryApiController;
use App\Http\Controllers\Api\SupportRequestApiController;
use App\Http\Controllers\Api\DocumentApiController;
use App\Http\Controllers\DailyReportController;

/* |-------------------------------------------------------------------------- | API Routes |-------------------------------------------------------------------------- | | Here is where you can register API routes for your application. These | routes are loaded by the RouteServiceProvider within a group which | is assigned the "api" middleware group. Enjoy building your API! | | Version: 1.0 | Base URL: /api/v1 | Authentication: Laravel Sanctum (Bearer Token) | */

// Public Routes (No Authentication Required)
Route::prefix('v1')->group(function () {

    // ============================================
    // Authentication Routes
    // ============================================
    Route::post('login', [AuthApiController::class , 'login']);
    Route::post('register', [AuthApiController::class , 'register']);
    Route::post('forgot-password', [AuthApiController::class , 'forgotPassword']);
    Route::post('reset-password', [AuthApiController::class , 'resetPassword']);
    Route::post('refresh-token', [AuthApiController::class , 'refreshToken']);
    Route::post('expire-token', [AuthApiController::class , 'expireToken']); // For testing purposes only

    // Public Job Listings
    Route::get('jobs/public', [AuthApiController::class , 'publicJobs']);
    Route::get('jobs/{id}/public', [AuthApiController::class , 'publicJobDetail']);
    Route::post('jobs/{id}/apply', [AuthApiController::class , 'applyJob']);

    // Public File Serving Routes (no auth required)
    Route::get('files/tasks/{fileId}', [TaskApiController::class , 'serveTaskFile']);
    Route::get('files/projects/{fileId}', [ProjectApiController::class , 'serveProjectFile']);
});

// Protected Routes (Authentication Required)
Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {

    // ============================================
    // Authentication & Profile
    // ============================================
    Route::post('logout', [AuthApiController::class , 'logout']);
    Route::get('profile', [AuthApiController::class , 'profile']);
    Route::put('profile', [AuthApiController::class , 'updateProfile']);
    Route::post('change-password', [AuthApiController::class , 'changePassword']);
    Route::post('upload-avatar', [AuthApiController::class , 'uploadAvatar']);

    // ============================================
    // Dashboard
    // ============================================
    Route::get('dashboard', [DashboardApiController::class , 'dashboard']);
    Route::get('dashboard/stats', [DashboardApiController::class , 'dashboardStats']);
    Route::get('dashboard/hrm', [DashboardApiController::class , 'hrmDashboard']);
    Route::get('dashboard/crm', [DashboardApiController::class , 'crmDashboard']);
    Route::get('dashboard/project', [DashboardApiController::class , 'projectDashboard']);
    Route::get('dashboard/account', [DashboardApiController::class , 'accountDashboard']);
    Route::get('dashboard/pos', [DashboardApiController::class , 'posDashboard']);

    // ============================================
    // HRM Module - Employees
    // ============================================
    Route::prefix('employees')->group(function () {
            Route::get('/', [EmployeeApiController::class , 'index']);
            Route::post('/', [EmployeeApiController::class , 'store']);
            Route::get('/{id}/documents', [EmployeeApiController::class , 'getEmployeeDocuments']);
            Route::post('/{id}/documents', [EmployeeApiController::class , 'uploadEmployeeDocument']);
            Route::get('/{id}/salary', [EmployeeApiController::class , 'getEmployeeSalary']);
            Route::post('/{id}/salary', [EmployeeApiController::class , 'setEmployeeSalary']);
            Route::get('/{id}/attendance', [AttendanceApiController::class , 'getEmployeeAttendanceList']);
            Route::get('/{id}/leaves', [EmployeeApiController::class , 'getEmployeeLeaves']);
            Route::get('/{id}/payslips', [EmployeeApiController::class , 'getEmployeePayslips']);
            Route::get('/{id}/permissions', [EmployeeApiController::class , 'getEmployeePermissions']);
            Route::get('/{id}/statistics', [EmployeeApiController::class , 'getEmployeeStatistics']);
            Route::get('/{id}', [EmployeeApiController::class , 'show']);
            Route::put('/{id}', [EmployeeApiController::class , 'update']);
            Route::delete('/{id}', [EmployeeApiController::class , 'destroy']);
            Route::get('/{id}/tasks', [TaskApiController::class, 'getTasksByUser']);
        }
        );

        // ============================================
        // HRM Module - Attendance
        // ============================================
        Route::prefix('attendance')->group(function () {
            Route::get('/', [AttendanceApiController::class , 'getAttendance']);
            Route::post('/', [AttendanceApiController::class , 'markAttendance']);
            Route::post('check-in', [AttendanceApiController::class , 'checkIn']);
            Route::post('check-out', [AttendanceApiController::class , 'checkOut']);
            Route::get('today', [AttendanceApiController::class , 'getTodayAttendance']);
            Route::post('bulk', [AttendanceApiController::class , 'bulkAttendance']);
            Route::post('import', [AttendanceApiController::class , 'importAttendance']);
            Route::get('export', [AttendanceApiController::class , 'exportAttendance']);
            Route::get('/{id}', [AttendanceApiController::class , 'getAttendanceDetail']);
            Route::put('/{id}', [AttendanceApiController::class , 'updateAttendance']);
            Route::delete('/{id}', [AttendanceApiController::class , 'deleteAttendance']);
            Route::get('employee/{id}', [AttendanceApiController::class , 'getEmployeeAttendanceList']);
        }
        );

        // ============================================
        // HRM Module - Leaves
        // ============================================
        Route::prefix('leaves')->group(function () {
            Route::get('/', [LeaveApiController::class , 'getLeaves']);
            Route::post('/', [LeaveApiController::class , 'createLeave']);
            Route::get('types', [LeaveApiController::class , 'getLeaveTypes']);
            Route::post('types', [LeaveApiController::class , 'createLeaveType']);
            Route::get('/{id}', [LeaveApiController::class , 'getLeave']);
            Route::put('/{id}', [LeaveApiController::class , 'updateLeave']);
            Route::delete('/{id}', [LeaveApiController::class , 'deleteLeave']);
            Route::post('/{id}/approve', [LeaveApiController::class , 'approveLeave']);
            Route::post('/{id}/reject', [LeaveApiController::class , 'rejectLeave']);
        }
        );

        // ============================================
        // HRM Module - Payroll
        // ============================================
        Route::prefix('payroll')->group(function () {
            Route::get('payslips',              [PayrollApiController::class, 'getPayslips']);
            Route::post('payslips',             [PayrollApiController::class, 'createPayslip']);
            Route::post('payslips/bulk-pay',    [PayrollApiController::class, 'bulkPay']);
            Route::get('payslips/{id}',         [PayrollApiController::class, 'getPayslip']);
            Route::put('payslips/{id}',         [PayrollApiController::class, 'updatePayslip']);
            Route::delete('payslips/{id}',      [PayrollApiController::class, 'deletePayslip']);
            Route::post('payslips/{id}/pay',    [PayrollApiController::class, 'paySalary']);
            Route::get('payslips/{id}/pdf',     [PayrollApiController::class, 'downloadPayslipPdf']);
            Route::post('payslips/{id}/send',   [PayrollApiController::class, 'sendPayslip']);
            Route::get('allowances', [PayrollApiController::class , 'getAllowances']);
            Route::get('deductions', [PayrollApiController::class , 'getDeductions']);
            Route::get('commissions', [PayrollApiController::class , 'getCommissions']);
            Route::get('loans', [PayrollApiController::class , 'getLoans']);
            Route::get('overtime', [PayrollApiController::class , 'getOvertime']);

            // Setup - Allowance Options
            Route::get('setup/allowance-options',          [PayrollSetupApiController::class, 'getAllowanceOptions']);
            Route::post('setup/allowance-options',         [PayrollSetupApiController::class, 'createAllowanceOption']);
            Route::get('setup/allowance-options/{id}',     [PayrollSetupApiController::class, 'getAllowanceOption']);
            Route::put('setup/allowance-options/{id}',     [PayrollSetupApiController::class, 'updateAllowanceOption']);
            Route::delete('setup/allowance-options/{id}',  [PayrollSetupApiController::class, 'deleteAllowanceOption']);

            // Setup - Deduction Options
            Route::get('setup/deduction-options',          [PayrollSetupApiController::class, 'getDeductionOptions']);
            Route::post('setup/deduction-options',         [PayrollSetupApiController::class, 'createDeductionOption']);
            Route::get('setup/deduction-options/{id}',     [PayrollSetupApiController::class, 'getDeductionOption']);
            Route::put('setup/deduction-options/{id}',     [PayrollSetupApiController::class, 'updateDeductionOption']);
            Route::delete('setup/deduction-options/{id}',  [PayrollSetupApiController::class, 'deleteDeductionOption']);

            // Setup - Loan Options
            Route::get('setup/loan-options',               [PayrollSetupApiController::class, 'getLoanOptions']);
            Route::post('setup/loan-options',              [PayrollSetupApiController::class, 'createLoanOption']);
            Route::get('setup/loan-options/{id}',          [PayrollSetupApiController::class, 'getLoanOption']);
            Route::put('setup/loan-options/{id}',          [PayrollSetupApiController::class, 'updateLoanOption']);
            Route::delete('setup/loan-options/{id}',       [PayrollSetupApiController::class, 'deleteLoanOption']);

            // Setup - Payslip Types
            Route::get('setup/payslip-types',              [PayrollSetupApiController::class, 'getPayslipTypes']);
            Route::post('setup/payslip-types',             [PayrollSetupApiController::class, 'createPayslipType']);
            Route::get('setup/payslip-types/{id}',         [PayrollSetupApiController::class, 'getPayslipType']);
            Route::put('setup/payslip-types/{id}',         [PayrollSetupApiController::class, 'updatePayslipType']);
            Route::delete('setup/payslip-types/{id}',      [PayrollSetupApiController::class, 'deletePayslipType']);

            // Setup - Salary Structures
            Route::get('setup/salary-structures',          [PayrollSetupApiController::class, 'getSalaryStructures']);
            Route::post('setup/salary-structures',         [PayrollSetupApiController::class, 'createSalaryStructure']);
            Route::get('setup/salary-structures/{id}',     [PayrollSetupApiController::class, 'getSalaryStructure']);
            Route::put('setup/salary-structures/{id}',     [PayrollSetupApiController::class, 'updateSalaryStructure']);
            Route::delete('setup/salary-structures/{id}',  [PayrollSetupApiController::class, 'deleteSalaryStructure']);
        }
        );

        // ============================================
        // HRM Module - Employee Salary (Set Salary)
        // ============================================
        Route::prefix('employee-salary')->group(function () {
            Route::get('/',                                           [EmployeeSalaryApiController::class, 'index']);
            Route::get('/{id}',                                       [EmployeeSalaryApiController::class, 'show']);
            Route::put('/{id}',                                       [EmployeeSalaryApiController::class, 'updateBasicSalary']);

            Route::get('/{id}/allowances',                            [EmployeeSalaryApiController::class, 'getAllowances']);
            Route::post('/{id}/allowances',                           [EmployeeSalaryApiController::class, 'createAllowance']);
            Route::put('/{id}/allowances/{allowanceId}',              [EmployeeSalaryApiController::class, 'updateAllowance']);
            Route::delete('/{id}/allowances/{allowanceId}',           [EmployeeSalaryApiController::class, 'deleteAllowance']);

            Route::get('/{id}/commissions',                           [EmployeeSalaryApiController::class, 'getCommissions']);
            Route::post('/{id}/commissions',                          [EmployeeSalaryApiController::class, 'createCommission']);
            Route::put('/{id}/commissions/{commissionId}',            [EmployeeSalaryApiController::class, 'updateCommission']);
            Route::delete('/{id}/commissions/{commissionId}',         [EmployeeSalaryApiController::class, 'deleteCommission']);

            Route::get('/{id}/loans',                                 [EmployeeSalaryApiController::class, 'getLoans']);
            Route::post('/{id}/loans',                                [EmployeeSalaryApiController::class, 'createLoan']);
            Route::put('/{id}/loans/{loanId}',                        [EmployeeSalaryApiController::class, 'updateLoan']);
            Route::delete('/{id}/loans/{loanId}',                     [EmployeeSalaryApiController::class, 'deleteLoan']);

            Route::get('/{id}/deductions',                            [EmployeeSalaryApiController::class, 'getDeductions']);
            Route::post('/{id}/deductions',                           [EmployeeSalaryApiController::class, 'createDeduction']);
            Route::put('/{id}/deductions/{deductionId}',              [EmployeeSalaryApiController::class, 'updateDeduction']);
            Route::delete('/{id}/deductions/{deductionId}',           [EmployeeSalaryApiController::class, 'deleteDeduction']);

            Route::get('/{id}/other-payments',                        [EmployeeSalaryApiController::class, 'getOtherPayments']);
            Route::post('/{id}/other-payments',                       [EmployeeSalaryApiController::class, 'createOtherPayment']);
            Route::put('/{id}/other-payments/{paymentId}',            [EmployeeSalaryApiController::class, 'updateOtherPayment']);
            Route::delete('/{id}/other-payments/{paymentId}',         [EmployeeSalaryApiController::class, 'deleteOtherPayment']);

            Route::get('/{id}/overtime',                              [EmployeeSalaryApiController::class, 'getOvertimes']);
            Route::post('/{id}/overtime',                             [EmployeeSalaryApiController::class, 'createOvertime']);
            Route::put('/{id}/overtime/{overtimeId}',                 [EmployeeSalaryApiController::class, 'updateOvertime']);
            Route::delete('/{id}/overtime/{overtimeId}',              [EmployeeSalaryApiController::class, 'deleteOvertime']);
        });

        // ============================================
        // HRM Module - Departments
        // ============================================
        Route::prefix('departments')->group(function () {
            Route::get('/', [DepartmentApiController::class , 'getDepartments']);
            Route::post('/', [DepartmentApiController::class , 'createDepartment']);
            Route::get('/{id}', [DepartmentApiController::class , 'getDepartment']);
            Route::put('/{id}', [DepartmentApiController::class , 'updateDepartment']);
            Route::delete('/{id}', [DepartmentApiController::class , 'deleteDepartment']);
            Route::get('/{id}/employees', [DepartmentApiController::class , 'getDepartmentEmployees']);
        }
        );

        // ============================================
        // HRM Module - Designations
        // ============================================
        Route::prefix('designations')->group(function () {
            Route::get('/', [DesignationApiController::class , 'getDesignations']);
            Route::post('/', [DesignationApiController::class , 'createDesignation']);
            Route::get('/{id}', [DesignationApiController::class , 'getDesignation']);
            Route::put('/{id}', [DesignationApiController::class , 'updateDesignation']);
            Route::delete('/{id}', [DesignationApiController::class , 'deleteDesignation']);
        }
        );

        // ============================================
        // HRM Module - Awards
        // ============================================
        Route::prefix('awards')->group(function () {
            Route::get('/', [AwardApiController::class , 'getAwards']);
            Route::post('/', [AwardApiController::class , 'createAward']);
            Route::get('/{id}', [AwardApiController::class , 'getAward']);
            Route::put('/{id}', [AwardApiController::class , 'updateAward']);
            Route::delete('/{id}', [AwardApiController::class , 'deleteAward']);
        }
        );

        // ============================================
        // HRM Module - Complaints
        // ============================================
        Route::prefix('complaints')->group(function () {
            Route::get('/', [ComplaintApiController::class , 'getComplaints']);
            Route::post('/', [ComplaintApiController::class , 'createComplaint']);
            Route::get('/{id}', [ComplaintApiController::class , 'getComplaint']);
            Route::put('/{id}', [ComplaintApiController::class , 'updateComplaint']);
            Route::delete('/{id}', [ComplaintApiController::class , 'deleteComplaint']);
        }
        );

        // ============================================
        // HRM Module - Warnings
        // ============================================
        Route::prefix('warnings')->group(function () {
            Route::get('/', [WarningApiController::class , 'getWarnings']);
            Route::post('/', [WarningApiController::class , 'createWarning']);
            Route::get('/{id}', [WarningApiController::class , 'getWarning']);
            Route::put('/{id}', [WarningApiController::class , 'updateWarning']);
            Route::delete('/{id}', [WarningApiController::class , 'deleteWarning']);
        }
        );

        // ============================================
        // HRM Module - Terminations
        // ============================================
        Route::prefix('terminations')->group(function () {
            Route::get('/', [TerminationApiController::class , 'getTerminations']);
            Route::post('/', [TerminationApiController::class , 'createTermination']);
            Route::get('/{id}', [TerminationApiController::class , 'getTermination']);
            Route::put('/{id}', [TerminationApiController::class , 'updateTermination']);
            Route::delete('/{id}', [TerminationApiController::class , 'deleteTermination']);
        }
        );

        // ============================================
        // HRM Module - Promotions
        // ============================================
        Route::prefix('promotions')->group(function () {
            Route::get('/', [PromotionApiController::class , 'getPromotions']);
            Route::post('/', [PromotionApiController::class , 'createPromotion']);
            Route::get('/{id}', [PromotionApiController::class , 'getPromotion']);
            Route::put('/{id}', [PromotionApiController::class , 'updatePromotion']);
            Route::delete('/{id}', [PromotionApiController::class , 'deletePromotion']);
        }
        );

        // ============================================
        // HRM Module - Transfers
        // ============================================
        Route::prefix('transfers')->group(function () {
            Route::get('/', [TransferApiController::class , 'getTransfers']);
            Route::post('/', [TransferApiController::class , 'createTransfer']);
            Route::get('/{id}', [TransferApiController::class , 'getTransfer']);
            Route::put('/{id}', [TransferApiController::class , 'updateTransfer']);
            Route::delete('/{id}', [TransferApiController::class , 'deleteTransfer']);
        }
        );

        // ============================================
        // HRM Module - Resignations
        // ============================================
        Route::prefix('resignations')->group(function () {
            Route::get('/', [ResignationApiController::class , 'getResignations']);
            Route::post('/', [ResignationApiController::class , 'createResignation']);
            Route::get('/{id}', [ResignationApiController::class , 'getResignation']);
            Route::put('/{id}', [ResignationApiController::class , 'updateResignation']);
            Route::delete('/{id}', [ResignationApiController::class , 'deleteResignation']);
        }
        );

        // ============================================
        // HRM Module - Travels
        // ============================================
        Route::prefix('travels')->group(function () {
            Route::get('/', [TravelApiController::class , 'getTravels']);
            Route::post('/', [TravelApiController::class , 'createTravel']);
            Route::get('/{id}', [TravelApiController::class , 'getTravel']);
            Route::put('/{id}', [TravelApiController::class , 'updateTravel']);
            Route::delete('/{id}', [TravelApiController::class , 'deleteTravel']);
        }
        );

        // ============================================
        // HRM Module - Training
        // ============================================
        // (Already updated in previous step, skipping or keeping as is if I select carefully)
        // Wait, the block I am selecting starts at 199 (Designations) and ends at 321 (Performance).
        // Training is in between. I updated Training in step 242.
        // I should check if Training is currently correct in File.
        // view_file 269 showed:
        // 301:         Route::get('/', [TrainingApiController::class, 'getTrainings']);
        // So Training is ALREADY TrainingApiController.
        // I must include it in the replacement block as unmodified or updated.
        // I will include it as unmodified (using TrainingApiController).
    
        Route::prefix('trainings')->group(function () {
            Route::get('/', [TrainingApiController::class , 'getTrainings']);
            Route::post('/', [TrainingApiController::class , 'createTraining']);
            Route::get('types', [TrainingApiController::class , 'getTrainingTypes']);
            Route::get('trainers', [TrainingApiController::class , 'getTrainers']);
            Route::get('/{id}', [TrainingApiController::class , 'getTraining']);
            Route::put('/{id}', [TrainingApiController::class , 'updateTraining']);
            Route::delete('/{id}', [TrainingApiController::class , 'deleteTraining']);
        }
        );

        // ============================================
        // HRM Module - Performance
        // ============================================
        Route::prefix('performance')->group(function () {
            Route::get('indicators', [IndicatorApiController::class , 'getIndicators']);
            Route::post('indicators', [IndicatorApiController::class , 'createIndicator']);
            Route::get('appraisals', [AppraisalApiController::class , 'getAppraisals']);
            Route::post('appraisals', [AppraisalApiController::class , 'createAppraisal']);
            Route::get('goals', [GoalApiController::class , 'getGoals']);
            Route::post('goals', [GoalApiController::class , 'createGoal']);
            Route::get('competencies', [CompetenciesApiController::class , 'getCompetencies']);
        }
        );

        // ============================================
        // Project Management Module
        // ============================================
        Route::prefix('projects')->group(function () {
            Route::get('/', [ProjectApiController::class , 'getProjects']);
            Route::post('/', [ProjectApiController::class , 'createProject']);
            Route::get('/{id}', [ProjectApiController::class , 'getProject']);
            Route::put('/{id}', [ProjectApiController::class , 'updateProject']);
            Route::delete('/{id}', [ProjectApiController::class , 'deleteProject']);
            Route::post('/{id}/duplicate', [ProjectApiController::class , 'duplicateProject']);
            Route::get('/{id}/tasks', [TaskApiController::class, 'getTasksByProject']);
            Route::get('/{id}/milestones', [ProjectApiController::class , 'getProjectMilestones']);
            Route::get('/{id}/bugs', [ProjectApiController::class , 'getProjectBugs']);
            Route::get('/{id}/files', [ProjectApiController::class , 'getProjectFiles']);
            Route::post('/{id}/files', [ProjectApiController::class , 'uploadProjectFile']);
            Route::get('/files/{fileId}/serve', [ProjectApiController::class , 'serveProjectFile']);
            Route::get('/{id}/members', [ProjectApiController::class , 'getProjectMembers']);
            Route::post('/{id}/members', [ProjectApiController::class , 'addProjectMember']);
            Route::delete('/{id}/members/{user_id}', [ProjectApiController::class , 'removeProjectMember']);
            Route::get('/{id}/activity', [ProjectApiController::class , 'getProjectActivity']);
            Route::get('/{id}/timesheet', [ProjectApiController::class , 'getProjectTimesheet']);
            Route::get('/{id}/expenses', [ProjectApiController::class , 'getProjectExpenses']);
            Route::post('/{id}/expenses', [ProjectApiController::class , 'createProjectExpense']);
        }
        );

        // ============================================
        // Project Management - Tasks
        // ============================================
        Route::prefix('tasks')->group(function () {
            Route::get('/', [TaskApiController::class , 'getTasks']);
            Route::get('my', [TaskApiController::class , 'getMyTasks']);
            Route::post('/', [TaskApiController::class , 'createProjectTask']);
            Route::get('/{id}', [TaskApiController::class , 'getProjectTask']);
            Route::put('/{id}', [TaskApiController::class , 'updateProjectTask']);
            Route::delete('/{id}', [TaskApiController::class , 'deleteProjectTask']);
            Route::post('/{id}/complete', [TaskApiController::class , 'completeTask']);
            // check-in / check-out are aliases of the task time tracker start / stop
            Route::post('/{id}/check-in', [TaskApiController::class , 'startTimeTracker']);
            Route::post('/{id}/check-out', [TaskApiController::class , 'stopTimeTracker']);
            Route::post('/{id}/comments', [TaskApiController::class , 'addTaskComment']);
            Route::get('/{id}/comments', [TaskApiController::class , 'getTaskComments']);
            Route::post('/{id}/files', [TaskApiController::class , 'uploadTaskFile']);
            Route::get('/{id}/files', [TaskApiController::class , 'getTaskFiles']);
            Route::get('/files/{fileId}/serve', [TaskApiController::class , 'serveTaskFile']);
            Route::post('/{id}/checklist', [TaskApiController::class , 'addTaskChecklist']);
            Route::get('/{id}/time-tracker', [TaskApiController::class , 'getTaskTimeTracker']);
            Route::post('/{id}/time-tracker/start', [TaskApiController::class , 'startTimeTracker']);
            Route::post('/{id}/time-tracker/stop', [TaskApiController::class , 'stopTimeTracker']);
            Route::patch('/{id}/status', [TaskApiController::class , 'updateTaskStatus']);
        }
        );

        // ============================================
        // Unified File Upload
        // ============================================
        Route::post('uploads', [TaskApiController::class , 'uploadFile']);

        // ============================================
        // Project Milestones
        // ============================================
        Route::prefix('milestones')->group(function () {
            Route::get('/', [MilestoneApiController::class , 'getMilestones']);
            Route::post('/', [MilestoneApiController::class , 'createMilestone']);
            Route::get('/{id}', [MilestoneApiController::class , 'getMilestone']);
            Route::put('/{id}', [MilestoneApiController::class , 'updateMilestone']);
            Route::delete('/{id}', [MilestoneApiController::class , 'deleteMilestone']);
        }
        );

        // ============================================
        // Project Bugs
        // ============================================
        Route::prefix('bugs')->group(function () {
            Route::get('/', [BugApiController::class , 'getBugs']);
            Route::post('/', [BugApiController::class , 'createBug']);
            Route::get('/{id}', [BugApiController::class , 'getBug']);
            Route::put('/{id}', [BugApiController::class , 'updateBug']);
            Route::delete('/{id}', [BugApiController::class , 'deleteBug']);
            Route::post('/{id}/comments', [BugApiController::class , 'addBugComment']);
            Route::get('/{id}/comments', [BugApiController::class , 'getBugComments']);
        }
        );

        // ============================================
        // CRM Module - Customers
        // ============================================
        Route::prefix('customers')->group(function () {
            Route::get('/', [CustomerApiController::class , 'getCustomers']);
            Route::post('/', [CustomerApiController::class , 'createCustomer']);
            Route::get('/{id}', [CustomerApiController::class , 'getCustomer']);
            Route::put('/{id}', [CustomerApiController::class , 'updateCustomer']);
            Route::delete('/{id}', [CustomerApiController::class , 'deleteCustomer']);
            Route::get('/{id}/invoices', [CustomerApiController::class , 'getCustomerInvoices']);
            Route::get('/{id}/payments', [CustomerApiController::class , 'getCustomerPayments']);
            Route::get('/{id}/deals', [CustomerApiController::class , 'getCustomerDeals']);
        }
        );

        // ============================================
        // CRM Module - Leads
        // ============================================
        Route::prefix('leads')->group(function () {
            Route::get('/',              [LeadApiController::class, 'getLeads']);
            Route::post('/',             [LeadApiController::class, 'createLead']);
            Route::get('stages',         [LeadApiController::class, 'getLeadStages']);
            Route::get('sources',        [LeadApiController::class, 'getLeadSources']);
            Route::get('labels',         [LeadApiController::class, 'getLeadLabels']);
            Route::get('interests',      [LeadApiController::class, 'getLeadInterests']);
            Route::get('pipelines',      [LeadApiController::class, 'getLeadPipelines']);
            Route::get('costing-types',  [LeadApiController::class, 'getCostingTypes']);
            Route::get('/{id}',          [LeadApiController::class, 'getLead']);
            Route::put('/{id}',          [LeadApiController::class, 'updateLead']);
            Route::delete('/{id}',       [LeadApiController::class, 'deleteLead']);
            Route::post('/{id}/convert', [LeadApiController::class, 'convertLead']);
            Route::get('/{id}/deals',   [DealApiController::class, 'getLeadDeals']);
        });

        // ============================================
        // CRM Module - Deals
        // ============================================
        Route::prefix('deals')->group(function () {
            Route::get('/', [DealApiController::class , 'getDeals']);
            Route::post('/', [DealApiController::class , 'createDeal']);
            Route::get('pipelines', [DealApiController::class , 'getPipelines']);
            Route::get('stages', [DealApiController::class , 'getDealStages']);
            Route::get('/{id}', [DealApiController::class , 'getDeal']);
            Route::put('/{id}', [DealApiController::class , 'updateDeal']);
            Route::delete('/{id}', [DealApiController::class , 'deleteDeal']);
            Route::post('/{id}/products', [DealApiController::class , 'addDealProduct']);
            Route::get('/{id}/attachments', [DealApiController::class , 'getDealAttachments']);
            Route::post('/{id}/attachments', [DealApiController::class , 'uploadDealAttachment']);
        }
        );

        // ============================================
        // CRM Module - Sales Meetings
        // ============================================
        Route::prefix('sales-meetings')->group(function () {
            Route::get('/', [SalesMeetingApiController::class, 'getMeetings']);
            Route::post('/', [SalesMeetingApiController::class, 'createMeeting']);
            Route::get('types', [SalesMeetingApiController::class, 'getMeetingTypes']);
            Route::get('/{id}', [SalesMeetingApiController::class, 'getMeeting']);
            Route::put('/{id}', [SalesMeetingApiController::class, 'updateMeeting']);
            Route::delete('/{id}', [SalesMeetingApiController::class, 'deleteMeeting']);
        });

        // ============================================
        // CRM Module - Deal Purchases
        // ============================================
        Route::prefix('deal-purchases')->group(function () {
            Route::get('/', [DealPurchaseApiController::class, 'getDealPurchases']);
            Route::post('/', [DealPurchaseApiController::class, 'createDealPurchase']);
            Route::get('payment-methods', [DealPurchaseApiController::class, 'getPaymentMethods']);
            Route::get('payment-types',   [DealPurchaseApiController::class, 'getPaymentTypes']);
            Route::get('statuses',        [DealPurchaseApiController::class, 'getStatuses']);
            Route::get('/{id}', [DealPurchaseApiController::class, 'getDealPurchase']);
            Route::put('/{id}', [DealPurchaseApiController::class, 'updateDealPurchase']);
            Route::delete('/{id}', [DealPurchaseApiController::class, 'deleteDealPurchase']);
            Route::post('/{id}/attachments', [DealPurchaseApiController::class, 'uploadAttachment']);
        });

        // ============================================
        // CRM Module - Installments
        // ============================================
        Route::prefix('installments')->group(function () {
            Route::get('/', [InstallmentApiController::class, 'getInstallments']);
            Route::post('/', [InstallmentApiController::class, 'createInstallment']);
            Route::get('/{id}', [InstallmentApiController::class, 'getInstallment']);
            Route::put('/{id}', [InstallmentApiController::class, 'updateInstallment']);
            Route::delete('/{id}', [InstallmentApiController::class, 'deleteInstallment']);
            Route::patch('/{id}/status', [InstallmentApiController::class, 'updateInstallmentStatus']);
        });

        // ============================================
        // Accounting Module - Invoices
        // ============================================
        Route::prefix('invoices')->group(function () {
            Route::get('/', [InvoiceApiController::class , 'getInvoices']);
            Route::post('/', [InvoiceApiController::class , 'createInvoice']);
            Route::get('/{id}', [InvoiceApiController::class , 'getInvoice']);
            Route::put('/{id}', [InvoiceApiController::class , 'updateInvoice']);
            Route::delete('/{id}', [InvoiceApiController::class , 'deleteInvoice']);
            Route::get('/{id}/pdf', [InvoiceApiController::class , 'downloadInvoicePdf']);
            Route::post('/{id}/send', [InvoiceApiController::class , 'sendInvoice']);
            Route::post('/{id}/payment', [InvoiceApiController::class , 'addInvoicePayment']);
            Route::get('/{id}/payments', [InvoiceApiController::class , 'getInvoicePayments']);
            Route::post('/{id}/duplicate', [InvoiceApiController::class , 'duplicateInvoice']);
        }
        );

        // ============================================
        // Accounting Module - Bills
        // ============================================
        Route::prefix('bills')->group(function () {
            Route::get('/', [BillApiController::class , 'getBills']);
            Route::post('/', [BillApiController::class , 'createBill']);
            Route::get('/{id}', [BillApiController::class , 'getBill']);
            Route::put('/{id}', [BillApiController::class , 'updateBill']);
            Route::delete('/{id}', [BillApiController::class , 'deleteBill']);
            Route::get('/{id}/pdf', [BillApiController::class , 'downloadBillPdf']);
            Route::post('/{id}/payment', [BillApiController::class , 'addBillPayment']);
        }
        );

        // ============================================
        // Accounting Module - Vendors
        // ============================================
        Route::prefix('vendors')->group(function () {
            Route::get('/', [VendorApiController::class , 'getVendors']);
            Route::post('/', [VendorApiController::class , 'createVendor']);
            Route::get('/{id}', [VendorApiController::class , 'getVendor']);
            Route::put('/{id}', [VendorApiController::class , 'updateVendor']);
            Route::delete('/{id}', [VendorApiController::class , 'deleteVendor']);
            Route::get('/{id}/bills', [VendorApiController::class , 'getVendorBills']);
            Route::get('/{id}/payments', [VendorApiController::class , 'getVendorPayments']);
        }
        );

        // ============================================
        // Accounting Module - Products/Services
        // ============================================
        Route::prefix('products')->group(function () {
            Route::get('/',          [ProductApiController::class, 'getProducts']);
            Route::post('/',         [ProductApiController::class, 'createProduct']);
            Route::get('categories', [ProductApiController::class, 'getProductCategories']);
            Route::post('categories',[ProductApiController::class, 'createProductCategory']);
            Route::get('units',      [ProductApiController::class, 'getProductUnits']);
            Route::post('units',     [ProductApiController::class, 'createProductUnit']);
            Route::get('taxes',      [ProductApiController::class, 'getProductTaxes']);
            Route::post('taxes',     [ProductApiController::class, 'createProductTax']);
            Route::get('accounts',   [ProductApiController::class, 'getProductAccounts']);
            Route::get('generate-sku',[ProductApiController::class, 'generateSku']);
            Route::get('/{id}',      [ProductApiController::class, 'getProduct']);
            Route::put('/{id}',      [ProductApiController::class, 'updateProduct']);
            Route::post('/{id}',     [ProductApiController::class, 'updateProduct']);
            Route::delete('/{id}',   [ProductApiController::class, 'deleteProduct']);
            Route::get('/{id}/stock',[ProductApiController::class, 'getProductStock']);
        });

        // ============================================
        // Accounting Module - Expenses
        // ============================================
        Route::prefix('expenses')->group(function () {
            Route::get('/', [ExpenseApiController::class , 'getExpenses']);
            Route::post('/', [ExpenseApiController::class , 'createExpense']);
            Route::get('/{id}', [ExpenseApiController::class , 'getExpense']);
            Route::put('/{id}', [ExpenseApiController::class , 'updateExpense']);
            Route::delete('/{id}', [ExpenseApiController::class , 'deleteExpense']);
        }
        );

        // ============================================
        // Accounting Module - Revenue
        // ============================================
        Route::prefix('revenue')->group(function () {
            Route::get('/', [RevenueApiController::class , 'getRevenues']);
            Route::post('/', [RevenueApiController::class , 'createRevenue']);
            Route::get('/{id}', [RevenueApiController::class , 'getRevenue']);
            Route::put('/{id}', [RevenueApiController::class , 'updateRevenue']);
            Route::delete('/{id}', [RevenueApiController::class , 'deleteRevenue']);
        }
        );

        // ============================================
        // Accounting Module - Budget
        // ============================================
        Route::prefix('budgets')->group(function () {
            Route::get('/', [BudgetApiController::class , 'getBudgets']);
            Route::post('/', [BudgetApiController::class , 'createBudget']);
            Route::get('/{id}', [BudgetApiController::class , 'getBudget']);
            Route::put('/{id}', [BudgetApiController::class , 'updateBudget']);
            Route::delete('/{id}', [BudgetApiController::class , 'deleteBudget']);
        }
        );

        // ============================================
        // Accounting Module - Chart of Accounts
        // ============================================
        Route::prefix('chart-of-accounts')->group(function () {
            Route::get('/', [ChartOfAccountApiController::class , 'getChartOfAccounts']);
            Route::post('/', [ChartOfAccountApiController::class , 'createChartOfAccount']);
            Route::get('/{id}', [ChartOfAccountApiController::class , 'getChartOfAccount']);
            Route::put('/{id}', [ChartOfAccountApiController::class , 'updateChartOfAccount']);
            Route::delete('/{id}', [ChartOfAccountApiController::class , 'deleteChartOfAccount']);
        }
        );

        // ============================================
        // Accounting Module - Journal Entries
        // ============================================
        Route::prefix('journal-entries')->group(function () {
            Route::get('/', [JournalEntryApiController::class , 'getJournalEntries']);
            Route::post('/', [JournalEntryApiController::class , 'createJournalEntry']);
            Route::get('/{id}', [JournalEntryApiController::class , 'getJournalEntry']);
            Route::put('/{id}', [JournalEntryApiController::class , 'updateJournalEntry']);
            Route::delete('/{id}', [JournalEntryApiController::class , 'deleteJournalEntry']);
        }
        );

        // ============================================
        // Accounting Module - Bank Accounts
        // ============================================
        Route::prefix('bank-accounts')->group(function () {
            Route::get('/', [BankAccountApiController::class , 'getBankAccounts']);
            Route::post('/', [BankAccountApiController::class , 'createBankAccount']);
            Route::get('/{id}', [BankAccountApiController::class , 'getBankAccount']);
            Route::put('/{id}', [BankAccountApiController::class , 'updateBankAccount']);
            Route::delete('/{id}', [BankAccountApiController::class , 'deleteBankAccount']);
        }
        );

        // ============================================
        // Accounting Module - Transactions
        // ============================================
        Route::prefix('transactions')->group(function () {
            Route::get('/', [TransactionApiController::class , 'getTransactions']);
            Route::post('/', [TransactionApiController::class , 'createTransaction']);
            Route::get('/{id}', [TransactionApiController::class , 'getTransaction']);
            Route::put('/{id}', [TransactionApiController::class , 'updateTransaction']);
            Route::delete('/{id}', [TransactionApiController::class , 'deleteTransaction']);
        }
        );

        // ============================================
        // Inventory Module - Warehouses
        // ============================================
        Route::prefix('warehouses')->group(function () {
            Route::get('/', [WarehouseApiController::class , 'getWarehouses']);
            Route::post('/', [WarehouseApiController::class , 'createWarehouse']);
            Route::get('/{id}', [WarehouseApiController::class , 'getWarehouse']);
            Route::put('/{id}', [WarehouseApiController::class , 'updateWarehouse']);
            Route::delete('/{id}', [WarehouseApiController::class , 'deleteWarehouse']);
            Route::get('/{id}/products', [WarehouseApiController::class , 'getWarehouseProducts']);
            Route::post('transfers', [WarehouseApiController::class , 'createWarehouseTransfer']);
        }
        );

        // ============================================
        // Inventory Module - Purchases
        // ============================================
        Route::prefix('purchases')->group(function () {
            Route::get('/', [PurchaseApiController::class , 'getPurchases']);
            Route::post('/', [PurchaseApiController::class , 'createPurchase']);
            Route::get('/{id}', [PurchaseApiController::class , 'getPurchase']);
            Route::put('/{id}', [PurchaseApiController::class , 'updatePurchase']);
            Route::delete('/{id}', [PurchaseApiController::class , 'deletePurchase']);
        }
        );

        // ============================================
        // POS Module
        // ============================================
        Route::prefix('pos')->group(function () {
            Route::get('products', [PosApiController::class , 'getPosProducts']);
            Route::post('cart/add', [PosApiController::class , 'addToCart']);
            Route::post('cart/remove', [PosApiController::class , 'removeFromCart']);
            Route::get('cart', [PosApiController::class , 'getCart']);
            Route::post('checkout', [PosApiController::class , 'posCheckout']);
            Route::get('sales', [PosApiController::class , 'getPosSales']);
        }
        );

        // ============================================
        // Proposals
        // ============================================
        Route::prefix('proposals')->group(function () {
            Route::get('/', [ProposalApiController::class , 'getProposals']);
            Route::post('/', [ProposalApiController::class , 'createProposal']);
            Route::get('/{id}', [ProposalApiController::class , 'getProposal']);
            Route::put('/{id}', [ProposalApiController::class , 'updateProposal']);
            Route::delete('/{id}', [ProposalApiController::class , 'deleteProposal']);
            Route::get('/{id}/pdf', [ProposalApiController::class , 'downloadProposalPdf']);
            Route::post('/{id}/send', [ProposalApiController::class , 'sendProposal']);
        }
        );

        // ============================================
        // Contracts
        // ============================================
        Route::prefix('contracts')->group(function () {
            Route::get('/', [ContractApiController::class , 'getContracts']);
            Route::post('/', [ContractApiController::class , 'createContract']);
            Route::get('/{id}', [ContractApiController::class , 'getContract']);
            Route::put('/{id}', [ContractApiController::class , 'updateContract']);
            Route::delete('/{id}', [ContractApiController::class , 'deleteContract']);
            Route::post('/{id}/sign', [ContractApiController::class , 'signContract']);
        }
        );

        // ============================================
        // Recruitment Module - Jobs
        // ============================================
        Route::prefix('jobs')->group(function () {
            Route::get('/', [JobApiController::class , 'getJobs']);
            Route::post('/', [JobApiController::class , 'createJob']);
            Route::get('categories', [JobApiController::class , 'getJobCategories']);
            Route::get('/{id}', [JobApiController::class , 'getJob']);
            Route::put('/{id}', [JobApiController::class , 'updateJob']);
            Route::delete('/{id}', [JobApiController::class , 'deleteJob']);
            Route::get('/{id}/applications', [JobApiController::class , 'getJobApplications']);
        }
        );

        // ============================================
        // Recruitment Module - Job Applications
        // ============================================
        Route::prefix('job-applications')->group(function () {
            Route::get('/', [JobApplicationApiController::class , 'getJobApplications']);
            Route::get('/{id}', [JobApplicationApiController::class , 'getJobApplication']);
            Route::put('/{id}', [JobApplicationApiController::class , 'updateJobApplication']);
            Route::delete('/{id}', [JobApplicationApiController::class , 'deleteJobApplication']);
            Route::post('/{id}/move-stage', [JobApplicationApiController::class , 'moveApplicationStage']);
        }
        );

        // ============================================
        // Recruitment Module - Interviews
        // ============================================
        Route::prefix('interviews')->group(function () {
            Route::get('/', [InterviewApiController::class , 'getInterviews']);
            Route::post('/', [InterviewApiController::class , 'scheduleInterview']);
            Route::get('/{id}', [InterviewApiController::class , 'getInterview']);
            Route::put('/{id}', [InterviewApiController::class , 'updateInterview']);
            Route::delete('/{id}', [InterviewApiController::class , 'deleteInterview']);
        }
        );

        // ============================================
        // Support Module - Tickets
        // ============================================
        Route::prefix('tickets')->group(function () {
            Route::get('/', [TicketApiController::class , 'getTickets']);
            Route::post('/', [TicketApiController::class , 'createTicket']);
            Route::get('/{id}', [TicketApiController::class , 'getTicket']);
            Route::put('/{id}', [TicketApiController::class , 'updateTicket']);
            Route::delete('/{id}', [TicketApiController::class , 'deleteTicket']);
            Route::post('/{id}/reply', [TicketApiController::class , 'addTicketReply']);
            Route::get('/{id}/replies', [TicketApiController::class , 'getTicketReplies']);
            Route::post('/{id}/close', [TicketApiController::class , 'closeTicket']);
        }
        );

        // ============================================
        // Support Requests
        // ============================================
        Route::prefix('support-requests')->group(function () {
            Route::get('/', [SupportRequestApiController::class , 'getSupportRequests']);
            Route::post('/', [SupportRequestApiController::class , 'createSupportRequest']);
            Route::get('/{id}', [SupportRequestApiController::class , 'getSupportRequest']);
            Route::put('/{id}', [SupportRequestApiController::class , 'updateSupportRequest']);
            Route::post('/{id}/update-status', [SupportRequestApiController::class , 'updateSupportRequestStatus']);
        }
        );

        // ============================================
        // Assets Management
        // ============================================
        Route::prefix('assets')->group(function () {
            Route::get('/', [AssetApiController::class , 'getAssets']);
            Route::post('/', [AssetApiController::class , 'createAsset']);
            Route::get('/{id}', [AssetApiController::class , 'getAsset']);
            Route::put('/{id}', [AssetApiController::class , 'updateAsset']);
            Route::delete('/{id}', [AssetApiController::class , 'deleteAsset']);
        }
        );

        // ============================================
        // Documents
        // ============================================
        // /documents = document types; /document-uploads = the uploaded files (separate id spaces)
        Route::prefix('documents')->group(function () {
            Route::get('/', [ApiController::class , 'getDocuments']);
            Route::post('/', [DocumentApiController::class , 'createDocumentType']);
            Route::get('/{id}', [ApiController::class , 'getDocument']);
            Route::delete('/{id}', [DocumentApiController::class , 'deleteDocumentType']);
        }
        );

        Route::prefix('document-uploads')->group(function () {
            Route::get('/', [DocumentApiController::class , 'getDocumentUploads']);
            Route::post('/', [DocumentApiController::class , 'uploadDocument']);
            Route::get('/{id}', [DocumentApiController::class , 'getDocumentUpload']);
            Route::delete('/{id}', [DocumentApiController::class , 'deleteDocument']);
            Route::get('/{id}/download', [DocumentApiController::class , 'downloadDocument']);
        });

        // ============================================
        // Announcements
        // ============================================
        Route::prefix('announcements')->group(function () {
            Route::get('/', [AnnouncementApiController::class , 'getAnnouncements']);
            Route::post('/', [AnnouncementApiController::class , 'createAnnouncement']);
            Route::get('/{id}', [AnnouncementApiController::class , 'getAnnouncement']);
            Route::put('/{id}', [AnnouncementApiController::class , 'updateAnnouncement']);
            Route::delete('/{id}', [AnnouncementApiController::class , 'deleteAnnouncement']);
        }
        );

        // ============================================
        // Meetings
        // ============================================
        Route::prefix('meetings')->group(function () {
            Route::get('/', [MeetingApiController::class , 'getMeetings']);
            Route::post('/', [MeetingApiController::class , 'createMeeting']);
            Route::get('/{id}', [MeetingApiController::class , 'getMeeting']);
            Route::put('/{id}', [MeetingApiController::class , 'updateMeeting']);
            Route::delete('/{id}', [MeetingApiController::class , 'deleteMeeting']);
            Route::post('/zoom', [MeetingApiController::class , 'createZoomMeeting']);
        });

        // ============================================
        // Google Meet
        // ============================================
        Route::prefix('google-meet')->group(function () {
            Route::get('/', [GoogleMeetApiController::class, 'getMeetings']);
            Route::post('/', [GoogleMeetApiController::class, 'createMeeting']);
            Route::get('/{id}', [GoogleMeetApiController::class, 'getMeeting']);
            Route::put('/{id}', [GoogleMeetApiController::class, 'updateMeeting']);
            Route::delete('/{id}', [GoogleMeetApiController::class, 'deleteMeeting']);
            Route::post('/{id}/finish', [GoogleMeetApiController::class, 'finishMeeting']);
        });

        // ============================================
        // Events
        // ============================================
        Route::prefix('events')->group(function () {
            Route::get('/', [EventApiController::class , 'getEvents']);
            Route::post('/', [EventApiController::class , 'createEvent']);
            Route::get('/{id}', [EventApiController::class , 'getEvent']);
            Route::put('/{id}', [EventApiController::class , 'updateEvent']);
            Route::delete('/{id}', [EventApiController::class , 'deleteEvent']);
        }
        );

        // ============================================
        // Holidays
        // ============================================
        // ============================================
        // Holidays
        // ============================================
        Route::prefix('holidays')->group(function () {
            Route::get('/', [HolidayApiController::class, 'getHolidays']);
            Route::post('/', [HolidayApiController::class, 'createHoliday']);
            Route::get('/upcoming', [HolidayApiController::class, 'getUpcomingHolidays']);
            Route::get('/stats', [HolidayApiController::class, 'getHolidayStats']);
            Route::get('/by-date-range', [HolidayApiController::class, 'getHolidaysByDateRange']);
            Route::get('/calendar', [HolidayApiController::class, 'getCalendarHolidays']);
            Route::get('/google-calendar', [HolidayApiController::class, 'getGoogleCalendarHolidays']);
            Route::get('/{id}', [HolidayApiController::class, 'getHoliday']);
            Route::put('/{id}', [HolidayApiController::class, 'updateHoliday']);
            Route::delete('/{id}', [HolidayApiController::class, 'deleteHoliday']);
        });

        // ============================================
        // Company Policies
        // ============================================
        Route::prefix('policies')->group(function () {
            Route::get('/', [PolicyApiController::class , 'getPolicies']);
            Route::post('/', [PolicyApiController::class , 'createPolicy']);
            Route::get('/{id}', [PolicyApiController::class , 'getPolicy']);
            Route::put('/{id}', [PolicyApiController::class , 'updatePolicy']);
            Route::delete('/{id}', [PolicyApiController::class , 'deletePolicy']);
        }
        );

        // ============================================
        // Timesheet
        // ============================================
        Route::prefix('timesheet')->group(function () {
            Route::get('/', [TimesheetApiController::class , 'getTimesheets']);
            Route::post('/', [TimesheetApiController::class , 'createTimesheet']);
            Route::get('/{id}', [TimesheetApiController::class , 'getTimesheet']);
            Route::put('/{id}', [TimesheetApiController::class , 'updateTimesheet']);
            Route::delete('/{id}', [TimesheetApiController::class , 'deleteTimesheet']);
        }
        );

        // ============================================
        // Time Tracker
        // ============================================
        Route::prefix('time-tracker')->group(function () {
            Route::post('start', [TimeTrackerApiController::class , 'startTimeTracker']);
            Route::post('stop', [TimeTrackerApiController::class , 'stopTimeTracker']);
            Route::get('current', [TimeTrackerApiController::class , 'getCurrentTimeTracker']);
            Route::get('history', [TimeTrackerApiController::class , 'getTimeTrackerHistory']);
        }
        );

        // ============================================
        // Reports
        // ============================================
        // ============================================
        // Reports
        // ============================================
        Route::prefix('reports')->group(function () {
            Route::get('income-summary', [ReportApiController::class , 'incomeReport']);
            Route::get('expense-summary', [ReportApiController::class , 'expenseReport']);
            Route::get('income-vs-expense', [ReportApiController::class , 'incomeVsExpenseReport']);
            Route::get('tax-summary', [ReportApiController::class , 'taxReport']);
            Route::get('profit-loss', [ReportApiController::class , 'profitLossReport']);
            Route::get('balance-sheet', [ReportApiController::class , 'balanceSheetReport']);
            Route::get('trial-balance', [ReportApiController::class , 'trialBalanceReport']);
            Route::get('invoice-summary', [ReportApiController::class , 'invoiceSummaryReport']);
            Route::get('bill-summary', [ReportApiController::class , 'billSummaryReport']);
            Route::get('product-stock', [ReportApiController::class , 'productStockReport']);
            Route::get('attendance', [ReportApiController::class , 'attendanceReport']);
            Route::get('leave', [ReportApiController::class , 'leaveReport']);
            Route::get('payroll', [ReportApiController::class , 'payrollReport']);
            Route::get('project', [ReportApiController::class , 'projectReport']);
            Route::get('task', [ReportApiController::class , 'taskReport']);
            Route::get('timesheet', [ReportApiController::class , 'timesheetReport']);
            Route::get('lead', [ReportApiController::class , 'leadReport']);
            Route::get('deal', [ReportApiController::class , 'dealReport']);
        }
        );

        // ============================================
        // Branches
        // ============================================
        // ============================================
        // Branches
        // ============================================
        Route::prefix('branches')->group(function () {
            Route::get('/', [BranchApiController::class , 'getBranches']);
            Route::post('/', [BranchApiController::class , 'createBranch']);
            Route::get('/{id}', [BranchApiController::class , 'getBranch']);
            Route::put('/{id}', [BranchApiController::class , 'updateBranch']);
            Route::delete('/{id}', [BranchApiController::class , 'deleteBranch']);
        }
        );

        // ============================================
        // Users & Roles
        // ============================================
        Route::prefix('users')->group(function () {
            Route::get('/', [UserApiController::class , 'getUsers']);
            Route::post('/', [UserApiController::class , 'createUser']);
            Route::get('/{id}', [UserApiController::class , 'getUser']);
            Route::put('/{id}', [UserApiController::class , 'updateUser']);
            Route::delete('/{id}', [UserApiController::class , 'deleteUser']);
            Route::post('/{id}/reset-password', [UserApiController::class , 'resetUserPassword']);
        }
        );

        Route::prefix('roles')->group(function () {
            Route::get('/', [RoleApiController::class , 'getRoles']);
            Route::post('/', [RoleApiController::class , 'createRole']);
            Route::get('/{id}', [RoleApiController::class , 'getRole']);
            Route::put('/{id}', [RoleApiController::class , 'updateRole']);
            Route::delete('/{id}', [RoleApiController::class , 'deleteRole']);
        }
        );

        Route::get('permissions', [RoleApiController::class , 'getPermissions']);

        // ============================================
        // Settings
        // ============================================
        Route::prefix('settings')->group(function () {
            Route::get('/', [SettingsApiController::class , 'getSettings']);
            Route::put('/', [SettingsApiController::class , 'updateSettings']);
            Route::get('company', [SettingsApiController::class , 'getCompanySettings']);
            Route::put('company', [SettingsApiController::class , 'updateCompanySettings']);
            Route::get('email', [SettingsApiController::class , 'getEmailSettings']);
            Route::put('email', [SettingsApiController::class , 'updateEmailSettings']);
            Route::get('payment', [SettingsApiController::class , 'getPaymentSettings']);
            Route::put('payment', [SettingsApiController::class , 'updatePaymentSettings']);
        }
        );

        // ============================================
        // Notifications
        // ============================================
        Route::prefix('notifications')->group(function () {
            Route::get('/', [NotificationApiController::class , 'getNotifications']);
            Route::post('/{id}/read', [NotificationApiController::class , 'markNotificationRead']);
            Route::post('/read-all', [NotificationApiController::class , 'markAllNotificationsRead']);
            Route::delete('/{id}', [NotificationApiController::class , 'deleteNotification']);
        }
        );

        // ============================================
        // Search
        // ============================================
        Route::get('search', [SearchApiController::class , 'globalSearch']);

        // ============================================
        // Import/Export
        // ============================================
        // ============================================
        // Import/Export
        // ============================================
        Route::post('import/employees', [EmployeeApiController::class , 'importEmployees']); // Assuming Method exists or will create
        Route::post('import/customers', [CustomerApiController::class , 'importCustomers']);
        Route::post('import/vendors', [VendorApiController::class , 'importVendors']);
        Route::post('import/products', [ProductApiController::class , 'importProducts']);

        Route::get('export/employees', [EmployeeApiController::class , 'exportEmployees']);
        Route::get('export/customers', [CustomerApiController::class , 'exportCustomers']);
        Route::get('export/vendors', [VendorApiController::class , 'exportVendors']);
        Route::get('export/products', [ProductApiController::class , 'exportProducts']);
        Route::get('export/invoices', [InvoiceApiController::class , 'exportInvoices']);
        Route::get('export/bills', [BillApiController::class , 'exportBills']);

        // ============================================
        // Department-based Project Routes (Legacy)
        // ============================================
        Route::get('get-departments', [DepartmentApiController::class , 'getDepartments']); // Redundant but keeping for legacy compatibility
        Route::get('get-department-projects', [ProjectApiController::class , 'getDepartmentProjects']);
        Route::post('create-project', [ProjectApiController::class , 'createProject']); // Redundant
        // These take project_id / task_id in the body, so they must stay on the legacy ApiController
        // methods: the RESTful controller methods expect {id} in the URL and 500 without it.
        Route::post('update-project', [ApiController::class , 'updateProject']);
        Route::post('delete-project', [ApiController::class , 'deleteProject']);
        Route::post('duplicate-project', [ApiController::class , 'duplicateProject']);
        Route::get('get-project-tasks', [ApiController::class , 'getProjectTasks']);
        Route::post('create-project-task', [TaskApiController::class , 'createProjectTask']); // Redundant
        Route::get('get-project-task', [ApiController::class , 'getProjectTask']);
        Route::post('update-project-task', [ApiController::class , 'updateProjectTask']);
        Route::post('delete-project-task', [ApiController::class , 'deleteProjectTask']);
        // ============================================
        // Quality Evaluation - Daily Reports (SOD/EOD)
        // ============================================
        // Moved to routes/web.php for Session-based Authentication
    });

Route::middleware('auth:sanctum')->group(function () {
    Route::get(
        '/attendance/get-department-projects',
    [AttendanceApiController::class , 'getDepartmentProjects']
    );

    Route::get(
        '/attendance/multiple-records',
    [AttendanceApiController::class , 'getMultipleAttendanceRecords']
    );
});
