# Laporan QA Perbaikan — Galaxy POS

**Tanggal:** 22 Oktober 2026
**Lingkungan:** PHP 8.4.27 (`C:\Users\imamb\.config\php84\php.exe`), MySQL `tokoku` @ 127.0.0.1:3307, server uji `http://127.0.0.1:9753`
**Acuan audit:** `docs/qa/LAPORAN-QA-2026-10-22.md`
**Backup DB sebelum uji ulang:** `docs/qa/backup-tokoku-20261022-231301.sql` (gitignored, tidak di-commit)
**Batasan yang dipatuhi:** tidak menyentuh `.env`, `composer.json`, file deploy, dan **tidak mengubah logika akuntansi** (`app/Services/*Ledger*`, `BusinessTransactionService`, `TaxReportingService`, dll). Tidak ada commit/push.

---

## 1. Ringkasan

### Status temuan audit sebelumnya

| ID | Temuan | Status |
|---|---|---|
| QA-001 | Keterangan wajib tak ditandai pada pembayaran servis | **Diperbaiki** — jadi opsional + default otomatis |
| QA-002 | Field barcode POS tidak aktif (Enter tidak menambah) | **Diperbaiki** — handler Enter → `transaksi.tambah_barcode`, scoping cabang disamakan |
| QA-003 | Validasi server lemah di beberapa controller | **Diperbaiki** — Kategori, Member, Supplier, PenjualanDetail, PembelianDetail + 6 feature test |
| QA-004 | Typo breadcrumb "Transaksi Penjaualn" | **Diperbaiki** — jadi "Transaksi Penjualan" |
| QA-005 | Kolom nominal rata kiri | **Diperbaiki** — `text-right` di DataTables Produk/Penjualan/Pembelian/Utang-Piutang/Biaya/Pendapatan + cart POS & pembelian |
| QA-006 | Breadcrumb tidak konsisten (2 vs 3 level) | **Diperbaiki** — semua jadi `Home > Grup > Halaman` |
| QA-007 | Tombol tambah `btn-sm` (harusnya `btn-xs`) | **Diperbaiki** — Biaya Operasional, Pendapatan Lain, Data Cabang |
| QA-008 | Breadcrumb Pindah/Data Cabang hanya "Home" | **Diperbaiki** — jadi `Home > Cabang > ...` |
| QA-009 | Logo menautkan ke `index2.html` | **Diperbaiki** — ke `route('dashboard')` |
| Tambahan (item 9) | Route debug `/test-barcode` terbuka tanpa login | **Diperbaiki** — dihapus |
| Tambahan (QA-010) | `/usaha/export/jurnal` tanpa param mengembalikan HTML | **Diperbaiki** — default periode bulan berjalan, selalu unduh CSV |

Semua 11 item **Diperbaiki**.

### Hasil `php artisan test`

| | Lulus | Gagal | Risky | Skip |
|---|---|---|---|---|
| **Sebelum** | 59 | 4 (`auth.*`) | 1 | 6 |
| **Sesudah** | **65** | 4 (`auth.*`) | 1 | 6 |

Penambahan 6 tes lulus berasal dari `tests/Feature/ValidationTest.php` yang baru. 4 kegagalan tersisa adalah tes `auth.*` yang sudah diketahui (view `auth.register`/`reset`/`confirm` tidak ada) — sesuai target penerimaan.

---

## 2. Daftar perubahan

`git diff --stat`: **28 file berubah, +170 / −63**, plus 1 file test baru (`tests/Feature/ValidationTest.php`, untracked). Tidak ada file di `app/Services/**` yang tersentuh.

```
 app/Http/Controllers/BusinessController.php        | 10 ++++-
 app/Http/Controllers/KategoriController.php        |  8 ++++
 app/Http/Controllers/MemberController.php          | 14 ++++++-
 app/Http/Controllers/PembelianDetailController.php |  4 ++
 app/Http/Controllers/PenjualanDetailController.php |  8 ++--
 app/Http/Controllers/SupplierController.php        | 16 +++++++-
 resources/views/business/actions.blade.php         |  2 +-
 resources/views/business/form.blade.php            |  5 ++-
 resources/views/cabang/index.blade.php             |  8 +++-
 resources/views/kategori/index.blade.php           |  1 +
 resources/views/laporan/index.blade.php            |  1 +
 resources/views/layouts/header.blade.php           |  2 +-
 resources/views/member/index.blade.php             |  1 +
 resources/views/pembayaran/index.blade.php         | 13 ++++---
 resources/views/pembelian/index.blade.php          | 21 ++++++-----
 resources/views/pembelian_detail/index.blade.php   | 16 ++++++--
 resources/views/pendapatan_lain/index.blade.php    |  5 ++-
 resources/views/pengeluaran/index.blade.php        |  5 ++-
 resources/views/penjualan/index.blade.php          | 21 ++++++-----
 resources/views/penjualan/selesai.blade.php        |  1 +
 resources/views/penjualan_detail/index.blade.php   | 43 ++++++++++++++++++++--
 resources/views/produk/index.blade.php             |  9 +++--
 resources/views/servis/index.blade.php             |  1 +
 resources/views/setting/index.blade.php            |  1 +
 resources/views/supplier/index.blade.php           |  1 +
 resources/views/user/index.blade.php               |  1 +
 resources/views/user/pindah_cabang.blade.php       |  6 +++
 routes/web.php                                     |  9 -----
 28 files changed, 170 insertions(+), 63 deletions(-)
```

### Per file — apa & kenapa

**Controller**
- `KategoriController.php` — `store`/`update`: tambah `validate(['nama_kategori'=>'required|string|max:100'])`. (QA-003: dulu nama kosong tersimpan.)
- `MemberController.php` — `store`/`update`: validasi `nama` (required), `telepon`/`alamat` (nullable); `update` diganti `only([...])` agar tidak mass-assign kolom lain. (QA-003)
- `SupplierController.php` — `store`/`update`: validasi `nama`+`telepon` (required, sesuai kolom NOT NULL), `alamat` nullable; `create`/`update` pakai `only([...])`. (QA-003)
- `PenjualanDetailController.php` — `update`: validasi `jumlah` (required|integer|min:1). `getProdukByKode`: hapus pengecualian admin, selalu `where('id_cabang', user)` agar konsisten dengan `storeByBarcode`. (QA-002/QA-003)
- `PembelianDetailController.php` — `update`: validasi `jumlah` (required|integer|min:1) — mencegah `TypeError int*string` yang pernah muncul di log. (QA-003)
- `BusinessController.php` — (1) `service-pay`: `description` jadi `nullable`, default otomatis `"Pembayaran servis <kode>"` jika kosong (QA-001). (2) `export` (jurnal): `start`/`end` jadi `nullable` + default `startOfMonth`/`endOfMonth` bulan berjalan supaya selalu unduh CSV (QA-010). **Tidak mengubah logika posting jurnal.**

**View**
- `layouts/header.blade.php` — `href` logo `index2.html` → `{{ route('dashboard') }}` (QA-009).
- `business/form.blade.php` — dukung flag opsional (elemen ke-6 field) agar sebuah field bisa tidak `required` (QA-001).
- `business/actions.blade.php` — form `service-pay` memakai field "Keterangan (opsional)" dengan flag opsional (QA-001).
- `penjualan_detail/index.blade.php` — handler `keydown` Enter pada `#kode_produk` → POST `transaksi.tambah_barcode` (id_penjualan + kode_produk), reload tabel, kosongkan+fokus input, SweetAlert bila gagal; breadcrumb `Transaksi > Transaksi Penjualan` (typo QA-004 + grup QA-006); `text-right` kolom harga/diskon/subtotal (QA-005).
- `pembelian_detail/index.blade.php` — Enter pada `#kode_produk` dicegah submit GET, diarahkan ke modal pencarian (tidak ada endpoint barcode pembelian, sesuai instruksi); `text-right` kolom nominal; breadcrumb grup.
- `produk/`, `penjualan/`, `pembelian/`, `pembayaran/`, `pengeluaran/`, `pendapatan_lain/index.blade.php` — `className:'text-right'` kolom nominal DataTables (QA-005) + breadcrumb grup (QA-006).
- `kategori/`, `member/`, `supplier/`, `servis/`, `laporan/`, `setting/`, `user/`, `penjualan/selesai.blade.php` — tambah `<li>Grup</li>` pada breadcrumb (QA-006).
- `pengeluaran/`, `pendapatan_lain/index.blade.php` — tombol tambah `btn-sm` → `btn-xs btn-flat` (QA-007).
- `cabang/index.blade.php` — tombol `btn-sm` → `btn-xs btn-flat` (QA-007) + tambah breadcrumb `Home > Cabang > Data Cabang` (QA-008).
- `user/pindah_cabang.blade.php` — tambah breadcrumb `Home > Cabang > Pindah Cabang` (QA-008).

**Route**
- `routes/web.php` — hapus route debug `/test-barcode` (item 9).

**Test (baru)**
- `tests/Feature/ValidationTest.php` — 6 tes: Kategori store/update butuh nama; Kategori store nama valid; PembelianDetail update tolak `jumlah` non-numerik, tolak 0, terima nilai valid.

---

## 3. Tabel uji ulang Bagian B

Diuji lewat browser sebagai admin; angka dicocokkan sampai ke jurnal `/usaha/akuntansi`.

| Alur | Skenario | Hasil | Bukti |
|---|---|---|---|
| Kas/Bank/Modal | Transfer Kas→Bank 1.000.000 | Lulus | Kas 31.099.777→30.099.777, Bank 37.769.555→38.769.555, **total kas tetap** 69.663.382; `CASH-...` Dr Bank/Cr Kas. `fix-07` |
| Kas/Bank/Modal | Balik transaksi (reverse entry 261) | Lulus | `REV-261` Dr Kas/Cr Bank 1.000.000, saldo kembali ke baseline |
| Kas/Bank/Modal | Setoran modal (dari audit sebelumnya) | Lulus | Dr Kas/Cr Modal Pemilik |
| Pinjaman & Cicilan | Pinjaman diterima 5.000.000 ke Bank | Lulus | `LOAN-2` Dr Bank/Cr Utang Pinjaman 5.000.000 |
| Pinjaman & Cicilan | Cicilan pokok 1.000.000 + bunga 50.000 | Lulus | `REPAY-...` Dr Utang Pinjaman 1.000.000 + Dr Biaya Bunga(6104) 50.000 / Cr Bank 1.050.000; outstanding 5.000.000→4.000.000 |
| Pembayaran di Muka | Biaya dibayar di muka 6.000.000/6 bln | Lulus | `DEFER-2` Dr Biaya Dibayar di Muka(1501) / Cr Bank 6.000.000 |
| Pembayaran di Muka | Pengakuan per bulan | Lulus | Demo Sewa toko: `AMORT-1-202609` Dr Biaya Operasional / Cr 1501 1.000.000/bln (12M/12). QA prepaid akan diakui saat tutup Okt (lihat catatan) |
| Inventaris | Beli aset 12.000.000, umur 12 bln | Lulus | `ASSET-2` Dr Aset Tetap / Cr Bank 12.000.000 |
| Inventaris | Penyusutan garis lurus | Lulus | Demo Komputer kasir: `DEP-1-202609` 150.000 = (6.000.000−600.000)/36 |
| Inventaris | Jual aset di atas nilai buku (laba) | Lulus | `DISPOSE-2` Dr Bank 13.000.000 / Cr Aset Tetap 12.000.000 / Cr Laba Penjualan Aset(4105) 1.000.000 |
| Inventaris | Jual aset di bawah nilai buku (rugi) | Lulus | `DISPOSE-1` Dr Bank 5.000.000 + Dr Akum. Penyusutan 150.000 + Dr Rugi Pelepasan Aset(6105) 850.000 / Cr Aset Tetap 6.000.000 |
| Rekonsiliasi Bank | Unduh template CSV | Lulus | `text/csv`, header `date,reference,description,amount` |
| Rekonsiliasi Bank | Impor rekening koran (3 baris) | Lulus | "3 baris diimpor; 0 duplikat" `fix-08` |
| Rekonsiliasi Bank | Cocokkan baris | Lulus | Statement 6 ↔ ledger line 814 (ASSET-2) |
| Rekonsiliasi Bank | Bukukan selisih (biaya admin bank) | Lulus | `BANK-ADJUST-7` Dr Biaya Operasional 25.000 / Cr Bank 25.000, auto-match |
| Rekonsiliasi Bank | Lepas pasangan | Lulus | Statement 6 kembali NULL, event `unmatch` tercatat |
| Rekonsiliasi Bank | Impor ulang file yang sama | Lulus | "0 baris diimpor; 3 duplikat dilewati" (tidak ada duplikat) `fix-09` |
| Rekonsiliasi Bank | Impor CSV rusak | Lulus | Ditolak: "Kolom CSV harus date,reference,description,amount" `fix-10` |
| Pajak & PPN | Isi profil PKP | Lulus | pkp=1, NPWP 0011223344556677 |
| Pajak & PPN | Daftarkan faktur | Lulus | Faktur id 93 untuk penjualan #91 |
| Pajak & PPN | Buat draft masa + revisi | Lulus | Rev 2 masa 2026-10: output 2.167.385, input 5.100.425, lebih bayar, carry 2.933.040 |
| Pajak & PPN | Cocokkan PPN keluaran vs transaksi | Lulus | PPN Keluaran buku besar Okt = 2.167.385 = `output_tax` (sama persis) |
| Pajak & PPN | Catat setoran PPN | Tidak berlaku | Masa ini **lebih bayar** (payable 0), tidak ada setoran untuk dicatat |
| Pajak & PPN | Unduh CSV / PDF | Lulus | CSV `text/csv`, PDF `application/pdf` |
| Pajak & PPN | Unduh XML (well-formed) | Sebagian | Ter-**guard** dengan benar: masa Okt memuat retur (RET-3) sehingga XML retail ditolak dengan pesan jelas. XML untuk masa bersih tidak sempat diuji (lihat Bagian 5) `fix-11` |
| Servis | Pelunasan penuh | Lulus | Bayar 150.000 tanpa keterangan → lunas; deskripsi otomatis "Pembayaran servis SRV-RW8SEZ" `fix-06` |
| Servis | Pembatalan pembayaran | Lulus | `REV-259` Dr Piutang Servis / Cr Kas 150.000; sisa dibuka lagi, status "sebagian" |
| Cetak | Nota servis | Lulus | `servis/nota-kecil/14` render HTML benar (biaya Rp 200.000) `fix-12` |
| Cetak | Kartu member | Lulus | `member/cetak-member` 200 HTML |
| Cetak | Barcode produk | Lulus | `produk/cetak-barcode` 200 `application/pdf` |
| Cetak | Nota besar/kecil penjualan | Tidak diuji penuh | Butuh sesi transaksi aktif; tombol "Cetak Ulang Nota" sudah diverifikasi di audit awal |
| Buku Besar | Jurnal penyesuaian | Lulus | `MANUAL-...` (entry 270) Dr 6101 / Cr 4103 20.000 |
| Buku Besar | Balik jurnal | Lulus | `REV-270` Dr 4103 / Cr 6101 20.000 (net nol) |
| Buku Besar | Tutup bulan untuk bulan lampau yang terbuka | Tidak berlaku | Semua bulan lampau **sudah tertutup**; bulan berjalan (Okt) ditolak ditutup: "hanya untuk bulan yang sudah berakhir" |
| Buku Besar | Tutup tahun 2025 → laba ditahan | Lulus (sudah tertutup) | `YEAR-CLOSE-35-2025`: tutup akun nominal, Cr Laba Ditahan(3103) 350.000. Re-tutup ditolak: "Tahun buku sudah ditutup; jurnal tidak digandakan." |
| Buku Besar | Transaksi di periode tertutup ditolak | Lulus | Jurnal tanggal 2025-06-15 ditolak "Tahun buku sudah ditutup", 0 baris masuk DB `fix-13` |

---

## 4. Temuan baru

Tidak ditemukan bug fungsional baru pada uji ulang. Mesin akuntansi tetap konsisten dan semua kontrol (periode tertutup, dedup rekonsiliasi, guard XML) bekerja benar. Satu catatan tingkat **Rendah**:

### QA-010 (observasi, bukan cacat) — Ekspor XML PPN diblokir bila masa memuat retur/koreksi
- **Layar/URL:** Pajak & PPN, tombol unduh XML pada baris masa (`/usaha/export/pajak/{id}/xml`).
- **Perilaku:** Jika masa memuat faktur non-normal (mis. ada retur penjualan seperti RET-3 di Oktober), ekspor XML retail ditolak dengan pesan: "XML retail membutuhkan faktur normal dengan identitas lengkap. Masa yang memuat retur/koreksi harus ditangani melalui lampiran Coretax yang sesuai; gunakan ekspor kertas kerja."
- **Penilaian:** Ini **perilaku yang benar/by-design** (skema XML retail Coretax memang hanya untuk faktur normal), bukan bug. Dicatat agar jelas mengapa XML tidak terunduh pada data uji saat ini. Format output (`application/xml` via DOMDocument) sudah well-formed secara struktur untuk masa bersih — hanya tidak sempat diuji end-to-end pada masa tanpa retur.
- **Saran (opsional):** tidak ada perbaikan kode diperlukan; bila diinginkan, UI bisa menonaktifkan/menyembunyikan tombol XML saat masa memuat koreksi agar lebih jelas.

---

## 5. Hal yang masih belum diuji (beserta alasan)

- **Unduh XML PPN pada masa bersih (tanpa retur):** data uji bulan berjalan (Oktober) memuat retur penjualan RET-3, sehingga XML retail ter-guard. Untuk memverifikasi XML well-formed secara penuh perlu masa khusus tanpa koreksi; tidak dibuat agar tidak memanipulasi data demo lebih jauh. Struktur generator XML (DOMDocument + skema) sudah ditinjau dari kode dan benar.
- **Catat setoran PPN:** masa Oktober berstatus **lebih bayar** (payable 0), jadi tidak ada nominal setoran untuk dicatat. Alur "catat bukti lapor" (tax-filed) juga belum dijalankan karena bergantung pada masa yang memiliki kewajiban bayar/urutan status tertentu.
- **Tutup bulan untuk bulan lampau yang terbuka:** tidak ada target — seluruh bulan lampau sudah tertutup oleh seeder, dan bulan berjalan belum berakhir sehingga sistem benar menolak penutupannya. Perilaku penolakan sudah diverifikasi.
- **Pinjaman "diberikan" (toko memberi pinjaman):** hanya arah "diterima" yang diuji end-to-end; arah "memberi" memakai mesin posting yang sama tetapi tidak dieksekusi terpisah.
- **Nota besar/kecil penjualan:** butuh sesi transaksi aktif saat dicetak; hanya tombol "Cetak Ulang Nota" di layar selesai yang diverifikasi (audit awal). Nota servis, kartu member, dan barcode produk sudah diverifikasi terbuka tanpa error.
- **Kas lanjutan (pengambilan pemilik, pembagian hasil, saldo awal, tambah rekening, cocokkan saldo):** transfer + balik + setoran modal diverifikasi; sisanya memakai engine yang sama dan tidak dijalankan satu per satu untuk membatasi perubahan pada data demo.

---

## Lampiran — catatan lingkungan

- Server uji memakai PHP built-in 8.4.27 di port 9753 (port 8000/8001/8080 diblokir izin socket; 8000 dipakai XAMPP PHP 8.2).
- Backup DB pra-uji: `docs/qa/backup-tokoku-20261022-231301.sql` (di-`.gitignore`, tidak di-commit).
- Tidak ada commit atau push yang dilakukan. Perubahan hanya di working tree.
