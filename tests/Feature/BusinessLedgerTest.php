<?php

namespace Tests\Feature;

use App\Models\Penjualan;
use App\Models\PenjualanPembayaran;
use App\Models\Pengeluaran;
use App\Models\User;
use App\Services\BusinessFinanceService;
use App\Services\BusinessTransactionService;
use App\Services\LedgerService;
use App\Services\StockLedgerService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BusinessLedgerTest extends TestCase
{
    use RefreshDatabase;

    private int $product;
    private int $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-22 12:00:00', 'Asia/Jakarta'));
        $branch = DB::table('cabang')->insertGetId(['nama_cabang' => 'Test']);
        $user = User::factory()->create(['level' => 1, 'id_cabang' => $branch]);
        $this->actingAs($user);
        $category = DB::table('kategori')->insertGetId(['nama_kategori' => 'Test', 'id_cabang' => $branch]);
        $this->supplier = DB::table('supplier')->insertGetId(['nama' => 'Supplier', 'telepon' => '000', 'id_cabang' => $branch]);
        $this->product = DB::table('produk')->insertGetId(['id_kategori' => $category, 'kode_produk' => 'TEST', 'nama_produk' => 'Test', 'harga_beli' => 10000, 'harga_jual' => 20000, 'stok' => 0, 'inventory_value' => 0, 'id_cabang' => $branch]);
        DB::table('setting')->insert(['nama_perusahaan' => 'Test', 'alamat' => '-', 'telepon' => '000', 'tipe_nota' => 1, 'diskon' => 0, 'ppn_default' => 11, 'path_logo' => '/img/logo.png', 'path_kartu_member' => '/img/member.png']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function transaction(string $type, int $quantity, int $unit, int $paid = 0): int
    {
        $subtotal = $quantity * $unit;
        $data = ['id_cabang' => 1, 'total_item' => $quantity, 'total_harga' => $subtotal, 'diskon' => 0, 'ppn_persen' => 11, 'ppn_nominal' => (int) round($subtotal * .11), 'bayar' => (int) round($subtotal * 1.11), 'created_at' => now(), 'updated_at' => now()];
        $data += $type === 'penjualan' ? ['id_user' => auth()->id(), 'id_member' => null] : ['id_supplier' => $this->supplier];
        $id = DB::table($type)->insertGetId($data);
        DB::table($type . '_detail')->insert(['id_' . $type => $id, 'id_produk' => $this->product, $type === 'penjualan' ? 'harga_jual' : 'harga_beli' => $unit, 'jumlah' => $quantity, 'subtotal' => $subtotal]);
        app(BusinessTransactionService::class)->post($type, $id, 1);
        if ($paid > 0) {
            $class = $type === 'penjualan' ? PenjualanPembayaran::class : \App\Models\PembelianPembayaran::class;
            $class::create(['id_' . $type => $id, 'nominal' => $paid, 'metode_pembayaran' => 'tunai', 'id_user' => auth()->id(), 'id_cabang' => 1]);
        }
        $class = $type === 'penjualan' ? Penjualan::class : \App\Models\Pembelian::class;
        $class::find($id)->syncStatusPembayaran();
        return $id;
    }

    private function returnData(int $id, int $quantity = 1, bool $restock = true, string $type = 'penjualan'): array
    {
        return ['type' => $type, 'transaction_id' => $id, 'detail_id' => DB::table($type . '_detail')->where('id_' . $type, $id)->value('id_' . $type . '_detail'), 'quantity' => $quantity, 'restock' => $restock, 'date' => '2026-10-22', 'method' => 'tunai', 'reason' => 'Test retur'];
    }

    public function test_purchase_sale_and_return_keep_inventory_and_ledger_consistent(): void
    {
        $this->transaction('pembelian', 10, 10000, 111000);
        $sale = $this->transaction('penjualan', 3, 20000, 66600);
        app(BusinessTransactionService::class)->returnItem(1, $this->returnData($sale));
        $this->assertDatabaseHas('produk', ['id_produk' => $this->product, 'stok' => 8, 'inventory_value' => 80000]);
        $this->assertDatabaseHas('penjualan', ['id_penjualan' => $sale, 'returned_total' => 22200, 'refunded_total' => 22200, 'sisa' => 0]);
        $b = app(LedgerService::class)->balances(1, '2026-10-22')->keyBy('code');
        $this->assertEquals(80000, $b['1301']->balance);
        $this->assertEquals(20000, $b['5101']->balance);
        $this->assertEquals(0, $b->sum('balance'));
    }

    public function test_return_offsets_unpaid_invoice_before_refunding_cash(): void
    {
        $this->transaction('pembelian', 10, 10000);
        $sale = $this->transaction('penjualan', 3, 20000, 10000);
        app(BusinessTransactionService::class)->returnItem(1, $this->returnData($sale, 1, false));
        $this->assertDatabaseHas('penjualan', ['id_penjualan' => $sale, 'sisa' => 34400, 'refunded_total' => 0]);
        $this->assertDatabaseHas('produk', ['id_produk' => $this->product, 'stok' => 7]);
        Penjualan::find($sale)->syncStatusPembayaran();
        $this->assertDatabaseHas('penjualan', ['id_penjualan' => $sale, 'sisa' => 34400]);
    }

    public function test_over_return_rolls_back_every_change(): void
    {
        $this->transaction('pembelian', 10, 10000);
        $sale = $this->transaction('penjualan', 1, 20000);
        try {
            app(BusinessTransactionService::class)->returnItem(1, $this->returnData($sale, 2));
            $this->fail('Over-return accepted');
        } catch (ValidationException $e) {
            $this->assertDatabaseCount('stock_returns', 0);
            $this->assertDatabaseHas('produk', ['id_produk' => $this->product, 'stok' => 9]);
        }
    }

    public function test_insufficient_stock_does_not_post_sale(): void
    {
        $this->expectException(ValidationException::class);
        $this->transaction('penjualan', 1, 20000);
    }

    public function test_duplicate_finalization_is_rejected(): void
    {
        $id = $this->transaction('pembelian', 10, 10000);
        try {
            app(BusinessTransactionService::class)->post('pembelian', $id, 1);
            $this->fail('Duplicate accepted');
        } catch (ValidationException $e) {
            $this->assertDatabaseHas('produk', ['id_produk' => $this->product, 'stok' => 10]);
            $this->assertDatabaseCount('stock_movements', 1);
        }
    }

    public function test_cancellation_reverses_sale_and_payments_without_deleting_history(): void
    {
        $this->transaction('pembelian', 10, 10000);
        $sale = $this->transaction('penjualan', 2, 20000, 44400);
        app(BusinessTransactionService::class)->cancel('penjualan', $sale, 1);
        $this->assertNotNull(DB::table('penjualan')->where('id_penjualan', $sale)->value('cancelled_at'));
        $this->assertDatabaseHas('produk', ['id_produk' => $this->product, 'stok' => 10, 'inventory_value' => 100000]);
        $balances = app(LedgerService::class)->balances(1, '2026-10-22')->keyBy('code');
        $this->assertEquals(0, $balances['4101']->balance);
        $this->assertEquals(0, $balances['1101']->balance);
    }

    public function test_stock_opname_posts_valuation_difference(): void
    {
        $this->transaction('pembelian', 10, 10000);
        app(StockLedgerService::class)->adjustment(1, $this->product, 8, 'opname', 'Count', now()->toDateTimeString());
        $this->assertDatabaseHas('produk', ['id_produk' => $this->product, 'stok' => 8, 'inventory_value' => 80000]);
        $this->assertEquals(20000, app(LedgerService::class)->balances(1, '2026-10-22')->keyBy('code')['6102']->balance);
    }

    public function test_month_end_is_repeatable_and_closed_period_rejects_posting(): void
    {
        $finance = app(BusinessFinanceService::class);
        $finance->asset(1, ['date' => '2026-09-01', 'name' => 'PC', 'amount' => 1200000, 'residual' => 0, 'life_months' => 12, 'cash' => '1101']);
        $finance->defer(1, ['date' => '2026-09-01', 'type' => 'expense', 'description' => 'Rent', 'amount' => 1200000, 'months' => 12, 'cash' => '1101']);
        $finance->monthEnd(1, '2026-09-01', false);
        $finance->monthEnd(1, '2026-09-01', true);
        $this->assertDatabaseHas('business_assets', ['depreciated' => 100000]);
        $this->assertDatabaseHas('business_deferrals', ['recognized' => 100000]);
        $this->expectException(ValidationException::class);
        $finance->money(1, ['date' => '2026-09-20', 'type' => 'capital', 'cash' => '1101', 'amount' => 1000, 'description' => 'Forbidden']);
    }

    public function test_loan_repayment_separates_interest_and_principal(): void
    {
        $finance = app(BusinessFinanceService::class);
        $id = $finance->loan(1, ['date' => '2026-10-01', 'party' => 'Bank', 'direction' => 'borrowed', 'due_date' => '2027-10-01', 'amount' => 1000000, 'cash' => '1102']);
        $finance->repay(1, $id, ['date' => '2026-10-22', 'principal' => 100000, 'interest' => 10000, 'cash' => '1102']);
        $this->assertDatabaseHas('business_loans', ['id' => $id, 'outstanding' => 900000]);
        $b = app(LedgerService::class)->balances(1, '2026-10-22')->keyBy('code');
        $this->assertEquals(-900000, $b['2102']->balance);
        $this->assertEquals(10000, $b['6104']->balance);
    }

    public function test_operational_expense_edit_and_cancel_preserve_journal_history(): void
    {
        $expense = Pengeluaran::create(['tanggal_pengeluaran' => '2026-10-22', 'kategori_pengeluaran' => 'listrik', 'deskripsi' => 'Listrik', 'nominal' => 50000, 'metode_pembayaran' => 'tunai', 'id_cabang' => 1]);
        $expense->update(['nominal' => 60000]);
        $b = app(LedgerService::class)->balances(1, '2026-10-22')->keyBy('code');
        $this->assertEquals(60000, $b['6101']->balance);
        $expense->delete();
        $this->assertEquals(0, Pengeluaran::count());
        $this->assertDatabaseCount('pengeluaran', 1);
        $this->assertEquals(0, app(LedgerService::class)->balances(1, '2026-10-22')->sum('balance'));
    }

    public function test_other_branch_cannot_return_transaction(): void
    {
        $purchase = $this->transaction('pembelian', 10, 10000);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(BusinessTransactionService::class)->returnItem(2, $this->returnData($purchase, 1, false, 'pembelian'));
    }

    public function test_business_pages_render_and_cashier_is_denied(): void
    {
        foreach (array_keys(config('business')) as $module) {
            $this->get('/usaha/' . $module)->assertOk();
        }
        $this->actingAs(User::factory()->create(['level' => 2, 'id_cabang' => 1]));
        $this->get('/usaha/akuntansi')->assertRedirect();
    }

    public function test_purchase_return_reduces_supplier_balance_and_inventory(): void
    {
        $purchase = $this->transaction('pembelian', 10, 10000, 50000);
        app(BusinessTransactionService::class)->returnItem(1, $this->returnData($purchase, 2, false, 'pembelian'));
        $this->assertDatabaseHas('pembelian', ['id_pembelian' => $purchase, 'returned_total' => 22200, 'refunded_total' => 0, 'sisa' => 38800]);
        $this->assertDatabaseHas('produk', ['id_produk' => $this->product, 'stok' => 8, 'inventory_value' => 80000]);
        $balances = app(LedgerService::class)->balances(1, '2026-10-22')->keyBy('code');
        $this->assertEquals(-38800, $balances['2101']->balance);
        $this->assertEquals(80000, $balances['1301']->balance);
    }

    public function test_fractional_average_cost_exhausts_inventory_without_rounding_residue(): void
    {
        $this->transaction('pembelian', 3, 10001);
        $this->transaction('pembelian', 2, 10002);
        for ($i = 0; $i < 5; $i++) {
            $this->transaction('penjualan', 1, 20000);
        }
        $this->assertDatabaseHas('produk', ['id_produk' => $this->product, 'stok' => 0, 'inventory_value' => 0]);
        $b = app(LedgerService::class)->balances(1, '2026-10-22')->keyBy('code');
        $this->assertEquals(0, $b['1301']->balance);
        $this->assertEquals(50007, $b['5101']->balance);
    }

    public function test_finalized_detail_cannot_be_modified_through_existing_endpoint(): void
    {
        $this->transaction('pembelian', 10, 10000);
        $sale = $this->transaction('penjualan', 1, 20000);
        $detail = DB::table('penjualan_detail')->where('id_penjualan', $sale)->value('id_penjualan_detail');
        $this->putJson('/transaksi/' . $detail, ['jumlah' => 4])->assertUnprocessable();
        $this->assertDatabaseHas('penjualan_detail', ['id_penjualan_detail' => $detail, 'jumlah' => 1]);
        $this->assertDatabaseHas('produk', ['id_produk' => $this->product, 'stok' => 9]);
    }

    public function test_cash_transfer_is_neutral_and_reconciliation_saves_difference(): void
    {
        $this->post('/usaha/simpan/money', ['date' => '2026-10-22', 'type' => 'capital', 'cash' => '1101', 'amount' => 100000, 'description' => 'Modal'])->assertRedirect();
        $this->post('/usaha/simpan/money', ['date' => '2026-10-22', 'type' => 'transfer', 'cash' => '1101', 'destination' => '1102', 'amount' => 25000, 'description' => 'Setor bank'])->assertRedirect();
        $b = app(LedgerService::class)->balances(1, '2026-10-22')->keyBy('code');
        $this->assertEquals(75000, $b['1101']->balance);
        $this->assertEquals(25000, $b['1102']->balance);
        $this->post('/usaha/simpan/reconcile', ['date' => '2026-10-22', 'cash' => '1102', 'amount' => 24000, 'description' => 'Bank statement'])->assertRedirect();
        $this->assertDatabaseHas('cash_reconciliations', ['statement_balance' => 24000, 'system_balance' => 25000]);
        $this->get('/laporan/keuangan/cash')->assertOk()->assertViewHas('cashFlows', fn ($flows) => $flows['Pendanaan'] === 100000 && array_sum($flows) === 100000);
    }

    public function test_asset_disposal_recognizes_book_value_and_gain(): void
    {
        $finance = app(BusinessFinanceService::class);
        $id = $finance->asset(1, ['date' => '2026-09-01', 'name' => 'PC', 'amount' => 1200000, 'residual' => 0, 'life_months' => 12, 'cash' => '1102']);
        $finance->monthEnd(1, '2026-09-01', false);
        $finance->dispose(1, $id, ['date' => '2026-10-22', 'amount' => 1150000, 'cash' => '1102']);
        $this->assertDatabaseHas('business_assets', ['id' => $id, 'disposed_at' => '2026-10-22']);
        $b = app(LedgerService::class)->balances(1, '2026-10-22')->keyBy('code');
        $this->assertEquals(-50000, $b['4105']->balance);
        $this->assertEquals(0, $b['1601']->balance);
        $this->assertEquals(0, $b['1602']->balance);
    }

    public function test_servis_taken_status_does_not_duplicate_income(): void
    {
        $s = \App\Models\Servis::create(['kode_servis' => 'TEST-SERVICE', 'status' => 'selesai', 'biaya_servis' => 100000, 'tanggal_selesai' => now(), 'id_cabang' => 1, 'id_user' => auth()->id()]);
        $s->update(['status' => 'diambil']);
        $b = app(LedgerService::class)->balances(1, '2026-10-22')->keyBy('code');
        $this->assertEquals(-100000, $b['4102']->balance);
        $this->assertEquals(0, $b['1101']->balance);
        $this->assertEquals(100000, $b['1203']->balance);
    }

    public function test_cash_summary_accounts_for_return_and_cancellation(): void
    {
        $this->transaction('pembelian', 10, 10000, 111000);
        $sale = $this->transaction('penjualan', 2, 20000, 44400);
        app(BusinessTransactionService::class)->returnItem(1, $this->returnData($sale, 1));
        $summary = app(\App\Services\LaporanService::class)->ringkasan('2026-10-22', '2026-10-22', 1);
        $this->assertEquals(22200, $summary[0]['penjualan_masuk']);
        $this->assertEquals(111000, $summary[0]['pembelian_keluar']);
        $this->assertEquals(-88800, $summary[0]['pendapatan_bersih']);
    }

    public function test_journal_rejects_unbalanced_entries(): void
    {
        $ledger = app(LedgerService::class);
        $count = DB::table('ledger_entries')->count();
        try {
            $ledger->post(1, '2026-10-22', 'BAD', 'Bad entry', [['1101', 100, 0], ['3101', 0, 99]]);
            $this->fail('Unbalanced journal accepted');
        } catch (ValidationException $e) {
            $this->assertDatabaseCount('ledger_entries', $count);
        }
    }

    public function test_demo_seeder_builds_balanced_books_and_is_repeatable(): void
    {
        $this->seed(\Database\Seeders\UserTableSeeder::class);
        $this->seed(\Database\Seeders\KategoriTableSeeder::class);
        $this->seed(\Database\Seeders\BusinessDemoSeeder::class);
        $count = DB::table('ledger_entries')->count();
        $this->seed(\Database\Seeders\BusinessDemoSeeder::class);
        $this->assertDatabaseCount('ledger_entries', $count);
        $this->assertEquals(88, DB::table('penjualan')->count());
        $this->assertEquals(4, DB::table('pembelian')->count());
        $imbalanced = DB::table('ledger_lines')->selectRaw('entry_id, SUM(debit)-SUM(credit) as difference')->groupBy('entry_id')->havingRaw('SUM(debit)-SUM(credit) != 0')->get();
        $this->assertCount(0, $imbalanced);
        $balances = app(LedgerService::class)->balances(1, '2026-10-22')->keyBy('code');
        $this->assertEquals(DB::table('produk')->sum('inventory_value'), $balances['1301']->balance);
        $this->assertEquals(DB::table('penjualan')->sum('sisa'), $balances['1201']->balance);
        $this->assertEquals(DB::table('pembelian')->sum('sisa'), -$balances['2101']->balance);
        $this->assertEquals('2026-10-22', now()->toDateString());
    }

    public function test_new_finance_form_actions_and_journal_export(): void
    {
        $common = ['date' => '2026-10-22', 'cash' => '1101'];
        $this->post('/usaha/simpan/loan', $common + ['party' => 'Test bank', 'direction' => 'borrowed', 'due_date' => '2027-10-22', 'amount' => 1000000])->assertSessionHasNoErrors()->assertRedirect();
        $loan = DB::table('business_loans')->value('id');
        $this->post('/usaha/simpan/repay', $common + ['id' => $loan, 'principal' => 100000, 'interest' => 5000])->assertSessionHasNoErrors()->assertRedirect();
        $this->post('/usaha/simpan/asset', $common + ['name' => 'Test asset', 'amount' => 100000, 'residual' => 0, 'life_months' => 12])->assertSessionHasNoErrors()->assertRedirect();
        $asset = DB::table('business_assets')->value('id');
        $this->post('/usaha/simpan/dispose', $common + ['id' => $asset, 'amount' => 95000])->assertSessionHasNoErrors()->assertRedirect();
        $this->post('/usaha/simpan/defer', $common + ['type' => 'expense', 'description' => 'Rent', 'amount' => 12000, 'months' => 12])->assertSessionHasNoErrors()->assertRedirect();
        $this->post('/usaha/simpan/account', ['name' => 'Test rekening'])->assertSessionHasNoErrors()->assertRedirect();
        $this->post('/usaha/simpan/journal', ['date' => '2026-10-22', 'description' => 'Adjustment', 'debit_account' => '6101', 'credit_account' => '4103', 'amount' => 1000])->assertSessionHasNoErrors()->assertRedirect();
        $entry = DB::table('ledger_entries')->where('reference', 'like', 'MANUAL-%')->value('id');
        $this->post('/usaha/simpan/reverse', ['date' => '2026-10-22', 'id' => $entry])->assertSessionHasNoErrors()->assertRedirect();
        $response = $this->get('/usaha/export/jurnal?start=2026-10-01&end=2026-10-22')->assertOk();
        $this->assertStringContainsString('Tanggal,Referensi,Keterangan', $response->streamedContent());
    }

    public function test_year_close_transfers_profit_and_drawings_without_erasing_profit_report(): void
    {
        $finance=app(BusinessFinanceService::class);
        foreach ([['income',600000],['expense',100000],['withdrawal',50000]] as [$type,$amount]) {
            $finance->money(1,['date'=>'2025-12-31','type'=>$type,'cash'=>'1101','amount'=>$amount,'description'=>'Year close test']);
        }
        $finance->closeYear(1,2025);
        $balances=app(LedgerService::class)->balances(1,'2025-12-31')->keyBy('code');
        $this->assertEquals(-450000,$balances['3103']->balance);
        $this->assertEquals(0,$balances['3102']->balance);
        $this->assertEquals(0,$balances['4103']->balance);
        $this->assertEquals(0,$balances['6101']->balance);
        $history=app(LedgerService::class)->balances(1,'2025-12-31','2025-01-01',true);
        $this->assertEquals(500000,-$history->whereIn('type',['income','expense'])->sum('balance'));
        $this->assertDatabaseHas('ledger_years',['id_cabang'=>1,'year'=>2025,'profit'=>500000]);
        $count=DB::table('ledger_entries')->count();
        try { $finance->closeYear(1,2025); $this->fail('Duplicate year close accepted'); }
        catch (ValidationException $e) { $this->assertDatabaseCount('ledger_entries',$count); }
        $this->expectException(ValidationException::class);
        $finance->money(1,['date'=>'2025-12-31','type'=>'income','cash'=>'1101','amount'=>1,'description'=>'Locked']);
    }

    public function test_service_instalments_and_reversal_keep_receivable_correct(): void
    {
        $s=\App\Models\Servis::create(['kode_servis'=>'INSTALMENT','status'=>'selesai','biaya_servis'=>150000,'tanggal_selesai'=>now(),'id_cabang'=>1,'id_user'=>auth()->id()]);
        $service=app(\App\Services\ServicePaymentService::class);
        $first=$service->pay(1,$s->id_servis,['date'=>'2026-10-22','amount'=>50000,'cash'=>'1101','description'=>'First']);
        $second=$service->pay(1,$s->id_servis,['date'=>'2026-10-22','amount'=>100000,'cash'=>'1102','description'=>'Final']);
        $this->assertDatabaseHas('servis',['id_servis'=>$s->id_servis,'sisa'=>0,'dibayar'=>150000,'status_pembayaran'=>'lunas']);
        $service->cancel(1,$second,'2026-10-22');
        $this->assertDatabaseHas('servis',['id_servis'=>$s->id_servis,'sisa'=>100000,'dibayar'=>50000,'status_pembayaran'=>'sebagian']);
        $balances=app(LedgerService::class)->balances(1,'2026-10-22')->keyBy('code');
        $this->assertEquals(100000,$balances['1203']->balance);
        $this->assertEquals(-150000,$balances['4102']->balance);
        $this->assertDatabaseHas('servis_pembayaran',['id'=>$first,'cancelled_at'=>null]);
        $this->expectException(ValidationException::class);
        $service->pay(1,$s->id_servis,['date'=>'2026-10-22','amount'=>100001,'cash'=>'1101','description'=>'Too much']);
    }

    public function test_bank_import_deduplicates_and_matches_each_line_once(): void
    {
        $entry=app(BusinessFinanceService::class)->money(1,['date'=>'2026-10-22','type'=>'capital','cash'=>'1102','amount'=>100000,'description'=>'Bank deposit']);
        $bank=app(\App\Services\BankReconciliationService::class);
        $path=$this->bankCsv([['2026-10-22','BANK-001','Deposit',100000],['2026-10-22','BANK-FEE','Fee',-6500]]);
        try {
            $this->assertSame(['added'=>2,'skipped'=>0],$bank->import(1,'1102',$path));
            $this->assertSame(['added'=>0,'skipped'=>2],$bank->import(1,'1102',$path));
        } finally { unlink($path); }
        $cashId=DB::table('ledger_accounts')->where('code','1102')->value('id');
        $line=DB::table('ledger_lines')->where('entry_id',$entry)->where('account_id',$cashId)->value('id');
        $statement=DB::table('bank_statement_lines')->where('reference','BANK-001')->value('id');
        $this->get('/usaha/rekonsiliasi')->assertOk();
        $this->getJson('/usaha/rekonsiliasi?' . http_build_query(['data_table'=>'bank_page','draw'=>1,'offset'=>0,'length'=>10]))->assertOk()->assertJsonPath('recordsTotal',2)->assertSee('BANK-001');
        $bank->match(1,$statement,$line);
        $this->assertDatabaseHas('bank_statement_lines',['id'=>$statement,'ledger_line_id'=>$line]);
        $bank->unmatch(1,$statement);
        $bank->match(1,$statement,$line);
        $this->assertDatabaseCount('bank_match_events',3);
        $fee=DB::table('bank_statement_lines')->where('reference','BANK-FEE')->value('id');
        $bank->adjust(1,$fee,'6101');
        $this->assertNotNull(DB::table('bank_statement_lines')->where('id',$fee)->value('ledger_line_id'));
        $this->assertEquals(93500,app(LedgerService::class)->balances(1,'2026-10-22')->keyBy('code')['1102']->balance);
        $this->expectException(ValidationException::class);
        $bank->match(1,$statement,$line);
    }

    public function test_invalid_bank_row_does_not_import_partial_statement(): void
    {
        $path=$this->bankCsv([['2026-10-22','VALID','Valid',100],['2026-02-31','INVALID','Bad date',200]]);
        try { app(\App\Services\BankReconciliationService::class)->import(1,'1102',$path); $this->fail('Invalid date accepted'); }
        catch (ValidationException $e) { $this->assertDatabaseCount('bank_statement_lines',0); }
        finally { unlink($path); }
    }

    public function test_transaction_and_product_reports_include_returns_in_the_right_period(): void
    {
        $this->transaction('pembelian',10,10000);
        $sale=$this->transaction('penjualan',3,20000,66600);
        app(BusinessTransactionService::class)->returnItem(1,$this->returnData($sale,1));
        $report=app(\App\Services\LaporanService::class);
        $invoice=$report->penjualanPeriode('2026-10-01','2026-10-22',1)->first();
        $this->assertEquals(2,$invoice['total_item']);
        $this->assertEquals(44400,$invoice['grand_total']);
        $this->assertEquals(44400,$invoice['dibayar']);
        $product=$report->laporanProduk('2026-10-01','2026-10-22',1)->first();
        $this->assertEquals(2,$product['qty_jual']);
        $this->assertEquals(44400,$product['penjualan_total']);
        Carbon::setTestNow(Carbon::parse('2026-11-02 12:00:00','Asia/Jakarta'));
        $data=$this->returnData($sale,1); $data['date']='2026-11-02';
        app(BusinessTransactionService::class)->returnItem(1,$data);
        $later=$report->laporanProduk('2026-11-01','2026-11-02',1)->first();
        $this->assertEquals(-1,$later['qty_jual']);
        $this->assertEquals(-22200,$later['penjualan_total']);
        $this->assertEquals(44400,$report->penjualanPeriode('2026-10-01','2026-10-31',1)->first()['grand_total']);
    }

    public function test_tax_draft_credits_only_verified_input_and_contains_signed_returns(): void
    {
        $purchase=$this->transaction('pembelian',10,10000);
        $sale=$this->transaction('penjualan',3,20000);
        app(BusinessTransactionService::class)->returnItem(1,$this->returnData($sale,1));
        $tax=app(\App\Services\TaxReportingService::class);
        $first=$tax->prepare(1,'2026-10-01');
        $this->assertDatabaseHas('tax_returns',['id'=>$first,'output_tax'=>4400,'input_tax'=>0,'payable'=>4400]);
        $this->taxDocument('pembelian',$purchase,true);
        $second=$tax->prepare(1,'2026-10-01');
        $this->assertDatabaseHas('tax_returns',['id'=>$second,'revision'=>2,'output_tax'=>4400,'input_tax'=>11000,'carry_out'=>6600]);
        $snapshot=json_decode(DB::table('tax_returns')->where('id',$second)->value('snapshot'),true);
        $this->assertCount(1,array_filter($snapshot,fn($e)=>$e['tax']===-2200));
    }

    public function test_tax_settlements_offset_input_once_and_record_filing_receipt(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-22 12:00:00','Asia/Jakarta'));
        $purchase=$this->transaction('pembelian',10,10000);
        $this->transaction('penjualan',5,40000);
        $this->taxDocument('pembelian',$purchase,true);
        $tax=app(\App\Services\TaxReportingService::class);
        $id=$tax->prepare(1,'2026-09-01');
        $this->assertDatabaseHas('tax_returns',['id'=>$id,'payable'=>11000]);
        Carbon::setTestNow(Carbon::parse('2026-10-22 12:00:00','Asia/Jakarta'));
        $tax->payment(1,$id,['date'=>'2026-10-01','cash'=>'1102','amount'=>5000,'receipt'=>'NTPN-TEST-1']);
        $tax->payment(1,$id,['date'=>'2026-10-02','cash'=>'1102','amount'=>6000,'receipt'=>'NTPN-TEST-2']);
        $this->assertEquals(1,DB::table('ledger_entries')->where('reference','like','TAX-OFFSET-%')->count());
        $this->taxProfile();
        $tax->filed(1,$id,'2026-10-03','BPE-TEST');
        $this->assertDatabaseHas('tax_returns',['id'=>$id,'status'=>'filed','receipt'=>'BPE-TEST']);
        $b=app(LedgerService::class)->balances(1,'2026-10-22')->keyBy('code');
        $this->assertEquals(0,$b['2201']->balance);
        $this->assertEquals(0,$b['1401']->balance);
    }

    public function test_retail_xml_escapes_identity_and_blocks_unsupported_corrections(): void
    {
        $this->transaction('pembelian',10,10000);
        $sale=$this->transaction('penjualan',2,20000);
        $this->taxDocument('penjualan',$sale,false);
        $this->taxProfile();
        $tax=app(\App\Services\TaxReportingService::class);
        $id=$tax->prepare(1,'2026-10-01');
        $xml=$tax->retailXml(1,$id);
        $document=new \DOMDocument(); $this->assertTrue($document->loadXML($xml));
        $this->assertEquals('RetailInvoiceBulk',$document->documentElement->nodeName);
        $this->assertEquals('Buyer & Test',$document->getElementsByTagName('BuyerName')->item(0)->textContent);
        $this->get('/usaha/export/pajak/'.$id.'/csv')->assertOk();
        $this->get('/usaha/export/pajak/'.$id.'/pdf')->assertOk();
        app(BusinessTransactionService::class)->returnItem(1,$this->returnData($sale));
        $revision=$tax->prepare(1,'2026-10-01');
        $this->expectException(ValidationException::class);
        $tax->retailXml(1,$revision);
    }

    public function test_tax_payment_rejects_stale_snapshot(): void
    {
        $this->transaction('pembelian',10,10000);
        $sale=$this->transaction('penjualan',2,20000);
        $tax=app(\App\Services\TaxReportingService::class);
        $id=$tax->prepare(1,'2026-10-01');
        app(BusinessTransactionService::class)->returnItem(1,$this->returnData($sale));
        $this->expectException(ValidationException::class);
        $tax->payment(1,$id,['date'=>'2026-10-22','cash'=>'1101','amount'=>1,'receipt'=>'STALE']);
    }

    public function test_new_lists_search_paginate_and_old_pages_do_not_load_new_styles(): void
    {
        $this->transaction('pembelian',10,10000);
        app(StockLedgerService::class)->adjustment(1,$this->product,1,'internal','Specific-Search',now()->toDateTimeString());
        $this->get('/usaha/stok?q=Specific-Search&per_page=10')->assertOk()->assertViewHas('movements',fn($rows)=>$rows->total()===1 && $rows->perPage()===10);
        $this->getJson('/usaha/stok?' . http_build_query(['data_table'=>'stock_page','draw'=>1,'offset'=>0,'length'=>10,'search'=>['value'=>'Missing']]))->assertOk()->assertJsonPath('recordsFiltered',0)->assertJsonPath('data',[]);
        $this->get('/produk')->assertOk()->assertDontSee('css/galaxy-ui.css',false)->assertDontSee('js/galaxy-ui.js',false);
    }

    public function test_datatables_paginate_search_sort_and_reject_unrelated_tables(): void
    {
        $this->transaction('pembelian',100,10000);
        foreach(range(0,12) as $index) {
            app(StockLedgerService::class)->adjustment(1,$this->product,1,'internal','Batch '.str_pad((string)$index,2,'0',STR_PAD_LEFT),now()->toDateTimeString());
        }
        $query = ['data_table'=>'stock_page','draw'=>9,'offset'=>10,'length'=>10,'search'=>['value'=>'Batch'],'order'=>[['column'=>4,'dir'=>'asc']]];
        $response = $this->getJson('/usaha/stok?'.http_build_query($query))->assertOk()->assertJsonPath('draw',9)->assertJsonPath('recordsTotal',14)->assertJsonPath('recordsFiltered',13)->assertJsonCount(3,'data')->assertJsonPath('data.0.0',11);
        $this->assertStringContainsString('Batch 10',$response->json('data.0.4'));
        $query['data_table'] = 'journal_page';
        $this->getJson('/usaha/stok?'.http_build_query($query))->assertNotFound();
        $query['data_table'] = 'stock_page';
        $query['order'][0]['dir'] = 'invalid';
        $this->getJson('/usaha/stok?'.http_build_query($query))->assertStatus(422);
    }

    public function test_all_table_endpoints_and_report_pages_keep_access_boundaries(): void
    {
        foreach(config('business_tables') as $key=>$definition) {
            $module = ((array)$definition['module'])[0];
            $this->getJson('/usaha/'.$module.'?'.http_build_query(['data_table'=>$key,'draw'=>1,'offset'=>0,'length'=>10,'order'=>[['column'=>1,'dir'=>'desc']]]))->assertOk()->assertJsonStructure(['draw','recordsTotal','recordsFiltered','data']);
        }
        foreach(['profit','balance','cash','tax'] as $report) {
            $this->get('/laporan/keuangan/'.$report)->assertOk()->assertDontSee('data-target="#form-close"',false);
        }
        $cashier = User::factory()->create(['level'=>2,'id_cabang'=>1]);
        $this->actingAs($cashier)->get('/laporan/keuangan/profit')->assertRedirect(route('dashboard'));
        $this->getJson('/usaha/stok?'.http_build_query(['data_table'=>'stock_page','draw'=>1,'offset'=>0,'length'=>10]))->assertRedirect(route('dashboard'));
    }

    private function bankCsv(array $rows): string
    {
        $path=tempnam(sys_get_temp_dir(),'galaxy-test-bank-');
        $stream=fopen($path,'w'); fputcsv($stream,['date','reference','description','amount']);
        foreach($rows as $row) fputcsv($stream,$row);
        fclose($stream); return $path;
    }

    private function taxDocument(string $type,int $id,bool $creditable): void
    {
        DB::table('tax_invoices')->insert(['id_cabang'=>1,'source_type'=>$type,'source_id'=>$id,'date'=>now()->toDateString(),'invoice_number'=>'TEST-'.$type.'-'.$id,'party_name'=>'Buyer & Test','party_npwp'=>'1111111111111111','creditable'=>$creditable,'created_at'=>now(),'updated_at'=>now()]);
    }

    private function taxProfile(): void
    {
        DB::table('tax_profiles')->insert(['id_cabang'=>1,'name'=>'Test entity','npwp'=>'1111111111111111','nitku'=>'1111111111111111000000','address'=>'Test','pkp'=>true,'created_at'=>now(),'updated_at'=>now()]);
    }
}
