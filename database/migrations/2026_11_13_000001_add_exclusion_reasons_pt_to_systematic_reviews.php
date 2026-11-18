<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('systematic_reviews', function (Blueprint $table) {
            // Rótulo em português de cada código de exclusão ({"E1": "…"}), mostrado em destaque nos botões da triagem.
            // O código gravado no voto e o texto em inglês do protocolo (PRISMA/OSF) não mudam.
            $table->json('exclusion_reasons_pt')->nullable()->after('exclusion_reasons');
        });
    }

    public function down(): void
    {
        Schema::table('systematic_reviews', function (Blueprint $table) {
            $table->dropColumn('exclusion_reasons_pt');
        });
    }
};
