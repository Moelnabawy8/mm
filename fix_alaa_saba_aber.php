<?php
/**
 * Debug Script for Deal Purchase Total Count Issue
 * This script diagnoses why the Total Deals card shows wrong count
 */

// Detect if running from public directory or root
$isInPublic = basename(__DIR__) === 'public';

// Load Laravel
if ($isInPublic) {
    $autoloadPath = __DIR__ . '/../vendor/autoload.php';
    $bootstrapPath = __DIR__ . '/../bootstrap/app.php';
} else {
    $autoloadPath = __DIR__ . '/vendor/autoload.php';
    $bootstrapPath = __DIR__ . '/bootstrap/app.php';
}

if (!file_exists($autoloadPath)) {
    die("<h1>Error: Cannot find vendor/autoload.php</h1>");
}

if (!file_exists($bootstrapPath)) {
    die("<h1>Error: Cannot find bootstrap/app.php</h1>");
}

require $autoloadPath;
$app = require_once $bootstrapPath;
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "<h1>Deal Purchase Count Debug Script</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
    .section { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    .info { color: blue; }
    table { width: 100%; border-collapse: collapse; margin: 10px 0; }
    th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
    th { background: #4CAF50; color: white; }
    pre { background: #f4f4f4; padding: 10px; border-radius: 3px; overflow-x: auto; }
    code { background: #f4f4f4; padding: 2px 5px; border-radius: 3px; }
</style>";

// Simulate the controller logic
echo "<div class='section'>";
echo "<h2>Step 1: Checking Current User</h2>";

try {
    // Try to get authenticated user or use a test user
    $usr = Auth::user();
    
    if (!$usr) {
        echo "<p class='warning'>⚠ No authenticated user. Using first Alaa,Saba,aber user for testing...</p>";
        $usr = App\Models\User::where('type', 'Alaa,Saba,aber')->first();
        
        if (!$usr) {
            echo "<p class='error'>✗ No Alaa,Saba,aber user found. Using first admin user...</p>";
            $usr = App\Models\User::where('type', 'company')->first();
        }
    }
    
    if ($usr) {
        echo "<p class='success'>✓ Testing with user: <strong>{$usr->name}</strong> (ID: {$usr->id}, Type: {$usr->type})</p>";
    } else {
        die("<p class='error'>✗ Cannot find any user to test with.</p></div>");
    }
} catch (Exception $e) {
    die("<p class='error'>✗ Error: " . $e->getMessage() . "</p></div>");
}
echo "</div>";

echo "<div class='section'>";
echo "<h2>Step 2: Building Query (Same as Controller)</h2>";

try {
    $query = App\Models\DealPurchase::with(['deal.lead', 'attachments']);
    
    echo "<p class='info'>Initial query created</p>";
    
    // Check if user should be filtered
    $isFiltered = false;
    if (($usr->hasRole('Sales Employee') || $usr->hasRole('sales employee')) && 
        $usr->type != 'Alaa,Saba,aber') {
        $isFiltered = true;
        echo "<p class='warning'>⚠ User is Sales Employee (not Alaa,Saba,aber), applying lead filter...</p>";
        
        $userLeadIds = App\Models\UserLead::where('user_id', $usr->id)->pluck('lead_id');
        echo "<p>User has " . $userLeadIds->count() . " assigned leads</p>";
        
        $query->whereHas('deal', function($q) use ($userLeadIds) {
            $q->whereIn('lead_id', $userLeadIds);
        });
    } else {
        echo "<p class='success'>✓ User type is '{$usr->type}' - NO filtering applied (should see all records)</p>";
    }
    
} catch (Exception $e) {
    die("<p class='error'>✗ Error building query: " . $e->getMessage() . "</p></div>");
}
echo "</div>";

echo "<div class='section'>";
echo "<h2>Step 3: Counting Records</h2>";

try {
    // Get count BEFORE get()
    $totalCount = $query->count();
    echo "<p class='info'><strong>Total Count (from query->count()):</strong> <span class='success' style='font-size: 24px;'>{$totalCount}</span></p>";
    
    // Get actual records
    $dealPurchases = $query->orderBy('id', 'desc')->get();
    $collectionCount = $dealPurchases->count();
    echo "<p class='info'><strong>Collection Count (from get()->count()):</strong> <span class='info' style='font-size: 24px;'>{$collectionCount}</span></p>";
    
    if ($totalCount != $collectionCount) {
        echo "<p class='warning'>⚠ WARNING: Counts don't match! This might indicate pagination or limiting.</p>";
    } else {
        echo "<p class='success'>✓ Counts match - no pagination detected</p>";
    }
    
} catch (Exception $e) {
    echo "<p class='error'>✗ Error counting: " . $e->getMessage() . "</p>";
}
echo "</div>";

echo "<div class='section'>";
echo "<h2>Step 4: Checking Database Directly</h2>";

try {
    // Count all deal purchases in database
    $totalInDb = DB::table('deal_purchases')->count();
    echo "<p class='info'><strong>Total in Database:</strong> {$totalInDb}</p>";
    
    // Count by created_by
    $byCreatedBy = DB::table('deal_purchases')
        ->select('created_by', DB::raw('count(*) as count'))
        ->groupBy('created_by')
        ->get();
    
    echo "<table>";
    echo "<tr><th>Created By (User ID)</th><th>Count</th></tr>";
    foreach ($byCreatedBy as $row) {
        $user = App\Models\User::find($row->created_by);
        $userName = $user ? $user->name : 'Unknown';
        echo "<tr><td>{$userName} (ID: {$row->created_by})</td><td>{$row->count}</td></tr>";
    }
    echo "</table>";
    
    // Check if there's a created_by filter being applied
    if ($usr->ownerId()) {
        $forOwner = DB::table('deal_purchases')->where('created_by', $usr->ownerId())->count();
        echo "<p class='info'><strong>For owner ID {$usr->ownerId()}:</strong> {$forOwner}</p>";
    }
    
} catch (Exception $e) {
    echo "<p class='error'>✗ Error: " . $e->getMessage() . "</p>";
}
echo "</div>";

echo "<div class='section'>";
echo "<h2>Step 5: Checking Controller Code</h2>";

$controllerPath = __DIR__ . '/../app/Http/Controllers/DealPurchaseController.php';
if (file_exists($controllerPath)) {
    $controllerCode = file_get_contents($controllerPath);
    
    // Check if totalCount is calculated
    if (strpos($controllerCode, '$totalCount = $query->count();') !== false) {
        echo "<p class='success'>✓ Controller has: <code>\$totalCount = \$query->count();</code></p>";
    } else {
        echo "<p class='error'>✗ Controller is missing: <code>\$totalCount = \$query->count();</code></p>";
    }
    
    // Check if totalCount is passed to view
    if (strpos($controllerCode, "compact('dealPurchases', 'deals', 'totalCount')") !== false) {
        echo "<p class='success'>✓ Controller passes totalCount to view</p>";
    } else {
        echo "<p class='error'>✗ Controller doesn't pass totalCount to view</p>";
    }
    
    // Check order of operations
    $countPos = strpos($controllerCode, '$totalCount = $query->count();');
    $getPos = strpos($controllerCode, '$dealPurchases = $query->orderBy');
    
    if ($countPos !== false && $getPos !== false) {
        if ($countPos < $getPos) {
            echo "<p class='success'>✓ totalCount is calculated BEFORE get() - Correct order</p>";
        } else {
            echo "<p class='error'>✗ totalCount is calculated AFTER get() - Wrong order!</p>";
            echo "<p class='warning'>FIX: Move <code>\$totalCount = \$query->count();</code> BEFORE <code>\$dealPurchases = \$query->get();</code></p>";
        }
    }
    
} else {
    echo "<p class='error'>✗ Cannot find controller file</p>";
}
echo "</div>";

echo "<div class='section'>";
echo "<h2>Step 6: Checking View Code</h2>";

$viewPath = __DIR__ . '/../resources/views/admin/deal_purchase/index.blade.php';
if (file_exists($viewPath)) {
    $viewCode = file_get_contents($viewPath);
    
    // Check how totalDeals is calculated
    if (strpos($viewCode, '$totalDeals = $totalCount ?? $dealPurchases->count();') !== false) {
        echo "<p class='success'>✓ View uses: <code>\$totalDeals = \$totalCount ?? \$dealPurchases->count();</code></p>";
    } elseif (strpos($viewCode, '$totalDeals = $dealPurchases->count();') !== false) {
        echo "<p class='error'>✗ View uses: <code>\$totalDeals = \$dealPurchases->count();</code> (missing \$totalCount)</p>";
        echo "<p class='warning'>FIX: Change to <code>\$totalDeals = \$totalCount ?? \$dealPurchases->count();</code></p>";
    } else {
        echo "<p class='warning'>⚠ Cannot find totalDeals calculation in view</p>";
    }
    
} else {
    echo "<p class='error'>✗ Cannot find view file</p>";
}
echo "</div>";

echo "<div class='section'>";
echo "<h2>Step 7: Testing What View Would Receive</h2>";

try {
    // Simulate what the view receives
    $testTotalCount = $totalCount ?? null;
    $testCollectionCount = $dealPurchases->count();
    
    echo "<p><strong>Variables passed to view:</strong></p>";
    echo "<ul>";
    echo "<li><code>\$totalCount</code>: " . ($testTotalCount !== null ? "<span class='success'>{$testTotalCount}</span>" : "<span class='error'>NOT SET</span>") . "</li>";
    echo "<li><code>\$dealPurchases->count()</code>: <span class='info'>{$testCollectionCount}</span></li>";
    echo "</ul>";
    
    // Simulate view calculation
    $viewTotalDeals = $testTotalCount ?? $testCollectionCount;
    echo "<p><strong>What view calculates:</strong> <code>\$totalDeals = \$totalCount ?? \$dealPurchases->count()</code></p>";
    echo "<p style='font-size: 20px;'><strong>Result:</strong> <span class='success'>{$viewTotalDeals}</span></p>";
    
    if ($viewTotalDeals == 10 && $totalInDb > 10) {
        echo "<p class='error'>✗ PROBLEM FOUND: View shows 10 but database has {$totalInDb}</p>";
        echo "<p class='warning'>This suggests pagination or a limit is being applied somewhere</p>";
    }
    
} catch (Exception $e) {
    echo "<p class='error'>✗ Error: " . $e->getMessage() . "</p>";
}
echo "</div>";

echo "<div class='section'>";
echo "<h2>Summary & Diagnosis</h2>";

$issues = [];
$fixes = [];

if (!isset($totalCount) || $totalCount != $totalInDb) {
    $issues[] = "totalCount ({$totalCount}) doesn't match database total ({$totalInDb})";
    $fixes[] = "Check if there's a created_by filter or other filter being applied";
}

if ($totalCount == 10 && $totalInDb > 10) {
    $issues[] = "Count is exactly 10 - suggests pagination limit";
    $fixes[] = "Check if query has ->limit(10) or ->paginate(10) somewhere";
}

if (count($issues) > 0) {
    echo "<h3 class='error'>Issues Found:</h3>";
    echo "<ol>";
    foreach ($issues as $issue) {
        echo "<li class='error'>{$issue}</li>";
    }
    echo "</ol>";
    
    echo "<h3 class='info'>Recommended Fixes:</h3>";
    echo "<ol>";
    foreach ($fixes as $fix) {
        echo "<li class='info'>{$fix}</li>";
    }
    echo "</ol>";
} else {
    echo "<p class='success'>✓ No obvious issues found!</p>";
    echo "<p class='info'>Expected count: <strong>{$totalCount}</strong></p>";
    echo "<p class='info'>If the page still shows 10, try:</p>";
    echo "<ul>";
    echo "<li>Clear browser cache (Ctrl+Shift+Delete)</li>";
    echo "<li>Hard refresh the page (Ctrl+F5)</li>";
    echo "<li>Clear Laravel cache: <code>php artisan cache:clear && php artisan view:clear</code></li>";
    echo "</ul>";
}

echo "</div>";

echo "<div class='section'>";
echo "<p><em>Script completed at " . date('Y-m-d H:i:s') . "</em></p>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ol>";
echo "<li>Review the findings above</li>";
echo "<li>If issues found, apply the recommended fixes</li>";
echo "<li>Clear all caches</li>";
echo "<li>Test the page again</li>";
echo "</ol>";
echo "</div>";
