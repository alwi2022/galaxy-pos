<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use SQLite3;
use ZipArchive;

class DesktopBackupService
{
    private function directory(): string
    {
        abort_unless(config('desktop.enabled') && config('database.default') === 'sqlite', 403);
        return config('desktop.data_path');
    }

    public function create(string $destination): string
    {
        $directory = $this->directory();
        File::ensureDirectoryExists(dirname($destination));
        $snapshot = tempnam(storage_path('app'), 'snapshot-');
        $temporary = $destination . '.' . bin2hex(random_bytes(6)) . '.tmp';
        try {
            $source = new SQLite3(config('database.connections.sqlite.database'), SQLITE3_OPEN_READONLY);
            $source->busyTimeout(10000);
            $target = new SQLite3($snapshot);
            if (!$source->backup($target)) throw new \RuntimeException('Salinan database gagal dibuat.');
            $target->close();
            $source->close();
            $zip = new ZipArchive();
            if ($zip->open($temporary, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new \RuntimeException('File cadangan tidak dapat dibuat.');
            $zip->addFile($snapshot, 'database.sqlite');
            $zip->addFromString('manifest.json', json_encode(['format'=>'galaxy-pos-desktop','version'=>1,'created_at'=>now()->toIso8601String(),'database_sha256'=>hash_file('sha256',$snapshot),'app_key'=>config('app.key')], JSON_THROW_ON_ERROR));
            foreach (['public'=>$directory.'/public','media'=>storage_path('app/public')] as $prefix=>$folder) {
                if (!is_dir($folder)) continue;
                foreach (File::allFiles($folder) as $file) {
                    if ($file->isLink()) continue;
                    $relative = str_replace('\\','/',$file->getRelativePathname());
                    $zip->addFile($file->getPathname(), $prefix . '/' . $relative);
                }
            }
            if (!$zip->close()) throw new \RuntimeException('File cadangan gagal diselesaikan.');
            if (!rename($temporary,$destination)) throw new \RuntimeException('Cadangan tidak dapat disimpan di lokasi tujuan.');
            return $destination;
        } finally {
            if (is_file($snapshot)) unlink($snapshot);
            if (is_file($temporary)) unlink($temporary);
        }
    }

    public function restore(string $archive): void
    {
        $directory = $this->directory();
        $stage = storage_path('app/restore-' . bin2hex(random_bytes(8)));
        File::ensureDirectoryExists($stage);
        $zip = new ZipArchive();
        try {
            if ($zip->open($archive) !== true) $this->invalid('File cadangan tidak dapat dibuka.');
            $manifest = json_decode($zip->getFromName('manifest.json') ?: '', true);
            if (($manifest['format']??'') !== 'galaxy-pos-desktop' || ($manifest['version']??0)!==1 || !preg_match('/^base64:[A-Za-z0-9+\/]{43}=$/', $manifest['app_key']??'')) $this->invalid('Format cadangan Galaxy POS tidak valid.');
            if ($zip->numFiles > 20000) $this->invalid('Jumlah file cadangan melebihi batas.');
            $size = 0;
            $seen = [];
            for ($index=0; $index<$zip->numFiles; $index++) {
                $entry = $zip->statIndex($index);
                $name = $entry['name'];
                $size += $entry['size'];
                if ($size > 2 * 1024 * 1024 * 1024 || isset($seen[strtolower($name)]) || str_contains($name,'\\') || str_contains($name,':') || preg_match('#(^|/)\.{1,2}(/|$)#',$name) || !preg_match('#^(manifest\.json|database\.sqlite|(public|media)/[^\x00]+)$#',$name)) $this->invalid('Isi cadangan tidak valid.');
                foreach (explode('/',rtrim($name,'/')) as $part) {
                    if ($part === '' || rtrim($part," .") !== $part || preg_match('/^(CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(\.|$)/i',$part)) $this->invalid('Nama file cadangan tidak valid.');
                }
                $seen[strtolower($name)] = true;
                if (str_ends_with($name,'/')) continue;
                $target = $stage . '/' . $name;
                File::ensureDirectoryExists(dirname($target));
                $input = $zip->getStream($name);
                $output = fopen($target,'wb');
                if (!$input || !$output) throw new \RuntimeException('File cadangan gagal dibaca.');
                $copied = stream_copy_to_stream($input,$output);
                fclose($input); fclose($output);
                if ($copied !== $entry['size']) $this->invalid('File cadangan tidak lengkap.');
            }
            $zip->close();
            $database = $stage . '/database.sqlite';
            if (!is_file($database) || !hash_equals($manifest['database_sha256']??'',hash_file('sha256',$database))) $this->invalid('Pemeriksaan database cadangan gagal.');
            $check = new SQLite3($database,SQLITE3_OPEN_READONLY);
            try {
                if ($check->querySingle('PRAGMA integrity_check') !== 'ok') $this->invalid('Database cadangan rusak.');
                foreach (['migrations','users','setting','cabang'] as $table) {
                    if (!$check->querySingle("SELECT count(*) FROM sqlite_master WHERE type='table' AND name='$table'")) $this->invalid('Database cadangan bukan database Galaxy POS.');
                }
            } finally { $check->close(); }

            $this->create($directory.'/backups/sebelum-pemulihan-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(3)).'.galaxypos.zip');
            DB::disconnect();
            $currentDatabase = $directory.'/database.sqlite';
            $oldEnv = file_get_contents($directory.'/.env');
            $oldDatabase = $stage.'/previous.sqlite';
            File::ensureDirectoryExists($stage.'/public');
            File::ensureDirectoryExists($stage.'/media');
            $swapped = [];
            try {
                $this->copyDatabase($currentDatabase,$oldDatabase);
                $this->copyDatabase($database,$currentDatabase);
                foreach (['public'=>$directory.'/public','media'=>storage_path('app/public')] as $name=>$destination) {
                    if (is_dir($destination) && !rename($destination,$stage.'/previous-'.$name)) throw new \RuntimeException('File toko masih digunakan.');
                    $swapped[$name]=$destination;
                    if (!rename($stage.'/'.$name,$destination)) throw new \RuntimeException('File toko gagal dipulihkan.');
                }
                $newEnv = preg_replace('/^APP_KEY=.*$/m','APP_KEY='.$manifest['app_key'],$oldEnv);
                if (file_put_contents($directory.'/.env',$newEnv,LOCK_EX) === false) throw new \RuntimeException('Kunci aplikasi gagal dipulihkan.');
            } catch (\Throwable $error) {
                foreach ($swapped as $name=>$destination) {
                    if (is_dir($destination)) File::deleteDirectory($destination);
                    if (is_dir($stage.'/previous-'.$name)) rename($stage.'/previous-'.$name,$destination);
                }
                if (is_file($oldDatabase)) $this->copyDatabase($oldDatabase,$currentDatabase);
                file_put_contents($directory.'/.env',$oldEnv,LOCK_EX);
                throw $error;
            }
        } finally {
            try { $zip->close(); } catch (\Throwable $ignored) {}
            File::deleteDirectory($stage);
        }
    }

    private function invalid(string $message): void
    {
        throw ValidationException::withMessages(['backup'=>$message]);
    }

    private function copyDatabase(string $sourcePath, string $destination): void
    {
        $source = new SQLite3($sourcePath,SQLITE3_OPEN_READONLY);
        $target = new SQLite3($destination);
        $source->busyTimeout(10000);
        $target->busyTimeout(10000);
        try {
            if (!$source->backup($target)) throw new \RuntimeException('Database masih digunakan atau tidak dapat dipulihkan.');
        } finally { $target->close(); $source->close(); }
    }
}
