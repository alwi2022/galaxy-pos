<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddPhaseOnePaymentSupport extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('penjualan', function (Blueprint $table) {
            $table->string('skema_pembayaran', 20)->default('langsung');
            $table->string('metode_pembayaran', 20)->default('tunai');
            $table->integer('dibayar')->default(0);
            $table->integer('sisa')->default(0);
            $table->string('status_pembayaran', 20)->default('belum_bayar');
            $table->date('jatuh_tempo')->nullable();
        });

        Schema::table('pembelian', function (Blueprint $table) {
            $table->string('skema_pembayaran', 20)->default('langsung');
            $table->string('metode_pembayaran', 20)->default('tunai');
            $table->integer('dibayar')->default(0);
            $table->integer('sisa')->default(0);
            $table->string('status_pembayaran', 20)->default('belum_bayar');
            $table->date('jatuh_tempo')->nullable();
        });

        Schema::create('penjualan_pembayaran', function (Blueprint $table) {
            $table->increments('id_penjualan_pembayaran');
            $table->integer('id_penjualan')->index();
            $table->integer('nominal');
            $table->string('metode_pembayaran', 20)->default('tunai');
            $table->text('keterangan')->nullable();
            $table->integer('id_user')->nullable();
            $table->unsignedBigInteger('id_cabang')->nullable();
            $table->timestamps();
        });

        Schema::create('pembelian_pembayaran', function (Blueprint $table) {
            $table->increments('id_pembelian_pembayaran');
            $table->integer('id_pembelian')->index();
            $table->integer('nominal');
            $table->string('metode_pembayaran', 20)->default('tunai');
            $table->text('keterangan')->nullable();
            $table->integer('id_user')->nullable();
            $table->unsignedBigInteger('id_cabang')->nullable();
            $table->timestamps();
        });

        $this->backfillPenjualan();
        $this->backfillPembelian();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pembelian_pembayaran');
        Schema::dropIfExists('penjualan_pembayaran');

        Schema::table('pembelian', function (Blueprint $table) {
            $table->dropColumn([
                'skema_pembayaran',
                'metode_pembayaran',
                'dibayar',
                'sisa',
                'status_pembayaran',
                'jatuh_tempo',
            ]);
        });

        Schema::table('penjualan', function (Blueprint $table) {
            $table->dropColumn([
                'skema_pembayaran',
                'metode_pembayaran',
                'dibayar',
                'sisa',
                'status_pembayaran',
                'jatuh_tempo',
            ]);
        });
    }

    protected function backfillPenjualan()
    {
        $penjualan = DB::table('penjualan')->get();

        foreach ($penjualan as $item) {
            $totalTagihan = (int) $item->bayar;
            $nominalMasuk = (int) $item->diterima;
            $dibayar = $nominalMasuk > 0
                ? min($nominalMasuk, $totalTagihan)
                : $totalTagihan;

            $sisa = max($totalTagihan - $dibayar, 0);
            $status = $this->resolveStatusPembayaran($totalTagihan, $dibayar);

            DB::table('penjualan')
                ->where('id_penjualan', $item->id_penjualan)
                ->update([
                    'skema_pembayaran' => 'langsung',
                    'metode_pembayaran' => 'tunai',
                    'dibayar' => $dibayar,
                    'sisa' => $sisa,
                    'status_pembayaran' => $status,
                    'jatuh_tempo' => null,
                ]);

            if ($dibayar > 0) {
                DB::table('penjualan_pembayaran')->insert([
                    'id_penjualan' => $item->id_penjualan,
                    'nominal' => $dibayar,
                    'metode_pembayaran' => 'tunai',
                    'keterangan' => 'Pembayaran awal transaksi lama',
                    'id_user' => $item->id_user,
                    'id_cabang' => $item->id_cabang,
                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at,
                ]);
            }
        }
    }

    protected function backfillPembelian()
    {
        $pembelian = DB::table('pembelian')->get();

        foreach ($pembelian as $item) {
            $totalTagihan = (int) $item->bayar;
            $dibayar = $totalTagihan;
            $sisa = max($totalTagihan - $dibayar, 0);
            $status = $this->resolveStatusPembayaran($totalTagihan, $dibayar);

            DB::table('pembelian')
                ->where('id_pembelian', $item->id_pembelian)
                ->update([
                    'skema_pembayaran' => 'langsung',
                    'metode_pembayaran' => 'tunai',
                    'dibayar' => $dibayar,
                    'sisa' => $sisa,
                    'status_pembayaran' => $status,
                    'jatuh_tempo' => null,
                ]);

            if ($dibayar > 0) {
                DB::table('pembelian_pembayaran')->insert([
                    'id_pembelian' => $item->id_pembelian,
                    'nominal' => $dibayar,
                    'metode_pembayaran' => 'tunai',
                    'keterangan' => 'Pembayaran awal transaksi lama',
                    'id_user' => null,
                    'id_cabang' => $item->id_cabang,
                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at,
                ]);
            }
        }
    }

    protected function resolveStatusPembayaran($totalTagihan, $dibayar)
    {
        if ($totalTagihan <= 0 || $dibayar <= 0) {
            return 'belum_bayar';
        }

        if ($dibayar >= $totalTagihan) {
            return 'lunas';
        }

        return 'sebagian';
    }
}
