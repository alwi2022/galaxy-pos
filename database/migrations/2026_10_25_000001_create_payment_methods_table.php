<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreatePaymentMethodsTable extends Migration
{
    public function up()
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_cabang');
            $table->string('code', 20);          // disimpan di kolom metode_pembayaran transaksi
            $table->string('type', 10);          // tunai, bank, qris
            $table->string('name', 200);         // label yang tampil di transaksi & nota
            $table->string('account_code', 20);  // akun kas / bank tujuan jurnal
            $table->string('bank_name', 60)->nullable();
            $table->string('account_number', 40)->nullable();
            $table->string('account_holder', 80)->nullable();
            $table->string('address', 200)->nullable();
            $table->string('city', 60)->nullable();
            $table->string('phone', 30)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['id_cabang', 'code']);
        });

        // Rekening kas tambahan yang sudah dibuat sebelumnya ikut terdaftar sebagai rekening bank.
        $accounts = DB::table('ledger_accounts')->where('cash_account', true)->whereNotIn('code', ['1101', '1102', '1103'])->get();
        foreach ($accounts as $account) {
            DB::table('payment_methods')->insert(['id_cabang' => $account->id_cabang, 'code' => 'bank_' . $account->code, 'type' => 'bank', 'name' => $account->name, 'account_code' => $account->code, 'bank_name' => $account->name, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down()
    {
        Schema::dropIfExists('payment_methods');
    }
}
