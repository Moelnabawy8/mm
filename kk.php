<?php

/**
 * Check All Task Files Script
 * 
 * This script checks all task files in database and verifies if they exist on server
 * 
 * Usage: php check_all_task_files.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\TaskFile;

echo "==============================================\n";
echo "Task Files Integrity Check\n";
echo "==============================================\n\n";

// Get all task files
$allFiles = TaskFile::orderBy('id', 'desc')->get();
$totalFiles = $allFiles->count();

echo "Total files in database: {$totalFiles}\n\n";

if ($totalFiles == 0) {
    echo "No files found in database.\n";
    exit(0);
}

// Check storage directory
$storageDir = storage_path('uploads/tasks/');
echo "Storage directory: {$storageDir}\n";

if (!is_dir($storageDir)) {
    echo "❌ Storage directory does not exist!\n";
    exit(1);
}

// Get all files in storage
$filesOnServer = array_diff(scandir($storageDir), ['.', '..']);
$filesOnServerCount = count($filesOnServer);

echo "Files on server: {$filesOnServerCount}\n\n";

// Check each database record
$found = 0;
$missing = 0;
$missingFiles = [];

echo "Checking files...\n";
echo "-------------------------------------------\n";

foreach ($allFiles as $file) {
    $filePath = $storageDir . $file->file;
    
    if (file_exists($filePath)) {
        $found++;
    } else {
        $missing++;
        $missingFiles[] = [
            'id' => $file->id,
            'file' => $file->file,
            'name' => $file->name,
            'task_id' => $file->task_id,
            'created_at' => $file->created_at,
        ];
    }
}

echo "\n";
echo "==============================================\n";
echo "Results\n";
echo "==============================================\n";
echo "Total records: {$totalFiles}\n";
echo "✅ Found on server: {$found}\n";
echo "❌ Missing from server: {$missing}\n";
echo "Percentage found: " . round(($found / $totalFiles) * 100, 2) . "%\n\n";

if ($missing > 0) {
    echo "Missing files (showing first 20):\n";
    echo "-------------------------------------------\n";
    
    $displayCount = min(20, count($missingFiles));
    for ($i = 0; $i < $displayCount; $i++) {
        $f = $missingFiles[$i];
        echo "ID: {$f['id']}\n";
        echo "  File: {$f['file']}\n";
        echo "  Name: {$f['name']}\n";
        echo "  Task: {$f['task_id']}\n";
        echo "  Created: {$f['created_at']}\n";
        echo "\n";
    }
    
    if ($missing > 20) {
        echo "... and " . ($missing - 20) . " more missing files\n\n";
    }
    
    // Offer to clean up
    echo "Would you like to delete missing file records from database? (yes/no): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    $response = trim(strtolower($line));
    fclose($handle);
    
    if ($response === 'yes' || $response === 'y') {
        echo "\nDeleting missing file records...\n";
        $deleted = 0;
        
        foreach ($missingFiles as $f) {
            TaskFile::where('id', $f['id'])->delete();
            $deleted++;
        }
        
        echo "✅ Deleted {$deleted} records\n";
    } else {
        echo "Cleanup cancelled.\n";
    }
}

// Check for orphaned files (on server but not in database)
echo "\n";
echo "Checking for orphaned files...\n";
echo "-------------------------------------------\n";

$dbFiles = $allFiles->pluck('file')->toArray();
$orphaned = [];

foreach ($filesOnServer as $serverFile) {
    if (!in_array($serverFile, $dbFiles)) {
        $orphaned[] = $serverFile;
    }
}

$orphanedCount = count($orphaned);

if ($orphanedCount > 0) {
    echo "⚠️  Found {$orphanedCount} orphaned file(s) on server (not in database)\n\n";
    
    $displayCount = min(10, $orphanedCount);
    echo "Orphaned files (showing first 10):\n";
    for ($i = 0; $i < $displayCount; $i++) {
        echo "  - {$orphaned[$i]}\n";
    }
    
    if ($orphanedCount > 10) {
        echo "  ... and " . ($orphanedCount - 10) . " more\n";
    }
} else {
    echo "✅ No orphaned files found\n";
}

echo "\n==============================================\n";
echo "Check Complete\n";
echo "==============================================\n";
