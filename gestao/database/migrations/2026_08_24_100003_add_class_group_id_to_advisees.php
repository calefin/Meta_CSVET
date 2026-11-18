<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dá a advisees (orientandos) um vínculo opcional com uma turma
     * (class_groups) — sem isso não existe roster nenhum pra importar no
     * ProvaGen na hora de gerar prova (ver server/_core/gestaoImport.ts).
     */
    public function up(): void
    {
        Schema::table('advisees', function (Blueprint $table) {
            $table->foreignId('class_group_id')->nullable()->after('user_id')->constrained('class_groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('advisees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('class_group_id');
        });
    }
};
