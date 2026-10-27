<?php

namespace App\Console\Commands;

use App\Services\DesktopBackupService;
use Illuminate\Console\Command;

class DesktopBackup extends Command
{
    protected $signature = 'desktop:backup {destination}';
    protected $description = 'Mencadangkan database dan file aplikasi Windows.';

    public function handle(DesktopBackupService $backup): int
    {
        $backup->create($this->argument('destination'));
        $this->info('Cadangan berhasil disimpan.');
        return 0;
    }
}
