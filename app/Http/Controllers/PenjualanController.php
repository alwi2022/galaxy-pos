<?php

namespace App\Http\Controllers;
// app/Http/Controllers/PenjualanController.php

use App\Models\Penjualan;
use App\Models\PenjualanDetail;
use App\Models\PenjualanPembayaran;
use App\Models\Produk;
use App\Models\Setting;
use App\Support\TransactionCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PDF;

class PenjualanController extends Controller
{
    protected TransactionCalculator $calculator;

    public function __construct(TransactionCalculator $calculator)
    {
        $this->calculator = $calculator;
    }

    public function index()
    {
        return view('penjualan.index');
    }

    public function data()
    {
        $penjualan = Penjualan::with(['member', 'user'])
            ->where('id_cabang', auth()->user()->id_cabang)
            ->where('total_item', '>', 0)
            ->orderBy('id_penjualan', 'desc')
            ->get();

        return datatables()
            ->of($penjualan)
            ->addIndexColumn()
            ->addColumn('total_item', function ($penjualan) {
                return format_uang($penjualan->total_item);
            })
            ->addColumn('total_harga', function ($penjualan) {
                return 'Rp. ' . format_uang($penjualan->total_harga);
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
            ->addColumn('tanggal', function ($penjualan) {
                return tanggal_indonesia($penjualan->created_at, false);
            })
            ->addColumn('kode_member', function ($penjualan) {
                if (! $penjualan->member) {
                    return '<span class="label label-default">Umum</span>';
                }

                return '<span class="label label-success">' . $penjualan->member->kode_member . '</span><br><small>' .
                    $penjualan->member->nama .
                    '</small>';
            })
            ->addColumn('metode_pembayaran', function ($penjualan) {
                return label_metode_pembayaran($penjualan->metode_pembayaran);
            })
            ->addColumn('skema_pembayaran', function ($penjualan) {
                return label_skema_pembayaran($penjualan->skema_pembayaran);
            })
            ->addColumn('ppn', function ($penjualan) {
                return rtrim(rtrim(number_format((float) ($penjualan->ppn_persen ?? 0), 2, ',', '.'), '0'), ',') .
                    '%<br><small>Rp. ' . format_uang($penjualan->ppn_nominal ?? 0) . '</small>';
            })
            ->addColumn('jatuh_tempo', function ($penjualan) {
                return $penjualan->jatuh_tempo ? tanggal_indonesia($penjualan->jatuh_tempo, false) : '-';
            })
            ->addColumn('status_pembayaran', function ($penjualan) {
                return '<span class="label '. class_status_pembayaran($penjualan->status_pembayaran) .'">' .
                    label_status_pembayaran($penjualan->status_pembayaran) .
                    '</span>';
            })
            ->editColumn('diskon', function ($penjualan) {
                return $penjualan->diskon . '%';
            })
            ->editColumn('kasir', function ($penjualan) {
                return $penjualan->user->name ?? '';
            })
            ->addColumn('aksi', function ($penjualan) {
                return '
                <div class="btn-group">
                    <button onclick="showDetail(`' . route('penjualan.show', $penjualan->id_penjualan) . '`)" class="btn btn-xs btn-info btn-flat"><i class="fa fa-eye"></i></button>
                    '. ($penjualan->sisa > 0 ? '<a href="' . route('pembayaran.index', ['tab' => 'piutang']) . '" class="btn btn-xs btn-warning btn-flat"><i class="fa fa-money"></i></a>' : '') .'
                    <button onclick="deleteData(`' . route('penjualan.destroy', $penjualan->id_penjualan) . '`)" class="btn btn-xs btn-danger btn-flat"><i class="fa fa-trash"></i></button>
                </div>
                ';
            })
            ->rawColumns(['aksi', 'kode_member', 'ppn', 'status_pembayaran'])
            ->make(true);
    }

    public function create()
    {
        $ppnPersen = Setting::defaultPpnPersen();

        $penjualan = new Penjualan();
        $penjualan->id_member = null;
        $penjualan->total_item = 0;
        $penjualan->total_harga = 0;
        $penjualan->diskon = 0;
        $penjualan->ppn_persen = $ppnPersen;
        $penjualan->ppn_nominal = 0;
        $penjualan->bayar = 0;
        $penjualan->diterima = 0;
        $penjualan->skema_pembayaran = 'langsung';
        $penjualan->metode_pembayaran = 'tunai';
        $penjualan->dibayar = 0;
        $penjualan->sisa = 0;
        $penjualan->status_pembayaran = 'belum_bayar';
        $penjualan->jatuh_tempo = null;
        $penjualan->id_user = auth()->id();
        $penjualan->id_cabang = auth()->user()->id_cabang; 
        $penjualan->save();

        session(['id_penjualan' => $penjualan->id_penjualan]);
        session()->forget('id_penjualan_selesai');
        return redirect()->route('transaksi.index');
    }

    public function store(Request $request)
    {
        $request->validate([
            'id_penjualan' => 'required|exists:penjualan,id_penjualan',
            'id_member' => 'nullable|exists:member,id_member',
            'diskon' => 'nullable|numeric|min:0|max:100',
            'diterima' => 'nullable|integer|min:0',
            'skema_pembayaran' => 'required|in:langsung,kredit',
            'metode_pembayaran' => 'required|in:tunai,transfer_bank,qris',
            'jatuh_tempo' => 'nullable|required_if:skema_pembayaran,kredit|date',
        ]);

        $nominalDiterima = max((int) $request->diterima, 0);

        if ($request->skema_pembayaran === 'kredit' && empty($request->id_member)) {
            return back()->withErrors(['id_member' => 'Penjualan kredit harus terhubung ke member.'])->withInput();
        }

        DB::transaction(function () use ($request, $nominalDiterima) {
            $penjualan = Penjualan::where('id_cabang', auth()->user()->id_cabang)
                ->findOrFail($request->id_penjualan);
            $detail = PenjualanDetail::where('id_penjualan', $penjualan->id_penjualan)->get();
            $ppnPersen = Setting::resolvePpnPersen($penjualan->ppn_persen);

            if ($detail->isEmpty()) {
                throw ValidationException::withMessages([
                    'transaksi' => 'Transaksi penjualan belum memiliki item.',
                ]);
            }

            $summary = $this->calculator->calculateFromSubtotal(
                (int) $detail->sum('subtotal'),
                (float) $request->diskon,
                $ppnPersen,
                $nominalDiterima
            );

            if ($request->skema_pembayaran === 'langsung' && $summary['dibayar'] < $summary['grand_total']) {
                throw ValidationException::withMessages([
                    'diterima' => 'Pembayaran langsung harus lunas.',
                ]);
            }

            if ($request->metode_pembayaran !== 'tunai' && $nominalDiterima > $summary['grand_total']) {
                throw ValidationException::withMessages([
                    'diterima' => 'Pembayaran non tunai tidak boleh melebihi total tagihan.',
                ]);
            }

            $penjualan->id_member = $request->id_member;
            $penjualan->total_item = (int) $detail->sum('jumlah');
            $penjualan->total_harga = $summary['subtotal'];
            $penjualan->diskon = $request->diskon;
            $penjualan->ppn_persen = $summary['ppn_persen'];
            $penjualan->ppn_nominal = $summary['ppn_nominal'];
            $penjualan->bayar = $summary['grand_total'];
            $penjualan->diterima = $nominalDiterima;
            $penjualan->skema_pembayaran = $request->skema_pembayaran;
            $penjualan->metode_pembayaran = $request->metode_pembayaran;
            $penjualan->jatuh_tempo = $request->skema_pembayaran === 'kredit' ? $request->jatuh_tempo : null;
            $penjualan->id_cabang = auth()->user()->id_cabang;
            $penjualan->update();

            foreach ($detail as $item) {
                $item->diskon = $request->diskon;
                $item->update();

                $produk = Produk::find($item->id_produk);
                $produk->stok -= $item->jumlah;
                $produk->update();
            }

            if ($summary['dibayar'] > 0) {
                PenjualanPembayaran::create([
                    'id_penjualan' => $penjualan->id_penjualan,
                    'nominal' => $summary['dibayar'],
                    'metode_pembayaran' => $request->metode_pembayaran,
                    'keterangan' => 'Pembayaran awal transaksi',
                    'id_user' => auth()->id(),
                    'id_cabang' => auth()->user()->id_cabang,
                ]);
            }

            $penjualan->syncStatusPembayaran();

            session([
                'id_penjualan_selesai' => $penjualan->id_penjualan,
            ]);
            session()->forget('id_penjualan');
        });

        return redirect()->route('transaksi.selesai');
    }

    public function show($id)
    {
        $detail = PenjualanDetail::with('produk')->where('id_penjualan', $id)->get();

        return datatables()
            ->of($detail)
            ->addIndexColumn()
            ->addColumn('kode_produk', function ($detail) {
                return '<span class="label label-success">' . $detail->produk->kode_produk . '</span>';
            })
            ->addColumn('nama_produk', function ($detail) {
                return $detail->produk->nama_produk;
            })
            ->addColumn('harga_jual', function ($detail) {
                return 'Rp. ' . format_uang($detail->harga_jual);
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
        $penjualan = Penjualan::where('id_cabang', auth()->user()->id_cabang)->findOrFail($id);
        $detail = PenjualanDetail::where('id_penjualan', $penjualan->id_penjualan)->get();
        foreach ($detail as $item) {
            $produk = Produk::find($item->id_produk);
            if ($produk) {
                $produk->stok += $item->jumlah;
                $produk->update();
            }

            $item->delete();
        }

        PenjualanPembayaran::where('id_penjualan', $penjualan->id_penjualan)->delete();
        $penjualan->delete();

        return response(null, 204);
    }

    public function selesai()
    {
        $setting = Setting::first();

        return view('penjualan.selesai', compact('setting'));
    }

    public function notaKecil()
    {
        $setting = Setting::first();
        $penjualan = Penjualan::find(session('id_penjualan_selesai', session('id_penjualan')));
        if (!$penjualan) {
            abort(404);
        }
        $detail = PenjualanDetail::with('produk')
            ->where('id_penjualan', $penjualan->id_penjualan)
            ->get();

        return view('penjualan.nota_kecil', compact('setting', 'penjualan', 'detail'));
    }

    public function notaBesar()
    {
        $setting = Setting::first();
        $penjualan = Penjualan::find(session('id_penjualan_selesai', session('id_penjualan')));
        if (!$penjualan) {
            abort(404);
        }
        $detail = PenjualanDetail::with('produk')
            ->where('id_penjualan', $penjualan->id_penjualan)
            ->get();

        $pdf = PDF::loadView('penjualan.nota_besar', compact('setting', 'penjualan', 'detail'));
        $pdf->setPaper(0, 0, 609, 440, 'potrait');
        return $pdf->stream('Transaksi-' . date('Y-m-d-his') . '.pdf');
    }
}
