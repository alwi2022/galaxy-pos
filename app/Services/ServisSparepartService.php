<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Mengelola pemakaian sparepart pada servis: mengambil barang dari stok,
 * mencatat kartu stok, dan membukukan HPP (meniru leg COGS pada penjualan).
 *
 * Alur mengikuti aplikasi referensi MITRA: barang keluar dari gudang saat
 * dipakai untuk servis. Pendapatan sparepart diakui bersama tagihan servis
 * saat status "selesai"/"diambil" (lihat PostsServiceInvoice).
 */
class ServisSparepartService
{
    public function add(int $branch, int $servisId, int $productId, int $qty): int
    {
        return DB::transaction(function () use ($branch, $servisId, $productId, $qty) {
            $ledger = app(LedgerService::class);
            $servis = DB::table('servis')->where('id_cabang', $branch)->where('id_servis', $servisId)->lockForUpdate()->first();
            abort_unless($servis, 404);
            if ($servis->cancelled_at) {
                $ledger->fail('Servis sudah dibatalkan.');
            }
            if (in_array($servis->status, ['selesai', 'diambil'], true)) {
                $ledger->fail('Sparepart hanya dapat diubah saat servis masih diproses.');
            }
            if ($qty < 1) {
                $ledger->fail('Jumlah sparepart minimal 1.');
            }
            $produk = DB::table('produk')->where('id_cabang', $branch)->where('id_produk', $productId)->lockForUpdate()->first();
            abort_unless($produk, 404);

            $reference = 'SPART-' . (string) Str::uuid();
            // Keluarkan stok (barang dipakai). Menggunakan harga pokok rata-rata bergerak.
            $move = app(StockLedgerService::class)->move(
                $branch,
                $productId,
                -$qty,
                $reference,
                'Pemakaian sparepart servis ' . $servis->kode_servis,
                now()->toDateTimeString()
            );
            $cost = (int) $move->value;      // nilai HPP sparepart
            $unitCost = (int) $move->unit_cost;
            $hargaJual = (int) $produk->harga_jual;
            $subtotal = $hargaJual * $qty;

            // Bukukan HPP sparepart (leg COGS, sama seperti penjualan).
            if ($cost > 0) {
                $ledger->post($branch, now()->toDateString(), $reference, 'HPP sparepart servis ' . $servis->kode_servis . ' - ' . $produk->nama_produk, [
                    ['5101', $cost, 0],
                    ['1301', 0, $cost],
                ]);
            }

            $id = DB::table('servis_sparepart')->insertGetId([
                'id_servis' => $servisId,
                'id_produk' => $productId,
                'id_cabang' => $branch,
                'jumlah' => $qty,
                'harga_jual' => $hargaJual,
                'unit_cost' => $unitCost,
                'subtotal' => $subtotal,
                'cost_total' => $cost,
                'reference' => $reference,
                'id_user' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->syncTotal($servisId);
            return $id;
        });
    }

    public function remove(int $branch, int $servisId, int $sparepartId): void
    {
        DB::transaction(function () use ($branch, $servisId, $sparepartId) {
            $ledger = app(LedgerService::class);
            $servis = DB::table('servis')->where('id_cabang', $branch)->where('id_servis', $servisId)->lockForUpdate()->first();
            abort_unless($servis, 404);
            if (in_array($servis->status, ['selesai', 'diambil'], true)) {
                $ledger->fail('Sparepart hanya dapat diubah saat servis masih diproses.');
            }
            $row = DB::table('servis_sparepart')->where('id_cabang', $branch)->where('id_servis', $servisId)->where('id_servis_sparepart', $sparepartId)->first();
            abort_unless($row, 404);

            // Kembalikan stok (barang batal dipakai) dan balik jurnal HPP.
            app(StockLedgerService::class)->move(
                $branch,
                $row->id_produk,
                $row->jumlah,
                'CANCEL-' . $row->reference,
                'Pembatalan sparepart servis ' . $servis->kode_servis,
                now()->toDateTimeString(),
                (int) $row->unit_cost,
                (int) $row->cost_total
            );
            if ((int) $row->cost_total > 0) {
                $entry = DB::table('ledger_entries')->where('id_cabang', $branch)->where('reference', $row->reference)->first();
                if ($entry) {
                    $ledger->reverse($branch, $entry->id, now()->toDateString());
                }
            }

            DB::table('servis_sparepart')->where('id_servis_sparepart', $sparepartId)->delete();
            $this->syncTotal($servisId);
        });
    }

    private function syncTotal(int $servisId): void
    {
        $total = (int) DB::table('servis_sparepart')->where('id_servis', $servisId)->sum('subtotal');
        DB::table('servis')->where('id_servis', $servisId)->update(['sparepart_total' => $total, 'updated_at' => now()]);
    }
}
