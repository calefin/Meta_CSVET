<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('systematic_reviews', function (Blueprint $table) {
            // Quadro "Atenção neste projeto" na tela da triagem (erros mais comuns). Ajuda de leitura, não muda critério.
            $table->text('screening_tips')->nullable()->after('screening_checklist');
        });
    }

    public function down(): void
    {
        Schema::table('systematic_reviews', function (Blueprint $table) {
            $table->dropColumn('screening_tips');
        });
    }
};
