<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProdukImportTest extends TestCase
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

    private function csv(array $lines): UploadedFile
    {
        $content = "nama_produk,kategori,merk,harga_beli,harga_jual,diskon,stok\n" . implode("\n", $lines) . "\n";

        return UploadedFile::fake()->createWithContent('produk.csv', $content);
    }

    public function test_template_downloads_as_xlsx(): void
    {
        $this->get(route('produk.import_template'))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=template-import-produk.xlsx');
    }

    public function test_import_creates_products_categories_and_opening_stock(): void
    {
        DB::table('kategori')->insert(['nama_kategori' => 'Aksesoris', 'id_cabang' => $this->branch]);

        $this->post(route('produk.import'), ['file' => $this->csv([
            'Mouse Wireless,aksesoris,Logitech,95000,125000,0,10',
            'Flashdisk 32GB,Penyimpanan,,55000,75000,5,',
        ])])->assertSessionHas('success', '2 produk berhasil diimpor, 1 kategori baru dibuat.');

        $this->assertDatabaseCount('kategori', 2);
        $mouse = DB::table('produk')->where('nama_produk', 'Mouse Wireless')->first();
        $this->assertSame(10, (int) $mouse->stok);
        $this->assertSame($this->branch, (int) $mouse->id_cabang);
        $this->assertSame((int) DB::table('kategori')->where('nama_kategori', 'Aksesoris')->value('id_kategori'), (int) $mouse->id_kategori);
        $this->assertDatabaseHas('produk', ['nama_produk' => 'Flashdisk 32GB', 'stok' => 0, 'merk' => null, 'diskon' => 5]);
        $this->assertDatabaseHas('stock_movements', ['id_produk' => $mouse->id_produk, 'quantity' => 10, 'balance' => 10]);
        $entries = DB::table('ledger_entries')->where('reference', 'like', 'OPEN-IMPORT-%')->pluck('id');
        $this->assertCount(1, $entries);
        $this->assertSame(950000, (int) DB::table('ledger_lines')->where('entry_id', $entries[0])->sum('debit'));
        $this->assertSame(950000, (int) DB::table('ledger_lines')->where('entry_id', $entries[0])->sum('credit'));
        $this->assertSame(950000, (int) DB::table('stock_movements')->sum('value'));
        $this->assertNotSame(
            DB::table('produk')->where('nama_produk', 'Mouse Wireless')->value('kode_produk'),
            DB::table('produk')->where('nama_produk', 'Flashdisk 32GB')->value('kode_produk')
        );
    }

    public function test_import_rejects_whole_file_when_any_row_is_invalid(): void
    {
        DB::table('kategori')->insert(['nama_kategori' => 'Aksesoris', 'id_cabang' => $this->branch]);
        $kategori = DB::table('kategori')->value('id_kategori');
        DB::table('produk')->insert(['nama_produk' => 'Keyboard', 'id_kategori' => $kategori, 'kode_produk' => 'P000001', 'harga_beli' => 1, 'harga_jual' => 1, 'diskon' => 0, 'stok' => 0, 'id_cabang' => $this->branch]);

        $this->post(route('produk.import'), ['file' => $this->csv([
            'Mouse,Aksesoris,,95000,125000,0,1',
            'keyboard,Aksesoris,,1000,2000,0,0',
            'Mouse,Aksesoris,,95000,125000,0,0',
            'Kabel,,,abc,15.000,0,0',
        ])])->assertSessionHas('import_error_total', 3);

        $errors = session('import_errors');
        $this->assertStringContainsString('Baris 3', $errors[0]);
        $this->assertStringContainsString('sudah ada', $errors[0]);
        $this->assertStringContainsString('ganda dengan baris 2', $errors[1]);
        $this->assertStringContainsString('kategori wajib', $errors[2]);
        $this->assertStringContainsString('harga_beli harus angka', $errors[2]);
        $this->assertDatabaseCount('produk', 1);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_downloaded_xlsx_template_can_be_imported(): void
    {
        $template = $this->get(route('produk.import_template'))->baseResponse->getFile()->getPathname();

        $this->post(route('produk.import'), ['file' => new UploadedFile($template, 'template.xlsx', null, null, true)])
            ->assertSessionHas('success', '2 produk berhasil diimpor, 2 kategori baru dibuat.');

        $this->assertDatabaseHas('produk', ['nama_produk' => 'Mouse Wireless M185', 'harga_beli' => 95000, 'harga_jual' => 125000, 'stok' => 10, 'merk' => 'Logitech']);
        $this->assertDatabaseHas('produk', ['nama_produk' => 'Flashdisk 32GB', 'diskon' => 5, 'stok' => 0]);
    }

    public function test_import_rejects_empty_file(): void
    {
        $this->post(route('produk.import'), ['file' => $this->csv([])])
            ->assertSessionHas('import_errors');
        $this->assertDatabaseCount('produk', 0);
    }
}
