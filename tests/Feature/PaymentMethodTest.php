<?php

namespace Tests\Feature;

use App\Models\Pengeluaran;
use App\Models\User;
use App\Services\LedgerService;
use App\Services\PaymentMethodService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentMethodTest extends TestCase
{
    use RefreshDatabase;

    private int $branch;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-25 12:00:00', 'Asia/Jakarta'));
        $this->branch = DB::table('cabang')->insertGetId(['nama_cabang' => 'Test']);
        $this->actingAs(User::factory()->create(['level' => 1, 'id_cabang' => $this->branch]));
        app(LedgerService::class)->initialize($this->branch);
        DB::table('setting')->insert(['nama_perusahaan' => 'Test', 'alamat' => '-', 'telepon' => '000', 'tipe_nota' => 1, 'diskon' => 0, 'ppn_default' => 11, 'path_logo' => '/img/logo.png', 'path_kartu_member' => '/img/member.png']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function addBca(): object
    {
        $this->post(route('business.store', 'bank-account'), ['bank_name' => 'BCA Cilegon', 'account_number' => '1234567', 'account_holder' => 'Alief Comp', 'address' => 'Jl SA Tirtayasa', 'city' => 'Cilegon', 'phone' => '0254'])->assertSessionHasNoErrors();
        app()->forgetInstance(PaymentMethodService::class);
        return DB::table('payment_methods')->where('id_cabang', $this->branch)->where('type', 'bank')->orderByDesc('id')->first();
    }

    public function test_bank_page_seeds_defaults_and_lists_bank_accounts_only(): void
    {
        $this->get(route('business.index', 'rekening-bank'))->assertOk()->assertSee('Tambah rekening bank');
        $this->assertSame(['tunai' => 'Tunai', 'transfer_bank' => 'Bank / Transfer', 'qris' => 'QRIS'], daftar_metode_pembayaran());

        $this->addBca();
        $response = $this->getJson(route('business.index', ['rekening-bank', 'data_table' => 'bank_account_page', 'draw' => 1, 'offset' => 0, 'length' => 10]));
        $response->assertOk()->assertJsonPath('recordsTotal', 2);
    }

    public function test_new_bank_account_gets_its_own_ledger_account_and_receives_cash_directly(): void
    {
        $bca = $this->addBca();
        $this->assertSame('bank_1110', $bca->code);
        $this->assertSame('BCA Cilegon 1234567 a/n Alief Comp', label_metode_pembayaran('bank_1110'));
        $this->assertDatabaseHas('ledger_accounts', ['id_cabang' => $this->branch, 'code' => '1110', 'name' => 'BCA Cilegon 1234567 a/n Alief Comp', 'cash_account' => true]);

        Pengeluaran::create(['id_cabang' => $this->branch, 'tanggal_pengeluaran' => '2026-10-25', 'kategori_pengeluaran' => 'listrik', 'deskripsi' => 'Listrik', 'nominal' => 50000, 'metode_pembayaran' => 'bank_1110']);
        $balance = app(LedgerService::class)->balances($this->branch, '2026-10-25')->firstWhere('code', '1110');
        $this->assertEquals(-50000, $balance->balance);
    }

    public function test_cara_bayar_renders_banks_and_inactive_bank_is_hidden_but_labelled(): void
    {
        $bca = $this->addBca();
        $this->get(route('pengeluaran.index'))->assertOk()->assertSee('Cara Bayar')->assertSee('Rekening Bank')->assertSee('data-type="bank"', false)->assertSee('BCA Cilegon 1234567');

        $this->post(route('business.store', 'bank-account-update'), ['id' => $bca->id, 'bank_name' => 'BCA Cilegon', 'account_number' => '1234567', 'account_holder' => 'Alief Comp', 'active' => 0])->assertSessionHasNoErrors();
        app()->forgetInstance(PaymentMethodService::class);
        $this->assertArrayNotHasKey('bank_1110', daftar_metode_pembayaran());
        $this->assertSame('BCA Cilegon 1234567 a/n Alief Comp', label_metode_pembayaran('bank_1110'));

        $tunai = DB::table('payment_methods')->where('id_cabang', $this->branch)->where('code', 'tunai')->value('id');
        $this->post(route('business.store', 'bank-account-update'), ['id' => $tunai, 'bank_name' => 'X', 'active' => 0])->assertNotFound();
    }
}
