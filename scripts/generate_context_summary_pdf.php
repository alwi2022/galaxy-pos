<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$outputPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'rangkuman_context_codex.pdf';

$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Codex');
$pdf->SetAuthor('Codex');
$pdf->SetTitle('Rangkuman Context Codex - Galaxy POS');
$pdf->SetSubject('Rangkuman percakapan, implementasi fase 1, dan perbaikan environment');
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(14, 14, 14);
$pdf->SetAutoPageBreak(true, 14);
$pdf->SetFont('dejavusans', '', 10);
$pdf->AddPage();

$html = <<<'HTML'
<style>
    body {
        font-family: dejavusans;
        color: #1f2937;
        line-height: 1.45;
    }
    h1 {
        font-size: 20pt;
        color: #0f172a;
        margin-bottom: 6px;
    }
    h2 {
        font-size: 13pt;
        color: #0f172a;
        border-bottom: 1px solid #cbd5e1;
        padding-bottom: 4px;
        margin-top: 18px;
        margin-bottom: 8px;
    }
    p {
        font-size: 10pt;
        margin: 0 0 7px 0;
    }
    ul, ol {
        margin: 0 0 8px 16px;
        padding: 0;
        font-size: 10pt;
    }
    li {
        margin-bottom: 4px;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin: 6px 0 10px 0;
    }
    th {
        background-color: #e2e8f0;
        color: #0f172a;
        font-size: 9.5pt;
        text-align: left;
        border: 1px solid #cbd5e1;
        padding: 6px;
    }
    td {
        border: 1px solid #cbd5e1;
        padding: 6px;
        font-size: 9.5pt;
        vertical-align: top;
    }
    .muted {
        color: #475569;
        font-size: 9pt;
    }
    .box {
        border: 1px solid #cbd5e1;
        background-color: #f8fafc;
        padding: 9px 10px;
        margin-top: 6px;
        margin-bottom: 10px;
    }
    .mono {
        font-family: dejavusansmono;
        font-size: 9pt;
        color: #0f172a;
    }
</style>

<h1>Rangkuman Context Codex - Galaxy POS</h1>
<p class="muted">
Dokumen ringkasan percakapan untuk melanjutkan pekerjaan di tab berikutnya.
Topik utama: analisis struktur project, implementasi fase 1 transaksi hutang/piutang dan metode pembayaran,
serta perbaikan error environment PHP/Composer.
</p>

<h2>Ringkasan Singkat</h2>
<div class="box">
    <p>
        Percakapan dimulai dari evaluasi apakah codebase Galaxy POS yang ada sekarang cukup memungkinkan untuk
        menambahkan fitur penjualan dan pembelian kredit, pembayaran sebagian, metode pembayaran tunai/bank/QRIS,
        transaksi pembayaran hutang-piutang, laporan yang lebih kaya, serta kebutuhan lanjutan seperti PPN,
        pendapatan lain-lain, biaya operasional, dan export Excel.
    </p>
    <p>
        Hasil analisis menyimpulkan bahwa struktur project cukup memungkinkan, tetapi model transaksi lama terlalu
        sederhana karena hanya mengandalkan satu angka <span class="mono">bayar</span> dan belum memiliki status pembayaran,
        metode pembayaran, jatuh tempo, saldo sisa, maupun histori cicilan. Atas persetujuan user, Codex lalu
        mengintegrasikan fase 1: penjualan/pembelian kredit, bayar sebagian, metode pembayaran tunai/transfer bank/QRIS,
        halaman pembayaran hutang-piutang, pembaruan listing transaksi, penyesuaian nota, dan perhitungan laporan/dashboard
        berbasis uang masuk/keluar aktual. Setelah itu muncul error environment karena project dikunci ke PHP 8.3 sementara
        user menjalankan PHP 8.2.12. Root cause-nya berasal dari constraint root project dan lock Composer, lalu diperbaiki
        dengan melonggarkan requirement ke <span class="mono">^8.2</span> dan menyinkronkan file platform check Composer.
    </p>
</div>

<h2>Timeline / Urutan Progres</h2>
<table>
    <thead>
        <tr>
            <th width="18%">Tahap</th>
            <th>Detail Progres</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>1. Permintaan awal</td>
            <td>
                User menjelaskan kebutuhan bisnis: penjualan hutang atau bayar sebagian, pembayaran via bank atau QRIS,
                PPN, pembelian cash atau kredit, pembayaran hutang-piutang, laporan per periode / barang / kategori,
                pendapatan tunai lain-lain, biaya operasional, dan export ke Excel untuk laporan keuangan atau neraca.
            </td>
        </tr>
        <tr>
            <td>2. Audit codebase</td>
            <td>
                Codex menelusuri struktur Laravel project, routes, model, migration, controller, form transaksi,
                pengeluaran, dan laporan. Tool pencarian yang dipakai antara lain <span class="mono">rg --files</span>,
                <span class="mono">rg -n "hutang|piutang|bank|qris|ppn|..."</span>, pembacaan file controller/migration/view,
                serta pemeriksaan route dan dependency di <span class="mono">composer.json</span>.
            </td>
        </tr>
        <tr>
            <td>3. Kesimpulan analisis</td>
            <td>
                Struktur project dinilai layak untuk dikembangkan, tetapi arsitektur transaksi saat ini masih
                mengasumsikan semua transaksi lunas sekali bayar. Codex menyarankan pendekatan
                <span class="mono">header transaksi + payment ledger</span> agar fase 1 tidak rapuh.
            </td>
        </tr>
        <tr>
            <td>4. Persetujuan implementasi</td>
            <td>
                User menyetujui integrasi fase 1. Codex menyusun plan internal:
                perubahan skema data, perubahan flow penjualan/pembelian, penambahan menu pembayaran hutang-piutang,
                lalu verifikasi.
            </td>
        </tr>
        <tr>
            <td>5. Implementasi backend</td>
            <td>
                Ditambahkan migration untuk status pembayaran dan tabel histori pembayaran, model pembayaran baru,
                relasi model, helper status/metode pembayaran, controller baru untuk pembayaran outstanding,
                serta perubahan logic di penjualan dan pembelian.
            </td>
        </tr>
        <tr>
            <td>6. Implementasi UI</td>
            <td>
                Form transaksi penjualan dan pembelian diperluas dengan pilihan skema pembayaran, metode pembayaran,
                jatuh tempo, nominal bayar, saldo sisa, dan validasi client-side. Dibuat pula halaman
                pembayaran hutang-piutang dan pembaruan listing transaksi agar status keuangan terlihat jelas.
            </td>
        </tr>
        <tr>
            <td>7. Verifikasi fase 1</td>
            <td>
                Syntax PHP dicek dengan <span class="mono">php -l</span> dan lolos. Route baru terdaftar dengan
                <span class="mono">php artisan route:list</span>. Ditambahkan unit test helper pembayaran dan test itu lolos.
                Test suite feature penuh gagal bukan karena logic fase 1, tetapi karena koneksi database lokal ditolak.
            </td>
        </tr>
        <tr>
            <td>8. Error environment user</td>
            <td>
                User melaporkan <span class="mono">php artisan serve</span> dan <span class="mono">php artisan install</span>
                gagal karena Composer memeriksa requirement PHP >= 8.3.0, sementara terminal user memakai PHP 8.2.12.
            </td>
        </tr>
        <tr>
            <td>9. Fix environment</td>
            <td>
                Codex memeriksa versi PHP yang tersedia, path PHP aktif, isi <span class="mono">composer.json</span>,
                hasil <span class="mono">composer why-not php 8.2.12</span>, dan isi
                <span class="mono">vendor/composer/platform_check.php</span>. Root cause ditemukan pada
                constraint root project yang terlalu ketat. Constraint diubah dari <span class="mono">~8.3.0</span> menjadi
                <span class="mono">^8.2</span>, lock file disinkronkan, dan platform check Composer sekarang mengizinkan PHP 8.2.
            </td>
        </tr>
    </tbody>
</table>

<h2>Analisis Struktur Codebase</h2>
<p>
    Audit awal menemukan bahwa project ini adalah aplikasi Laravel POS klasik dengan modul inti
    <span class="mono">penjualan</span>, <span class="mono">pembelian</span>, <span class="mono">pengeluaran</span>,
    <span class="mono">laporan</span>, <span class="mono">servis</span>, serta dukungan multi-cabang.
</p>
<ul>
    <li><span class="mono">routes/web.php</span> menunjukkan modul CRUD utama, transaksi aktif, laporan, pengeluaran, pembelian, penjualan, dan servis.</li>
    <li><span class="mono">app/Http/Controllers/PenjualanController.php</span> dan <span class="mono">PembelianController.php</span> menyimpan transaksi final dengan field sederhana seperti <span class="mono">total_harga</span>, <span class="mono">diskon</span>, <span class="mono">bayar</span>, dan pada penjualan ada <span class="mono">diterima</span>.</li>
    <li><span class="mono">app/Http/Controllers/PenjualanDetailController.php</span> dan <span class="mono">PembelianDetailController.php</span> menunjukkan bahwa form transaksi menghitung total dan membangun transaksi aktif, tetapi belum memiliki konsep saldo sisa atau histori pembayaran.</li>
    <li><span class="mono">database/migrations/2021_03_05_200853_buat_penjualan_table.php</span> dan <span class="mono">2021_03_05_200841_buat_pembelian_table.php</span> membuktikan bahwa tabel header transaksi belum menyimpan status pembayaran, metode pembayaran, atau jatuh tempo.</li>
    <li><span class="mono">app/Http/Controllers/LaporanController.php</span> dan <span class="mono">DashboardController.php</span> awalnya menjumlahkan field <span class="mono">bayar</span> untuk menghitung pendapatan harian.</li>
    <li><span class="mono">app/Models/Produk.php</span> sudah punya relasi ke kategori, sehingga secara struktur laporan per kategori atau per jenis barang dinilai mungkin untuk fase selanjutnya.</li>
    <li><span class="mono">app/Http/Controllers/PengeluaranController.php</span> dan migration <span class="mono">pengeluaran</span> menunjukkan modul biaya sudah ada, tetapi masih sangat sederhana: hanya deskripsi dan nominal.</li>
    <li><span class="mono">composer.json</span> pada saat audit belum memiliki paket Excel, sehingga export yang tersedia masih PDF.</li>
</ul>

<h2>Keputusan Teknis yang Diambil</h2>
<ul>
    <li>Tidak mempertahankan model lama yang hanya mengandalkan satu field <span class="mono">bayar</span> untuk seluruh kebutuhan pembayaran.</li>
    <li>Menggunakan pendekatan header transaksi ditambah tabel histori pembayaran (<span class="mono">payment ledger</span>) untuk penjualan dan pembelian.</li>
    <li>Menambahkan kolom ringkasan ke tabel header: skema pembayaran, metode pembayaran, total terbayar, sisa, status, dan jatuh tempo.</li>
    <li>Menyediakan metode pembayaran <span class="mono">tunai</span>, <span class="mono">transfer_bank</span>, dan <span class="mono">qris</span>.</li>
    <li>Menyediakan skema pembayaran <span class="mono">langsung</span> dan <span class="mono">kredit</span>.</li>
    <li>Untuk penjualan kredit, member dibuat wajib dipilih agar piutang bisa punya pihak yang jelas.</li>
    <li>Laporan dan dashboard harian digeser dari logika total invoice menjadi logika uang yang benar-benar masuk/keluar berdasarkan histori pembayaran.</li>
    <li>Root requirement PHP project dilonggarkan ke <span class="mono">^8.2</span> karena dependency nyata tidak memaksa 8.3, sedangkan user menjalankan PHP 8.2.12.</li>
</ul>

<h2>Detail Implementasi</h2>

<p><strong>A. Perubahan skema data</strong></p>
<ul>
    <li>Migration baru: <span class="mono">database/migrations/2026_03_19_000001_add_phase_one_payment_support.php</span>.</li>
    <li>Kolom baru di tabel <span class="mono">penjualan</span>: <span class="mono">skema_pembayaran</span>, <span class="mono">metode_pembayaran</span>, <span class="mono">dibayar</span>, <span class="mono">sisa</span>, <span class="mono">status_pembayaran</span>, <span class="mono">jatuh_tempo</span>.</li>
    <li>Kolom baru di tabel <span class="mono">pembelian</span>: struktur yang sama dengan penjualan.</li>
    <li>Tabel baru: <span class="mono">penjualan_pembayaran</span> dan <span class="mono">pembelian_pembayaran</span>.</li>
    <li>Migration melakukan backfill transaksi lama agar data existing tetap terbaca sebagai transaksi langsung, umumnya lunas, dan memiliki riwayat pembayaran awal.</li>
</ul>

<p><strong>B. Model dan relasi baru</strong></p>
<ul>
    <li>Model baru: <span class="mono">app/Models/PenjualanPembayaran.php</span>.</li>
    <li>Model baru: <span class="mono">app/Models/PembelianPembayaran.php</span>.</li>
    <li><span class="mono">app/Models/Penjualan.php</span> ditambah relasi <span class="mono">pembayaran()</span> dan method <span class="mono">syncStatusPembayaran()</span>.</li>
    <li><span class="mono">app/Models/Pembelian.php</span> ditambah relasi <span class="mono">pembayaran()</span> dan method <span class="mono">syncStatusPembayaran()</span>.</li>
</ul>

<p><strong>C. Helper baru</strong></p>
<ul>
    <li>File: <span class="mono">app/Http/Helpers/helpers.php</span>.</li>
    <li>Fungsi baru: <span class="mono">daftar_metode_pembayaran()</span>, <span class="mono">label_metode_pembayaran()</span>, <span class="mono">daftar_skema_pembayaran()</span>, <span class="mono">label_skema_pembayaran()</span>, <span class="mono">status_pembayaran()</span>, <span class="mono">label_status_pembayaran()</span>, dan <span class="mono">class_status_pembayaran()</span>.</li>
</ul>

<p><strong>D. Perubahan logic transaksi penjualan</strong></p>
<ul>
    <li>Controller: <span class="mono">app/Http/Controllers/PenjualanController.php</span>.</li>
    <li>Validasi baru untuk skema, metode, jumlah bayar, dan jatuh tempo.</li>
    <li>Penjualan kredit mensyaratkan member.</li>
    <li>Pembayaran langsung harus lunas.</li>
    <li>Pembayaran non-tunai tidak boleh melebihi total tagihan.</li>
    <li>Pembayaran awal disimpan ke <span class="mono">penjualan_pembayaran</span>, lalu header disinkronkan dengan <span class="mono">syncStatusPembayaran()</span>.</li>
    <li>Session transaksi aktif dibersihkan setelah simpan untuk mengurangi risiko submit ganda.</li>
</ul>

<p><strong>E. Perubahan logic transaksi pembelian</strong></p>
<ul>
    <li>Controller: <span class="mono">app/Http/Controllers/PembelianController.php</span>.</li>
    <li>Struktur validasi sejalan dengan penjualan.</li>
    <li>Pembayaran awal pembelian disimpan di <span class="mono">pembelian_pembayaran</span>.</li>
    <li>Header pembelian ikut menyimpan status pembayaran, sisa, dan jatuh tempo.</li>
</ul>

<p><strong>F. Halaman pembayaran hutang-piutang</strong></p>
<ul>
    <li>Controller baru: <span class="mono">app/Http/Controllers/PembayaranController.php</span>.</li>
    <li>View baru: <span class="mono">resources/views/pembayaran/index.blade.php</span>.</li>
    <li>Fitur: daftar outstanding piutang penjualan, daftar outstanding hutang pembelian, modal detail transaksi, histori pembayaran, dan form pembayaran lanjutan.</li>
    <li>Route baru di <span class="mono">routes/web.php</span>:
        <span class="mono">pembayaran.index</span>,
        <span class="mono">pembayaran.piutang.data/show/store</span>,
        <span class="mono">pembayaran.hutang.data/show/store</span>.
    </li>
</ul>

<p><strong>G. Perubahan UI transaksi</strong></p>
<ul>
    <li><span class="mono">resources/views/penjualan_detail/index.blade.php</span> ditambah field skema, metode, jatuh tempo, nominal bayar, terbayar, sisa, dan validasi JavaScript.</li>
    <li><span class="mono">resources/views/pembelian_detail/index.blade.php</span> ditambah field serupa untuk pembelian.</li>
    <li>Route loadform pembelian diperluas dari 2 parameter menjadi 3 parameter agar bisa menghitung total, dibayar, dan sisa secara dinamis.</li>
</ul>

<p><strong>H. Perubahan listing dan nota</strong></p>
<ul>
    <li><span class="mono">resources/views/penjualan/index.blade.php</span> dan <span class="mono">resources/views/pembelian/index.blade.php</span> sekarang menampilkan tagihan, dibayar, sisa, metode, dan status.</li>
    <li><span class="mono">resources/views/penjualan/nota_kecil.blade.php</span> dan <span class="mono">nota_besar.blade.php</span> disesuaikan agar mencetak tagihan, terbayar, sisa, metode, dan status, bukan mengasumsikan transaksi selalu lunas.</li>
    <li><span class="mono">resources/views/layouts/sidebar.blade.php</span> ditambah menu <span class="mono">Pembayaran</span>.</li>
    <li><span class="mono">resources/views/layouts/master.blade.php</span> ditambah alert untuk error validasi.</li>
</ul>

<p><strong>I. Perubahan laporan dan dashboard</strong></p>
<ul>
    <li><span class="mono">app/Http/Controllers/LaporanController.php</span> sekarang membaca uang masuk penjualan dari <span class="mono">PenjualanPembayaran</span> dan uang keluar pembelian dari <span class="mono">PembelianPembayaran</span>.</li>
    <li><span class="mono">app/Http/Controllers/DashboardController.php</span> diubah dengan prinsip yang sama untuk grafik pendapatan harian.</li>
</ul>

<p><strong>J. Test tambahan</strong></p>
<ul>
    <li>Test baru: <span class="mono">tests/Unit/PaymentHelperTest.php</span>.</li>
    <li>Fokus test: perhitungan status pembayaran dan label metode/skema pembayaran.</li>
</ul>

<h2>Masalah &amp; Solusi</h2>
<table>
    <thead>
        <tr>
            <th width="25%">Masalah</th>
            <th width="27%">Root Cause</th>
            <th>Solusi / Fix</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Fitur hutang/piutang tidak tersedia secara native di transaksi lama</td>
            <td>
                Skema lama hanya menyimpan satu field <span class="mono">bayar</span> dan belum punya payment ledger,
                saldo sisa, status, metode, atau jatuh tempo.
            </td>
            <td>
                Dibuat migration fase 1, tabel histori pembayaran, relasi baru, helper status pembayaran, dan halaman
                pembayaran outstanding.
            </td>
        </tr>
        <tr>
            <td>Laporan pendapatan harian bisa bias jika transaksi kredit dipakai</td>
            <td>
                Dashboard dan laporan lama memakai total invoice dari field <span class="mono">bayar</span>,
                bukan uang yang benar-benar masuk/keluar.
            </td>
            <td>
                Query di dashboard dan laporan dipindahkan ke tabel histori pembayaran:
                <span class="mono">PenjualanPembayaran</span> dan <span class="mono">PembelianPembayaran</span>.
            </td>
        </tr>
        <tr>
            <td><span class="mono">php artisan test</span> gagal</td>
            <td>
                Environment test mencoba terkoneksi ke MySQL lokal
                <span class="mono">127.0.0.1:3306 / db tokoku / user root</span>,
                tetapi akses database ditolak.
            </td>
            <td>
                Tidak dibloker oleh perubahan fase 1. Verifikasi dialihkan ke syntax check, route check, dan unit test helper.
                Perlu konfigurasi DB test terpisah jika ingin menyalakan suite penuh.
            </td>
        </tr>
        <tr>
            <td><span class="mono">php artisan serve</span> gagal di mesin user</td>
            <td>
                Composer platform check masih mengharuskan PHP >= 8.3.0, sedangkan user menjalankan PHP 8.2.12.
                Hasil audit menunjukkan constraint 8.3 datang dari root project, bukan dari dependency inti.
            </td>
            <td>
                File <span class="mono">composer.json</span> diubah dari <span class="mono">~8.3.0</span> menjadi
                <span class="mono">^8.2</span>, <span class="mono">composer.lock</span> disinkronkan,
                dan <span class="mono">vendor/composer/platform_check.php</span> kini hanya memeriksa PHP >= 8.2.0.
            </td>
        </tr>
        <tr>
            <td>Muncul warning deprecated saat menggunakan PHP 8.4</td>
            <td>
                Project masih memakai Laravel 8 dan vendor lama yang belum sepenuhnya bersih terhadap warning deprecation di PHP 8.4.
            </td>
            <td>
                Tidak diblok saat ini. Untuk operasional sekarang, PHP 8.2 dinilai aman.
                Penanganan warning bisa dijadwalkan sebagai pekerjaan upgrade framework di fase terpisah.
            </td>
        </tr>
    </tbody>
</table>

<h2>Command Penting yang Dipakai dalam Percakapan</h2>
<ul>
    <li><span class="mono">rg --files</span> untuk memetakan codebase.</li>
    <li><span class="mono">rg -n "hutang|piutang|bank|qris|ppn|..." app database routes resources\views</span> untuk menemukan jejak fitur lama.</li>
    <li><span class="mono">php -l</span> untuk syntax check file PHP yang diubah.</li>
    <li><span class="mono">php artisan route:list --name=pembayaran</span> untuk memastikan route baru aktif.</li>
    <li><span class="mono">php artisan test tests/Unit/PaymentHelperTest.php</span> untuk validasi helper pembayaran.</li>
    <li><span class="mono">php artisan test</span> untuk test suite global, yang kemudian gagal karena DB access denied.</li>
    <li><span class="mono">php -v</span>, <span class="mono">where php</span>, dan <span class="mono">composer why-not php 8.2.12</span> untuk diagnosis masalah environment.</li>
    <li><span class="mono">composer update --lock</span> / sinkronisasi lock dan <span class="mono">composer dump-autoload</span> untuk memperbarui metadata vendor setelah perubahan constraint PHP.</li>
</ul>

<h2>Status Terakhir Project</h2>
<div class="box">
    <p><strong>Status implementasi:</strong> fase 1 sudah ditanamkan di codebase.</p>
    <p><strong>Status environment:</strong> platform check Composer sudah kompatibel dengan PHP 8.2.</p>
    <p><strong>Status verifikasi:</strong></p>
    <ul>
        <li>Syntax PHP untuk file utama yang diubah: lolos.</li>
        <li>Route pembayaran baru: terdaftar.</li>
        <li>Unit test helper pembayaran: lolos.</li>
        <li>Test suite feature global: belum hijau karena masalah koneksi database test, bukan karena syntax implementasi.</li>
    </ul>
    <p><strong>Catatan operasional:</strong> migration fase 1 belum dinyatakan sudah dijalankan di database user, jadi
    langkah nyata berikutnya adalah mengeksekusi migration dan smoke test manual di browser.</p>
</div>

<h2>Next Steps</h2>
<ol>
    <li>Jalankan <span class="mono">php artisan migrate</span> pada database yang aktif.</li>
    <li>Lakukan smoke test manual untuk 5 flow utama:
        penjualan tunai penuh, penjualan kredit tanpa bayar, penjualan kredit bayar sebagian,
        pembelian tunai, dan pembelian kredit lalu pelunasan dari menu pembayaran.</li>
    <li>Periksa data existing hasil backfill, terutama transaksi lama penjualan dan pembelian.</li>
    <li>Jika ingin verifikasi otomatis yang lebih kuat, siapkan database testing yang valid agar
        <span class="mono">php artisan test</span> bisa dijalankan penuh.</li>
    <li>Masuk ke fase berikutnya yang sebelumnya sudah disepakati secara arah:
        implementasi PPN, laporan per kategori atau jenis barang, dan export Excel.</li>
    <li>Setelah fase bisnis selesai, pertimbangkan pekerjaan teknis terpisah untuk upgrade framework atau
        pembersihan warning deprecated jika nantinya project dijalankan pada PHP 8.4 secara konsisten.</li>
</ol>

<h2>Daftar File Penting yang Tersentuh / Dibahas</h2>
<ul>
    <li><span class="mono">routes/web.php</span></li>
    <li><span class="mono">composer.json</span></li>
    <li><span class="mono">composer.lock</span></li>
    <li><span class="mono">vendor/composer/platform_check.php</span></li>
    <li><span class="mono">app/Http/Controllers/PenjualanController.php</span></li>
    <li><span class="mono">app/Http/Controllers/PembelianController.php</span></li>
    <li><span class="mono">app/Http/Controllers/PenjualanDetailController.php</span></li>
    <li><span class="mono">app/Http/Controllers/PembelianDetailController.php</span></li>
    <li><span class="mono">app/Http/Controllers/PembayaranController.php</span></li>
    <li><span class="mono">app/Http/Controllers/LaporanController.php</span></li>
    <li><span class="mono">app/Http/Controllers/DashboardController.php</span></li>
    <li><span class="mono">app/Http/Helpers/helpers.php</span></li>
    <li><span class="mono">app/Models/Penjualan.php</span></li>
    <li><span class="mono">app/Models/Pembelian.php</span></li>
    <li><span class="mono">app/Models/PenjualanPembayaran.php</span></li>
    <li><span class="mono">app/Models/PembelianPembayaran.php</span></li>
    <li><span class="mono">database/migrations/2026_03_19_000001_add_phase_one_payment_support.php</span></li>
    <li><span class="mono">resources/views/penjualan_detail/index.blade.php</span></li>
    <li><span class="mono">resources/views/pembelian_detail/index.blade.php</span></li>
    <li><span class="mono">resources/views/pembayaran/index.blade.php</span></li>
    <li><span class="mono">resources/views/penjualan/index.blade.php</span></li>
    <li><span class="mono">resources/views/pembelian/index.blade.php</span></li>
    <li><span class="mono">resources/views/penjualan/nota_kecil.blade.php</span></li>
    <li><span class="mono">resources/views/penjualan/nota_besar.blade.php</span></li>
    <li><span class="mono">resources/views/layouts/sidebar.blade.php</span></li>
    <li><span class="mono">resources/views/layouts/master.blade.php</span></li>
    <li><span class="mono">tests/Unit/PaymentHelperTest.php</span></li>
</ul>

<p class="muted">
Dokumen ini dibuat untuk menjaga context kerja lintas tab. Seluruh poin di atas merupakan rangkuman runtut dari thread yang sama,
dengan fokus pada keputusan teknis, perubahan implementasi, error yang muncul, root cause, solusi, status terakhir, dan langkah lanjutan.
</p>
HTML;

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output($outputPath, 'F');

echo $outputPath . PHP_EOL;
