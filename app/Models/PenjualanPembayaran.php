<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PenjualanPembayaran extends Model
{
    use HasFactory;

    protected $table = 'penjualan_pembayaran';
    protected $primaryKey = 'id_penjualan_pembayaran';
    protected $guarded = [];

    public function penjualan()
    {
        return $this->belongsTo(Penjualan::class, 'id_penjualan', 'id_penjualan');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }
}
