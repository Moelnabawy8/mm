<?php
/**
 * report_empty_api_deals v1  --  READ ONLY. This script never changes a row.
 *
 * Sibling of report_duplicate_deals_v1.php. That one removed rows that were
 * identical copies of each other. This one finds the leftovers: deals the API
 * created that carry no information at all --
 *
 *     no products, no notes, no purchase, meeting, call, task, file,
 *     attachment or any other child record, and added_by empty
 *
 * -- on a lead that still has a real deal of its own. Those rows only exist
 * because POST /api/v1/deals used to accept anything; on the lead page they
 * show up as "<lead name> - deal" with a dash under every column.
 *
 * A lead whose ONLY deals are empty ones is counted and reported but never
 * listed, because deleting them would leave the lead with no deal at all. Pass
 * --include-lone-leads to list those as well.
 *
 * It writes into storage/backups/empty-deals-report-<ts>/ :
 *
 *     deleted_deals.sql   a full INSERT per affected row  (restore with this)
 *     delete_ids.txt      the ids, one per line
 *     delete.sql          the DELETE statements, in batches -- NOT executed
 *
 *   php report_empty_api_deals_v1.php
 *   php report_empty_api_deals_v1.php --include-lone-leads
 *
 * Exit codes: 0 fine, 2 preflight, 3 could not write the report.
 */

const BATCH = 500;

// Every table with a deal_id foreign key. All ten are ON DELETE CASCADE, so a
// row that owns any of them is not empty and must never be removed.
const CHILD_TABLES = [
    'deal_purchases',
    'sales_meetings',
    'deal_discussions',
    'deal_calls',
    'deal_emails',
    'deal_tasks',
    'deal_files',
    'deal_attachments',
    'user_deals',
    'client_deals',
];

$root = __DIR__;
while ($root !== dirname($root) && !is_file($root . '/artisan')) {
    $root = dirname($root);
}
if (!is_file($root . '/artisan')) {
    fwrite(STDERR, "Could not find the Laravel root (no artisan above this file).\n");
    exit(2);
}

$includeLone = in_array('--include-lone-leads', $argv, true);

echo "report_empty_api_deals v1  (READ ONLY -- lists API deals that carry no information)\n\n";
echo "Laravel root: $root\n";
echo 'Running as:   ' . (function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] : get_current_user()) . "\n";
echo 'Scope:        ' . ($includeLone
    ? 'every empty API deal, even when it is the lead\'s only one'
    : 'only leads that keep a real deal afterwards') . "\n";

// ---------------------------------------------------------------- connection

function env_value($root, $key)
{
    $file = $root . '/.env';
    if (!is_file($file)) {
        fwrite(STDERR, "No .env at $file\n");
        exit(2);
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        list($k, $v) = explode('=', $line, 2);
        if (trim($k) !== $key) {
            continue;
        }
        $v = trim($v);
        if (strlen($v) > 1 && ($v[0] === '"' || $v[0] === "'") && substr($v, -1) === $v[0]) {
            $v = substr($v, 1, -1);
        }
        return $v;
    }
    return null;
}

$host = env_value($root, 'DB_HOST') ?: '127.0.0.1';
$port = env_value($root, 'DB_PORT') ?: '3306';
$name = env_value($root, 'DB_DATABASE');
$user = env_value($root, 'DB_USERNAME');
$pass = env_value($root, 'DB_PASSWORD');

if (!$name || !$user) {
    fwrite(STDERR, "DB_DATABASE / DB_USERNAME missing from .env\n");
    exit(2);
}

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
} catch (Exception $e) {
    fwrite(STDERR, 'Cannot connect: ' . $e->getMessage() . "\n");
    exit(2);
}
echo "Database:     $name on $host\n\n";

// ======================================================================= survey

echo "Looking for API deals with nothing in them...\n";

// An empty row: written by the API, no products, no notes, attached to a lead.
// Blank HTML paragraphs count as empty -- that is what an untouched notes box
// saves -- but anything with real text in it does not.
$empty = $pdo->query(
    "SELECT id, lead_id, name, stage_id, status, created_at
     FROM deals
     WHERE added_by IS NULL
       AND lead_id IS NOT NULL
       AND (products IS NULL OR products = '')
       AND (notes IS NULL
            OR TRIM(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
                   COALESCE(notes, ''),
                   '<p>', ''), '</p>', ''), '<br>', ''), '<br/>', ''), '&nbsp;', '')) = '')
     ORDER BY id"
)->fetchAll(PDO::FETCH_ASSOC);

$total = (int) $pdo->query('SELECT COUNT(*) FROM deals')->fetchColumn();
printf("  deals in the table:        %d\n", $total);
printf("  empty API deals:           %d\n", count($empty));

if (!$empty) {
    echo "\nNothing to clean up.\n";
    exit(0);
}

$ids = array_map(function ($r) { return (int) $r['id']; }, $empty);

// ---- anything that owns a child record is not empty --------------------------

echo "\nChecking every candidate against the ten child tables...\n";
$blocked = [];
foreach (CHILD_TABLES as $table) {
    foreach (array_chunk($ids, 1000) as $chunk) {
        $in = implode(',', $chunk);
        foreach ($pdo->query("SELECT DISTINCT deal_id FROM `$table` WHERE deal_id IN ($in)")->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $blocked[(int) $id][] = $table;
        }
    }
}

if ($blocked) {
    echo '  ' . count($blocked) . " of them own child records and are EXCLUDED:\n";
    $shown = 0;
    foreach ($blocked as $id => $tables) {
        echo "    deal $id -> " . implode(', ', array_unique($tables)) . "\n";
        if (++$shown >= 10) {
            echo "    ...\n";
            break;
        }
    }
} else {
    echo "  none of them own a purchase, meeting, call, task, file or attachment.\n";
}

$candidates = [];
foreach ($empty as $row) {
    if (!isset($blocked[(int) $row['id']])) {
        $candidates[(int) $row['id']] = $row;
    }
}

// ---- a lead must keep at least one deal --------------------------------------

$byLead = [];
foreach ($candidates as $id => $row) {
    $byLead[(int) $row['lead_id']][] = $id;
}

$dealsPerLead = [];
foreach (array_chunk(array_keys($byLead), 1000) as $chunk) {
    $in = implode(',', $chunk);
    foreach ($pdo->query("SELECT lead_id, COUNT(*) AS c FROM deals WHERE lead_id IN ($in) GROUP BY lead_id") as $r) {
        $dealsPerLead[(int) $r['lead_id']] = (int) $r['c'];
    }
}

$listed = [];
$lone = [];
$loneLeads = 0;
foreach ($byLead as $leadId => $dealIds) {
    $keepsSomething = ($dealsPerLead[$leadId] ?? 0) > count($dealIds);
    if ($keepsSomething) {
        $listed = array_merge($listed, $dealIds);
    } else {
        $loneLeads++;
        $lone = array_merge($lone, $dealIds);
    }
}

printf("\n  on leads that keep a real deal:    %d\n", count($listed));
printf("  the lead's only deal(s):           %d  (across %d lead(s))\n", count($lone), $loneLeads);

if ($includeLone) {
    $listed = array_merge($listed, $lone);
}
sort($listed);

echo "\n--------------------------------------------------\n";
printf("rows listed for removal: %d   excluded: %d\n", count($listed), count($blocked));
printf("the table would go from %d rows to %d\n", $total, $total - count($listed));

if (!$includeLone && $lone) {
    echo "\nThe " . count($lone) . " row(s) on leads with no other deal were NOT listed.\n";
    echo "Re-run with --include-lone-leads to have them listed too.\n";
}

if (!$listed) {
    echo "\nNothing to list.\n";
    exit(0);
}

// ---- how many carry the bot's signature, and what stages they sit in ---------

$sampleIn = implode(',', array_slice($listed, 0, 2000));
$signature = (int) $pdo->query(
    "SELECT COUNT(*) FROM deals d JOIN leads l ON l.id = d.lead_id
     WHERE d.id IN ($sampleIn)
       AND (d.name = CONCAT(l.name, ' - deal') OR d.name = CONCAT(l.name, ' - Deal'))"
)->fetchColumn();
printf("\nOf the first %d listed, %d are named exactly \"<lead name> - deal\".\n", min(2000, count($listed)), $signature);

echo "\nBy status:\n";
foreach ($pdo->query("SELECT COALESCE(status, '(none)') AS s, COUNT(*) AS c
                      FROM deals WHERE id IN ($sampleIn) GROUP BY s ORDER BY c DESC LIMIT 10") as $r) {
    printf("  %-28s %d\n", $r['s'], $r['c']);
}

echo "\nFirst ten listed rows:\n";
$firstTen = implode(',', array_slice($listed, 0, 10));
foreach ($pdo->query("SELECT d.id, d.lead_id, LEFT(d.name, 40) AS name, COALESCE(d.status, '') AS status,
                             d.created_at, (SELECT COUNT(*) FROM deals x WHERE x.lead_id = d.lead_id) AS deals_on_lead
                      FROM deals d WHERE d.id IN ($firstTen) ORDER BY d.id") as $r) {
    printf(
        "  %-8s lead=%-8s %-42s %-14s deals on that lead: %s   %s\n",
        $r['id'], $r['lead_id'], $r['name'], $r['status'], $r['deals_on_lead'], $r['created_at']
    );
}

// ======================================================================= report

$stamp = date('Ymd-His');
$dir = $root . '/storage/backups/empty-deals-report-' . $stamp;
if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
    fwrite(STDERR, "Could not create $dir\n");
    exit(3);
}
@file_put_contents($root . '/storage/backups/.htaccess', "Require all denied\nDeny from all\n");

$columns = $pdo->query('SHOW COLUMNS FROM deals')->fetchAll(PDO::FETCH_COLUMN);
$colList = '`' . implode('`, `', $columns) . '`';

$backupFile = $dir . '/deleted_deals.sql';
$fh = fopen($backupFile, 'w');
if (!$fh) {
    fwrite(STDERR, "Could not write $backupFile\n");
    exit(3);
}
fwrite($fh, "-- empty API deals listed for removal by report_empty_api_deals v1 on " . date('c') . "\n");
fwrite($fh, "-- replay this whole file to put every one of them back\n");

$written = 0;
foreach (array_chunk($listed, BATCH) as $chunk) {
    $in = implode(',', $chunk);
    foreach ($pdo->query("SELECT $colList FROM deals WHERE id IN ($in) ORDER BY id") as $row) {
        $values = [];
        foreach ($columns as $c) {
            $values[] = $row[$c] === null ? 'NULL' : $pdo->quote((string) $row[$c]);
        }
        fwrite($fh, "INSERT INTO `deals` ($colList) VALUES (" . implode(', ', $values) . ");\n");
        $written++;
    }
}
fclose($fh);

if ($written !== count($listed)) {
    fwrite(STDERR, "The backup holds $written row(s) but " . count($listed) . " were listed - the report is not trustworthy.\n");
    exit(3);
}

$readBack = 0;
foreach (file($backupFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (strpos($line, 'INSERT INTO `deals`') === 0) {
        $readBack++;
    }
}
if ($readBack !== $written) {
    fwrite(STDERR, "Backup verification failed: wrote $written row(s) but read back $readBack.\n");
    exit(3);
}

file_put_contents($dir . '/delete_ids.txt', implode("\n", $listed) . "\n");

$deleteSql = "-- generated by report_empty_api_deals v1 on " . date('c') . "\n"
    . "-- NOT executed by that script. Read deleted_deals.sql first.\n"
    . "-- Every id below was written by the API, has no products, no notes and no\n"
    . "-- child record of any kind"
    . ($includeLone ? ".\n\n" : ", and its lead keeps at least one other deal.\n\n");
foreach (array_chunk($listed, BATCH) as $chunk) {
    $deleteSql .= 'DELETE FROM `deals` WHERE `added_by` IS NULL AND `id` IN (' . implode(',', $chunk) . ");\n";
}
file_put_contents($dir . '/delete.sql', $deleteSql);

file_put_contents($dir . '/MANIFEST.txt',
    "report_empty_api_deals v1\n"
    . 'taken: ' . date('c') . "\n"
    . 'database: ' . $name . "\n"
    . 'scope: ' . ($includeLone ? 'including leads with no other deal' : 'leads that keep a real deal') . "\n"
    . 'deals in table: ' . $total . "\n"
    . 'rows listed: ' . $written . "\n"
    . 'rows excluded (own child records): ' . count($blocked) . "\n"
    . 'rows held back (lead has no other deal): ' . ($includeLone ? 0 : count($lone)) . "\n"
    . 'deleted_deals.sha256: ' . hash_file('sha256', $backupFile) . "\n"
);

echo "\nWritten to $dir\n";
echo "  deleted_deals.sql  $written row(s), verified\n";
echo "  delete_ids.txt     the ids\n";
echo "  delete.sql         the DELETE statements -- NOT run by this script\n";
echo "\nNothing in the database was changed.\n";
exit(0);
