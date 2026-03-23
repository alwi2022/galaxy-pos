<?php

namespace App\Http\Controllers;

use App\Models\PendapatanLain;
use Illuminate\Http\Request;

class PendapatanLainController extends Controller
{
    public function index()
    {
        return view('pendapatan_lain.index');
    }

    public function data()
    {
        $pendapatan = PendapatanLain::where('id_cabang', auth()->user()->id_cabang)
            ->orderByDesc('tanggal_pendapatan')
            ->orderByDesc('id_pendapatan_lain')
            ->get();

        return datatables()
            ->of($pendapatan)
            ->addIndexColumn()
            ->addColumn('tanggal_pendapatan', function ($item) {
                return tanggal_indonesia($item->tanggal_pendapatan, false);
            })
            ->addColumn('kategori_pendapatan', function ($item) {
                return label_kategori_pendapatan_lain($item->kategori_pendapatan);
            })
            ->addColumn('nominal', function ($item) {
                return format_uang($item->nominal);
            })
            ->addColumn('metode_pembayaran', function ($item) {
                return label_metode_pembayaran($item->metode_pembayaran);
            })
            ->addColumn('aksi', function ($item) {
                return '
                <div class="btn-group">
                    <button type="button" onclick="editForm(`'. route('pendapatan-lain.update', $item->id_pendapatan_lain) .'`)" class="btn btn-xs btn-info btn-flat"><i class="fa fa-pencil"></i></button>
                    <button type="button" onclick="deleteData(`'. route('pendapatan-lain.destroy', $item->id_pendapatan_lain) .'`)" class="btn btn-xs btn-danger btn-flat"><i class="fa fa-trash"></i></button>
                </div>
                ';
            })
            ->rawColumns(['aksi'])
            ->make(true);
    }

    public function store(Request $request)
    {
        $payload = $this->validatePayload($request);
        $payload['id_user'] = auth()->id();
        $payload['id_cabang'] = auth()->user()->id_cabang;

        PendapatanLain::create($payload);

        return response()->json('Data berhasil disimpan', 200);
    }

    public function show($id)
    {
        $item = PendapatanLain::where('id_cabang', auth()->user()->id_cabang)
            ->findOrFail($id);

        return response()->json([
            'id_pendapatan_lain' => $item->id_pendapatan_lain,
            'tanggal_pendapatan' => optional($item->tanggal_pendapatan)->format('Y-m-d'),
            'kategori_pendapatan' => $item->kategori_pendapatan,
            'deskripsi' => $item->deskripsi,
            'metode_pembayaran' => $item->metode_pembayaran,
            'nominal' => $item->nominal,
        ]);
    }

    public function update(Request $request, $id)
    {
        $payload = $this->validatePayload($request);

        PendapatanLain::where('id_cabang', auth()->user()->id_cabang)
            ->findOrFail($id)
            ->update($payload);

        return response()->json('Data berhasil disimpan', 200);
    }

    public function destroy($id)
    {
        PendapatanLain::where('id_cabang', auth()->user()->id_cabang)
            ->findOrFail($id)
            ->delete();

        return response(null, 204);
    }

    protected function validatePayload(Request $request): array
    {
        return $request->validate([
            'tanggal_pendapatan' => 'required|date',
            'kategori_pendapatan' => 'required|in:' . implode(',', array_keys(daftar_kategori_pendapatan_lain())),
            'deskripsi' => 'required|string|max:500',
            'nominal' => 'required|integer|min:1',
            'metode_pembayaran' => 'required|in:tunai,transfer_bank,qris',
        ]);
    }
}
