<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'no_invoice' => $this->no_invoice,
            'tanggal' => $this->tanggal?->format('Y-m-d'),
            'supplier' => $this->whenLoaded('supplier', fn() => [
                'id' => $this->supplier->id,
                'nama' => $this->supplier->nama,
            ]),
            'user' => $this->whenLoaded('user', fn() => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'total' => (float) $this->total,
            'keterangan' => $this->keterangan,
            'items' => $this->whenLoaded(
                'items',
                fn() =>
                $this->items->map(function ($item) {
                    $latestSaleItem = null;
                    if ($item->product && $item->product->relationLoaded('saleItems')) {
                        $latestSaleItem = $item->product->saleItems
                            ->sortByDesc('created_at')
                            ->first();
                    }

                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'product' => $item->product ? [
                        'id' => $item->product->id,
                        'barcode' => $item->product->barcode,
                        'nama' => $item->product->masterProduct?->nama,
                        'master_product_id' => $item->product->master_product_id,
                        'imei1' => $item->product->imei1,
                        'imei2' => $item->product->imei2,
                            'keterangan' => $item->product->keterangan,
                            'brand' => $item->product->masterProduct?->brand?->nama,
                            'brand_id' => $item->product->masterProduct?->brand_id,
                            'grade' => $item->product->grade?->nama,
                            'grade_id' => $item->product->grade_id,
                            'unit' => $item->product->unit?->nama,
                            'unit_id' => $item->product->unit_id,
                            'harga_jual' => (float) $item->product->harga_jual,
                            'harga_jual_transaksi' => (float) ($latestSaleItem?->harga_satuan ?? 0),
                            'is_sold' => $item->product->saleItems?->isNotEmpty() ?? false,
                            'sale' => $latestSaleItem?->salesTransaction ? [
                                'no_invoice' => $latestSaleItem->salesTransaction->no_invoice,
                                'tanggal' => $latestSaleItem->salesTransaction->tanggal?->format('Y-m-d'),
                            ] : null,
                            'stok' => (int) $item->product->stok,
                            'foto' => $item->product->foto,
                        ] : null,
                        'qty' => $item->qty,
                        'harga_beli' => (float) $item->harga_beli,
                        'subtotal' => (float) $item->subtotal,
                    ];
                })
            ),
            'items_count' => $this->whenCounted('items'),
            'total_qty' => (float) $this->items_sum_qty,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
