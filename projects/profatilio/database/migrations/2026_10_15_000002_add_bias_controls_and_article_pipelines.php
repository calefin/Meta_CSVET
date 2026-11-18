<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Controles de viés da triagem (amostra aleatória do piloto, ordem por
 * triador, auditoria do desempate) e pipeline de submissão por tipo de
 * artigo no processo de revisão.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('systematic_review_records', function (Blueprint $table) {
            // Chave aleatória fixa: define a amostra do piloto e a base da ordem embaralhada.
            $table->unsignedInteger('random_key')->default(0)->after('seq');
            // Decisão de consenso dos alunos, guardada para auditar se o professor a sobrepôs.
            $table->string('ta_consensus', 10)->nullable()->after('ta_decision');
            $table->string('ft_consensus', 10)->nullable()->after('ft_decision');
            $table->index(['systematic_review_id', 'random_key']);
        });

        DB::table('systematic_review_records')->orderBy('id')->each(function ($row) {
            DB::table('systematic_review_records')->where('id', $row->id)->update(['random_key' => random_int(1, 2_000_000_000)]);
        });

        Schema::table('article_reviews', function (Blueprint $table) {
            $table->string('article_type', 40)->nullable()->after('work_type');
            $table->json('submission_checklist')->nullable()->after('article_type');
        });
    }

    public function down(): void
    {
        Schema::table('article_reviews', function (Blueprint $table) {
            $table->dropColumn(['article_type', 'submission_checklist']);
        });
        Schema::table('systematic_review_records', function (Blueprint $table) {
            $table->dropIndex(['systematic_review_id', 'random_key']);
            $table->dropColumn(['random_key', 'ta_consensus', 'ft_consensus']);
        });
    }
};
