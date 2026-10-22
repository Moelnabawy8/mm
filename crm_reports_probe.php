<?php
/**
 * CRM Reports — read-only data probe.
 *
 * Run from the Laravel root on the server:
 *     php crm_reports_probe.php > crm_probe.txt
 *
 * It only runs SELECT / SHOW statements and prints aggregates (counts, dates,
 * column definitions). No names, phones or emails are printed. Delete the file
 * when done.
 */

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function out($title, $rows)
{
    echo "\n### {$title}\n";
    if ($rows instanceof \Illuminate\Support\Collection) {
        $rows = $rows->all();
    }
    if (!is_array($rows)) {
        echo var_export($rows, true), "\n";
        return;
    }
    if (empty($rows)) {
        echo "(none)\n";
        return;
    }
    foreach ($rows as $r) {
        echo implode(' | ', array_map(fn ($v) => $v === null ? 'NULL' : (string) $v, (array) $r)), "\n";
    }
}

function q($sql, $b = [])
{
    try {
        return DB::select($sql, $b);
    } catch (\Throwable $e) {
        return ['ERROR: ' . substr($e->getMessage(), 0, 200)];
    }
}

echo "CRM probe @ " . date('c') . "  db=" . DB::getDatabaseName() . "\n";

// ---- schema ----------------------------------------------------------------
$tables = ['leads', 'user_leads', 'lead_stages', 'pipelines', 'lead_activity_logs', 'lead_calls',
    'lead_discussions', 'lead_emails', 'deals', 'stages', 'sales_meetings', 'sales_meeting_types',
    'deal_purchases', 'installments', 'quality_evaluations', 'sources', 'call_types'];
foreach ($tables as $t) {
    if (!Schema::hasTable($t)) {
        out("COLUMNS {$t}", ['TABLE MISSING']);
        continue;
    }
    out("COLUMNS {$t}", collect(q("SHOW COLUMNS FROM `{$t}`"))->map(fn ($c) => [$c->Field, $c->Type, $c->Null, $c->Key, $c->Default]));
    out("INDEXES {$t}", collect(q("SHOW INDEX FROM `{$t}`"))->map(fn ($i) => [$i->Key_name, $i->Column_name, $i->Seq_in_index]));
    out("ROWS {$t}", q("SELECT COUNT(*) n FROM `{$t}`"));
}

// ---- leads -----------------------------------------------------------------
out('leads per company (created_by)', q("SELECT created_by, COUNT(*) n, MIN(created_at) first, MAX(created_at) last FROM leads GROUP BY created_by ORDER BY n DESC LIMIT 10"));
out('leads per month (created_at, last 18)', q("SELECT DATE_FORMAT(created_at,'%Y-%m') m, COUNT(*) n FROM leads GROUP BY m ORDER BY m DESC LIMIT 18"));
out('leads created_at NULL / date NULL', q("SELECT SUM(created_at IS NULL) created_null, SUM(date IS NULL) date_null, SUM(DATE(created_at) <> date) date_differs FROM leads"));
out('leads user_id state', q("SELECT SUM(user_id IS NULL OR user_id=0) no_owner, SUM(user_id NOT IN (SELECT id FROM users)) owner_missing_user, COUNT(DISTINCT user_id) owners FROM leads"));
out('owners: type + lead count (top 40)', q("SELECT l.user_id, u.type, COUNT(*) n FROM leads l LEFT JOIN users u ON u.id=l.user_id GROUP BY l.user_id, u.type ORDER BY n DESC LIMIT 40"));
out('owner not in user_leads', q("SELECT COUNT(*) n FROM leads l WHERE NOT EXISTS (SELECT 1 FROM user_leads ul WHERE ul.lead_id=l.id AND ul.user_id=l.user_id)"));
out('leads by user_leads count', q("SELECT c, COUNT(*) leads FROM (SELECT l.id, (SELECT COUNT(*) FROM user_leads ul WHERE ul.lead_id=l.id) c FROM leads l) x GROUP BY c ORDER BY c"));
out('stage_id state', q("SELECT SUM(stage_id IS NULL OR stage_id=0) no_stage, SUM(stage_id NOT IN (SELECT id FROM lead_stages)) bad_stage, SUM(pipeline_id IS NULL OR pipeline_id=0) no_pipeline FROM leads"));
out('leads per stage', q("SELECT s.pipeline_id, s.id, s.name, s.`order`, COUNT(l.id) n FROM lead_stages s LEFT JOIN leads l ON l.stage_id=s.id GROUP BY s.id ORDER BY s.pipeline_id, s.`order`"));
out('pipelines', q("SELECT p.id, p.name, p.created_by, (SELECT COUNT(*) FROM leads WHERE pipeline_id=p.id) n FROM pipelines p"));
out('country top 25', q("SELECT country, COUNT(*) n FROM leads GROUP BY country ORDER BY n DESC LIMIT 25"));
out('sources format sample (distinct, top 15)', q("SELECT sources, COUNT(*) n FROM leads GROUP BY sources ORDER BY n DESC LIMIT 15"));
out('campaign_name distinct / null', q("SELECT COUNT(DISTINCT campaign_name) distinct_campaigns, SUM(campaign_name IS NULL OR campaign_name='') empty_campaign FROM leads"));
out('campaign top 15', q("SELECT campaign_name, COUNT(*) n FROM leads GROUP BY campaign_name ORDER BY n DESC LIMIT 15"));
out('is_active', q("SELECT is_active, COUNT(*) n FROM leads GROUP BY is_active"));
out('notes filled', q("SELECT SUM(notes IS NOT NULL AND notes<>'') with_notes FROM leads"));

// ---- activity ----------------------------------------------------------------
out('lead_activity_logs by type', q("SELECT log_type, COUNT(*) n, MIN(created_at) first, MAX(created_at) last FROM lead_activity_logs GROUP BY log_type"));
out('lead_activity_logs per month (last 12)', q("SELECT DATE_FORMAT(created_at,'%Y-%m') m, COUNT(*) n FROM lead_activity_logs GROUP BY m ORDER BY m DESC LIMIT 12"));
out('move logs: actor == lead owner?', q("SELECT SUM(a.user_id=l.user_id) by_owner, SUM(a.user_id<>l.user_id) by_other FROM lead_activity_logs a JOIN leads l ON l.id=a.lead_id WHERE a.log_type='Move'"));
foreach (['lead_calls', 'lead_discussions', 'lead_emails'] as $t) {
    out("{$t} volume", q("SELECT COUNT(*) n, MIN(created_at) first, MAX(created_at) last FROM `{$t}`"));
}
out('lead_calls call_type', q("SELECT call_type, COUNT(*) n FROM lead_calls GROUP BY call_type"));
out('call_types', q("SELECT id, name, created_by FROM call_types"));

// ---- quality evaluations ---------------------------------------------------
out('qe by potential', q("SELECT is_potential_lead, COUNT(*) n FROM quality_evaluations GROUP BY is_potential_lead"));
out('qe filled vs placeholder', q("SELECT SUM(is_potential_lead<>'unknown') decided, SUM(engagement_level IS NOT NULL) engagement, SUM(rating IS NOT NULL) rated, SUM(updated_at>created_at) edited FROM quality_evaluations"));
out('qe evaluator == lead owner?', q("SELECT SUM(q.evaluator_id=l.user_id) by_owner, SUM(q.evaluator_id<>l.user_id) by_other FROM quality_evaluations q JOIN leads l ON l.id=q.lead_id WHERE q.is_potential_lead<>'unknown'"));
out('qe decided per month (updated_at, last 12)', q("SELECT DATE_FORMAT(updated_at,'%Y-%m') m, COUNT(*) n FROM quality_evaluations WHERE is_potential_lead<>'unknown' GROUP BY m ORDER BY m DESC LIMIT 12"));
out('qe engagement', q("SELECT engagement_level, COUNT(*) n FROM quality_evaluations GROUP BY engagement_level"));
out('qe course fit', q("SELECT course_fit_level, COUNT(*) n FROM quality_evaluations GROUP BY course_fit_level"));
out('qe career stage', q("SELECT career_stage, COUNT(*) n FROM quality_evaluations GROUP BY career_stage"));

// ---- deals -----------------------------------------------------------------
out('deals volume', q("SELECT COUNT(*) n, SUM(lead_id IS NULL) no_lead, SUM(added_by IS NULL) no_added_by, MIN(created_at) first, MAX(created_at) last FROM deals"));
out('deals status', q("SELECT status, is_active, COUNT(*) n FROM deals GROUP BY status, is_active"));
out('deals added_by == lead owner?', q("SELECT SUM(d.added_by=l.user_id) by_owner, SUM(d.added_by<>l.user_id) by_other FROM deals d JOIN leads l ON l.id=d.lead_id"));
out('deals added_by top 15', q("SELECT d.added_by, u.type, COUNT(*) n FROM deals d LEFT JOIN users u ON u.id=d.added_by GROUP BY d.added_by, u.type ORDER BY n DESC LIMIT 15"));
out('deals per lead', q("SELECT c, COUNT(*) leads FROM (SELECT lead_id, COUNT(*) c FROM deals WHERE lead_id IS NOT NULL GROUP BY lead_id) x GROUP BY c"));
out('deals per stage', q("SELECT s.name, COUNT(d.id) n FROM stages s LEFT JOIN deals d ON d.stage_id=s.id GROUP BY s.id"));
out('deals per month (last 12)', q("SELECT DATE_FORMAT(created_at,'%Y-%m') m, COUNT(*) n FROM deals GROUP BY m ORDER BY m DESC LIMIT 12"));

// ---- sales meetings --------------------------------------------------------
out('sales_meetings volume', q("SELECT COUNT(*) n, SUM(lead_id IS NULL) no_lead, SUM(deal_id IS NULL) no_deal, SUM(date IS NULL) no_date, SUM(datetime IS NULL) no_datetime, SUM(date < CURDATE()) past, SUM(date >= CURDATE()) upcoming FROM sales_meetings"));
out('sales_meetings by type', q("SELECT t.name, COUNT(m.id) n FROM sales_meetings m LEFT JOIN sales_meeting_types t ON t.id=m.meeting_type_id GROUP BY t.name"));
out('sales_meetings per month (last 12)', q("SELECT DATE_FORMAT(COALESCE(date, created_at),'%Y-%m') m, COUNT(*) n FROM sales_meetings GROUP BY m ORDER BY m DESC LIMIT 12"));
out('meetings per lead', q("SELECT c, COUNT(*) leads FROM (SELECT lead_id, COUNT(*) c FROM sales_meetings WHERE lead_id IS NOT NULL GROUP BY lead_id) x GROUP BY c"));
out('sales_meetings created_by', q("SELECT created_by, COUNT(*) n FROM sales_meetings GROUP BY created_by"));

// ---- purchases / installments ---------------------------------------------
out('deal_purchases', q("SELECT payment_type, status, currency, COUNT(*) n, SUM(price) price, SUM(paid_amount) paid FROM deal_purchases GROUP BY payment_type, status, currency"));
out('deal_purchases payment_method', q("SELECT payment_method, COUNT(*) n FROM deal_purchases GROUP BY payment_method ORDER BY n DESC LIMIT 15"));
out('deal_purchases per month', q("SELECT DATE_FORMAT(purchase_date,'%Y-%m') m, COUNT(*) n FROM deal_purchases GROUP BY m ORDER BY m DESC LIMIT 12"));
out('installments', q("SELECT status, is_hopeless, COUNT(*) n, SUM(amount) amount, SUM(exemption) exemption FROM installments GROUP BY status, is_hopeless"));
out('installments overdue', q("SELECT COUNT(*) n, SUM(amount) amount FROM installments WHERE status<>'paid' AND date < CURDATE()"));

// ---- users / permissions ---------------------------------------------------
out('user types (non-client)', q("SELECT type, COUNT(*) n FROM users WHERE type<>'client' GROUP BY type ORDER BY n DESC"));
out('roles containing sales', q("SELECT r.name, COUNT(m.model_id) n FROM roles r LEFT JOIN model_has_roles m ON m.role_id=r.id WHERE r.name LIKE '%ales%' GROUP BY r.id"));
out('sales_group values', q("SELECT sales_group, COUNT(*) n FROM users WHERE sales_group IS NOT NULL GROUP BY sales_group"));
out('crm permissions present', q("SELECT name FROM permissions WHERE name LIKE '%crm%' OR name LIKE '%lead%' OR name LIKE '%deal%' ORDER BY name"));

// ---- deploy facts ----------------------------------------------------------
$dirs = ['app/Services', 'app/Http/Controllers', 'app/Exports', 'resources/views', 'resources/views/report',
    'database/migrations', 'routes', 'resources/views/partials/admin', 'storage/framework/views'];
out('writable dirs', array_map(fn ($d) => [$d, is_dir(base_path($d)) ? (is_writable(base_path($d)) ? 'writable' : 'NOT WRITABLE') : 'missing'], $dirs));
out('route names present', array_map(fn ($n) => [$n, \Route::has($n) ? 'yes' : 'no'], ['report.lead', 'report.deal', 'leads.show', 'sales-meetings.index', 'deal-purchase.index', 'installments.index', 'quality-evaluations.index', 'crm.dashboard']));
out('php / laravel', [[PHP_VERSION, app()->version()]]);
out('maatwebsite excel', [[class_exists(\Maatwebsite\Excel\Facades\Excel::class) ? 'yes' : 'no']]);

echo "\nDONE\n";
