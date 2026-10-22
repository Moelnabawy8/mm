<?php
define('LARAVEL_START', microtime(true));
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=================================================\n";
echo "🔍 DIAGNOSING PATHS ON THIS SERVER\n";
echo "=================================================\n\n";

echo "base_path():    " . base_path() . "\n";
echo "public_path():  " . public_path() . "\n";
echo "storage_path(): " . storage_path() . "\n";
echo "asset('test'):  " . asset('test') . "\n";
echo "__DIR__:        " . __DIR__ . "\n";
echo "\n";

// Check if marketing_media was saved
$publicPath = public_path('marketing_media');
echo "Expected save path: $publicPath\n";
echo "Path exists? " . (is_dir($publicPath) ? "YES ✅" : "NO ❌") . "\n";

// Find where files actually were written
$knownFiles = glob(public_path('marketing_media') . '/*.jpg');
echo "Files in public_path/marketing_media: " . count($knownFiles) . "\n";

// Check if public_html exists at the parent level
$possiblePublic = dirname(base_path()) . '/public_html';
echo "\nChecking alternative public dir: $possiblePublic\n";
echo "Exists? " . (is_dir($possiblePublic) ? "YES ✅" : "NO ❌") . "\n";

// Check sibling public_html
$alt2 = base_path() . '/../public_html';
$alt2real = realpath($alt2);
echo "Alt path realpath: " . ($alt2real ?: 'NOT FOUND') . "\n";

// Check if there's a gdrive_test file anywhere
echo "\nSearching for gdrive_test files...\n";
$found = shell_exec('find / -name "gdrive_test_*.jpg" 2>/dev/null | head -5');
echo $found ?: "None found or search not permitted.\n";

echo "\n=================================================\n";
echo "🏁 DIAGNOSIS DONE\n";
echo "=================================================\n";
