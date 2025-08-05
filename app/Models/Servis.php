<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Servis extends Model
{   
    protected $table = 'servis';
    protected $primaryKey = 'id_servis';
    protected $fillable = [
        'kode_servis', 'nama_pelanggan', 'telepon', 'id_member', 'keluhan',
        'tipe_barang', 'merk', 'kerusakan', 'status', 'biaya_servis',
        'teknisi', 'garansi_hari', 'tanggal_masuk', 'tanggal_selesai',
        'id_cabang', 'id_user'
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
    
}