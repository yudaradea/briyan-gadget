<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MasterProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nama' => $this->nama,
            'brand_id' => $this->brand_id,
            'category_id' => $this->category_id,
            'unit_id' => $this->unit_id,
            'grade_id' => $this->grade_id,
            'identifier_type' => $this->identifier_type ?? 'none',
            'brand' => $this->brand ? ['id' => $this->brand->id, 'nama' => $this->brand->nama] : null,
            'category' => $this->category ? ['id' => $this->category->id, 'nama' => $this->category->nama] : null,
            'unit' => $this->unit ? ['id' => $this->unit->id, 'nama' => $this->unit->nama] : null,
            'grade' => $this->grade ? ['id' => $this->grade->id, 'nama' => $this->grade->nama] : null,
            'foto' => $this->foto,
            'keterangan' => $this->keterangan,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
