<?php

namespace App\Repositories;

use App\Helpers\ResponseHelper;
use App\Models\ServiceOrder;
use App\Models\ServicePart;
use App\Models\Product;
use App\Http\Resources\PaginateResource;
use Illuminate\Support\Facades\DB;

class ServiceRepository
{
    public function __construct(private ServiceOrder $model) {}

    public function index($perPage, $search, $status = null, $startDate = null, $endDate = null)
    {
        $query = $this->model->newQuery()
            ->withCount('parts')
            ->search($search)
            ->status($status)
            ->dateRange($startDate, $endDate)
            ->latest('tanggal_masuk')
            ->latest('created_at');

        $data = $query->paginate($perPage);

        return ResponseHelper::success(
            new PaginateResource($data, \App\Http\Resources\ServiceOrderResource::class),
            'Service orders retrieved successfully'
        );
    }

    public function show($id)
    {
        $service = $this->model->with(['parts.product', 'salesTransaction'])->findOrFail($id);
        return ResponseHelper::success(
            new \App\Http\Resources\ServiceOrderResource($service),
            'Service order details retrieved'
        );
    }

    public function store(array $data)
    {
        return DB::transaction(function () use ($data) {
            $service = $this->model->create([
                'nama_pelanggan' => $data['nama_pelanggan'],
                'no_hp_pelanggan' => $data['no_hp_pelanggan'] ?? null,
                'merk_hp' => $data['merk_hp'],
                'tipe_hp' => $data['tipe_hp'],
                'kerusakan' => $data['kerusakan'],
                'imei_hp' => $data['imei_hp'] ?? null,
                'kelengkapan' => $data['kelengkapan'] ?? null,
                'biaya_jasa' => $data['biaya_jasa'] ?? 0,
                'status' => 'dikerjakan',
                'tanggal_masuk' => $data['tanggal_masuk'] ?? now()->toDateString(),
            ]);

            if (!empty($data['parts'])) {
                foreach ($data['parts'] as $part) {
                    $product = Product::findOrFail($part['product_id']);

                    // Validate stock
                    if ($product->stok < $part['qty']) {
                        throw new \Exception("Stok {$product->nama} tidak mencukupi.");
                    }

                    ServicePart::create([
                        'service_order_id' => $service->id,
                        'product_id' => $product->id,
                        'nama_part' => $product->nama,
                        'qty' => $part['qty'],
                        'harga_satuan' => $part['harga_satuan'],
                        'subtotal' => $part['qty'] * $part['harga_satuan'],
                    ]);

                    // Deduct stock
                    $product->decrement('stok', $part['qty']);
                }
            }

            return ResponseHelper::success(
                new \App\Http\Resources\ServiceOrderResource($service->load('parts.product')),
                'Service order created successfully',
                201
            );
        });
    }

    public function update($id, array $data)
    {
        $service = $this->model->findOrFail($id);
        $service->update($data);

        return ResponseHelper::success(
            new \App\Http\Resources\ServiceOrderResource($service->fresh()),
            'Service order updated successfully'
        );
    }

    public function updateStatus($id, array $data)
    {
        return DB::transaction(function () use ($id, $data) {
            $service = $this->model->with(['parts.product'])->findOrFail($id);
            $updateData = [];
            $restoreParts = array_key_exists('restore_parts', $data)
                ? (bool) $data['restore_parts']
                : true;
            $statusToBatal = array_key_exists('status', $data)
                && $data['status'] === 'batal'
                && $service->status !== 'batal';

            if (array_key_exists('status', $data) && $data['status'] !== null) {
                $updateData['status'] = $data['status'];
                if ($data['status'] === 'selesai' && !$service->tanggal_selesai) {
                    $updateData['tanggal_selesai'] = now()->toDateString();
                }
                if ($data['status'] === 'batal') {
                    $updateData['tanggal_selesai'] = null;
                }
            }

            if (array_key_exists('status_pengambilan', $data) && $data['status_pengambilan'] !== null) {
                $updateData['status_pengambilan'] = $data['status_pengambilan'];
            }

            if (array_key_exists('catatan_teknisi', $data)) {
                $updateData['catatan_teknisi'] = $data['catatan_teknisi'];
            }

            if ($statusToBatal) {
                if ($restoreParts) {
                    foreach ($service->parts as $part) {
                        if ($part->product) {
                            $part->product->increment('stok', $part->qty);
                        }
                    }
                    $service->parts()->delete();
                }
                $updateData['status_pengambilan'] = 'belum_diambil';
            }

            $service->update($updateData);

            return ResponseHelper::success(
                new \App\Http\Resources\ServiceOrderResource(
                    $service->fresh()->load(['parts.product', 'salesTransaction'])
                ),
                "Status updated successfully"
            );
        });
    }

    public function addPart($id, array $data)
    {
        return DB::transaction(function () use ($id, $data) {
            $service = $this->model->findOrFail($id);
            $product = Product::findOrFail($data['product_id']);

            // Validate stock
            if ($product->stok < $data['qty']) {
                throw new \Exception("Stok {$product->nama} tidak mencukupi.");
            }

            $part = ServicePart::create([
                'service_order_id' => $service->id,
                'product_id' => $product->id,
                'nama_part' => $product->nama,
                'qty' => $data['qty'],
                'harga_satuan' => $data['harga_satuan'] ?? $product->harga_jual,
                'subtotal' => $data['qty'] * ($data['harga_satuan'] ?? $product->harga_jual),
            ]);

            // Deduct stock
            $product->decrement('stok', $data['qty']);

            return ResponseHelper::success(null, 'Part added successfully');
        });
    }

    public function removePart($id, $partId)
    {
        return DB::transaction(function () use ($id, $partId) {
            $part = ServicePart::where('service_order_id', $id)->findOrFail($partId);
            $product = $part->product;

            // Restore stock
            if ($product) {
                $product->increment('stok', $part->qty);
            }

            $part->delete();

            return ResponseHelper::success(null, 'Part removed successfully');
        });
    }

    public function destroy($id)
    {
        $service = $this->model->findOrFail($id);

        // If has parts, restore stock? Or prevent deletion?
        // Typically prevent deletion if completed.
        /** @var \App\Models\User|null $authuser */
        $authuser = \Illuminate\Support\Facades\Auth::user();
        if (!$authuser || !$authuser->hasRole('super-admin')) {
            if ($service->status === 'selesai' || $service->status_pengambilan === 'sudah_diambil') {
                throw new \Exception("Service yang sudah selesai / sudah diambil tidak dapat dihapus.");
            }
        }

        return DB::transaction(function () use ($service) {
            foreach ($service->parts as $part) {
                if ($part->product) {
                    $part->product->increment('stok', $part->qty);
                }
                $part->delete();
            }
            $service->delete();
            return ResponseHelper::success(null, 'Service order deleted successfully');
        });
    }
}
