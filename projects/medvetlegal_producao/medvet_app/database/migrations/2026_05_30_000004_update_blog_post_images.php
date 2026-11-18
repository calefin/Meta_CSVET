<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $imageMappings = [
            'como-comprovar-intoxicacao-por-chumbinho-carbamatos-processos-judiciais' => 'https://images.unsplash.com/photo-1532187863486-abf9d39d66e8?q=80&w=600&auto=format&fit=crop',
            'diferenca-pericia-assistencia-tecnica-laudo-veterinario' => 'https://images.unsplash.com/photo-1453728013993-6d66e9c9123a?q=80&w=600&auto=format&fit=crop',
            'erro-veterinario-como-provar-negligencia-imprudencia-impericia' => 'https://images.unsplash.com/photo-1584132967334-10e028bd69f7?q=80&w=600&auto=format&fit=crop',
            'necropsia-veterinaria-forense-quando-e-necessaria-como-solicitar' => 'https://images.unsplash.com/photo-1518152006812-edab29b069ac?q=80&w=600&auto=format&fit=crop',
            'envenenamento-de-animais-o-que-fazer-como-agir-juridicamente' => 'https://images.unsplash.com/photo-1601758228041-f3b2795255f1?q=80&w=600&auto=format&fit=crop',
            'laudo-pericial-vs-parecer-tecnico-entenda-a-diferenca-processual' => 'https://images.unsplash.com/photo-1589829545856-d10d557cf95f?q=80&w=600&auto=format&fit=crop',
            'maus-tratos-a-animais-como-provar-perante-a-lei' => 'https://images.unsplash.com/photo-1544568100-847a948585b9?q=80&w=600&auto=format&fit=crop',
            'exumacao-de-animais-e-possivel-realizar-pericia-apos-o-enterro' => 'https://images.unsplash.com/photo-1448375240586-882707db888b?q=80&w=600&auto=format&fit=crop',
            'responsabilidade-civil-de-clinicas-veterinarias-em-erros-profissionais' => 'https://images.unsplash.com/photo-1581888227599-779811939961?q=80&w=600&auto=format&fit=crop',
            'como-age-a-toxicologia-forense-na-identificacao-de-venenos-em-pets' => 'https://images.unsplash.com/photo-1576086213369-97a306d36557?q=80&w=600&auto=format&fit=crop',
            'assistente-tecnico-veterinario-o-defensor-da-verdade-cientifica-em-juizo' => 'https://images.unsplash.com/photo-1521791136064-7986c2920216?q=80&w=600&auto=format&fit=crop',
            'protocolos-de-pericia-veterinaria-em-casos-de-maus-tratos' => 'https://images.unsplash.com/photo-1516733725897-1aa73b87c8e8?q=80&w=600&auto=format&fit=crop'
        ];

        foreach ($imageMappings as $slug => $imageUrl) {
            DB::table('posts')
                ->where('slug', $slug)
                ->update(['image' => $imageUrl]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
