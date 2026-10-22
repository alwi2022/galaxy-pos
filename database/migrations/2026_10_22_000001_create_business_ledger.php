<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBusinessLedger extends Migration
{
    public function up()
    {
        Schema::create('ledger_accounts', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('id_cabang');
            $t->string('code', 20);
            $t->string('name');
            $t->string('type', 20);
            $t->boolean('cash_account')->default(false);
            $t->timestamps();
            $t->unique(['id_cabang', 'code']);
        });
        Schema::create('ledger_periods', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('id_cabang');
            $t->date('month');
            $t->boolean('closed')->default(false);
            $t->timestamps();
            $t->unique(['id_cabang', 'month']);
        });
        Schema::create('ledger_entries', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('id_cabang');
            $t->date('date');
            $t->string('reference')->unique();
            $t->string('description', 500);
            $t->unsignedBigInteger('id_user')->nullable();
            $t->unsignedBigInteger('reversal_of')->nullable()->unique();
            $t->timestamps();
            $t->index(['id_cabang', 'date']);
        });
        Schema::create('ledger_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entry_id')->constrained('ledger_entries');
            $t->foreignId('account_id')->constrained('ledger_accounts');
            $t->bigInteger('debit')->default(0);
            $t->bigInteger('credit')->default(0);
        });
        Schema::create('stock_movements', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('id_cabang');
            $t->unsignedInteger('id_produk');
            $t->dateTime('occurred_at');
            $t->string('reference');
            $t->string('reason', 500);
            $t->integer('quantity');
            $t->integer('balance');
            $t->bigInteger('unit_cost');
            $t->unsignedBigInteger('id_user')->nullable();
            $t->timestamps();
            $t->unique(['reference', 'id_produk']);
            $t->index(['id_cabang', 'id_produk']);
        });
        Schema::create('stock_returns', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('id_cabang');
            $t->string('type', 20);
            $t->unsignedInteger('transaction_id');
            $t->unsignedInteger('detail_id');
            $t->unsignedInteger('id_produk');
            $t->date('date');
            $t->unsignedInteger('quantity');
            $t->boolean('restock');
            $t->bigInteger('amount');
            $t->bigInteger('tax');
            $t->bigInteger('refund');
            $t->bigInteger('unit_cost');
            $t->string('reason', 500);
            $t->unsignedBigInteger('id_user')->nullable();
            $t->timestamps();
        });
        Schema::create('business_assets', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('id_cabang');
            $t->string('name');
            $t->date('purchased_at');
            $t->bigInteger('cost');
            $t->bigInteger('residual');
            $t->unsignedInteger('life_months');
            $t->bigInteger('depreciated')->default(0);
            $t->date('disposed_at')->nullable();
            $t->timestamps();
        });
        Schema::create('business_loans', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('id_cabang');
            $t->string('party');
            $t->string('direction', 20);
            $t->date('date');
            $t->date('due_date');
            $t->bigInteger('principal');
            $t->bigInteger('outstanding');
            $t->timestamps();
        });
        Schema::create('business_deferrals', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('id_cabang');
            $t->string('description');
            $t->string('type', 20);
            $t->date('start_date');
            $t->unsignedInteger('months');
            $t->bigInteger('amount');
            $t->bigInteger('recognized')->default(0);
            $t->timestamps();
        });
        foreach (['penjualan', 'pembelian'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dateTime('posted_at')->nullable();
                $t->dateTime('cancelled_at')->nullable();
                $t->bigInteger('returned_total')->default(0);
                $t->bigInteger('refunded_total')->default(0);
            });
        }
        Schema::table('penjualan_detail', function (Blueprint $t) {
            $t->bigInteger('unit_cost')->default(0);
        });
    }

    public function down()
    {
        Schema::table('penjualan_detail', fn (Blueprint $t) => $t->dropColumn('unit_cost'));
        foreach (['penjualan', 'pembelian'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn(['posted_at', 'cancelled_at', 'returned_total', 'refunded_total']));
        }
        foreach (['business_deferrals', 'business_loans', 'business_assets', 'stock_returns', 'stock_movements', 'ledger_lines', 'ledger_entries', 'ledger_periods', 'ledger_accounts'] as $table) {
            Schema::dropIfExists($table);
        }
    }
}
