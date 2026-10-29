<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\LedgerService;
use App\Services\PaymentMethodService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RekeningBankImportExportTest extends TestCase
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

    private function csv(array $lines): UploadedFile
    {
        $content = "nama bank,no rekening,atas nama,kota\n" . implode("\n", $lines) . "\n";

        return UploadedFile::fake()->createWithContent('rekening-bank.csv', $content);
    }

    private function bankCount(): int
    {
        return (int) DB::table('payment_methods')->where('id_cabang', $this->branch)->where('type', 'bank')->count();
    }

    public function test_template_downloads_as_xlsx(): void
    {
        $this->get(route('business.bank-master-template'))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=template-import-rekening-bank.xlsx');
    }

    public function test_import_creates_bank_payment_methods_and_ledger_accounts(): void
    {
        $this->post(route('business.bank-master-import'), ['file' => $this->csv([
            'BCA Cilegon,1234567890,Toko Galaxy,Cilegon',
            'Mandiri,0987654321,Toko Galaxy,Serang',
        ])])->assertSessionHas('success', '2 rekening bank berhasil diimpor.');

        $this->assertDatabaseHas('payment_methods', ['id_cabang' => $this->branch, 'type' => 'bank', 'bank_name' => 'BCA Cilegon', 'account_number' => '1234567890']);
        $this->assertDatabaseHas('payment_methods', ['id_cabang' => $this->branch, 'type' => 'bank', 'bank_name' => 'Mandiri', 'account_number' => '0987654321']);
        $this->assertDatabaseHas('ledger_accounts', ['id_cabang' => $this->branch, 'cash_account' => true]);
    }

    public function test_import_rejects_whole_file_when_duplicate_or_invalid(): void
    {
        $this->post(route('business.store', 'bank-account'), ['bank_name' => 'BCA Cilegon', 'account_number' => '1234567890', 'account_holder' => 'Toko Galaxy', 'city' => 'Cilegon'])->assertSessionHasNoErrors();
        app()->forgetInstance(PaymentMethodService::class);
        $before = $this->bankCount();

        $this->post(route('business.bank-master-import'), ['file' => $this->csv([
            'BCA Cilegon,1234567890,Toko Galaxy,Cilegon',
            ',0987654321,Toko Galaxy,Serang',
        ])])->assertSessionHas('import_errors');

        $this->assertSame($before, $this->bankCount());
    }

    public function test_export_downloads_as_xlsx(): void
    {
        $this->get(route('business.bank-master-export'))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=export-rekening-bank-' . now()->format('Y-m-d') . '.xlsx');
    }
}
