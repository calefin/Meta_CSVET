<?php

namespace Database\Seeders;

use App\Models\Supplier;
use App\Models\Supply;
use App\Models\SupplyPrice;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class ScrapedSuppliersCatalogSeeder extends Seeder
{
    /**
     * Run the database seeds for the scraped suppliers.
     */
    public function run(): void
    {
        $jsonPath = database_path('seeders/scraped_catalog.json');
        if (!File::exists($jsonPath)) {
            $this->command->warn("Seeder JSON file not found at: {$jsonPath}");
            return;
        }

        $items = json_decode(File::get($jsonPath), true);
        if (!$items) {
            $this->command->error("Invalid or empty JSON data in scraped_catalog.json.");
            return;
        }

        $suppliers = [];

        foreach ($items as $item) {
            $supplierName = $item['supplier'];
            $supplyName = trim($item['name']);
            $price = (float)$item['price'];

            // Clean up double spaces
            $supplyName = preg_replace('/\s+/', ' ', $supplyName);

            // 1. Get or create supplier
            if (!isset($suppliers[$supplierName])) {
                $suppliers[$supplierName] = Supplier::firstOrCreate(
                    ['name' => $supplierName],
                    [
                        'type' => 'materiais',
                        'notes' => 'Importado via script de raspagem web.',
                    ]
                );
            }
            $supplier = $suppliers[$supplierName];

            // 2. Find or create supply (case-insensitive name check)
            $supply = Supply::whereRaw('LOWER(name) = ?', [strtolower($supplyName)])->first();
            if (!$supply) {
                $supply = Supply::create([
                    'name' => $supplyName,
                    'category' => 'Artigos Religiosos',
                    'unit' => 'unidade',
                    'active' => true,
                ]);
            }

            // 3. Create price mapping
            SupplyPrice::firstOrCreate(
                [
                    'supply_id' => $supply->id,
                    'supplier_id' => $supplier->id,
                    'purchase_date' => now()->toDateString(),
                ],
                [
                    'unit_price' => $price,
                    'quantity' => 1.000,
                ]
            );
        }

        $this->command->info("Seeded " . count($items) . " supplies and prices successfully!");
    }
}
