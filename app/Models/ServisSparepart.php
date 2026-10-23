<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServisSparepart extends Model
{
    protected $table = 'servis_sparepart';
    protected $primaryKey = 'id_servis_sparepart';
    protected $fillable = [
        'id_servis', 'id_produk', 'id_cabang', 'jumlah', 'harga_jual',
        'unit_cost', 'subtotal', 'cost_total', 'reference', 'id_user',
    ];

    public function produk()
    {
        return $this->belongsTo(Produk::class, 'id_produk');
    }

    public function servis()
    {
        return $this->belongsTo(Servis::class, 'id_servis');
    }
}
