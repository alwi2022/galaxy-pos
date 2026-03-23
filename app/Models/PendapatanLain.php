<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PendapatanLain extends Model
{
    use HasFactory;

    protected $table = 'pendapatan_lain';

    protected $primaryKey = 'id_pendapatan_lain';

    protected $guarded = [];

    protected $casts = [
        'tanggal_pendapatan' => 'date',
    ];

    public function cabang()
    {
        return $this->belongsTo(Cabang::class, 'id_cabang');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }
}
