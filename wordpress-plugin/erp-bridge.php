<?php
/**
 * Plugin Name: ERP Bridge (LearnPress → ERP)
 * Description: Secure REST endpoints that expose LearnPress order data to the external ERP dashboard.
 * Version:     1.0.0
 * Author:      My-Communication
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * INSTALLATION
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * 1) Upload this file to the WordPress server:
 *
 *        /wp-content/mu-plugins/erp-bridge.php
 *
 *    (Create the `mu-plugins` folder if it doesn't exist. Files in `mu-plugins`
 *    are loaded automatically — no need to activate from the admin.)
 *
 * 2) Generate a long random token (e.g. `openssl rand -hex 32`).
 *
 * 3) Edit `wp-config.php` on the WordPress server and add this line ABOVE
 *    the "That's all, stop editing!" marker:
 *
 *        define('ERP_BRIDGE_TOKEN', 'PASTE_YOUR_LONG_RANDOM_TOKEN_HERE');
 *
 *    Keep `wp-config.php` out of version control — the token is a secret.
 *
 * 4) On the Laravel ERP server, edit `.env` and add the same token plus the
 *    base URL of the WordPress site:
 *
 *        WORDPRESS_LP_BRIDGE_URL=https://my-communication.com/wp-json/erp/v1
 *        WORDPRESS_LP_BRIDGE_TOKEN=PASTE_THE_SAME_TOKEN_HERE
 *
 *    Then clear Laravel's config cache:  `php artisan config:clear`
 *
 * 5) Test connectivity from the ERP server:
 *
 *        curl -H "Authorization: Bearer YOUR_TOKEN" \
 *             https://my-communication.com/wp-json/erp/v1/health
 *
 *    Expected: HTTP 200 with `{"status":"ok",...,"total_lp_orders":10408}`
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * SECURITY
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * - Token must travel over HTTPS only. The plugin refuses non-HTTPS requests
 *   unless `WP_DEBUG` is true (for local dev).
 * - Token comparison uses `hash_equals` (timing-safe).
 * - Endpoints are read-only — no mutations, no DB writes.
 * - Without `ERP_BRIDGE_TOKEN` defined in wp-config.php, the plugin no-ops.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ENDPOINTS  (all under /wp-json/erp/v1/)
 * ─────────────────────────────────────────────────────────────────────────────
 *
 *  GET /health
 *      Quick smoke test — returns total LP orders + LearnPress version.
 *
 *  GET /dashboard
 *      One-shot fetch used by the ERP dashboard. Returns:
 *        - status_counts (completed/pending/processing/cancelled/failed)
 *        - totals (today / week / month / all revenue + counts)
 *        - monthly_trend (last 6 months)
 *        - recent_orders (last 20 with student + course detail)
 *        - top_courses (10 best-selling)
 *
 *  GET /orders?page=N&per_page=N&status=X&date_from=Y-m-d&date_to=Y-m-d
 *      Paginated order list (for future drill-down screens).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 */

if (!defined('ABSPATH')) {
    exit;
}

// Bail out silently if the token is not configured. The plugin only activates
// once `define('ERP_BRIDGE_TOKEN', '...')` is added to wp-config.php.
if (!defined('ERP_BRIDGE_TOKEN') || ERP_BRIDGE_TOKEN === '' || ERP_BRIDGE_TOKEN === 'PASTE_YOUR_LONG_RANDOM_TOKEN_HERE') {
    return;
}

add_action('rest_api_init', function () {
    $auth = ['permission_callback' => 'erp_bridge_auth'];

    register_rest_route('erp/v1', '/health', array_merge([
        'methods'  => 'GET',
        'callback' => 'erp_bridge_health',
    ], $auth));

    register_rest_route('erp/v1', '/dashboard', array_merge([
        'methods'  => 'GET',
        'callback' => 'erp_bridge_dashboard',
    ], $auth));

    register_rest_route('erp/v1', '/orders', array_merge([
        'methods'  => 'GET',
        'callback' => 'erp_bridge_orders',
        'args'     => [
            'page'      => ['type' => 'integer', 'default' => 1],
            'per_page'  => ['type' => 'integer', 'default' => 50],
            'status'    => ['type' => 'string'],
            'date_from' => ['type' => 'string'],
            'date_to'   => ['type' => 'string'],
        ],
    ], $auth));

    register_rest_route('erp/v1', '/activations', array_merge([
        'methods'  => 'GET',
        'callback' => 'erp_bridge_activations',
        'args'     => [
            'period'    => ['type' => 'string', 'default' => 'this_month'],
            'date_from' => ['type' => 'string'],
            'date_to'   => ['type' => 'string'],
            'page'      => ['type' => 'integer', 'default' => 1],
            'per_page'  => ['type' => 'integer', 'default' => 200],
            'q'         => ['type' => 'string'],
        ],
    ], $auth));
});

/**
 * Bearer-token auth. Refuses non-HTTPS in production.
 */
function erp_bridge_auth(WP_REST_Request $req)
{
    // Enforce HTTPS in production
    if (!is_ssl() && !(defined('WP_DEBUG') && WP_DEBUG)) {
        return new WP_Error('https_required', 'HTTPS required', ['status' => 400]);
    }

    $auth = $req->get_header('authorization');
    $token = '';
    if ($auth && stripos($auth, 'Bearer ') === 0) {
        $token = trim(substr($auth, 7));
    } elseif ($auth) {
        $token = trim($auth);
    } else {
        // Last-resort fallback: ?token= (handy for browser testing).
        $token = (string) $req->get_param('token');
    }

    if ($token === '' || !hash_equals((string) ERP_BRIDGE_TOKEN, $token)) {
        return new WP_Error('forbidden', 'Invalid or missing token', ['status' => 403]);
    }
    return true;
}

/**
 * Smoke test.
 */
function erp_bridge_health()
{
    global $wpdb;
    $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'lp_order'");
    return [
        'status'          => 'ok',
        'time'            => current_time('mysql'),
        'total_lp_orders' => $total,
        'lp_version'      => defined('LEARNPRESS_VERSION') ? LEARNPRESS_VERSION : null,
        'wp_version'      => get_bloginfo('version'),
    ];
}

/**
 * Single endpoint the ERP dashboard hits on page load.
 * Returns everything needed for the LearnPress section in one round-trip.
 */
function erp_bridge_dashboard()
{
    global $wpdb;

    // ── Status breakdown ────────────────────────────────────────────────
    $rows = $wpdb->get_results("
        SELECT post_status, COUNT(*) AS c
        FROM {$wpdb->posts}
        WHERE post_type = 'lp_order' AND post_status NOT IN ('trash','auto-draft')
        GROUP BY post_status
    ");
    $status_counts = [];
    $total_orders  = 0;
    foreach ($rows as $r) {
        $key = preg_replace('/^lp-/', '', $r->post_status);
        $status_counts[$key] = (int) $r->c;
        $total_orders += (int) $r->c;
    }

    // ── Revenue (completed only) ────────────────────────────────────────
    $today_str  = current_time('Y-m-d');
    $week_start = date('Y-m-d', strtotime('monday this week'));
    $month_start = date('Y-m-01');

    $sumRevenue = function ($whereDate, $bind) use ($wpdb) {
        $sql = "
            SELECT COALESCE(SUM(CAST(pm.meta_value AS DECIMAL(15,2))), 0)
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_order_total'
            WHERE p.post_type = 'lp_order'
              AND p.post_status = 'lp-completed'
              AND $whereDate
        ";
        return (float) $wpdb->get_var($bind ? $wpdb->prepare($sql, $bind) : $sql);
    };
    $countOrders = function ($whereDate, $bind) use ($wpdb) {
        $sql = "
            SELECT COUNT(*) FROM {$wpdb->posts}
            WHERE post_type = 'lp_order'
              AND post_status NOT IN ('trash','auto-draft')
              AND $whereDate
        ";
        return (int) $wpdb->get_var($bind ? $wpdb->prepare($sql, $bind) : $sql);
    };

    $totals = [
        'today_revenue'  => $sumRevenue('DATE(p.post_date) = %s', [$today_str]),
        'today_count'    => $countOrders('DATE(post_date) = %s', [$today_str]),
        'week_revenue'   => $sumRevenue('p.post_date >= %s', [$week_start . ' 00:00:00']),
        'week_count'     => $countOrders('post_date >= %s', [$week_start . ' 00:00:00']),
        'month_revenue'  => $sumRevenue('p.post_date >= %s', [$month_start . ' 00:00:00']),
        'month_count'    => $countOrders('post_date >= %s', [$month_start . ' 00:00:00']),
        'all_revenue'    => $sumRevenue('1=1', []),
        'all_count'      => $total_orders,
    ];

    // ── Monthly trend (last 6 months) ───────────────────────────────────
    $six_months_ago = date('Y-m-01 00:00:00', strtotime('-5 months'));
    $monthly = $wpdb->get_results($wpdb->prepare("
        SELECT DATE_FORMAT(p.post_date, '%%Y-%%m') AS mo,
               COUNT(*) AS cnt,
               COALESCE(SUM(CAST(pm.meta_value AS DECIMAL(15,2))), 0) AS revenue
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_order_total'
        WHERE p.post_type = 'lp_order'
          AND p.post_status = 'lp-completed'
          AND p.post_date >= %s
        GROUP BY mo
        ORDER BY mo
    ", $six_months_ago));

    $monthly_map = [];
    foreach ($monthly as $m) {
        $monthly_map[$m->mo] = ['cnt' => (int) $m->cnt, 'revenue' => (float) $m->revenue];
    }
    $monthly_trend = [];
    for ($i = 5; $i >= 0; $i--) {
        $mo = date('Y-m', strtotime("-{$i} months"));
        $monthly_trend[] = [
            'month'   => date('M Y', strtotime("-{$i} months")),
            'count'   => $monthly_map[$mo]['cnt'] ?? 0,
            'revenue' => $monthly_map[$mo]['revenue'] ?? 0,
        ];
    }

    // ── Recent orders (last 20) ─────────────────────────────────────────
    $recent_rows = $wpdb->get_results("
        SELECT p.ID, p.post_date, p.post_status
        FROM {$wpdb->posts} p
        WHERE p.post_type = 'lp_order'
          AND p.post_status NOT IN ('trash','auto-draft')
        ORDER BY p.post_date DESC
        LIMIT 20
    ");
    $recent_orders = [];
    foreach ($recent_rows as $row) {
        $recent_orders[] = erp_bridge_format_order($row);
    }

    // ── Top selling courses (10) ────────────────────────────────────────
    $top = $wpdb->get_results("
        SELECT oi.order_item_name AS name,
               COUNT(*) AS sales,
               COALESCE(SUM(CAST(itm.meta_value AS DECIMAL(15,2))), 0) AS revenue
        FROM {$wpdb->prefix}learnpress_order_items oi
        JOIN {$wpdb->posts} p
            ON p.ID = oi.order_id
            AND p.post_type = 'lp_order'
            AND p.post_status = 'lp-completed'
        LEFT JOIN {$wpdb->prefix}learnpress_order_itemmeta itm
            ON itm.learnpress_order_item_id = oi.order_item_id
            AND itm.meta_key = '_total'
        GROUP BY oi.order_item_name
        ORDER BY sales DESC
        LIMIT 10
    ");
    $top_courses = array_map(function ($r) {
        return [
            'name'    => $r->name,
            'sales'   => (int) $r->sales,
            'revenue' => (float) $r->revenue,
        ];
    }, $top);

    return [
        'status_counts' => $status_counts,
        'totals'        => $totals,
        'monthly_trend' => $monthly_trend,
        'recent_orders' => $recent_orders,
        'top_courses'   => $top_courses,
        'fetched_at'    => current_time('c'),
    ];
}

/**
 * Paginated order list. Used by drill-down screens.
 */
function erp_bridge_orders(WP_REST_Request $req)
{
    global $wpdb;

    $page     = max(1, (int) $req->get_param('page'));
    $per_page = min(200, max(1, (int) $req->get_param('per_page')));
    $offset   = ($page - 1) * $per_page;

    $status    = (string) $req->get_param('status');
    $date_from = (string) $req->get_param('date_from');
    $date_to   = (string) $req->get_param('date_to');

    $where = ["p.post_type = 'lp_order'"];
    $args  = [];

    if ($status !== '') {
        $where[] = 'p.post_status = %s';
        $args[]  = 'lp-' . ltrim($status, 'lp-');
    } else {
        $where[] = "p.post_status NOT IN ('trash','auto-draft')";
    }
    if ($date_from !== '') {
        $where[] = 'p.post_date >= %s';
        $args[]  = $date_from . ' 00:00:00';
    }
    if ($date_to !== '') {
        $where[] = 'p.post_date <= %s';
        $args[]  = $date_to . ' 23:59:59';
    }
    $wsql = 'WHERE ' . implode(' AND ', $where);

    $count_sql = "SELECT COUNT(*) FROM {$wpdb->posts} p $wsql";
    $total = (int) $wpdb->get_var($args ? $wpdb->prepare($count_sql, $args) : $count_sql);

    $list_sql = "
        SELECT p.ID, p.post_date, p.post_status
        FROM {$wpdb->posts} p
        $wsql
        ORDER BY p.post_date DESC
        LIMIT %d OFFSET %d
    ";
    $args2 = array_merge($args, [$per_page, $offset]);
    $rows  = $wpdb->get_results($wpdb->prepare($list_sql, $args2));

    $orders = [];
    foreach ($rows as $row) {
        $orders[] = erp_bridge_format_order($row);
    }

    return [
        'page'        => $page,
        'per_page'    => $per_page,
        'total'       => $total,
        'total_pages' => (int) ceil($total / max(1, $per_page)),
        'orders'      => $orders,
    ];
}

/**
 * Internal: shape a single order row into the JSON contract the ERP expects.
 * Pulls postmeta + order_items + itemmeta in a few small queries each.
 */
function erp_bridge_format_order($row)
{
    global $wpdb;

    $id   = (int) $row->ID;
    $meta = get_post_meta($id);
    $g    = function ($k) use ($meta) { return isset($meta[$k][0]) ? $meta[$k][0] : null; };

    // Items
    $item_rows = $wpdb->get_results($wpdb->prepare(
        "SELECT order_item_id, order_item_name FROM {$wpdb->prefix}learnpress_order_items WHERE order_id = %d",
        $id
    ));
    $items = [];
    foreach ($item_rows as $it) {
        $item_meta = $wpdb->get_results($wpdb->prepare(
            "SELECT meta_key, meta_value FROM {$wpdb->prefix}learnpress_order_itemmeta WHERE learnpress_order_item_id = %d",
            $it->order_item_id
        ));
        $im = [];
        foreach ($item_meta as $rm) {
            $im[$rm->meta_key] = $rm->meta_value;
        }
        $items[] = [
            'name'      => $it->order_item_name,
            'course_id' => isset($im['_course_id']) ? (int) $im['_course_id'] : null,
            'quantity'  => isset($im['_quantity']) ? (int) $im['_quantity'] : 1,
            'subtotal'  => isset($im['_subtotal']) ? (float) $im['_subtotal'] : null,
            'total'     => isset($im['_total']) ? (float) $im['_total'] : null,
        ];
    }

    // User
    $user_id = (int) ($g('_user_id') ?: $g('_order_user_id'));
    $user    = $user_id ? get_userdata($user_id) : null;

    return [
        'id'                   => $id,
        'date'                 => $row->post_date,
        'status'               => preg_replace('/^lp-/', '', $row->post_status),
        'order_key'            => $g('_order_key'),
        'total'                => (float) $g('_order_total'),
        'subtotal'             => (float) $g('_order_subtotal'),
        'currency'             => $g('_order_currency'),
        'payment_method'       => $g('_payment_method'),
        'payment_method_title' => $g('_payment_method_title'),
        'user'                 => $user ? [
            'id'    => (int) $user->ID,
            'name'  => $user->display_name,
            'email' => $user->user_email,
        ] : null,
        'items' => $items,
    ];
}

/**
 * Resolves a period keyword into a [date_from, date_to] pair (Y-m-d strings)
 * in the WordPress site timezone.
 */
function erp_bridge_period_range($period)
{
    $today_ts = current_time('timestamp');
    $today    = date('Y-m-d', $today_ts);

    switch ($period) {
        case 'today':
            return [$today, $today];
        case 'yesterday':
            $y = date('Y-m-d', $today_ts - 86400);
            return [$y, $y];
        case 'this_week':
            // ISO week — Monday as start
            $dow = (int) date('N', $today_ts); // 1=Mon..7=Sun
            return [date('Y-m-d', $today_ts - ($dow - 1) * 86400), $today];
        case 'last_7d':
            return [date('Y-m-d', $today_ts - 6 * 86400), $today];
        case 'last_30d':
            return [date('Y-m-d', $today_ts - 29 * 86400), $today];
        case 'last_month':
            $start = date('Y-m-01', strtotime('first day of last month', $today_ts));
            $end   = date('Y-m-t',  strtotime('last day of last month', $today_ts));
            return [$start, $end];
        case 'this_year':
            return [date('Y-01-01', $today_ts), $today];
        case 'this_month':
        default:
            return [date('Y-m-01', $today_ts), $today];
    }
}

/**
 * GET /activations — one row per (order × course) pair for completed orders
 * in the given period. The "amount" is the per-course item total — what the
 * student actually paid for THAT course in that order.
 */
function erp_bridge_activations(WP_REST_Request $req)
{
    global $wpdb;

    $period   = (string) $req->get_param('period');
    $df       = (string) $req->get_param('date_from');
    $dt       = (string) $req->get_param('date_to');
    $q        = trim((string) $req->get_param('q'));
    $page     = max(1, (int) $req->get_param('page'));
    $per_page = min(500, max(1, (int) $req->get_param('per_page')));
    $offset   = ($page - 1) * $per_page;

    if ($df === '' || $dt === '') {
        list($df, $dt) = erp_bridge_period_range($period);
    }

    $where = [
        "p.post_type = 'lp_order'",
        "p.post_status = 'lp-completed'",
        "p.post_date >= %s",
        "p.post_date <= %s",
    ];
    $args = [$df . ' 00:00:00', $dt . ' 23:59:59'];

    if ($q !== '') {
        $where[]  = "(oi.order_item_name LIKE %s OR u.display_name LIKE %s OR u.user_email LIKE %s)";
        $q_like   = '%' . $wpdb->esc_like($q) . '%';
        $args[]   = $q_like;
        $args[]   = $q_like;
        $args[]   = $q_like;
    }
    $wsql = 'WHERE ' . implode(' AND ', $where);

    $join_sql = "
        FROM {$wpdb->prefix}learnpress_order_items oi
        JOIN {$wpdb->posts} p ON p.ID = oi.order_id
        LEFT JOIN {$wpdb->postmeta} pm_user
               ON pm_user.post_id = p.ID AND pm_user.meta_key = '_user_id'
        LEFT JOIN {$wpdb->postmeta} pm_pay
               ON pm_pay.post_id = p.ID AND pm_pay.meta_key = '_payment_method_title'
        LEFT JOIN {$wpdb->prefix}learnpress_order_itemmeta itm_total
               ON itm_total.learnpress_order_item_id = oi.order_item_id AND itm_total.meta_key = '_total'
        LEFT JOIN {$wpdb->prefix}learnpress_order_itemmeta itm_course
               ON itm_course.learnpress_order_item_id = oi.order_item_id AND itm_course.meta_key = '_course_id'
        LEFT JOIN {$wpdb->users} u
               ON u.ID = pm_user.meta_value
    ";

    // Aggregates (count + sum + unique students)
    $agg_sql = "
        SELECT COUNT(*) AS total,
               COALESCE(SUM(CAST(itm_total.meta_value AS DECIMAL(15,2))), 0) AS sum_items,
               COUNT(DISTINCT pm_user.meta_value) AS unique_students
        $join_sql
        $wsql
    ";
    $agg = $wpdb->get_row($wpdb->prepare($agg_sql, $args));

    // Page rows
    $list_sql = "
        SELECT oi.order_item_id, oi.order_item_name AS course_name, oi.order_id,
               p.post_date AS order_date,
               pm_user.meta_value AS user_id,
               pm_pay.meta_value AS payment_method,
               itm_total.meta_value AS item_total,
               itm_course.meta_value AS course_id,
               u.display_name AS user_name,
               u.user_email AS user_email
        $join_sql
        $wsql
        ORDER BY p.post_date DESC, oi.order_item_id DESC
        LIMIT %d OFFSET %d
    ";
    $list_args = array_merge($args, [$per_page, $offset]);
    $rows = $wpdb->get_results($wpdb->prepare($list_sql, $list_args));

    $activations = array_map(function ($r) {
        return [
            'order_id'      => (int) $r->order_id,
            'order_item_id' => (int) $r->order_item_id,
            'course_id'     => $r->course_id ? (int) $r->course_id : null,
            'course'        => $r->course_name,
            'student'       => [
                'id'    => $r->user_id ? (int) $r->user_id : null,
                'name'  => $r->user_name ?: null,
                'email' => $r->user_email ?: null,
            ],
            'amount'         => $r->item_total !== null ? (float) $r->item_total : null,
            'payment_method' => $r->payment_method,
            'date'           => $r->order_date,
        ];
    }, $rows);

    $total = (int) ($agg->total ?? 0);
    return [
        'period'          => $period,
        'date_from'       => $df,
        'date_to'         => $dt,
        'page'            => $page,
        'per_page'        => $per_page,
        'total'           => $total,
        'total_pages'     => (int) ceil($total / max(1, $per_page)),
        'sum_amount'      => (float) ($agg->sum_items ?? 0),
        'unique_students' => (int) ($agg->unique_students ?? 0),
        'activations'     => $activations,
        'fetched_at'      => current_time('c'),
    ];
}
