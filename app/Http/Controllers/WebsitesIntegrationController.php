<?php

namespace App\Http\Controllers;

use App\Services\MyCommunicationArService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WebsitesIntegrationController extends Controller
{
    private MyCommunicationArService $ar;

    public function __construct(MyCommunicationArService $ar)
    {
        $this->ar = $ar;
    }

    public function dashboard()
    {
        $kpis = $this->ar->getDashboardKpis();
        $topCustomers = $this->ar->getTopCustomers(5);
        $topProducts = $this->ar->getTopProducts(5);
        $mostEnrolled = $this->ar->getMostEnrolledCourses(5);
        $activity = $this->ar->getActivityFeed(15);
        $abandoned = $this->ar->getAbandonedOrders(24);
        return view('websites_integration.dashboard', compact(
            'kpis', 'topCustomers', 'topProducts', 'mostEnrolled', 'activity', 'abandoned'
        ));
    }

    public function chartTimeline(Request $request)
    {
        $days = max(7, min(90, (int) $request->input('days', 30)));
        return response()->json($this->ar->getOrdersTimeline($days));
    }

    public function users(Request $request)
    {
        $page = max(1, (int) $request->input('page', 1));
        $search = trim((string) $request->input('q', ''));
        $result = $this->ar->getUsers(20, $page, $search);
        return view('websites_integration.users', [
            'users' => $result['items'],
            'total' => $result['total'],
            'pages' => $result['pages'],
            'currentPage' => $page,
            'search' => $search,
        ]);
    }

    public function customerDetail(int $id)
    {
        $customer = $this->ar->getCustomerDetail($id);
        if (!$customer) {
            return response()->view('websites_integration.partials.not_found', ['type' => 'customer']);
        }
        return view('websites_integration.partials.customer_modal', compact('customer'));
    }

    public function orders(Request $request)
    {
        $page = max(1, (int) $request->input('page', 1));
        $status = trim((string) $request->input('status', ''));
        $search = trim((string) $request->input('q', ''));
        $result = $this->ar->getOrders(20, $page, $status, $search);
        $stats = $this->ar->getOrderStats();
        return view('websites_integration.orders', [
            'orders' => $result['items'],
            'total' => $result['total'],
            'pages' => $result['pages'],
            'currentPage' => $page,
            'status' => $status,
            'search' => $search,
            'stats' => $stats,
        ]);
    }

    public function orderDetail(int $id)
    {
        $order = $this->ar->getOrderDetail($id);
        $notes = $this->ar->getOrderNotes($id);
        if (!$order) {
            return response()->view('websites_integration.partials.not_found', ['type' => 'order']);
        }
        return view('websites_integration.partials.order_modal', compact('order', 'notes'));
    }

    public function updateOrderStatus(Request $request, int $id)
    {
        $request->validate([
            'status' => 'required|string|in:pending,processing,on-hold,completed,cancelled,refunded,failed',
        ]);
        $r = $this->ar->updateOrderStatus($id, $request->input('status'));
        return response()->json($r);
    }

    public function addOrderNote(Request $request, int $id)
    {
        $request->validate([
            'note' => 'required|string|max:2000',
            'customer_note' => 'sometimes|boolean',
        ]);
        $r = $this->ar->addOrderNote($id, $request->input('note'), (bool) $request->input('customer_note', false));
        return response()->json($r);
    }

    public function refundOrder(Request $request, int $id)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'sometimes|string|max:500',
        ]);
        $r = $this->ar->refundOrder($id, (float) $request->input('amount'), (string) $request->input('reason', ''));
        return response()->json($r);
    }

    public function courses()
    {
        $courses = $this->ar->getCourses(100);
        $total = $this->ar->getCourseCount();
        return view('websites_integration.courses', compact('courses', 'total'));
    }

    public function blogs(Request $request)
    {
        $page = max(1, (int) $request->input('page', 1));
        $search = trim((string) $request->input('q', ''));
        $catId = (int) $request->input('cat', 0);
        $result = $this->ar->getPosts(15, $page, $search, $catId);
        $categories = $this->ar->getCategories(20);
        return view('websites_integration.blogs', [
            'posts' => $result['items'],
            'total' => $result['total'],
            'pages' => $result['pages'],
            'currentPage' => $page,
            'search' => $search,
            'catId' => $catId,
            'categories' => $categories,
        ]);
    }

    public function comments()    { return view('websites_integration.comments'); }
    public function seo()         { return view('websites_integration.seo'); }
    public function tracking()    { return view('websites_integration.tracking'); }
    public function subdomains()  { return view('websites_integration.subdomains'); }

    // ============================================================
    // CSV exports
    // ============================================================

    public function exportOrdersCsv()
    {
        $result = $this->ar->getOrders(100, 1);
        $filename = 'orders-' . now()->format('Y-m-d-His') . '.csv';
        return response()->streamDownload(function () use ($result) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Number', 'Status', 'Customer', 'Email', 'Total', 'Currency', 'Items', 'Payment', 'Date']);
            foreach ($result['items'] as $o) {
                fputcsv($out, [
                    $o['id'], $o['number'], $o['status'], $o['customer_name'], $o['customer_email'],
                    $o['total'], $o['currency'], $o['items_count'], $o['payment_method'], $o['date_created'],
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportCustomersCsv()
    {
        $result = $this->ar->getUsers(100, 1);
        $filename = 'customers-' . now()->format('Y-m-d-His') . '.csv';
        return response()->streamDownload(function () use ($result) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Name', 'Email', 'Phone', 'Country', 'City', 'Orders', 'Total Spent', 'Status', 'Registered']);
            foreach ($result['items'] as $u) {
                fputcsv($out, [
                    $u['id'], $u['name'], $u['email'], $u['phone'], $u['country'], $u['city'],
                    $u['orders_count'], $u['total_spent'], ($u['is_paying'] ? 'paying' : 'lead'), $u['date_created'],
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
