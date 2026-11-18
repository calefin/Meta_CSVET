<?php

namespace Database\Seeders;

use App\Models\IreIbiType;
use Illuminate\Database\Seeder;

class IreIbiTypeSeeder extends Seeder
{
    /**
     * Catalogo base conforme especificado no documento de requisitos (secao 7.2).
     */
    public function run(): void
    {
        $types = [
            ['classification' => 'ire', 'name' => 'Ire Àikú', 'description' => 'Vida longa / saude'],
            ['classification' => 'ire', 'name' => 'Ire Owó', 'description' => 'Prosperidade financeira'],
            ['classification' => 'ire', 'name' => 'Ire Ọmọ', 'description' => 'Filhos / descendencia'],
            ['classification' => 'ire', 'name' => 'Ire Ìfẹ̀', 'description' => 'Amor / relacionamento'],
            ['classification' => 'ire', 'name' => 'Ire Àṣeyọrí', 'description' => 'Vitoria / sucesso'],
            ['classification' => 'ire', 'name' => 'Ire Ilé', 'description' => 'Lar / familia'],
            ['classification' => 'ire', 'name' => 'Ire Orí', 'description' => 'Destino favoravel'],
            ['classification' => 'ire', 'name' => 'Ire Àgbàdo', 'description' => 'Colheita / abundancia'],
            ['classification' => 'ibi', 'name' => 'Ibi Àrùn', 'description' => 'Doenca'],
            ['classification' => 'ibi', 'name' => 'Ibi Òfò', 'description' => 'Perda material'],
            ['classification' => 'ibi', 'name' => 'Ibi Ejọ', 'description' => 'Conflito / processo judicial'],
            ['classification' => 'ibi', 'name' => 'Ibi Ikú', 'description' => 'Morte / risco de vida'],
            ['classification' => 'ibi', 'name' => 'Ibi Ọ̀fọ̀ Owó', 'description' => 'Ruina financeira'],
            ['classification' => 'ibi', 'name' => 'Ibi Àṣejù', 'description' => 'Excesso / descontrole'],
            ['classification' => 'ibi', 'name' => 'Ibi Ọ̀tá', 'description' => 'Inimigos / traicao'],
            ['classification' => 'ibi', 'name' => 'Ibi Àjọ', 'description' => 'Sociedades malignas / feitico'],
        ];

        foreach ($types as $type) {
            IreIbiType::firstOrCreate(
                ['name' => $type['name']],
                ['classification' => $type['classification'], 'description' => $type['description'], 'active' => true]
            );
        }
    }
}
