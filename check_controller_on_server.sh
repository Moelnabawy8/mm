#!/bin/bash

echo "=== Checking if Controller was uploaded correctly ==="
echo ""

echo "1. Checking for the fix in ProductServiceController.php:"
if grep -q "try user's categories first, then any" app/Http/Controllers/ProductServiceController.php; then
    echo "✓ Controller HAS the latest fix"
else
    echo "❌ Controller DOES NOT have the latest fix"
    echo ""
    echo "Current category lookup code:"
    grep -A 5 "Get first category as default" app/Http/Controllers/ProductServiceController.php | head -10
fi

echo ""
echo "2. Checking for existing products with same SKU:"
php artisan tinker --execute="
\$skus = ['00BG102', 'T0F201', 'L0912P'];
foreach (\$skus as \$sku) {
    \$product = App\Models\ProductService::where('sku', \$sku)->first();
    if (\$product) {
        echo 'SKU ' . \$sku . ' already exists (ID: ' . \$product->id . ')' . PHP_EOL;
    } else {
        echo 'SKU ' . \$sku . ' is available' . PHP_EOL;
    }
}
"

echo ""
echo "=== Check Complete ==="
