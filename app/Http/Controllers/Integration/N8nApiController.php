<?php

namespace App\Http\Controllers\Integration;

use App\Http\Controllers\Controller;
use App\Models\ApiClient;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Deal;
use App\Models\ProductService;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectUser;
use App\Models\TaskStage;
use App\Models\TaskFile;
use App\Models\TaskComment;
use App\Models\TaskChecklist;
use App\Models\ActivityLog;
use App\Models\Utility;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;

/**
 * N8nApiController
 * 
 * REST API endpoints for n8n integration.
 * All routes are protected by LogApiActivity middleware for authentication and logging.
 * Each endpoint requires specific abilities (scopes).
 */
class N8nApiController extends Controller
{
    /**
     * Get the authenticated API client from request.
     */
    protected function getClient(Request $request): ?ApiClient
    {
        return $request->attributes->get('api_client');
    }

    /**
     * Get the owner ID (company) for the API client.
     */
    protected function getOwnerId(Request $request): int
    {
        $client = $this->getClient($request);
        return $client ? $client->created_by : 0;
    }

    /**
     * Standard success response.
     */
    protected function success(mixed $data = null, string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    /**
     * Standard error response.
     */
    protected function error(string $message, int $code = 400, mixed $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }

    // ==================== HEALTH CHECK ====================

    /**
     * API Health check endpoint.
     * GET /api/n8n/health
     */
    public function health(Request $request): JsonResponse
    {
        $client = $this->getClient($request);

        return $this->success([
            'status' => 'healthy',
            'client' => $client->name ?? 'Unknown',
            'abilities' => $client->abilities ?? [],
            'timestamp' => now()->toIso8601String(),
        ], 'API is operational');
    }

    // ==================== USERS ====================

    /**
     * List all users.
     * GET /api/n8n/users
     * Requires: users:read
     */
    public function listUsers(Request $request): JsonResponse
    {
        $ownerId = $this->getOwnerId($request);

        $users = User::where('created_by', $ownerId)
            ->select('id', 'name', 'email', 'type', 'is_active', 'created_at')
            ->paginate($request->get('per_page', 15));

        return $this->success($users);
    }

    /**
     * Get a specific user.
     * GET /api/n8n/users/{id}
     * Requires: users:read
     */
    public function getUser(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->getOwnerId($request);

        $user = User::where('created_by', $ownerId)
            ->where('id', $id)
            ->first();

        if (!$user) {
            return $this->error('User not found', 404);
        }

        return $this->success($user);
    }

    /**
     * Create a new user.
     * POST /api/n8n/users
     * Requires: users:create
     */
    public function createUser(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'type' => 'required|string|in:employee,client',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $ownerId = $this->getOwnerId($request);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'type' => $request->type,
            'created_by' => $ownerId,
            'lang' => 'en',
        ]);

        return $this->success($user, 'User created successfully', 201);
    }

    /**
     * Update a user.
     * PUT /api/n8n/users/{id}
     * Requires: users:update
     */
    public function updateUser(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->getOwnerId($request);

        $user = User::where('created_by', $ownerId)
            ->where('id', $id)
            ->first();

        if (!$user) {
            return $this->error('User not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $user->update($request->only(['name', 'email', 'is_active']));

        return $this->success($user, 'User updated successfully');
    }

    /**
     * Delete a user.
     * DELETE /api/n8n/users/{id}
     * Requires: users:delete
     */
    public function deleteUser(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->getOwnerId($request);

        $user = User::where('created_by', $ownerId)
            ->where('id', $id)
            ->first();

        if (!$user) {
            return $this->error('User not found', 404);
        }

        $user->delete();

        return $this->success(null, 'User deleted successfully');
    }

    // ==================== CUSTOMERS ====================

    /**
     * List all customers.
     * GET /api/n8n/customers
     * Requires: customers:read
     */
    public function listCustomers(Request $request): JsonResponse
    {
        $ownerId = $this->getOwnerId($request);

        $customers = Customer::where('created_by', $ownerId)
            ->paginate($request->get('per_page', 15));

        return $this->success($customers);
    }

    /**
     * Get a specific customer.
     * GET /api/n8n/customers/{id}
     * Requires: customers:read
     */
    public function getCustomer(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->getOwnerId($request);

        $customer = Customer::where('created_by', $ownerId)
            ->where('id', $id)
            ->first();

        if (!$customer) {
            return $this->error('Customer not found', 404);
        }

        return $this->success($customer);
    }

    /**
     * Create a new customer.
     * POST /api/n8n/customers
     * Requires: customers:create
     */
    public function createCustomer(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:customers,email',
            'contact' => 'nullable|string',
            'billing_name' => 'nullable|string',
            'billing_address' => 'nullable|string',
            'billing_city' => 'nullable|string',
            'billing_country' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $ownerId = $this->getOwnerId($request);

        // Generate customer ID
        $lastCustomer = Customer::where('created_by', $ownerId)->orderBy('id', 'desc')->first();
        $customerId = $lastCustomer ? $lastCustomer->customer_id + 1 : 1;

        $customer = Customer::create([
            'customer_id' => $customerId,
            'name' => $request->name,
            'email' => $request->email,
            'contact' => $request->contact,
            'billing_name' => $request->billing_name,
            'billing_address' => $request->billing_address,
            'billing_city' => $request->billing_city,
            'billing_country' => $request->billing_country,
            'created_by' => $ownerId,
        ]);

        return $this->success($customer, 'Customer created successfully', 201);
    }

    /**
     * Update a customer.
     * PUT /api/n8n/customers/{id}
     * Requires: customers:update
     */
    public function updateCustomer(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->getOwnerId($request);

        $customer = Customer::where('created_by', $ownerId)
            ->where('id', $id)
            ->first();

        if (!$customer) {
            return $this->error('Customer not found', 404);
        }

        $customer->update($request->only([
            'name',
            'email',
            'contact',
            'billing_name',
            'billing_address',
            'billing_city',
            'billing_country',
            'shipping_name',
            'shipping_address',
            'shipping_city',
            'shipping_country',
        ]));

        return $this->success($customer, 'Customer updated successfully');
    }

    /**
     * Delete a customer.
     * DELETE /api/n8n/customers/{id}
     * Requires: customers:delete
     */
    public function deleteCustomer(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->getOwnerId($request);

        $customer = Customer::where('created_by', $ownerId)
            ->where('id', $id)
            ->first();

        if (!$customer) {
            return $this->error('Customer not found', 404);
        }

        $customer->delete();

        return $this->success(null, 'Customer deleted successfully');
    }

    // ==================== INVOICES ====================

    /**
     * List all invoices.
     * GET /api/n8n/invoices
     * Requires: invoices:read
     */
    public function listInvoices(Request $request): JsonResponse
    {
        $ownerId = $this->getOwnerId($request);

        $invoices = Invoice::where('created_by', $ownerId)
            ->with(['customer:id,name,email', 'items'])
            ->paginate($request->get('per_page', 15));

        return $this->success($invoices);
    }

    /**
     * Get a specific invoice.
     * GET /api/n8n/invoices/{id}
     * Requires: invoices:read
     */
    public function getInvoice(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->getOwnerId($request);

        $invoice = Invoice::where('created_by', $ownerId)
            ->with(['customer', 'items', 'payments'])
            ->where('id', $id)
            ->first();

        if (!$invoice) {
            return $this->error('Invoice not found', 404);
        }

        // Add calculated totals
        $invoice->subtotal = $invoice->getSubTotal();
        $invoice->total = $invoice->getTotal();
        $invoice->due = $invoice->getDue();

        return $this->success($invoice);
    }

    // ==================== PRODUCTS ====================

    /**
     * List all products.
     * GET /api/n8n/products
     * Requires: products:read
     */
    public function listProducts(Request $request): JsonResponse
    {
        $ownerId = $this->getOwnerId($request);

        $products = ProductService::where('created_by', $ownerId)
            ->with(['category', 'unit'])
            ->paginate($request->get('per_page', 15));

        return $this->success($products);
    }

    /**
     * Get a specific product.
     * GET /api/n8n/products/{id}
     * Requires: products:read
     */
    public function getProduct(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->getOwnerId($request);

        $product = ProductService::where('created_by', $ownerId)
            ->with(['category', 'unit'])
            ->where('id', $id)
            ->first();

        if (!$product) {
            return $this->error('Product not found', 404);
        }

        return $this->success($product);
    }

    /**
     * Create a new product.
     * POST /api/n8n/products
     * Requires: products:create
     */
    public function createProduct(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|unique:product_services,sku',
            'sale_price' => 'required|numeric|min:0',
            'purchase_price' => 'nullable|numeric|min:0',
            'category_id' => 'nullable|exists:product_service_categories,id',
            'unit_id' => 'nullable|exists:product_service_units,id',
            'type' => 'required|in:product,service',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $ownerId = $this->getOwnerId($request);

        $product = ProductService::create([
            'name' => $request->name,
            'sku' => $request->sku,
            'sale_price' => $request->sale_price,
            'purchase_price' => $request->purchase_price ?? 0,
            'category_id' => $request->category_id,
            'unit_id' => $request->unit_id,
            'type' => $request->type,
            'created_by' => $ownerId,
        ]);

        return $this->success($product, 'Product created successfully', 201);
    }

    // ==================== LEADS (CRM) ====================

    /**
     * List all leads.
     * GET /api/n8n/leads
     * Requires: leads:read
     */
    public function listLeads(Request $request): JsonResponse
    {
        $ownerId = $this->getOwnerId($request);

        $leads = Lead::where('created_by', $ownerId)
            ->with(['stage', 'pipeline'])
            ->paginate($request->get('per_page', 15));

        return $this->success($leads);
    }

    /**
     * Create a new lead.
     * POST /api/n8n/leads
     * Requires: leads:create
     */
    public function createLead(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'nullable|string',
            'subject' => 'nullable|string',
            'pipeline_id' => 'nullable|exists:pipelines,id',
            'stage_id' => 'nullable|exists:lead_stages,id',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $ownerId = $this->getOwnerId($request);

        $lead = Lead::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'subject' => $request->subject,
            'pipeline_id' => $request->pipeline_id,
            'stage_id' => $request->stage_id,
            'created_by' => $ownerId,
        ]);

        return $this->success($lead, 'Lead created successfully', 201);
    }

    // ==================== PROJECTS ====================

    /**
     * List all projects.
     * GET /api/n8n/projects
     * Requires: projects:read
     */
    public function listProjects(Request $request): JsonResponse
    {
        $ownerId = $this->getOwnerId($request);

        $projects = Project::where('created_by', $ownerId)
            ->with(['client:id,name'])
            ->paginate($request->get('per_page', 15));

        return $this->success($projects);
    }

    /**
     * Create a new project.
     * POST /api/n8n/projects
     * Requires: projects:create
     */
    public function createProject(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'project_name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'client_id' => 'nullable|exists:users,id',
            'budget' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'status' => 'nullable|in:in_progress,on_hold,complete,canceled',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $ownerId = $this->getOwnerId($request);

        $project = Project::create([
            'project_name' => $request->project_name,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'client_id' => $request->client_id,
            'budget' => $request->budget ?? 0,
            'description' => $request->description,
            'status' => $request->status ?? 'in_progress',
            'created_by' => $ownerId,
        ]);

        return $this->success($project, 'Project created successfully', 201);
    }

    // ==================== EMPLOYEES ====================

    /**
     * List all employees.
     * GET /api/n8n/employees
     * Requires: employees:read
     */
    public function listEmployees(Request $request): JsonResponse
    {
        $ownerId = $this->getOwnerId($request);

        $employees = Employee::where('created_by', $ownerId)
            ->with(['branch', 'department', 'designation'])
            ->paginate($request->get('per_page', 15));

        return $this->success($employees);
    }

    /**
     * Get a specific employee.
     * GET /api/n8n/employees/{id}
     * Requires: employees:read
     */
    public function getEmployee(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->getOwnerId($request);

        $employee = Employee::where('created_by', $ownerId)
            ->with(['branch', 'department', 'designation', 'user'])
            ->where('id', $id)
            ->first();

        if (!$employee) {
            return $this->error('Employee not found', 404);
        }

        return $this->success($employee);
    }

    // ==================== WEBHOOKS TEST ====================

    /**
     * Webhook receiver endpoint for n8n.
     * POST /api/n8n/webhook
     * Allows n8n to send arbitrary data to the ERP.
     */
    public function webhook(Request $request): JsonResponse
    {
        // Log the entire payload
        $payload = $request->all();

        // Process based on event type if provided
        $eventType = $request->get('event_type', 'generic');

        return $this->success([
            'received' => true,
            'event_type' => $eventType,
            'payload_size' => strlen(json_encode($payload)),
            'processed_at' => now()->toIso8601String(),
        ], 'Webhook received successfully');
    }

    // ==================== TASKS ====================

    /**
     * List all task stages.
     * GET /api/n8n/tasks/stages
     * Requires: tasks:read
     */
    public function listTaskStages(Request $request): JsonResponse
    {
        $ownerId = $this->getOwnerId($request);

        $stages = TaskStage::where('created_by', $ownerId)
            ->orderBy('order', 'ASC')
            ->get();

        return $this->success($stages);
    }

    /**
     * List all tasks with optional filters.
     * GET /api/n8n/tasks
     * Requires: tasks:read
     * 
     * Query params:
     * - project_id: Filter by project
     * - stage_id: Filter by stage
     * - priority: Filter by priority (critical, high, medium, low)
     * - assign_to: Filter by assigned user ID
     * - is_complete: Filter by completion status (0 or 1)
     */
    public function listTasks(Request $request): JsonResponse
    {
        $ownerId = $this->getOwnerId($request);

        $query = ProjectTask::where('created_by', $ownerId)
            ->with(['project:id,project_name', 'stage:id,name,color', 'milestone:id,title']);

        // Apply filters
        if ($request->has('project_id')) {
            $query->where('project_id', $request->project_id);
        }
        if ($request->has('stage_id')) {
            $query->where('stage_id', $request->stage_id);
        }
        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }
        if ($request->has('assign_to')) {
            $query->where('assign_to', 'LIKE', '%' . $request->assign_to . '%');
        }
        if ($request->has('is_complete')) {
            $query->where('is_complete', $request->is_complete);
        }

        $tasks = $query->orderBy('created_at', 'DESC')
            ->paginate($request->get('per_page', 15));

        return $this->success($tasks);
    }

    /**
     * Get a specific task with full details.
     * GET /api/n8n/tasks/{id}
     * Requires: tasks:read
     */
    public function getTask(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->getOwnerId($request);

        $task = ProjectTask::where('created_by', $ownerId)
            ->with([
                'project:id,project_name,status',
                'stage:id,name,color',
                'milestone:id,title',
                'taskFiles',
                'comments.user:id,name,avatar',
                'checklist'
            ])
            ->where('id', $id)
            ->first();

        if (!$task) {
            return $this->error('Task not found', 404);
        }

        // Add assigned users info
        $assignedUserIds = explode(',', $task->assign_to);
        $task->assigned_users = User::whereIn('id', $assignedUserIds)
            ->select('id', 'name', 'email', 'avatar')
            ->get();

        return $this->success($task);
    }

    /**
     * Create a new task.
     * POST /api/n8n/tasks
     * Requires: tasks:create
     */
    public function createTask(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'project_id' => 'required|exists:projects,id',
            'stage_id' => 'required|exists:task_stages,id',
            'priority' => 'required|in:critical,high,medium,low',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'estimated_hrs' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'assign_to' => 'nullable|string', // Comma-separated user IDs
            'milestone_id' => 'nullable|exists:milestones,id',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $ownerId = $this->getOwnerId($request);

        // Verify project belongs to owner
        $project = Project::where('id', $request->project_id)
            ->where('created_by', $ownerId)
            ->first();

        if (!$project) {
            return $this->error('Project not found or access denied', 404);
        }

        // Get priority color
        $priorityColors = [
            'critical' => 'danger',
            'high' => 'warning',
            'medium' => 'primary',
            'low' => 'info',
        ];

        $task = ProjectTask::create([
            'name' => $request->name,
            'description' => $request->description,
            'project_id' => $request->project_id,
            'stage_id' => $request->stage_id,
            'priority' => $request->priority,
            'priority_color' => $priorityColors[$request->priority] ?? 'primary',
            'start_date' => date('Y-m-d H:i:s', strtotime($request->start_date)),
            'end_date' => $request->end_date ? date('Y-m-d H:i:s', strtotime($request->end_date)) : null,
            'estimated_hrs' => $request->estimated_hrs ?? 0,
            'assign_to' => $request->assign_to,
            'milestone_id' => $request->milestone_id,
            'created_by' => $ownerId,
            'order' => ProjectTask::where('project_id', $request->project_id)->max('order') + 1,
        ]);

        // Log activity
        ActivityLog::create([
            'user_id' => $ownerId,
            'project_id' => $request->project_id,
            'task_id' => $task->id,
            'log_type' => 'Create Task',
            'remark' => json_encode(['title' => $task->name, 'source' => 'API']),
        ]);

        return $this->success($task, 'Task created successfully', 201);
    }

    /**
     * Update a task.
     * PUT /api/n8n/tasks/{id}
     * Requires: tasks:update
     */
    public function updateTask(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->getOwnerId($request);

        $task = ProjectTask::where('created_by', $ownerId)
            ->where('id', $id)
            ->first();

        if (!$task) {
            return $this->error('Task not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'stage_id' => 'sometimes|exists:task_stages,id',
            'priority' => 'sometimes|in:critical,high,medium,low',
            'start_date' => 'sometimes|date',
            'end_date' => 'nullable|date',
            'estimated_hrs' => 'sometimes|numeric|min:0',
            'description' => 'nullable|string',
            'assign_to' => 'nullable|string',
            'milestone_id' => 'nullable|exists:milestones,id',
            'progress' => 'sometimes|integer|min:0|max:100',
            'is_complete' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $updateData = $request->only([
            'name',
            'description',
            'stage_id',
            'priority',
            'estimated_hrs',
            'assign_to',
            'milestone_id',
            'progress'
        ]);

        // Handle priority color
        if ($request->has('priority')) {
            $priorityColors = [
                'critical' => 'danger',
                'high' => 'warning',
                'medium' => 'primary',
                'low' => 'info',
            ];
            $updateData['priority_color'] = $priorityColors[$request->priority] ?? 'primary';
        }

        // Handle dates
        if ($request->has('start_date')) {
            $updateData['start_date'] = date('Y-m-d H:i:s', strtotime($request->start_date));
        }
        if ($request->has('end_date')) {
            $updateData['end_date'] = $request->end_date ? date('Y-m-d H:i:s', strtotime($request->end_date)) : null;
        }

        // Handle stage change - check if moving to complete stage
        if ($request->has('stage_id') && $task->stage_id != $request->stage_id) {
            $lastStage = TaskStage::where('created_by', $ownerId)
                ->orderBy('order', 'DESC')
                ->first();

            if ($lastStage && $request->stage_id == $lastStage->id) {
                $updateData['is_complete'] = 1;
                $updateData['marked_at'] = date('Y-m-d');
            } else {
                $updateData['is_complete'] = 0;
                $updateData['marked_at'] = null;
            }
        }

        // Handle explicit completion
        if ($request->has('is_complete')) {
            $updateData['is_complete'] = $request->is_complete ? 1 : 0;
            $updateData['marked_at'] = $request->is_complete ? date('Y-m-d') : null;
        }

        $task->update($updateData);

        // Log activity
        ActivityLog::create([
            'user_id' => $ownerId,
            'project_id' => $task->project_id,
            'task_id' => $task->id,
            'log_type' => 'Update Task',
            'remark' => json_encode(['title' => $task->name, 'source' => 'API']),
        ]);

        return $this->success($task->fresh(), 'Task updated successfully');
    }

    /**
     * Delete a task.
     * DELETE /api/n8n/tasks/{id}
     * Requires: tasks:delete
     */
    public function deleteTask(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->getOwnerId($request);

        $task = ProjectTask::where('created_by', $ownerId)
            ->where('id', $id)
            ->first();

        if (!$task) {
            return $this->error('Task not found', 404);
        }

        // Use the model's delete method which handles related data
        ProjectTask::deleteTask([$id]);

        return $this->success(null, 'Task deleted successfully');
    }

    /**
     * Update task stage (move task).
     * PATCH /api/n8n/tasks/{id}/stage
     * Requires: tasks:update
     */
    public function updateTaskStage(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'stage_id' => 'required|exists:task_stages,id',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $ownerId = $this->getOwnerId($request);

        $task = ProjectTask::where('created_by', $ownerId)
            ->where('id', $id)
            ->first();

        if (!$task) {
            return $this->error('Task not found', 404);
        }

        $lastStage = TaskStage::where('created_by', $ownerId)
            ->orderBy('order', 'DESC')
            ->first();

        $task->stage_id = $request->stage_id;

        if ($lastStage && $request->stage_id == $lastStage->id) {
            $task->is_complete = 1;
            $task->marked_at = date('Y-m-d');
        } else {
            $task->is_complete = 0;
            $task->marked_at = null;
        }

        $task->save();

        // Log activity
        ActivityLog::create([
            'user_id' => $ownerId,
            'project_id' => $task->project_id,
            'task_id' => $task->id,
            'log_type' => 'Move Task',
            'remark' => json_encode(['title' => $task->name, 'stage' => $request->stage_id, 'source' => 'API']),
        ]);

        return $this->success($task->fresh(['stage']), 'Task stage updated successfully');
    }

    // ==================== TASK FILES ====================

    /**
     * List files for a task.
     * GET /api/n8n/tasks/{id}/files
     * Requires: tasks:read
     */
    public function listTaskFiles(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->getOwnerId($request);

        $task = ProjectTask::where('created_by', $ownerId)
            ->where('id', $id)
            ->first();

        if (!$task) {
            return $this->error('Task not found', 404);
        }

        $files = TaskFile::where('task_id', $id)
            ->with('user:id,name')
            ->get();

        return $this->success($files);
    }

    /**
     * Add a file to a task.
     * POST /api/n8n/tasks/{id}/files
     * Requires: tasks:update
     * 
     * Accepts multipart/form-data with 'file' field
     * OR JSON with 'file_base64', 'file_name', 'file_extension'
     */
    public function addTaskFile(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->getOwnerId($request);

        $task = ProjectTask::where('created_by', $ownerId)
            ->where('id', $id)
            ->first();

        if (!$task) {
            return $this->error('Task not found', 404);
        }

        // Handle file upload (multipart or base64)
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $fileSize = $file->getSize();

            $path = $file->storeAs('task_files/' . $task->project_id, $fileName, 'public');
        } elseif ($request->has('file_base64')) {
            $validator = Validator::make($request->all(), [
                'file_base64' => 'required|string',
                'file_name' => 'required|string',
            ]);

            if ($validator->fails()) {
                return $this->error('Validation failed', 422, $validator->errors());
            }

            $fileData = base64_decode($request->file_base64);
            $fileName = time() . '_' . $request->file_name;
            $extension = pathinfo($request->file_name, PATHINFO_EXTENSION);
            $fileSize = strlen($fileData);

            $path = 'task_files/' . $task->project_id . '/' . $fileName;
            Storage::disk('public')->put($path, $fileData);
        } else {
            return $this->error('No file provided. Use multipart file upload or file_base64 with file_name.', 400);
        }

        $taskFile = TaskFile::create([
            'file' => $path,
            'name' => $fileName,
            'extension' => $extension,
            'file_size' => $fileSize,
            'task_id' => $task->id,
            'user_type' => 'company',
            'created_by' => $ownerId,
        ]);

        // Log activity
        ActivityLog::create([
            'user_id' => $ownerId,
            'project_id' => $task->project_id,
            'task_id' => $task->id,
            'log_type' => 'Upload File',
            'remark' => json_encode(['file_name' => $fileName, 'source' => 'API']),
        ]);

        return $this->success($taskFile, 'File uploaded successfully', 201);
    }

    /**
     * Delete a file from a task.
     * DELETE /api/n8n/tasks/{taskId}/files/{fileId}
     * Requires: tasks:delete
     */
    public function deleteTaskFile(Request $request, int $taskId, int $fileId): JsonResponse
    {
        $ownerId = $this->getOwnerId($request);

        $task = ProjectTask::where('created_by', $ownerId)
            ->where('id', $taskId)
            ->first();

        if (!$task) {
            return $this->error('Task not found', 404);
        }

        $file = TaskFile::where('task_id', $taskId)
            ->where('id', $fileId)
            ->first();

        if (!$file) {
            return $this->error('File not found', 404);
        }

        // Delete file from storage
        if (Storage::disk('public')->exists($file->file)) {
            Storage::disk('public')->delete($file->file);
        }

        $file->delete();

        return $this->success(null, 'File deleted successfully');
    }

    // ==================== TASK COMMENTS ====================

    /**
     * List comments for a task.
     * GET /api/n8n/tasks/{id}/comments
     * Requires: tasks:read
     */
    public function listTaskComments(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->getOwnerId($request);

        $task = ProjectTask::where('created_by', $ownerId)
            ->where('id', $id)
            ->first();

        if (!$task) {
            return $this->error('Task not found', 404);
        }

        $comments = TaskComment::where('task_id', $id)
            ->with('user:id,name,avatar')
            ->orderBy('created_at', 'DESC')
            ->get();

        return $this->success($comments);
    }

    /**
     * Add a comment to a task.
     * POST /api/n8n/tasks/{id}/comments
     * Requires: tasks:update
     */
    public function addTaskComment(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'comment' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $ownerId = $this->getOwnerId($request);

        $task = ProjectTask::where('created_by', $ownerId)
            ->where('id', $id)
            ->first();

        if (!$task) {
            return $this->error('Task not found', 404);
        }

        $comment = TaskComment::create([
            'comment' => $request->comment,
            'task_id' => $task->id,
            'user_id' => $ownerId,
            'user_type' => 'company',
            'created_by' => $ownerId,
        ]);

        // Log activity
        ActivityLog::create([
            'user_id' => $ownerId,
            'project_id' => $task->project_id,
            'task_id' => $task->id,
            'log_type' => 'Add Comment',
            'remark' => json_encode(['comment' => substr($request->comment, 0, 50), 'source' => 'API']),
        ]);

        return $this->success($comment->load('user:id,name,avatar'), 'Comment added successfully', 201);
    }

    /**
     * Delete a comment from a task.
     * DELETE /api/n8n/tasks/{taskId}/comments/{commentId}
     * Requires: tasks:delete
     */
    public function deleteTaskComment(Request $request, int $taskId, int $commentId): JsonResponse
    {
        $ownerId = $this->getOwnerId($request);

        $task = ProjectTask::where('created_by', $ownerId)
            ->where('id', $taskId)
            ->first();

        if (!$task) {
            return $this->error('Task not found', 404);
        }

        $comment = TaskComment::where('task_id', $taskId)
            ->where('id', $commentId)
            ->first();

        if (!$comment) {
            return $this->error('Comment not found', 404);
        }

        $comment->delete();

        return $this->success(null, 'Comment deleted successfully');
    }

    // ==================== TASK CHECKLISTS ====================

    /**
     * List checklists for a task.
     * GET /api/n8n/tasks/{id}/checklists
     * Requires: tasks:read
     */
    public function listTaskChecklists(Request $request, int $id): JsonResponse
    {
        $ownerId = $this->getOwnerId($request);

        $task = ProjectTask::where('created_by', $ownerId)
            ->where('id', $id)
            ->first();

        if (!$task) {
            return $this->error('Task not found', 404);
        }

        $checklists = TaskChecklist::where('task_id', $id)
            ->orderBy('created_at', 'ASC')
            ->get();

        return $this->success($checklists);
    }

    /**
     * Add a checklist item to a task.
     * POST /api/n8n/tasks/{id}/checklists
     * Requires: tasks:update
     */
    public function addTaskChecklist(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'status' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $ownerId = $this->getOwnerId($request);

        $task = ProjectTask::where('created_by', $ownerId)
            ->where('id', $id)
            ->first();

        if (!$task) {
            return $this->error('Task not found', 404);
        }

        $checklist = TaskChecklist::create([
            'name' => $request->name,
            'task_id' => $task->id,
            'status' => $request->status ?? 0,
            'created_by' => $ownerId,
        ]);

        return $this->success($checklist, 'Checklist item added successfully', 201);
    }

    /**
     * Update a checklist item.
     * PUT /api/n8n/tasks/{taskId}/checklists/{checklistId}
     * Requires: tasks:update
     */
    public function updateTaskChecklist(Request $request, int $taskId, int $checklistId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'status' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $ownerId = $this->getOwnerId($request);

        $task = ProjectTask::where('created_by', $ownerId)
            ->where('id', $taskId)
            ->first();

        if (!$task) {
            return $this->error('Task not found', 404);
        }

        $checklist = TaskChecklist::where('task_id', $taskId)
            ->where('id', $checklistId)
            ->first();

        if (!$checklist) {
            return $this->error('Checklist item not found', 404);
        }

        $checklist->update($request->only(['name', 'status']));

        return $this->success($checklist->fresh(), 'Checklist item updated successfully');
    }

    /**
     * Delete a checklist item.
     * DELETE /api/n8n/tasks/{taskId}/checklists/{checklistId}
     * Requires: tasks:delete
     */
    public function deleteTaskChecklist(Request $request, int $taskId, int $checklistId): JsonResponse
    {
        $ownerId = $this->getOwnerId($request);

        $task = ProjectTask::where('created_by', $ownerId)
            ->where('id', $taskId)
            ->first();

        if (!$task) {
            return $this->error('Task not found', 404);
        }

        $checklist = TaskChecklist::where('task_id', $taskId)
            ->where('id', $checklistId)
            ->first();

        if (!$checklist) {
            return $this->error('Checklist item not found', 404);
        }

        $checklist->delete();

        return $this->success(null, 'Checklist item deleted successfully');
    }

    /**
     * Bulk create tasks.
     * POST /api/n8n/tasks/bulk
     * Requires: tasks:create
     */
    public function bulkCreateTasks(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tasks' => 'required|array|min:1|max:50',
            'tasks.*.name' => 'required|string|max:255',
            'tasks.*.project_id' => 'required|exists:projects,id',
            'tasks.*.stage_id' => 'required|exists:task_stages,id',
            'tasks.*.priority' => 'required|in:critical,high,medium,low',
            'tasks.*.start_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $ownerId = $this->getOwnerId($request);
        $createdTasks = [];
        $errors = [];

        $priorityColors = [
            'critical' => 'danger',
            'high' => 'warning',
            'medium' => 'primary',
            'low' => 'info',
        ];

        foreach ($request->tasks as $index => $taskData) {
            // Verify project belongs to owner
            $project = Project::where('id', $taskData['project_id'])
                ->where('created_by', $ownerId)
                ->first();

            if (!$project) {
                $errors[] = "Task {$index}: Project not found or access denied";
                continue;
            }

            $task = ProjectTask::create([
                'name' => $taskData['name'],
                'description' => $taskData['description'] ?? null,
                'project_id' => $taskData['project_id'],
                'stage_id' => $taskData['stage_id'],
                'priority' => $taskData['priority'],
                'priority_color' => $priorityColors[$taskData['priority']] ?? 'primary',
                'start_date' => date('Y-m-d H:i:s', strtotime($taskData['start_date'])),
                'end_date' => isset($taskData['end_date']) ? date('Y-m-d H:i:s', strtotime($taskData['end_date'])) : null,
                'estimated_hrs' => $taskData['estimated_hrs'] ?? 0,
                'assign_to' => $taskData['assign_to'] ?? null,
                'milestone_id' => $taskData['milestone_id'] ?? null,
                'created_by' => $ownerId,
                'order' => ProjectTask::where('project_id', $taskData['project_id'])->max('order') + 1,
            ]);

            $createdTasks[] = $task;
        }

        return $this->success([
            'created' => count($createdTasks),
            'tasks' => $createdTasks,
            'errors' => $errors,
        ], 'Bulk task creation completed', 201);
    }

    /**
     * Assign users to a task.
     * PUT /api/n8n/tasks/{id}/assign
     * Requires: tasks:update
     */
    public function assignTaskUsers(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $ownerId = $this->getOwnerId($request);

        $task = ProjectTask::where('created_by', $ownerId)
            ->where('id', $id)
            ->first();

        if (!$task) {
            return $this->error('Task not found', 404);
        }

        $task->assign_to = implode(',', $request->user_ids);
        $task->save();

        // Get assigned users info
        $assignedUsers = User::whereIn('id', $request->user_ids)
            ->select('id', 'name', 'email', 'avatar')
            ->get();

        // Log activity
        ActivityLog::create([
            'user_id' => $ownerId,
            'project_id' => $task->project_id,
            'task_id' => $task->id,
            'log_type' => 'Assign Task',
            'remark' => json_encode(['users' => $request->user_ids, 'source' => 'API']),
        ]);

        return $this->success([
            'task' => $task->fresh(),
            'assigned_users' => $assignedUsers,
        ], 'Users assigned successfully');
    }

    /**
     * Get task statistics.
     * GET /api/n8n/tasks/statistics
     * Requires: tasks:read
     */
    public function getTaskStatistics(Request $request): JsonResponse
    {
        $ownerId = $this->getOwnerId($request);

        $total = ProjectTask::where('created_by', $ownerId)->count();
        $completed = ProjectTask::where('created_by', $ownerId)->where('is_complete', 1)->count();
        $overdue = ProjectTask::where('created_by', $ownerId)
            ->where('is_complete', 0)
            ->whereNotNull('end_date')
            ->where('end_date', '<', now())
            ->count();

        $byPriority = ProjectTask::where('created_by', $ownerId)
            ->selectRaw('priority, COUNT(*) as count')
            ->groupBy('priority')
            ->pluck('count', 'priority');

        $byStage = ProjectTask::where('created_by', $ownerId)
            ->join('task_stages', 'project_tasks.stage_id', '=', 'task_stages.id')
            ->selectRaw('task_stages.name as stage_name, COUNT(*) as count')
            ->groupBy('task_stages.id', 'task_stages.name')
            ->pluck('count', 'stage_name');

        return $this->success([
            'total' => $total,
            'completed' => $completed,
            'pending' => $total - $completed,
            'overdue' => $overdue,
            'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0,
            'by_priority' => $byPriority,
            'by_stage' => $byStage,
        ]);
    }
}
