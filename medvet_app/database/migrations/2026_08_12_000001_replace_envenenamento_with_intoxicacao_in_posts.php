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
        // Ordem importa: formas plurais e frases com artigo de gênero
        // precisam ser tratadas antes do termo singular genérico, senão
        // "envenenamentos" viraria "intoxicaçãos" em vez de "intoxicações".
        $replacements = [
            'O envenenamento' => 'A intoxicação',
            'o envenenamento' => 'a intoxicação',
            'Envenenamentos' => 'Intoxicações',
            'envenenamentos' => 'intoxicações',
            'Envenenamento' => 'Intoxicação',
            'envenenamento' => 'intoxicação',
        ];

        foreach (DB::table('posts')->select('id', 'title', 'summary', 'content')->get() as $post) {
            $title = strtr($post->title, $replacements);
            $summary = strtr($post->summary, $replacements);
            $content = strtr($post->content, $replacements);

            if ($title !== $post->title || $summary !== $post->summary || $content !== $post->content) {
                DB::table('posts')->where('id', $post->id)->update([
                    'title' => $title,
                    'summary' => $summary,
                    'content' => $content,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Edição textual não reversível de forma segura.
    }
};
