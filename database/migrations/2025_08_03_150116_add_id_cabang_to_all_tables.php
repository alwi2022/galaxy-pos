<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIdCabangToAllTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('id_cabang')->nullable()->after('level');
            $table->foreign('id_cabang')->references('id_cabang')->on('cabang')->nullOnDelete();
        });
    
        Schema::table('produk', function (Blueprint $table) {
            $table->unsignedBigInteger('id_cabang')->nullable()->after('stok');
            $table->foreign('id_cabang')->references('id_cabang')->on('cabang')->nullOnDelete();
        });
    
        Schema::table('pembelian', function (Blueprint $table) {
            $table->unsignedBigInteger('id_cabang')->nullable()->after('bayar');
            $table->foreign('id_cabang')->references('id_cabang')->on('cabang')->nullOnDelete();
        });
    
        Schema::table('penjualan', function (Blueprint $table) {
            $table->unsignedBigInteger('id_cabang')->nullable()->after('diterima');
            $table->foreign('id_cabang')->references('id_cabang')->on('cabang')->nullOnDelete();
        });
    
        Schema::table('pengeluaran', function (Blueprint $table) {
            $table->unsignedBigInteger('id_cabang')->nullable()->after('nominal');
            $table->foreign('id_cabang')->references('id_cabang')->on('cabang')->nullOnDelete();
        });
    
        Schema::table('member', function (Blueprint $table) {
            $table->unsignedBigInteger('id_cabang')->nullable()->after('telepon');
            $table->foreign('id_cabang')->references('id_cabang')->on('cabang')->nullOnDelete();
        });
    
        Schema::table('supplier', function (Blueprint $table) {
            $table->unsignedBigInteger('id_cabang')->nullable()->after('telepon');
            $table->foreign('id_cabang')->references('id_cabang')->on('cabang')->nullOnDelete();
        });
    }
    

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['id_cabang']);
            $table->dropColumn('id_cabang');
        });
    
        Schema::table('produk', function (Blueprint $table) {
            $table->dropForeign(['id_cabang']);
            $table->dropColumn('id_cabang');
        });
    
        Schema::table('pembelian', function (Blueprint $table) {
            $table->dropForeign(['id_cabang']);
            $table->dropColumn('id_cabang');
        });
    
        Schema::table('penjualan', function (Blueprint $table) {
            $table->dropForeign(['id_cabang']);
            $table->dropColumn('id_cabang');
        });
    
        Schema::table('pengeluaran', function (Blueprint $table) {
            $table->dropForeign(['id_cabang']);
            $table->dropColumn('id_cabang');
        });
    
        Schema::table('member', function (Blueprint $table) {
            $table->dropForeign(['id_cabang']);
            $table->dropColumn('id_cabang');
        });

        Schema::table('supplier', function (Blueprint $table) {
            $table->dropForeign(['id_cabang']);
            $table->dropColumn('id_cabang');
        });
    }
}
