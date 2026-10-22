<?php

require __DIR__.'/../vendor/autoload.php';

use Illuminate\Support\Facades\Artisan;
use Maatwebsite\Excel\Facades\Excel;

// Bootstrap Laravel
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

header('Content-Type: text/plain; charset=utf-8');

echo "=== Debug Excel Import ===\n\n";

// Check if file is uploaded
if (!isset($_FILES['excel_file'])) {
    echo "ERROR: No file uploaded\n";
    echo "Usage: Upload file via POST with name 'excel_file'\n";
    exit;
}

$file = $_FILES['excel_file'];
$tmpPath = $file['tmp_name'];

echo "File Name: " . $file['name'] . "\n";
echo "File Size: " . $file['size'] . " bytes\n";
echo "File Type: " . $file['type'] . "\n\n";

try {
    // Load the Excel file
    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($tmpPath);
    $spreadsheet = $reader->load($tmpPath);
    $worksheet = $spreadsheet->getActiveSheet();
    
    echo "=== Excel File Structure ===\n";
    echo "Total Rows: " . $worksheet->getHighestRow() . "\n";
    echo "Total Columns: " . $worksheet->getHighestColumn() . "\n\n";
    
    // Get headers (first row)
    echo "=== Headers (Row 1) ===\n";
    $headers = [];
    $highestColumn = $worksheet->getHighestColumn();
    $columnIndex = 1;
    
    for ($col = 'A'; $col <= $highestColumn; $col++) {
        $value = $worksheet->getCell($col . '1')->getValue();
        $headers[] = $value;
        echo "Column $col: '$value'\n";
    }
    echo "\n";
    
    // Show what Laravel Excel will convert headers to
    echo "=== Headers After Laravel Excel Processing ===\n";
    foreach ($headers as $index => $header) {
        $processed = strtolower(str_replace(' ', '_', trim($header)));
        echo "Original: '$header' => Processed: '$processed'\n";
    }
    echo "\n";
    
    // Show first 3 data rows
    echo "=== First 3 Data Rows ===\n";
    for ($row = 2; $row <= min(4, $worksheet->getHighestRow()); $row++) {
        echo "Row $row:\n";
        $colIndex = 0;
        for ($col = 'A'; $col <= $highestColumn; $col++) {
            $value = $worksheet->getCell($col . $row)->getValue();
            $headerName = $headers[$colIndex] ?? "Column $col";
            echo "  [$headerName]: '$value'\n";
            $colIndex++;
        }
        echo "\n";
    }
    
    // Test import logic
    echo "=== Testing Import Logic ===\n";
    
    // Simulate what WithHeadingRow does
    $processedHeaders = [];
    foreach ($headers as $header) {
        $processedHeaders[] = strtolower(str_replace(' ', '_', trim($header)));
    }
    
    echo "Required fields check:\n";
    $requiredFields = ['name', 'sku'];
    foreach ($requiredFields as $field) {
        $exists = in_array($field, $processedHeaders);
        echo "  - '$field': " . ($exists ? "✓ FOUND" : "✗ MISSING") . "\n";
    }
    echo "\n";
    
    // Check first data row
    if ($worksheet->getHighestRow() >= 2) {
        echo "First row data mapping:\n";
        $rowData = [];
        $colIndex = 0;
        for ($col = 'A'; $col <= $highestColumn; $col++) {
            $value = $worksheet->getCell($col . '2')->getValue();
            $key = $processedHeaders[$colIndex] ?? "unknown_$colIndex";
            $rowData[$key] = $value;
            echo "  \$row['$key'] = '$value'\n";
            $colIndex++;
        }
        echo "\n";
        
        // Check if name is empty
        $nameValue = $rowData['name'] ?? null;
        echo "Name field check:\n";
        echo "  Value: " . ($nameValue === null ? "NULL" : "'$nameValue'") . "\n";
        echo "  empty() result: " . (empty($nameValue) ? "TRUE (will skip)" : "FALSE (will import)") . "\n";
        echo "\n";
    }
    
    // Try actual import
    echo "=== Attempting Actual Import ===\n";
    
    try {
        // Login as first user for testing
        $user = \App\Models\User::first();
        if (!$user) {
            echo "ERROR: No users found in database\n";
        } else {
            \Illuminate\Support\Facades\Auth::login($user);
            echo "Logged in as: " . $user->email . "\n";
            
            $import = new \App\Imports\ProductServiceImport();
            $result = Excel::import($import, $tmpPath);
            
            echo "Import completed successfully!\n";
            
            // Count imported records
            $count = \App\Models\ProductService::where('created_by', $user->creatorId())
                ->where('created_at', '>=', now()->subMinutes(1))
                ->count();
            
            echo "Records imported in last minute: $count\n";
        }
        
    } catch (\Exception $e) {
        echo "Import Error: " . $e->getMessage() . "\n";
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    }
    
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Debug Complete ===\n";
