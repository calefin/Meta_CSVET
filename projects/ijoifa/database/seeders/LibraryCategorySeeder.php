<?php

namespace Database\Seeders;

use App\Models\LibraryCategory;
use Illuminate\Database\Seeder;

class LibraryCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Rezas diárias', 'slug' => 'rezas-diarias', 'description' => 'Orações e rezas do dia a dia.'],
            ['name' => 'Técnicas de consulta', 'slug' => 'tecnicas-de-consulta', 'description' => 'Procedimentos de Obi e Osé Ifá.'],
            ['name' => 'Incantations', 'slug' => 'incantations', 'description' => 'Cânticos e incantations em inglês e Yorùbá.'],
            ['name' => 'Mitologia e terminologia', 'slug' => 'mitologia-e-terminologia', 'description' => 'Material de referência sobre mitologia e termos Yorùbá.'],
        ];

        foreach ($categories as $i => $category) {
            LibraryCategory::firstOrCreate(
                ['slug' => $category['slug']],
                [...$category, 'order' => $i]
            );
        }
    }
}
