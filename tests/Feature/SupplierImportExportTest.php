<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SupplierImportExportTest extends TestCase
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
        $content = "nama,telepon,alamat\n" . implode("\n", $lines) . "\n";

        return UploadedFile::fake()->createWithContent('supplier.csv', $content);
    }

    public function test_template_downloads_as_xlsx(): void
    {
        $this->get(route('supplier.import_template'))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=template-import-supplier.xlsx');
    }

    public function test_import_creates_suppliers(): void
    {
        $this->post(route('supplier.import'), ['file' => $this->csv([
            'Sumber Jaya,0251111222,Jl. Merdeka 10',
            'Mitra Sparepart,0251333444,',
        ])])->assertSessionHas('success', '2 supplier berhasil diimpor.');

        $this->assertDatabaseCount('supplier', 2);
        $this->assertDatabaseHas('supplier', ['nama' => 'Sumber Jaya', 'telepon' => '0251111222', 'alamat' => 'Jl. Merdeka 10', 'id_cabang' => $this->branch]);
        $this->assertDatabaseHas('supplier', ['nama' => 'Mitra Sparepart', 'alamat' => null, 'id_cabang' => $this->branch]);
    }

    public function test_import_rejects_whole_file_when_any_row_is_invalid(): void
    {
        DB::table('supplier')->insert(['nama' => 'Sumber Jaya', 'telepon' => '000', 'id_cabang' => $this->branch]);

        $this->post(route('supplier.import'), ['file' => $this->csv([
            'Sumber Jaya,0251111222,Jl. Merdeka 10',
            'Mitra Sparepart,,',
        ])])->assertSessionHas('import_errors');

        $this->assertDatabaseCount('supplier', 1);
    }

    public function test_export_downloads_as_xlsx(): void
    {
        $this->get(route('supplier.export'))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=export-supplier-' . now()->format('Y-m-d') . '.xlsx');
    }
}
