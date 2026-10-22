<?php

use App\Http\Controllers\Integration\IntegrationController;
use App\Http\Controllers\Integration\N8nApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Integration Hub Routes
|--------------------------------------------------------------------------
|
| Routes for the Integration Hub module.
| Includes both web routes (admin dashboard) and API routes (n8n endpoints).
|
*/

// ============================================================================
// WEB ROUTES - Admin Dashboard (Protected by auth middleware)
// ============================================================================

Route::prefix('integration')->name('integration.')->middleware(['XSS', 'revalidate'])->group(function () {

    // Dashboard
    Route::get('/', [IntegrationController::class, 'index'])->name('index');
    Route::get('/stats', [IntegrationController::class, 'statsApi'])->name('stats.api');

    // API Clients Management
    Route::get('/clients', [IntegrationController::class, 'clients'])->name('clients');
    Route::get('/clients/create', [IntegrationController::class, 'createClient'])->name('clients.create');
    Route::post('/clients', [IntegrationController::class, 'storeClient'])->name('clients.store');
    Route::get('/clients/{client}', [IntegrationController::class, 'showClient'])->name('clients.show');
    Route::get('/clients/{client}/edit', [IntegrationController::class, 'editClient'])->name('clients.edit');
    Route::put('/clients/{client}', [IntegrationController::class, 'updateClient'])->name('clients.update');
    Route::post('/clients/{client}/revoke', [IntegrationController::class, 'revokeClient'])->name('clients.revoke');
    Route::post('/clients/{client}/activate', [IntegrationController::class, 'activateClient'])->name('clients.activate');
    Route::delete('/clients/{client}', [IntegrationController::class, 'destroyClient'])->name('clients.destroy');

    // API Logs (Live Monitor)
    Route::get('/logs', [IntegrationController::class, 'logs'])->name('logs');
    Route::get('/logs/{log}', [IntegrationController::class, 'showLog'])->name('logs.show');

    // Documentation
    Route::get('/docs', [IntegrationController::class, 'docs'])->name('docs');
});

// ============================================================================
// API ROUTES - n8n Endpoints (Protected by custom middleware)
// ============================================================================

Route::prefix('api/n8n')->middleware(['log.api.activity', 'api.rate.limit'])->group(function () {

    // Health Check (no ability required)
    Route::get('/health', [N8nApiController::class, 'health'])->name('api.n8n.health');

    // Webhook receiver (generic endpoint for n8n workflows)
    Route::post('/webhook', [N8nApiController::class, 'webhook'])
        ->middleware('api.ability:*')
        ->name('api.n8n.webhook');

    // ========== USERS ==========
    Route::prefix('users')->group(function () {
        Route::get('/', [N8nApiController::class, 'listUsers'])
            ->middleware('api.ability:users:read')
            ->name('api.n8n.users.index');

        Route::get('/{id}', [N8nApiController::class, 'getUser'])
            ->middleware('api.ability:users:read')
            ->name('api.n8n.users.show');

        Route::post('/', [N8nApiController::class, 'createUser'])
            ->middleware('api.ability:users:create')
            ->name('api.n8n.users.store');

        Route::put('/{id}', [N8nApiController::class, 'updateUser'])
            ->middleware('api.ability:users:update')
            ->name('api.n8n.users.update');

        Route::delete('/{id}', [N8nApiController::class, 'deleteUser'])
            ->middleware('api.ability:users:delete')
            ->name('api.n8n.users.destroy');
    });

    // ========== CUSTOMERS ==========
    Route::prefix('customers')->group(function () {
        Route::get('/', [N8nApiController::class, 'listCustomers'])
            ->middleware('api.ability:customers:read')
            ->name('api.n8n.customers.index');

        Route::get('/{id}', [N8nApiController::class, 'getCustomer'])
            ->middleware('api.ability:customers:read')
            ->name('api.n8n.customers.show');

        Route::post('/', [N8nApiController::class, 'createCustomer'])
            ->middleware('api.ability:customers:create')
            ->name('api.n8n.customers.store');

        Route::put('/{id}', [N8nApiController::class, 'updateCustomer'])
            ->middleware('api.ability:customers:update')
            ->name('api.n8n.customers.update');

        Route::delete('/{id}', [N8nApiController::class, 'deleteCustomer'])
            ->middleware('api.ability:customers:delete')
            ->name('api.n8n.customers.destroy');
    });

    // ========== INVOICES ==========
    Route::prefix('invoices')->group(function () {
        Route::get('/', [N8nApiController::class, 'listInvoices'])
            ->middleware('api.ability:invoices:read')
            ->name('api.n8n.invoices.index');

        Route::get('/{id}', [N8nApiController::class, 'getInvoice'])
            ->middleware('api.ability:invoices:read')
            ->name('api.n8n.invoices.show');
    });

    // ========== PRODUCTS ==========
    Route::prefix('products')->group(function () {
        Route::get('/', [N8nApiController::class, 'listProducts'])
            ->middleware('api.ability:products:read')
            ->name('api.n8n.products.index');

        Route::get('/{id}', [N8nApiController::class, 'getProduct'])
            ->middleware('api.ability:products:read')
            ->name('api.n8n.products.show');

        Route::post('/', [N8nApiController::class, 'createProduct'])
            ->middleware('api.ability:products:create')
            ->name('api.n8n.products.store');
    });

    // ========== LEADS (CRM) ==========
    Route::prefix('leads')->group(function () {
        Route::get('/', [N8nApiController::class, 'listLeads'])
            ->middleware('api.ability:leads:read')
            ->name('api.n8n.leads.index');

        Route::post('/', [N8nApiController::class, 'createLead'])
            ->middleware('api.ability:leads:create')
            ->name('api.n8n.leads.store');
    });

    // ========== PROJECTS ==========
    Route::prefix('projects')->group(function () {
        Route::get('/', [N8nApiController::class, 'listProjects'])
            ->middleware('api.ability:projects:read')
            ->name('api.n8n.projects.index');

        Route::post('/', [N8nApiController::class, 'createProject'])
            ->middleware('api.ability:projects:create')
            ->name('api.n8n.projects.store');
    });

    // ========== EMPLOYEES ==========
    Route::prefix('employees')->group(function () {
        Route::get('/', [N8nApiController::class, 'listEmployees'])
            ->middleware('api.ability:employees:read')
            ->name('api.n8n.employees.index');

        Route::get('/{id}', [N8nApiController::class, 'getEmployee'])
            ->middleware('api.ability:employees:read')
            ->name('api.n8n.employees.show');
    });

    // ========== TASKS ==========
    Route::prefix('tasks')->group(function () {
        // Task Stages
        Route::get('/stages', [N8nApiController::class, 'listTaskStages'])
            ->middleware('api.ability:tasks:read')
            ->name('api.n8n.tasks.stages');

        // Task Statistics
        Route::get('/statistics', [N8nApiController::class, 'getTaskStatistics'])
            ->middleware('api.ability:tasks:read')
            ->name('api.n8n.tasks.statistics');

        // Bulk Create Tasks
        Route::post('/bulk', [N8nApiController::class, 'bulkCreateTasks'])
            ->middleware('api.ability:tasks:create')
            ->name('api.n8n.tasks.bulk');

        // Task CRUD
        Route::get('/', [N8nApiController::class, 'listTasks'])
            ->middleware('api.ability:tasks:read')
            ->name('api.n8n.tasks.index');

        Route::get('/{id}', [N8nApiController::class, 'getTask'])
            ->middleware('api.ability:tasks:read')
            ->name('api.n8n.tasks.show');

        Route::post('/', [N8nApiController::class, 'createTask'])
            ->middleware('api.ability:tasks:create')
            ->name('api.n8n.tasks.store');

        Route::put('/{id}', [N8nApiController::class, 'updateTask'])
            ->middleware('api.ability:tasks:update')
            ->name('api.n8n.tasks.update');

        Route::delete('/{id}', [N8nApiController::class, 'deleteTask'])
            ->middleware('api.ability:tasks:delete')
            ->name('api.n8n.tasks.destroy');

        // Task Stage Update (Move Task)
        Route::patch('/{id}/stage', [N8nApiController::class, 'updateTaskStage'])
            ->middleware('api.ability:tasks:update')
            ->name('api.n8n.tasks.stage');

        // Task Assignment
        Route::put('/{id}/assign', [N8nApiController::class, 'assignTaskUsers'])
            ->middleware('api.ability:tasks:update')
            ->name('api.n8n.tasks.assign');

        // Task Files
        Route::get('/{id}/files', [N8nApiController::class, 'listTaskFiles'])
            ->middleware('api.ability:tasks:read')
            ->name('api.n8n.tasks.files.index');

        Route::post('/{id}/files', [N8nApiController::class, 'addTaskFile'])
            ->middleware('api.ability:tasks:update')
            ->name('api.n8n.tasks.files.store');

        Route::delete('/{taskId}/files/{fileId}', [N8nApiController::class, 'deleteTaskFile'])
            ->middleware('api.ability:tasks:delete')
            ->name('api.n8n.tasks.files.destroy');

        // Task Comments
        Route::get('/{id}/comments', [N8nApiController::class, 'listTaskComments'])
            ->middleware('api.ability:tasks:read')
            ->name('api.n8n.tasks.comments.index');

        Route::post('/{id}/comments', [N8nApiController::class, 'addTaskComment'])
            ->middleware('api.ability:tasks:update')
            ->name('api.n8n.tasks.comments.store');

        Route::delete('/{taskId}/comments/{commentId}', [N8nApiController::class, 'deleteTaskComment'])
            ->middleware('api.ability:tasks:delete')
            ->name('api.n8n.tasks.comments.destroy');

        // Task Checklists
        Route::get('/{id}/checklists', [N8nApiController::class, 'listTaskChecklists'])
            ->middleware('api.ability:tasks:read')
            ->name('api.n8n.tasks.checklists.index');

        Route::post('/{id}/checklists', [N8nApiController::class, 'addTaskChecklist'])
            ->middleware('api.ability:tasks:update')
            ->name('api.n8n.tasks.checklists.store');

        Route::put('/{taskId}/checklists/{checklistId}', [N8nApiController::class, 'updateTaskChecklist'])
            ->middleware('api.ability:tasks:update')
            ->name('api.n8n.tasks.checklists.update');

        Route::delete('/{taskId}/checklists/{checklistId}', [N8nApiController::class, 'deleteTaskChecklist'])
            ->middleware('api.ability:tasks:delete')
            ->name('api.n8n.tasks.checklists.destroy');
    });
});
