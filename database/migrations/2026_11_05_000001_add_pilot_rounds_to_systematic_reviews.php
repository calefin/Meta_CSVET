<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('systematic_reviews', function (Blueprint $table) {
            // Rodadas de calibração já fechadas (protocolo: kappa < 0,60 → discutir e triar NOVA amostra-piloto).
            $table->json('pilot_rounds')->nullable()->after('pilot_released_at');
        });
    }

    public function down(): void
    {
        Schema::table('systematic_reviews', function (Blueprint $table) {
            $table->dropColumn('pilot_rounds');
        });
    }
};
