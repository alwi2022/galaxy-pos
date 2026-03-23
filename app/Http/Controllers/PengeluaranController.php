<?php

namespace App\Http\Controllers;
// app/Http/Controllers/PengeluaranController.php

use Illuminate\Http\Request;
use App\Models\Pengeluaran;

class PengeluaranController extends Controller
{
    public function index()
    {
        return view('pengeluaran.index');
    }

    public function data()
    {
        $pengeluaran = Pengeluaran::where('id_cabang', auth()->user()->id_cabang)
            ->orderByDesc('tanggal_pengeluaran')
            ->orderByDesc('id_pengeluaran')
            ->get();

        return datatables()
            ->of($pengeluaran)
            ->addIndexColumn()
            ->addColumn('tanggal_pengeluaran', function ($pengeluaran) {
                return tanggal_indonesia($pengeluaran->tanggal_pengeluaran, false);
            })
            ->addColumn('kategori_pengeluaran', function ($pengeluaran) {
                return label_kategori_pengeluaran($pengeluaran->kategori_pengeluaran);
            })
            ->addColumn('nominal', function ($pengeluaran) {
                return format_uang($pengeluaran->nominal);
            })
            ->addColumn('metode_pembayaran', function ($pengeluaran) {
                return label_metode_pembayaran($pengeluaran->metode_pembayaran);
            })
            ->addColumn('aksi', function ($pengeluaran) {
                return '
                <div class="btn-group">
                    <button type="button" onclick="editForm(`'. route('pengeluaran.update', $pengeluaran->id_pengeluaran) .'`)" class="btn btn-xs btn-info btn-flat"><i class="fa fa-pencil"></i></button>
                    <button type="button" onclick="deleteData(`'. route('pengeluaran.destroy', $pengeluaran->id_pengeluaran) .'`)" class="btn btn-xs btn-danger btn-flat"><i class="fa fa-trash"></i></button>
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
        $payload = $this->validatePayload($request);
        $payload['id_cabang'] = auth()->user()->id_cabang;
        Pengeluaran::create($payload);

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
        $pengeluaran = Pengeluaran::where('id_pengeluaran', $id)
            ->where('id_cabang', auth()->user()->id_cabang)
            ->firstOrFail();

        return response()->json([
            'id_pengeluaran' => $pengeluaran->id_pengeluaran,
            'tanggal_pengeluaran' => optional($pengeluaran->tanggal_pengeluaran)->format('Y-m-d'),
            'kategori_pengeluaran' => $pengeluaran->kategori_pengeluaran,
            'deskripsi' => $pengeluaran->deskripsi,
            'metode_pembayaran' => $pengeluaran->metode_pembayaran,
            'nominal' => $pengeluaran->nominal,
        ]);
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
        $payload = $this->validatePayload($request);
        $pengeluaran = Pengeluaran::where('id_pengeluaran', $id)
            ->where('id_cabang', auth()->user()->id_cabang)
            ->firstOrFail();

        $pengeluaran->update($payload);

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
        $pengeluaran = Pengeluaran::where('id_pengeluaran', $id)
        ->where('id_cabang', auth()->user()->id_cabang)
        ->firstOrFail();
    
        $pengeluaran->delete();

        return response(null, 204);
    }

    protected function validatePayload(Request $request): array
    {
        return $request->validate([
            'tanggal_pengeluaran' => 'required|date',
            'kategori_pengeluaran' => 'required|in:' . implode(',', array_keys(daftar_kategori_pengeluaran())),
            'deskripsi' => 'required|string|max:500',
            'nominal' => 'required|integer|min:1',
            'metode_pembayaran' => 'required|in:tunai,transfer_bank,qris',
        ]);
    }
}
