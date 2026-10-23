<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateServisSparepartTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('servis_sparepart', function (Blueprint $table) {
            $table->id('id_servis_sparepart');
            $table->unsignedBigInteger('id_servis');
            $table->unsignedBigInteger('id_produk');
            $table->unsignedBigInteger('id_cabang');
            $table->integer('jumlah')->default(1);
            $table->bigInteger('harga_jual')->default(0);   // harga jual sparepart (pendapatan)
            $table->bigInteger('unit_cost')->default(0);    // harga pokok per unit saat dipakai
            $table->bigInteger('subtotal')->default(0);     // harga_jual * jumlah
            $table->bigInteger('cost_total')->default(0);   // unit_cost * jumlah (untuk HPP)
            $table->string('reference', 100)->nullable();   // referensi pergerakan stok (SPART-...)
            $table->unsignedBigInteger('id_user')->nullable();
            $table->timestamps();

            $table->foreign('id_servis')->references('id_servis')->on('servis')->onDelete('cascade');
        });

        Schema::table('servis', function (Blueprint $table) {
            // total nilai sparepart yang dipakai pada servis ini (untuk tagihan & nota)
            $table->bigInteger('sparepart_total')->default(0)->after('biaya_servis');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('servis', function (Blueprint $table) {
            $table->dropColumn('sparepart_total');
        });
        Schema::dropIfExists('servis_sparepart');
    }
}
