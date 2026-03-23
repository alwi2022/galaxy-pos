<?php

namespace App\Http\Controllers;


use App\Models\Pembelian;
use App\Models\PembelianDetail;
use App\Models\Produk;
use App\Models\Setting;
use App\Models\Supplier;
use App\Support\TransactionCalculator;
use Illuminate\Http\Request;

class PembelianDetailController extends Controller
{
    protected TransactionCalculator $calculator;

    public function __construct(TransactionCalculator $calculator)
    {
        $this->calculator = $calculator;
    }

    public function index()
    {
        $id_pembelian = session('id_pembelian');
        $pembelian = Pembelian::find($id_pembelian);
        $produk = Produk::orderBy('nama_produk')
        ->where('id_cabang', auth()->user()->id_cabang)
        ->get();
        $supplier = Supplier::where('id_cabang', auth()->user()->id_cabang)
            ->find(session('id_supplier'));
        $diskon = $pembelian->diskon ?? 0;
        $ppnPersen = Setting::resolvePpnPersen($pembelian->ppn_persen ?? null);

        if (! $supplier) {
            abort(404);
        }

        return view('pembelian_detail.index', compact('id_pembelian', 'produk', 'supplier', 'diskon', 'pembelian', 'ppnPersen'));
    }

    public function data($id)
    {
        $detail = PembelianDetail::with('produk')
        ->where('id_pembelian', $id)
        ->whereHas('pembelian', function ($q) {
            $q->where('id_cabang', auth()->user()->id_cabang);
        })
        ->get();
    
        $data = array();
        $total = 0;
        $total_item = 0;

        foreach ($detail as $item) {
            $row = array();
            $row['kode_produk'] = '<span class="label label-success">'. $item->produk['kode_produk'] .'</span';
            $row['nama_produk'] = $item->produk['nama_produk'];
            $row['harga_beli']  = 'Rp. '. format_uang($item->harga_beli);
            $row['jumlah']      = '<input type="number" class="form-control input-sm quantity" data-id="'. $item->id_pembelian_detail .'" value="'. $item->jumlah .'">';
            $row['subtotal']    = 'Rp. '. format_uang($item->subtotal);
            $row['aksi']        = '<div class="btn-group">
                                    <button onclick="deleteData(`'. route('pembelian_detail.destroy', $item->id_pembelian_detail) .'`)" class="btn btn-xs btn-danger btn-flat"><i class="fa fa-trash"></i></button>
                                </div>';
            $data[] = $row;

            $total += $item->harga_beli * $item->jumlah;
            $total_item += $item->jumlah;
        }
        $data[] = [
            'kode_produk' => '
                <div class="total hide">'. $total .'</div>
                <div class="total_item hide">'. $total_item .'</div>',
            'nama_produk' => '',
            'harga_beli'  => '',
            'jumlah'      => '',
            'subtotal'    => '',
            'aksi'        => '',
        ];

        return datatables()
            ->of($data)
            ->addIndexColumn()
            ->rawColumns(['aksi', 'kode_produk', 'jumlah'])
            ->make(true);
    }

    public function store(Request $request)
    {
        $produk = Produk::where('id_produk', $request->id_produk)
            ->where('id_cabang', auth()->user()->id_cabang)
            ->first();

        if (! $produk) {
            return response()->json('Data gagal disimpan', 400);
        }

        $detail = new PembelianDetail();
        $detail->id_pembelian = $request->id_pembelian;
        $detail->id_produk = $produk->id_produk;
        $detail->harga_beli = $produk->harga_beli;
        $detail->jumlah = 1;
        $detail->subtotal = $produk->harga_beli;
        $detail->save();

        return back()->with('success', 'Data berhasil disimpan');
    }

    public function update(Request $request, $id)
    {
        $detail = PembelianDetail::find($id);
        $detail->jumlah = $request->jumlah;
        $detail->subtotal = $detail->harga_beli * $request->jumlah;
        $detail->update();
    }

    public function destroy($id)
    {
        $detail = PembelianDetail::find($id);
        $detail->delete();

        return response(null, 204);
    }

    public function loadForm($diskon, $total, $dibayar = 0)
    {
        $ppnPersen = request()->has('ppn_persen')
            ? (float) request('ppn_persen')
            : Setting::defaultPpnPersen();

        $summary = $this->calculator->calculateFromSubtotal(
            (int) $total,
            (float) $diskon,
            $ppnPersen,
            max((int) $dibayar, 0)
        );

        $data  = [
            'totalrp' => format_uang($total),
            'diskonrp' => format_uang($summary['diskon_nominal']),
            'ppn_persen' => $summary['ppn_persen'],
            'dpp' => $summary['dpp'],
            'dpprp' => format_uang($summary['dpp']),
            'ppn' => $summary['ppn_nominal'],
            'ppnrp' => format_uang($summary['ppn_nominal']),
            'bayar' => $summary['grand_total'],
            'bayarrp' => format_uang($summary['grand_total']),
            'dibayar' => $summary['dibayar'],
            'dibayarrp' => format_uang($summary['dibayar']),
            'sisa' => $summary['sisa'],
            'sisarp' => format_uang($summary['sisa']),
            'terbilang' => ucwords(terbilang($summary['grand_total']). ' Rupiah')
        ];

        return response()->json($data);
    }
}
