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
            $this->createProduct($request->only(['nama_produk', 'id_kategori', 'merk', 'harga_beli', 'harga_jual', 'diskon', 'stok']));
        });

        return back()->with('success', 'Data berhasil disimpan');
    }

    private function createProduct(array $data, bool $postJournal = true): Produk
    {
        $initialStock = (int) ($data['stok'] ?? 0);
        $next = (int) Produk::max('id_produk') + 1;
        while (Produk::where('kode_produk', 'P' . tambah_nol_didepan($next, 6))->exists()) {
            $next++;
        }
        $produk = Produk::create([
            'nama_produk' => $data['nama_produk'],
            'id_kategori' => $data['id_kategori'],
            'merk' => $data['merk'] ?? null,
            'harga_beli' => $data['harga_beli'],
            'harga_jual' => $data['harga_jual'],
            'diskon' => $data['diskon'] ?? 0,
            'kode_produk' => 'P' . tambah_nol_didepan($next, 6),
            'id_cabang' => auth()->user()->id_cabang,
            'stok' => 0,
        ]);
        if ($initialStock > 0) {
            $ref = 'OPEN-PRODUCT-' . $produk->id_produk;
            app(\App\Services\StockLedgerService::class)->move(auth()->user()->id_cabang, $produk->id_produk, $initialStock, $ref, 'Stok awal produk', now()->toDateTimeString(), (int) $produk->harga_beli);
            $value = $initialStock * $produk->harga_beli;
            if ($postJournal && $value > 0) {
                app(\App\Services\LedgerService::class)->post(auth()->user()->id_cabang, now()->toDateString(), $ref, 'Stok awal ' . $produk->nama_produk, [['1301', $value, 0], ['3101', 0, $value]]);
            }
        }

        return $produk;
    }

    public function importTemplate()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\ReportExport(
            ['nama_produk', 'kategori', 'merk', 'harga_beli', 'harga_jual', 'diskon', 'stok'],
            [
                ['Mouse Wireless M185', 'Aksesoris', 'Logitech', 95000, 125000, 0, 10],
                ['Flashdisk 32GB', 'Penyimpanan', 'Sandisk', 55000, 75000, 5, 0],
            ]
        ), 'template-import-produk.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv,txt|max:5120']);
        $reader = new \App\Imports\ProdukImport();
        \Maatwebsite\Excel\Facades\Excel::import($reader, $request->file('file'));

        $branch = auth()->user()->id_cabang;
        $integer = fn ($value) => is_int($value) || (is_float($value) && floor($value) === $value) || (is_string($value) && preg_match('/^\d+$/', trim($value)));
        $categories = Kategori::where('id_cabang', $branch)->get()->keyBy(fn ($k) => mb_strtolower(trim($k->nama_kategori)));
        $existingNames = Produk::pluck('nama_produk')->map(fn ($n) => mb_strtolower(trim($n)))->flip();
        $rows = [];
        $errors = [];
        $seen = [];

        foreach ($reader->rows ?? [] as $index => $row) {
            $line = $index + 2;
            $nama = trim((string) ($row['nama_produk'] ?? ''));
            $kategori = trim((string) ($row['kategori'] ?? ''));
            if ($nama === '' && $kategori === '' && blank($row['harga_beli'] ?? null) && blank($row['harga_jual'] ?? null)) {
                continue;
            }
            $rowErrors = [];
            $key = mb_strtolower($nama);
            if ($nama === '' || mb_strlen($nama) > 255) {
                $rowErrors[] = 'nama_produk wajib diisi (maks. 255 karakter)';
            } elseif (isset($existingNames[$key])) {
                $rowErrors[] = 'nama_produk "' . $nama . '" sudah ada';
            } elseif (isset($seen[$key])) {
                $rowErrors[] = 'nama_produk "' . $nama . '" ganda dengan baris ' . $seen[$key];
            }
            if ($kategori === '' || mb_strlen($kategori) > 100) {
                $rowErrors[] = 'kategori wajib diisi (maks. 100 karakter)';
            }
            $numbers = [];
            foreach (['harga_beli' => [true, 2000000000], 'harga_jual' => [true, 2000000000], 'diskon' => [false, 100], 'stok' => [false, 1000000]] as $field => [$required, $max]) {
                $value = $row[$field] ?? null;
                if (blank($value)) {
                    if ($required) {
                        $rowErrors[] = $field . ' wajib diisi';
                    }
                    $numbers[$field] = 0;
                } elseif (! $integer($value) || (int) $value > $max) {
                    $rowErrors[] = $field . ' harus angka bulat 0 sampai ' . number_format($max, 0, ',', '.') . ' tanpa titik/koma';
                } else {
                    $numbers[$field] = (int) $value;
                }
            }
            if ($rowErrors) {
                $errors[] = 'Baris ' . $line . ': ' . implode('; ', $rowErrors) . '.';
                continue;
            }
            $seen[$key] = $line;
            $rows[] = ['nama_produk' => $nama, 'kategori' => $kategori, 'merk' => trim((string) ($row['merk'] ?? '')) ?: null] + $numbers;
        }

        if (! $rows && ! $errors) {
            $errors[] = 'File tidak berisi data produk. Gunakan template dan isi mulai baris kedua.';
        }
        if (count($rows) + count($errors) > 1000) {
            $errors = ['Maksimal 1.000 produk per file.'];
        }
        if ($errors) {
            return back()->with('import_errors', array_slice($errors, 0, 50))->with('import_error_total', count($errors));
        }

        $newCategories = 0;
        \Illuminate\Support\Facades\DB::transaction(function () use ($rows, $branch, &$categories, &$newCategories) {
            foreach ($rows as $row) {
                $key = mb_strtolower($row['kategori']);
                if (! isset($categories[$key])) {
                    $categories[$key] = Kategori::create(['nama_kategori' => $row['kategori'], 'id_cabang' => $branch]);
                    $newCategories++;
                }
                $this->createProduct(['id_kategori' => $categories[$key]->id_kategori] + $row, false);
            }
            $value = array_sum(array_map(fn ($row) => $row['stok'] * $row['harga_beli'], $rows));
            if ($value > 0) {
                app(\App\Services\LedgerService::class)->post($branch, now()->toDateString(), 'OPEN-IMPORT-' . \Illuminate\Support\Str::uuid(), 'Stok awal import ' . count($rows) . ' produk', [['1301', $value, 0], ['3101', 0, $value]]);
            }
        });

        return back()->with('success', count($rows) . ' produk berhasil diimpor' . ($newCategories ? ', ' . $newCategories . ' kategori baru dibuat' : '') . '.');
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
