<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class LinkCashRecords extends Migration
{
    public function up()
    {
        foreach (['pengeluaran', 'pendapatan_lain', 'servis'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->unsignedBigInteger('ledger_entry_id')->nullable();
                $t->dateTime('cancelled_at')->nullable();
            });
        }
    }

    public function down()
    {
        foreach (['pengeluaran', 'pendapatan_lain', 'servis'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn(['ledger_entry_id', 'cancelled_at']));
        }
    }
}
