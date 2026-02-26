<?php

namespace App\Http\Controllers;

use App\Http\Requests\Purchase\PurchaseItemStoreRequest;
use App\Http\Requests\Purchase\PurchaseItemUpdateRequest;
use App\Http\Requests\Purchase\PurchaseStoreRequest;
use App\Http\Requests\Purchase\PurchaseUpdateRequest;
use App\Repositories\PurchaseRepository;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class PurchaseController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view purchases', only: ['index', 'show', 'generateInvoiceNumber', 'indexItems']),
            new Middleware('permission:create purchases', only: ['store', 'addItem']),
            new Middleware('permission:edit purchases', only: ['update', 'updateItem']),
            new Middleware('permission:delete purchases', only: ['destroy', 'removeItem']),
        ];
    }

    public function __construct(private PurchaseRepository $repository) {}

    /**
     * List purchases with pagination + search + date filter
     */
    public function index(Request $request)
    {
        return $this->repository->index(
            $request->query('per_page', 10),
            $request->query('search', ''),
            $request->query('start_date'),
            $request->query('end_date'),
            $request->query('supplier_id')
        );
    }

    /**
     * List individual purchase items (broken down per product)
     */
    public function indexItems(Request $request)
    {
        return $this->repository->indexItems(
            $request->query('per_page', 10),
            $request->query('search', ''),
            $request->query('start_date'),
            $request->query('end_date'),
            $request->query('supplier_id'),
            $request->query('no_invoice'),
            $request->query('status'),
            $request->query('category_id')
        );
    }

    /**
     * Show purchase detail with items
     */
    public function show($id)
    {
        return $this->repository->show($id);
    }

    /**
     * Create purchase header
     */
    public function store(PurchaseStoreRequest $request)
    {
        return $this->repository->store($request->validated());
    }

    /**
     * Add product item to purchase (auto-generates barcode)
     */
    public function addItem(PurchaseItemStoreRequest $request, $id)
    {
        $data = $request->validated();

        if ($request->hasFile('foto')) {
            $data['foto'] = $request->file('foto')->store('products', 'public');
        }

        return $this->repository->addItem($id, $data);
    }

    /**
     * Update existing item in purchase (inline edit)
     */
    public function updateItem(PurchaseItemUpdateRequest $request, $purchaseId, $itemId)
    {
        $data = $request->validated();

        if ($request->hasFile('foto')) {
            $data['foto'] = $request->file('foto')->store('products', 'public');
        }

        return $this->repository->updateItem($purchaseId, $itemId, $data);
    }

    /**
     * Remove item from purchase
     */
    public function removeItem($purchaseId, $itemId)
    {
        return $this->repository->removeItem($purchaseId, $itemId);
    }

    /**
     * Update purchase header
     */
    public function update(PurchaseUpdateRequest $request, $id)
    {
        return $this->repository->update($request->validated(), $id);
    }

    /**
     * Delete purchase
     */
    public function destroy($id)
    {
        return $this->repository->destroy($id);
    }

    /**
     * Generate next invoice number
     */
    public function generateInvoiceNumber()
    {
        return response()->json([
            'success' => true,
            'data' => ['no_invoice' => $this->repository->generateInvoiceNumber()],
        ]);
    }
}
