<?php
/**
 * report_duplicate_deals v1  --  READ ONLY. This script never changes a row.
 *
 * POST /api/v1/deals had no duplicate protection, so a client that re-posted
 * the same payload wrote a new row every time.
 *
 * This script finds the rows that are byte-for-byte identical to another row
 * that would survive:
 *
 *     same lead_id, name, products, notes, stage_id, pipeline_id, created_by
 *
 * The lowest id in each identical set is kept. Rows created from the web
 * (added_by filled) are never considered, and any row that owns a child record
 * -- a purchase, a meeting, a call, an attachment, anything -- is excluded and
 * reported instead.
 *
 * It writes three files into storage/backups/deal-dedupe-report-<ts>/ :
 *
 *     deleted_deals.sql   a full INSERT per affected row  (restore with this)
 *     delete_ids.txt      the ids, one per line
 *     delete.sql          the DELETE statements, in batches -- NOT executed
 *
 * Run it, read the summary, check deleted_deals.sql, and only then decide.
 *
 *   php report_duplicate_deals_v1.php
 *
 * Exit codes: 0 fine, 2 preflight, 3 could not write the report.
 */

const BATCH = 500;

// Every table with a deal_id foreign key. All ten are ON DELETE CASCADE, so a
// row that owns any of them must never be removed blindly.
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

echo "report_duplicate_deals v1  (READ ONLY -- lists the deal rows that are identical copies)\n\n";
echo "Laravel root: $root\n";
echo 'Running as:   ' . (function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] : get_current_user()) . "\n";

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

$pdo->exec('SET SESSION group_concat_max_len = 1000000');

// ======================================================================= survey

echo "Looking for rows that are identical copies of another row...\n";

$sql = "SELECT MIN(id) AS keep_id, COUNT(*) AS c, GROUP_CONCAT(id ORDER BY id) AS ids
        FROM deals
        WHERE added_by IS NULL
        GROUP BY lead_id, name, COALESCE(products, '\x01'), COALESCE(notes, '\x01'),
                 stage_id, pipeline_id, created_by
        HAVING COUNT(*) > 1";

$sets = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$candidates = [];
foreach ($sets as $set) {
    foreach (array_map('intval', explode(',', $set['ids'])) as $id) {
        if ($id !== (int) $set['keep_id']) {
            $candidates[] = $id;
        }
    }
}

$total = (int) $pdo->query('SELECT COUNT(*) FROM deals')->fetchColumn();
printf("  deals in the table:         %d\n", $total);
printf("  identical sets found:       %d\n", count($sets));
printf("  rows kept (one per set):    %d\n", count($sets));
printf("  rows that are exact copies: %d\n\n", count($candidates));

if (!$candidates) {
    echo "Nothing to clean up.\n";
    exit(0);
}

// ---- a row that owns anything is excluded, whatever the survey said

echo "Checking every candidate against the ten child tables...\n";
$blocked = [];
foreach (CHILD_TABLES as $table) {
    foreach (array_chunk($candidates, 1000) as $chunk) {
        $in = implode(',', $chunk);
        $rows = $pdo->query("SELECT DISTINCT deal_id FROM `$table` WHERE deal_id IN ($in)")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($rows as $id) {
            $blocked[(int) $id][] = $table;
        }
    }
}

if ($blocked) {
    echo '  ' . count($blocked) . " candidate(s) own child records and are EXCLUDED:\n";
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

$listed = array_values(array_diff($candidates, array_keys($blocked)));

echo "\n--------------------------------------------------\n";
printf("rows listed for removal: %d   excluded: %d\n", count($listed), count($blocked));
printf("the table would go from %d rows to %d\n", $total, $total - count($listed));

// ---- show the first set so the rule can be eyeballed

$sample = $sets[0];
$sampleIds = implode(',', array_map('intval', explode(',', $sample['ids'])));
echo "\nFirst set, for a sanity check (keeping id " . $sample['keep_id'] . "):\n";
$stmt = $pdo->query("SELECT id, lead_id, LEFT(name, 36) AS name, products,
                            LEFT(COALESCE(notes, ''), 36) AS notes, created_at
                     FROM deals WHERE id IN ($sampleIds) ORDER BY id");
foreach ($stmt as $r) {
    printf(
        "  %-9s lead=%-8s %-38s products=%-8s notes=%-38s %s\n",
        $r['id'] . ($r['id'] == $sample['keep_id'] ? ' *' : ''),
        $r['lead_id'],
        $r['name'],
        $r['products'] === null ? 'NULL' : $r['products'],
        $r['notes'],
        $r['created_at']
    );
}
echo "  (* = kept)\n";

// ======================================================================= report

$stamp = date('Ymd-His');
$dir = $root . '/storage/backups/deal-dedupe-report-' . $stamp;
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
fwrite($fh, "-- deal rows listed for removal by report_duplicate_deals v1 on " . date('c') . "\n");
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

$deleteSql = "-- generated by report_duplicate_deals v1 on " . date('c') . "\n"
    . "-- NOT executed by that script. Read deleted_deals.sql first.\n"
    . "-- Every id below has a surviving twin with identical lead, name, products,\n"
    . "-- notes, stage, pipeline and owner, and owns no child record.\n\n";
foreach (array_chunk($listed, BATCH) as $chunk) {
    $deleteSql .= 'DELETE FROM `deals` WHERE `added_by` IS NULL AND `id` IN (' . implode(',', $chunk) . ");\n";
}
file_put_contents($dir . '/delete.sql', $deleteSql);

file_put_contents($dir . '/MANIFEST.txt',
    "report_duplicate_deals v1\n"
    . 'taken: ' . date('c') . "\n"
    . 'database: ' . $name . "\n"
    . 'deals in table: ' . $total . "\n"
    . 'rows listed: ' . $written . "\n"
    . 'rows excluded (own child records): ' . count($blocked) . "\n"
    . 'deleted_deals.sha256: ' . hash_file('sha256', $backupFile) . "\n"
);

echo "\nWritten to $dir\n";
echo "  deleted_deals.sql  $written row(s), verified\n";
echo "  delete_ids.txt     the ids\n";
echo "  delete.sql         the DELETE statements -- NOT run by this script\n";
echo "\nNothing in the database was changed.\n";
exit(0);
