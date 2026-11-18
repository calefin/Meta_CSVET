<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('systematic_reviews', function (Blueprint $table) {
            // Perguntas de apoio à triagem (PICO em português), cada uma ligada a um código de exclusão.
            $table->json('screening_checklist')->nullable()->after('highlight_terms');
        });
        Schema::table('systematic_review_decisions', function (Blueprint $table) {
            // Respostas do triador ao checklist naquele trabalho (sim / nao / incerto), por índice da pergunta.
            $table->json('checklist')->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('systematic_reviews', function (Blueprint $table) {
            $table->dropColumn('screening_checklist');
        });
        Schema::table('systematic_review_decisions', function (Blueprint $table) {
            $table->dropColumn('checklist');
        });
    }
};
