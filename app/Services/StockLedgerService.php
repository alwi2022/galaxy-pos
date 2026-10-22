<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class StockLedgerService
{
    public function move(int $branch, int $productId, int $quantity, string $reference, string $reason, string $date, ?int $incomingCost = null, ?int $incomingValue = null): object
    {
        return DB::transaction(function () use ($branch, $productId, $quantity, $reference, $reason, $date, $incomingCost, $incomingValue) {
            app(LedgerService::class)->open($branch, $date);
            $product = DB::table('produk')->where('id_cabang', $branch)->where('id_produk', $productId)->lockForUpdate()->first();
            abort_unless($product, 404);
            if ($product->stok + $quantity < 0) {
                app(LedgerService::class)->fail('Stok ' . $product->nama_produk . ' tidak cukup.');
            }
            if (DB::table('stock_movements')->where('reference', $reference)->where('id_produk', $productId)->exists()) {
                app(LedgerService::class)->fail('Pergerakan stok sudah dicatat.');
            }
            $last = DB::table('stock_movements')->where('id_produk', $productId)->max('occurred_at');
            if ($last && strtotime($date) < strtotime($last)) {
                app(LedgerService::class)->fail('Tanggal pergerakan stok harus mengikuti transaksi terakhir produk.');
            }
            $cost = $incomingCost ?? (int) $product->harga_beli;
            $value = $quantity > 0 ? ($incomingValue ?? $quantity * $cost) : -($incomingValue ?? (int) round($product->inventory_value * abs($quantity) / max(1, $product->stok)));
            $balance = $product->stok + $quantity;
            $valuation = $product->inventory_value + $value;
            if ($valuation < 0) {
                app(LedgerService::class)->fail('Nilai persediaan tidak mencukupi untuk pembatalan ini. Gunakan retur pembelian.');
            }
            $newCost = $balance > 0 ? (int) round($valuation / $balance) : $cost;
            DB::table('stock_movements')->insert(['id_cabang' => $branch, 'id_produk' => $productId, 'occurred_at' => $date, 'reference' => $reference, 'reason' => $reason, 'quantity' => $quantity, 'balance' => $balance, 'unit_cost' => $cost, 'value' => $value, 'id_user' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('produk')->where('id_produk', $productId)->update(['stok' => $balance, 'harga_beli' => $newCost, 'inventory_value' => $valuation, 'updated_at' => now()]);
            return (object) ['unit_cost' => $cost, 'balance' => $balance, 'value' => abs($value)];
        });
    }

    public function adjustment(int $branch, int $product, int $quantity, string $type, string $reason, string $date): void
    {
        DB::transaction(function () use ($branch, $product, $quantity, $type, $reason, $date) {
            $ledger = app(LedgerService::class);
            $row = DB::table('produk')->where('id_cabang', $branch)->where('id_produk', $product)->lockForUpdate()->first();
            abort_unless($row, 404);
            $delta = $type === 'opname' ? $quantity - $row->stok : -$quantity;
            if ($delta === 0 || ($type !== 'opname' && $quantity < 1)) {
                $ledger->fail('Stok fisik sama dengan stok aplikasi.');
            }
            $reference = 'STK-' . (string) \Illuminate\Support\Str::uuid();
            $movement = $this->move($branch, $product, $delta, $reference, $type . ': ' . $reason, $date);
            $value = $movement->value;
            if ($value > 0) {
                $ledger->post($branch, substr($date, 0, 10), $reference, $reason, $delta > 0 ? [['1301', $value, 0], ['6102', 0, $value]] : [['6102', $value, 0], ['1301', 0, $value]]);
            }
        });
    }
}
