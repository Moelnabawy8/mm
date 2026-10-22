<?php

namespace App\Imports;

use App\Models\ProductService;
use App\Models\ProductServiceCategory;
use App\Models\ProductServiceUnit;
use App\Models\Tax;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\Auth;

class ProductServiceImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        $user = Auth::user();
        $creatorId = $user->creatorId();

        // Clean BOM from array keys
        $cleanRow = [];
        foreach ($row as $key => $value) {
            $cleanKey = preg_replace('/^\x{FEFF}/u', '', $key);
            $cleanRow[$cleanKey] = $value;
        }
        $row = $cleanRow;

        // Helper to check if row is empty
        if (empty($row['name']) || trim($row['name']) === '') {
            return null;
        }

        // Find or create Category
        $categoryId = null;
        $categoryName = $row['category'] ?? $row['category_name'] ?? null;
        if (!empty($categoryName)) {
            $category = ProductServiceCategory::where('created_by', $creatorId)
                ->where('name', $categoryName)
                ->first();

            if (!$category) {
                $category = ProductServiceCategory::create([
                    'name' => $categoryName,
                    'created_by' => $creatorId,
                    'type' => 'product & service'
                ]);
            }
            $categoryId = $category->id;
        }

        // Find or create Unit
        $unitId = null;
        $unitName = $row['unit'] ?? $row['unit_name'] ?? null;
        if (!empty($unitName)) {
            $unit = ProductServiceUnit::where('created_by', $creatorId)
                ->where('name', $unitName)
                ->first();

            if (!$unit) {
                $unit = ProductServiceUnit::create([
                    'name' => $unitName,
                    'created_by' => $creatorId
                ]);
            }
            $unitId = $unit->id;
        } else {
            // Create or get default unit if none specified
            $defaultUnit = ProductServiceUnit::where('created_by', $creatorId)
                ->where('name', 'Default')
                ->first();

            if (!$defaultUnit) {
                $defaultUnit = ProductServiceUnit::create([
                    'name' => 'Default',
                    'created_by' => $creatorId
                ]);
            }
            $unitId = $defaultUnit->id;
        }

        // Find Tax (multiple taxes separated by comma?)
        // For simplicity, assuming single tax by name for now, or just ID if provided.
        $taxId = 0; // Default
        $taxName = $row['tax'] ?? $row['tax_name'] ?? null;
        if (!empty($taxName)) {
            $tax = Tax::where('created_by', $creatorId)
                ->where('name', $taxName)
                ->first();
            if ($tax) {
                $taxId = $tax->id;
            }
        }

        // Check if product exists to avoid duplicates (optional, but good practice)
        // or just create new. user didn't specify update logic.
        // I'll assume create new.

        return new ProductService([
            'name' => $row['name'],
            'sku' => $row['sku'],
            'sale_price' => $row['sale_price'] ?? 0,
            'purchase_price' => $row['purchase_price'] ?? 0,
            'quantity' => $row['quantity'] ?? 0,
            'tax_id' => $taxId,
            'category_id' => $categoryId,
            'unit_id' => $unitId,
            'type' => $row['type'] ?? 'service',
            'description' => $row['description'] ?? null,
            'created_by' => $creatorId,
        ]);
    }
}
