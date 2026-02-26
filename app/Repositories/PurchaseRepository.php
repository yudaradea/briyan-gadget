<?php

namespace App\Repositories;

use App\Helpers\ResponseHelper;
use App\Http\Resources\PaginateResource;
use App\Http\Resources\PurchaseResource;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Services\BarcodeService;
use Illuminate\Support\Facades\DB;

class PurchaseRepository
{
    public function __construct(
        private Purchase $model,
        private BarcodeService $barcodeService
    ) {}

    /**
     * List purchases with pagination, search, and date range
     */
    public function index($perPage, $search, $startDate = null, $endDate = null, $supplierId = null)
    {
        $query = $this->model->newQuery()
            ->with(['supplier', 'user'])
            ->withCount('items')
            ->withSum('items', 'qty')
            ->search($search)
            ->dateRange($startDate, $endDate)
            ->when($supplierId, fn($q) => $q->where('supplier_id', $supplierId))
            ->latest('tanggal')
            ->latest('created_at');

        $data = $query->paginate($perPage);

        return ResponseHelper::success(
            new PaginateResource($data, PurchaseResource::class),
            'Purchases retrieved successfully'
        );
    }

    /**
     * List individual purchase items (broken down per product)
     */
    public function indexItems($perPage, $search, $startDate = null, $endDate = null, $supplierId = null, $invoiceNo = null, $status = null, $categoryId = null)
    {
        $query = PurchaseItem::query()
            ->with([
                'purchase.supplier',
                'product.masterProduct.brand',
                'product.masterProduct.category',
                'product.masterProduct.unit',
                'product.grade',
                'product.saleItems.salesTransaction'
            ])
            ->whereHas('purchase', function ($q) use ($startDate, $endDate, $supplierId, $invoiceNo) {
                $q->dateRange($startDate, $endDate)
                    ->when($supplierId, fn($sq) => $sq->where('supplier_id', $supplierId))
                    ->when($invoiceNo, fn($sq) => $sq->where('no_invoice', 'like', "%{$invoiceNo}%"));
            })
            ->whereHas('product', function ($q) use ($search, $status, $categoryId) {
                $q->search($search)
                    ->when($categoryId, fn($sq) => $sq->where('category_id', $categoryId));

                if ($status === 'sold') {
                    $q->has('saleItems');
                } elseif ($status === 'available') {
                    $q->doesntHave('saleItems');
                }
            })
            ->latest();

        $data = $query->paginate($perPage);

        return ResponseHelper::success(
            new \App\Http\Resources\PaginateResource($data, \App\Http\Resources\PurchaseItemResource::class),
            'Purchase items retrieved successfully'
        );
    }

    /**
     * Show purchase detail with all items and products
     */
    public function show($id)
    {
        $purchase = $this->model->with([
            'supplier',
            'user',
            'items.product.masterProduct.brand',
            'items.product.masterProduct.category',
            'items.product.masterProduct.unit',
            'items.product.grade',
        ])->findOrFail($id);

        return ResponseHelper::success(
            new PurchaseResource($purchase),
            'Purchase retrieved successfully'
        );
    }

    /**
     * Create purchase header
     */
    public function store(array $data)
    {
        $purchase = $this->model->create([
            'no_invoice' => $data['no_invoice'],
            'tanggal' => $data['tanggal'],
            'supplier_id' => $data['supplier_id'],
            'user_id' => \Illuminate\Support\Facades\Auth::id(),
            'keterangan' => $data['keterangan'] ?? null,
            'total' => 0,
        ]);

        $purchase->load(['supplier', 'user']);

        return ResponseHelper::success(
            new PurchaseResource($purchase),
            'Purchase created successfully',
            201
        );
    }

    /**
     * Add item to purchase — auto-generates barcode & creates product
     */
    public function addItem($purchaseId, array $data)
    {
        return DB::transaction(function () use ($purchaseId, $data) {
            $purchase = $this->model->findOrFail($purchaseId);

            // If product_id is provided, we are restocking an existing product
            if (!empty($data['product_id'])) {
                $product = Product::findOrFail($data['product_id']);

                // Update product info if provided (optional, but good for price updates)
                $productUpdateData = [
                    'harga_modal' => $data['harga_beli'],
                    'stok' => $product->stok + ($data['qty'] ?? 1),
                ];

                if (isset($data['harga_jual'])) {
                    $productUpdateData['harga_jual'] = $data['harga_jual'];
                }

                $product->update($productUpdateData);
            } else {
                // Use provided barcode or generate for NEW product
                $barcode = $data['barcode'] ?? $this->barcodeService->generate();

                // Create product with barcode
                $product = Product::create([
                    'master_product_id' => $data['master_product_id'],
                    'barcode' => $barcode,
                    'grade_id' => $data['grade_id'] ?? null,
                    'imei1' => $data['imei1'] ?? null,
                    'imei2' => $data['imei2'] ?? null,
                    'harga_modal' => $data['harga_beli'],
                    'harga_jual' => $data['harga_jual'] ?? $data['harga_beli'],
                    'stok' => $data['qty'] ?? 1,
                    'keterangan' => $data['keterangan'] ?? null,
                    'foto' => $data['foto'] ?? null,
                ]);
            }

            // Create purchase item
            $qty = $data['qty'] ?? 1;
            $hargaBeli = $data['harga_beli'];
            $subtotal = $qty * $hargaBeli;

            $item = PurchaseItem::create([
                'purchase_id' => $purchase->id,
                'product_id' => $product->id,
                'qty' => $qty,
                'harga_beli' => $hargaBeli,
                'subtotal' => $subtotal,
            ]);

            // Recalculate purchase total
            $purchase->recalculateTotal();

            // Load product with relations for response
            $product->load(['masterProduct.brand', 'masterProduct.category', 'masterProduct.unit', 'grade']);

            return ResponseHelper::success([
                'item' => [
                    'id' => $item->id,
                    'product_id' => $product->id,
                    'product' => [
                        'id' => $product->id,
                        'barcode' => $product->barcode,
                        'nama' => $product->nama,
                        'imei1' => $product->imei1,
                        'imei2' => $product->imei2,
                        'brand' => $product->masterProduct?->brand?->nama,
                        'brand_id' => $product->masterProduct?->brand_id,
                        'category' => $product->masterProduct?->category?->nama,
                        'category_id' => $product->masterProduct?->category_id,
                        'grade' => $product->grade?->nama,
                        'grade_id' => $product->grade_id,
                        'unit' => $product->masterProduct?->unit?->nama,
                        'unit_id' => $product->masterProduct?->unit_id,
                        'identifier_type' => $product->masterProduct?->identifier_type ?? 'none',
                        'harga_jual' => (float) $product->harga_jual,
                        'foto' => $product->foto,
                    ],
                    'qty' => $item->qty,
                    'harga_beli' => (float) $item->harga_beli,
                    'subtotal' => (float) $item->subtotal,
                ],
                'purchase_total' => (float) $purchase->fresh()->total,
            ], 'Item added successfully', 201);
        });
    }

    /**
     * Update item in purchase (inline edit)
     */
    public function updateItem($purchaseId, $itemId, array $data)
    {
        return DB::transaction(function () use ($purchaseId, $itemId, $data) {
            $purchase = $this->model->findOrFail($purchaseId);
            $item = PurchaseItem::where('purchase_id', $purchaseId)->findOrFail($itemId);
            $product = $item->product;

            // Update product fields
            $productData = [];
            foreach (['master_product_id', 'grade_id', 'imei1', 'imei2', 'foto'] as $field) {
                if (array_key_exists($field, $data)) {
                    // Jika ada upload foto baru dan foto lama sudah ada, hapus foto lamanya
                    if ($field === 'foto' && $data['foto'] && $product->foto) {
                        \Illuminate\Support\Facades\Storage::disk('public')->delete($product->foto);
                    }
                    $productData[$field] = $data[$field];
                }
            }
            if (isset($data['harga_jual'])) {
                $productData['harga_jual'] = $data['harga_jual'];
            }
            if (isset($data['harga_beli'])) {
                $productData['harga_modal'] = $data['harga_beli'];
            }
            if (!empty($productData)) {
                $product->update($productData);
            }

            // Update purchase item
            $qty = $data['qty'] ?? $item->qty;
            $hargaBeli = $data['harga_beli'] ?? $item->harga_beli;
            $subtotal = $qty * $hargaBeli;

            $item->update([
                'qty' => $qty,
                'harga_beli' => $hargaBeli,
                'subtotal' => $subtotal,
            ]);

            // Recalculate total
            $purchase->recalculateTotal();

            $product->load(['masterProduct.brand', 'masterProduct.category', 'masterProduct.unit', 'grade']);

            return ResponseHelper::success([
                'item' => [
                    'id' => $item->id,
                    'product_id' => $product->id,
                    'product' => [
                        'id' => $product->id,
                        'barcode' => $product->barcode,
                        'nama' => $product->nama,
                        'imei1' => $product->imei1,
                        'imei2' => $product->imei2,
                        'brand' => $product->masterProduct?->brand?->nama,
                        'brand_id' => $product->masterProduct?->brand_id,
                        'category' => $product->masterProduct?->category?->nama,
                        'category_id' => $product->masterProduct?->category_id,
                        'grade' => $product->grade?->nama,
                        'grade_id' => $product->grade_id,
                        'unit' => $product->masterProduct?->unit?->nama,
                        'unit_id' => $product->masterProduct?->unit_id,
                        'identifier_type' => $product->masterProduct?->identifier_type ?? 'none',
                        'harga_jual' => (float) $product->harga_jual,
                        'foto' => $product->foto,
                    ],
                    'qty' => $item->qty,
                    'harga_beli' => (float) $item->harga_beli,
                    'subtotal' => (float) $item->subtotal,
                ],
                'purchase_total' => (float) $purchase->fresh()->total,
            ], 'Item updated successfully');
        });
    }

    /**
     * Remove item from purchase (and its product if unique)
     */
    public function removeItem($purchaseId, $itemId)
    {
        return DB::transaction(function () use ($purchaseId, $itemId) {
            $purchase = $this->model->findOrFail($purchaseId);
            $item = PurchaseItem::where('purchase_id', $purchaseId)->findOrFail($itemId);
            $product = $item->product;

            if ($product) {
                // Check if product is sold
                if ($product->saleItems()->exists()) {
                    throw new \Exception("Barang tidak bisa dihapus karena sudah terjual.");
                }
            }

            // Delete item
            $item->delete();

            // Check if ANY other purchase items OR service parts reference this product
            if ($product) {
                $otherPurchaseItems = PurchaseItem::where('product_id', $product->id)->exists();
                $hasServiceParts = $product->serviceParts()->exists();

                if (!$otherPurchaseItems && !$hasServiceParts) {
                    if ($product->foto) {
                        \Illuminate\Support\Facades\Storage::disk('public')->delete($product->foto);
                    }
                    // Use forceDelete because Product uses SoftDeletes
                    $product->forceDelete();
                }
            }

            // Recalculate total
            $purchase->recalculateTotal();

            return ResponseHelper::success(
                ['purchase_total' => (float) $purchase->fresh()->total],
                'Item removed successfully'
            );
        });
    }

    /**
     * Update purchase header
     */
    public function update(array $data, $id)
    {
        $purchase = $this->model->findOrFail($id);
        $purchase->update($data);
        $purchase->load(['supplier', 'user']);

        return ResponseHelper::success(
            new PurchaseResource($purchase),
            'Purchase updated successfully'
        );
    }

    /**
     * Delete purchase and all its items/products
     */
    public function destroy($id)
    {
        return DB::transaction(function () use ($id) {
            $purchase = $this->model->with('items.product')->findOrFail($id);
            $productsToClean = [];

            // Delete items first and save references to products
            foreach ($purchase->items as $item) {
                if ($item->product) {
                    $productsToClean[$item->product->id] = $item->product;
                }
                $item->delete();
            }

            // Delete header permanently to free up the unique no_invoice
            $purchase->forceDelete();

            // Lastly, clean up orphan products and photos only when truly unused.
            foreach ($productsToClean as $product) {
                $hasPurchaseItems = PurchaseItem::where('product_id', $product->id)->exists();
                $hasSaleItems = $product->saleItems()->exists();
                $hasServiceParts = $product->serviceParts()->exists();

                if ($hasPurchaseItems || $hasSaleItems || $hasServiceParts) {
                    continue;
                }

                if ($product->foto) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($product->foto);
                }
                $product->forceDelete();
            }

            return ResponseHelper::success(null, 'Purchase deleted successfully');
        });
    }

    /**
     * Generate next invoice number
     */
    public function generateInvoiceNumber(): string
    {
        $prefix = 'PUR';
        $date = now()->format('Ymd');

        $latest = $this->model->withTrashed()
            ->where('no_invoice', 'like', "{$prefix}-{$date}-%")
            ->orderByDesc('no_invoice')
            ->value('no_invoice');

        if ($latest) {
            $lastNum = (int) substr($latest, -4);
            $nextNum = $lastNum + 1;
        } else {
            $nextNum = 1;
        }

        return sprintf('%s-%s-%04d', $prefix, $date, $nextNum);
    }
}
