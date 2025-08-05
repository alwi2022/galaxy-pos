<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CabangTableSeeder extends Seeder
{
    public function run()
    {
        DB::table('cabang')->insert([
            [
                'nama_cabang' => 'Pusat',
                'alamat' => 'Jl. Raya Utama No. 1',
                'telepon' => '02112345678',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama_cabang' => 'Cabang Serang',
                'alamat' => 'Jl. Serang Tengah',
                'telepon' => '0254123456',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
