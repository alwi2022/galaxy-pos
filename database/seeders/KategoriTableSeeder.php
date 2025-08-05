<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class KategoriTableSeeder extends Seeder
{
    public function run()
    {
        $kategori = [
            'Laptop', 'HP', 'Printer', 'Mouse', 'Keyboard',
            'Monitor', 'Speaker', 'Headset', 'Webcam', 'Scanner',
            'UPS', 'Power Supply', 'Hard Disk', 'SSD', 'RAM',
            'CPU', 'Motherboard', 'VGA', 'Sound Card',
            'Network Card', 'Modem', 'Router',
        ];

        foreach ($kategori as $nama) {
            DB::table('kategori')->insert([
                'nama_kategori' => $nama,
                'id_cabang' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
