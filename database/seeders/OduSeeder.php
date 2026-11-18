<?php

namespace Database\Seeders;

use App\Models\Odu;
use Illuminate\Database\Seeder;

class OduSeeder extends Seeder
{
    /**
     * The 16 primary Odu Meji, in the traditional seniority order (Eji Ogbe first).
     * These 16 names are standardized across Ifa traditions.
     */
    private const MEJI = [
        'Ogbè', 'Ọ̀yẹ̀kú', 'Ìwòrì', 'Òdí',
        'Ìrosùn', 'Ọ̀wọ́nrín', 'Ọ̀bàrà', 'Ọ̀kànràn',
        'Ògúndá', 'Ọ̀sá', 'Ìká', 'Òtúrúpọ̀n',
        'Òtúrá', 'Ìrẹ̀tẹ̀', 'Ọ̀ṣẹ́', 'Òfún',
    ];

    /**
     * Seeds the 256 base Odu (16 Meji x 16 Ameji) and enriches them
     * with detailed summaries, binary marks, and recommendations.
     */
    public function run(): void
    {
        $number = 1;

        // 1. Ensure basic mechanical Odu entries exist
        foreach (self::MEJI as $i => $father) {
            foreach (self::MEJI as $j => $mother) {
                if ($i === $j) {
                    Odu::firstOrCreate(
                        ['number' => $number],
                        ['name' => "{$father} Méjì", 'category' => 'meji']
                    );
                } else {
                    Odu::firstOrCreate(
                        ['number' => $number],
                        ['name' => "{$father}-{$mother}", 'category' => 'ameji']
                    );
                }

                $number++;
            }
        }

        // 2. Load rich details from extracted JSON
        $jsonPath = database_path('seeders/odus_parsed.json');
        if (file_exists($jsonPath)) {
            $parsedOdus = json_decode(file_get_contents($jsonPath), true);
            foreach ($parsedOdus as $parsed) {
                $code = $parsed['code'];
                $parts = explode('.', $code);
                if (count($parts) !== 2) {
                    continue;
                }
                $X = (int) $parts[0];
                $Y = (int) $parts[1];

                $father = self::MEJI[$X - 1];
                if ($Y === 1) {
                    $mother = $father;
                } else {
                    $remaining = array_values(array_filter(self::MEJI, fn($m) => $m !== $father));
                    $mother = $remaining[$Y - 2];
                }

                $i = array_search($father, self::MEJI);
                $j = array_search($mother, self::MEJI);
                $dbNumber = ($i * 16) + $j + 1;

                $odu = Odu::where('number', $dbNumber)->first();
                if ($odu) {
                    $alternativeNames = [];
                    if (!empty($parsed['alt_name'])) {
                        $alternativeNames[] = $parsed['alt_name'];
                    }

                    $odu->update([
                        'alternative_names' => $alternativeNames,
                        'summary' => $parsed['summary'],
                        'ebos' => $parsed['ebos'],
                        'orisas' => $parsed['orisas'],
                        'binary_mark' => $parsed['binary_mark'],
                        'description' => implode("\n", $parsed['summary']),
                    ]);
                }
            }
        }
    }
}
