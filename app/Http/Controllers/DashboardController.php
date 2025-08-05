<?php

namespace App\Http\Controllers;
// app/Http/Controllers/DashboardController.php

use App\Models\Kategori;
use App\Models\Member;
use App\Models\Pembelian;
use App\Models\Pengeluaran;
use App\Models\Penjualan;
use App\Models\Produk;
use App\Models\Servis;
use App\Models\Supplier;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        if ($user->level == 1) {
            // Admin
            $id_cabang = $user->id_cabang;
            $kategori = Kategori::where('id_cabang', $id_cabang)->count();
            $produk = Produk::where('id_cabang', $id_cabang)->count();
            $supplier = Supplier::where('id_cabang', $id_cabang)->count();
            $member = Member::where('id_cabang', $id_cabang)->count();

            $tanggal_awal = date('Y-m-01');
            $tanggal_akhir = date('Y-m-d');

            $data_tanggal = [];
            $data_pendapatan = [];

            while (strtotime($tanggal_awal) <= strtotime($tanggal_akhir)) {
                $data_tanggal[] = (int) substr($tanggal_awal, 8, 2);
                $penjualan = Penjualan::where('id_cabang', $id_cabang)->whereDate('created_at', $tanggal_awal)->sum('bayar');
                $pembelian = Pembelian::where('id_cabang', $id_cabang)->whereDate('created_at', $tanggal_awal)->sum('bayar');
                $pengeluaran = Pengeluaran::where('id_cabang', $id_cabang)->whereDate('created_at', $tanggal_awal)->sum('nominal');

                $data_pendapatan[] = $penjualan - $pembelian - $pengeluaran;
                $tanggal_awal = date('Y-m-d', strtotime('+1 day', strtotime($tanggal_awal)));
            }

            return view('admin.dashboard', compact(
                'kategori', 'produk', 'supplier', 'member',
                'tanggal_awal', 'tanggal_akhir', 'data_tanggal', 'data_pendapatan'
            ));
        } elseif ($user->level == 2) {
            // Kasir
            return view('kasir.dashboard');
        } elseif ($user->level == 3) {
            // Teknisi
            $servisSaya = Servis::where('teknisi', $user->name)
                ->where('id_cabang', $user->id_cabang)
                ->latest()
                ->get();

            return view('teknisi.dashboard', compact('servisSaya'));
        }
    }

    public function teknisi()
    {
        $servisSaya = Servis::where('teknisi', auth()->user()->name)
            ->where('id_cabang', auth()->user()->id_cabang)
            ->orderByDesc('created_at')
            ->get();

        return view('teknisi.dashboard', compact('servisSaya'));
    }
}
