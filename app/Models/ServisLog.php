<?php

// app/Models/ServisLog.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServisLog extends Model
{
    protected $fillable = ['id_servis', 'id_user', 'status'];

    public function user() {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function servis() {
        return $this->belongsTo(Servis::class, 'id_servis');
    }
}
