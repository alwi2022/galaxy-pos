<p align="center">
    <img src="public/img/logo.png" width="120" alt="Galaxy POS">
</p>

<h1 align="center">Galaxy POS</h1>

<p align="center">
    Aplikasi Point of Sales untuk toko komputer: penjualan, kasir, servis, keuangan, dan akuntansi dalam satu sistem.
</p>

---

## Tentang Aplikasi

**Galaxy POS** adalah aplikasi Point of Sales (POS) untuk mengelola transaksi penjualan, pembelian, layanan servis, serta pembukuan keuangan toko. Dibangun dengan **Laravel 8** (berjalan di **PHP 8.4**), mendukung banyak cabang, banyak peran pengguna, dan tersedia dalam dua mode: **aplikasi web** dan **aplikasi desktop Windows offline**.

Project ini dikembangkan dari [tutorial POS nursandiid](https://github.com/nursandiid/tutorial-membuat-aplikasi-point-of-sales) dengan penambahan modul keuangan/akuntansi lengkap, servis dengan sparepart, import/export data, perbaikan bug, dan pembaruan UI.

- **Demo online:** [https://point-of-sale-a0143e453f08.herokuapp.com/login](https://point-of-sale-a0143e453f08.herokuapp.com/login)
- **Dokumentasi keuangan & operasional:** [docs/OPERASIONAL-KEUANGAN.md](docs/OPERASIONAL-KEUANGAN.md)

---

## Akun Demo

| Peran | Email | Password |
| --- | --- | --- |
| Administrator | `admin@gmail.com` | `123` |
| Kasir | `kasir1@gmail.com` | `123` |
| Teknisi | `teknisi1@gmail.com` | `123` |

---

## Fitur Utama

### Master Data
- **Kategori, Produk, Supplier, Member** dengan data terpisah per cabang.
- **Produk**: multiple delete, cetak barcode, stok awal otomatis masuk jurnal persediaan.
- **Member**: cetak kartu member.
- **Import & Export data master** (Excel/CSV) untuk Produk, Supplier, Kategori, Member, dan Rekening Bank — lengkap dengan unduh template, validasi per baris, tolak duplikat, dan impor massal atomik per cabang.

### Transaksi
- **Penjualan** (POS kasir dengan dukungan barcode), **Pembelian**, **Pengeluaran**, **Pendapatan Lain**.
- **Pembayaran hutang/piutang** bertahap dengan riwayat pembayaran.
- Metode bayar: tunai (Kas Toko), transfer (Bank), dan QRIS — terhubung otomatis ke jurnal.

### Servis
- Tambah/edit/hapus data servis, update status (proses, selesai, diambil), dan tracking status real-time.
- **Pemakaian sparepart dari stok** pada servis — stok berkurang otomatis, HPP tercatat.
- **Cetak faktur servis** (gaya nota lama) berisi jasa + sparepart.
- **Notifikasi WhatsApp** ke pelanggan berisi status servis & link tracking.

### Keuangan & Akuntansi
Menu **Operasional & Keuangan** (khusus admin, mengikuti cabang pengguna):
- **Kartu Stok & Opname** — riwayat stok masuk/keluar, pemakaian internal, barang rusak/hilang, penghitungan stok fisik.
- **Retur Barang** — retur sebagian/seluruh transaksi, membedakan barang layak jual dan rusak.
- **Kas, Bank & Modal** — modal awal/tambahan, pengambilan pemilik, rekening tambahan, transfer saldo, pembayaran PPN, rekonsiliasi.
- **Pinjaman & Cicilan** — pinjaman diterima/diberikan beserta pokok, bunga, sisa, dan jatuh tempo.
- **Inventaris** — aset, penyusutan garis lurus, pelepasan aset.
- **Pembayaran di Muka** — biaya/pendapatan diakui merata beberapa bulan.
- **Akuntansi & Pajak** — jurnal, buku besar, laba rugi, neraca, arus kas, rekap PPN, ekspor CSV, dan tutup periode.

### Laporan
- Ringkasan kas, penjualan, pembelian, per barang, per kategori (periode harian/bulanan/custom).
- Ekspor **PDF** dan **Excel**.
- Laba rugi, neraca, dan arus kas pada menu Akuntansi & Pajak.

### Lainnya
- **Multi-cabang** dengan isolasi data per cabang.
- **Multi-role**: Administrator, Kasir, Teknisi.
- **Nota** besar dan kecil/thermal.
- **Pengaturan toko**: identitas, desain kartu member, diskon, PPN.
- **Dashboard** dengan grafik ChartJS.

---

## Aplikasi Desktop Windows (offline)

Tersedia versi desktop yang menjalankan Laravel + PHP yang disertakan di dalam paket, dengan **database SQLite lokal**. Tidak memerlukan XAMPP, Composer, Node.js, atau koneksi internet saat dipakai.

- Database, gambar toko, konfigurasi, dan cadangan tersimpan di `%APPDATA%\GalaxyPOS`.
- Backup/restore `.galaxypos.zip` lewat menu **Data**.
- Pengaturan awal (nama toko + akun admin) saat pertama dibuka.

Membangun installer Windows (di komputer pengembang Windows x64 dengan PHP 8.4):

```powershell
cd desktop
npm install
npm run dist
```

Hasil installer: `desktop/dist/Galaxy-POS-Setup-<versi>-x64.exe`. Detail lengkap di [desktop/README.md](desktop/README.md).

---

## Instalasi (mode web)

#### Clone Repository
```bash
git clone https://github.com/alwi2022/galaxy-pos.git
cd galaxy-pos
```

#### Install Dependency
```bash
composer install
```

#### Konfigurasi `.env`
```bash
cp .env.example .env
```
Atur database di `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pos_db
DB_USERNAME=root
DB_PASSWORD=
```

#### Generate Key, Migrate, Seed
```bash
php artisan key:generate
php artisan migrate --seed
```

#### Jalankan Server
```bash
php artisan serve
```

> Catatan: aplikasi membutuhkan **PHP 8.4** sesuai `composer.lock`.

---

## Teknologi yang Digunakan

- **Laravel 8** + **Livewire** + **Laravel Jetstream/Fortify**
- **Maatwebsite Excel** (import/export)
- **DomPDF** & **TCPDF** (cetak nota, barcode, laporan)
- **Laravel DataTables** (tabel server-side)
- **ChartJS** (dashboard)
- **AdminLTE 2 / Bootstrap 3** (antarmuka)
- **Electron** (aplikasi desktop Windows + PHP & SQLite bundled)

---

## Dokumentasi Tambahan

- [Operasional & Keuangan](docs/OPERASIONAL-KEUANGAN.md) — panduan stok, retur, kas/bank, modal, pinjaman, aset, pembayaran di muka, dan akuntansi.
- [Aplikasi Desktop Windows](desktop/README.md) — instalasi, data, cadangan, dan cara build installer.

---

## Screenshot

- **Halaman Login** — `public/screenshots/login.png`
- **Sidebar** — `public/screenshots/sidebar.png`
- **Manajemen Service** — `public/screenshots/service.png`
- **Tracking Status Servis** — `public/screenshots/tracking.png`
- **Notifikasi WhatsApp** — `public/screenshots/wa-notif.png`

---

**Demo Aplikasi:** [https://point-of-sale-a0143e453f08.herokuapp.com/login](https://point-of-sale-a0143e453f08.herokuapp.com/login)
