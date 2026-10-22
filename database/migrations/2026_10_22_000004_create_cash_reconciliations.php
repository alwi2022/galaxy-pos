<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCashReconciliations extends Migration
{
    public function up()
    {
        Schema::create('cash_reconciliations', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('id_cabang');
            $t->foreignId('account_id')->constrained('ledger_accounts');
            $t->date('date');
            $t->bigInteger('statement_balance');
            $t->bigInteger('system_balance');
            $t->string('note', 400);
            $t->unsignedBigInteger('id_user')->nullable();
            $t->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('cash_reconciliations');
    }
}
