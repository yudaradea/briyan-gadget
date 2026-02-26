<?php

namespace App\Repositories;

use App\Helpers\ResponseHelper;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class ProductRepository
{
    public function __construct(private Product $model) {}

    /**
     * Search products by exact barcode or IMEI (for scanner)
     */
    public function scan($code)
    {
        $product = $this->model->with(['masterProduct.brand', 'masterProduct.category', 'masterProduct.unit', 'grade'])
            ->inStock()
            ->scan($code)
            ->first();

        if (!$product) {
            return ResponseHelper::error('Product not found or out of stock', 404);
        }

        $availableStock = $this->resolveAvailableStock($product);

        return ResponseHelper::success([
            'id' => $product->id,
            'barcode' => $product->barcode,
            'nama' => $product->masterProduct->nama,
            'imei1' => $product->imei1,
            'imei2' => $product->imei2,
            'harga_jual' => (float) $product->harga_jual,
            'stok' => $availableStock,
            'brand' => $product->masterProduct->brand?->nama,
            'category' => $product->masterProduct->category?->nama,
            'unit' => $product->masterProduct->unit ? ['nama' => $product->masterProduct->unit->nama] : null,
            'grade' => $product->grade ? ['nama' => $product->grade->nama] : null,
            'foto' => $product->foto,
        ], 'Product found');
    }

    /**
     * Search products by keyword (for text search autocomplete)
     */
    public function search($keyword, $category = null, $includeOutStock = false)
    {
        $query = $this->model->with(['masterProduct.brand', 'masterProduct.category', 'masterProduct.unit', 'grade'])
            ->when(!$includeOutStock, fn($q) => $q->inStock())
            ->search($keyword);

        if ($category) {
            $query->whereHas('masterProduct.category', function ($q) use ($category) {
                $q->where('nama', 'like', '%' . $category . '%');
            });
        }

        $products = $query->take(10) // Limit results for autocomplete
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'master_product_id' => $product->master_product_id,
                    'barcode' => $product->barcode,
                    'nama' => $product->masterProduct->nama,
                    'imei1' => $product->imei1,
                    'imei2' => $product->imei2,
                    'harga_jual' => (float) $product->harga_jual,
                    'harga_modal' => (float) $product->harga_modal,
                    'stok' => $this->resolveAvailableStock($product),
                    'brand_id' => $product->masterProduct->brand_id,
                    'category_id' => $product->masterProduct->category_id,
                    'unit_id' => $product->masterProduct->unit_id,
                    'brand' => $product->masterProduct->brand?->nama,
                    'category' => $product->masterProduct->category?->nama,
                    'unit' => $product->masterProduct->unit ? ['nama' => $product->masterProduct->unit->nama] : null,
                    'grade' => $product->grade ? ['nama' => $product->grade->nama] : null,
                    'foto' => $product->foto,
                ];
            });

        return ResponseHelper::success($products, 'Products retrieved successfully');
    }

    public function groupedStock($perPage = 10, $search = null, $categoryId = null, $brandId = null)
    {
        $query = Product::query()
            ->join('master_products', 'products.master_product_id', '=', 'master_products.id')
            ->leftJoin('brands', 'master_products.brand_id', '=', 'brands.id')
            ->leftJoin('categories', 'master_products.category_id', '=', 'categories.id')
            ->leftJoin('grades', 'products.grade_id', '=', 'grades.id')
            ->leftJoin('units', 'master_products.unit_id', '=', 'units.id')
            ->select(
                'products.master_product_id',
                'master_products.nama',
                'master_products.brand_id',
                'master_products.category_id',
                'products.grade_id',
                'master_products.unit_id',
                DB::raw('MAX(products.harga_jual) as harga_jual'),
                DB::raw('SUM(products.stok) as total_stok'),
                'brands.nama as brand_nama',
                'categories.nama as category_nama',
                'grades.nama as grade_nama',
                'units.nama as unit_nama'
            )
            ->groupBy(
                'products.master_product_id',
                'master_products.nama',
                'master_products.brand_id',
                'master_products.category_id',
                'products.grade_id',
                'master_products.unit_id',
                'brands.nama',
                'categories.nama',
                'grades.nama',
                'units.nama'
            )
            ->having('total_stok', '>', 0)
            ->when($search, function ($q) use ($search) {
                $q->where('master_products.nama', 'like', "%{$search}%");
            })
            ->when($categoryId, fn($q) => $q->where('master_products.category_id', $categoryId))
            ->when($brandId, fn($q) => $q->where('master_products.brand_id', $brandId))
            ->orderBy('master_products.nama');

        $data = $query->paginate($perPage);

        // Transform data format for frontend consistency
        $data->getCollection()->transform(function ($item) {
            return [
                'master_product_id' => $item->master_product_id,
                'nama' => $item->nama,
                'total_stok' => (int) $item->total_stok,
                'harga_jual' => (float) $item->harga_jual,
                'brand' => ['nama' => $item->brand_nama],
                'category' => ['nama' => $item->category_nama],
                'grade' => $item->grade_nama ? ['nama' => $item->grade_nama] : null,
                'unit' => ['nama' => $item->unit_nama],
            ];
        });

        return ResponseHelper::success($data, 'Grouped stock retrieved successfully');
    }

    private function resolveAvailableStock(Product $product): int
    {
        $identifierType = $product->masterProduct?->identifier_type ?? 'none';
        if ($identifierType !== 'none') {
            return (int) $product->stok;
        }

        return (int) Product::query()
            ->where('master_product_id', $product->master_product_id)
            ->where('grade_id', $product->grade_id)
            ->sum('stok');
    }
}
