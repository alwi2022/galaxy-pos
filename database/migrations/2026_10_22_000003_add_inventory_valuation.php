<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddInventoryValuation extends Migration
{
    public function up()
    {
        Schema::table('produk', fn (Blueprint $t) => $t->bigInteger('inventory_value')->default(0));
        Schema::table('stock_movements', fn (Blueprint $t) => $t->bigInteger('value')->default(0));
        Schema::table('penjualan_detail', fn (Blueprint $t) => $t->bigInteger('cost_total')->default(0));
    }

    public function down()
    {
        Schema::table('penjualan_detail', fn (Blueprint $t) => $t->dropColumn('cost_total'));
        Schema::table('stock_movements', fn (Blueprint $t) => $t->dropColumn('value'));
        Schema::table('produk', fn (Blueprint $t) => $t->dropColumn('inventory_value'));
    }
}
