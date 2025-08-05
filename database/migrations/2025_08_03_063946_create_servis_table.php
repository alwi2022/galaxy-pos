<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateServisTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('servis', function (Blueprint $table) {
            $table->id('id_servis');
            $table->string('kode_servis')->unique();
            $table->string('nama_pelanggan')->nullable();
            $table->string('telepon', 50)->nullable();
            $table->unsignedInteger('id_member')->nullable();
            $table->text('keluhan')->nullable();
            $table->string('tipe_barang')->nullable();
            $table->string('merk')->nullable();
            $table->text('kerusakan')->nullable();
            $table->enum('status', ['diproses', 'selesai', 'diambil'])->default('diproses');
            $table->integer('biaya_servis')->default(0);
            $table->string('teknisi')->nullable();
            $table->integer('garansi_hari')->default(30);
            $table->timestamp('tanggal_masuk')->useCurrent();
            $table->timestamp('tanggal_selesai')->nullable();
            $table->unsignedBigInteger('id_cabang')->nullable();
            $table->unsignedBigInteger('id_user')->nullable();
            $table->timestamps();
    
            $table->foreign('id_member')->references('id_member')->on('member')->onDelete('set null');
            $table->foreign('id_cabang')->references('id_cabang')->on('cabang')->onDelete('set null');
            $table->foreign('id_user')->references('id')->on('users')->onDelete('set null');
        });
    }
    

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('servis');
    }
}
