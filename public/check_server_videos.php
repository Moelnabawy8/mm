<?php
// check_server_videos.php

// Force text output for CLI
$isCli = (php_sapi_name() == 'cli');
$nl = $isCli ? "\n" : "<br>";
$hr = $isCli ? "\n------------------------------------------------\n" : "<hr>";
if (!$isCli) echo "<pre>";

echo "Running Video Diagnostic Tool v3 (CLI/Web Mode)\n";
echo "===============================================\n\n";

echo "DEBUG INFO:\n";
echo "Current File: " . __FILE__ . $nl;
echo "Current Dir:  " . __DIR__ . $nl;
echo $hr;

// 1. Check Directory Permissions
$uploadDir = __DIR__ . '/uploads';
echo "1. Checking Directory: 'uploads' $nl";

if (is_dir($uploadDir)) {
    echo "   [OK] Directory exists.$nl";
    $perms = substr(sprintf('%o', fileperms($uploadDir)), -4);
    echo "   Permissions: 'uploads' ($perms) $nl";
} else {
    echo "   [CRITICAL] 'uploads' directory NOT FOUND in " . __DIR__ . $nl;
    exit(1);
}

echo $hr;

// 2. Locate Autoload and Bootstrap
echo "2. locating Laravel Core... $nl";

$possibleRoots = [
    __DIR__ . '/..',      // Standard: public is inside project
    __DIR__,              // Root: project is in public_html
    __DIR__ . '/laravel', // Subdomain/Subfolder
];

$validRoot = false;

foreach ($possibleRoots as $root) {
    echo "   Checking root: $root $nl";
    $autoload = $root . '/vendor/autoload.php';
    $bootstrap = $root . '/bootstrap/app.php';
    
    $hasAutoload = file_exists($autoload);
    $hasBootstrap = file_exists($bootstrap);
    
    echo "      - vendor/autoload.php: " . ($hasAutoload ? "FOUND" : "MISSING") . $nl;
    echo "      - bootstrap/app.php:   " . ($hasBootstrap ? "FOUND" : "MISSING") . $nl;
    
    if ($hasAutoload && $hasBootstrap) {
        $validRoot = $root;
        echo "   [SUCCESS] Found valid Laravel root at: $root $nl";
        break;
    }
}

if (!$validRoot) {
    echo $nl . "[CRITICAL] Could not find a valid Laravel installation (vendor + bootstrap).$nl";
    echo "Please check where the 'vendor' folder is located relative to this script.$nl";
    exit(1);
}

// 3. Load Laravel
echo $hr;
echo "3. Loading Laravel... $nl";

require $validRoot . '/vendor/autoload.php';
$app = require $validRoot . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "   [OK] Laravel Bootstrapped.$nl$nl";

// 4. Check Videos
try {
    $videos = \Modules\Guide\Entities\GuideVideo::all();
    $actualFiles = scandir($uploadDir);
    
    $mask = "| %-5s | %-40s | %-10s | %-10s | %-45s |\n";
    printf($mask, 'ID', 'Filename (DB)', 'Status', 'Match?', 'Fix');
    echo str_repeat('-', 120) . "\n";

    foreach ($videos as $video) {
        $dbPath = $video->video_path; 
        $filename = basename($dbPath);
        $fullPath = $uploadDir . '/' . $filename;
        
        $exists = file_exists($fullPath);
        $exactMatch = "NO";
        $suggestion = "Upload file: $filename";
        
        foreach ($actualFiles as $f) {
            if ($f === '.' || $f === '..') continue;
            if ($f === $filename) {
                $exactMatch = "YES";
                $suggestion = "OK";
                break;
            }
            if (strtolower($f) === strtolower($filename)) {
                $suggestion = "Rename '$f' to '$filename' (Case mismatch)";
                break;
            }
            if (str_replace(' ', '', strtolower($f)) === str_replace(' ', '', strtolower($filename))) {
                    $suggestion = "Rename '$f' to '$filename' (Space mismatch)";
            }
        }
        
        printf($mask, 
            $video->id, 
            substr($filename, 0, 40), 
            ($exists ? 'Found' : 'MISSING'), 
            $exactMatch, 
            substr(strip_tags($suggestion), 0, 45)
        );
    }
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}

echo $nl;
