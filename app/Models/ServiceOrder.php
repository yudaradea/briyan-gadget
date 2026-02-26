<?php

namespace App\Models;

use App\Traits\UUID;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceOrder extends Model
{
    use HasFactory, UUID, SoftDeletes;

    protected $fillable = [
        'sales_transaction_id',
        'nama_pelanggan',
        'no_hp_pelanggan',
        'merk_hp',
        'tipe_hp',
        'kerusakan',
        'imei_hp',
        'kelengkapan',
        'biaya_jasa',
        'status',
        'status_pengambilan',
        'tanggal_masuk',
        'tanggal_selesai',
        'catatan_teknisi',
    ];

    protected function casts(): array
    {
        return [
            'biaya_jasa' => 'decimal:2',
            'tanggal_masuk' => 'date',
            'tanggal_selesai' => 'date',
        ];
    }

    public function scopeSearch($query, $search)
    {
        if (empty($search)) return $query;
        return $query->where(function ($q) use ($search) {
            $q->where('nama_pelanggan', 'like', '%' . $search . '%')
                ->orWhere('merk_hp', 'like', '%' . $search . '%')
                ->orWhere('tipe_hp', 'like', '%' . $search . '%')
                ->orWhere('no_hp_pelanggan', 'like', '%' . $search . '%')
                ->orWhere('imei_hp', 'like', '%' . $search . '%');
        });
    }

    public function scopeStatus($query, $status)
    {
        if ($status) return $query->where('status', $status);
        return $query;
    }

    public function scopeDateRange($query, $startDate, $endDate)
    {
        if ($startDate) $query->where('tanggal_masuk', '>=', $startDate);
        if ($endDate) $query->where('tanggal_masuk', '<=', $endDate);
        return $query;
    }

    // === Relationships ===

    public function salesTransaction()
    {
        return $this->belongsTo(SalesTransaction::class);
    }

    public function parts()
    {
        return $this->hasMany(ServicePart::class);
    }

    /**
     * Calculate total cost = biaya_jasa + sum of parts
     */
    public function getTotalBiayaAttribute(): float
    {
        return (float) $this->biaya_jasa + (float) $this->parts()->sum('subtotal');
    }
}
