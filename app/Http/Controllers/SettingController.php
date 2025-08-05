<?php


namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    public function index()
    {
        return view('setting.index');
    }

    public function show()
    {
        return Setting::first();
    }

    public function update(Request $request)
    {
        $setting = Setting::first();
        $setting->nama_perusahaan = $request->nama_perusahaan;
        $setting->telepon = $request->telepon;
        $setting->alamat = $request->alamat;
        $setting->diskon = $request->diskon;
        $setting->tipe_nota = $request->tipe_nota;

        if ($request->hasFile('path_logo')) {
            $file = $request->file('path_logo');
            $nama = 'logo-' . date('YmdHis') . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('/img'), $nama);

            $setting->path_logo = "/img/$nama";
        }

        if ($request->hasFile('path_kartu_member')) {
            $file = $request->file('path_kartu_member');
            $nama = 'logo-' . date('Y-m-dHis') . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('/img'), $nama);

            $setting->path_kartu_member = "/img/$nama";
        }

        $setting->update();

        return response()->json('Data berhasil disimpan', 200);
    }

    public function backup()
    {
        try {
            // Jalankan backup database saja (tanpa file)
            Artisan::call('backup:run', ['--only-db' => true]);
    
            // Ambil path file backup terbaru
            $backupPath = collect(Storage::files('laravel-backup'))
                ->filter(fn($path) => str_ends_with($path, '.zip'))
                ->sort()
                ->last();
    
            if (!$backupPath) {
                return back()->with('error', 'Gagal membuat backup.');
            }
    
            return response()->download(storage_path("app/{$backupPath}"));
    
        } catch (\Exception $e) {
            return back()->with('error', 'Backup gagal: ' . $e->getMessage());
        }
    }

public function restore(Request $request)
{
    $request->validate([
        'sql_file' => 'required|mimes:sql'
    ]);

    $file = $request->file('sql_file');
    $path = $file->storeAs('temp', 'restore.sql');

    $command = "mysql -u " . env('DB_USERNAME') . " -p'" . env('DB_PASSWORD') . "' " . env('DB_DATABASE') . " < " . storage_path("app/$path");

    $result = null;
    exec($command, $output, $result);

    if ($result === 0) {
        return back()->with('success', 'Database berhasil di-restore.');
    } else {
        return back()->with('error', 'Restore gagal.');
    }
}

}
