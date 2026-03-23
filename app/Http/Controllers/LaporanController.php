<?php

namespace App\Http\Controllers;

use App\Exports\ReportExport;
use App\Services\LaporanService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use PDF;

class LaporanController extends Controller
{
    protected LaporanService $laporanService;

    public function __construct(LaporanService $laporanService)
    {
        $this->laporanService = $laporanService;
    }

    public function index(Request $request)
    {
        $tanggalAwal = date('Y-m-d', mktime(0, 0, 0, date('m'), 1, date('Y')));
        $tanggalAkhir = date('Y-m-d');

        if ($request->filled('tanggal_awal') && $request->filled('tanggal_akhir')) {
            $tanggalAwal = $request->tanggal_awal;
            $tanggalAkhir = $request->tanggal_akhir;
        }

        $activeTab = $this->normalizeTab($request->get('tab', 'ringkasan'));

        return view('laporan.index', compact('tanggalAwal', 'tanggalAkhir', 'activeTab'));
    }

    public function data(Request $request, $awal, $akhir)
    {
        $tab = $this->normalizeTab($request->get('tab', 'ringkasan'));
        $rows = $this->formatForTable($tab, $this->getRawRows($tab, $awal, $akhir));

        return datatables()
            ->of($rows)
            ->rawColumns(['status'])
            ->make(true);
    }

    public function exportPDF($awal, $akhir)
    {
        $data = $this->formatForTable('ringkasan', $this->getRawRows('ringkasan', $awal, $akhir));
        $pdf = PDF::loadView('laporan.pdf', compact('awal', 'akhir', 'data'));
        $pdf->setPaper('a4', 'landscape');

        return $pdf->stream('laporan-ringkasan-' . date('Y-m-d-his') . '.pdf');
    }

    public function exportExcel($tab, $awal, $akhir)
    {
        $tab = $this->normalizeTab($tab);
        [$headings, $rows, $filename] = $this->buildExportPayload($tab, $this->getRawRows($tab, $awal, $akhir), $awal, $akhir);

        return Excel::download(new ReportExport($headings, $rows), $filename);
    }

    protected function getRawRows(string $tab, string $awal, string $akhir)
    {
        $idCabang = auth()->user()->id_cabang;

        if ($tab === 'ringkasan') {
            return $this->appendRingkasanTotalRow(
                $this->laporanService->ringkasan($awal, $akhir, $idCabang)
            );
        }

        if ($tab === 'penjualan') {
            return $this->laporanService->penjualanPeriode($awal, $akhir, $idCabang);
        }

        if ($tab === 'pembelian') {
            return $this->laporanService->pembelianPeriode($awal, $akhir, $idCabang);
        }

        if ($tab === 'kategori') {
            return $this->laporanService->laporanKategori($awal, $akhir, $idCabang);
        }

        return $this->laporanService->laporanProduk($awal, $akhir, $idCabang);
    }

    protected function appendRingkasanTotalRow(array $rows): array
    {
        $rows[] = [
            'DT_RowIndex' => '',
            'tanggal' => 'TOTAL',
            'penjualan_masuk' => array_sum(array_column($rows, 'penjualan_masuk')),
            'pembelian_keluar' => array_sum(array_column($rows, 'pembelian_keluar')),
            'pendapatan_lain' => array_sum(array_column($rows, 'pendapatan_lain')),
            'servis' => array_sum(array_column($rows, 'servis')),
            'biaya' => array_sum(array_column($rows, 'biaya')),
            'pendapatan_bersih' => array_sum(array_column($rows, 'pendapatan_bersih')),
        ];

        return $rows;
    }

    protected function formatForTable(string $tab, $rows)
    {
        if ($tab === 'ringkasan') {
            return collect($rows)->map(function ($row) {
                return [
                    'DT_RowIndex' => $row['DT_RowIndex'],
                    'tanggal' => $row['tanggal'],
                    'penjualan_masuk' => format_uang($row['penjualan_masuk']),
                    'pembelian_keluar' => format_uang($row['pembelian_keluar']),
                    'pendapatan_lain' => format_uang($row['pendapatan_lain']),
                    'servis' => format_uang($row['servis']),
                    'biaya' => format_uang($row['biaya']),
                    'pendapatan_bersih' => format_uang($row['pendapatan_bersih']),
                ];
            })->values();
        }

        if ($tab === 'penjualan') {
            return $rows->map(function ($row) {
                return [
                    'tanggal' => $row['tanggal'],
                    'nomor' => $row['nomor'],
                    'pelanggan' => $row['pelanggan'],
                    'total_item' => format_uang($row['total_item']),
                    'subtotal' => format_uang($row['subtotal']),
                    'diskon' => rtrim(rtrim(number_format($row['diskon_persen'], 2, ',', '.'), '0'), ',') . '%',
                    'diskon_nominal' => format_uang($row['diskon_nominal']),
                    'dpp' => format_uang($row['dpp']),
                    'ppn' => rtrim(rtrim(number_format($row['ppn_persen'], 2, ',', '.'), '0'), ',') . '%',
                    'ppn_nominal' => format_uang($row['ppn_nominal']),
                    'grand_total' => format_uang($row['grand_total']),
                    'dibayar' => format_uang($row['dibayar']),
                    'sisa' => format_uang($row['sisa']),
                    'skema' => $row['skema'],
                    'metode' => $row['metode'],
                    'status' => '<span class="label ' . class_status_pembayaran($row['status_kode']) . '">' . $row['status'] . '</span>',
                    'jatuh_tempo' => $row['jatuh_tempo'],
                    'kasir' => $row['kasir'],
                ];
            })->values();
        }

        if ($tab === 'pembelian') {
            return $rows->map(function ($row) {
                return [
                    'tanggal' => $row['tanggal'],
                    'nomor' => $row['nomor'],
                    'supplier' => $row['supplier'],
                    'total_item' => format_uang($row['total_item']),
                    'subtotal' => format_uang($row['subtotal']),
                    'diskon' => rtrim(rtrim(number_format($row['diskon_persen'], 2, ',', '.'), '0'), ',') . '%',
                    'diskon_nominal' => format_uang($row['diskon_nominal']),
                    'dpp' => format_uang($row['dpp']),
                    'ppn' => rtrim(rtrim(number_format($row['ppn_persen'], 2, ',', '.'), '0'), ',') . '%',
                    'ppn_nominal' => format_uang($row['ppn_nominal']),
                    'grand_total' => format_uang($row['grand_total']),
                    'dibayar' => format_uang($row['dibayar']),
                    'sisa' => format_uang($row['sisa']),
                    'skema' => $row['skema'],
                    'metode' => $row['metode'],
                    'status' => '<span class="label ' . class_status_pembayaran($row['status_kode']) . '">' . $row['status'] . '</span>',
                    'jatuh_tempo' => $row['jatuh_tempo'],
                ];
            })->values();
        }

        if ($tab === 'kategori') {
            return $rows->map(function ($row) {
                return [
                    'kategori' => $row['kategori'],
                    'jumlah_produk' => format_uang($row['jumlah_produk']),
                    'qty_jual' => format_uang($row['qty_jual']),
                    'penjualan_dpp' => format_uang($row['penjualan_dpp']),
                    'penjualan_ppn' => format_uang($row['penjualan_ppn']),
                    'penjualan_total' => format_uang($row['penjualan_total']),
                    'qty_beli' => format_uang($row['qty_beli']),
                    'pembelian_dpp' => format_uang($row['pembelian_dpp']),
                    'pembelian_ppn' => format_uang($row['pembelian_ppn']),
                    'pembelian_total' => format_uang($row['pembelian_total']),
                    'saldo_qty' => format_uang($row['saldo_qty']),
                ];
            })->values();
        }

        return $rows->map(function ($row) {
            return [
                'kode_produk' => $row['kode_produk'],
                'nama_produk' => $row['nama_produk'],
                'kategori' => $row['kategori'],
                'qty_jual' => format_uang($row['qty_jual']),
                'penjualan_dpp' => format_uang($row['penjualan_dpp']),
                'penjualan_ppn' => format_uang($row['penjualan_ppn']),
                'penjualan_total' => format_uang($row['penjualan_total']),
                'qty_beli' => format_uang($row['qty_beli']),
                'pembelian_dpp' => format_uang($row['pembelian_dpp']),
                'pembelian_ppn' => format_uang($row['pembelian_ppn']),
                'pembelian_total' => format_uang($row['pembelian_total']),
                'saldo_qty' => format_uang($row['saldo_qty']),
                'stok_saat_ini' => format_uang($row['stok_saat_ini']),
            ];
        })->values();
    }

    protected function buildExportPayload(string $tab, $rows, string $awal, string $akhir): array
    {
        if ($tab === 'ringkasan') {
            return [
                ['Tanggal', 'Penjualan Masuk', 'Pembelian Keluar', 'Pendapatan Lain', 'Servis', 'Biaya', 'Pendapatan Bersih'],
                collect($rows)->map(function ($row) {
                    return [
                        $row['tanggal'],
                        $row['penjualan_masuk'],
                        $row['pembelian_keluar'],
                        $row['pendapatan_lain'],
                        $row['servis'],
                        $row['biaya'],
                        $row['pendapatan_bersih'],
                    ];
                })->values()->all(),
                "laporan-ringkasan-{$awal}-{$akhir}.xlsx",
            ];
        }

        if ($tab === 'penjualan') {
            return [
                ['Tanggal', 'No Transaksi', 'Pelanggan', 'Total Item', 'Subtotal', 'Diskon %', 'Diskon Nominal', 'DPP', 'PPN %', 'PPN Nominal', 'Grand Total', 'Dibayar', 'Sisa', 'Skema', 'Metode', 'Status', 'Jatuh Tempo', 'Kasir'],
                $rows->map(function ($row) {
                    return [
                        $row['tanggal'],
                        $row['nomor'],
                        $row['pelanggan'],
                        $row['total_item'],
                        $row['subtotal'],
                        $row['diskon_persen'],
                        $row['diskon_nominal'],
                        $row['dpp'],
                        $row['ppn_persen'],
                        $row['ppn_nominal'],
                        $row['grand_total'],
                        $row['dibayar'],
                        $row['sisa'],
                        $row['skema'],
                        $row['metode'],
                        $row['status'],
                        $row['jatuh_tempo'],
                        $row['kasir'],
                    ];
                })->values()->all(),
                "laporan-penjualan-{$awal}-{$akhir}.xlsx",
            ];
        }

        if ($tab === 'pembelian') {
            return [
                ['Tanggal', 'No Transaksi', 'Supplier', 'Total Item', 'Subtotal', 'Diskon %', 'Diskon Nominal', 'DPP', 'PPN %', 'PPN Nominal', 'Grand Total', 'Dibayar', 'Sisa', 'Skema', 'Metode', 'Status', 'Jatuh Tempo'],
                $rows->map(function ($row) {
                    return [
                        $row['tanggal'],
                        $row['nomor'],
                        $row['supplier'],
                        $row['total_item'],
                        $row['subtotal'],
                        $row['diskon_persen'],
                        $row['diskon_nominal'],
                        $row['dpp'],
                        $row['ppn_persen'],
                        $row['ppn_nominal'],
                        $row['grand_total'],
                        $row['dibayar'],
                        $row['sisa'],
                        $row['skema'],
                        $row['metode'],
                        $row['status'],
                        $row['jatuh_tempo'],
                    ];
                })->values()->all(),
                "laporan-pembelian-{$awal}-{$akhir}.xlsx",
            ];
        }

        if ($tab === 'kategori') {
            return [
                ['Kategori', 'Jumlah Produk', 'Qty Jual', 'Penjualan DPP', 'Penjualan PPN', 'Penjualan Total', 'Qty Beli', 'Pembelian DPP', 'Pembelian PPN', 'Pembelian Total', 'Saldo Qty'],
                $rows->map(function ($row) {
                    return [
                        $row['kategori'],
                        $row['jumlah_produk'],
                        $row['qty_jual'],
                        $row['penjualan_dpp'],
                        $row['penjualan_ppn'],
                        $row['penjualan_total'],
                        $row['qty_beli'],
                        $row['pembelian_dpp'],
                        $row['pembelian_ppn'],
                        $row['pembelian_total'],
                        $row['saldo_qty'],
                    ];
                })->values()->all(),
                "laporan-kategori-{$awal}-{$akhir}.xlsx",
            ];
        }

        return [
            ['Kode Produk', 'Nama Produk', 'Kategori', 'Qty Jual', 'Penjualan DPP', 'Penjualan PPN', 'Penjualan Total', 'Qty Beli', 'Pembelian DPP', 'Pembelian PPN', 'Pembelian Total', 'Saldo Qty', 'Stok Saat Ini'],
            $rows->map(function ($row) {
                return [
                    $row['kode_produk'],
                    $row['nama_produk'],
                    $row['kategori'],
                    $row['qty_jual'],
                    $row['penjualan_dpp'],
                    $row['penjualan_ppn'],
                    $row['penjualan_total'],
                    $row['qty_beli'],
                    $row['pembelian_dpp'],
                    $row['pembelian_ppn'],
                    $row['pembelian_total'],
                    $row['saldo_qty'],
                    $row['stok_saat_ini'],
                ];
            })->values()->all(),
            "laporan-produk-{$awal}-{$akhir}.xlsx",
        ];
    }

    protected function normalizeTab(string $tab): string
    {
        $allowed = ['ringkasan', 'penjualan', 'pembelian', 'produk', 'kategori'];

        return in_array($tab, $allowed, true) ? $tab : 'ringkasan';
    }
}
