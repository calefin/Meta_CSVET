<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Protocolo v1.1 (IA declarada):
 * - triagem de título/resumo priorizada por aprendizado ativo, com regra de
 *   parada e checagem aleatória do que ninguém leu (ta_qc);
 * - pré-extração por modelo de linguagem só para o Revisor 1, com o valor
 *   original guardado e a conferência registrada (sr_extractions.ai_*).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('systematic_reviews', function (Blueprint $table) {
            $table->json('ta_qc')->nullable();          // rodadas da checagem aleatória
            $table->json('ai_extraction')->nullable();  // modelo, versão, prompt fixo e datas de uso
        });

        Schema::table('sr_extractions', function (Blueprint $table) {
            $table->json('ai_prefill')->nullable();     // valores propostos pelo modelo (originais)
            $table->timestamp('ai_prefilled_at')->nullable();
            $table->timestamp('ai_verified_at')->nullable();
            $table->json('ai_changes')->nullable();     // resumo: propostos, mantidos, alterados, apagados
        });
    }

    public function down(): void
    {
        Schema::table('sr_extractions', function (Blueprint $table) {
            $table->dropColumn(['ai_prefill', 'ai_prefilled_at', 'ai_verified_at', 'ai_changes']);
        });
        Schema::table('systematic_reviews', function (Blueprint $table) {
            $table->dropColumn(['ta_qc', 'ai_extraction']);
        });
    }
};
