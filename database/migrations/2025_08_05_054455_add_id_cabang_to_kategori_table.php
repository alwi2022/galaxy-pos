<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIdCabangToKategoriTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('kategori', function (Blueprint $table) {
            $table->unsignedBigInteger('id_cabang')->nullable()->after('nama_kategori');
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
        Schema::table('kategori', function (Blueprint $table) {
            $table->dropForeign(['id_cabang']);
            $table->dropColumn('id_cabang');
        });
    }
}
