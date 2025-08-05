<?php

namespace App\Http\Controllers;

use App\Models\Pembelian;
use App\Models\Pengeluaran;
use App\Models\Penjualan;
use App\Models\Servis;
use Illuminate\Http\Request;
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
    
        while (strtotime($awal) <= strtotime($akhir)) {
            $tanggal = $awal;
            $awal = date('Y-m-d', strtotime("+1 day", strtotime($awal)));
    
            $penjualan = Penjualan::where('id_cabang', auth()->user()->id_cabang)
                ->whereDate('created_at', $tanggal)
                ->sum('bayar');
    
            $pembelian = Pembelian::where('id_cabang', auth()->user()->id_cabang)
                ->whereDate('created_at', $tanggal)
                ->sum('bayar');
    
            $pengeluaran = Pengeluaran::where('id_cabang', auth()->user()->id_cabang)
                ->whereDate('created_at', $tanggal)
                ->sum('nominal');
    
            $servis = Servis::where('id_cabang', auth()->user()->id_cabang)
                    ->where('status', 'selesai')
                    ->whereDate('tanggal_selesai', $tanggal)
                    ->sum('biaya_servis');
        
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
        $pdf->setPaper('a4', 'potrait');
        
        return $pdf->stream('Laporan-pendapatan-'. date('Y-m-d-his') .'.pdf');
    }
}
