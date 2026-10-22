<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\DB;

trait GuardsDraftDetail
{
    public static function bootGuardsDraftDetail()
    {
        $guard = function ($detail) {
            $type = $detail->getTable() === 'penjualan_detail' ? 'penjualan' : 'pembelian';
            $key = 'id_' . $type;
            $query = DB::table($type)->where($key, $detail->$key);
            if (auth()->check()) {
                $query->where('id_cabang', auth()->user()->id_cabang);
            }
            $header = $query->lockForUpdate()->first();
            abort_unless($header, 404);
            if ($header->posted_at || $header->cancelled_at) {
                app(\App\Services\LedgerService::class)->fail('Detail transaksi final tidak dapat diubah. Gunakan retur atau pembatalan.');
            }
            if ($detail->jumlah < 1) {
                app(\App\Services\LedgerService::class)->fail('Jumlah barang minimal satu.');
            }
            if (! DB::table('produk')->where('id_produk', $detail->id_produk)->where('id_cabang', $header->id_cabang)->exists()) {
                app(\App\Services\LedgerService::class)->fail('Produk harus berasal dari cabang transaksi.');
            }
        };
        static::saving($guard);
        static::deleting($guard);
    }
}
