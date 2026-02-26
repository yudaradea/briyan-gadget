<?php

namespace App\Http\Controllers;

use App\Http\Requests\Service\ServiceAddPartRequest;
use App\Http\Requests\Service\ServiceStatusUpdateRequest;
use App\Http\Requests\Service\ServiceStoreRequest;
use App\Http\Requests\Service\ServiceUpdateRequest;
use App\Repositories\ServiceRepository;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ServiceController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view services', only: ['index', 'show']),
            new Middleware('permission:create services', only: ['store', 'addPart']),
            new Middleware('permission:edit services', only: ['update', 'updateStatus']),
            new Middleware('permission:delete services', only: ['destroy', 'removePart']),
        ];
    }

    public function __construct(private ServiceRepository $repository) {}

    public function index(Request $request)
    {
        return $this->repository->index(
            $request->query('per_page', 10),
            $request->query('search', ''),
            $request->query('status'),
            $request->query('start_date'),
            $request->query('end_date')
        );
    }

    public function show($id)
    {
        return $this->repository->show($id);
    }

    public function store(ServiceStoreRequest $request)
    {
        return $this->repository->store($request->validated());
    }

    public function update(ServiceUpdateRequest $request, $id)
    {
        return $this->repository->update($id, $request->validated());
    }

    public function updateStatus(ServiceStatusUpdateRequest $request, $id)
    {
        return $this->repository->updateStatus($id, $request->validated());
    }

    public function addPart(ServiceAddPartRequest $request, $id)
    {
        try {
            return $this->repository->addPart($id, $request->validated());
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function removePart($id, $partId)
    {
        return $this->repository->removePart($id, $partId);
    }

    public function destroy($id)
    {
        try {
            return $this->repository->destroy($id);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
