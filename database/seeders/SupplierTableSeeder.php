<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SupplierTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('supplier')->insert([
            [
                'nama' => 'CV Sumber Makmur',
                'alamat' => 'Jl. Raya Barat No. 12',
                'telepon' => '081234567890',
                'id_cabang' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama' => 'PT Elektronik Jaya',
                'alamat' => 'Jl. Industri No. 88',
                'telepon' => '082345678901',
                'id_cabang' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            
        ]);
    }
    
}
