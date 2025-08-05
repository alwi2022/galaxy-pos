<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cabang extends Model
{
    protected $table = 'cabang';
    protected $primaryKey = 'id_cabang';
    protected $fillable = ['nama_cabang', 'alamat', 'telepon'];
    protected $guarded = [];
}