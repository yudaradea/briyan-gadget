<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaleStoreRequest;
use App\Repositories\SaleRepository;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    public function __construct(private SaleRepository $repository) {}

    /**
     * Display a listing of sales
     */
    public function index(Request $request)
    {
        return $this->repository->index(
            $request->query('per_page', 10),
            $request->query('search'),
            $request->query('start_date'),
            $request->query('end_date'),
            $request->query('tipe'),
            $request->query('user_id'),
            $request->query('sales_rep_id')
        );
    }

    /**
     * Get sales statistics
     */
    public function stats()
    {
        return $this->repository->stats();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(SaleStoreRequest $request)
    {
        return $this->repository->store($request->validated());
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        return $this->repository->show($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(SaleStoreRequest $request, $id)
    {
        return $this->repository->update($id, $request->validated());
    }

    /**
     * Delete the specified resource.
     */
    public function destroy($id)
    {
        return $this->repository->destroy($id);
    }
}
