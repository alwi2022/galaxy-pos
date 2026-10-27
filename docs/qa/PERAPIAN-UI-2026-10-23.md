# Perapian UI Galaxy POS — Konsistensi Desain Lama

Tanggal: 23 Oktober 2026
Lingkup: Halaman login, halaman setup awal desktop, dan layar loading aplikasi desktop Windows.
Tujuan: Menyelaraskan tampilan dengan desain lama (AdminLTE-2 / Bootstrap 3) tanpa membuat desain baru, tanpa mengubah logika auth/transaksi/DB, tanpa CSS global, dan memastikan seluruh aset tetap lokal (offline).

---

## 1. Halaman Login (`resources/views/auth/login.blade.php`)

### Masalah sebelumnya
- Form login memakai CSS custom inline (~250 baris) dengan gaya di luar AdminLTE: tombol gradient ungu, `border-radius` besar, teks uppercase, tipografi custom.
- Email developer `imambahrialwi21@gmail.com` tampil permanen di bawah tombol login (tidak pantas di layar produksi).
- Ada link "Clear" non-standar beserta skrip `clearForm()`.
- Judul besar "POS SERVIS Galaxy Computer" tanpa kerangka `login-logo` AdminLTE.

### Perbaikan
- Form kanan ditulis ulang memakai komponen AdminLTE standar yang sudah dimuat di `layouts/auth.blade.php`:
  - `login-box` + `login-box-body` (kotak putih).
  - `form-group has-feedback` dengan ikon FontAwesome (`glyphicon-envelope`, `glyphicon-lock`).
  - Checkbox iCheck "Ingat saya" (otomatis dapat gaya `icheck` dari skrip layout).
  - Tombol `btn btn-primary btn-block btn-flat`.
- Judul memakai `login-logo` yang menampilkan nama toko dari `$setting->nama_perusahaan`; jika kosong (instalasi baru) jatuh ke teks default **"Galaxy Computer"**.
- Email developer dan link "Clear" dihapus beserta skripnya.
- Layout dua kolom + ilustrasi tetap dipertahankan sesuai permintaan (opsi B), hanya form kanan yang dirapikan. Warna tombol di-tint `#605CA8` agar selaras dengan ilustrasi, strukturnya murni kelas AdminLTE.

### Catatan regresi yang ditemukan & diperbaiki
- Versi awal perbaikan sempat memakai `$setting->nama` (kolom tidak ada; kolom sebenarnya `nama_perusahaan`) dan membaca properti pada `$setting` null tanpa penjaga. Ini menyebabkan **HTTP 500 pada `/login` saat tabel `setting` kosong** (skenario first-run desktop). Diperbaiki dengan blok `@if(!empty($setting->nama_perusahaan))` + fallback. Test `AuthenticationTest::test_login_screen_can_be_rendered` kembali hijau.

---

## 2. Setup Awal Desktop (`resources/views/desktop/setup.blade.php`)

### Status
- Sudah memakai komponen AdminLTE (`login-box`), konsisten. Dirapikan agar struktur form lebih bersih dan konsisten dengan login.

### Perbaikan
- Markup form dirapikan (indentasi, satu field per baris) tetap memakai `form-group` + `form-control` AdminLTE.
- Daftar error divalidasi tampil via `alert alert-danger`.
- **Pencegahan double-submit**: skrip menonaktifkan tombol submit dan mengubah labelnya menjadi "Menyimpan..." saat form dikirim.
- Input non-password dipertahankan via `old()` jika validasi gagal; password sengaja tidak dipertahankan (perilaku bawaan Laravel, sesuai praktik keamanan).
- Logika controller (`DesktopSetupController`) tidak diubah: tetap transaksional, membuat cabang "Pusat" + setting + user admin level 1, lalu login otomatis.

---

## 3. Layar Loading Desktop (`desktop/loading.html`)

### Masalah sebelumnya
- Sangat polos: hanya teks "Galaxy POS" + progress bar indeterminate satu baris.
- Tanpa logo/branding.

### Perbaikan
- Tetap memakai skema warna AdminLTE (`#ecf0f5` background, `#3c8dbc` aksen) yang sama dengan `backgroundColor` window Electron.
- Ditambahkan logo mark berupa SVG inline (base64) — tanpa dependensi file eksternal, aman untuk CSP ketat.
- Judul "**Galaxy** POS", pesan status jujur ("Menyiapkan data toko. Mohon tunggu..."), dan indikator progres **indeterminate** (animasi bar bergerak) — tidak memalsukan persentase, karena proses startup nyata di `main.cjs` tidak mengirim progres granular.
- CSP disesuaikan menjadi `default-src 'none'; style-src 'unsafe-inline'; img-src data:` agar logo inline bisa tampil tanpa membuka sumber eksternal.

### Pesan kegagalan (tidak diubah, sudah memadai)
- `main.cjs` sudah menampilkan dialog error native yang jelas saat gagal start (`'Galaxy POS belum dapat dibuka'`) dan saat server lokal berhenti (`'Server lokal berhenti. Buka kembali aplikasi...'`). Logika tidak disentuh.
- Ukuran window sudah mengikuti `width:1366,height:900,minWidth:900,minHeight:650` dengan `backgroundColor:'#ecf0f5'`. Tidak diubah.

---

## Batasan yang dihormati
- Tidak membuat desain baru; hanya reuse komponen AdminLTE yang sudah ada.
- Tidak mengubah logika auth, transaksi, keuangan, atau skema DB.
- Tidak menyentuh `vendor/` maupun `node_modules/`.
- Tidak mengganti framework/tema/ikon.
- Tidak menambah CSS global; gaya di-scope ke `.login-right` dan halaman terkait.
- Semua aset lokal (ilustrasi, font Source Sans Pro, AdminLTE, logo inline) — tidak ada permintaan jaringan eksternal.

## Verifikasi
- `php artisan test`: 86 passed. Sisa 4 kegagalan adalah isu lama yang tidak terkait (route Jetstream `/register`, `/forgot-password`, `/reset-password`, `/user/confirm-password` tidak memiliki view di aplikasi POS ini).
- Desktop runtime tests (`node --test`): 2 passed.
- Login diuji end-to-end via browser: render 200, submit `admin@gmail.com` berhasil redirect ke `/dashboard`.
- Installer Windows dibangun ulang via `npm run dist` (NSIS x64) agar paket memuat view yang sudah diperbarui.

## Berkas yang diubah
- `resources/views/auth/login.blade.php`
- `resources/views/desktop/setup.blade.php`
- `desktop/loading.html`
