<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class ChatController extends Controller
{
    private function getSystemPrompt()
    {
        $user = Auth::user();
        $userName = $user ? $user->name : 'User';

        return "You are an intelligent AI assistant for the ERP Platform (erp.my-communication.uk). You are helpful, professional, and knowledgeable about the entire system.

## About the Platform:
This is a comprehensive ERP (Enterprise Resource Planning) system that includes multiple modules for business management.

## Main Modules & Their Locations:

### 1. Dashboard (/dashboard)
- Main overview with statistics and charts
- Account Dashboard, HRM Dashboard, CRM Dashboard, Project Dashboard, POS Dashboard
- Quick access to reports and analytics

### 2. HRM (Human Resource Management)
- **Employees** (/employee) - Manage all employees, add new employees, view profiles
- **Payroll** (/payslip) - Generate payslips, manage salaries
- **Attendance** (/attendanceemployee) - Track employee attendance, bulk attendance
- **Leave Management** (/leave) - Manage leave requests, leave types
- **Recruitment** (/job) - Post jobs, manage applications, interview scheduling
- **Training** (/training) - Training programs, trainers management
- **Performance** (/indicator, /appraisal) - KPIs, appraisals, goal tracking
- **Awards & Recognition** (/award) - Employee awards
- **Holidays** (/holiday) - Company holidays calendar
- **Announcements** (/announcement) - Company announcements
- **Meetings** (/meeting) - Schedule and manage meetings
- **Documents** (/document-upload) - Employee documents
- **Assets** (/account-assets) - Company assets management

### 3. CRM (Customer Relationship Management)
- **Leads** (/leads) - Manage leads, lead stages, sources
- **Deals** (/deals) - Deal pipeline, deal stages
- **Clients** (/clients) - Client management
- **Contracts** (/contract) - Contract management

### 4. Accounting & Finance
- **Invoices** (/invoice) - Create and manage invoices
- **Bills** (/bill) - Manage bills and expenses
- **Proposals** (/proposal) - Create proposals
- **Bank Accounts** (/bank-account) - Manage bank accounts
- **Transactions** (/transaction) - View all transactions
- **Journal Entry** (/journal-entry) - Double entry accounting
- **Chart of Accounts** (/chart-of-account) - Account management
- **Budget** (/budget) - Budget planning
- **Revenue** (/revenue) - Revenue tracking
- **Expenses** (/expense) - Expense management
- **Credit Notes** (/credit-note) - Credit note management
- **Debit Notes** (/debit-note) - Debit note management

### 5. Projects
- **Projects** (/projects) - Project management
- **Tasks** (/tasks) - Task management
- **Timesheet** (/timesheet) - Time tracking
- **Project Reports** (/project-report) - Project analytics

### 6. POS (Point of Sale)
- **POS** (/pos) - Point of sale system
- **Warehouses** (/warehouse) - Warehouse management
- **Products** (/productservice) - Product catalog
- **Purchases** (/purchase) - Purchase orders

### 7. Support & Tickets
- **Support Tickets** (/support) - Customer support tickets
- **Ticket Management** (/ticket-management) - Internal ticket system

### 8. Reports
- Located under each module's dashboard
- Income Summary, Expense Summary, Tax Reports
- Payroll Reports, Leave Reports, Attendance Reports
- Lead Reports, Deal Reports
- POS Reports, Warehouse Reports

### 9. Settings (/setting)
- System Settings, Company Settings
- Email Settings, Payment Settings
- Tax Settings, Invoice Settings

## How to Navigate:
- Use the sidebar menu on the left to access all modules
- Each module has sub-menus for different features
- Use the search bar to quickly find what you need
- Dashboard shows quick overview and shortcuts

## Common Tasks:
1. **Add Employee**: Go to HRM > Employees > Click 'Create Employee'
2. **Create Invoice**: Go to Accounting > Invoices > Click 'Create Invoice'
3. **Add Lead**: Go to CRM > Leads > Click 'Create Lead'
4. **Create Project**: Go to Projects > Click 'Create Project'
5. **Generate Payslip**: Go to HRM > Payroll > Select Employee > Generate

## User Info:
Current user: {$userName}

## Instructions:
- Always be helpful and provide step-by-step guidance
- If user asks about a feature, explain where to find it and how to use it
- Provide URLs when mentioning locations
- Be concise but thorough
- Use Arabic or English based on the user's language
- If you don't know something specific, guide them to the Settings or Admin section";
    }

    /** Everything here needs the permission; the routes only ever checked auth. */
    private function denyUnlessAllowed()
    {
        if (!Auth::check() || !Auth::user()->can('use ai assistant')) {
            return redirect()->route('dashboard')->with('error', __('Permission Denied.'));
        }

        return null;
    }

    public function index()
    {
        if ($deny = $this->denyUnlessAllowed()) {
            return $deny;
        }

        $messages = ChatMessage::forUser(Auth::id())
            ->orderBy('created_at', 'asc')
            ->get();

        $suggestedQuestions = $this->getSuggestedQuestions();

        return view('chatbot.index', compact('messages', 'suggestedQuestions'));
    }

    private function getSuggestedQuestions()
    {
        return [
            [
                'icon' => 'ti-users',
                'title' => 'Employee Management',
                'question' => 'How do I add a new employee to the system?'
            ],
            [
                'icon' => 'ti-file-invoice',
                'title' => 'Create Invoice',
                'question' => 'How can I create and send an invoice to a client?'
            ],
            [
                'icon' => 'ti-chart-bar',
                'title' => 'View Reports',
                'question' => 'Where can I find financial reports and analytics?'
            ],
            [
                'icon' => 'ti-calendar',
                'title' => 'Leave Management',
                'question' => 'How do I manage employee leave requests?'
            ],
            [
                'icon' => 'ti-briefcase',
                'title' => 'Projects',
                'question' => 'How do I create and manage a new project?'
            ],
            [
                'icon' => 'ti-cash',
                'title' => 'Payroll',
                'question' => 'How do I generate payslips for employees?'
            ],
        ];
    }

    public function send(Request $request)
    {
        if (!Auth::check() || !Auth::user()->can('use ai assistant')) {
            return response()->json([
                'success' => false,
                'message' => __('Permission Denied.'),
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:2000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        try {
            $userMessage = $request->input('message');
            $userId = Auth::id();

            ChatMessage::create([
                'user_id' => $userId,
                'message' => $userMessage,
                'role' => 'user',
                'created_by' => $userId
            ]);

            $conversationHistory = ChatMessage::forUser($userId)
                ->latest()
                ->limit(20)
                ->get()
                ->reverse()
                ->map(function ($msg) {
                    return [
                        'role' => $msg->role,
                        'content' => $msg->message
                    ];
                })
                ->toArray();

            // The lookups, the key resolution, the model choice and the monthly cap all
            // live in AiAssistant so the controller stays a controller. The history
            // excludes the message just stored, which ask() adds as the final turn.
            $priorTurns = array_slice(array_values($conversationHistory), 0, -1);

            $answer = app(\App\Services\Ai\AiAssistant::class)->ask($userMessage, $priorTurns);

            if (!$answer['ok']) {
                // 200, not 500: "you are out of questions this month" and "no key is
                // configured" are answers, not server faults, and the chat box shows
                // them to the user either way.
                return response()->json([
                    'success' => false,
                    'message' => $answer['message'],
                ], 200);
            }

            $botMessage = $answer['message'];

            if ($botMessage === '') {
                return response()->json([
                    'success' => false,
                    'message' => __('The assistant had nothing to say. Try rephrasing.'),
                ], 200);
            }

            $chatMessage = ChatMessage::create([
                'user_id' => $userId,
                'message' => $botMessage,
                'role' => 'assistant',
                'created_by' => 0
            ]);

            return response()->json([
                'success' => true,
                'message' => $botMessage,
                'id' => $chatMessage->id,
                'created_at' => $chatMessage->created_at->format('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    public function clear(Request $request)
    {
        try {
            ChatMessage::where('user_id', Auth::id())->delete();

            return response()->json([
                'success' => true,
                'message' => 'Chat history cleared successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear chat history.'
            ], 500);
        }
    }

    public function history(Request $request)
    {
        $messages = ChatMessage::forUser(Auth::id())
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'messages' => $messages
        ]);
    }
}
