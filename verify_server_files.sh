#!/bin/bash

echo "=== Checking Server Files ==="
echo ""

# Check if import_modal.blade.php has the fix
echo "1. Checking import_modal.blade.php for 'hasRequiredColumns'..."
if grep -q "hasRequiredColumns" resources/views/productservice/import_modal.blade.php; then
    echo "✓ File HAS the fix"
else
    echo "❌ File DOES NOT have the fix - needs to be uploaded!"
fi
echo ""

# Check if ProductServiceController has the fix
echo "2. Checking ProductServiceController.php for 'Get first category as default'..."
if grep -q "Get first category as default" app/Http/Controllers/ProductServiceController.php; then
    echo "✓ File HAS the fix"
else
    echo "❌ File DOES NOT have the fix - needs to be uploaded!"
fi
echo ""

# Show current validation logic
echo "3. Current validation in import_modal.blade.php:"
grep -A 5 "total_selection = " resources/views/productservice/import_modal.blade.php | head -10
echo ""

echo "=== Check Complete ==="
