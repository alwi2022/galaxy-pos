<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    protected $table = 'supplier';
    protected $primaryKey = 'id_supplier';
    protected $guarded = [];

    public function cabang()
    {
        return $this->belongsTo(Cabang::class, 'id_cabang');
    }
}
