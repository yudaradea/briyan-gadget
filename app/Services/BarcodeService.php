<?php

namespace App\Services;

use App\Models\Product;

class BarcodeService
{
    /**
     * Generate a unique barcode for a product.
     * Format: BG-YYYYMMDD-XXXXX (e.g., BG-20260225-00001)
     *
     * Uses withTrashed() to include soft-deleted products,
     * preventing duplicate barcode generation.
     */
    public function generate(): string
    {
        $prefix = 'BG';
        $date = now()->format('Ymd');

        // Include soft-deleted records to avoid duplicate barcodes
        $latestBarcode = Product::withTrashed()
            ->where('barcode', 'like', "{$prefix}-{$date}-%")
            ->orderByDesc('barcode')
            ->value('barcode');

        if ($latestBarcode) {
            $lastNumber = (int) substr($latestBarcode, -5);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        $barcode = sprintf('%s-%s-%05d', $prefix, $date, $nextNumber);

        // Safety check: ensure the barcode doesn't exist (handles edge cases)
        while (Product::withTrashed()->where('barcode', $barcode)->exists()) {
            $nextNumber++;
            $barcode = sprintf('%s-%s-%05d', $prefix, $date, $nextNumber);
        }

        return $barcode;
    }
}
