<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            OduSeeder::class,
            IreIbiTypeSeeder::class,
            LibraryCategorySeeder::class,
            CourseSeeder::class,
            MacedoCatalogSeeder::class,
            ScrapedSuppliersCatalogSeeder::class,
        ]);
    }
}
