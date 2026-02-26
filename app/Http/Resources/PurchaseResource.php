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
                $this->items->map(fn($item) => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product' => $item->product ? [
                        'id' => $item->product->id,
                        'barcode' => $item->product->barcode,
                        'nama' => $item->product->masterProduct?->nama,
                        'imei1' => $item->product->imei1,
                        'imei2' => $item->product->imei2,
                        'brand' => $item->product->masterProduct?->brand?->nama,
                        'brand_id' => $item->product->masterProduct?->brand_id,
                        'category' => $item->product->masterProduct?->category?->nama,
                        'category_id' => $item->product->masterProduct?->category_id,
                        'grade' => $item->product->grade?->nama,
                        'grade_id' => $item->product->grade_id,
                        'unit' => $item->product->masterProduct?->unit?->nama,
                        'unit_id' => $item->product->masterProduct?->unit_id,
                        'harga_jual' => (float) $item->product->harga_jual,
                        'foto' => $item->product->foto,
                    ] : null,
                    'qty' => $item->qty,
                    'harga_beli' => (float) $item->harga_beli,
                    'subtotal' => (float) $item->subtotal,
                ])
            ),
            'items_count' => $this->whenCounted('items'),
            'total_qty' => (float) $this->items_sum_qty,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
