<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaleStoreRequest;
use App\Repositories\SaleRepository;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class SaleController extends Controller
{
    public function __construct(private SaleRepository $repository)
    {
        $this->middleware(function ($request, $next) {
            $action = $request->route()->getActionMethod();
            
            // View sales - allow if has 'view sales' permission
            if (in_array($action, ['index', 'show', 'stats'])) {
                if (!\Illuminate\Support\Facades\Auth::user()->can('view sales')) {
                    return response()->json(['message' => 'Unauthorized - requires view sales permission'], 403);
                }
            }
            // Create sales - allow if has 'create sales' permission
            if (in_array($action, ['store'])) {
                if (!\Illuminate\Support\Facades\Auth::user()->can('create sales')) {
                    return response()->json(['message' => 'Unauthorized - requires create sales permission'], 403);
                }
            }
            // Edit sales - allow if has 'edit sales' permission
            if (in_array($action, ['update'])) {
                if (!\Illuminate\Support\Facades\Auth::user()->can('edit sales')) {
                    return response()->json(['message' => 'Unauthorized - requires edit sales permission'], 403);
                }
            }
            // Delete sales - allow if has 'delete sales' permission
            if (in_array($action, ['destroy'])) {
                if (!\Illuminate\Support\Facades\Auth::user()->can('delete sales')) {
                    return response()->json(['message' => 'Unauthorized - requires delete sales permission'], 403);
                }
            }
            
            return $next($request);
        });
    }

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
    public function stats(Request $request)
    {
        return $this->repository->stats($request->query('tipe'));
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
