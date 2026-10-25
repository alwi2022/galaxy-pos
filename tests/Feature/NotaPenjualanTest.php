<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\LedgerService;
use App\Services\PaymentMethodService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NotaPenjualanTest extends TestCase
{
    use RefreshDatabase;

    private int $branch;
    private int $sale;
    private User $kasir;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-25 12:00:00', 'Asia/Jakarta'));
        $this->branch = DB::table('cabang')->insertGetId(['nama_cabang' => 'Test']);
        $this->kasir = User::factory()->create(['name' => 'Kasir Pagi', 'level' => 2, 'id_cabang' => $this->branch]);
        DB::table('setting')->insert(['nama_perusahaan' => 'Arena Computer', 'alamat' => 'Cilegon', 'telepon' => '0819', 'tipe_nota' => 2, 'diskon' => 0, 'ppn_default' => 0, 'path_logo' => '/img/logo.png', 'path_kartu_member' => '/img/member.png']);
        $member = DB::table('member')->insertGetId(['kode_member' => 'M001', 'nama' => 'Budi Santoso', 'alamat' => 'Jl Anyer 10', 'telepon' => '0812345', 'id_cabang' => $this->branch]);
        $category = DB::table('kategori')->insertGetId(['nama_kategori' => 'Test', 'id_cabang' => $this->branch]);
        $product = DB::table('produk')->insertGetId(['id_kategori' => $category, 'kode_produk' => 'KODE-RAHASIA-1', 'nama_produk' => 'Mouse Logitech', 'harga_beli' => 50000, 'harga_jual' => 125000, 'stok' => 10, 'inventory_value' => 500000, 'id_cabang' => $this->branch]);
        $this->sale = DB::table('penjualan')->insertGetId(['id_member' => $member, 'id_user' => $this->kasir->id, 'id_cabang' => $this->branch, 'total_item' => 2, 'total_harga' => 250000, 'diskon' => 0, 'ppn_persen' => 0, 'ppn_nominal' => 0, 'bayar' => 250000, 'diterima' => 100000, 'dibayar' => 100000, 'sisa' => 150000, 'skema_pembayaran' => 'kredit', 'metode_pembayaran' => 'tunai', 'status_pembayaran' => 'sebagian', 'jatuh_tempo' => '2026-11-01', 'posted_at' => '2026-10-20 09:00:00', 'created_at' => '2026-10-20 09:00:00', 'updated_at' => '2026-10-20 09:00:00']);
        DB::table('penjualan_detail')->insert(['id_penjualan' => $this->sale, 'id_produk' => $product, 'harga_jual' => 125000, 'jumlah' => 2, 'diskon' => 0, 'subtotal' => 250000]);

        app(LedgerService::class)->initialize($this->branch);
        app(PaymentMethodService::class)->storeBank($this->branch, ['bank_name' => 'BCA Cilegon', 'account_number' => '2951961070', 'account_holder' => 'Yasfi Rahmat']);
        app()->forgetInstance(PaymentMethodService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_reprint_uses_transaction_data_not_current_user_or_date(): void
    {
        $admin = User::factory()->create(['name' => 'Admin Malam', 'level' => 1, 'id_cabang' => $this->branch]);
        $this->actingAs($admin);

        $html = $this->get(route('penjualan.nota_kecil', $this->sale))->assertOk()
            ->assertSee('20-10-2026')->assertDontSee('25-10-2026')
            ->assertSee('KASIR PAGI')->assertDontSee('ADMIN MALAM')
            ->assertSee('BUDI SANTOSO')->assertSee('01-11-2026')
            ->assertSee('DUA RATUS LIMA PULUH RIBU RUPIAH')
            ->assertSee('BCA Cilegon 2951961070 a/n Yasfi Rahmat')
            ->assertDontSee('KODE-RAHASIA-1');

        $this->get(route('penjualan.nota_besar', $this->sale))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_nota_besar_follows_arena_layout_without_product_code_or_gudang(): void
    {
        $this->actingAs($this->kasir);
        $sale = $this->sale;
        $data = (fn () => $this->notaData($sale))->call(app(\App\Http\Controllers\PenjualanController::class));
        $html = view('penjualan.nota_besar', $data)->render();

        foreach (['NOTA PENJUALAN', 'BUDI SANTOSO', 'Jl Anyer 10', '0812345', '20/10/2026', 'BAYAR SEBAGIAN - TUNAI', 'Jatuh Tempo', '01/11/2026', 'Mouse Logitech', '# DUA RATUS LIMA PULUH RIBU RUPIAH #', '150.000', 'Penerima', 'Kasir', 'No.Rek 2951961070'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringNotContainsString('KODE-RAHASIA-1', $html);
        $this->assertStringNotContainsString('Gudang', $html);
    }

    public function test_cashier_can_find_old_nota_by_number_like_mitra(): void
    {
        $this->actingAs($this->kasir);
        $nomor = str_pad((string) $this->sale, 10, '0', STR_PAD_LEFT);

        $this->getJson(route('transaksi.cari_nota', ['nomor' => $nomor]))->assertOk()
            ->assertJsonPath('nomor', $nomor)
            ->assertJsonPath('tanggal', '20/10/2026')
            ->assertJsonPath('customer', 'Budi Santoso')
            ->assertJsonPath('cara_bayar', 'BAYAR SEBAGIAN - TUNAI')
            ->assertJsonPath('jatuh_tempo', '01/11/2026')
            ->assertJsonPath('items.0.nama', 'Mouse Logitech')
            ->assertJsonPath('sisa', '150.000')
            ->assertJsonPath('cetak', route('penjualan.nota_besar', $this->sale));
        $this->getJson(route('transaksi.cari_nota', ['nomor' => (string) $this->sale]))->assertOk();
        $this->getJson(route('transaksi.cari_nota', ['nomor' => '999999']))->assertNotFound();

        $other = DB::table('cabang')->insertGetId(['nama_cabang' => 'Lain']);
        $this->actingAs(User::factory()->create(['level' => 2, 'id_cabang' => $other]));
        $this->getJson(route('transaksi.cari_nota', ['nomor' => $nomor]))->assertNotFound();
    }

    public function test_reprint_is_blocked_for_other_branch_cashier(): void
    {
        $other = DB::table('cabang')->insertGetId(['nama_cabang' => 'Lain']);
        $this->actingAs(User::factory()->create(['level' => 2, 'id_cabang' => $other]));
        $this->get(route('penjualan.nota_kecil', $this->sale))->assertForbidden();
    }
}
