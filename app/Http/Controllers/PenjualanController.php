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
        $notaRoute = (int) (Setting::first()->tipe_nota ?? 1) === 1 ? 'penjualan.nota_kecil' : 'penjualan.nota_besar';
        $penjualan =Penjualan::with(['member', 'user'])
            ->where('id_cabang', auth()->user()->id_cabang)
            ->where('total_item', '>', 0)
            ->whereNull('cancelled_at')
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
            ->addColumn('aksi', function ($penjualan) use ($notaRoute) {
                return '
                <div class="btn-group">
                    <button onclick="showDetail(`' . route('penjualan.show', $penjualan->id_penjualan) . '`)" class="btn btn-xs btn-info btn-flat"><i class="fa fa-eye"></i></button>
                    <a href="' . route($notaRoute, $penjualan->id_penjualan) . '" target="_blank" rel="noopener" class="btn btn-xs btn-default btn-flat" title="Cetak ulang nota"><i class="fa fa-print"></i></a>
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
            'metode_pembayaran' => ['required', \Illuminate\Validation\Rule::in(array_keys(daftar_metode_pembayaran()))],
            'jatuh_tempo' => 'nullable|required_if:skema_pembayaran,kredit|date',
        ]);

        $nominalDiterima = max((int) $request->diterima, 0);

        if ($request->skema_pembayaran === 'kredit' && empty($request->id_member)) {
            return back()->withErrors(['id_member' => 'Penjualan kredit harus terhubung ke member.'])->withInput();
        }

        DB::transaction(function () use ($request, $nominalDiterima) {
            $penjualan = Penjualan::where('id_cabang', auth()->user()->id_cabang)
                ->lockForUpdate()
                ->findOrFail($request->id_penjualan);
            if ($penjualan->posted_at || $penjualan->cancelled_at) {
                app(\App\Services\LedgerService::class)->fail('Transaksi sudah final atau dibatalkan.');
            }
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

            app(\App\Services\BusinessTransactionService::class)->post('penjualan', $penjualan->id_penjualan, auth()->user()->id_cabang);

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
        app(\App\Services\BusinessTransactionService::class)->cancel('penjualan', (int) $id, auth()->user()->id_cabang);

        return response(null, 204);
    }

    public function selesai()
    {
        $setting = Setting::first();

        return view('penjualan.selesai', compact('setting'));
    }

    public function notaKecil($id = null)
    {
        return view('penjualan.nota_kecil', $this->notaData($id));
    }

    public function notaBesar($id = null)
    {
        $data = $this->notaData($id);
        $pdf = PDF::loadView('penjualan.nota_besar', $data);
        $pdf->setPaper([0, 0, 609, 440], 'portrait');
        return $pdf->stream('Nota-' . tambah_nol_didepan($data['penjualan']->id_penjualan, 10) . '.pdf');
    }

    public function cariNota(Request $request)
    {
        $request->validate(['nomor' => 'required|string|max:20']);
        $id = (int) ltrim(preg_replace('/\D/', '', $request->nomor), '0');
        $penjualan = Penjualan::with(['member', 'user'])
            ->where('id_cabang', auth()->user()->id_cabang)
            ->whereNotNull('posted_at')
            ->where('total_item', '>', 0)
            ->find($id);
        if (!$penjualan) {
            return response()->json(['message' => 'Nota ' . $request->nomor . ' tidak ditemukan di cabang ini.'], 404);
        }
        $detail = PenjualanDetail::with('produk')->where('id_penjualan', $penjualan->id_penjualan)->get();
        $notaRoute = (int) (Setting::first()->tipe_nota ?? 1) === 1 ? 'penjualan.nota_kecil' : 'penjualan.nota_besar';

        return response()->json([
            'nomor' => tambah_nol_didepan($penjualan->id_penjualan, 10),
            'tanggal' => \Carbon\Carbon::parse($penjualan->posted_at)->format('d/m/Y'),
            'jatuh_tempo' => $penjualan->sisa > 0 && $penjualan->jatuh_tempo ? $penjualan->jatuh_tempo->format('d/m/Y') : null,
            'customer' => $penjualan->member->nama ?? 'UMUM',
            'alamat' => $penjualan->member->alamat ?? '-',
            'telepon' => $penjualan->member->telepon ?? '-',
            'kasir' => $penjualan->user->name ?? '-',
            'cara_bayar' => label_cara_bayar_penjualan($penjualan),
            'status' => label_status_pembayaran($penjualan->status_pembayaran),
            'items' => $detail->map(fn ($item) => ['nama' => $item->produk->nama_produk ?? '-', 'jumlah' => format_uang($item->jumlah), 'harga' => format_uang($item->harga_jual), 'diskon' => $item->diskon, 'subtotal' => format_uang($item->subtotal)]),
            'total' => format_uang($penjualan->bayar),
            'dibayar' => format_uang($penjualan->dibayar),
            'sisa' => format_uang($penjualan->sisa),
            'retur' => $penjualan->returned_total > 0 ? format_uang($penjualan->returned_total) : null,
            'cetak' => route($notaRoute, $penjualan->id_penjualan),
            'jenis_nota' => $notaRoute === 'penjualan.nota_kecil' ? 'kecil' : 'besar',
        ]);
    }

    private function notaData($id): array
    {
        $penjualan = Penjualan::with(['member', 'user'])->find($id ?? session('id_penjualan_selesai', session('id_penjualan')));
        if (!$penjualan) {
            abort(404);
        }
        $detail = PenjualanDetail::with('produk')
            ->where('id_penjualan', $penjualan->id_penjualan)
            ->get();
        $tanggal = \Carbon\Carbon::parse($penjualan->posted_at ?? $penjualan->created_at ?? now())->toDateString();
        // Rekening tujuan transfer hanya dicetak bila masih ada sisa tagihan.
        $rekening = $penjualan->sisa > 0
            ? app(\App\Services\PaymentMethodService::class)->all((int) $penjualan->id_cabang)->where('type', 'bank')->where('active', true)->filter(fn ($bank) => filled($bank->account_number))->values()
            : collect();

        return ['setting' => Setting::first()] + compact('penjualan', 'detail', 'tanggal', 'rekening');
    }
}
