<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PaymentHelperTest extends TestCase
{
    public function test_status_pembayaran_dihitung_sesuai_nominal()
    {
        $this->assertSame('belum_bayar', status_pembayaran(100000, 0));
        $this->assertSame('sebagian', status_pembayaran(100000, 25000));
        $this->assertSame('lunas', status_pembayaran(100000, 100000));
        $this->assertSame('lunas', status_pembayaran(100000, 150000));
    }

    public function test_label_metode_dan_skema_pembayaran_tersedia()
    {
        $this->assertSame('Tunai', label_metode_pembayaran('tunai'));
        $this->assertSame('Bank / Transfer', label_metode_pembayaran('transfer_bank'));
        $this->assertSame('QRIS', label_metode_pembayaran('qris'));
        $this->assertSame('Kredit / Hutang', label_skema_pembayaran('kredit'));
    }

    public function test_label_kategori_keuangan_tersedia()
    {
        $this->assertSame('Listrik', label_kategori_pengeluaran('listrik'));
        $this->assertSame('Pendapatan Lain-lain', label_kategori_pendapatan_lain('pendapatan_lain_lain'));
    }
}
