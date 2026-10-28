<?php

namespace Database\Seeders;

use App\Modules\Core\Database\Seeders\CoreSeeder;
use App\Modules\Catalog\Database\Seeders\CatalogSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CoreSeeder::class,
            CatalogSeeder::class,
        ]);
    }
}