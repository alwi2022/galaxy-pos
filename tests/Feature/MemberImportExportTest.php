<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MemberImportExportTest extends TestCase
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
        $content = "kode_member,nama,telepon,alamat\n" . implode("\n", $lines) . "\n";

        return UploadedFile::fake()->createWithContent('member.csv', $content);
    }

    public function test_template_downloads_as_xlsx(): void
    {
        $this->get(route('member.import_template'))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=template-import-member.xlsx');
    }

    public function test_import_auto_generates_kode_member_when_blank(): void
    {
        $this->post(route('member.import'), ['file' => $this->csv([
            ',Budi Santoso,08123456789,Jl. Mawar 1',
            ',Siti Aminah,08987654321,',
        ])])->assertSessionHas('success', '2 member berhasil diimpor.');

        $this->assertDatabaseCount('member', 2);
        $this->assertDatabaseHas('member', ['kode_member' => '00001', 'nama' => 'Budi Santoso', 'id_cabang' => $this->branch]);
        $this->assertDatabaseHas('member', ['kode_member' => '00002', 'nama' => 'Siti Aminah', 'alamat' => null]);
    }

    public function test_import_rejects_whole_file_when_kode_is_duplicate_global(): void
    {
        DB::table('member')->insert(['kode_member' => '00009', 'nama' => 'Existing', 'telepon' => '000', 'id_cabang' => $this->branch]);

        $this->post(route('member.import'), ['file' => $this->csv([
            '00009,Budi Santoso,08123456789,Jl. Mawar 1',
            ',Siti Aminah,,',
        ])])->assertSessionHas('import_errors');

        $this->assertDatabaseCount('member', 1);
    }

    public function test_export_downloads_as_xlsx(): void
    {
        $this->get(route('member.export'))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=export-member-' . now()->format('Y-m-d') . '.xlsx');
    }
}
