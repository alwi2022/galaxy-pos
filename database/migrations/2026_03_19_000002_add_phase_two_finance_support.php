<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddPhaseTwoFinanceSupport extends Migration
{
    public function up()
    {
        Schema::table('penjualan', function (Blueprint $table) {
            $table->decimal('ppn_persen', 5, 2)->default(0)->after('diskon');
            $table->integer('ppn_nominal')->default(0)->after('bayar');
        });

        Schema::table('pembelian', function (Blueprint $table) {
            $table->decimal('ppn_persen', 5, 2)->default(0)->after('diskon');
            $table->integer('ppn_nominal')->default(0)->after('bayar');
        });

        Schema::table('pengeluaran', function (Blueprint $table) {
            $table->date('tanggal_pengeluaran')->nullable()->after('id_pengeluaran');
            $table->string('kategori_pengeluaran', 40)->default('operasional_lainnya')->after('tanggal_pengeluaran');
            $table->string('metode_pembayaran', 20)->default('tunai')->after('nominal');
        });

        Schema::create('pendapatan_lain', function (Blueprint $table) {
            $table->increments('id_pendapatan_lain');
            $table->date('tanggal_pendapatan');
            $table->string('kategori_pendapatan', 50)->default('pendapatan_lain_lain');
            $table->text('deskripsi');
            $table->integer('nominal');
            $table->string('metode_pembayaran', 20)->default('tunai');
            $table->integer('id_user')->nullable();
            $table->unsignedBigInteger('id_cabang')->nullable();
            $table->timestamps();
        });

        DB::table('penjualan')
            ->whereNull('ppn_persen')
            ->update([
                'ppn_persen' => 0,
                'ppn_nominal' => 0,
            ]);

        DB::table('pembelian')
            ->whereNull('ppn_persen')
            ->update([
                'ppn_persen' => 0,
                'ppn_nominal' => 0,
            ]);

        DB::table('pengeluaran')
            ->whereNull('tanggal_pengeluaran')
            ->update([
                'tanggal_pengeluaran' => DB::raw('DATE(created_at)'),
            ]);
    }

    public function down()
    {
        Schema::dropIfExists('pendapatan_lain');

        Schema::table('pengeluaran', function (Blueprint $table) {
            $table->dropColumn([
                'tanggal_pengeluaran',
                'kategori_pengeluaran',
                'metode_pembayaran',
            ]);
        });

        Schema::table('pembelian', function (Blueprint $table) {
            $table->dropColumn([
                'ppn_persen',
                'ppn_nominal',
            ]);
        });

        Schema::table('penjualan', function (Blueprint $table) {
            $table->dropColumn([
                'ppn_persen',
                'ppn_nominal',
            ]);
        });
    }
}
