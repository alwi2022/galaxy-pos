<?php

namespace Tests\Feature;

use App\Services\DesktopBackupService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use ZipArchive;

class DesktopBackupTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/galaxy-backup-'.bin2hex(random_bytes(8));
        foreach (['storage/app/public','storage/framework/views','storage/logs','public/img','backups'] as $folder) File::ensureDirectoryExists($this->directory.'/'.$folder);
        touch($this->directory.'/database.sqlite');
        $key = 'base64:'.base64_encode(str_repeat('a',32));
        file_put_contents($this->directory.'/.env',"APP_KEY=$key\n");
        $this->app->useStoragePath($this->directory.'/storage');
        config(['desktop.enabled'=>true,'desktop.data_path'=>$this->directory,'database.default'=>'sqlite','database.connections.sqlite.database'=>$this->directory.'/database.sqlite','app.key'=>$key]);
        DB::purge('sqlite');
        Artisan::call('migrate',['--force'=>true]);
        DB::table('cabang')->insert(['nama_cabang'=>'Sebelum']);
        file_put_contents($this->directory.'/public/img/upload.png','before');
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_backup_restores_database_images_and_encryption_key_together(): void
    {
        $backup = app(DesktopBackupService::class);
        $archive = $backup->create($this->directory.'/backups/test.zip');
        DB::table('cabang')->update(['nama_cabang'=>'Sesudah']);
        file_put_contents($this->directory.'/public/img/upload.png','after');
        file_put_contents($this->directory.'/public/img/new.png','new');
        $originalKey = config('app.key');
        config(['app.key'=>'base64:'.base64_encode(str_repeat('b',32))]);
        file_put_contents($this->directory.'/.env','APP_KEY='.config('app.key')."\n");
        $backup->restore($archive);
        $this->assertSame('Sebelum',DB::table('cabang')->value('nama_cabang'));
        $this->assertSame('before',file_get_contents($this->directory.'/public/img/upload.png'));
        $this->assertFileDoesNotExist($this->directory.'/public/img/new.png');
        $this->assertStringContainsString($originalKey,file_get_contents($this->directory.'/.env'));
        $this->assertCount(1,glob($this->directory.'/backups/sebelum-pemulihan-*.zip'));
    }

    public function test_windows_path_traversal_is_rejected_without_changing_live_data(): void
    {
        $backup = app(DesktopBackupService::class);
        $archive = $backup->create($this->directory.'/backups/test.zip');
        $zip = new ZipArchive();
        $zip->open($archive);
        $zip->addFromString('public/.. /.. /escape.txt','bad');
        $zip->close();
        DB::table('cabang')->update(['nama_cabang'=>'Tetap']);
        try { $backup->restore($archive); $this->fail('Cadangan tidak valid diterima.'); }
        catch (ValidationException $error) { $this->assertSame('Tetap',DB::table('cabang')->value('nama_cabang')); }
        $this->assertFileDoesNotExist($this->directory.'/escape.txt');
    }
}
