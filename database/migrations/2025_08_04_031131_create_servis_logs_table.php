<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateServisLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('servis_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_servis');
            $table->unsignedBigInteger('id_user');
            $table->string('status');
            $table->timestamps();
        
            $table->foreign('id_servis')->references('id_servis')->on('servis')->onDelete('cascade');
            $table->foreign('id_user')->references('id')->on('users')->onDelete('cascade');
        });
        
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('servis_logs');
    }
}
