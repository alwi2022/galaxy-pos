<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Servis extends Model
{
    use \App\Models\Concerns\PostsServiceInvoice;
    protected $table = 'servis';
    protected $primaryKey = 'id_servis';
    protected $fillable = [
        'kode_servis', 'nama_pelanggan', 'telepon', 'id_member', 'keluhan',
        'tipe_barang', 'merk', 'kerusakan', 'status', 'biaya_servis', 'sparepart_total',
        'teknisi', 'garansi_hari', 'tanggal_masuk', 'tanggal_selesai',
        'id_cabang', 'id_user', 'jatuh_tempo'
    ];

    public function member() {
        return $this->belongsTo(Member::class, 'id_member');
    }

    public function cabang() {
        return $this->belongsTo(Cabang::class, 'id_cabang');
    }

    public function user() {
        return $this->belongsTo(User::class, 'id_user');
    }
    public function logs() {
        return $this->hasMany(ServisLog::class, 'id_servis');
    }

    public function spareparts() {
        return $this->hasMany(ServisSparepart::class, 'id_servis');
    }

    public function getTagihanAttribute() {
        return (int) $this->biaya_servis + (int) $this->sparepart_total;
    }
}
