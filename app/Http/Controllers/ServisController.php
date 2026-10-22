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
            ->addColumn('biaya', fn($s) => format_uang($s->biaya_servis))
            ->addColumn('dibayar', fn($s) => format_uang($s->dibayar))
            ->addColumn('sisa', fn($s) => format_uang($s->sisa))
            ->addColumn('pembayaran', fn($s) => '<span class="label '.class_status_pembayaran($s->status_pembayaran).'">'.label_status_pembayaran($s->status_pembayaran).'</span>')
            ->addColumn('aksi', function ($s) {
                $btnCetak = '<a href="' . route('servis.nota_kecil', $s->id_servis) . '" target="_blank" class="btn btn-xs btn-info btn-flat"><i class="fa fa-print"></i></a>';
                $btnEdit = '<button onclick="editForm(`' . route('servis.edit', $s->id_servis) . '`)" class="btn btn-xs btn-primary btn-flat"><i class="fa fa-edit"></i></button>';
                $btnHapus = '<button onclick="deleteData(`' . route('servis.destroy', $s->id_servis) . '`)" class="btn btn-xs btn-danger btn-flat"><i class="fa fa-trash"></i></button>';
                $btnBayar = auth()->user()->level == 1 && $s->ledger_entry_id ? '<a class="btn btn-xs btn-success" title="Pembayaran servis" href="'.route('business.index',['module'=>'piutang-servis','q'=>$s->kode_servis,'start'=>date('Y-m-01',strtotime($s->tanggal_selesai))]).'"><i class="fa fa-credit-card"></i></a>' : '';
                return '<div class="btn-group">'.$btnCetak . ' ' . $btnEdit . ' ' . $btnBayar . ' ' . $btnHapus.'</div>';
            })
            ->rawColumns(['kode_servis', 'status', 'pembayaran', 'aksi'])
            ->make(true);
    }

    public function store(Request $request)
    {
        $payload = $this->validateService($request);
        $kode = 'SRV-' . strtoupper(Str::random(6));

        $servis = new Servis();
        $servis->fill($payload);

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
        $payload = $this->validateService($request);
        $servis = Servis::where('id_cabang', auth()->user()->id_cabang)->lockForUpdate()->findOrFail($id);
        if ($request->status == 'selesai' && $servis->tanggal_selesai == null) {
            $servis->tanggal_selesai = now(); 
        }
        $servis->fill($payload)->save();
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
        Servis::where('id_cabang', auth()->user()->id_cabang)->findOrFail($id)->delete();
        return response(null, 204);
    }

    public function notaKecil($id)
    {
        $servis = Servis::with('cabang')->where('id_cabang', auth()->user()->id_cabang)->findOrFail($id);

        return view('servis.nota', compact('servis'));
    }

    public function edit($id)
    {
        $servis = Servis::where('id_cabang', auth()->user()->id_cabang)->findOrFail($id);
        return response()->json($servis);
    }

    public function track($kode)
    {
        $servis = Servis::where('kode_servis', $kode)->firstOrFail();
        return view('servis.track-lite', compact('servis'));
    }

    private function validateService(Request $request): array
    {
        $payload = $request->validate([
            'nama_pelanggan' => 'nullable|string|max:255', 'telepon' => 'nullable|string|max:50',
            'id_member' => 'nullable|integer', 'keluhan' => 'nullable|string|max:2000',
            'tipe_barang' => 'nullable|string|max:255', 'merk' => 'nullable|string|max:255',
            'kerusakan' => 'nullable|string|max:2000', 'status' => 'required|in:diproses,selesai,diambil',
            'biaya_servis' => 'required|integer|min:0|max:2000000000', 'teknisi' => 'nullable|string|max:255',
            'garansi_hari' => 'nullable|integer|min:0|max:3650',
            'jatuh_tempo' => 'nullable|date_format:Y-m-d',
        ]);
        if (! empty($payload['id_member'])) {
            abort_unless(Member::where('id_cabang', auth()->user()->id_cabang)->where('id_member', $payload['id_member'])->exists(), 422);
        }
        if (auth()->user()->level == 3) {
            $payload['teknisi'] = auth()->user()->name;
        }
        return $payload;
    }
}
