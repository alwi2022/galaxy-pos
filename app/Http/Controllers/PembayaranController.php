<?php

namespace App\Http\Controllers;

use App\Models\Pembelian;
use App\Models\PembelianPembayaran;
use App\Models\Penjualan;
use App\Models\PenjualanPembayaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PembayaranController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->get('tab', 'piutang');
        $canManageHutang = auth()->user()->level == 1;

        if (! $canManageHutang && $tab === 'hutang') {
            $tab = 'piutang';
        }

        return view('pembayaran.index', compact('tab', 'canManageHutang'));
    }

    public function piutangData()
    {
        $penjualan = Penjualan::with(['member', 'user'])
            ->where('id_cabang', auth()->user()->id_cabang)
            ->where('sisa', '>', 0)
            ->orderBy('created_at', 'desc')
            ->get();

        return datatables()
            ->of($penjualan)
            ->addIndexColumn()
            ->addColumn('tanggal', function ($penjualan) {
                return tanggal_indonesia($penjualan->created_at, false);
            })
            ->addColumn('pelanggan', function ($penjualan) {
                if ($penjualan->member) {
                    return $penjualan->member->nama . ' <br><small>' . $penjualan->member->kode_member . '</small>';
                }

                return 'Umum';
            })
            ->addColumn('tagihan', function ($penjualan) {
                return 'Rp. ' . format_uang($penjualan->bayar);
            })
            ->addColumn('dibayar', function ($penjualan) {
                return 'Rp. ' . format_uang($penjualan->dibayar);
            })
            ->addColumn('sisa', function ($penjualan) {
                return 'Rp. ' . format_uang($penjualan->sisa);
            })
            ->addColumn('jatuh_tempo', function ($penjualan) {
                return $penjualan->jatuh_tempo
                    ? tanggal_indonesia($penjualan->jatuh_tempo, false)
                    : '-';
            })
            ->addColumn('status_pembayaran', function ($penjualan) {
                return '<span class="label '. class_status_pembayaran($penjualan->status_pembayaran) .'">' .
                    label_status_pembayaran($penjualan->status_pembayaran) .
                    '</span>';
            })
            ->addColumn('aksi', function ($penjualan) {
                return '<button type="button" class="btn btn-xs btn-primary btn-flat" onclick="openPaymentModal(\'piutang\', '. $penjualan->id_penjualan .')"><i class="fa fa-money"></i> Bayar</button>';
            })
            ->rawColumns(['pelanggan', 'status_pembayaran', 'aksi'])
            ->make(true);
    }

    public function hutangData()
    {
        $pembelian = Pembelian::with('supplier')
            ->where('id_cabang', auth()->user()->id_cabang)
            ->where('sisa', '>', 0)
            ->orderBy('created_at', 'desc')
            ->get();

        return datatables()
            ->of($pembelian)
            ->addIndexColumn()
            ->addColumn('tanggal', function ($pembelian) {
                return tanggal_indonesia($pembelian->created_at, false);
            })
            ->addColumn('supplier', function ($pembelian) {
                return $pembelian->supplier->nama ?? '-';
            })
            ->addColumn('tagihan', function ($pembelian) {
                return 'Rp. ' . format_uang($pembelian->bayar);
            })
            ->addColumn('dibayar', function ($pembelian) {
                return 'Rp. ' . format_uang($pembelian->dibayar);
            })
            ->addColumn('sisa', function ($pembelian) {
                return 'Rp. ' . format_uang($pembelian->sisa);
            })
            ->addColumn('jatuh_tempo', function ($pembelian) {
                return $pembelian->jatuh_tempo
                    ? tanggal_indonesia($pembelian->jatuh_tempo, false)
                    : '-';
            })
            ->addColumn('status_pembayaran', function ($pembelian) {
                return '<span class="label '. class_status_pembayaran($pembelian->status_pembayaran) .'">' .
                    label_status_pembayaran($pembelian->status_pembayaran) .
                    '</span>';
            })
            ->addColumn('aksi', function ($pembelian) {
                return '<button type="button" class="btn btn-xs btn-primary btn-flat" onclick="openPaymentModal(\'hutang\', '. $pembelian->id_pembelian .')"><i class="fa fa-money"></i> Bayar</button>';
            })
            ->rawColumns(['status_pembayaran', 'aksi'])
            ->make(true);
    }

    public function showPiutang($id)
    {
        $penjualan = Penjualan::with([
            'member',
            'user',
            'pembayaran' => function ($query) {
                $query->latest();
            },
        ])
            ->where('id_cabang', auth()->user()->id_cabang)
            ->findOrFail($id);

        return response()->json([
            'jenis' => 'piutang',
            'judul' => 'Pembayaran Piutang Penjualan',
            'route' => route('pembayaran.piutang.store', $penjualan->id_penjualan),
            'transaksi' => [
                'nomor' => tambah_nol_didepan($penjualan->id_penjualan, 10),
                'pihak' => $penjualan->member->nama ?? 'Umum',
                'tanggal' => tanggal_indonesia($penjualan->created_at, false),
                'tagihan' => format_uang($penjualan->bayar),
                'dibayar' => format_uang($penjualan->dibayar),
                'sisa' => format_uang($penjualan->sisa),
                'jatuh_tempo' => $penjualan->jatuh_tempo ? tanggal_indonesia($penjualan->jatuh_tempo, false) : '-',
                'status' => label_status_pembayaran($penjualan->status_pembayaran),
            ],
            'history' => $this->mapRiwayat($penjualan->pembayaran),
        ]);
    }

    public function showHutang($id)
    {
        $pembelian = Pembelian::with([
            'supplier',
            'pembayaran' => function ($query) {
                $query->latest();
            },
        ])
            ->where('id_cabang', auth()->user()->id_cabang)
            ->findOrFail($id);

        return response()->json([
            'jenis' => 'hutang',
            'judul' => 'Pembayaran Hutang Pembelian',
            'route' => route('pembayaran.hutang.store', $pembelian->id_pembelian),
            'transaksi' => [
                'nomor' => tambah_nol_didepan($pembelian->id_pembelian, 10),
                'pihak' => $pembelian->supplier->nama ?? '-',
                'tanggal' => tanggal_indonesia($pembelian->created_at, false),
                'tagihan' => format_uang($pembelian->bayar),
                'dibayar' => format_uang($pembelian->dibayar),
                'sisa' => format_uang($pembelian->sisa),
                'jatuh_tempo' => $pembelian->jatuh_tempo ? tanggal_indonesia($pembelian->jatuh_tempo, false) : '-',
                'status' => label_status_pembayaran($pembelian->status_pembayaran),
            ],
            'history' => $this->mapRiwayat($pembelian->pembayaran),
        ]);
    }

    public function storePiutang(Request $request, $id)
    {
        $request->validate([
            'nominal' => 'required|integer|min:1',
            'metode_pembayaran' => 'required|in:tunai,transfer_bank',
            'keterangan' => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($request, $id) {
            $penjualan = Penjualan::where('id_cabang', auth()->user()->id_cabang)->findOrFail($id);
            $nominal = (int) $request->nominal;

            if ($nominal <= 0 || $nominal > (int) $penjualan->sisa) {
                abort(422, 'Nominal pembayaran melebihi sisa piutang.');
            }

            PenjualanPembayaran::create([
                'id_penjualan' => $penjualan->id_penjualan,
                'nominal' => $nominal,
                'metode_pembayaran' => $request->metode_pembayaran,
                'keterangan' => $request->keterangan,
                'id_user' => auth()->id(),
                'id_cabang' => auth()->user()->id_cabang,
            ]);

            $penjualan->syncStatusPembayaran();
        });

        return response()->json([
            'message' => 'Pembayaran piutang berhasil disimpan.',
        ]);
    }

    public function storeHutang(Request $request, $id)
    {
        $request->validate([
            'nominal' => 'required|integer|min:1',
            'metode_pembayaran' => 'required|in:tunai,transfer_bank',
            'keterangan' => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($request, $id) {
            $pembelian = Pembelian::where('id_cabang', auth()->user()->id_cabang)->findOrFail($id);
            $nominal = (int) $request->nominal;

            if ($nominal <= 0 || $nominal > (int) $pembelian->sisa) {
                abort(422, 'Nominal pembayaran melebihi sisa hutang.');
            }

            PembelianPembayaran::create([
                'id_pembelian' => $pembelian->id_pembelian,
                'nominal' => $nominal,
                'metode_pembayaran' => $request->metode_pembayaran,
                'keterangan' => $request->keterangan,
                'id_user' => auth()->id(),
                'id_cabang' => auth()->user()->id_cabang,
            ]);

            $pembelian->syncStatusPembayaran();
        });

        return response()->json([
            'message' => 'Pembayaran hutang berhasil disimpan.',
        ]);
    }

    protected function mapRiwayat($riwayat)
    {
        return $riwayat->map(function ($item) {
            return [
                'tanggal' => optional($item->created_at)->format('d-m-Y H:i'),
                'metode' => label_metode_pembayaran($item->metode_pembayaran),
                'nominal' => format_uang($item->nominal),
                'keterangan' => $item->keterangan ?: '-',
            ];
        })->values();
    }
}
