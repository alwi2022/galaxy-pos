<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pembelian extends Model
{
    use HasFactory;

    protected $table = 'pembelian';
    protected $primaryKey = 'id_pembelian';
    protected $guarded = [];

    protected $casts = [
        'jatuh_tempo' => 'date',
        'ppn_persen' => 'float',
    ];

    protected static function booted()
    {
        static::addGlobalScope('active', fn ($q) => $q->whereNull('pembelian.cancelled_at'));
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'id_supplier', 'id_supplier');
    }
    public function cabang()
    {
        return $this->belongsTo(Cabang::class, 'id_cabang');
    }

    public function pembayaran()
    {
        return $this->hasMany(PembelianPembayaran::class, 'id_pembelian', 'id_pembelian');
    }

    public function syncStatusPembayaran()
    {
        $totalTagihan = max((int) $this->bayar - (int) $this->returned_total, 0);
        $dibayar = (int) $this->pembayaran()->sum('nominal');
        $netPaid = max($dibayar - (int) $this->refunded_total, 0);
        $sisa = max($totalTagihan - $netPaid, 0);

        $this->forceFill([
            'dibayar' => $dibayar,
            'sisa' => $sisa,
            'status_pembayaran' => status_pembayaran($totalTagihan, $netPaid),
        ])->save();

        return $this->refresh();
    }
}
