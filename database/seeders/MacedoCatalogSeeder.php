<?php

namespace Database\Seeders;

use App\Models\Animal;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class MacedoCatalogSeeder extends Seeder
{
    /**
     * One-time real catalog data for this casa's animal supplier - not
     * part of the default install (run manually: php artisan db:seed
     * --class=MacedoCatalogSeeder), since it's this house's specific
     * data, not generic structural seed data like Odu/Ire-Ibi.
     */
    public function run(): void
    {
        $supplier = Supplier::firstOrCreate(
            ['name' => 'Macedo'],
            [
                'type' => 'animais',
                'contact' => '(11) 94758-7731',
                'notes' => 'Frete: R$ 30,00.',
            ]
        );

        $animals = [
            ['Frango branco', 33.00],
            ['Franga branca', 33.00],
            ['Frango de cor', 65.00],
            ['Franga de cor', 60.00],
            ['Galinha vermelha', 30.00],
            ['Pé seco', 15.00],
            ['Angola adulta', 160.00],
            ['Angola nova', 120.00],
            ['Angola branca', 280.00],
            ['Pato', 100.00],
            ['Pata', 85.00],
            ['Preá', 60.00],
            ['Peru', 280.00],
            ['Perua', 250.00],
            ['Marreco', 120.00],
            ['Marreca', 120.00],
            ['Pombo de cor', 40.00],
            ['Pombo claro', 50.00],
            ['Pombo branco', 80.00],
            ['Pintinho', 15.00],
            ['Codorna', 15.00],
            ['Garnizé', 60.00],
            ['Galinha caipira', 85.00],
            ['Frango caipira', 95.00],
            ['Galo branco', 80.00],
            ['Galo caipira', 190.00],
            ['Cabrito', 430.00, 'Preço a partir de.'],
            ['Cabra', 450.00, 'Preço a partir de.'],
            ['Porco', 360.00, 'Preço a partir de.'],
            ['Galinha preta', 85.00],
            ['Frango preto', 95.00],
            ['Ibi', 65.00],
            ['Coelho', 85.00],
        ];

        foreach ($animals as $row) {
            [$name, $price] = $row;
            $notes = $row[2] ?? null;

            $animal = Animal::firstOrCreate(
                ['name' => $name, 'supplier_id' => $supplier->id],
                ['unit' => 'unidade', 'notes' => $notes, 'active' => true]
            );

            $animal->prices()->firstOrCreate(
                ['supplier_id' => $supplier->id, 'purchase_date' => now()->toDateString()],
                ['unit_price' => $price]
            );
        }
    }
}
