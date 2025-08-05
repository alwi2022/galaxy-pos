<?php

namespace App\Http\Controllers;
// app/Http/Controllers/PenjualanDetailController.php

use App\Models\Member;
use App\Models\Penjualan;
use App\Models\PenjualanDetail;
use App\Models\Produk;
use App\Models\Setting;
use Illuminate\Http\Request;

class PenjualanDetailController extends Controller
{
    public function index()
    {
        $produk = Produk::orderBy('nama_produk')
        ->where('id_cabang', auth()->user()->id_cabang)
        ->get();
    
    $member = Member::orderBy('nama')
        ->where('id_cabang', auth()->user()->id_cabang)
        ->get();
    
        $diskon = Setting::first()->diskon ?? 0;

        // Cek apakah ada transaksi yang sedang berjalan
        if ($id_penjualan = session('id_penjualan')) {
            $penjualan = Penjualan::find($id_penjualan);
            $memberSelected = $penjualan->member ?? new Member();

            return view('penjualan_detail.index', compact('produk', 'member', 'diskon', 'id_penjualan', 'penjualan', 'memberSelected'));
        } else {
            if (auth()->user()->level == 1) {
                return redirect()->route('transaksi.baru');
            } else {
                return redirect()->route('dashboard')->with('alert', 'Tidak ada transaksi aktif. Silakan mulai transaksi baru terlebih dahulu.');
            }
        }
    }

    public function data($id)
    {
        $detail = PenjualanDetail::with('produk')
        ->where('id_penjualan', $id)
        ->whereHas('penjualan', function ($q) {
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
            $row['harga_jual']  = 'Rp. '. format_uang($item->harga_jual);
            $row['jumlah']      = '<input type="number" class="form-control input-sm quantity" data-id="'. $item->id_penjualan_detail .'" value="'. $item->jumlah .'">';
            $row['diskon']      = $item->diskon . '%';
            $row['subtotal']    = 'Rp. '. format_uang($item->subtotal);
            $row['aksi']        = '<div class="btn-group">
                                    <button onclick="deleteData(`'. route('transaksi.destroy', $item->id_penjualan_detail) .'`)" class="btn btn-xs btn-danger btn-flat"><i class="fa fa-trash"></i></button>
                                </div>';
            $data[] = $row;

            $total += $item->harga_jual * $item->jumlah - (($item->diskon * $item->jumlah) / 100 * $item->harga_jual);;
            $total_item += $item->jumlah;
        }
        $data[] = [
            'kode_produk' => '
                <div class="total hide">'. $total .'</div>
                <div class="total_item hide">'. $total_item .'</div>',
            'nama_produk' => '',
            'harga_jual'  => '',
            'jumlah'      => '',
            'diskon'      => '',
            'subtotal'    => '',
            'aksi'        => '',
        ];

        return datatables()
            ->of($data)
            ->addIndexColumn()
            ->rawColumns(['aksi', 'kode_produk', 'jumlah'])
            ->make(true);
    }

      // Method baru untuk mencari produk berdasarkan kode_produk (untuk barcode)
      public function getProdukByKode($kode_produk)
      {
          $produk = Produk::where('kode_produk', $kode_produk)
              ->when(auth()->user()->level != 1, function($q) {
                  return $q->where('id_cabang', auth()->user()->id_cabang);
              })
              ->first();
  
          if (!$produk) {
              return response()->json([
                  'success' => false,
                  'message' => 'Produk tidak ditemukan'
              ], 404);
          }
  
          return response()->json([
              'success' => true,
              'data' => [
                  'id_produk' => $produk->id_produk,
                  'kode_produk' => $produk->kode_produk,
                  'nama_produk' => $produk->nama_produk,
                  'harga_jual' => $produk->harga_jual,
                  'stok' => $produk->stok,
                  'diskon' => $produk->diskon
              ]
          ]);
      }
  
      // Method baru untuk menambah produk via barcode
      public function storeByBarcode(Request $request)
      {
            $produk = Produk::where('kode_produk', $request->kode_produk)
                ->where('id_cabang', auth()->user()->id_cabang)
                ->first();
  
          if (!$produk) {
              return response()->json([
                  'success' => false,
                  'message' => 'Produk tidak ditemukan'
              ], 400);
          }
  
          // Cek apakah produk sudah ada di transaksi
          $existing = PenjualanDetail::where('id_penjualan', $request->id_penjualan)
              ->where('id_produk', $produk->id_produk)
              ->first();
  
          if ($existing) {
              // Jika sudah ada, tambah jumlahnya
              $existing->jumlah += 1;
              $existing->subtotal = $existing->harga_jual * $existing->jumlah - (($existing->diskon * $existing->jumlah) / 100 * $existing->harga_jual);
              $existing->save();
          } else {
              // Jika belum ada, buat baru
              $detail = new PenjualanDetail();
              $detail->id_penjualan = $request->id_penjualan;
              $detail->id_produk = $produk->id_produk;
              $detail->harga_jual = $produk->harga_jual;
              $detail->jumlah = 1;
              $detail->diskon = $produk->diskon;
              $detail->subtotal = $produk->harga_jual - ($produk->diskon / 100 * $produk->harga_jual);
              $detail->save();
          }
  
          return response()->json([
              'success' => true,
              'message' => 'Produk berhasil ditambahkan'
          ]);
      }

    public function store(Request $request)
    {
        $produk = Produk::where('id_produk', $request->id_produk)
        ->where('id_cabang', auth()->user()->id_cabang)
        ->first();
    
        if (! $produk) {
            return response()->json('Data gagal disimpan', 400);
        }

        $detail = new PenjualanDetail();
        $detail->id_penjualan = $request->id_penjualan;
        $detail->id_produk = $produk->id_produk;
        $detail->harga_jual = $produk->harga_jual;
        $detail->jumlah = 1;
        $detail->diskon = $produk->diskon;
        $detail->subtotal = $produk->harga_jual - ($produk->diskon / 100 * $produk->harga_jual);;
        $detail->save();

        return back()->with('success', 'Data berhasil disimpan');
    }

    public function update(Request $request, $id)
    {
        $detail = PenjualanDetail::find($id);
        $detail->jumlah = $request->jumlah;
        $detail->subtotal = $detail->harga_jual * $request->jumlah - (($detail->diskon * $request->jumlah) / 100 * $detail->harga_jual);;
        $detail->update();
    }

    public function destroy($id)
    {
        $detail = PenjualanDetail::find($id);
        $detail->delete();

        return back()->with('success', 'Data berhasil dihapus');
    }

    public function loadForm($diskon = 0, $total = 0, $diterima = 0)
    {
        $bayar   = $total - ($diskon / 100 * $total);
        $kembali = ($diterima != 0) ? $diterima - $bayar : 0;
        $data    = [
            'totalrp' => format_uang($total),
            'bayar' => $bayar,
            'bayarrp' => format_uang($bayar),
            'terbilang' => ucwords(terbilang($bayar). ' Rupiah'),
            'kembalirp' => format_uang($kembali),
            'kembali_terbilang' => ucwords(terbilang($kembali). ' Rupiah'),
        ];

        return response()->json($data);
    }
}
