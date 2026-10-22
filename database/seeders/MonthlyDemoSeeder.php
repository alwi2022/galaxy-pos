<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class MonthlyDemoSeeder extends Seeder
{
    public function run()
    {
        $this->call(BusinessDemoSeeder::class);
    }
}
