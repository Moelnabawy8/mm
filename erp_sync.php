<?php
/**
 * erp_sync — tell, precisely, how the local checkout differs from production.
 *
 * The local repo is behind production on an unknown set of shared files, which
 * is how a deploy of a "complete" local routes/web.php deleted five server-only
 * routes and took the site down. Guessing is what caused that; this measures.
 *
 * Line endings are hashed twice — raw and normalised to LF — because the local
 * checkout is CRLF and the server is LF, so a raw-only comparison would report
 * almost every file as different and bury the real differences.
 *
 * USAGE
 *   On the server:   php erp_sync.php manifest server
 *   Locally:         php erp_sync.php manifest local
 *   Locally:         php erp_sync.php compare manifest-server.txt manifest-local.txt
 *   On the server:   php erp_sync.php pack pull-list.txt
 */

$mode = $argv[1] ?? '';

switch ($mode) {
    case 'manifest': manifest($argv[2] ?? 'out'); break;
    case 'compare':  compare($argv[2] ?? '', $argv[3] ?? ''); break;
    case 'pack':     pack_files($argv[2] ?? ''); break;
    default:
        fwrite(STDERR, "usage:\n"
            . "  php erp_sync.php manifest <label>\n"
            . "  php erp_sync.php compare <server-manifest> <local-manifest>\n"
            . "  php erp_sync.php pack <pull-list.txt>\n");
        exit(1);
}

/** Directories worth comparing: application code, not dependencies or state. */
function scanRoots(): array
{
    return [
        'app',
        'routes',
        'config',
        'database/migrations',
        'database/seeders',
        'resources/views',
        'resources/lang',
        'public/js',
        'public/css',
    ];
}

function skipPath(string $rel): bool
{
    foreach ([
        '/vendor/', '/node_modules/', '/.git/', '/storage/',
        '.bak', '.bak_', '.zip', '.log', '.map',
    ] as $needle) {
        if (strpos($rel, $needle) !== false) {
            return true;
        }
    }

    return false;
}

function manifest(string $label): void
{
    $root = getcwd();

    if (!is_file($root . '/artisan')) {
        fwrite(STDERR, "Run from the Laravel root (artisan not found).\n");
        exit(1);
    }

    $out   = "manifest-{$label}.txt";
    $fh    = fopen($out, 'w');
    $count = 0;

    fwrite($fh, "# erp_sync manifest  label={$label}  generated=" . date('c') . "\n");
    fwrite($fh, "# <sha_raw16> <sha_lf16> <bytes> <path>\n");

    foreach (scanRoots() as $dirRel) {
        $dir = $root . '/' . $dirRel;
        if (!is_dir($dir)) {
            continue;
        }

        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($it as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));

            if (skipPath('/' . $rel)) {
                continue;
            }

            $raw  = file_get_contents($file->getPathname());
            $norm = str_replace("\r\n", "\n", $raw);

            fwrite($fh, substr(hash('sha256', $raw), 0, 16) . ' '
                      . substr(hash('sha256', $norm), 0, 16) . ' '
                      . strlen($raw) . ' ' . $rel . "\n");
            $count++;
        }
    }

    fclose($fh);
    echo "wrote {$out}  ({$count} files, " . round(filesize($out) / 1024, 1) . " KB)\n";
}

function readManifest(string $path): array
{
    if (!is_file($path)) {
        fwrite(STDERR, "manifest not found: {$path}\n");
        exit(1);
    }

    $rows = [];

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $parts = explode(' ', $line, 4);
        if (count($parts) < 4) {
            continue;
        }
        $rows[$parts[3]] = ['raw' => $parts[0], 'lf' => $parts[1], 'size' => (int) $parts[2]];
    }

    return $rows;
}

function compare(string $serverPath, string $localPath): void
{
    $server = readManifest($serverPath);
    $local  = readManifest($localPath);

    $onlyServer = $onlyLocal = $differ = $eolOnly = $same = [];

    foreach ($server as $path => $s) {
        if (!isset($local[$path])) {
            $onlyServer[$path] = $s;
        } elseif ($local[$path]['raw'] === $s['raw']) {
            $same[$path] = $s;
        } elseif ($local[$path]['lf'] === $s['lf']) {
            $eolOnly[$path] = $s;
        } else {
            $differ[$path] = ['server' => $s, 'local' => $local[$path]];
        }
    }

    foreach ($local as $path => $l) {
        if (!isset($server[$path])) {
            $onlyLocal[$path] = $l;
        }
    }

    ksort($onlyServer);
    ksort($onlyLocal);
    ksort($differ);

    $line = str_repeat('=', 74);

    echo "\n{$line}\n  LOCAL vs PRODUCTION\n{$line}\n";
    printf("  identical                     %5d\n", count($same));
    printf("  identical except line endings %5d\n", count($eolOnly));
    printf("  DIFFERENT CONTENT             %5d   <- review these\n", count($differ));
    printf("  ON SERVER ONLY                %5d   <- pull these\n", count($onlyServer));
    printf("  local only                    %5d   (yours, or deleted on prod)\n", count($onlyLocal));

    echo "\n{$line}\n  ON SERVER ONLY — missing from the local repo\n{$line}\n";
    if (empty($onlyServer)) {
        echo "  (none)\n";
    }
    foreach ($onlyServer as $path => $s) {
        printf("  %9s  %s\n", number_format($s['size']), $path);
    }

    echo "\n{$line}\n  DIFFERENT CONTENT — local is not the same file\n{$line}\n";
    if (empty($differ)) {
        echo "  (none)\n";
    }
    foreach ($differ as $path => $d) {
        $delta = $d['server']['size'] - $d['local']['size'];
        printf("  server %9s  local %9s  %+8d  %s\n",
            number_format($d['server']['size']),
            number_format($d['local']['size']),
            $delta,
            $path);
    }

    echo "\n{$line}\n  LOCAL ONLY — not on the server\n{$line}\n";
    if (empty($onlyLocal)) {
        echo "  (none)\n";
    }
    foreach ($onlyLocal as $path => $l) {
        printf("  %9s  %s\n", number_format($l['size']), $path);
    }

    // A pull list the server can pack directly.
    $pull = array_merge(array_keys($onlyServer), array_keys($differ));
    sort($pull);
    file_put_contents('pull-list.txt', implode("\n", $pull) . "\n");

    echo "\n{$line}\n";
    echo "  wrote pull-list.txt (" . count($pull) . " files)\n";
    echo "  Next: copy it to the server and run\n";
    echo "      php erp_sync.php pack pull-list.txt\n";
    echo "{$line}\n";
}

function pack_files(string $listPath): void
{
    if (!is_file($listPath)) {
        fwrite(STDERR, "list not found: {$listPath}\n");
        exit(1);
    }

    $out = 'erp-pull.zip';
    if (file_exists($out)) {
        unlink($out);
    }

    $zip = new ZipArchive();
    if ($zip->open($out, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        fwrite(STDERR, "could not create {$out}\n");
        exit(1);
    }

    $added = $missing = 0;

    foreach (file($listPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $rel) {
        $rel = trim($rel);
        if ($rel === '' || $rel[0] === '#') {
            continue;
        }
        // Never let a list entry escape the project root.
        if (strpos($rel, '..') !== false || $rel[0] === '/') {
            echo "  refused  {$rel}\n";
            continue;
        }
        if (!is_file($rel)) {
            echo "  missing  {$rel}\n";
            $missing++;
            continue;
        }
        $zip->addFile($rel, $rel);
        $added++;
    }

    $zip->close();

    echo "\nwrote {$out}  ({$added} files";
    echo $missing ? ", {$missing} missing)" : ")";
    echo "  " . round(filesize($out) / 1024, 1) . " KB\n";
}
