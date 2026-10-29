<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProdukExportTest extends TestCase
{
    use RefreshDatabase;

    private int $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branch = DB::table('cabang')->insertGetId(['nama_cabang' => 'Test']);
        $user = User::factory()->create(['level' => 1, 'id_cabang' => $this->branch]);
        $this->actingAs($user);
        DB::table('setting')->insert(['nama_perusahaan' => 'Test', 'alamat' => '-', 'telepon' => '000', 'tipe_nota' => 1, 'diskon' => 0, 'ppn_default' => 11, 'path_logo' => '/img/logo.png', 'path_kartu_member' => '/img/member.png']);
    }

    public function test_export_downloads_as_xlsx(): void
    {
        $kategori = DB::table('kategori')->insertGetId(['nama_kategori' => 'Aksesoris', 'id_cabang' => $this->branch]);
        DB::table('produk')->insert(['nama_produk' => 'Mouse', 'id_kategori' => $kategori, 'kode_produk' => 'P000001', 'harga_beli' => 1000, 'harga_jual' => 2000, 'diskon' => 0, 'stok' => 5, 'id_cabang' => $this->branch]);

        $this->get(route('produk.export'))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=export-produk-' . now()->format('Y-m-d') . '.xlsx');
    }
}
