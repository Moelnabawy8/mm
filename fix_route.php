<?php
// fix_route.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$webPhpPath = __DIR__ . '/routes/web.php';

if (!file_exists($webPhpPath)) {
    die("Error: Could not find routes/web.php. Please make sure this script is in the root directory (/home/erp.my-communication.uk/public_html/).");
}

$content = file_get_contents($webPhpPath);

if (strpos($content, "name('generate.attendance')") !== false) {
    echo "<h3>Route 'generate.attendance' is already defined in routes/web.php.</h3>";
} else {
    // We will append the missing route 
    $routeCode = "\n// Added to resolve missing route error \nRoute::get('generate-attendance', [App\\Http\\Controllers\\AttendanceEmployeeController::class, 'index'])->name('generate.attendance')->middleware(['auth', 'XSS']);\n";
    
    // Attempt to append to a logic place if found, else just append at the end of the file.
    $searchString = "name('attendance.export');";
    if (strpos($content, $searchString) !== false) {
        $content = str_replace($searchString, $searchString . $routeCode, $content);
    } else {
        $content .= $routeCode;
    }
    
    if (file_put_contents($webPhpPath, $content)) {
        echo "<h3>Success: Route 'generate.attendance' added to routes/web.php.</h3>";
    } else {
        die("<h3>Error: Failed to write to routes/web.php. Please check file permissions.</h3>");
    }
}

// Clear caches to make sure Laravel picks up the new route and clears corrupted compiled views
echo "<h4>Clearing Laravel Caches...</h4>";
echo "<pre>";
if(function_exists('shell_exec')) {
    echo shell_exec('php artisan route:clear 2>&1') . "\n";
    echo shell_exec('php artisan view:clear 2>&1') . "\n";
    echo shell_exec('php artisan cache:clear 2>&1') . "\n";
} else {
    echo "shell_exec is disabled, please run 'php artisan route:clear' and 'php artisan view:clear' manually via SSH.\n";
}
echo "</pre>";

echo "<h3>All done! The error should be resolved. You can now delete this fix_route.php file for security.</h3>";
