<?php

function format_uang ($angka) {
    return number_format($angka, 0, ',', '.');
}

function terbilang ($angka) {
    $angka = abs($angka);
    $baca  = array('', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas');
    $terbilang = '';

    if ($angka < 12) { // 0 - 11
        $terbilang = ' ' . $baca[$angka];
    } elseif ($angka < 20) { // 12 - 19
        $terbilang = terbilang($angka -10) . ' belas';
    } elseif ($angka < 100) { // 20 - 99
        $terbilang = terbilang($angka / 10) . ' puluh' . terbilang($angka % 10);
    } elseif ($angka < 200) { // 100 - 199
        $terbilang = ' seratus' . terbilang($angka -100);
    } elseif ($angka < 1000) { // 200 - 999
        $terbilang = terbilang($angka / 100) . ' ratus' . terbilang($angka % 100);
    } elseif ($angka < 2000) { // 1.000 - 1.999
        $terbilang = ' seribu' . terbilang($angka -1000);
    } elseif ($angka < 1000000) { // 2.000 - 999.999
        $terbilang = terbilang($angka / 1000) . ' ribu' . terbilang($angka % 1000);
    } elseif ($angka < 1000000000) { // 1000000 - 999.999.990
        $terbilang = terbilang($angka / 1000000) . ' juta' . terbilang($angka % 1000000);
    }

    return $terbilang;
}

function tanggal_indonesia($tgl, $tampil_hari = true)
{
    $nama_hari  = array(
        'Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jum\'at', 'Sabtu'
    );
    $nama_bulan = array(1 =>
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    );

    $tahun   = substr($tgl, 0, 4);
    $bulan   = $nama_bulan[(int) substr($tgl, 5, 2)];
    $tanggal = substr($tgl, 8, 2);
    $text    = '';

    if ($tampil_hari) {
        $urutan_hari = date('w', mktime(0,0,0, substr($tgl, 5, 2), $tanggal, $tahun));
        $hari        = $nama_hari[$urutan_hari];
        $text       .= "$hari, $tanggal $bulan $tahun";
    } else {
        $text       .= "$tanggal $bulan $tahun";
    }
    
    return $text; 
}

function tambah_nol_didepan($value, $threshold = null)
{
    return sprintf("%0". $threshold . "s", $value);
}

function daftar_metode_pembayaran()
{
    return [
        'tunai' => 'Tunai',
        'transfer_bank' => 'Bank / Transfer',
        'qris' => 'QRIS',
    ];
}

function label_metode_pembayaran($value)
{
    $options = daftar_metode_pembayaran();

    return $options[$value] ?? ucfirst(str_replace('_', ' ', $value));
}

function daftar_skema_pembayaran()
{
    return [
        'langsung' => 'Langsung',
        'kredit' => 'Kredit / Hutang',
    ];
}

function label_skema_pembayaran($value)
{
    $options = daftar_skema_pembayaran();

    return $options[$value] ?? ucfirst(str_replace('_', ' ', $value));
}

function status_pembayaran($totalTagihan, $dibayar)
{
    if ((int) $totalTagihan <= 0 || (int) $dibayar <= 0) {
        return 'belum_bayar';
    }

    if ((int) $dibayar >= (int) $totalTagihan) {
        return 'lunas';
    }

    return 'sebagian';
}

function label_status_pembayaran($value)
{
    $options = [
        'belum_bayar' => 'Belum Bayar',
        'sebagian' => 'Bayar Sebagian',
        'lunas' => 'Lunas',
    ];

    return $options[$value] ?? ucfirst(str_replace('_', ' ', $value));
}

function class_status_pembayaran($value)
{
    $options = [
        'belum_bayar' => 'label-danger',
        'sebagian' => 'label-warning',
        'lunas' => 'label-success',
    ];

    return $options[$value] ?? 'label-default';
}

function daftar_kategori_pengeluaran()
{
    return [
        'listrik' => 'Listrik',
        'atk' => 'ATK',
        'gaji_karyawan' => 'Gaji Karyawan',
        'operasional_lainnya' => 'Operasional Lainnya',
    ];
}

function label_kategori_pengeluaran($value)
{
    $options = daftar_kategori_pengeluaran();

    return $options[$value] ?? ucfirst(str_replace('_', ' ', $value));
}

function daftar_kategori_pendapatan_lain()
{
    return [
        'penjualan_barang_bekas' => 'Penjualan Barang Bekas',
        'penjualan_kardus_bekas' => 'Penjualan Kardus Bekas',
        'pendapatan_lain_lain' => 'Pendapatan Lain-lain',
    ];
}

function label_kategori_pendapatan_lain($value)
{
    $options = daftar_kategori_pendapatan_lain();

    return $options[$value] ?? ucfirst(str_replace('_', ' ', $value));
}

function hitung_diskon_nominal($subtotal, $diskonPersen = 0)
{
    $subtotal = max((int) $subtotal, 0);
    $diskonPersen = max((float) $diskonPersen, 0);

    return (int) round($subtotal * $diskonPersen / 100);
}
