<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Kategori;
use Illuminate\Support\Facades\DB;

class KategoriController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('kategori.index');
    }

    public function importTemplate()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\ReportExport(
            ['nama_kategori'],
            [
                ['Aksesoris'],
                ['Penyimpanan'],
            ]
        ), 'template-import-kategori.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv,txt|max:5120']);
        $reader = new \App\Imports\KategoriImport();
        \Maatwebsite\Excel\Facades\Excel::import($reader, $request->file('file'));

        $branch = auth()->user()->id_cabang;
        $existingNames = Kategori::where('id_cabang', $branch)->pluck('nama_kategori')->map(fn ($n) => mb_strtolower(trim($n)))->flip();
        $rows = [];
        $errors = [];
        $seen = [];

        foreach ($reader->rows ?? [] as $index => $row) {
            $line = $index + 2;
            $nama = trim((string) ($row['nama_kategori'] ?? ''));
            if ($nama === '') {
                continue;
            }
            $rowErrors = [];
            $key = mb_strtolower($nama);
            if (mb_strlen($nama) > 100) {
                $rowErrors[] = 'nama_kategori wajib diisi (maks. 100 karakter)';
            } elseif (isset($existingNames[$key])) {
                $rowErrors[] = 'nama_kategori "' . $nama . '" sudah ada';
            } elseif (isset($seen[$key])) {
                $rowErrors[] = 'nama_kategori "' . $nama . '" ganda dengan baris ' . $seen[$key];
            }
            if ($rowErrors) {
                $errors[] = 'Baris ' . $line . ': ' . implode('; ', $rowErrors) . '.';
                continue;
            }
            $seen[$key] = $line;
            $rows[] = ['nama_kategori' => $nama];
        }

        if (! $rows && ! $errors) {
            $errors[] = 'File tidak berisi data kategori. Gunakan template dan isi mulai baris kedua.';
        }
        if (count($rows) + count($errors) > 1000) {
            $errors = ['Maksimal 1.000 kategori per file.'];
        }
        if ($errors) {
            return back()->with('import_errors', array_slice($errors, 0, 50))->with('import_error_total', count($errors));
        }

        DB::transaction(function () use ($rows, $branch) {
            foreach ($rows as $row) {
                Kategori::create([
                    'nama_kategori' => $row['nama_kategori'],
                    'id_cabang' => $branch,
                ]);
            }
        });

        return back()->with('success', count($rows) . ' kategori berhasil diimpor.');
    }

    public function export()
    {
        $branch = auth()->user()->id_cabang;
        $rows = Kategori::where('id_cabang', $branch)->get()
            ->map(fn ($kategori) => [$kategori->nama_kategori])
            ->toArray();

        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\ReportExport(
            ['nama_kategori'],
            $rows
        ), 'export-kategori-' . now()->format('Y-m-d') . '.xlsx');
    }

    public function data()
    {
        $kategori = Kategori::where('id_cabang', auth()->user()->id_cabang)
        ->orderBy('id_kategori', 'desc')
        ->get();


        return datatables()
            ->of($kategori)
            ->addIndexColumn()
            ->addColumn('aksi', function ($kategori) {
                return '
                <div class="btn-group">
                    <button onclick="editForm(`'. route('kategori.update', $kategori->id_kategori) .'`)" class="btn btn-xs btn-info btn-flat"><i class="fa fa-pencil"></i></button>
                    <button onclick="deleteData(`'. route('kategori.destroy', $kategori->id_kategori) .'`)" class="btn btn-xs btn-danger btn-flat"><i class="fa fa-trash"></i></button>
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
            'nama_kategori' => 'required|string|max:100',
        ]);

        $kategori = new Kategori();
        $kategori->nama_kategori = $request->nama_kategori;
        $kategori->id_cabang = auth()->user()->id_cabang;
        $kategori->save();

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
        $kategori = Kategori::find($id);

        return response()->json($kategori);
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
            'nama_kategori' => 'required|string|max:100',
        ]);

        $kategori = Kategori::find($id);
        $kategori->nama_kategori = $request->nama_kategori;
        $kategori->update();

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
        $kategori = Kategori::find($id);
        $kategori->delete();

        return response(null, 204);
    }
}
