<?php

namespace App\Http\Controllers;

use App\Models\Pembelian;
use App\Models\Pengeluaran;
use App\Models\Penjualan;
use App\Models\Servis;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PDF;

class LaporanController extends Controller
{
    public function index(Request $request)
    {
        $tanggalAwal = date('Y-m-d', mktime(0, 0, 0, date('m'), 1, date('Y')));
        $tanggalAkhir = date('Y-m-d');

        if ($request->has('tanggal_awal') && $request->tanggal_awal != "" && $request->has('tanggal_akhir') && $request->tanggal_akhir) {
            $tanggalAwal = $request->tanggal_awal;
            $tanggalAkhir = $request->tanggal_akhir;
        }

        return view('laporan.index', compact('tanggalAwal', 'tanggalAkhir'));
    }

    public function getData($awal, $akhir)
    {
        $no = 1;
        $data = [];
        $total_pendapatan = 0;
        $idCabang = auth()->user()->id_cabang;

        $penjualanByDate = Penjualan::selectRaw('DATE(created_at) as tanggal, SUM(bayar) as total')
            ->where('id_cabang', $idCabang)
            ->whereBetween('created_at', [$awal . ' 00:00:00', $akhir . ' 23:59:59'])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('total', 'tanggal');

        $pembelianByDate = Pembelian::selectRaw('DATE(created_at) as tanggal, SUM(bayar) as total')
            ->where('id_cabang', $idCabang)
            ->whereBetween('created_at', [$awal . ' 00:00:00', $akhir . ' 23:59:59'])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('total', 'tanggal');

        $pengeluaranByDate = Pengeluaran::selectRaw('DATE(created_at) as tanggal, SUM(nominal) as total')
            ->where('id_cabang', $idCabang)
            ->whereBetween('created_at', [$awal . ' 00:00:00', $akhir . ' 23:59:59'])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('total', 'tanggal');

        $servisByDate = Servis::selectRaw('DATE(tanggal_selesai) as tanggal, SUM(biaya_servis) as total')
            ->where('id_cabang', $idCabang)
            ->where('status', 'selesai')
            ->whereBetween('tanggal_selesai', [$awal . ' 00:00:00', $akhir . ' 23:59:59'])
            ->groupBy(DB::raw('DATE(tanggal_selesai)'))
            ->pluck('total', 'tanggal');
    
        while (strtotime($awal) <= strtotime($akhir)) {
            $tanggal = $awal;
            $awal = date('Y-m-d', strtotime("+1 day", strtotime($awal)));
    
            $penjualan = (int) ($penjualanByDate[$tanggal] ?? 0);
            $pembelian = (int) ($pembelianByDate[$tanggal] ?? 0);
            $pengeluaran = (int) ($pengeluaranByDate[$tanggal] ?? 0);
            $servis = (int) ($servisByDate[$tanggal] ?? 0);
        
            $pendapatan = $penjualan + $servis - $pembelian - $pengeluaran;
            $total_pendapatan += $pendapatan;
    
            $data[] = [
                'DT_RowIndex' => $no++,
                'tanggal' => tanggal_indonesia($tanggal, false),
                'penjualan' => format_uang($penjualan),
                'pembelian' => format_uang($pembelian),
                'pengeluaran' => format_uang($pengeluaran),
                'servis' => format_uang($servis), 
                'pendapatan' => format_uang($pendapatan),
            ];
            
        }
    
        $data[] = [
            'DT_RowIndex' => '',
            'tanggal' => '',
            'penjualan' => '',
            'pembelian' => '',
            'pengeluaran' => '',
            'servis' => '',
            'pendapatan' => format_uang($total_pendapatan),
        ];
        
    
        return $data;
    }
    

    public function data($awal, $akhir)
    {
        $data = $this->getData($awal, $akhir);

        return datatables()
            ->of($data)
            ->make(true);
    }

    public function exportPDF($awal, $akhir)
    {
        $data = $this->getData($awal, $akhir);
        $pdf  = PDF::loadView('laporan.pdf', compact('awal', 'akhir', 'data'));
        $pdf->setPaper('a4', 'portrait');
        
        return $pdf->stream('Laporan-pendapatan-'. date('Y-m-d-his') .'.pdf');
    }
}
