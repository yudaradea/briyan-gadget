<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sales_transaction_id' => $this->sales_transaction_id,
            'nama_pelanggan' => $this->nama_pelanggan,
            'no_hp_pelanggan' => $this->no_hp_pelanggan,
            'merk_hp' => $this->merk_hp,
            'tipe_hp' => $this->tipe_hp,
            'kerusakan' => $this->kerusakan,
            'imei_hp' => $this->imei_hp,
            'kelengkapan' => $this->kelengkapan,
            'biaya_jasa' => (float) $this->biaya_jasa,
            'status' => $this->status,
            'status_pengambilan' => $this->status_pengambilan,
            'tanggal_masuk' => $this->tanggal_masuk?->format('Y-m-d'),
            'tanggal_selesai' => $this->tanggal_selesai?->format('Y-m-d'),
            'catatan_teknisi' => $this->catatan_teknisi,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),

            // Totals
            'total_biaya_parts' => (float) $this->parts()->sum('subtotal'),
            'grand_total' => (float) $this->biaya_jasa + (float) $this->parts()->sum('subtotal'),

            // Relationships
            'parts' => $this->whenLoaded('parts', fn() => $this->parts->map(fn($p) => [
                'id' => $p->id,
                'nama_part' => $p->nama_part,
                'product_id' => $p->product_id,
                'qty' => $p->qty,
                'harga_satuan' => (float) $p->harga_satuan,
                'subtotal' => (float) $p->subtotal,
                'product' => $p->product ? [
                    'id' => $p->product->id,
                    'nama' => $p->product->nama,
                    'barcode' => $p->product->barcode,
                ] : null,
            ])),

            'transaction' => $this->whenLoaded('salesTransaction', fn() => [
                'id' => $this->salesTransaction->id,
                'no_invoice' => $this->salesTransaction->no_invoice,
            ]),
        ];
    }
}
