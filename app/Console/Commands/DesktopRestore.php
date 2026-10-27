<?php

namespace App\Console\Commands;

use App\Services\DesktopBackupService;
use Illuminate\Console\Command;

class DesktopRestore extends Command
{
    protected $signature = 'desktop:restore {archive}';
    protected $description = 'Memulihkan cadangan setelah server aplikasi Windows dihentikan.';

    public function handle(DesktopBackupService $backup): int
    {
        $backup->restore($this->argument('archive'));
        $this->info('Data toko berhasil dipulihkan.');
        return 0;
    }
}
