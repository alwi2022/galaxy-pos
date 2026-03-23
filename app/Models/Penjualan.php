<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Penjualan extends Model
{
    use HasFactory;

    protected $table = 'penjualan';
    protected $primaryKey = 'id_penjualan';
    protected $guarded = [];

    protected $casts = [
        'jatuh_tempo' => 'date',
        'ppn_persen' => 'float',
    ];

    public function member()
    {
        return $this->hasOne(Member::class, 'id_member', 'id_member');
    }

    public function user()
    {
        return $this->hasOne(User::class, 'id', 'id_user');
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class, 'id_cabang');
    }

    public function pembayaran()
    {
        return $this->hasMany(PenjualanPembayaran::class, 'id_penjualan', 'id_penjualan');
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
