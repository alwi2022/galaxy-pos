<?php

return [
    'stok' => ['title' => 'Kartu Stok & Opname', 'icon' => 'cubes', 'statuses' => []],
    'retur' => ['title' => 'Retur Barang', 'icon' => 'exchange', 'statuses' => ['penjualan' => 'Retur penjualan', 'pembelian' => 'Retur pembelian']],
    'kas' => ['title' => 'Kas, Bank & Modal', 'icon' => 'money', 'statuses' => []],
    'rekening-bank' => ['title' => 'Rekening Bank', 'icon' => 'bank', 'statuses' => ['active' => 'Aktif', 'inactive' => 'Nonaktif']],
    'pinjaman' => ['title' => 'Pinjaman & Cicilan', 'icon' => 'handshake-o', 'statuses' => ['open' => 'Belum lunas', 'paid' => 'Lunas']],
    'aset' => ['title' => 'Inventaris & Penyusutan', 'icon' => 'desktop', 'statuses' => ['active' => 'Aktif', 'disposed' => 'Dilepas']],
    'akrual' => ['title' => 'Pembayaran di Muka', 'icon' => 'calendar', 'statuses' => ['open' => 'Belum selesai', 'paid' => 'Selesai diakui']],
    'piutang-servis' => ['title' => 'Pembayaran Servis', 'icon' => 'wrench', 'statuses' => ['belum_bayar' => 'Belum dibayar', 'sebagian' => 'Sebagian', 'lunas' => 'Lunas']],
    'rekonsiliasi' => ['title' => 'Rekonsiliasi Bank', 'icon' => 'balance-scale', 'statuses' => ['unmatched' => 'Belum cocok', 'matched' => 'Sudah cocok']],
    'pajak' => ['title' => 'Pajak & PPN', 'icon' => 'file-text-o', 'statuses' => ['draft' => 'Draft', 'filed' => 'Bukti lapor dicatat']],
    'akuntansi' => ['title' => 'Buku Besar & Tutup Buku', 'icon' => 'book', 'statuses' => []],
];
