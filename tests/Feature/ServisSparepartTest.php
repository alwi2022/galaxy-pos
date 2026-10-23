<?php

namespace Tests\Feature;

use App\Models\Servis;
use App\Models\User;
use App\Services\LedgerService;
use App\Services\ServisSparepartService;
use App\Services\StockLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ServisSparepartTest extends TestCase
{
    use RefreshDatabase;

    private int $branch;
    private int $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branch = DB::table('cabang')->insertGetId(['nama_cabang' => 'Test']);
        $this->actingAs(User::factory()->create(['level' => 1, 'id_cabang' => $this->branch]));
        DB::table('setting')->insert(['nama_perusahaan' => 'Test', 'alamat' => '-', 'telepon' => '000', 'tipe_nota' => 1, 'diskon' => 0, 'ppn_default' => 11, 'path_logo' => '/img/logo.png', 'path_kartu_member' => '/img/member.png']);
        $category = DB::table('kategori')->insertGetId(['nama_kategori' => 'Part', 'id_cabang' => $this->branch]);
        $this->product = DB::table('produk')->insertGetId(['id_kategori' => $category, 'kode_produk' => 'P1', 'nama_produk' => 'Keyboard', 'harga_beli' => 60000, 'harga_jual' => 95000, 'stok' => 0, 'inventory_value' => 0, 'id_cabang' => $this->branch]);
        app(StockLedgerService::class)->move($this->branch, $this->product, 5, 'OPEN-TEST', 'Stok awal', now()->toDateTimeString(), 60000);
        app(LedgerService::class)->post($this->branch, now()->toDateString(), 'OPEN-TEST', 'Stok awal', [['1301', 300000, 0], ['3101', 0, 300000]]);
    }

    private function servis(): Servis
    {
        return Servis::create(['kode_servis' => 'SRV-T', 'nama_pelanggan' => 'A', 'status' => 'diproses', 'biaya_servis' => 100000, 'tanggal_masuk' => now(), 'id_cabang' => $this->branch, 'id_user' => auth()->id()]);
    }

    private function balance(string $code): int
    {
        return (int) app(LedgerService::class)->balances($this->branch, now()->toDateString())->keyBy('code')[$code]->balance;
    }

    public function test_sparepart_reduces_stock_and_is_billed_when_finished(): void
    {
        $servis = $this->servis();
        app(ServisSparepartService::class)->add($this->branch, $servis->id_servis, $this->product, 1);

        $this->assertSame(4, (int) DB::table('produk')->where('id_produk', $this->product)->value('stok'));
        $this->assertSame(60000, $this->balance('5101'));

        $servis->refresh()->update(['status' => 'selesai']);
        $servis->refresh();
        $this->assertSame(195000, (int) $servis->sisa);
        $this->assertSame(195000, $this->balance('1203'));
        $this->assertSame(-100000, $this->balance('4102'));
        $this->assertSame(-95000, $this->balance('4101'));
    }

    public function test_cancelling_servis_returns_spareparts_to_stock(): void
    {
        $servis = $this->servis();
        app(ServisSparepartService::class)->add($this->branch, $servis->id_servis, $this->product, 2);

        $servis->refresh()->delete();

        $this->assertSame(5, (int) DB::table('produk')->where('id_produk', $this->product)->value('stok'));
        $this->assertSame(0, $this->balance('5101'));
        $this->assertSame(300000, $this->balance('1301'));
    }

    public function test_cancelling_finished_servis_reverses_invoice_and_spareparts(): void
    {
        $servis = $this->servis();
        app(ServisSparepartService::class)->add($this->branch, $servis->id_servis, $this->product, 1);
        $servis->refresh()->update(['status' => 'selesai']);

        $servis->refresh()->delete();

        $this->assertSame(5, (int) DB::table('produk')->where('id_produk', $this->product)->value('stok'));
        foreach (['1203', '4101', '4102', '5101'] as $code) {
            $this->assertSame(0, $this->balance($code), 'akun ' . $code);
        }
        $this->assertSame(0, DB::table('servis_sparepart')->count());
    }

    public function test_sparepart_cannot_change_after_finished(): void
    {
        $servis = $this->servis();
        $servis->update(['status' => 'selesai']);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(ServisSparepartService::class)->add($this->branch, $servis->id_servis, $this->product, 1);
    }
}
