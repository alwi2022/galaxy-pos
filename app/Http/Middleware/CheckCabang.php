<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckCabang
{
    public function handle(Request $request, Closure $next): Response
    {
        $resource = $request->route('id') 
            ?? $request->route('id_produk') 
            ?? $request->route('id_penjualan') 
            ?? $request->route('id_servis')   
            ?? $request->route('id_pembelian') 
            ?? $request->route('id_pengeluaran') 
            ?? $request->route('id_member') 
            ?? $request->route('id_supplier');

        $model = null;

        if ($resource) {
            // Tentukan model berdasarkan URL
            if ($request->is('produk/*')) {
                $model = \App\Models\Produk::find($resource);
            } elseif ($request->is('penjualan/*')) {
                $model = \App\Models\Penjualan::find($resource);
            } elseif ($request->is('servis/*')) {
                $model = \App\Models\Servis::find($resource);
            } elseif ($request->is('pembelian/*')) {
                $model = \App\Models\Pembelian::find($resource);
            } elseif ($request->is('pengeluaran/*')) {
                $model = \App\Models\Pengeluaran::find($resource);
            } elseif ($request->is('member/*')) {
                $model = \App\Models\Member::find($resource);
            } elseif ($request->is('supplier/*')) {
                $model = \App\Models\Supplier::find($resource);
            }

            if (!$model) {
                abort(404, 'Data tidak ditemukan.');
            }

            if (auth()->user()->level != 1 && auth()->user()->id_cabang != $model->id_cabang) {
                abort(403, 'Anda tidak memiliki akses ke data dari cabang lain.');
            }
        }

        return $next($request);
    }
}
