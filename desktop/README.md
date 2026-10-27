# Galaxy POS untuk satu komputer Windows

Versi desktop menjalankan Laravel dan PHP yang disertakan di dalam paket. Database SQLite, gambar toko, konfigurasi, dan cadangan disimpan di komputer pengguna. Aiven, XAMPP, Node.js, Composer, serta koneksi internet tidak diperlukan saat aplikasi dipakai.

## Memasang dan membuka

1. Jalankan `dist/Galaxy-POS-Setup-1.0.0-x64.exe` dan ikuti petunjuk instalasi. Installer memerlukan izin administrator untuk memasang aplikasi dan Microsoft Visual C++ Runtime yang ikut disertakan.
2. Buka ikon **Galaxy POS** di desktop atau Start Menu.
3. Pada pembukaan pertama, tunggu persiapan database lalu isi nama toko, nama administrator, email login, dan password minimal delapan karakter.
4. Tambahkan kategori, produk, supplier, dan data operasional melalui tampilan aplikasi yang sudah ada.

Installer ini membuat database baru tanpa transaksi demo dan tanpa password bawaan. Database Aiven maupun database pengembangan tidak disalin atau diubah. Paket pertama ini belum ditandatangani dengan sertifikat penerbit aplikasi.

## Data dan cadangan

Lokasi data: `%APPDATA%\GalaxyPOS`.

- `database.sqlite`: seluruh transaksi dan pengaturan toko.
- `.env`: kunci enkripsi aplikasi; harus tetap bersama database saat membuat salinan manual.
- `public` dan `storage/app/public`: gambar serta berkas yang diunggah.
- `backups`: cadangan otomatis harian sebelum aplikasi dibuka kembali.
- `storage/logs/desktop.log`: catatan persiapan dan server lokal.

Gunakan menu **Data > Buat Cadangan** untuk menyimpan file `.galaxypos.zip` ke flashdisk atau lokasi lain. Cadangan mencakup database, berkas toko, dan kunci enkripsi. Simpan cadangan dengan baik karena berisi data toko.

Untuk pemulihan, gunakan **Data > Pulihkan Cadangan**. Aplikasi meminta konfirmasi, menutup server lokal, membuat cadangan kondisi saat ini, memeriksa file yang dipilih, lalu membuka ulang aplikasi. Pemulihan menolak file yang rusak atau memiliki nama file berbahaya.

Data berada di luar folder instalasi sehingga pemasangan ulang atau pembaruan aplikasi tidak menggantinya. Menghapus atau mengganti akun Windows memerlukan pemindahan cadangan secara terpisah. Cadangan otomatis berada pada disk yang sama; salinan ke flashdisk tetap diperlukan untuk menghadapi kerusakan disk.

## Membangun dari source

Di komputer pengembang Windows x64, sediakan Node.js, dependency Composer proyek, dan PHP 8.4 sesuai `composer.lock`.

```powershell
npm run desktop:install
npm run desktop:build
```

Builder menemukan PHP melalui `PATH`. Gunakan `GALAXY_PHP_DIR` jika runtime berada di lokasi lain. Unduhan dependency, font, library tampilan, dan Visual C++ Redistributable hanya dilakukan pada tahap pembangunan. Setelah tersimpan, semua aset masuk ke installer.

`desktop/.staging` berisi backend dan PHP sebelum dipaketkan. `.env` proyek, database, sertifikat Aiven, cache konfigurasi produksi, dan storage pengguna tidak diikutkan. Runtime menolak koneksi halaman aplikasi ke origin di luar server lokal miliknya; server hanya mendengarkan `127.0.0.1` dan memeriksa token proses aplikasi.

Jangan menjalankan build sambil mengganti versi PHP yang sedang dipakai aplikasi pengujian. Jangan mengarahkan `GALAXY_DESKTOP_TEST_DATA` ke folder data toko sesungguhnya.

## Validasi 27 Oktober 2026

- Lima tes PHP khusus desktop: pembuatan admin, pencegahan pengaturan ulang akun, validasi, isolasi mode web, cadangan/pemulihan database-gambar-kunci, serta penolakan path traversal Windows.
- Dua tes Node: data dan kunci tetap tersimpan setelah dibuka ulang; environment desktop memakai SQLite dan menolak origin lain.
- Smoke test runtime bundel: migrasi dari database kosong, akun admin pertama, dashboard dengan autentikasi, tabel server-side, PDF, Excel, aset lokal, cadangan, dan pembukaan ulang berhasil.
- `dist/win-unpacked/Galaxy POS.exe` berhasil dijalankan dalam mode pengujian tersembunyi dan membuka `/desktop/setup` dari database baru. Server lokal berhenti saat aplikasi selesai.
- Form pengaturan awal diperiksa melalui browser dengan database uji terpisah. Font, JavaScript, stylesheet, dan logo dimuat lokal.
- Suite PHP lengkap: 97 tes, 465 assertions; masih ada empat kegagalan yang sudah ada karena view `auth.confirm-password`, `auth.forgot-password`, `auth.reset-password`, dan `auth.register` tidak tersedia. Enam tes dilewati dan satu tes lama tidak memiliki assertions. Ini bukan klaim seluruh suite lulus.

Pengujian dilakukan pada mesin pengembang Windows x64. Pengujian instalasi di PC toko baru, printer fisik, serta skenario kegagalan listrik belum dilakukan. Jalankan transaksi uji dan coba printer toko sebelum dipakai operasional.

Referensi implementasi: [panduan keamanan Electron](https://www.electronjs.org/docs/latest/tutorial/security) dan [installer NSIS electron-builder](https://www.electron.build/nsis/). Lisensi PHP, Chromium/Electron, font, dan SweetAlert2 disertakan bersama berkas terkait.
