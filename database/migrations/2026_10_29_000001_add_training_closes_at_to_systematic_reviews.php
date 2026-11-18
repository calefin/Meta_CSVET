<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('systematic_reviews', function (Blueprint $table) {
            // Treino: prazo de fechamento. Depois dele, os alunos veem os resultados e o gabarito.
            $table->timestamp('training_closes_at')->nullable()->after('is_training');
        });
    }

    public function down(): void
    {
        Schema::table('systematic_reviews', function (Blueprint $table) {
            $table->dropColumn('training_closes_at');
        });
    }
};
