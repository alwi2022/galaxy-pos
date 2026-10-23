<?php

namespace App\Http\Controllers;

use App\Models\Kategori;
use Illuminate\Http\Request;
use App\Models\Produk;
use PDF;
use DNS1D;
use TCPDF;



class ProdukController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $kategori = Kategori::where('id_cabang', auth()->user()->id_cabang)
        ->pluck('nama_kategori', 'id_kategori');


        return view('produk.index', compact('kategori'));
    }

    public function data()
    {
        $produk = Produk::leftJoin('kategori', 'kategori.id_kategori', 'produk.id_kategori')
            ->select('produk.*', 'nama_kategori')
            ->where('produk.id_cabang', auth()->user()->id_cabang)

            ->get();

        return datatables()
            ->of($produk)
            ->addIndexColumn()
            ->addColumn('select_all', function ($produk) {
                return '
                    <input type="checkbox" name="id_produk[]" value="'. $produk->id_produk .'">
                ';
            })
            ->addColumn('kode_produk', function ($produk) {
                return '<span class="label label-success">'. $produk->kode_produk .'</span>';
            })
            ->addColumn('harga_beli', function ($produk) {
                return format_uang($produk->harga_beli);
            })
            ->addColumn('harga_jual', function ($produk) {
                return format_uang($produk->harga_jual);
            })
            ->addColumn('stok', function ($produk) {
                return format_uang($produk->stok);
            })
            ->addColumn('aksi', function ($produk) {
                return '
                <div class="btn-group">
                    <button type="button" onclick="editForm(`'. route('produk.update', $produk->id_produk) .'`)" class="btn btn-xs btn-info btn-flat"><i class="fa fa-pencil"></i></button>
                    <button type="button" onclick="deleteData(`'. route('produk.destroy', $produk->id_produk) .'`)" class="btn btn-xs btn-danger btn-flat"><i class="fa fa-trash"></i></button>
                </div>
                ';
            })
            ->rawColumns(['aksi', 'kode_produk', 'select_all'])
            ->make(true);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate(['nama_produk' => 'required|string|max:255|unique:produk,nama_produk', 'id_kategori' => 'required|integer', 'harga_beli' => 'required|integer|min:0|max:2000000000', 'harga_jual' => 'required|integer|min:0|max:2000000000', 'stok' => 'required|integer|min:0|max:1000000', 'diskon' => 'nullable|integer|min:0|max:100']);
        abort_unless(Kategori::where('id_cabang', auth()->user()->id_cabang)->where('id_kategori', $request->id_kategori)->exists(), 422);
        \Illuminate\Support\Facades\DB::transaction(function () use ($request) {
        $initialStock = (int) $request->stok;
        $next = (int) Produk::max('id_produk') + 1;
        while (Produk::where('kode_produk', 'P' . tambah_nol_didepan($next, 6))->exists()) {
            $next++;
        }
        $request['kode_produk'] = 'P' . tambah_nol_didepan($next, 6);
        $request['id_cabang'] = auth()->user()->id_cabang;
        $produk = Produk::create($request->only(['nama_produk', 'id_kategori', 'merk', 'harga_beli', 'harga_jual', 'diskon', 'kode_produk', 'id_cabang']) + ['stok' => 0]);
        if ($initialStock > 0) {
            $ref = 'OPEN-PRODUCT-' . $produk->id_produk;
            app(\App\Services\StockLedgerService::class)->move(auth()->user()->id_cabang, $produk->id_produk, $initialStock, $ref, 'Stok awal produk', now()->toDateTimeString(), (int) $produk->harga_beli);
            $value = $initialStock * $produk->harga_beli;
            if ($value > 0) {
                app(\App\Services\LedgerService::class)->post(auth()->user()->id_cabang, now()->toDateString(), $ref, 'Stok awal ' . $produk->nama_produk, [['1301', $value, 0], ['3101', 0, $value]]);
            }
        }
        });

        return back()->with('success', 'Data berhasil disimpan');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $produk = Produk::find($id);

        return response()->json($produk);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $request->validate(['nama_produk' => 'required|string|max:255|unique:produk,nama_produk,' . (int) $id . ',id_produk', 'id_kategori' => 'required|integer', 'harga_beli' => 'required|integer|min:0|max:2000000000', 'harga_jual' => 'required|integer|min:0|max:2000000000', 'stok' => 'required|integer|min:0|max:1000000', 'diskon' => 'nullable|integer|min:0|max:100']);
        \Illuminate\Support\Facades\DB::transaction(function () use ($request, $id) {
            $produk = Produk::where('id_cabang', auth()->user()->id_cabang)->lockForUpdate()->findOrFail($id);
            abort_unless(Kategori::where('id_cabang', auth()->user()->id_cabang)->where('id_kategori', $request->id_kategori)->exists(), 422);
            if ((int) $request->stok !== (int) $produk->stok) {
                app(\App\Services\StockLedgerService::class)->adjustment(auth()->user()->id_cabang, $produk->id_produk, (int) $request->stok, 'opname', 'Penyesuaian melalui master produk', now()->toDateTimeString());
            }
            if ((int) $request->harga_beli !== (int) $produk->harga_beli && $produk->stok > 0) {
                app(\App\Services\LedgerService::class)->fail('Harga modal stok aktif dihitung dari pembelian. Ubah melalui transaksi pembelian.');
            }
            $produk->update($request->only(['nama_produk', 'id_kategori', 'merk', 'harga_jual', 'diskon']));
            if ((int) $produk->stok === 0) {
                $produk->update(['harga_beli' => (int) $request->harga_beli]);
            }
        });

        return back()->with('success', 'Data berhasil disimpan');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $produk = Produk::where('id_cabang', auth()->user()->id_cabang)->findOrFail($id);
        if ($produk->stok > 0 || \Illuminate\Support\Facades\DB::table('stock_movements')->where('id_produk', $id)->exists()) {
            app(\App\Services\LedgerService::class)->fail('Produk yang memiliki stok atau riwayat transaksi tidak dapat dihapus.');
        }
        $produk->delete();

        return back()->with('success', 'Data berhasil dihapus');
    }

    public function deleteSelected(Request $request)
    {
        foreach ($request->id_produk as $id) {
            $this->destroy($id);
        }

        return back()->with('success', 'Data berhasil dihapus');
    }

    public function cetakBarcode(Request $request)
    {
        $dataproduk = array();
        foreach ($request->id_produk as $id) {
            $produk = Produk::find($id);
            $dataproduk[] = $produk;
        }

        // Create new PDF document
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // Set document information
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('Your App');
        $pdf->SetTitle('Barcode Products');

        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // Set margins
        $pdf->SetMargins(10, 10, 10);

        // Add a page
        $pdf->AddPage();

        // Set font
        $pdf->SetFont('helvetica', '', 10);

        $x = 10;
        $y = 10;
        $counter = 0;

        foreach ($dataproduk as $produk) {
            // Product name
            $pdf->SetXY($x, $y);
            $pdf->Cell(60, 10, $produk->nama_produk, 1, 0, 'C');
            
            // Price
            $pdf->SetXY($x, $y + 10);
            $pdf->Cell(60, 10, 'Rp. ' . format_uang($produk->harga_jual), 1, 0, 'C');
            
            // Barcode
            $pdf->SetXY($x, $y + 20);
            $style = array(
                'position' => '',
                'align' => 'C',
                'stretch' => false,
                'fitwidth' => true,
                'cellfitscale' => false,
                'border' => true,
                'hpadding' => 'auto',
                'vpadding' => 'auto',
                'fgcolor' => array(0,0,0),
                'bgcolor' => false,
                'text' => true,
                'font' => 'helvetica',
                'fontsize' => 8,
                'stretchtext' => 4
            );
            
            $pdf->write1DBarcode($produk->kode_produk, 'C39', '', '', 60, 15, 0.4, $style, 'N');
            
            $counter++;
            if ($counter % 3 == 0) {
                $x = 10;
                $y += 50;
            } else {
                $x += 65;
            }
            
            // New page if needed
            if ($y > 250) {
                $pdf->AddPage();
                $x = 10;
                $y = 10;
            }
        }

        return response($pdf->Output('barcode.pdf', 'S'))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="barcode.pdf"');
    }

}
