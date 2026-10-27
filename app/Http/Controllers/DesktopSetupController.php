<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DesktopSetupController extends Controller
{
    public function create()
    {
        abort_unless(config('desktop.enabled'), 404);
        if (DB::table('users')->exists()) return redirect()->route('login');
        return view('desktop.setup');
    }

    public function store(Request $request)
    {
        abort_unless(config('desktop.enabled'), 404);
        abort_if(DB::table('users')->exists(), 409, 'Akun awal sudah dibuat.');
        $data = $request->validate(['store_name'=>'required|string|max:255','name'=>'required|string|max:255','email'=>'required|email|max:255','password'=>'required|string|min:8|confirmed']);
        $id = DB::transaction(function () use ($data) {
            abort_if(DB::table('users')->exists(), 409);
            $branch = DB::table('cabang')->insertGetId(['nama_cabang'=>'Pusat','alamat'=>'','telepon'=>'','created_at'=>now(),'updated_at'=>now()]);
            DB::table('setting')->insert(['nama_perusahaan'=>$data['store_name'],'alamat'=>'','telepon'=>'','tipe_nota'=>1,'diskon'=>0,'ppn_default'=>0,'path_logo'=>'/img/logo.png','path_kartu_member'=>'/img/member.png']);
            return DB::table('users')->insertGetId(['name'=>$data['name'],'email'=>$data['email'],'password'=>Hash::make($data['password']),'level'=>1,'id_cabang'=>$branch,'foto'=>'/img/user.jpg','email_verified_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
        });
        Auth::login(User::findOrFail($id));
        $request->session()->regenerate();
        return redirect()->route('dashboard');
    }
}
