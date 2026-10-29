<?php
// routes/web.php

use App\Http\Controllers\{
    DashboardController,
    KategoriController,
    LaporanController,
    ProdukController,
    MemberController,
    PengeluaranController,
    PendapatanLainController,
    PembelianController,
    PembelianDetailController,
    PembayaranController,
    PenjualanController,
    PenjualanDetailController,
    SettingController,
    SupplierController,
    UserController,
    ServisController,
    CabangController,
};
use Illuminate\Support\Facades\Route;

/*
 * |--------------------------------------------------------------------------
 * | Web Routes
 * |--------------------------------------------------------------------------
 * |
 * | Here is where you can register web routes for your application. These
 * | routes are loaded by the RouteServiceProvider within a group which
 * | contains the "web" middleware group. Now create something great!
 * |
 */

Route::get('/', function () {
    return redirect()->route('login');
});
Route::get('/servis/track/{kode}', [ServisController::class, 'track'])->name('servis.track');
Route::get('/desktop/setup', [\App\Http\Controllers\DesktopSetupController::class, 'create'])->name('desktop.setup');
Route::post('/desktop/setup', [\App\Http\Controllers\DesktopSetupController::class, 'store'])->middleware('throttle:10,1')->name('desktop.setup.store');
Route::group(['middleware' => ['auth', \App\Http\Middleware\AtomicBusinessWrite::class]], function () {
    Route::group(['middleware' => 'level:1'], function () {
        Route::get('/usaha/export/jurnal', [\App\Http\Controllers\BusinessController::class, 'export'])->name('business.export');
        Route::get('/usaha/export/pajak/{id}/{format}', [\App\Http\Controllers\BusinessController::class, 'taxExport'])->name('business.tax-export');
        Route::get('/usaha/template/bank', [\App\Http\Controllers\BusinessController::class, 'bankTemplate'])->name('business.bank-template');
        Route::get('/usaha/detail/{type}/{id}', [\App\Http\Controllers\BusinessController::class, 'details'])->name('business.details');
        Route::post('/usaha/simpan/{action}', [\App\Http\Controllers\BusinessController::class, 'store'])->name('business.store');
        Route::get('/usaha/{module?}', [\App\Http\Controllers\BusinessController::class, 'index'])->name('business.index');
    });
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::group(['middleware' => 'level:1'], function () {
        Route::get('/cabang', [CabangController::class, 'index'])->name('cabang.index');
        Route::post('/cabang', [CabangController::class, 'store'])->name('cabang.store');
        Route::get('/cabang/data', [CabangController::class, 'data'])->name('cabang.data');
        Route::get('/cabang/{id}/edit', [CabangController::class, 'edit']);
        Route::put('/cabang/{id}', [CabangController::class, 'update']);
        Route::delete('/cabang/{id}', [CabangController::class, 'destroy']);
        Route::get('user/pindah-cabang', [UserController::class, 'formPindahCabang'])->name('user.pindah_cabang');
        Route::post('user/pindah-cabang', [UserController::class, 'pindahCabang'])->name('user.update_cabang');

        Route::get('/setting/backup', [SettingController::class, 'backup'])->name('setting.backup')->middleware('level:1');
        Route::post('/setting/restore', [SettingController::class, 'restore'])->name('setting.restore')->middleware('level:1');

        Route::get('/kategori/data', [KategoriController::class, 'data'])->name('kategori.data');
        Route::get('/kategori/import/template', [KategoriController::class, 'importTemplate'])->name('kategori.import_template');
        Route::post('/kategori/import', [KategoriController::class, 'import'])->name('kategori.import');
        Route::get('/kategori/export', [KategoriController::class, 'export'])->name('kategori.export');
        Route::resource('/kategori', KategoriController::class);

        Route::get('/produk/data', [ProdukController::class, 'data'])->name('produk.data');
        Route::post('/produk/delete-selected', [ProdukController::class, 'deleteSelected'])->name('produk.delete_selected');
        Route::post('/produk/cetak-barcode', [ProdukController::class, 'cetakBarcode'])->name('produk.cetak_barcode');
        Route::get('/produk/import/template', [ProdukController::class, 'importTemplate'])->name('produk.import_template');
        Route::post('/produk/import', [ProdukController::class, 'import'])->name('produk.import');
        Route::get('/produk/export', [ProdukController::class, 'export'])->name('produk.export');
        Route::resource('/produk', ProdukController::class)->middleware('cabang');

        Route::get('/member/data', [MemberController::class, 'data'])->name('member.data');
        Route::post('/member/cetak-member', [MemberController::class, 'cetakMember'])->name('member.cetak_member');
        Route::get('/member/import/template', [MemberController::class, 'importTemplate'])->name('member.import_template');
        Route::post('/member/import', [MemberController::class, 'import'])->name('member.import');
        Route::get('/member/export', [MemberController::class, 'export'])->name('member.export');
        Route::resource('/member', MemberController::class)->middleware('cabang');

        Route::get('/supplier/data', [SupplierController::class, 'data'])->name('supplier.data');
        Route::get('/supplier/import/template', [SupplierController::class, 'importTemplate'])->name('supplier.import_template');
        Route::post('/supplier/import', [SupplierController::class, 'import'])->name('supplier.import');
        Route::get('/supplier/export', [SupplierController::class, 'export'])->name('supplier.export');
        Route::resource('/supplier', SupplierController::class)->middleware('cabang');

        Route::get('/pengeluaran/data', [PengeluaranController::class, 'data'])->name('pengeluaran.data');
        Route::resource('/pengeluaran', PengeluaranController::class)->middleware('cabang');
        Route::get('/pendapatan-lain/data', [PendapatanLainController::class, 'data'])->name('pendapatan-lain.data');
        Route::resource('/pendapatan-lain', PendapatanLainController::class)->except('create', 'edit');

        Route::get('/pembelian/data', [PembelianController::class, 'data'])->name('pembelian.data');
        Route::get('/pembelian/{id}/create', [PembelianController::class, 'create'])->name('pembelian.create');
        Route::resource('/pembelian', PembelianController::class)
            ->except('create');

        Route::get('/pembelian_detail/{id}/data', [PembelianDetailController::class, 'data'])->name('pembelian_detail.data');
        Route::get('/pembelian_detail/loadform/{diskon}/{total}/{dibayar?}', [PembelianDetailController::class, 'loadForm'])->name('pembelian_detail.load_form');
        Route::resource('/pembelian_detail', PembelianDetailController::class)
            ->except('create', 'show', 'edit');

        Route::get('/penjualan/data', [PenjualanController::class, 'data'])->name('penjualan.data');
        Route::get('/penjualan', [PenjualanController::class, 'index'])->name('penjualan.index');
        Route::get('/penjualan/{id}', [PenjualanController::class, 'show'])->name('penjualan.show')->middleware('cabang');
        Route::delete('/penjualan/{id}', [PenjualanController::class, 'destroy'])->name('penjualan.destroy')->middleware('cabang');
    });

    Route::group(['middleware' => 'level:1,2'], function () {
        Route::get('/transaksi/baru', [PenjualanController::class, 'create'])->name('transaksi.baru');
        Route::post('/transaksi/simpan', [PenjualanController::class, 'store'])->name('transaksi.simpan');
        Route::get('/transaksi/selesai', [PenjualanController::class, 'selesai'])->name('transaksi.selesai');
        Route::get('/transaksi/nota-kecil', [PenjualanController::class, 'notaKecil'])->name('transaksi.nota_kecil');
        Route::get('/transaksi/nota-besar', [PenjualanController::class, 'notaBesar'])->name('transaksi.nota_besar');
        Route::get('/transaksi/cari-nota', [PenjualanController::class, 'cariNota'])->name('transaksi.cari_nota');
        Route::get('/penjualan/{id}/nota-kecil', [PenjualanController::class, 'notaKecil'])->name('penjualan.nota_kecil')->middleware('cabang');
        Route::get('/penjualan/{id}/nota-besar', [PenjualanController::class, 'notaBesar'])->name('penjualan.nota_besar')->middleware('cabang');

        Route::get('/transaksi/{id}/data', [PenjualanDetailController::class, 'data'])->name('transaksi.data');
        Route::get('/transaksi/loadform/{diskon}/{total}/{diterima}', [PenjualanDetailController::class, 'loadForm'])->name('transaksi.load_form');

        Route::get('/transaksi/produk-barcode/{kode}', [PenjualanDetailController::class, 'getProdukByKode'])->name('transaksi.produk_barcode');
        Route::post('/transaksi/tambah-barcode', [PenjualanDetailController::class, 'storeByBarcode'])->name('transaksi.tambah_barcode');
        
        Route::resource('/transaksi', PenjualanDetailController::class)
            ->except('create', 'show', 'edit');

        Route::get('/pembayaran', [PembayaranController::class, 'index'])->name('pembayaran.index');
        Route::get('/pembayaran/piutang/data', [PembayaranController::class, 'piutangData'])->name('pembayaran.piutang.data');
        Route::get('/pembayaran/piutang/{id}', [PembayaranController::class, 'showPiutang'])->name('pembayaran.piutang.show');
        Route::post('/pembayaran/piutang/{id}', [PembayaranController::class, 'storePiutang'])->name('pembayaran.piutang.store');
    });

    Route::group(['middleware' => 'level:1'], function () {
        Route::get('/laporan', [LaporanController::class, 'index'])->name('laporan.index');
        Route::get('/laporan/keuangan/{report}', [\App\Http\Controllers\BusinessController::class, 'financialReport'])->name('laporan.keuangan');
        Route::get('/laporan/data/{awal}/{akhir}', [LaporanController::class, 'data'])->name('laporan.data');
        Route::get('/laporan/pdf/{awal}/{akhir}', [LaporanController::class, 'exportPDF'])->name('laporan.export_pdf');
        Route::get('/laporan/excel/{tab}/{awal}/{akhir}', [LaporanController::class, 'exportExcel'])->name('laporan.export_excel');

        Route::get('/pembayaran/hutang/data', [PembayaranController::class, 'hutangData'])->name('pembayaran.hutang.data');
        Route::get('/pembayaran/hutang/{id}', [PembayaranController::class, 'showHutang'])->name('pembayaran.hutang.show');
        Route::post('/pembayaran/hutang/{id}', [PembayaranController::class, 'storeHutang'])->name('pembayaran.hutang.store');

        Route::get('/user/data', [UserController::class, 'data'])->name('user.data');
        Route::resource('/user', UserController::class);

        Route::get('/setting', [SettingController::class, 'index'])->name('setting.index');
        Route::get('/setting/first', [SettingController::class, 'show'])->name('setting.show');
        Route::post('/setting', [SettingController::class, 'update'])->name('setting.update');
    });

    Route::group(['middleware' => ['level:1,2,3']], function () {
        Route::get('/profil', [UserController::class, 'profil'])->name('user.profil');
        Route::post('/profil', [UserController::class, 'updateProfil'])->name('user.update_profil');
    });

    Route::group(['middleware' => ['level:1,3']], function () {
        Route::get('/servis/data', [ServisController::class, 'data'])->name('servis.data');
        Route::get('/servis-produk/data', [ServisController::class, 'produkData'])->name('servis.produk.data');
        Route::get('/servis/{id}/sparepart/data', [ServisController::class, 'sparepartData'])->name('servis.sparepart.data')->middleware('cabang');
        Route::post('/servis/{id}/sparepart', [ServisController::class, 'sparepartStore'])->name('servis.sparepart.store')->middleware('cabang');
        Route::delete('/servis/{id}/sparepart/{sparepart}', [ServisController::class, 'sparepartDestroy'])->name('servis.sparepart.destroy')->middleware('cabang');
        Route::resource('/servis', ServisController::class)->except('show')->middleware('cabang');
        Route::get('/servis/nota-kecil/{id}', [ServisController::class, 'notaKecil'])->name('servis.nota_kecil')->middleware('cabang');
    });
});
