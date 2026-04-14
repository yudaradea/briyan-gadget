<?php

namespace App\Repositories;

use App\Helpers\ResponseHelper;
use App\Http\Resources\PaginateResource;
use App\Http\Resources\PurchaseResource;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\SaleItem;
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
    public function indexItems($perPage, $search, $startDate = null, $endDate = null, $supplierId = null, $invoiceNo = null, $status = null, $brandId = null)
    {
        $baseQuery = PurchaseItem::query()
            ->whereHas('purchase', function ($q) use ($startDate, $endDate, $supplierId, $invoiceNo) {
                $q->dateRange($startDate, $endDate)
                    ->when($supplierId, fn($sq) => $sq->where('supplier_id', $supplierId))
                    ->when($invoiceNo, fn($sq) => $sq->where('no_invoice', 'like', "%{$invoiceNo}%"));
            })
            ->whereHas('product', function ($q) use ($status, $brandId) {
                $q->when($brandId, fn($sq) => $sq->whereHas('masterProduct', fn($mq) => $mq->where('brand_id', $brandId)));

                if ($status === 'sold') {
                    $q->has('saleItems');
                } elseif ($status === 'available') {
                    $q->doesntHave('saleItems');
                }
            })
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sq) use ($search) {
                    $sq->whereHas('product', fn($pq) => $pq->search($search))
                        ->orWhereHas('purchase', fn($pq) => $pq->where('no_invoice', 'like', "%{$search}%"));
                });
            });

        // Summary totals from ALL matching records (across all pages)
        $totalItem  = (clone $baseQuery)->count();
        $totalModal = (clone $baseQuery)->sum(DB::raw('harga_beli * COALESCE(qty, 1)'));
        $totalJual  = (clone $baseQuery)
            ->join('products', 'purchase_items.product_id', '=', 'products.id')
            ->selectRaw('COALESCE(SUM(products.harga_jual * COALESCE(purchase_items.qty, 1)), 0) as total')
            ->value('total');

        $data = (clone $baseQuery)
            ->with([
                'purchase.supplier',
                'product.masterProduct.brand',
                'product.unit',
                'product.grade',
                'product.saleItems.salesTransaction'
            ])
            ->latest()
            ->paginate($perPage);

        $paginateArray = (new \App\Http\Resources\PaginateResource($data, \App\Http\Resources\PurchaseItemResource::class))->toArray(request());
        $paginateArray['summary'] = [
            'total_item'  => (int) $totalItem,
            'total_modal' => (float) ($totalModal ?? 0),
            'total_jual'  => (float) ($totalJual ?? 0),
        ];

        return ResponseHelper::success($paginateArray, 'Purchase items retrieved successfully');
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
            'items.product.unit',
            'items.product.grade',
            'items.product.saleItems.salesTransaction',
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
                if (array_key_exists('unit_id', $data)) {
                    $productUpdateData['unit_id'] = $data['unit_id'] ?: null;
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
                    'unit_id' => $data['unit_id'] ?? null,
                    'imei1' => $data['imei1'] ?? null,
                    'imei2' => $data['imei2'] ?? null,
                    'harga_modal' => $data['harga_beli'],
                    'harga_jual' => $data['harga_jual'] ?? 0,
                    'stok' => $data['qty'] ?? 1,
                    'keterangan' => $data['keterangan'] ?? null,
                    'foto' => $data['foto'] ?? null,
                ]);

            }

            // Create purchase item
            $qty = $data['qty'] ?? 1;
            $hargaBeli = $data['harga_beli'];
            $subtotal = $qty * $hargaBeli;
            $nextLineOrder = ((int) $purchase->items()->max('line_order')) + 1;

            $item = PurchaseItem::create([
                'purchase_id' => $purchase->id,
                'product_id' => $product->id,
                'line_order' => $nextLineOrder,
                'qty' => $qty,
                'harga_beli' => $hargaBeli,
                'subtotal' => $subtotal,
            ]);

            // Recalculate purchase total
            $purchase->recalculateTotal();

            // Load product with relations for response
            $product->load(['masterProduct.brand', 'unit', 'grade']);

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
                        'grade' => $product->grade?->nama,
                        'grade_id' => $product->grade_id,
                        'unit' => $product->unit?->nama,
                        'unit_id' => $product->unit_id,
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
            $product = $item->product()->with('masterProduct')->firstOrFail();

            // Update product fields
            $productData = [];
            foreach (['master_product_id', 'grade_id', 'unit_id', 'imei1', 'imei2', 'foto'] as $field) {
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

            // Sync hpp_total on sale items when harga_beli changes
            if (isset($data['harga_beli'])) {
                $newHargaBeli = (float) $data['harga_beli'];

                // Update sale items where this product was the selected item in POS
                $affectedSaleItems = SaleItem::where('product_id', $product->id)->get();
                foreach ($affectedSaleItems as $saleItem) {
                    // Update allocations for this sale item that came from this product lot
                    $saleItem->allocations()
                        ->where('product_id', $product->id)
                        ->each(fn($alloc) => $alloc->update([
                            'harga_modal' => $newHargaBeli,
                            'subtotal_hpp' => $newHargaBeli * $alloc->qty,
                        ]));

                    $saleItem->update([
                        'hpp_total' => $newHargaBeli * $saleItem->qty,
                    ]);
                }
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

            $product->load(['masterProduct.brand', 'unit', 'grade']);

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
                        'grade' => $product->grade?->nama,
                        'grade_id' => $product->grade_id,
                        'unit' => $product->unit?->nama,
                        'unit_id' => $product->unit_id,
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
                // Reduce stock back
                $product->update(['stok' => max(0, $product->stok - $item->qty)]);
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
                    // Reduce stock back
                    $item->product->update(['stok' => max(0, $item->product->stok - $item->qty)]);
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
        $prefix = 'INV';
        $date = date('ymd');

        $latest = $this->model->withTrashed()
            ->where('no_invoice', 'like', "{$prefix}{$date}%")
            ->whereRaw('LENGTH(no_invoice) <= 12')
            ->orderByDesc('no_invoice')
            ->value('no_invoice');

        if ($latest) {
            $lastNum = (int) substr($latest, 9);
            $nextNum = $lastNum + 1;
        } else {
            $nextNum = 1;
        }

        $invoice = sprintf('%s%s%03d', $prefix, $date, $nextNum);

        while ($this->model->withTrashed()->where('no_invoice', $invoice)->exists()) {
            $nextNum++;
            $invoice = sprintf('%s%s%03d', $prefix, $date, $nextNum);
        }

        return $invoice;
    }
}
