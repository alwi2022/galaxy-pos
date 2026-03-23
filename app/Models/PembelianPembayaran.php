<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PembelianPembayaran extends Model
{
    use HasFactory;

    protected $table = 'pembelian_pembayaran';
    protected $primaryKey = 'id_pembelian_pembayaran';
    protected $guarded = [];

    public function pembelian()
    {
        return $this->belongsTo(Pembelian::class, 'id_pembelian', 'id_pembelian');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }
}
