<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class DesktopInitialize extends Command
{
    protected $signature = 'desktop:initialize';
    protected $description = 'Mempersiapkan database lokal aplikasi Windows tanpa data demo.';

    public function handle(): int
    {
        if (!config('desktop.enabled') || config('database.default') !== 'sqlite') {
            $this->error('Perintah ini hanya boleh dijalankan untuk database lokal aplikasi Windows.');
            return 1;
        }
        $expected = realpath(config('desktop.data_path') . '/database.sqlite');
        if (!$expected || realpath(config('database.connections.sqlite.database')) !== $expected) {
            $this->error('Lokasi database lokal tidak sesuai.');
            return 1;
        }
        if ($this->call('migrate', ['--force'=>true]) !== 0) return 1;
        File::ensureDirectoryExists(storage_path('fonts'));
        DB::statement('PRAGMA busy_timeout = 10000');
        DB::statement('PRAGMA journal_mode = DELETE');
        $source = base_path('public/img');
        File::ensureDirectoryExists(public_path('img'));
        foreach (File::files($source) as $file) {
            $destination = public_path('img/' . $file->getFilename());
            if (!is_file($destination)) File::copy($file->getPathname(), $destination);
        }
        $this->info('Database lokal siap.');
        return 0;
    }
}
