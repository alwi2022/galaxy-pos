# Fitur Baru: Servis Pakai Sparepart + Cetak Faktur Servis

**Tanggal:** 23 Oktober 2026
**Referensi:** aplikasi lama MITRA3/ARENA (`ARENA.PRG` proc `pil13`/`takservis`) — screenshot "SERVICE COMPUTER" dengan A. SERVICE + B. SPAREPARTS.

## Ringkasan

Sebelumnya servis hanya punya satu biaya jasa dan tidak bisa memakai barang dari stok, serta tidak ada cetak faktur servis yang merinci jasa + sparepart. Sekarang:

1. **Servis bisa memakai sparepart yang diambil dari stok barang.** Saat sparepart ditambahkan, stok produk berkurang dan tercatat di Kartu Stok.
2. **Cetak Faktur Servis** menampilkan rincian seperti MITRA: A. Service (jasa) + Sub Total Service, B. Spareparts + Sub Total Sparepart, lalu TOTAL, Terbayar, Sisa, dan terbilang.

## Cara pakai

1. Buat servis seperti biasa (menu Servis > Servis Komputer > Tambah). Isi jasa di kolom **Biaya**.
2. Pada baris servis (status masih **diproses**), klik tombol kuning **ikon kunci (wrench)** untuk membuka **Sparepart Servis**.
3. Klik cari produk, pilih barang, isi qty, klik **Tambah Sparepart**. Stok barang langsung berkurang. Ringkasan menampilkan Sub Total Jasa + Sub Total Sparepart + Total Tagihan.
4. Hapus sparepart (ikon merah) bila salah — stok barang dikembalikan.
5. Setelah selesai, ubah status servis ke **Selesai** → tagihan dibuat otomatis (jasa + sparepart). Bayar lewat menu **Pembayaran Servis**.
6. Cetak faktur lewat tombol **print** pada baris servis.

> Catatan: sparepart hanya bisa diubah selama servis masih **diproses**. Setelah **selesai/diambil**, sparepart terkunci agar tagihan tidak berubah.

## Logika akuntansi (ikut pola penjualan yang sudah ada)

- **Saat sparepart ditambahkan** (barang keluar gudang): `Dr Harga Pokok Penjualan (5101) / Cr Persediaan Barang (1301)` sebesar harga pokok rata-rata bergerak. Kartu stok mencatat "Pemakaian sparepart servis <kode>".
- **Saat servis Selesai** (tagihan dibuat): `Dr Piutang Servis (1203)` sebesar total; `Cr Pendapatan Servis (4102)` untuk jasa; `Cr Penjualan Barang (4101)` untuk sparepart.
- **Hapus sparepart**: stok dikembalikan dan jurnal HPP dibalik (REV-...).
- Pembayaran servis direkonsiliasi terhadap total = jasa + sparepart.

## Verifikasi (data uji)

Servis SRV-OOXS0D: jasa PASANG LCD 100.000 + sparepart Keyboard USB (DEMO-002) 1×95.000.

| Cek | Hasil |
|---|---|
| Stok DEMO-002 saat sparepart ditambah | 82 → 81 (berkurang 1) |
| Jurnal HPP sparepart | Dr HPP 59.144 / Cr Persediaan 59.144 |
| Kartu stok | tercatat "Pemakaian sparepart servis SRV-OOXS0D -1 saldo 81" |
| Tagihan saat Selesai | 195.000 (jasa 100.000 + sparepart 95.000) |
| Jurnal faktur | Dr Piutang 195.000 / Cr Pendapatan Servis 100.000 / Cr Penjualan Barang 95.000 |
| Hapus sparepart (uji) | stok kembali 79→81, jurnal HPP dibalik (REV) |
| Guard | tambah sparepart saat status selesai ditolak ("hanya saat diproses") |
| Neraca | tetap seimbang, selisih 0 |
| Faktur servis | menampilkan A.Service + Sub Total Service, B.Spareparts + Sub Total Sparepart, Total, terbilang |
| `php artisan test` | 70 lulus, hanya 4 `auth.*` yang gagal (sudah diketahui) |

Screenshot: `docs/qa/screenshots/servis-sparepart/`.

## File yang diubah/ditambah

**Baru:**
- `database/migrations/2026_10_23_000001_create_servis_sparepart_table.php` — tabel `servis_sparepart` + kolom `servis.sparepart_total`
- `app/Models/ServisSparepart.php`
- `app/Services/ServisSparepartService.php` — tambah/hapus sparepart (stok + jurnal HPP)
- `resources/views/servis/sparepart.blade.php` — modal sparepart + pemilih produk

**Diubah:**
- `app/Models/Servis.php` — relasi spareparts, accessor tagihan, fillable
- `app/Models/Concerns/PostsServiceInvoice.php` — tagihan = jasa + sparepart, jurnal dipisah 4102/4101
- `app/Services/ServicePaymentService.php` — sisa dihitung dari jasa + sparepart
- `app/Http/Controllers/ServisController.php` — endpoint sparepart (data/store/destroy), pemilih produk, nota eager-load
- `resources/views/servis/index.blade.php` — tombol Sparepart + JS pengelola
- `resources/views/servis/nota.blade.php` — faktur servis bergaya MITRA
- `routes/web.php` — rute sparepart servis
