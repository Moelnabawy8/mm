<?php

namespace App\Http\Controllers\Integration;

use App\Http\Controllers\Controller;
use App\Models\ApiClient;
use App\Models\ApiLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * IntegrationController
 * 
 * Manages the Integration Hub admin dashboard.
 * Provides UI for managing API clients, viewing logs, and monitoring activity.
 */
class IntegrationController extends Controller
{
    /**
     * Constructor - Apply XSS middleware only.
     */
    public function __construct()
    {
        $this->middleware(['XSS']);
    }

    /**
     * Display the Integration Hub dashboard.
     * Shows statistics and overview of API activity.
     */
    public function index()
    {
        // Get statistics
        $stats = [
            'total_clients' => ApiClient::count(),
            'active_clients' => ApiClient::active()->count(),
            'today_requests' => ApiLog::today()->count(),
            'today_failed' => ApiLog::today()->failed()->count(),
            'week_requests' => ApiLog::thisWeek()->count(),
            'week_failed' => ApiLog::thisWeek()->failed()->count(),
        ];

        // Get hourly stats for chart
        $hourlyStats = ApiLog::getHourlyStats();

        // Recent logs
        $recentLogs = ApiLog::with('client')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Active clients
        $clients = ApiClient::query()
            ->withCount(['logs as today_requests' => function ($query) {
                $query->whereDate('created_at', today());
            }])
            ->orderBy('last_used_at', 'desc')
            ->limit(5)
            ->get();

        return view('integration.index', compact('stats', 'hourlyStats', 'recentLogs', 'clients'));
    }

    /**
     * Display the API clients manager.
     */
    public function clients()
    {
        $clients = ApiClient::query()
            ->withCount(['logs', 'logs as failed_requests' => function ($query) {
                $query->where('response_code', '>=', 400);
            }])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $availableAbilities = ApiClient::$availableAbilities;

        return view('integration.clients.index', compact('clients', 'availableAbilities'));
    }

    /**
     * Show form to create a new API client.
     */
    public function createClient()
    {
        $availableAbilities = ApiClient::$availableAbilities;

        return view('integration.clients.create', compact('availableAbilities'));
    }

    /**
     * Store a new API client and generate token.
     */
    public function storeClient(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'abilities' => 'required|array|min:1',
            'abilities.*' => 'string',
            'allowed_ips' => 'nullable|string',
            'rate_limit' => 'required|integer|min:1|max:1000',
            'expires_at' => 'nullable|date|after:today',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Parse allowed IPs
        $allowedIps = null;
        if ($request->filled('allowed_ips')) {
            $allowedIps = array_map('trim', explode(',', $request->allowed_ips));
            $allowedIps = array_filter($allowedIps, function ($ip) {
                return filter_var($ip, FILTER_VALIDATE_IP);
            });
            $allowedIps = array_values($allowedIps);
        }

        // Generate token
        $result = ApiClient::generateToken([
            'name' => $request->name,
            'description' => $request->description,
            'abilities' => $request->abilities,
            'allowed_ips' => $allowedIps,
            'rate_limit' => $request->rate_limit,
            'created_by' => Auth::check() ? Auth::user()->creatorId() : 1,
            'expires_at' => $request->expires_at,
        ]);

        // Store plain token in session for one-time display
        session()->flash('new_api_token', $result['plain_token']);
        session()->flash('new_api_client_name', $result['client']->name);

        return redirect()->route('integration.clients.show', $result['client']->id)
            ->with('success', __('API client created successfully. Copy your token now - it won\'t be shown again!'));
    }

    /**
     * Display a specific API client.
     */
    public function showClient(ApiClient $client)
    {
        // Get client stats
        $stats = [
            'total_requests' => $client->total_requests,
            'today_requests' => $client->today_requests_count,
            'today_failed' => $client->today_failed_requests_count,
            'success_rate' => $client->success_rate,
        ];

        // Recent logs for this client
        $recentLogs = $client->logs()
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        $availableAbilities = ApiClient::$availableAbilities;

        return view('integration.clients.show', compact('client', 'stats', 'recentLogs', 'availableAbilities'));
    }

    /**
     * Show form to edit an API client.
     */
    public function editClient(ApiClient $client)
    {
        $availableAbilities = ApiClient::$availableAbilities;

        return view('integration.clients.edit', compact('client', 'availableAbilities'));
    }

    /**
     * Update an API client.
     */
    public function updateClient(Request $request, ApiClient $client)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'abilities' => 'required|array|min:1',
            'abilities.*' => 'string',
            'allowed_ips' => 'nullable|string',
            'rate_limit' => 'required|integer|min:1|max:1000',
            'is_active' => 'boolean',
            'expires_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Parse allowed IPs
        $allowedIps = null;
        if ($request->filled('allowed_ips')) {
            $allowedIps = array_map('trim', explode(',', $request->allowed_ips));
            $allowedIps = array_filter($allowedIps, function ($ip) {
                return filter_var($ip, FILTER_VALIDATE_IP);
            });
            $allowedIps = array_values($allowedIps);
        }

        $client->update([
            'name' => $request->name,
            'description' => $request->description,
            'abilities' => $request->abilities,
            'allowed_ips' => $allowedIps,
            'rate_limit' => $request->rate_limit,
            'is_active' => $request->boolean('is_active'),
            'expires_at' => $request->expires_at,
        ]);

        return redirect()->route('integration.clients.show', $client->id)
            ->with('success', __('API client updated successfully.'));
    }

    /**
     * Revoke/Deactivate an API client.
     */
    public function revokeClient(ApiClient $client)
    {
        $client->update(['is_active' => false]);

        return redirect()->back()
            ->with('success', __('API client revoked successfully.'));
    }

    /**
     * Reactivate an API client.
     */
    public function activateClient(ApiClient $client)
    {
        $client->update(['is_active' => true]);

        return redirect()->back()
            ->with('success', __('API client activated successfully.'));
    }

    /**
     * Permanently delete an API client.
     */
    public function destroyClient(ApiClient $client)
    {
        $client->delete();

        return redirect()->route('integration.clients')
            ->with('success', __('API client deleted successfully.'));
    }

    /**
     * Display the API logs (Live Monitor).
     */
    public function logs(Request $request)
    {
        $query = ApiLog::with('client');

        // Filter by client
        if ($request->filled('client_id')) {
            $query->where('api_client_id', $request->client_id);
        }

        // Filter by status
        if ($request->filled('status')) {
            switch ($request->status) {
                case 'success':
                    $query->successful();
                    break;
                case 'failed':
                    $query->failed();
                    break;
                case 'client_error':
                    $query->clientErrors();
                    break;
                case 'server_error':
                    $query->serverErrors();
                    break;
            }
        }

        // Filter by method
        if ($request->filled('method')) {
            $query->method($request->method);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Search endpoint
        if ($request->filled('search')) {
            $query->where('endpoint', 'like', '%' . $request->search . '%');
        }

        $logs = $query->orderBy('created_at', 'desc')->paginate(50);

        // Get clients for filter dropdown
        $clients = ApiClient::pluck('name', 'id');

        return view('integration.logs.index', compact('logs', 'clients'));
    }

    /**
     * Get a single log entry details (for modal).
     */
    public function showLog(ApiLog $log)
    {
        return response()->json([
            'id' => $log->id,
            'method' => $log->method,
            'endpoint' => $log->endpoint,
            'full_url' => $log->full_url,
            'headers' => $log->headers,
            'payload' => $log->payload,
            'query_params' => $log->query_params,
            'response_code' => $log->response_code,
            'response_body' => $log->response_body,
            'response_time' => $log->response_time,
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
            'error_message' => $log->error_message,
            'ability_used' => $log->ability_used,
            'ability_granted' => $log->ability_granted,
            'created_at' => $log->created_at->format('Y-m-d H:i:s'),
            'client_name' => $log->client?->name ?? 'Unknown',
        ]);
    }

    /**
     * Display API documentation page.
     */
    public function docs()
    {
        // Get all API routes
        $routes = collect(\Route::getRoutes())->filter(function ($route) {
            return str_starts_with($route->uri(), 'api/n8n') || 
                   str_starts_with($route->uri(), 'api/integration');
        })->map(function ($route) {
            return [
                'method' => implode('|', $route->methods()),
                'uri' => $route->uri(),
                'name' => $route->getName(),
                'action' => $route->getActionName(),
                'middleware' => $route->middleware(),
            ];
        })->values();

        $availableAbilities = ApiClient::$availableAbilities;
        $baseUrl = config('app.url') . '/api/n8n';

        return view('integration.docs', compact('routes', 'availableAbilities', 'baseUrl'));
    }

    /**
     * Get dashboard stats via AJAX (for live updates).
     */
    public function statsApi()
    {
        return response()->json([
            'today_requests' => ApiLog::today()->count(),
            'today_failed' => ApiLog::today()->failed()->count(),
            'hourly_stats' => ApiLog::getHourlyStats(),
        ]);
    }
}
