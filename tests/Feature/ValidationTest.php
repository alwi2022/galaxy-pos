<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ValidationTest extends TestCase
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

    public function test_kategori_store_requires_nama(): void
    {
        $response = $this->post(route('kategori.store'), ['nama_kategori' => '']);

        $response->assertSessionHasErrors('nama_kategori');
        $this->assertDatabaseCount('kategori', 0);
    }

    public function test_kategori_store_accepts_valid_nama(): void
    {
        $response = $this->post(route('kategori.store'), ['nama_kategori' => 'Aksesoris']);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('kategori', ['nama_kategori' => 'Aksesoris', 'id_cabang' => $this->branch]);
    }

    public function test_kategori_update_requires_nama(): void
    {
        $id = DB::table('kategori')->insertGetId(['nama_kategori' => 'Lama', 'id_cabang' => $this->branch]);

        $response = $this->put(route('kategori.update', $id), ['nama_kategori' => '']);

        $response->assertSessionHasErrors('nama_kategori');
        $this->assertDatabaseHas('kategori', ['id_kategori' => $id, 'nama_kategori' => 'Lama']);
    }

    public function test_pembelian_detail_update_rejects_invalid_jumlah(): void
    {
        $category = DB::table('kategori')->insertGetId(['nama_kategori' => 'Test', 'id_cabang' => $this->branch]);
        $supplier = DB::table('supplier')->insertGetId(['nama' => 'Supplier', 'telepon' => '000', 'id_cabang' => $this->branch]);
        $product = DB::table('produk')->insertGetId(['id_kategori' => $category, 'kode_produk' => 'TEST', 'nama_produk' => 'Test', 'harga_beli' => 10000, 'harga_jual' => 20000, 'stok' => 0, 'inventory_value' => 0, 'id_cabang' => $this->branch]);
        $pembelian = DB::table('pembelian')->insertGetId(['id_supplier' => $supplier, 'total_item' => 1, 'total_harga' => 10000, 'bayar' => 10000, 'id_cabang' => $this->branch, 'created_at' => now(), 'updated_at' => now()]);
        $detail = DB::table('pembelian_detail')->insertGetId(['id_pembelian' => $pembelian, 'id_produk' => $product, 'harga_beli' => 10000, 'jumlah' => 1, 'subtotal' => 10000]);

        $response = $this->put(route('pembelian_detail.update', $detail), ['jumlah' => 'abc']);

        $response->assertSessionHasErrors('jumlah');
        $this->assertDatabaseHas('pembelian_detail', ['id_pembelian_detail' => $detail, 'jumlah' => 1]);
    }

    public function test_pembelian_detail_update_rejects_zero_jumlah(): void
    {
        $category = DB::table('kategori')->insertGetId(['nama_kategori' => 'Test', 'id_cabang' => $this->branch]);
        $supplier = DB::table('supplier')->insertGetId(['nama' => 'Supplier', 'telepon' => '000', 'id_cabang' => $this->branch]);
        $product = DB::table('produk')->insertGetId(['id_kategori' => $category, 'kode_produk' => 'TEST', 'nama_produk' => 'Test', 'harga_beli' => 10000, 'harga_jual' => 20000, 'stok' => 0, 'inventory_value' => 0, 'id_cabang' => $this->branch]);
        $pembelian = DB::table('pembelian')->insertGetId(['id_supplier' => $supplier, 'total_item' => 1, 'total_harga' => 10000, 'bayar' => 10000, 'id_cabang' => $this->branch, 'created_at' => now(), 'updated_at' => now()]);
        $detail = DB::table('pembelian_detail')->insertGetId(['id_pembelian' => $pembelian, 'id_produk' => $product, 'harga_beli' => 10000, 'jumlah' => 1, 'subtotal' => 10000]);

        $response = $this->put(route('pembelian_detail.update', $detail), ['jumlah' => 0]);

        $response->assertSessionHasErrors('jumlah');
    }

    public function test_pembelian_detail_update_accepts_valid_jumlah(): void
    {
        $category = DB::table('kategori')->insertGetId(['nama_kategori' => 'Test', 'id_cabang' => $this->branch]);
        $supplier = DB::table('supplier')->insertGetId(['nama' => 'Supplier', 'telepon' => '000', 'id_cabang' => $this->branch]);
        $product = DB::table('produk')->insertGetId(['id_kategori' => $category, 'kode_produk' => 'TEST', 'nama_produk' => 'Test', 'harga_beli' => 10000, 'harga_jual' => 20000, 'stok' => 0, 'inventory_value' => 0, 'id_cabang' => $this->branch]);
        $pembelian = DB::table('pembelian')->insertGetId(['id_supplier' => $supplier, 'total_item' => 1, 'total_harga' => 10000, 'bayar' => 10000, 'id_cabang' => $this->branch, 'created_at' => now(), 'updated_at' => now()]);
        $detail = DB::table('pembelian_detail')->insertGetId(['id_pembelian' => $pembelian, 'id_produk' => $product, 'harga_beli' => 10000, 'jumlah' => 1, 'subtotal' => 10000]);

        $response = $this->put(route('pembelian_detail.update', $detail), ['jumlah' => 5]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pembelian_detail', ['id_pembelian_detail' => $detail, 'jumlah' => 5, 'subtotal' => 50000]);
    }
}
