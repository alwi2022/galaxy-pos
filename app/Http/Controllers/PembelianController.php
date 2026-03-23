<?php

namespace App\Http\Controllers;
// app/Http/Controllers/PembelianController.php

use App\Models\Pembelian;
use App\Models\PembelianDetail;
use App\Models\PembelianPembayaran;
use App\Models\Produk;
use App\Models\Setting;
use App\Models\Supplier;
use App\Support\TransactionCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PembelianController extends Controller
{
    protected TransactionCalculator $calculator;

    public function __construct(TransactionCalculator $calculator)
    {
        $this->calculator = $calculator;
    }

    public function index()
    {
        $supplier = Supplier::orderBy('nama')
            ->where('id_cabang', auth()->user()->id_cabang)
            ->get();

        return view('pembelian.index', compact('supplier'));
    }

    public function data()
    {
        $pembelian = Pembelian::with('supplier')
            ->where('id_cabang', auth()->user()->id_cabang)
            ->where('total_item', '>', 0)
            ->orderBy('id_pembelian', 'desc')
            ->get();

        return datatables()
            ->of($pembelian)
            ->addIndexColumn()
            ->addColumn('total_item', function ($pembelian) {
                return format_uang($pembelian->total_item);
            })
            ->addColumn('total_harga', function ($pembelian) {
                return 'Rp. ' . format_uang($pembelian->total_harga);
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
            ->addColumn('tanggal', function ($pembelian) {
                return tanggal_indonesia($pembelian->created_at, false);
            })
            ->addColumn('supplier', function ($pembelian) {
                return $pembelian->supplier->nama ?? '-';
            })
            ->addColumn('metode_pembayaran', function ($pembelian) {
                return label_metode_pembayaran($pembelian->metode_pembayaran);
            })
            ->addColumn('skema_pembayaran', function ($pembelian) {
                return label_skema_pembayaran($pembelian->skema_pembayaran);
            })
            ->addColumn('ppn', function ($pembelian) {
                return rtrim(rtrim(number_format((float) ($pembelian->ppn_persen ?? 0), 2, ',', '.'), '0'), ',') .
                    '%<br><small>Rp. ' . format_uang($pembelian->ppn_nominal ?? 0) . '</small>';
            })
            ->addColumn('jatuh_tempo', function ($pembelian) {
                return $pembelian->jatuh_tempo ? tanggal_indonesia($pembelian->jatuh_tempo, false) : '-';
            })
            ->addColumn('status_pembayaran', function ($pembelian) {
                return '<span class="label '. class_status_pembayaran($pembelian->status_pembayaran) .'">' .
                    label_status_pembayaran($pembelian->status_pembayaran) .
                    '</span>';
            })
            ->editColumn('diskon', function ($pembelian) {
                return $pembelian->diskon . '%';
            })
            ->addColumn('aksi', function ($pembelian) {
                return '
                <div class="btn-group">
                    <button onclick="showDetail(`' . route('pembelian.show', $pembelian->id_pembelian) . '`)" class="btn btn-xs btn-info btn-flat"><i class="fa fa-eye"></i></button>
                    '. ($pembelian->sisa > 0 ? '<a href="' . route('pembayaran.index', ['tab' => 'hutang']) . '" class="btn btn-xs btn-warning btn-flat"><i class="fa fa-money"></i></a>' : '') .'
                    <button onclick="deleteData(`' . route('pembelian.destroy', $pembelian->id_pembelian) . '`)" class="btn btn-xs btn-danger btn-flat"><i class="fa fa-trash"></i></button>
                </div>
                ';
            })
            ->rawColumns(['aksi', 'ppn', 'status_pembayaran'])
            ->make(true);
    }

    public function create($id)
    {
        $ppnPersen = Setting::defaultPpnPersen();

        $pembelian = new Pembelian();
        $pembelian->id_supplier = $id;
        $pembelian->total_item = 0;
        $pembelian->total_harga = 0;
        $pembelian->diskon = 0;
        $pembelian->ppn_persen = $ppnPersen;
        $pembelian->ppn_nominal = 0;
        $pembelian->bayar = 0;
        $pembelian->skema_pembayaran = 'langsung';
        $pembelian->metode_pembayaran = 'tunai';
        $pembelian->dibayar = 0;
        $pembelian->sisa = 0;
        $pembelian->status_pembayaran = 'belum_bayar';
        $pembelian->jatuh_tempo = null;
        $pembelian->id_cabang = auth()->user()->id_cabang;
        $pembelian->save();

        session(['id_pembelian' => $pembelian->id_pembelian]);
        session(['id_supplier' => $pembelian->id_supplier]);
        session()->forget('id_pembelian_selesai');

        return redirect()->route('pembelian_detail.index');
    }

    public function store(Request $request)
    {
        $request->validate([
            'id_pembelian' => 'required|exists:pembelian,id_pembelian',
            'diskon' => 'nullable|numeric|min:0|max:100',
            'nominal_dibayar' => 'nullable|integer|min:0',
            'skema_pembayaran' => 'required|in:langsung,kredit',
            'metode_pembayaran' => 'required|in:tunai,transfer_bank,qris',
            'jatuh_tempo' => 'nullable|required_if:skema_pembayaran,kredit|date',
        ]);

        $nominalDibayar = max((int) $request->nominal_dibayar, 0);

        DB::transaction(function () use ($request, $nominalDibayar) {
            $pembelian = Pembelian::where('id_cabang', auth()->user()->id_cabang)
                ->findOrFail($request->id_pembelian);
            $detail = PembelianDetail::where('id_pembelian', $pembelian->id_pembelian)->get();
            $ppnPersen = Setting::resolvePpnPersen($pembelian->ppn_persen);

            if ($detail->isEmpty()) {
                throw ValidationException::withMessages([
                    'transaksi' => 'Transaksi pembelian belum memiliki item.',
                ]);
            }

            $summary = $this->calculator->calculateFromSubtotal(
                (int) $detail->sum('subtotal'),
                (float) $request->diskon,
                $ppnPersen,
                $nominalDibayar
            );

            if ($request->skema_pembayaran === 'langsung' && $summary['dibayar'] < $summary['grand_total']) {
                throw ValidationException::withMessages([
                    'nominal_dibayar' => 'Pembayaran langsung harus lunas.',
                ]);
            }

            if ($request->metode_pembayaran !== 'tunai' && $nominalDibayar > $summary['grand_total']) {
                throw ValidationException::withMessages([
                    'nominal_dibayar' => 'Pembayaran non tunai tidak boleh melebihi total tagihan.',
                ]);
            }

            $pembelian->total_item = (int) $detail->sum('jumlah');
            $pembelian->total_harga = $summary['subtotal'];
            $pembelian->diskon = $request->diskon;
            $pembelian->ppn_persen = $summary['ppn_persen'];
            $pembelian->ppn_nominal = $summary['ppn_nominal'];
            $pembelian->bayar = $summary['grand_total'];
            $pembelian->skema_pembayaran = $request->skema_pembayaran;
            $pembelian->metode_pembayaran = $request->metode_pembayaran;
            $pembelian->jatuh_tempo = $request->skema_pembayaran === 'kredit' ? $request->jatuh_tempo : null;
            $pembelian->update();

            foreach ($detail as $item) {
                $produk = Produk::find($item->id_produk);
                $produk->stok += $item->jumlah;
                $produk->update();
            }

            if ($summary['dibayar'] > 0) {
                PembelianPembayaran::create([
                    'id_pembelian' => $pembelian->id_pembelian,
                    'nominal' => $summary['dibayar'],
                    'metode_pembayaran' => $request->metode_pembayaran,
                    'keterangan' => 'Pembayaran awal transaksi',
                    'id_user' => auth()->id(),
                    'id_cabang' => auth()->user()->id_cabang,
                ]);
            }

            $pembelian->syncStatusPembayaran();

            session()->forget(['id_pembelian', 'id_supplier']);
        });

        return redirect()->route('pembelian.index');
    }

    public function show($id)
    {
        $detail = PembelianDetail::with('produk')->where('id_pembelian', $id)->get();

        return datatables()
            ->of($detail)
            ->addIndexColumn()
            ->addColumn('kode_produk', function ($detail) {
                return '<span class="label label-success">' . $detail->produk->kode_produk . '</span>';
            })
            ->addColumn('nama_produk', function ($detail) {
                return $detail->produk->nama_produk;
            })
            ->addColumn('harga_beli', function ($detail) {
                return 'Rp. ' . format_uang($detail->harga_beli);
            })
            ->addColumn('jumlah', function ($detail) {
                return format_uang($detail->jumlah);
            })
            ->addColumn('subtotal', function ($detail) {
                return 'Rp. ' . format_uang($detail->subtotal);
            })
            ->rawColumns(['kode_produk'])
            ->make(true);
    }

    public function destroy($id)
    {
        $pembelian = Pembelian::where('id_cabang', auth()->user()->id_cabang)->findOrFail($id);
        $detail = PembelianDetail::where('id_pembelian', $pembelian->id_pembelian)->get();
        foreach ($detail as $item) {
            $produk = Produk::find($item->id_produk);
            if ($produk) {
                $produk->stok -= $item->jumlah;
                $produk->update();
            }
            $item->delete();
        }

        PembelianPembayaran::where('id_pembelian', $pembelian->id_pembelian)->delete();
        $pembelian->delete();

        return response(null, 204);
    }
}
