<?php

namespace App\Http\Controllers;
// app/Http/Controllers/SupplierController.php

use App\Models\Cabang;
use Illuminate\Http\Request;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

class SupplierController extends Controller
{
    public function index()
    {
        return view('supplier.index');
    }

    public function importTemplate()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\ReportExport(
            ['nama', 'telepon', 'alamat'],
            [
                ['Sumber Jaya Komputer', '0251111222', 'Jl. Merdeka 10'],
                ['Mitra Sparepart', '0251333444', ''],
            ]
        ), 'template-import-supplier.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv,txt|max:5120']);
        $reader = new \App\Imports\SupplierImport();
        \Maatwebsite\Excel\Facades\Excel::import($reader, $request->file('file'));

        $branch = auth()->user()->id_cabang;
        $existingNames = Supplier::where('id_cabang', $branch)->pluck('nama')->map(fn ($n) => mb_strtolower(trim($n)))->flip();
        $rows = [];
        $errors = [];
        $seen = [];

        foreach ($reader->rows ?? [] as $index => $row) {
            $line = $index + 2;
            $nama = trim((string) ($row['nama'] ?? ''));
            $telepon = trim((string) ($row['telepon'] ?? ''));
            $alamat = trim((string) ($row['alamat'] ?? ''));
            if ($nama === '' && $telepon === '' && $alamat === '') {
                continue;
            }
            $rowErrors = [];
            $key = mb_strtolower($nama);
            if ($nama === '' || mb_strlen($nama) > 255) {
                $rowErrors[] = 'nama wajib diisi (maks. 255 karakter)';
            } elseif (isset($existingNames[$key])) {
                $rowErrors[] = 'nama "' . $nama . '" sudah ada';
            } elseif (isset($seen[$key])) {
                $rowErrors[] = 'nama "' . $nama . '" ganda dengan baris ' . $seen[$key];
            }
            if ($telepon === '' || mb_strlen($telepon) > 255) {
                $rowErrors[] = 'telepon wajib diisi (maks. 255 karakter)';
            }
            if (mb_strlen($alamat) > 255) {
                $rowErrors[] = 'alamat maks. 255 karakter';
            }
            if ($rowErrors) {
                $errors[] = 'Baris ' . $line . ': ' . implode('; ', $rowErrors) . '.';
                continue;
            }
            $seen[$key] = $line;
            $rows[] = ['nama' => $nama, 'telepon' => $telepon, 'alamat' => $alamat ?: null];
        }

        if (! $rows && ! $errors) {
            $errors[] = 'File tidak berisi data supplier. Gunakan template dan isi mulai baris kedua.';
        }
        if (count($rows) + count($errors) > 1000) {
            $errors = ['Maksimal 1.000 supplier per file.'];
        }
        if ($errors) {
            return back()->with('import_errors', array_slice($errors, 0, 50))->with('import_error_total', count($errors));
        }

        DB::transaction(function () use ($rows, $branch) {
            foreach ($rows as $row) {
                Supplier::create([
                    'nama' => $row['nama'],
                    'telepon' => $row['telepon'],
                    'alamat' => $row['alamat'],
                    'id_cabang' => $branch,
                ]);
            }
        });

        return back()->with('success', count($rows) . ' supplier berhasil diimpor.');
    }

    public function export()
    {
        $branch = auth()->user()->id_cabang;
        $rows = Supplier::where('id_cabang', $branch)->get()
            ->map(fn ($supplier) => [$supplier->nama, $supplier->telepon, $supplier->alamat])
            ->toArray();

        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\ReportExport(
            ['nama', 'telepon', 'alamat'],
            $rows
        ), 'export-supplier-' . now()->format('Y-m-d') . '.xlsx');
    }

    public function data()
    {
        $supplier = Supplier::where('id_cabang', auth()->user()->id_cabang)
        ->orderBy('id_supplier', 'desc')
        ->get();

        return datatables()
            ->of($supplier)
            ->addIndexColumn()
            ->addColumn('aksi', function ($supplier) {
                return '
                <div class="btn-group">
                    <button type="button" onclick="editForm(`'. route('supplier.update', $supplier->id_supplier) .'`)" class="btn btn-xs btn-info btn-flat"><i class="fa fa-pencil"></i></button>
                    <button type="button" onclick="deleteData(`'. route('supplier.destroy', $supplier->id_supplier) .'`)" class="btn btn-xs btn-danger btn-flat"><i class="fa fa-trash"></i></button>
                </div>
                ';
            })
            ->rawColumns(['aksi'])
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
        $request->validate([
            'nama' => 'required|string|max:255',
            'telepon' => 'required|string|max:255',
            'alamat' => 'nullable|string|max:255',
        ]);

        $request['id_cabang'] = auth()->user()->id_cabang;
        $supplier = Supplier::create($request->only(['nama', 'telepon', 'alamat', 'id_cabang']));
        

        return response()->json('Data berhasil disimpan', 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $supplier = Supplier::find($id);

        return response()->json($supplier);
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
        $request->validate([
            'nama' => 'required|string|max:255',
            'telepon' => 'required|string|max:255',
            'alamat' => 'nullable|string|max:255',
        ]);

        $supplier = Supplier::where('id_supplier', $id)
        ->where('id_cabang', auth()->user()->id_cabang)
        ->firstOrFail();
    
    $supplier->update($request->only(['nama', 'telepon', 'alamat']));
    

        return response()->json('Data berhasil disimpan', 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $supplier = Supplier::where('id_supplier', $id)
        ->where('id_cabang', auth()->user()->id_cabang)
        ->firstOrFail();
    
    $supplier->delete();

        return response(null, 204);
    }
}
