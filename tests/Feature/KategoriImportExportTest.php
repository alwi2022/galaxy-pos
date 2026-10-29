<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KategoriImportExportTest extends TestCase
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
        $content = "nama_kategori\n" . implode("\n", $lines) . "\n";

        return UploadedFile::fake()->createWithContent('kategori.csv', $content);
    }

    public function test_template_downloads_as_xlsx(): void
    {
        $this->get(route('kategori.import_template'))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=template-import-kategori.xlsx');
    }

    public function test_import_creates_categories(): void
    {
        $this->post(route('kategori.import'), ['file' => $this->csv([
            'Aksesoris',
            'Penyimpanan',
        ])])->assertSessionHas('success', '2 kategori berhasil diimpor.');

        $this->assertDatabaseCount('kategori', 2);
        $this->assertDatabaseHas('kategori', ['nama_kategori' => 'Aksesoris', 'id_cabang' => $this->branch]);
    }

    public function test_import_rejects_whole_file_when_any_row_is_invalid(): void
    {
        DB::table('kategori')->insert(['nama_kategori' => 'Aksesoris', 'id_cabang' => $this->branch]);

        $this->post(route('kategori.import'), ['file' => $this->csv([
            'Aksesoris',
            'Penyimpanan',
        ])])->assertSessionHas('import_errors');

        $this->assertDatabaseCount('kategori', 1);
    }

    public function test_export_downloads_as_xlsx(): void
    {
        $this->get(route('kategori.export'))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=export-kategori-' . now()->format('Y-m-d') . '.xlsx');
    }
}
