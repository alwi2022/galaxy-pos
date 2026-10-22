# Operasional dan keuangan Galaxy POS

Implementasi lokal, 22 Oktober 2026. Menu baru berada di **Operasional & Keuangan** dan hanya dapat diakses admin. Data selalu mengikuti cabang pengguna.

## Modul

| Menu | Fungsi |
| --- | --- |
| Kartu Stok & Opname | Riwayat stok masuk/keluar, pemakaian internal, barang rusak/hilang, dan hasil penghitungan stok fisik |
| Retur Barang | Retur sebagian/seluruh barang dari transaksi asal; membedakan barang pelanggan yang layak jual dan rusak |
| Kas, Bank & Modal | Modal awal/tambahan, pengambilan pemilik, pembagian hasil, rekening tambahan, transfer, pembayaran PPN, dan pencocokan saldo |
| Pinjaman & Cicilan | Pinjaman yang diterima/diberikan; pokok, bunga, sisa pinjaman, dan jatuh tempo |
| Inventaris | Pembelian aset, nilai sisa, umur manfaat, penyusutan, penjualan/penghapusan aset, laba/rugi pelepasan |
| Pembayaran di Muka | Pembayaran biaya atau penerimaan pendapatan yang diakui merata selama beberapa bulan |
| Akuntansi & Pajak | Jurnal, filter buku besar akun, ekspor CSV, laba rugi, neraca, arus kas, rekap PPN, dan tutup periode |

Pembelian, penjualan, cicilan tagihan, biaya operasional, pendapatan lain, serta servis dari menu sebelumnya kini terhubung ke jurnal. Metode tunai memakai Kas Toko, transfer memakai Bank, dan QRIS memakai akun QRIS. Rekening tambahan dapat dipakai dalam transaksi keuangan dan transfer saldo; formulir transaksi barang tetap memakai pemetaan tiga metode tersebut.

## Mulai memakai

1. Login dengan akun admin dan pastikan cabang sesuai.
2. Catat modal melalui Kas, Bank & Modal.
3. Buat kategori dan produk. Stok awal yang diisi pada produk membentuk riwayat stok dan jurnal persediaan terhadap modal.
4. Catat restok berikutnya melalui Pembelian. Pembayaran awal/cicilan membentuk jurnal tersendiri.
5. Catat penjualan seperti biasa. Stok dan HPP dicatat saat transaksi difinalkan.
6. Gunakan Retur Barang untuk pengembalian; gunakan Kartu Stok & Opname untuk pemakaian, kerusakan, dan selisih stok fisik.
7. Cocokkan kas fisik/rekening koran dengan saldo aplikasi. Hasil pencocokan disimpan sebagai snapshot; selisih tidak otomatis menjadi jurnal penyesuaian.
8. Setelah bulan berakhir, proses penyusutan/pengakuan melalui Akuntansi & Pajak. Tutup bulan terdahulu sebelum bulan berikutnya.

## Aturan pencatatan

- Nilai jurnal menggunakan rupiah bulat; total debit wajib sama dengan kredit.
- HPP memakai rata-rata bergerak. Nilai persediaan disimpan terpisah dari harga satuan agar pembulatan tidak meninggalkan saldo saat stok habis.
- Pergerakan stok tidak dapat membuat stok negatif dan harus mengikuti urutan waktu per produk.
- Transaksi final tidak dapat disimpan ulang atau diedit detailnya. Pembatalan menyimpan transaksi asli dan membuat jurnal serta pergerakan pembalik.
- Pembelian yang barangnya sudah memiliki pergerakan lanjutan harus ditangani melalui retur, agar pembatalan tidak merusak valuasi.
- Retur tidak dapat melebihi jumlah transaksi asal. Diskon dan PPN dialokasikan dari transaksi asal, termasuk pembulatan retur bertahap.
- Nilai retur mengurangi sisa tagihan terlebih dahulu. Kelebihannya menjadi pengembalian uang. Riwayat pembayaran awal tetap tersimpan.
- Retur pelanggan rusak tidak menambah stok layak jual; HPP barang tersebut tetap menjadi beban.
- Produk dengan stok atau riwayat stok tidak dapat dihapus. Perubahan stok melalui master produk juga dicatat sebagai penyesuaian.
- Penyusutan garis lurus dan pengakuan pembayaran di muka dimulai pada bulan transaksi. Proses akhir bulan dapat diulang sebelum penutupan tanpa menggandakan jurnal.
- Periode tertutup menolak pencatatan pada tanggal di bulan tersebut. Pembatalan pada periode berikutnya dicatat pada tanggal pembatalan.
- Laba berjalan ditampilkan secara kumulatif pada neraca. Tutup periode mengunci bulan dan menjalankan pengakuan; tidak membuat jurnal penutupan tahunan ke laba ditahan.
- Servis berstatus selesai/diambil dianggap sudah diterima tunai, mengikuti pola data servis sebelumnya. Belum ada cicilan/piutang khusus servis.

## Laporan

Laba rugi pada Akuntansi & Pajak memakai pendapatan dikurangi HPP dan beban, dengan PPN dipisahkan. Neraca menampilkan aset, kewajiban, modal, dan laba berjalan. Arus kas memisahkan operasional, investasi, dan pendanaan; transfer antarrekening tidak mengubah kas bersih.

Menu Laporan sebelumnya tetap tersedia untuk ringkasan transaksi, PDF, dan Excel. Ringkasan kas sudah memperhitungkan pengembalian uang serta pembatalan. Tabel detail penjualan/pembelian dan agregat barang/kategori masih menunjukkan transaksi asal sebelum retur; lihat riwayat retur dan jurnal untuk koreksinya. Nilai "Selisih Kas Transaksi" bukan laba rugi akuntansi.

Rekap PPN menunjukkan pergerakan akun PPN masukan dan keluaran. Pengkreditan PPN masukan terhadap keluaran dapat dicatat melalui jurnal penyesuaian, lalu pembayaran melalui Kas, Bank & Modal. Belum mencakup e-Faktur, pelaporan SPT, atau validasi kelayakan pengkreditan pajak.

Ekspor jurnal CSV mengikuti tanggal dan cabang. Rekening/saldo dan laporan dapat difilter melalui halaman Akuntansi & Pajak.

## Data demo dan menjalankan proyek

PHP lokal yang dipakai: `C:\xampp\php\php.exe` (8.2). Database lokal: MariaDB XAMPP, port 3307, database `tokoku`. MySQL 8 pada port 3306 merupakan instalasi berbeda.

```powershell
C:\xampp\php\php.exe artisan serve --host=127.0.0.1 --port=8000
```

Akun dummy bawaan: `admin@gmail.com`, `kasir1@gmail.com`, `teknisi1@gmail.com`, semuanya berpassword `123`.

Untuk membuat ulang seluruh data dummy, perintah berikut menghapus seluruh tabel pada database yang dikonfigurasi:

```powershell
C:\xampp\php\php.exe artisan migrate:fresh --seed
C:\xampp\php\php.exe artisan db:seed --class=BusinessDemoSeeder
```

Seeder demo default mencakup 1–22 Oktober 2026: 88 penjualan, 4 pembelian, 11 servis, 22 biaya operasional, 5 pendapatan lain, serta contoh retur, pemakaian internal, cicilan, dan transfer. September berisi modal, pembelian aset, sewa di muka, pinjaman bank, dan contoh periode tertutup. Tanggal akhir bisa diganti dengan environment `DEMO_SEED_END=YYYY-MM-DD` sebelum menjalankan seeder.

Seeder tidak mengisi ulang jika jurnal sudah terisi. `MonthlyDemoSeeder` merupakan alias ke `BusinessDemoSeeder`. Pengujian browser dapat menambah transaksi dummy bertanda "Uji browser".

## Verifikasi

```powershell
C:\xampp\php\php.exe vendor/phpunit/phpunit/phpunit --filter BusinessLedgerTest
```

PHPUnit memakai SQLite dalam memori, sehingga tidak menghapus database lokal. Pengujian mencakup stok/valuasi, HPP, retur tunai/kredit, penolakan retur berlebih, penolakan stok kurang, pembatalan, transaksi final, opname, modal/transfer/rekonsiliasi, pinjaman/bunga, pelepasan aset, servis, tutup periode, ekspor, batas cabang, otorisasi, serta konsistensi dan pengulangan seeder.

Pengujian menyeluruh proyek juga menemukan empat halaman autentikasi bawaan yang sudah tidak tersedia: `auth.confirm-password`, `auth.forgot-password`, `auth.reset-password`, dan `auth.register`. Modul operasional baru tidak menggunakan halaman-halaman ini. Konfigurasi email untuk reset password juga belum diuji.
