<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CompleteFinancialWorkflows extends Migration
{
    public function up()
    {
        Schema::table('ledger_entries', fn (Blueprint $t) => $t->boolean('is_closing')->default(false));
        Schema::create('ledger_years', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('id_cabang');
            $t->unsignedInteger('year');
            $t->unsignedBigInteger('entry_id')->nullable();
            $t->bigInteger('profit');
            $t->unsignedBigInteger('id_user')->nullable();
            $t->timestamps();
            $t->unique(['id_cabang', 'year']);
        });
        Schema::table('servis', function (Blueprint $t) {
            $t->bigInteger('dibayar')->default(0);
            $t->bigInteger('sisa')->default(0);
            $t->string('status_pembayaran', 20)->default('belum_bayar');
            $t->date('jatuh_tempo')->nullable();
        });
        Schema::create('servis_pembayaran', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('id_cabang');
            $t->unsignedBigInteger('id_servis');
            $t->date('date');
            $t->bigInteger('nominal');
            $t->string('cash_code', 20);
            $t->string('description', 400);
            $t->unsignedBigInteger('entry_id');
            $t->unsignedBigInteger('id_user')->nullable();
            $t->dateTime('cancelled_at')->nullable();
            $t->timestamps();
            $t->index(['id_cabang', 'id_servis']);
        });
        Schema::create('bank_statement_lines', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('id_cabang');
            $t->foreignId('account_id')->constrained('ledger_accounts');
            $t->date('date');
            $t->string('reference', 150);
            $t->string('description', 400);
            $t->bigInteger('amount');
            $t->string('fingerprint', 64);
            $t->unsignedBigInteger('ledger_line_id')->nullable()->unique();
            $t->unsignedBigInteger('matched_by')->nullable();
            $t->dateTime('matched_at')->nullable();
            $t->timestamps();
            $t->unique(['account_id', 'fingerprint']);
        });
        Schema::create('bank_match_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('statement_line_id')->constrained('bank_statement_lines');
            $t->unsignedBigInteger('ledger_line_id');
            $t->string('action', 20);
            $t->unsignedBigInteger('id_user')->nullable();
            $t->timestamps();
        });
        Schema::create('tax_profiles', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('id_cabang')->unique();
            $t->string('name');
            $t->string('npwp', 16);
            $t->string('nitku', 22);
            $t->string('address', 500);
            $t->boolean('pkp')->default(false);
            $t->timestamps();
        });
        Schema::create('tax_invoices', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('id_cabang');
            $t->string('source_type', 20);
            $t->unsignedInteger('source_id');
            $t->date('date');
            $t->string('invoice_number', 100);
            $t->string('party_name');
            $t->string('party_npwp', 16);
            $t->boolean('creditable')->default(false);
            $t->timestamps();
            $t->unique(['id_cabang', 'source_type', 'source_id']);
            $t->unique(['id_cabang', 'source_type', 'invoice_number']);
        });
        Schema::create('tax_returns', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('id_cabang');
            $t->date('month');
            $t->unsignedInteger('revision');
            $t->bigInteger('output_tax');
            $t->bigInteger('input_tax');
            $t->bigInteger('carry_in');
            $t->bigInteger('payable');
            $t->bigInteger('carry_out');
            $t->string('status', 20)->default('draft');
            $t->string('receipt', 100)->nullable();
            $t->date('filed_at')->nullable();
            $t->bigInteger('offset_amount')->default(0);
            $t->unsignedBigInteger('offset_entry_id')->nullable();
            $t->json('snapshot');
            $t->unsignedBigInteger('id_user')->nullable();
            $t->timestamps();
            $t->unique(['id_cabang', 'month', 'revision']);
        });
        Schema::create('tax_payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('return_id')->constrained('tax_returns');
            $t->unsignedBigInteger('id_cabang');
            $t->date('date');
            $t->bigInteger('amount');
            $t->string('cash_code', 20);
            $t->string('receipt', 100);
            $t->unsignedBigInteger('entry_id');
            $t->timestamps();
            $t->unique(['id_cabang', 'receipt']);
        });
    }

    public function down()
    {
        foreach (['tax_payments', 'tax_returns', 'tax_invoices', 'tax_profiles', 'bank_match_events', 'bank_statement_lines', 'servis_pembayaran', 'ledger_years'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('servis', fn (Blueprint $t) => $t->dropColumn(['dibayar', 'sisa', 'status_pembayaran', 'jatuh_tempo']));
        Schema::table('ledger_entries', fn (Blueprint $t) => $t->dropColumn('is_closing'));
    }
}
