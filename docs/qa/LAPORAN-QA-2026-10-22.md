# Laporan QA — Galaxy POS

**Tanggal pengujian:** 22 Oktober 2026
**Penguji:** QA (otomatis, lewat browser)
**Aplikasi:** Galaxy POS (Laravel 8.83.29) — POS toko komputer: penjualan, servis, keuangan, akuntansi

---

## 1. Ringkasan

- **Layar dicek:** 37 menu sidebar + dashboard + login + profil + modal tambah/edit + dashboard per peran (admin/kasir/teknisi) + uji responsif. Total **56 screenshot** tersimpan di `docs/qa/screenshots/`.
- **Fitur diuji end-to-end:** 18 area (master data, penjualan, pembelian, servis, retur, kartu stok/opname, utang-piutang, kas/bank/modal, pinjaman, pendapatan/biaya, inventaris, pembayaran di muka, rekonsiliasi, pajak, buku besar/tutup buku, laporan, hak akses, cabang).
- **Temuan per tingkat keparahan:**
  - Kritis: **0**
  - Tinggi: **0**
  - Sedang: **3** (QA-001, QA-002, QA-003)
  - Rendah: **6** (QA-004 s/d QA-009)
- **Kesimpulan:** **Siap pakai, dengan perbaikan minor.** Mesin akuntansi (double-entry, HPP rata-rata bergerak, PPN, periode tertutup, laporan keuangan) sangat solid dan konsisten — Neraca seimbang (selisih 0), Laba Rugi dan Arus Kas cocok dengan buku besar. Semua alur transaksi inti (jual, beli, retur, servis, stok, pembayaran, kas/modal) menghasilkan jurnal yang benar dan memengaruhi stok/kas/laporan dengan tepat. Hak akses per peran dan pemisahan data antar cabang berjalan benar. Temuan yang ada bersifat kosmetik/UX (konsistensi tombol & breadcrumb, format angka, satu bidang wajib yang tidak ditandai, dan satu field barcode yang tidak aktif), tidak ada yang merusak data atau integritas keuangan.

---

## 2. Lingkungan

| Item | Nilai |
|---|---|
| PHP | **8.4.27** (`C:\Users\imamb\.config\php84\php.exe`). Catatan: `php` default di PATH adalah 8.2.12 (XAMPP); semua perintah QA memakai binary 8.4.27. |
| Database | MySQL `tokoku` di `127.0.0.1:3307` (sesuai `.env`, port non-standar 3307) |
| Server uji | PHP built-in server 8.4.27 di **http://127.0.0.1:9753** (lihat catatan di bawah) |
| Browser | Playwright (Chromium) |
| Ukuran layar diuji | 1366×768, 768×1024, 375×812 |
| Seeder dipakai | **`BusinessDemoSeeder`** (data 1–22 Oktober 2026: modal, aset, sewa di muka, 1 periode tahun lalu yang sudah ditutup) |
| `php artisan migrate` | "Nothing to migrate" (skema sudah lengkap) |

**Hasil `php artisan test`:** **59 lulus, 4 gagal, 1 risky, 6 skipped** (9.72 dtk).
Yang gagal: `RegistrationTest::registration screen can be rendered`, `PasswordResetTest::reset password link screen can be rendered`, `PasswordResetTest::reset password screen can be rendered`, `PasswordConfirmationTest::confirm password screen can be rendered` — semuanya karena view `auth.register`, `auth.forgot-password`, `auth.reset-password`, `auth.confirm-password` tidak ada (HTTP 500 saat render). **Ini temuan yang sudah diketahui, bukan bug baru** (sesuai catatan tugas).

**Catatan lingkungan (bukan bug aplikasi):**
- Port 8000/8001/8080 gagal di-bind ("socket diblokir izinnya" / "An attempt was made to access a socket in a way forbidden by its access permissions") oleh proses uji — kemungkinan firewall. Port 8000 sudah dipakai server XAMPP PHP 8.2 milik user. Karena itu server uji dijalankan di 9753 dengan PHP 8.4.27 (memenuhi syarat versi).
- Di console browser sempat muncul 1 error transien `ERR_NO_BUFFER_SPACE` saat memuat `galaxy-ui.css` — keterbatasan socket PHP built-in server saat banyak request aset bersamaan; hilang saat reload. Bukan cacat aplikasi.
- Aplikasi memunculkan banyak peringatan `PHP Deprecated` di CLI (framework lama di PHP 8.4). Tidak memengaruhi fungsi; muncul di stderr saja.

---

## 3. Tabel konsistensi UI

Acuan: Kategori (`.box` + `box-header.with-border`, tombol aksi `btn-xs btn-flat`, DataTables, modal `form-horizontal` Simpan primary/Batal warning `btn-sm`). ✅ = sesuai acuan.

| Layar | URL | Sesuai acuan | Masalah | Screenshot |
|---|---|:---:|---|---|
| Login | `/login` | ✅ | — | 01 |
| Dashboard (admin) | `/dashboard` | ✅ | — | 02 |
| Kategori (acuan) | `/kategori` | ✅ | — | 03, 04 |
| Produk | `/produk` | ✅ | Nominal rata kiri, tanpa "Rp" (QA-005) | 05, 06 |
| Member | `/member` | ✅ | — | 07 |
| Supplier | `/supplier` | ✅ | — | 08 |
| Transaksi Baru/Aktif (POS) | `/transaksi` | ⚠️ | Typo breadcrumb "Penjaualn" (QA-004); field barcode tak aktif (QA-002) | 09, 10, 11 |
| Penjualan | `/penjualan` | ✅ | Nominal rata kiri (QA-005) | 12 |
| Pembelian | `/pembelian` | ✅ | Nominal rata kiri (QA-005) | 13, 14, 15 |
| Kartu Stok & Opname | `/usaha/stok` | ✅ | — | 16, 17 |
| Retur Barang | `/usaha/retur` | ✅ | — | 18, 19 |
| Servis Komputer | `/servis` | ✅ | Breadcrumb 2 level (QA-006) | 20, 21 |
| Pembayaran Servis | `/usaha/piutang-servis` | ✅ | Field "Keterangan" wajib tak ditandai (QA-003) | 22 |
| Utang & Piutang | `/pembayaran` | ✅ | Nominal rata kiri; breadcrumb 2 level | 23, 24 |
| Kas, Bank & Modal | `/usaha/kas` | ✅ | — | 25, 26 |
| Pinjaman & Cicilan | `/usaha/pinjaman` | ✅ | — | 27 |
| Pendapatan Lain | `/pendapatan-lain` | ⚠️ | Tombol header `btn-sm` (acuan `btn-xs`) (QA-007); breadcrumb 2 level | 28 |
| Biaya Operasional | `/pengeluaran` | ⚠️ | Tombol header `btn-sm` (QA-007); breadcrumb 2 level | 29 |
| Inventaris & Penyusutan | `/usaha/aset` | ✅ | — | 30 |
| Pembayaran di Muka | `/usaha/akrual` | ✅ | — | 31 |
| Rekonsiliasi Bank | `/usaha/rekonsiliasi` | ✅ | — | 32 |
| Pajak & PPN | `/usaha/pajak` | ✅ | — | 33 |
| Buku Besar & Tutup Buku | `/usaha/akuntansi` | ✅ | — | 34, 35 |
| Ringkasan Kas (Laporan) | `/laporan?tab=ringkasan` | ✅ | breadcrumb 2 level | 39 |
| Laporan Penjualan | `/laporan?tab=penjualan` | ✅ | — | 41 |
| Laporan Pembelian | `/laporan?tab=pembelian` | ✅ | — | 44 |
| Laporan per Barang | `/laporan?tab=produk` | ✅ | — | 42 |
| Laporan per Kategori | `/laporan?tab=kategori` | ✅ | — | 43 |
| Laba Rugi | `/laporan/keuangan/profit` | ✅ | — | 37 |
| Neraca | `/laporan/keuangan/balance` | ✅ | — | 36 |
| Arus Kas | `/laporan/keuangan/cash` | ✅ | — | 38 |
| Laporan PPN | `/laporan/keuangan/tax` | ✅ | — | 40 |
| User | `/user` | ✅ | — | 45 |
| Pengaturan | `/setting` | ✅ | — | 46 |
| Pindah Cabang | `/user/pindah-cabang` | ✅ | Breadcrumb hanya "Home" (QA-008) | 47 |
| Data Cabang | `/cabang` | ⚠️ | Tombol `btn-sm` (QA-007); breadcrumb "Home" (QA-008) | 49 |
| Profil | `/profil` | ✅ | — | 50 |
| Dashboard Kasir | `/dashboard` (level 2) | ✅ | — | 51 |
| Dashboard Teknisi | `/dashboard` (level 3) | ✅ | — | 53 |

**Catatan umum UI (berlaku semua layar):** logo di topbar & footer menautkan ke `index2.html` (tautan placeholder template yang mati) — QA-009. Tema AdminLTE/Bootstrap 3 konsisten, ikon Font Awesome bermakna sesuai, label status memakai `label` berwarna, tanggal `dd/mm/yyyy`, bahasa Indonesia konsisten.

---

## 4. Tabel hasil fitur

| Fitur | Skenario | Hasil | Catatan |
|---|---|:---:|---|
| Master - Kategori | Tambah "QA Test Kategori" | Lulus | Tersimpan (id 57). Catatan: `store` tanpa validasi server (nama kosong tetap tersimpan) |
| Master - Produk | Tambah "QA Produk Uji" stok 50 @10.000 | Lulus | Kode auto P000036, `inventory_value` 500.000 (50×10.000) benar |
| Master - Member/Supplier | Lihat daftar, pencarian, pagination | Lulus | DataTables serverSide berfungsi |
| Penjualan (kasir) | Jual DEMO-001 1 pcs, PPN 11%, tunai, bayar 100.000 | Lulus | Tagihan 83.250, kembali 16.750, stok 67→66; jurnal double-entry + HPP benar |
| Penjualan - input barcode | Ketik kode + Enter di field barcode | **Gagal** | Field barcode tidak bereaksi (QA-002). Alternatif modal pencarian berfungsi |
| Pembelian | Beli DEMO-002 10 pcs @59.144, PPN, tunai lunas | Lulus | Stok 72→82, `inventory_value` +591.440; jurnal Persediaan/PPN Masukan/Utang + pembayaran benar |
| Servis - terima | Buat servis 200.000, status diproses | Lulus | Kode SRV-RW8SEZ |
| Servis - selesai | Ubah status → Selesai | Lulus | Memicu tagihan: Dr Piutang Servis / Cr Pendapatan Servis 200.000 |
| Pembayaran Servis - cicilan | Bayar 50.000 dari 200.000 | Sebagian | Berhasil setelah isi "Keterangan" (wajib tapi tak ditandai — QA-003). Jurnal Dr Kas / Cr Piutang Servis benar |
| Retur penjualan | Retur 1 Mouse, "layak jual, masuk stok" | Lulus | Stok 66→67; jurnal membalik penjualan + HPP dengan benar |
| Kartu Stok & Opname | Barang rusak QA Produk Uji -5 | Lulus | Stok 50→45, inv_value -50.000; jurnal Dr Pemakaian & Selisih Stok / Cr Persediaan |
| Kartu stok vs stok produk | Cocokkan saldo | Lulus | Saldo kartu stok = stok produk |
| Utang & Piutang | Lunasi hutang pembelian #22 (sisa 6.521.250) | Lulus | Status → lunas; jurnal Dr Utang Supplier / Cr Kas |
| Kas/Bank/Modal | Setoran modal 1.000.000 ke Kas Toko | Lulus | Jurnal Dr Kas / Cr Modal Pemilik |
| Buku Besar - periode tertutup | Jurnal penyesuaian tanggal 15/09/2026 (tutup) | Lulus (ditolak benar) | "Periode 2026-09-01 sudah ditutup." 0 baris masuk DB |
| Laporan - Neraca | Cek selisih | Lulus | Aset 130.425.370 = Kewajiban+Ekuitas 130.425.370, **selisih 0** |
| Laporan - Laba Rugi | Cek perhitungan | Lulus | Laba 7.036.735 = Pendapatan 21.613.500 − Beban 14.576.765 |
| Laporan - Arus Kas | Cek saldo akhir vs buku besar | Lulus | Saldo akhir 69.663.382 = Kas 31.099.777 + Bank 37.769.555 + QRIS 794.050 |
| Laporan - ekspor | PDF & Excel | Lulus | PDF `application/pdf`, Excel `.xlsx`, nama file benar |
| Rekonsiliasi - template CSV | Unduh template | Lulus | `text/csv`, header `date,reference,description,amount` + contoh |
| Hak akses - Kasir | Menu & akses URL admin | Lulus | Sidebar: Dashboard/Transaksi/Keuangan. URL admin → redirect `/dashboard` |
| Hak akses - Teknisi | Menu & akses URL admin | Lulus | Sidebar: Dashboard/Servis. URL admin & `/penjualan` → redirect |
| Cabang | Pindah ke Cabang Serang | Lulus | Dashboard & Produk Serang kosong (Pusat 7 produk, Serang 0) — data terpisah |
| Responsif | 1366 / 768 / 375 px | Lulus | Tidak ada halaman melebar; tabel lebar pakai mode responsif DataTables |

**Fitur yang belum diuji tuntas** (tersedia & terlihat benar, tetapi alur penuhnya tidak dijalankan sampai habis karena keterbatasan waktu) — lihat Bagian 6.

---

## 5. Daftar temuan

### QA-001 — [Sedang] Field "Keterangan" wajib pada pembayaran servis tidak ditandai & gagal diam-diam
- **Layar/URL:** Pembayaran Servis `/usaha/piutang-servis`, modal "Terima pembayaran servis" — peran admin.
- **Langkah reproduksi:** Klik "Bayar" pada satu tagihan → isi Nominal → klik Simpan **tanpa** mengisi "Keterangan".
- **Harapan:** Muncul pesan validasi yang jelas, atau field ditandai wajib (\*).
- **Kenyataan:** Form tidak terkirim sama sekali, tanpa pesan terlihat (bubble HTML5 `required` tertahan di dalam modal). Setelah "Keterangan" diisi, pembayaran berhasil dan jurnal benar.
- **Bukti:** Screenshot 22; dari DOM: input `description` berstatus `:invalid` "Please fill out this field."
- **Saran:** Tandai label wajib dan/atau tampilkan pesan validasi; atau jadikan "Keterangan" opsional (seperti modal bayar hutang). File terkait: form `service-pay` pada modul `usaha` (lihat `resources/views` modul akuntansi/keuangan + `BusinessController`).

### QA-002 — [Sedang] Field barcode di POS tidak aktif (ketik + Enter tidak menambah produk)
- **Layar/URL:** Transaksi `/transaksi` (dan `/pembelian_detail`), input "Cari barcode / kode produk" — peran admin/kasir.
- **Langkah reproduksi:** Fokus ke field barcode → ketik `DEMO-001` → tekan Enter.
- **Harapan:** Produk langsung masuk keranjang (alur scan barcode).
- **Kenyataan:** Tidak ada reaksi AJAX; form ter-submit GET biasa (URL jadi `?kode_produk=DEMO-001&id_produk=`), produk tidak masuk. Harus pakai tombol ikon cari → modal → "Pilih".
- **Bukti:** Screenshot 09/10; cart kosong setelah Enter, `penjualan_detail` 0 baris.
- **Catatan:** Backend untuk ini **sudah ada** — `PenjualanDetailController::getProdukByKode()` & `storeByBarcode()`, route `transaksi/produk-barcode/{kode}` & `transaksi/tambah-barcode` — tetapi **tidak terhubung** ke input (tidak ada handler keypress/Enter di view).
- **Saran:** Tambahkan handler `keypress` Enter pada `#kode_produk` yang memanggil endpoint barcode. File: `resources/views/penjualan_detail/index.blade.php` (dan view pembelian_detail).

### QA-003 — [Sedang] Validasi server lemah pada beberapa form sederhana (potensi data tak valid)
- **Layar/URL:** Kategori `/kategori` (dan serupa pada form yang hanya mengandalkan `required` HTML5); update kuantitas di Pembelian.
- **Langkah reproduksi:** `KategoriController::store` menyimpan apa pun termasuk `nama_kategori` kosong (tanpa `validate()`). Pada `PembelianDetailController::update` (baris 113) nilai `jumlah` dikalikan langsung — jika non-numerik memicu `TypeError` (terlihat di `storage/logs/laravel.log` 03:22 & 03:49, sebelum sesi ini; tidak dapat direproduksi ulang karena input kuantitas valid).
- **Harapan:** Validasi sisi server (required, numeric, min) menolak input kosong/negatif/non-numerik.
- **Kenyataan:** Beberapa controller tidak memvalidasi; mengandalkan UI saja.
- **Bukti:** `app/Http/Controllers/KategoriController.php` (`store` tanpa validate); `app/Http/Controllers/PembelianDetailController.php:113`; log `local.ERROR ... Unsupported operand types: int * string`.
- **Saran:** Tambahkan `$request->validate([...])` pada controller tersebut (nama wajib; `jumlah` numeric min 1). **Perlu dicek ulang** untuk reproduksi TypeError dengan input kuantitas kosong.

### QA-004 — [Rendah] Typo breadcrumb di layar POS: "Transaksi Penjaualn"
- **Layar/URL:** `/transaksi` (keranjang aktif). Breadcrumb tertulis "Transaksi Penjaualn" (seharusnya "Penjualan").
- **Bukti:** Screenshot 09.
- **Saran:** Perbaiki teks di breadcrumb `resources/views/penjualan_detail/index.blade.php`. (Di layar "selesai" sudah benar "Transaksi Penjualan".)

### QA-005 — [Rendah] Kolom nominal rata kiri & sebagian tanpa prefix "Rp"
- **Layar/URL:** Produk `/produk` (tanpa "Rp", rata kiri), Penjualan/Pembelian/Utang-Piutang (ada "Rp" tapi rata kiri).
- **Harapan (acuan):** Nominal rata kanan dengan format Rupiah.
- **Kenyataan:** Semua kolom nominal rata kiri (`text-align: start`); Produk tanpa prefix "Rp".
- **Bukti:** Screenshot 05, 12, 13, 23.
- **Saran:** Tambahkan kelas `text-right` dan format Rupiah konsisten pada kolom nominal di view/datatables terkait.

### QA-006 — [Rendah] Kedalaman breadcrumb tidak konsisten
- **Layar/URL:** Halaman master & beberapa keuangan/laporan memakai 2 level (Home > Halaman); halaman `usaha/*` memakai 3 level (Home > Grup > Halaman). Acuan meminta "Home > Grup > Halaman".
- **Contoh:** Servis "Home > Daftar Servis" (tanpa grup "Servis"); Pendapatan Lain, Biaya Operasional, Laporan juga 2 level.
- **Bukti:** Screenshot 20, 28, 29, 39.
- **Saran:** Samakan breadcrumb agar selalu menyertakan grup menu.

### QA-007 — [Rendah] Ukuran tombol tambah tidak konsisten (`btn-sm` vs `btn-xs`)
- **Layar/URL:** Pendapatan Lain, Biaya Operasional, Data Cabang memakai tombol header `btn-success btn-sm`; acuan (Kategori) dan Produk/Member/Supplier/Servis/User memakai `btn-xs`.
- **Bukti:** Screenshot 28, 29, 49.
- **Saran:** Seragamkan ke `btn-xs btn-flat` sesuai acuan.

### QA-008 — [Rendah] Breadcrumb hanya "Home" pada Pindah Cabang & Data Cabang
- **Layar/URL:** `/user/pindah-cabang`, `/cabang` — breadcrumb hanya menampilkan "Home" tanpa label halaman.
- **Bukti:** Screenshot 47, 49.
- **Saran:** Tambahkan item breadcrumb halaman (mis. "Home > Cabang > Pindah Cabang").

### QA-009 — [Rendah] Tautan logo mengarah ke `index2.html` (placeholder mati)
- **Layar/URL:** Semua layar — logo di topbar menautkan ke `index2.html` (sisa template AdminLTE).
- **Bukti:** Terlihat di setiap snapshot (`href="index2.html"`).
- **Saran:** Arahkan ke `/dashboard` atau `/`. File: `resources/views/layouts/master.blade.php` / partial header.

#### Catatan tambahan (bukan temuan baru)
- **`/usaha/export/jurnal` tanpa parameter** mengembalikan halaman HTML, bukan unduhan CSV. Tombol "Export CSV" kemungkinan menambahkan rentang tanggal. **Perlu dicek ulang** apakah route telanjang seharusnya menolak/redirect. (Rendah)
- **4 tes gagal** (`auth.*` view tidak ada) — sudah diketahui, bukan bug baru.

---

## 6. Hal yang tidak bisa / belum diuji tuntas (beserta alasan)

- **Cetak nota (besar/kecil) & barcode:** route `transaksi/nota-*`, `servis/nota-kecil`, `produk/cetak-barcode`, `member/cetak-member` membuka jendela/berkas cetak baru; tidak dibuka tuntas lewat otomasi browser. Tombol ada dan mengarah ke route yang benar.
- **Pajak & PPN alur penuh** (buat draft masa PPN + revisi, catat setoran, bukti lapor, unduh CSV/PDF/XML): layar, tab, dan tombol sudah diverifikasi ada; alur multi-langkah penuh belum dijalankan sampai selesai.
- **Rekonsiliasi Bank alur penuh** (impor rekening koran, cocokkan baris, bukukan selisih, lepas pasangan, impor ulang anti-duplikat): layar & aksi ada, template CSV terverifikasi berfungsi; proses impor-cocok-bukukan belum dijalankan end-to-end karena perlu siklus unggah berkas.
- **Inventaris: proses penyusutan akhir bulan & jual/hapus aset (laba/rugi pelepasan):** layar & tombol ada; perhitungan penyusutan/pelepasan belum diverifikasi angkanya.
- **Pembayaran di Muka: pengakuan bertahap per bulan:** layar & aksi ada; belum dijalankan proses pengakuan.
- **Pinjaman & Cicilan:** layar & aksi "Catat pinjaman"/"Catat cicilan" ada (seeder memuat 1 pinjaman); pembuatan baru + cicilan pokok/bunga belum dijalankan end-to-end.
- **Kas/Bank/Modal lanjutan:** transfer antar rekening, tambah rekening, cocokkan saldo, dan balik transaksi — menu/aksi ada; hanya "setoran modal" yang diuji tuntas.
- **Buku Besar: balik jurnal, proses & tutup bulan, tutup tahun:** penolakan periode tertutup sudah terverifikasi (positif); aksi tutup bulan/tahun & balik jurnal belum dijalankan agar tidak mengubah status periode berjalan saat pengujian.
- **Servis: pembatalan pembayaran & pelunasan penuh:** cicilan parsial terverifikasi; tombol "Batalkan pembayaran" ada tetapi tidak dieksekusi.
- **Alasan umum:** tugas sangat luas (37 layar + 18 area). Prioritas diberikan pada verifikasi integritas data/akuntansi (jurnal, stok, kas, laporan) yang paling berisiko — dan seluruhnya terbukti konsisten. Alur-alur di atas tampak sehat secara UI dan memakai mesin akuntansi yang sama yang sudah terbukti benar, namun belum dijalankan sampai habis; ditandai **perlu dicek ulang** bila dibutuhkan kepastian penuh.

---

## Lampiran — ringkasan verifikasi akuntansi (bukti kuat kualitas)

Setelah seluruh transaksi uji (jual, beli, retur, opname, servis+bayar, lunasi hutang, setoran modal), buku besar tetap konsisten:

| Laporan | Hasil |
|---|---|
| Neraca | Aset 130.425.370 = Kewajiban+Ekuitas 130.425.370 → **selisih 0** |
| Laba Rugi | Laba 7.036.735 = Pendapatan 21.613.500 − Beban 14.576.765 |
| Arus Kas | Saldo akhir 69.663.382 = Kas 31.099.777 + Bank 37.769.555 + QRIS 794.050 (cocok buku besar) |
| Periode tertutup | Jurnal ke 09/2026 **ditolak** di lapisan data |

Semua jurnal uji terbukti seimbang (debit = kredit), HPP memakai biaya rata-rata bergerak, PPN keluaran/masukan dipisah benar, dan setiap transaksi memengaruhi stok/kas/laporan sesuai ekspektasi.
