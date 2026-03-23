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
        $totalTagihan = (int) $this->bayar;
        $dibayar = (int) $this->pembayaran()->sum('nominal');
        $sisa = max($totalTagihan - $dibayar, 0);

        $this->forceFill([
            'dibayar' => $dibayar,
            'sisa' => $sisa,
            'status_pembayaran' => status_pembayaran($totalTagihan, $dibayar),
        ])->save();

        return $this->refresh();
    }
}
