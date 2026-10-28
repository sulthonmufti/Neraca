<?php

namespace Database\Seeders;

use App\Modules\Core\Database\Seeders\CoreSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CoreSeeder::class,
        ]);
    }
}