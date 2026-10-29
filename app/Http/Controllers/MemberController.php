<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class MemberController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('member.index');
    }

    public function importTemplate()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\ReportExport(
            ['kode_member', 'nama', 'telepon', 'alamat'],
            [
                ['', 'Budi Santoso', '08123456789', 'Jl. Mawar 1'],
                ['', 'Siti Aminah', '08987654321', ''],
            ]
        ), 'template-import-member.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv,txt|max:5120']);
        $reader = new \App\Imports\MemberImport();
        \Maatwebsite\Excel\Facades\Excel::import($reader, $request->file('file'));

        $branch = auth()->user()->id_cabang;
        $rows = [];
        $errors = [];
        $seen = [];

        foreach ($reader->rows ?? [] as $index => $row) {
            $line = $index + 2;
            $kode = trim((string) ($row['kode_member'] ?? ''));
            $nama = trim((string) ($row['nama'] ?? ''));
            $telepon = trim((string) ($row['telepon'] ?? ''));
            $alamat = trim((string) ($row['alamat'] ?? ''));
            if ($kode === '' && $nama === '' && $telepon === '' && $alamat === '') {
                continue;
            }
            $rowErrors = [];
            if ($kode !== '') {
                if (Member::where('kode_member', $kode)->exists()) {
                    $rowErrors[] = 'kode_member "' . $kode . '" sudah ada';
                } elseif (isset($seen[$kode])) {
                    $rowErrors[] = 'kode_member "' . $kode . '" ganda dengan baris ' . $seen[$kode];
                }
            }
            if ($nama === '' || mb_strlen($nama) > 255) {
                $rowErrors[] = 'nama wajib diisi (maks. 255 karakter)';
            }
            if ($telepon === '' || mb_strlen($telepon) > 20) {
                $rowErrors[] = 'telepon wajib diisi (maks. 20 karakter)';
            }
            if (mb_strlen($alamat) > 255) {
                $rowErrors[] = 'alamat maks. 255 karakter';
            }
            if ($rowErrors) {
                $errors[] = 'Baris ' . $line . ': ' . implode('; ', $rowErrors) . '.';
                continue;
            }
            if ($kode !== '') {
                $seen[$kode] = $line;
            }
            $rows[] = ['kode_member' => $kode ?: null, 'nama' => $nama, 'telepon' => $telepon, 'alamat' => $alamat ?: null];
        }

        if (! $rows && ! $errors) {
            $errors[] = 'File tidak berisi data member. Gunakan template dan isi mulai baris kedua.';
        }
        if (count($rows) + count($errors) > 1000) {
            $errors = ['Maksimal 1.000 member per file.'];
        }
        if ($errors) {
            return back()->with('import_errors', array_slice($errors, 0, 50))->with('import_error_total', count($errors));
        }

        DB::transaction(function () use ($rows, $branch) {
            foreach ($rows as $row) {
                $kode = $row['kode_member'];
                if (! $kode) {
                    $next = (int) Member::max('kode_member') + 1;
                    $kode = tambah_nol_didepan($next, 5);
                    while (Member::where('kode_member', $kode)->exists()) {
                        $next++;
                        $kode = tambah_nol_didepan($next, 5);
                    }
                }
                Member::create([
                    'kode_member' => $kode,
                    'nama' => $row['nama'],
                    'telepon' => $row['telepon'],
                    'alamat' => $row['alamat'],
                    'id_cabang' => $branch,
                ]);
            }
        });

        return back()->with('success', count($rows) . ' member berhasil diimpor.');
    }

    public function export()
    {
        $branch = auth()->user()->id_cabang;
        $rows = Member::where('id_cabang', $branch)->orderBy('kode_member')->get()
            ->map(fn ($member) => [$member->kode_member, $member->nama, $member->telepon, $member->alamat])
            ->toArray();

        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\ReportExport(
            ['kode_member', 'nama', 'telepon', 'alamat'],
            $rows
        ), 'export-member-' . now()->format('Y-m-d') . '.xlsx');
    }

    public function data()
    {
        $member = Member::where('id_cabang', auth()->user()->id_cabang)
            ->orderBy('kode_member')
            ->get();

        return datatables()
            ->of($member)
            ->addIndexColumn()
            ->addColumn('select_all', function ($produk) {
                return '
                    <input type="checkbox" name="id_member[]" value="' . $produk->id_member . '">
                ';
            })
            ->addColumn('kode_member', function ($member) {
                return '<span class="label label-success">' . $member->kode_member . '<span>';
            })
            ->addColumn('aksi', function ($member) {
                return '
                <div class="btn-group">
                    <button type="button" onclick="editForm(`' . route('member.update', $member->id_member) . '`)" class="btn btn-xs btn-info btn-flat"><i class="fa fa-pencil"></i></button>
                    <button type="button" onclick="deleteData(`' . route('member.destroy', $member->id_member) . '`)" class="btn btn-xs btn-danger btn-flat"><i class="fa fa-trash"></i></button>
                </div>
                ';
            })
            ->rawColumns(['aksi', 'select_all', 'kode_member'])
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
            'telepon' => 'required|string|max:20',
            'alamat' => 'nullable|string|max:255',
        ]);

        $member = Member::latest()->first() ?? new Member();
        $kode_member = (int) $member->kode_member + 1;

        $member = new Member();
        $member->kode_member = tambah_nol_didepan($kode_member, 5);
        $member->nama = $request->nama;
        $member->telepon = $request->telepon;
        $member->alamat = $request->alamat;
        $member->id_cabang = auth()->user()->id_cabang;
        $member->save();

        return response()->json('Data berhasil disimpan', 200);
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $member = Member::find($id);

        return response()->json($member);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
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
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'nama' => 'required|string|max:255',
            'telepon' => 'required|string|max:20',
            'alamat' => 'nullable|string|max:255',
        ]);

        $member = Member::where('id_member', $id)
            ->where('id_cabang', auth()->user()->id_cabang)
            ->firstOrFail();

        $member->update($request->only(['nama', 'telepon', 'alamat']));

        return response()->json('Data berhasil disimpan', 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $member = Member::where('id_member', $id)
            ->where('id_cabang', auth()->user()->id_cabang)
            ->firstOrFail();

        $member->delete();

        return response(null, 204);
    }


    public function cetakMember(Request $request)
    {
        if (!$request->filled('id_member')) {
            return redirect()->back()->with('error', 'Tidak ada member yang dipilih');
        }
    
        $datamember = Member::whereIn('id_member', $request->id_member)
            ->where('id_cabang', auth()->user()->id_cabang)
            ->get();
    
        $setting = Setting::first();
    
        return view('member.cetak', compact('datamember', 'setting'));
}


}
