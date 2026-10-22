<?php

namespace App\Services;

use App\Models\Pembelian;
use App\Models\PembelianDetail;
use App\Models\PembelianPembayaran;
use App\Models\PendapatanLain;
use App\Models\Pengeluaran;
use App\Models\Penjualan;
use App\Models\PenjualanDetail;
use App\Models\PenjualanPembayaran;
use App\Models\Servis;
use App\Support\TransactionCalculator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LaporanService
{
    protected TransactionCalculator $calculator;

    public function __construct(TransactionCalculator $calculator)
    {
        $this->calculator = $calculator;
    }

    public function ringkasan(string $awal, string $akhir, int $idCabang): array
    {
        $penjualanByDate = $pembelianByDate = $pendapatanLainByDate = $pengeluaranByDate = $servisByDate = [];
        $cashLines = DB::table('ledger_lines as l')
            ->join('ledger_entries as e', 'e.id', '=', 'l.entry_id')
            ->join('ledger_accounts as a', 'a.id', '=', 'l.account_id')
            ->leftJoin('ledger_entries as original', 'original.id', '=', 'e.reversal_of')
            ->where('e.id_cabang', $idCabang)->where('a.cash_account', true)
            ->whereBetween('e.date', [$awal, $akhir])
            ->selectRaw('e.date, COALESCE(original.reference,e.reference) as source, l.debit-l.credit as amount')->get();
        $returnTypes = DB::table('stock_returns')->where('id_cabang', $idCabang)->pluck('type', 'id');
        foreach ($cashLines as $line) {
            $amount = (int) $line->amount;
            $source = $line->source;
            $returnType = str_starts_with($source, 'RET-') ? ($returnTypes[(int) substr($source, 4)] ?? '') : '';
            if (str_starts_with($source, 'PENJUALAN-') || $returnType === 'penjualan') {
                $penjualanByDate[$line->date] = ($penjualanByDate[$line->date] ?? 0) + $amount;
            } elseif (str_starts_with($source, 'PEMBELIAN-') || $returnType === 'pembelian') {
                $pembelianByDate[$line->date] = ($pembelianByDate[$line->date] ?? 0) - $amount;
            } elseif (str_starts_with($source, 'PENDAPATAN_LAIN-')) {
                $pendapatanLainByDate[$line->date] = ($pendapatanLainByDate[$line->date] ?? 0) + $amount;
            } elseif (str_starts_with($source, 'PENGELUARAN-')) {
                $pengeluaranByDate[$line->date] = ($pengeluaranByDate[$line->date] ?? 0) - $amount;
            } elseif (str_starts_with($source, 'SERVIS-')) {
                $servisByDate[$line->date] = ($servisByDate[$line->date] ?? 0) + $amount;
            }
        }

        $rows = [];
        $no = 1;
        $cursor = $awal;

        while (strtotime($cursor) <= strtotime($akhir)) {
            $penjualanMasuk = (int) ($penjualanByDate[$cursor] ?? 0);
            $pembelianKeluar = (int) ($pembelianByDate[$cursor] ?? 0);
            $pendapatanLain = (int) ($pendapatanLainByDate[$cursor] ?? 0);
            $pengeluaran = (int) ($pengeluaranByDate[$cursor] ?? 0);
            $servis = (int) ($servisByDate[$cursor] ?? 0);
            $pendapatanBersih = $penjualanMasuk + $pendapatanLain + $servis - $pembelianKeluar - $pengeluaran;

            $rows[] = [
                'DT_RowIndex' => $no++,
                'tanggal' => tanggal_indonesia($cursor, false),
                'penjualan_masuk' => $penjualanMasuk,
                'pembelian_keluar' => $pembelianKeluar,
                'pendapatan_lain' => $pendapatanLain,
                'servis' => $servis,
                'biaya' => $pengeluaran,
                'pendapatan_bersih' => $pendapatanBersih,
            ];

            $cursor = date('Y-m-d', strtotime('+1 day', strtotime($cursor)));
        }

        return $rows;
    }

    public function penjualanPeriode(string $awal, string $akhir, int $idCabang): Collection
    {
        return Penjualan::with(['member', 'user'])
            ->where('id_cabang', $idCabang)
            ->where('total_item', '>', 0)
            ->whereBetween('created_at', [$awal . ' 00:00:00', $akhir . ' 23:59:59'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($penjualan) use ($akhir) {
                $totals = $this->calculator->calculateFromSubtotal(
                    (int) $penjualan->total_harga,
                    (float) $penjualan->diskon,
                    (float) ($penjualan->ppn_persen ?? 0),
                    (int) $penjualan->dibayar
                );

                $net = $this->invoiceNet($penjualan, 'penjualan', $akhir, $totals);
                return [
                    'tanggal' => tanggal_indonesia($penjualan->created_at, false),
                    'nomor' => tambah_nol_didepan($penjualan->id_penjualan, 10),
                    'pelanggan' => $penjualan->member->nama ?? 'Umum',
                    'total_item' => $net['quantity'],
                    'subtotal' => $net['subtotal'],
                    'diskon_persen' => (float) $penjualan->diskon,
                    'diskon_nominal' => $net['discount'],
                    'dpp' => $net['dpp'],
                    'ppn_persen' => (float) ($penjualan->ppn_persen ?? 0),
                    'ppn_nominal' => $net['tax'],
                    'grand_total' => $net['total'],
                    'dibayar' => $net['paid'],
                    'sisa' => $net['remaining'],
                    'skema' => label_skema_pembayaran($penjualan->skema_pembayaran),
                    'metode' => label_metode_pembayaran($penjualan->metode_pembayaran),
                    'status_kode' => $net['status'],
                    'status' => label_status_pembayaran($net['status']),
                    'jatuh_tempo' => $penjualan->jatuh_tempo ? tanggal_indonesia($penjualan->jatuh_tempo, false) : '-',
                    'kasir' => $penjualan->user->name ?? '-',
                ];
            })
            ->values();
    }

    public function pembelianPeriode(string $awal, string $akhir, int $idCabang): Collection
    {
        return Pembelian::with('supplier')
            ->where('id_cabang', $idCabang)
            ->where('total_item', '>', 0)
            ->whereBetween('created_at', [$awal . ' 00:00:00', $akhir . ' 23:59:59'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($pembelian) use ($akhir) {
                $totals = $this->calculator->calculateFromSubtotal(
                    (int) $pembelian->total_harga,
                    (float) $pembelian->diskon,
                    (float) ($pembelian->ppn_persen ?? 0),
                    (int) $pembelian->dibayar
                );

                $net = $this->invoiceNet($pembelian, 'pembelian', $akhir, $totals);
                return [
                    'tanggal' => tanggal_indonesia($pembelian->created_at, false),
                    'nomor' => tambah_nol_didepan($pembelian->id_pembelian, 10),
                    'supplier' => $pembelian->supplier->nama ?? '-',
                    'total_item' => $net['quantity'],
                    'subtotal' => $net['subtotal'],
                    'diskon_persen' => (float) $pembelian->diskon,
                    'diskon_nominal' => $net['discount'],
                    'dpp' => $net['dpp'],
                    'ppn_persen' => (float) ($pembelian->ppn_persen ?? 0),
                    'ppn_nominal' => $net['tax'],
                    'grand_total' => $net['total'],
                    'dibayar' => $net['paid'],
                    'sisa' => $net['remaining'],
                    'skema' => label_skema_pembayaran($pembelian->skema_pembayaran),
                    'metode' => label_metode_pembayaran($pembelian->metode_pembayaran),
                    'status_kode' => $net['status'],
                    'status' => label_status_pembayaran($net['status']),
                    'jatuh_tempo' => $pembelian->jatuh_tempo ? tanggal_indonesia($pembelian->jatuh_tempo, false) : '-',
                ];
            })
            ->values();
    }

    public function laporanProduk(string $awal, string $akhir, int $idCabang): Collection
    {
        $rows = collect();

        foreach ($this->aggregateProdukPenjualan($awal, $akhir, $idCabang) as $key => $item) {
            $rows->put($key, $item);
        }

        foreach ($this->aggregateProdukPembelian($awal, $akhir, $idCabang) as $key => $item) {
            $current = $rows->get($key, $this->emptyProdukRow($item['kode_produk'], $item['nama_produk'], $item['kategori']));
            $current['qty_beli'] += $item['qty_beli'];
            $current['pembelian_dpp'] += $item['pembelian_dpp'];
            $current['pembelian_ppn'] += $item['pembelian_ppn'];
            $current['pembelian_total'] += $item['pembelian_total'];
            $current['stok_saat_ini'] = $item['stok_saat_ini'];
            $rows->put($key, $current);
        }

        return $rows
            ->map(function ($item) {
                $item['saldo_qty'] = $item['qty_beli'] - $item['qty_jual'];

                return $item;
            })
            ->sortBy('nama_produk')
            ->values();
    }

    public function laporanKategori(string $awal, string $akhir, int $idCabang): Collection
    {
        return $this->laporanProduk($awal, $akhir, $idCabang)
            ->groupBy('kategori')
            ->map(function ($rows, $kategori) {
                return [
                    'kategori' => $kategori ?: 'Tanpa Kategori',
                    'jumlah_produk' => $rows->count(),
                    'qty_jual' => (int) $rows->sum('qty_jual'),
                    'penjualan_dpp' => (int) $rows->sum('penjualan_dpp'),
                    'penjualan_ppn' => (int) $rows->sum('penjualan_ppn'),
                    'penjualan_total' => (int) $rows->sum('penjualan_total'),
                    'qty_beli' => (int) $rows->sum('qty_beli'),
                    'pembelian_dpp' => (int) $rows->sum('pembelian_dpp'),
                    'pembelian_ppn' => (int) $rows->sum('pembelian_ppn'),
                    'pembelian_total' => (int) $rows->sum('pembelian_total'),
                    'saldo_qty' => (int) $rows->sum('saldo_qty'),
                ];
            })
            ->sortBy('kategori')
            ->values();
    }

    protected function aggregateProdukPenjualan(string $awal, string $akhir, int $idCabang): array
    {
        $headers = Penjualan::where('id_cabang', $idCabang)
            ->where('total_item', '>', 0)
            ->whereBetween('created_at', [$awal . ' 00:00:00', $akhir . ' 23:59:59'])
            ->get()
            ->keyBy('id_penjualan');

        $details = PenjualanDetail::with('produk.kategori')
            ->whereIn('id_penjualan', $headers->keys())
            ->get()
            ->groupBy('id_penjualan');

        $aggregates = [];

        foreach ($headers as $header) {
            $transactionDetails = $details->get($header->id_penjualan, collect())->values();
            $totals = $this->calculator->calculateFromSubtotal(
                (int) $header->total_harga,
                (float) $header->diskon,
                (float) ($header->ppn_persen ?? 0),
                (int) $header->dibayar
            );
            $allocations = $this->calculator->allocateTransactionLines(
                $transactionDetails->all(),
                $totals['diskon_nominal'],
                $totals['ppn_nominal']
            );

            foreach ($transactionDetails as $index => $detail) {
                $product = $detail->produk;
                if (! $product) {
                    continue;
                }

                $key = $product->id_produk;
                $current = $aggregates[$key] ?? $this->emptyProdukRow(
                    $product->kode_produk,
                    $product->nama_produk,
                    optional($product->kategori)->nama_kategori ?? 'Tanpa Kategori'
                );

                $current['qty_jual'] += (int) $detail->jumlah;
                $current['penjualan_dpp'] += (int) ($allocations[$index]['dpp'] ?? 0);
                $current['penjualan_ppn'] += (int) ($allocations[$index]['ppn_nominal'] ?? 0);
                $current['penjualan_total'] += (int) ($allocations[$index]['grand_total'] ?? 0);
                $current['stok_saat_ini'] = (int) $product->stok;

                $aggregates[$key] = $current;
            }
        }

        return $this->subtractReturns($aggregates, 'penjualan', $awal, $akhir, $idCabang);
    }

    protected function aggregateProdukPembelian(string $awal, string $akhir, int $idCabang): array
    {
        $headers = Pembelian::where('id_cabang', $idCabang)
            ->where('total_item', '>', 0)
            ->whereBetween('created_at', [$awal . ' 00:00:00', $akhir . ' 23:59:59'])
            ->get()
            ->keyBy('id_pembelian');

        $details = PembelianDetail::with('produk.kategori')
            ->whereIn('id_pembelian', $headers->keys())
            ->get()
            ->groupBy('id_pembelian');

        $aggregates = [];

        foreach ($headers as $header) {
            $transactionDetails = $details->get($header->id_pembelian, collect())->values();
            $totals = $this->calculator->calculateFromSubtotal(
                (int) $header->total_harga,
                (float) $header->diskon,
                (float) ($header->ppn_persen ?? 0),
                (int) $header->dibayar
            );
            $allocations = $this->calculator->allocateTransactionLines(
                $transactionDetails->all(),
                $totals['diskon_nominal'],
                $totals['ppn_nominal']
            );

            foreach ($transactionDetails as $index => $detail) {
                $product = $detail->produk;
                if (! $product) {
                    continue;
                }

                $key = $product->id_produk;
                $current = $aggregates[$key] ?? $this->emptyProdukRow(
                    $product->kode_produk,
                    $product->nama_produk,
                    optional($product->kategori)->nama_kategori ?? 'Tanpa Kategori'
                );

                $current['qty_beli'] += (int) $detail->jumlah;
                $current['pembelian_dpp'] += (int) ($allocations[$index]['dpp'] ?? 0);
                $current['pembelian_ppn'] += (int) ($allocations[$index]['ppn_nominal'] ?? 0);
                $current['pembelian_total'] += (int) ($allocations[$index]['grand_total'] ?? 0);
                $current['stok_saat_ini'] = (int) $product->stok;

                $aggregates[$key] = $current;
            }
        }

        return $this->subtractReturns($aggregates, 'pembelian', $awal, $akhir, $idCabang);
    }

    private function invoiceNet($header, string $type, string $end, array $totals): array
    {
        $id = $header->{'id_' . $type};
        $returns = DB::table('stock_returns')->where('type', $type)->where('transaction_id', $id)->where('date', '<=', $end)->get();
        $details = DB::table($type . '_detail')->where('id_' . $type, $id)->get()->keyBy('id_' . $type . '_detail');
        $returnedSubtotal = 0;
        foreach ($returns->groupBy('detail_id') as $detailId => $rows) {
            $detail = $details[$detailId];
            $returnedSubtotal += (int) round($detail->subtotal * $rows->sum('quantity') / $detail->jumlah);
        }
        $subtotal = (int) $header->total_harga - $returnedSubtotal;
        $dpp = (int) $totals['dpp'] - (int) ($returns->sum('amount') - $returns->sum('tax'));
        $tax = (int) $totals['ppn_nominal'] - (int) $returns->sum('tax');
        $total = (int) $header->bayar - (int) $returns->sum('amount');
        $paid = (int) DB::table($type . '_pembayaran')->where('id_' . $type, $id)->where('created_at', '<=', $end . ' 23:59:59')->sum('nominal') - (int) $returns->sum('refund');
        return ['quantity' => (int) $header->total_item - (int) $returns->sum('quantity'), 'subtotal' => $subtotal, 'discount' => $subtotal - $dpp, 'dpp' => $dpp, 'tax' => $tax, 'total' => $total, 'paid' => $paid, 'remaining' => max($total - $paid, 0), 'status' => status_pembayaran($total, $paid)];
    }

    private function subtractReturns(array $aggregates, string $type, string $start, string $end, int $branch): array
    {
        $returns = DB::table('stock_returns as r')->join('produk as p', 'p.id_produk', '=', 'r.id_produk')->leftJoin('kategori as k', 'k.id_kategori', '=', 'p.id_kategori')->where('r.id_cabang', $branch)->where('r.type', $type)->whereBetween('r.date', [$start, $end])->select('r.*', 'p.kode_produk', 'p.nama_produk', 'p.stok', 'k.nama_kategori')->get();
        foreach ($returns as $return) {
            $key = $return->id_produk;
            $row = $aggregates[$key] ?? $this->emptyProdukRow($return->kode_produk, $return->nama_produk, $return->nama_kategori ?? 'Tanpa Kategori');
            $row[$type === 'penjualan' ? 'qty_jual' : 'qty_beli'] -= (int) $return->quantity;
            $row[$type . '_dpp'] -= (int) $return->amount - (int) $return->tax;
            $row[$type . '_ppn'] -= (int) $return->tax;
            $row[$type . '_total'] -= (int) $return->amount;
            $row['stok_saat_ini'] = (int) $return->stok;
            $aggregates[$key] = $row;
        }
        return $aggregates;
    }

    protected function emptyProdukRow(string $kodeProduk, string $namaProduk, string $kategori): array
    {
        return [
            'kode_produk' => $kodeProduk,
            'nama_produk' => $namaProduk,
            'kategori' => $kategori,
            'qty_jual' => 0,
            'penjualan_dpp' => 0,
            'penjualan_ppn' => 0,
            'penjualan_total' => 0,
            'qty_beli' => 0,
            'pembelian_dpp' => 0,
            'pembelian_ppn' => 0,
            'pembelian_total' => 0,
            'saldo_qty' => 0,
            'stok_saat_ini' => 0,
        ];
    }
}
