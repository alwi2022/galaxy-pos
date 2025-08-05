<?php
// routes/web.php

use App\Http\Controllers\{
    DashboardController,
    KategoriController,
    LaporanController,
    ProdukController,
    MemberController,
    PengeluaranController,
    PembelianController,
    PembelianDetailController,
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
// routes/web.php
Route::get('/test-barcode', function() {
    try {
        $barcode = DNS1D::getBarcodePNG('123456789', 'C39');
        return '<img src="data:image/png;base64,'.$barcode.'">';
    } catch (Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
});
Route::group(['middleware' => 'auth'], function () {
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
        Route::resource('/kategori', KategoriController::class);

        Route::get('/produk/data', [ProdukController::class, 'data'])->name('produk.data');
        Route::post('/produk/delete-selected', [ProdukController::class, 'deleteSelected'])->name('produk.delete_selected');
        Route::post('/produk/cetak-barcode', [ProdukController::class, 'cetakBarcode'])->name('produk.cetak_barcode');
        Route::resource('/produk', ProdukController::class)->middleware('cabang');

        Route::get('/member/data', [MemberController::class, 'data'])->name('member.data');
        Route::post('/member/cetak-member', [MemberController::class, 'cetakMember'])->name('member.cetak_member');
        Route::resource('/member', MemberController::class)->middleware('cabang');

        Route::get('/supplier/data', [SupplierController::class, 'data'])->name('supplier.data');
        Route::resource('/supplier', SupplierController::class)->middleware('cabang');

        Route::get('/pengeluaran/data', [PengeluaranController::class, 'data'])->name('pengeluaran.data');
        Route::resource('/pengeluaran', PengeluaranController::class)->middleware('cabang');

        Route::get('/pembelian/data', [PembelianController::class, 'data'])->name('pembelian.data');
        Route::get('/pembelian/{id}/create', [PembelianController::class, 'create'])->name('pembelian.create');
        Route::resource('/pembelian', PembelianController::class)
            ->except('create');

        Route::get('/pembelian_detail/{id}/data', [PembelianDetailController::class, 'data'])->name('pembelian_detail.data');
        Route::get('/pembelian_detail/loadform/{diskon}/{total}', [PembelianDetailController::class, 'loadForm'])->name('pembelian_detail.load_form');
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

        Route::get('/transaksi/{id}/data', [PenjualanDetailController::class, 'data'])->name('transaksi.data');
        Route::get('/transaksi/loadform/{diskon}/{total}/{diterima}', [PenjualanDetailController::class, 'loadForm'])->name('transaksi.load_form');

        Route::get('/transaksi/produk-barcode/{kode}', [PenjualanDetailController::class, 'getProdukByKode'])->name('transaksi.produk_barcode');
        Route::post('/transaksi/tambah-barcode', [PenjualanDetailController::class, 'storeByBarcode'])->name('transaksi.tambah_barcode');
        
        Route::resource('/transaksi', PenjualanDetailController::class)
            ->except('create', 'show', 'edit');

            
    });

    Route::group(['middleware' => 'level:1'], function () {
        Route::get('/laporan', [LaporanController::class, 'index'])->name('laporan.index');
        Route::get('/laporan/data/{awal}/{akhir}', [LaporanController::class, 'data'])->name('laporan.data');
        Route::get('/laporan/pdf/{awal}/{akhir}', [LaporanController::class, 'exportPDF'])->name('laporan.export_pdf');

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
        Route::resource('/servis', ServisController::class)->except('show')->middleware('cabang');
        Route::get('/servis/nota-kecil/{id}', [ServisController::class, 'notaKecil'])->name('servis.nota_kecil')->middleware('cabang');
    });
});
