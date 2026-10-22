<?php

namespace App\Services;

use App\Support\TransactionCalculator;
use Illuminate\Support\Facades\DB;

class BusinessTransactionService
{
    public function post(string $type, int $id, int $branch): void
    {
        DB::transaction(function () use ($type, $id, $branch) {
            $ledger = app(LedgerService::class);
            $stock = app(StockLedgerService::class);
            $key = 'id_' . $type;
            $header = DB::table($type)->where($key, $id)->where('id_cabang', $branch)->lockForUpdate()->first();
            abort_unless($header, 404);
            if ($header->posted_at || $header->cancelled_at) {
                $ledger->fail('Transaksi sudah final atau dibatalkan.');
            }
            $sale = $type === 'penjualan';
            $details = DB::table($type . '_detail')->where($key, $id)->orderBy('id_produk')->get();
            if ($details->isEmpty()) {
                $ledger->fail('Transaksi belum memiliki barang.');
            }
            $allocations = app(TransactionCalculator::class)->allocateTransactionLines($details, (int) round($header->total_harga * $header->diskon / 100), (int) $header->ppn_nominal);
            $reference = strtoupper($type) . '-' . $id;
            $costTotal = 0;
            foreach ($details as $index => $detail) {
                $cost = $sale ? null : (int) round($allocations[$index]['dpp'] / $detail->jumlah);
                $move = $stock->move($branch, $detail->id_produk, $sale ? -$detail->jumlah : $detail->jumlah, $reference . '-L' . $detail->{'id_' . $type . '_detail'}, $sale ? 'Penjualan' : 'Pembelian', now()->toDateTimeString(), $cost, $sale ? null : $allocations[$index]['dpp']);
                $costTotal += $move->value;
                if ($sale) {
                    DB::table('penjualan_detail')->where('id_penjualan_detail', $detail->id_penjualan_detail)->update(['unit_cost' => $move->unit_cost, 'cost_total' => $move->value]);
                }
            }
            $net = (int) $header->bayar - (int) $header->ppn_nominal;
            $lines = $sale
                ? [['1201', (int) $header->bayar, 0], ['4101', 0, $net], ['2201', 0, (int) $header->ppn_nominal], ['5101', $costTotal, 0], ['1301', 0, $costTotal]]
                : [['1301', $net, 0], ['1401', (int) $header->ppn_nominal, 0], ['2101', 0, (int) $header->bayar]];
            $ledger->post($branch, now()->toDateString(), $reference, ucfirst($type) . ' #' . $id, $lines);
            DB::table($type)->where($key, $id)->update(['posted_at' => now()]);
        });
    }

    public function payment(string $type, object $payment): void
    {
        $sale = $type === 'penjualan';
        $header = DB::table($type)->where('id_' . $type, $payment->{'id_' . $type})->lockForUpdate()->first();
        if (! $header || ! $header->posted_at || $header->cancelled_at) {
            app(LedgerService::class)->fail('Pembayaran hanya untuk transaksi final yang aktif.');
        }
        $cash = app(LedgerService::class)->cashCode($payment->metode_pembayaran);
        $amount = (int) $payment->nominal;
        $sumPaid = (int) DB::table($type . '_pembayaran')->where('id_' . $type, $header->{'id_' . $type})->sum('nominal');
        if ($amount < 1 || $sumPaid - $header->refunded_total > $header->bayar - $header->returned_total) {
            app(LedgerService::class)->fail('Nominal pembayaran tidak valid atau melebihi sisa tagihan.');
        }
        app(LedgerService::class)->post($payment->id_cabang, now()->toDateString(), strtoupper($type) . '-PAY-' . $payment->{'id_' . $type . '_pembayaran'}, 'Pembayaran ' . $type . ' #' . $header->{'id_' . $type}, $sale ? [[$cash, $amount, 0], ['1201', 0, $amount]] : [['2101', $amount, 0], [$cash, 0, $amount]]);
    }

    public function returnItem(int $branch, array $data): int
    {
        return DB::transaction(function () use ($branch, $data) {
            $ledger = app(LedgerService::class);
            $type = $data['type'];
            $sale = $type === 'penjualan';
            $key = 'id_' . $type;
            $header = DB::table($type)->where('id_cabang', $branch)->where($key, $data['transaction_id'])->lockForUpdate()->first();
            abort_unless($header, 404);
            if (! $header->posted_at || $header->cancelled_at) {
                $ledger->fail('Pilih transaksi final yang aktif.');
            }
            if (substr($header->posted_at, 0, 10) > $data['date']) {
                $ledger->fail('Tanggal retur mendahului transaksi.');
            }
            $details = DB::table($type . '_detail')->where($key, $header->$key)->orderBy('id_produk')->get();
            $index = $details->search(fn ($d) => $d->{'id_' . $type . '_detail'} == $data['detail_id']);
            if ($index === false) {
                $ledger->fail('Barang tidak ditemukan di transaksi asal.');
            }
            $detail = $details[$index];
            $returned = DB::table('stock_returns')->where('type', $type)->where('detail_id', $data['detail_id'])->get();
            $previous = (int) $returned->sum('quantity');
            $quantity = (int) $data['quantity'];
            if ($quantity < 1 || $quantity + $previous > $detail->jumlah) {
                $ledger->fail('Jumlah retur melebihi jumlah transaksi asal.');
            }
            $allocation = app(TransactionCalculator::class)->allocateTransactionLines($details, (int) round($header->total_harga * $header->diskon / 100), (int) $header->ppn_nominal)[$index];
            $amount = (int) round($allocation['grand_total'] * ($previous + $quantity) / $detail->jumlah) - (int) $returned->sum('amount');
            $tax = (int) round($allocation['ppn_nominal'] * ($previous + $quantity) / $detail->jumlah) - (int) $returned->sum('tax');
            $refund = max($amount - (int) $header->sisa, 0);
            $offset = $amount - $refund;
            $unitCost = $sale ? (int) $detail->unit_cost : (int) round($allocation['dpp'] / $detail->jumlah);
            $id = DB::table('stock_returns')->insertGetId(['id_cabang' => $branch, 'type' => $type, 'transaction_id' => $header->$key, 'detail_id' => $data['detail_id'], 'id_produk' => $detail->id_produk, 'date' => $data['date'], 'quantity' => $quantity, 'restock' => $sale ? (bool) ($data['restock'] ?? false) : false, 'amount' => $amount, 'tax' => $tax, 'refund' => $refund, 'unit_cost' => $unitCost, 'reason' => $data['reason'], 'id_user' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
            $ref = 'RET-' . $id;
            $cash = $ledger->cashCode($data['method']);
            $cost = $sale ? (int) round($detail->cost_total * ($previous + $quantity) / $detail->jumlah) - (int) round($detail->cost_total * $previous / $detail->jumlah) : $unitCost * $quantity;
            if ($sale) {
                $lines = [['4101', $amount - $tax, 0], ['2201', $tax, 0], ['1201', 0, $offset], [$cash, 0, $refund]];
                if ($data['restock'] ?? false) {
                    app(StockLedgerService::class)->move($branch, $detail->id_produk, $quantity, $ref, 'Retur penjualan: ' . $data['reason'], $data['date'] . ' ' . now()->format('H:i:s'), $unitCost, $cost);
                    $lines[] = ['1301', $cost, 0];
                    $lines[] = ['5101', 0, $cost];
                }
            } else {
                $move = app(StockLedgerService::class)->move($branch, $detail->id_produk, -$quantity, $ref, 'Retur pembelian: ' . $data['reason'], $data['date'] . ' ' . now()->format('H:i:s'));
                $stockValue = $move->value;
                $net = $amount - $tax;
                $lines = [['2101', $offset, 0], [$cash, $refund, 0], ['1301', 0, $stockValue], ['1401', 0, $tax]];
                if ($stockValue > $net) {
                    $lines[] = ['6102', $stockValue - $net, 0];
                } elseif ($net > $stockValue) {
                    $lines[] = ['6102', 0, $net - $stockValue];
                }
            }
            if ($amount > 0) {
                $ledger->post($branch, $data['date'], $ref, 'Retur ' . $type . ' #' . $header->$key . ': ' . $data['reason'], $lines);
            }
            $newReturned = (int) $header->returned_total + $amount;
            $newRefund = (int) $header->refunded_total + $refund;
            $balance = max((int) $header->bayar - $newReturned - ((int) $header->dibayar - $newRefund), 0);
            DB::table($type)->where($key, $header->$key)->update(['returned_total' => $newReturned, 'refunded_total' => $newRefund, 'sisa' => $balance, 'status_pembayaran' => status_pembayaran(max((int) $header->bayar - $newReturned, 0), max((int) $header->dibayar - $newRefund, 0)), 'updated_at' => now()]);
            return $id;
        });
    }

    public function cancel(string $type, int $id, int $branch): void
    {
        DB::transaction(function () use ($type, $id, $branch) {
            $ledger = app(LedgerService::class);
            $key = 'id_' . $type;
            $header = DB::table($type)->where($key, $id)->where('id_cabang', $branch)->lockForUpdate()->first();
            abort_unless($header, 404);
            if ($header->cancelled_at || DB::table('stock_returns')->where('type', $type)->where('transaction_id', $id)->exists()) {
                $ledger->fail('Transaksi sudah dibatalkan atau memiliki retur.');
            }
            if ($header->posted_at) {
                $details = DB::table($type . '_detail')->where($key, $id)->get();
                if ($type === 'pembelian') {
                    foreach ($details as $detail) {
                        $lastReference = DB::table('stock_movements')->where('id_produk', $detail->id_produk)->orderByDesc('id')->value('reference');
                        if (! str_starts_with((string) $lastReference, 'PEMBELIAN-' . $id . '-L')) {
                            $ledger->fail('Barang pembelian sudah memiliki pergerakan lanjutan. Gunakan retur pembelian.');
                        }
                    }
                }
                foreach ($details as $detail) {
                    $original = DB::table('stock_movements')->where('reference', strtoupper($type) . '-' . $id . '-L' . $detail->{'id_' . $type . '_detail'})->first();
                    app(StockLedgerService::class)->move($branch, $detail->id_produk, $type === 'penjualan' ? $detail->jumlah : -$detail->jumlah, 'CANCEL-' . $type . '-' . $id . '-L' . $detail->{'id_' . $type . '_detail'}, 'Pembatalan ' . $type, now()->toDateTimeString(), $type === 'penjualan' ? (int) $detail->unit_cost : null, $original ? abs((int) $original->value) : null);
                }
                $entries = DB::table('ledger_entries')->where('id_cabang', $branch)->where(function ($q) use ($type, $id) {
                    $q->where('reference', strtoupper($type) . '-' . $id);
                    $paymentIds = DB::table($type . '_pembayaran')->where('id_' . $type, $id)->pluck('id_' . $type . '_pembayaran');
                    foreach ($paymentIds as $paymentId) {
                        $q->orWhere('reference', strtoupper($type) . '-PAY-' . $paymentId);
                    }
                })->get();
                foreach ($entries as $entry) {
                    $ledger->reverse($branch, $entry->id, now()->toDateString());
                }
            }
            DB::table($type)->where($key, $id)->update(['cancelled_at' => now(), 'sisa' => 0]);
        });
    }
}
