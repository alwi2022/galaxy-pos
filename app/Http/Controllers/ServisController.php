<?php

namespace App\Http\Controllers;
// app/Http/Controllers/ServisController.php

use App\Models\Cabang;
use App\Models\Member;
use App\Models\Servis;
use App\Models\ServisLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Auth;

class ServisController extends Controller
{
    public function index()
    {
        return view('servis.index');
    }

    public function data()
    {
        $servis = Servis::with('member', 'cabang')
            ->where('id_cabang', Auth::user()->id_cabang)
            ->orderByDesc('created_at')
            ->get();

        return datatables()
            ->of($servis)
            ->addIndexColumn()
            ->addColumn('kode_servis', fn($s) => '<span class="label label-success">' . $s->kode_servis . '</span>')
            ->addColumn('status', fn($s) => '<span class="label label-info">' . $s->status . '</span>')
            ->addColumn('aksi', function ($s) {
                $btnCetak = '<a href="' . route('servis.nota_kecil', $s->id_servis) . '" target="_blank" class="btn btn-xs btn-info btn-flat"><i class="fa fa-print"></i></a>';
                $btnEdit = '<button onclick="editForm(`' . route('servis.edit', $s->id_servis) . '`)" class="btn btn-xs btn-primary btn-flat"><i class="fa fa-edit"></i></button>';
                $btnHapus = '<button onclick="deleteData(`' . route('servis.destroy', $s->id_servis) . '`)" class="btn btn-xs btn-danger btn-flat"><i class="fa fa-trash"></i></button>';
                return $btnCetak . ' ' . $btnEdit . ' ' . $btnHapus;
            })
            ->rawColumns(['kode_servis', 'status', 'aksi'])
            ->make(true);
    }

    public function store(Request $request)
    {
        $kode = 'SRV-' . strtoupper(Str::random(6));

        $servis = new Servis();
        $servis->fill($request->all());

        if (Auth::user()->level == 3) {
            $servis->teknisi = Auth::user()->name;
        }
        
        $servis->kode_servis = $kode;
        $servis->id_cabang = Auth::user()->id_cabang;
        $servis->id_user = Auth::id();

        if ($request->status == 'selesai') {
            $servis->tanggal_selesai = now();
        }
    
        $servis->save();

        ServisLog::create([
            'id_servis' => $servis->id_servis,
            'id_user' => auth()->id(),
            'status' => $servis->status,
        ]);

        if ($servis->teknisi && $servis->telepon) {
            $pesan = urlencode("Halo *{$servis->teknisi}*,\nAda servis baru masuk:\nKode: {$servis->kode_servis}\nBarang: {$servis->tipe_barang}\nDari: {$servis->nama_pelanggan}");
            $link = 'https://wa.me/62' . ltrim($servis->telepon, '0') . '?text=' . $pesan;
            return response()->json(['message' => 'Servis tersimpan', 'wa_teknisi' => $link], 200);
        }

        return response()->json('Data berhasil disimpan', 200);
    }

    public function update(Request $request, $id)
    {
        $servis = Servis::findOrFail($id);
        if ($request->status == 'selesai' && $servis->tanggal_selesai == null) {
            $servis->tanggal_selesai = now(); 
        }
        $servis->fill($request->all())->save();
        ServisLog::create([
            'id_servis' => $servis->id_servis,
            'id_user' => auth()->id(),
            'status' => $servis->status,
        ]);

        if ($servis->telepon) {
            $pesan = urlencode("Halo, *{$servis->nama_pelanggan}*.\nStatus servis Anda *{$servis->status}*.\nKode: {$servis->kode_servis}\nCek: " . route('servis.track', $servis->kode_servis));
            $wa_link = 'https://wa.me/62' . ltrim($servis->telepon, '0') . '?text=' . $pesan;
            return response()->json(['message' => 'Updated', 'wa' => $wa_link], 200);
        }

        return response()->json('Data berhasil diupdate', 200);
    }

    public function destroy($id)
    {
        Servis::find($id)->delete();
        return response(null, 204);
    }

    public function notaKecil($id)
    {
        $servis = Servis::with('cabang')->findOrFail($id);

        return view('servis.nota', compact('servis'));
    }

    public function edit($id)
    {
        $servis = Servis::findOrFail($id);
        return response()->json($servis);
    }

    public function track($kode)
    {
        $servis = Servis::where('kode_servis', $kode)->firstOrFail();
        return view('servis.track-lite', compact('servis'));
    }
}
