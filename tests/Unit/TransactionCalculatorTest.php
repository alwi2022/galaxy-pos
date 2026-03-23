<?php

namespace Tests\Unit;

use App\Support\TransactionCalculator;
use PHPUnit\Framework\TestCase;

class TransactionCalculatorTest extends TestCase
{
    public function test_calculate_from_subtotal_menghasilkan_dpp_ppn_dan_sisa_yang_konsisten()
    {
        $calculator = new TransactionCalculator();
        $result = $calculator->calculateFromSubtotal(200000, 10, 11, 100000);

        $this->assertSame(200000, $result['subtotal']);
        $this->assertSame(20000, $result['diskon_nominal']);
        $this->assertSame(180000, $result['dpp']);
        $this->assertSame(19800, $result['ppn_nominal']);
        $this->assertSame(199800, $result['grand_total']);
        $this->assertSame(100000, $result['dibayar']);
        $this->assertSame(99800, $result['sisa']);
    }

    public function test_allocate_transaction_lines_membagi_diskon_dan_ppn_secara_proporsional()
    {
        $calculator = new TransactionCalculator();
        $lines = [
            ['subtotal' => 100000],
            ['subtotal' => 50000],
        ];

        $allocations = $calculator->allocateTransactionLines($lines, 15000, 14850);

        $this->assertCount(2, $allocations);
        $this->assertSame(10000, $allocations[0]['diskon_nominal']);
        $this->assertSame(5000, $allocations[1]['diskon_nominal']);
        $this->assertSame(90000, $allocations[0]['dpp']);
        $this->assertSame(45000, $allocations[1]['dpp']);
        $this->assertSame(14850, $allocations[0]['ppn_nominal'] + $allocations[1]['ppn_nominal']);
        $this->assertSame(149850, $allocations[0]['grand_total'] + $allocations[1]['grand_total']);
    }
}
